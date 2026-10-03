<?php
declare(strict_types=1);
// file ~/Sites/blog/src/Controller/CategoriesController.php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\User;
use App\Form\CategoryType;
use App\Repository\CategoriesRepository;
use App\Repository\PostsRepository;
use App\Service\EikonService;
use App\Service\KalimaService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * this controller manages everything related to viewing blog categories,
 * listing category items, and displaying posts assigned to specific categories.
 */
#[Route('/categories')]
class CategoriesController extends AbstractController
{
    public function __construct(
        private readonly CategoriesRepository $categoriesRepository,
        private readonly PostsRepository $postsRepository,
        private readonly KalimaService $kalimaService,
        private readonly EikonService $eikonService,
        private readonly LoggerInterface $mainLogger,
    ) {}

    /**
     * lists all categories regardless of language.
     */
    #[Route('/', name: 'app_categories', methods: ['GET'])]
    public function categories(Request $request): Response
    {
        $language = $request->getLocale();
        $currentUser = $this->getUser();

        $currentPage = (int) $request->get('page', 1);

        $defaultLimit = ($currentUser instanceof User) ? $currentUser->getResultsPerPage() : 10;
        $resultsPerPage = (int) $request->get('limit', $defaultLimit);

        $paginationData = $this->categoriesRepository->getCategoriesPaginated(
            currentPage: $currentPage,
            resultsPerPage: $resultsPerPage,
            sortOrder: 'ASC',
            language: null
        );

        $categories = $paginationData['paginator'];
        $totalCount = count($categories);
        $totalPages = (int) ceil($totalCount / $resultsPerPage);

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

    /**
     * admin action: handles creating a new category.
     */
    #[Route('/new', name: 'app_category_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request, EntityManagerInterface $em, KalimaService $kalimaService): Response
    {
        $category = new Category();
        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (empty($category->getSlug()) && $category->getName()) {
                $category->setSlug($kalimaService->slugificate($category->getName()));
            } else {
                $category->setSlug($kalimaService->slugificate((string) $category->getSlug()));
            }

            $em->persist($category);
            $em->flush();

            $this->addFlash('success', 'categories.flash.created_successfully');

            return $this->redirectToRoute('app_categories');
        }

        return $this->render('categories/category_form.html.twig', [
            'category' => $category,
            'form' => $form->createView(),
        ]);
    }

    /**
     * admin action: handles editing category details.
     */
    #[Route('/{slug}/edit', name: 'app_category_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(string $slug, Request $request, EntityManagerInterface $em, KalimaService $kalimaService): Response
    {
        $category = $this->categoriesRepository->findOneBy(['slug' => $slug]);

        if (!$category) {
            throw $this->createNotFoundException('the requested category does not exist.');
        }

        $form = $this->createForm(CategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (empty($category->getSlug()) && $category->getName()) {
                $category->setSlug($kalimaService->slugificate($category->getName()));
            } else {
                $category->setSlug($kalimaService->slugificate((string) $category->getSlug()));
            }

            $em->flush();

            $this->addFlash('success', 'categories.flash.updated_successfully');

            return $this->redirectToRoute('app_categories');
        }

        return $this->render('categories/category_form.html.twig', [
            'category' => $category,
            'form' => $form->createView(),
        ]);
    }

    /**
     * public detail page: displays category info & associated posts.
     */
    #[Route('/{slug}', name: 'app_category', methods: ['GET'])]
    public function category(string $slug, Request $request): Response
    {
        $category = $this->categoriesRepository->findOneBy(['slug' => $slug]);

        if (!$category) {
            throw $this->createNotFoundException('the requested category does not exist.');
        }

        $currentUser = $this->getUser();
        $currentPage = max(1, $request->query->getInt('page', 1));
        $defaultLimit = ($currentUser instanceof User) ? $currentUser->getResultsPerPage() : 10;
        $resultsPerPage = (int) $request->get('limit', $defaultLimit);

        // count total posts for this category via copulationes
        $totalCount = $this->postsRepository->countByCategory($category);
        $totalPages = (int) ceil($totalCount / $resultsPerPage);

        // fetch current page slice
        $posts = $this->postsRepository->findByCategoryPaginated($category, $currentPage, $resultsPerPage);

        foreach ($posts as $post) {
            $post->excerpt = $this->kalimaService->fetchExcerpt($post);
            $post->thumbnails = $this->eikonService->extractThumbnails($post);
        }

        return $this->render('categories/category.html.twig', [
            'category' => $category,
            'posts' => $posts,
            'total_count' => $totalCount,
            'total_pages' => $totalPages,
            'current_page' => $currentPage,
            'query_params' => [
                'limit' => $resultsPerPage,
            ],
            'language' => $request->getLocale(),
        ]);
    }
}
