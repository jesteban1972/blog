<?php
declare(strict_types=1);
// file ~/Sites/blog/src/Controller/PostsController.php

namespace App\Controller;

use App\Entity\Post;
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
     * lists posts filtered by the active language.
     */
    #[Route('/', name: 'app_posts', methods: ['GET'])]
    public function posts(Request $request): Response
    {
        $language = $request->getLocale(); // e.g. 'en', 'es'

        ////////////////////////////////////////////////////////////////////////
        /// fetch active posts for the current language using the paginator configuration

        $paginationData = $this->postsRepository->getPostsPaginated(
            currentPage: 1,
            resultsPerPage: 25,
            sortOrder: 'DESC',
            language: $language
        );

        ////////////////////////////////////////////////////////////////////////
        /// render list layout

        return $this->render('posts/posts.html.twig', [
            'posts' => $paginationData['paginator'],
            'language' => $language,
            'locale' => $language, // fallback for legacy base context expecting locale
        ]);
    }

    #[Route('/{id}/preview', name: 'app_post_preview', methods: ['GET'])]
    public function preview(Post $post, KalimaService $kalimaService): Response
    {
        return $this->render('posts/post_preview.html.twig', [
            'id' => $post->getId(),
            'title' => $post->getTitle(),
            'slug' => $post->getSlug(),
            'date' => $post->getCreatedAt()->format('d/m/Y'),
            'excerpt' => $kalimaService->fetchExcerpt($post),
            'language' => $post->getLanguage(),
            'diffusio' => $post->getDiffusio(),
            'category' => $post->getCategory(),
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
