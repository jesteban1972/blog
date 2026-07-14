<?php

namespace App\Controller;

use App\Service\CookieService;
use App\Service\JwtDecoderService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Routing\Attribute\Route;


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

    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        // force the lazy firewall to hydrate the user:
        $user = $this->getUser();

        return $this->render('app/index.html.twig');
    }
}
