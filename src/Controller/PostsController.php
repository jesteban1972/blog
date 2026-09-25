<?php
declare(strict_types=1);
// file ~/Sites/blog/src/Controller/PostsController.php

namespace App\Controller;

use App\Entity\Post;
use App\Entity\User;
use App\Repository\CommunityCommentsRepository;
use App\Repository\PostsRepository;
use App\Service\KalimaService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * this controller manages everything related to viewing blog posts, listing categories,
 * and displaying individual articles along with their comment sections.
 */
#[Route('/posts')]
class PostsController extends AbstractController
{
    /**
     * @param PostsRepository $postsRepository repository managing post entities.
     * @param CommunityCommentsRepository $commentsRepository repository managing comments.
     * @param LoggerInterface $mainLogger standard logger.
     */
    public function __construct(
        private PostsRepository             $postsRepository,
        private CommunityCommentsRepository $commentsRepository,
        private LoggerInterface             $mainLogger,
    ) {}

    /**
     * lists all posts regardless of language.
     */
    #[Route('/', name: 'app_posts', methods: ['GET'])]
    public function posts(Request $request, KalimaService $kalimaService): Response
    {
        $language = $request->getLocale();
        $currentUser = $this->getUser();

        $currentPage = (int) $request->get('page', 1);

        // dynamic results per page from user settings or fallback:
        $defaultLimit = ($currentUser instanceof User) ? $currentUser->getResultsPerPage() : 10;
        $resultsPerPage = (int) $request->get('limit', $defaultLimit);

        ////////////////////////////////////////////////////////////////////////
        /// fetch active posts across all languages by passing null for language

        $paginationData = $this->postsRepository->getPostsPaginated(
            currentPage: $currentPage,
            resultsPerPage: $resultsPerPage,
            sortOrder: 'DESC',
            language: null
        );

        $posts = $paginationData['paginator'];
        $totalCount = count($posts); // or $paginationData['totalCount'] if returned by repo
        $totalPages = (int) ceil($totalCount / $resultsPerPage);

        ////////////////////////////////////////////////////////////////////////
        /// attach excerpts and thumbnails dynamically to each post object

        foreach ($posts as $post) {
            $post->excerpt = $kalimaService->fetchExcerpt($post);
            $post->thumbnails = $kalimaService->extractThumbnails($post);
        }

        ////////////////////////////////////////////////////////////////////////
        /// render list layout

        return $this->render('posts/posts.html.twig', [
            'posts' => $posts,
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

    #[Route('/{id}/preview', name: 'app_post_preview', methods: ['GET'])]
    public function preview(Post $post, KalimaService $kalimaService): Response
    {
        dd('kk'); // TODO: never triggers
        return $this->render('posts/post_preview.html.twig', [
            'id' => $post->getId(),
            'title' => $post->getTitle(),
            'slug' => $post->getSlug(),
            'date' => $post->getCreatedAt()->format('d/m/Y'),
            'excerpt' => $kalimaService->fetchExcerpt($post),
            'thumbnails' => $kalimaService->extractThumbnails($post),
            'language' => $post->getLanguage(),
            'diffusio' => $post->getDiffusio(),
            'category' => $post->getCategory(),
        ]);
    }

    #[Route('/new', name: 'app_post_new', methods: ['GET', 'POST'])]
    public function new(): Response
    {
        return $this->render('posts/post_wizard.html.twig', [
            'post' => null,
        ]);
    }

    /**
     * shows a single post based on its unique slug, along with its comment tree.
     */
    #[Route('/{slug}', name: 'app_post', methods: ['GET'])]
    public function post(string $slug, Request $request): Response
    {
        ////////////////////////////////////////////////////////////////////////
        /// 1. fetch post entity metadata matching target slug

        $post = $this->postsRepository->findOneBySlugWithCategory($slug);

        if (!$post) {
            throw $this->createNotFoundException('the requested post does not exist.');
        }

        ////////////////////////////////////////////////////////////////////////
        /// 2. fetch community comment objects to allow proxy auto-initialization

        $comments = $this->commentsRepository->findCommentsByPostId((int) $post->getId());


        ////////////////////////////////////////////////////////////////////////
        /// 3. render layout

        return $this->render('posts/post.html.twig', [
            'post' => $post,
            'comments' => $comments,
            'language' => $post->getLanguage(),
        ]);
    }
}
