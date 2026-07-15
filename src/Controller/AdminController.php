<?php
declare(strict_types=1);
// file ~/Sites/blog/src/Controller/AdminController.php

namespace App\Controller;

use App\Entity\Post;
use App\Entity\Category;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/', name: 'app_admin_home', methods: ['GET'])]
    public function index(): Response
    {
        // placeholder for listing existing posts or display logs
        return $this->render('admin/dashboard.html.twig');
    }

    #[Route('/post/new', name: 'app_admin_post_new', methods: ['POST'])]
    public function createPost(Request $request): Response
    {
        // 1. retrieve parameter variables from the submission payload
        $title = $request->request->get('title');
        $content = $request->request->get('content');
        $categoryId = $request->request->get('category_id');
        $locale = $request->request->get('locale', 'en');

        if (empty($title) || empty($content) || empty($categoryId)) {
            $this->addFlash('error', 'missing required entry parameters fields.');
            return $this->redirectToRoute('app_admin_home');
        }

        // 2. resolve references to prevent mapping mismatches
        $category = $this->entityManager->getRepository(Category::class)->find($categoryId);
        if (!$category) {
            $this->addFlash('error', sprintf('selected category code "%s" does not exist.', $categoryId));
            return $this->redirectToRoute('app_admin_home');
        }

        // 3. fetch the currently authenticated shadow user entity instance
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser) {
            throw $this->createAccessDeniedException('no valid user session detected.');
        }

        // 4. construct the new post entry row
        $slugger = new AsciiSlugger();
        $slugText = strtolower($slugger->slug($title)->toString());

        $post = new Post();
        $post->setTitle($title);
        $post->setSlug($slugText);
        $post->setContent($content);
        $post->setLocale($locale);
        $post->setCategory($category);
        $post->setUser($currentUser);

        // 5. execute database operations cleanly
        $this->entityManager->persist($post);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('new post "%s" published smoothly.', $title));

        return $this->redirectToRoute('app_admin_home');
    }
}
