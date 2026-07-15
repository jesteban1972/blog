<?php
declare(strict_types=1);
// file ~/Sites/blog/src/EventSubscriber/WelcomeMessageSubscriber.php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Bundle\SecurityBundle\Security;

class WelcomeMessageSubscriber implements EventSubscriberInterface
{
    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if ($request->attributes->get('_route') !== 'app_home') {
            return;
        }

        $user = $this->security->getUser();

        /**
         * we create a unique key:
         * - 'show_welcome_anonymous' for visitors
         * - 'show_welcome_user_123' for logged-in users
         */
        $sessionKey = $user ? 'show_welcome_user_' . $user->getId() : 'show_welcome_anonymous';
        $session = $request->getSession();

        if (!$session->has($sessionKey)) {
            $request->attributes->set('show_welcome', true);
            $session->set($sessionKey, true);
        }
    }
}
