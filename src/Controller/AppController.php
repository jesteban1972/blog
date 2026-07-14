<?php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/')]

class AppController extends AbstractController
{
    public function __construct(
        private LoggerInterface $mainLogger,
        private RequestStack $requestStack,
        private TranslatorInterface $translator,
        private string $authBaseUrl,
        private string $callbackUrl,
        private string $appSecret,
    ) {}
}
