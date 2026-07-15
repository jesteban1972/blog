<?php
declare(strict_types=1);
// file ~/Sites/blog/src/Controller/PostsController.php

namespace App\Controller;

use Doctrine\DBAL\Connection;
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
     * @param Connection $connection dbal connection to run raw queries against our blog schema.
     * @param LoggerInterface $mainLogger standard logger.
     */
    public function __construct(
        private Connection      $connection,
        private LoggerInterface $mainLogger,
    ) {}

    /**
     * lists posts filtered by the active locale.
     */
    #[Route('/', name: 'app_posts', methods: ['GET'])]
    public function posts(Request $request): Response
    {
        $locale = $request->getLocale();

        ////////////////////////////////////////////////////////////////////////
        /// fetch active posts for the current locale with category metadata

        $posts = $this->connection->fetchAllAssociative(
            'SELECT p.*, c.name AS category_name, c.slug AS category_slug
             FROM posts p
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE p.locale = :locale
             ORDER BY p.created_at DESC',
            ['locale' => $locale]
        );

        ////////////////////////////////////////////////////////////////////////
        /// render list layout

        return $this->render('posts/posts.html.twig', [
            'posts' => $posts,
            'locale' => $locale,
        ]);
    }

    /**
     * shows a single post based on its unique slug, along with its comment tree.
     */
    #[Route('/{slug}', name: 'app_post', methods: ['GET'])]
    public function show(string $slug, Request $request): Response
    {
        ////////////////////////////////////////////////////////////////////////
        /// 1. query the post

        $post = $this->connection->fetchAssociative(
            'SELECT p.*, c.name AS category_name, c.slug AS category_slug
             FROM posts p
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE p.slug = :slug',
            ['slug' => $slug]
        );

        if (!$post) {
            throw $this->createNotFoundException('the requested post does not exist.');
        }

        ////////////////////////////////////////////////////////////////////////
        /// 2. fetch comments related to this post

        $rawComments = $this->connection->fetchAllAssociative(
            'SELECT co.*, u.prefer_markdown
             FROM comments co
             LEFT JOIN users u ON co.user_id = u.id
             WHERE co.post_id = :post_id
             ORDER BY co.created_at ASC',
            ['post_id' => $post['id']]
        );

        ////////////////////////////////////////////////////////////////////////
        /// 3. build chronological or threaded comment layout representation

        return $this->render('posts/post.html.twig', [
            'post' => $post,
            'comments' => $rawComments,
        ]);
    }
}
