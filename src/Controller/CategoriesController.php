<?php
declare(strict_types=1);
// file ~/Sites/blog/src/Controller/CategoriesController.php

namespace App\Controller;

use App\Entity\Categories;
use App\Entity\User;
use App\Repository\CategoriesRepository;
use App\Repository\PostsRepository;
use App\Service\KalimaService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * this controller manages everything related to viewing blog categories,
 * listing category items, and displaying posts assigned to specific categories.
 */
#[Route('/categories')]
class CategoriesController extends AbstractController
{
    /**
     * @param CategoriesRepository $categoriesRepository repository managing category entities.
     * @param PostsRepository $postsRepository repository managing post entities.
     * @param LoggerInterface $mainLogger standard logger.
     */
    public function __construct(
        private CategoriesRepository $categoriesRepository,
        private PostsRepository      $postsRepository,
        private LoggerInterface      $mainLogger,
    ) {}

    /**
     * lists all categories regardless of language.
     */
    #[Route('/', name: 'app_categories', methods: ['GET'])]
    public function categories(Request $request, KalimaService $kalimaService): Response
    {
        $language = $request->getLocale();
        $currentUser = $this->getUser();

        $currentPage = (int) $request->get('page', 1);

        // dynamic results per page from user settings or fallback:
        $defaultLimit = ($currentUser instanceof User) ? $currentUser->getResultsPerPage() : 10;
        $resultsPerPage = (int) $request->get('limit', $defaultLimit);

        ////////////////////////////////////////////////////////////////////////
        /// fetch active categories across all languages by passing null for language

        $paginationData = $this->categoriesRepository->getCategoriesPaginated(
            currentPage: $currentPage,
            resultsPerPage: $resultsPerPage,
            sortOrder: 'ASC',
            language: null
        );

        $categories = $paginationData['paginator'];
        $totalCount = count($categories);
        $totalPages = (int) ceil($totalCount / $resultsPerPage);

        ////////////////////////////////////////////////////////////////////////
        /// render list layout

        return $this->render('categories/categories.html.twig', [
            'categories' => $categories,
            'language' => $language,
            'locale' => $language,
            'total_count' => $totalCount,
            'total_pages' => $totalPages,
            'current_page' => $currentPage,
            'query_params' => [
                'limit' => $resultsPerPage,
            ],
        ]);
    }

    #[Route('/new', name: 'app_category_new', methods: ['GET', 'POST'])]
    public function new(): Response
    {
        return $this->render('categories/category_wizard.html.twig', [
            'category' => null,
        ]);
    }

    /**
     * shows a single category based on its unique slug, along with its associated posts.
     */
    #[Route('/{slug}', name: 'app_category', methods: ['GET'])]
    public function category(string $slug, Request $request): Response
    {
        ////////////////////////////////////////////////////////////////////////
        /// 1. fetch category entity metadata matching target slug

        $category = $this->categoriesRepository->findOneBy(['slug' => $slug]);

        if (!$category) {
            throw $this->createNotFoundException('the requested category does not exist.');
        }

        ////////////////////////////////////////////////////////////////////////
        /// 2. render layout

        return $this->render('categories/category.html.twig', [
            'category' => $category,
            'language' => $category->getLanguage(),
        ]);
    }
}
