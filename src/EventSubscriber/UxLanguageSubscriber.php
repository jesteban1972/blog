<?php
declare(strict_types=1);
// file ~/Sites/blog/src/EventSubscriber/UxLanguageSubscriber.php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * UxLanguageSubscriber is the universal linguistic anchor. it ensures that every request across the pendoncete.org
 * ecosystem respects the user's language preference by synchronizing the global cookie, the local session, and the
 * Symfony request context.
 *
 * this class is a "universal blueprint" for handling UX languages in all apps within pendoncete.org domain.
 */
class UxLanguageSubscriber implements EventSubscriberInterface
{
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
        /// 1. (identity) check the URL prefix first (for SEO/crawlers), then the global cookie.
        /// if either is found, they override the session preference

        $urlLocale = $request->attributes->get('_locale');
        $cookieLocale = $request->cookies->get('pendoncete_ux_language');

        ////////////////////////////////////////////////////////////////////////////////
        /// 2. (context) resolution hierarchy

        /**
         * the resolution hierarchy follows this priority:
         * 1. language cookie vs URL: if the cookie exists and differs from the URL, the cookie wins.
         * 2. URL Path (_locale): mandatory for SEO indexing and explicit user typing.
         * 3. session (_locale): the stateful fallback.
         * 4. 'en': the absolute fallback.
         */

        if ($cookieLocale && $cookieLocale !== $urlLocale) {

            $finalLocale = $cookieLocale;

        } elseif ($urlLocale) {

            $finalLocale = $urlLocale;

        } else {

            $finalLocale = $session?->get('_locale') ?: 'en';
        }

        ////////////////////////////////////////////////////////////////////////////////
        /// 3. (synchronization) set the locale for the current request execution (used by the translator)

        $request->setLocale($finalLocale);

        // inject the locale into the request attributes (necessary for the router and UrlGenerator
        // to produce localized paths correctly):
        $request->attributes->set('_locale', $finalLocale);

        ////////////////////////////////////////////////////////////////////////////////
        /// 4. (persistence) ensure the local session matches the global preference to prevent "flickering":

        if ($session && $session->get('_locale') !== $finalLocale) {
            $session->set('_locale', $finalLocale);
        }
    }

    public static function getSubscribedEvents(): array
    {
        /**
         * priority 20: this must run after the SessionListener (which starts the session) but before the LocaleListener
         * and the Translator, so that the correct language is ready when the first translation is called.
         */

        return [KernelEvents::REQUEST => [['onKernelRequest', 20]]];
    }
}
