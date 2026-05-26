<?php

namespace App\Controller;

use App\Entity\ShareWallPost;
use App\Repository\ShareWallPostRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard/share-wall')]
#[IsGranted('ROLE_STAFF')]
class ShareWallAdminController extends AbstractController
{
    #[Route('', name: 'admin_share_wall_index', methods: ['GET'])]
    public function index(ShareWallPostRepository $repo): Response
    {
        $all = $repo->createQueryBuilder('p')
            ->orderBy('p.isPinned', 'DESC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('share_wall/index.html.twig', [
            'posts' => $all,
        ]);
    }

    #[Route('/{id}/toggle-status', name: 'admin_share_wall_toggle_status', methods: ['POST'])]
    public function toggleStatus(ShareWallPost $post, EntityManagerInterface $em): JsonResponse
    {
        $post->setStatus($post->getStatus() === 'published' ? 'hidden' : 'published');
        $em->flush();

        return $this->json(['status' => $post->getStatus()]);
    }

    #[Route('/{id}/toggle-pin', name: 'admin_share_wall_toggle_pin', methods: ['POST'])]
    public function togglePin(ShareWallPost $post, EntityManagerInterface $em): JsonResponse
    {
        $post->setIsPinned(!$post->isPinned());
        $em->flush();

        return $this->json(['isPinned' => $post->isPinned()]);
    }

    #[Route('/{id}/delete', name: 'admin_share_wall_delete', methods: ['POST'])]
    public function delete(ShareWallPost $post, EntityManagerInterface $em, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('delete_wall_post_' . $post->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('admin_share_wall_index');
        }

        $em->remove($post);
        $em->flush();

        $this->addFlash('success', 'Post deleted.');
        return $this->redirectToRoute('admin_share_wall_index');
    }
}
