<?php
declare(strict_types=1);
// file ~/Sites/blog/src/EventListener/CookieListener.php

namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;

class CookieListener
{
    #[AsEventListener(event: KernelEvents::RESPONSE)]
    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if ($request->attributes->has('_set_cookie')) {
            $event->getResponse()->headers->setCookie($request->attributes->get('_set_cookie'));
        }
    }
}
