<?php
declare(strict_types=1);
// file ~/Sites/blog/src/EventSubscriber/UxLanguageSubscriber.php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * UxLanguageSubscriber is the universal linguistic anchor. it ensures that every request across the pendoncete.org
 * ecosystem respects the user's language preference by synchronizing the global cookie, the local session, and the
 * Symfony request context.
 */
class UxLanguageSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private string $cookieDomain
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        ////////////////////////////////////////////////////////////////////////////////
        /// 0. (guard) skip sitemap to prevent session/cookie interference

        if ($request->attributes->get('_route') === 'app_sitemap') {
            return;
        }

        $session = $request->hasSession() ? $request->getSession() : null;

        ////////////////////////////////////////////////////////////////////////////////
        /// 1. (identity) retrieve sources of truth

        $cookieLocale = $request->cookies->get('pendoncete_ux_language');

        /**
         * Check the TokenStorage to see if there is an authenticated user in the current request context.
         */
        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();

        $userLocale = null;
        if (is_object($user)) {
            if (method_exists($user, 'getUxLanguage')) {
                $userLocale = $user->getUxLanguage();
            } elseif (method_exists($user, 'getLanguage')) {
                $userLocale = $user->getLanguage();
            }
        }

        ////////////////////////////////////////////////////////////////////////////////
        /// 2. (context) resolution hierarchy
        ///
        /// Priority:
        /// 1. Explicit UI cookie (user changed language via dropdown/UI)
        /// 2. Authenticated user preference (DB setting or JWT claim)
        /// 3. Session fallback
        /// 4. Default fallback 'en'

        if ($cookieLocale) {
            $finalLocale = $cookieLocale;
        } elseif ($userLocale) {
            $finalLocale = $userLocale;
        } else {
            $finalLocale = $session?->get('_locale') ?: 'en';
        }

        ////////////////////////////////////////////////////////////////////////////////
        /// 3. (synchronization) set locale for translator and router

        $request->setLocale($finalLocale);
        $request->attributes->set('_locale', $finalLocale);

        ////////////////////////////////////////////////////////////////////////////////
        /// 4. (persistence) ensure local session matches global preference

        if ($session && $session->get('_locale') !== $finalLocale) {
            $session->set('_locale', $finalLocale);
        }
    }

    /**
     * Runs before the HTML response is returned to the browser.
     * Ensures the global 'pendoncete_ux_language' cookie matches the active locale.
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        $currentLocale = $request->getLocale();
        $cookieLocale = $request->cookies->get('pendoncete_ux_language');

        // Only update the cookie on response if the active request locale is valid and non-empty
        if ($currentLocale && $currentLocale !== $cookieLocale) {
            $response->headers->setCookie(
                Cookie::create('pendoncete_ux_language')
                    ->withValue($currentLocale)
                    ->withDomain($this->cookieDomain)
                    ->withExpires(new \DateTime('+1 year'))
                    ->withPath('/')
                    ->withHttpOnly(false)
                    ->withSecure($request->isSecure()) // Match HTTP/HTTPS
                    ->withSameSite(Cookie::SAMESITE_LAX)
            );
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 20]],
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }
}
