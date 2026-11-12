<?php

namespace App\Controller;

use App\Entity\Community\User as CommunityUser;
use App\Entity\User;
use App\Repository\UsersRepository;
use App\Service\CookieService;
use App\Service\JwtDecoderService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Routing\Attribute\Route;


#[Route('/')]
class AppController extends AbstractController
{
    public function __construct(
        private LoggerInterface $mainLogger,
        private RequestStack $requestStack,
        private TranslatorInterface $translator,
    ) {}

    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        // force the lazy firewall to hydrate the user:
        $user = $this->getUser();

        //dd($user);

        return $this->render('app/homepage.html.twig');
    }

    #[Route('/info', name: 'app_info')]
    public function info(): Response
    {
        return $this->render('app/info.html.twig');
    }


    ////////////////////////////////////////////////////////////////////////////////
    /// sitemap

    // NOTE: route explicitly defined in config/routes.yaml to ensure it bypasses locale prefixes.
    // #[Route('/sitemap.xml', name: 'app_sitemap', defaults: ['_format' => 'xml'])]
    public function sitemap(): Response
    {
        $locales = ['en', 'es', 'el'];

        $staticRoutes = [
            'app_home',
            'app_info',
            'app_posts',
            'app_categories',
        ];

        $groupedUrls = [];

        foreach ($staticRoutes as $routeName) {
            $translations = [];
            foreach ($locales as $locale) {
                // generates localized absolute URLs automatically
                $translations[$locale] = $this->generateUrl(
                    $routeName,
                    ['_locale' => $locale],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );
            }

            $groupedUrls[] = [
                'loc' => $translations['en'], // canonical (English/No-prefix)
                'translations' => $translations,
                'priority' => '0.8',
                'lastmod' => date('Y-m-d'),
            ];
        }

        return new Response(
            content: $this->renderView(
                view: 'app/sitemap.xml.twig',
                parameters: [
                    'grouped_urls' => $groupedUrls,
                ]
            ),
            status: Response::HTTP_OK,
            headers: ['Content-Type' => 'text/xml; charset=UTF-8']
        );
    }


    ////////////////////////////////////////////////////////////////////////////////
    /// llms.txt & llms-full.txt for AI crawlers

    public function llmsTxt(string $_locale = 'en'): Response // $_locale matches Symfony's reserved '_locale' request attribute for automatic argument injection
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Madrid'));

        // fetch dynamic metrics from your repository or service here if needed
        // $metrics = $this->annalesRepository->getLiveMetrics();

        return new Response(
            content: $this->renderView(
                view: sprintf('docs/%s/llms.txt.twig', $_locale),
                parameters: [
                    '_locale' => $_locale,
                    'generated_at' => $now->format('Y-m-d H:i:s P'),
                    // 'metrics' => $metrics,
                ]
            ),
            status: Response::HTTP_OK,
            headers: ['Content-Type' => 'text/plain; charset=UTF-8']
        );
    }

    public function llmsFullTxt(
        UsersRepository $usersRepository,
        //MetricsService $metricsService,
        string $_locale = 'en'  // $_locale matches Symfony's reserved '_locale' request attribute for automatic argument injection
    ): Response {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Madrid'));

        $pendoncete = $usersRepository->find(User::PENDONCETE);
//        $metrics = $pendoncete ? $metricsService->getUserMetrics($pendoncete) : [
//            'practica_amount' => 0,
//            'amores_amount' => 0,
//        ];

        return new Response(
            content: $this->renderView(
                view: sprintf('docs/%s/llms-full.txt.twig', $_locale),
                parameters: [
                    '_locale' => $_locale,
                    'generated_at' => $now->format('Y-m-d H:i:s P'),
                    //'metrics' => $metrics,
                ],
            ),
            status: Response::HTTP_OK,
            headers: ['Content-Type' => 'text/plain; charset=UTF-8']
        );
    }
}
