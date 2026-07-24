<?php
declare(strict_types=1);
// file ~/Sites/blog/src/Controller/AdminController.php

namespace App\Controller;

use App\Entity\Post;
use App\Entity\User;
use App\Form\PostType;
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
        $post = new Post();
        $form = $this->createForm(PostType::class, $post);

        return $this->render('admin/dashboard.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/post/new', name: 'app_admin_post_new', methods: ['POST'])]
    public function createPost(Request $request): Response
    {
        $post = new Post();
        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User|null $currentUser */
            $currentUser = $this->getUser();
            if (!$currentUser) {
                throw $this->createAccessDeniedException('no valid user session detected.');
            }

            $slugger = new AsciiSlugger();
            $post->setSlug(strtolower($slugger->slug($post->getTitle())->toString()));
            $post->setUser($currentUser);

            $this->entityManager->persist($post);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('new post "%s" published smoothly.', $post->getTitle()));

            return $this->redirectToRoute('app_admin_home', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/dashboard.html.twig', [
            'form' => $form->createView(),
        ], new Response(null, Response::HTTP_UNPROCESSABLE_ENTITY));
    }

    #[Route('/post/{id}/edit', name: 'app_admin_post_edit', methods: ['GET', 'POST'])]
    public function editPost(Request $request, Post $post): Response
    {
        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $slugger = new AsciiSlugger();
            $post->setSlug(strtolower($slugger->slug($post->getTitle())->toString()));

            $this->entityManager->flush();

            $this->addFlash('success', sprintf('post "%s" updated successfully.', $post->getTitle()));

            return $this->redirectToRoute('app_post_show', ['slug' => $post->getSlug()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/edit.html.twig', [
            'post' => $post,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/post/{id}/delete', name: 'app_admin_post_delete', methods: ['POST'])]
    public function deletePost(Request $request, Post $post): Response
    {
        if ($this->isCsrfTokenValid('delete' . $post->getId(), (string) $request->request->get('_token'))) {
            $this->entityManager->remove($post);
            $this->entityManager->flush();

            $this->addFlash('success', sprintf('post "%s" removed successfully.', $post->getTitle()));
        }

        return $this->redirectToRoute('app_posts', [], Response::HTTP_SEE_OTHER);
    }
}
