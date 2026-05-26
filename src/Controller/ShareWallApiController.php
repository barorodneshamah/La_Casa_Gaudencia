<?php

namespace App\Controller;

use App\Entity\ShareWallComment;
use App\Entity\ShareWallPost;
use App\Repository\ShareWallPostRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/api/wall')]
class ShareWallApiController extends AbstractController
{
    public function __construct(private string $shareWallImagesDirectory) {}

    #[Route('/upload-image', name: 'api_wall_upload_image', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function uploadImage(Request $request, SluggerInterface $slugger): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file) {
            return $this->json(['error' => 'No file uploaded.'], 422);
        }

        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($file->getMimeType(), $allowed, true)) {
            return $this->json(['error' => 'Invalid file type. Only JPEG, PNG, WebP, or GIF allowed.'], 422);
        }

        if ($file->getSize() > 10 * 1024 * 1024) {
            return $this->json(['error' => 'File too large. Maximum 10MB.'], 422);
        }

        $safe     = $slugger->slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $fileName = $safe . '-' . uniqid() . '.' . $file->guessExtension();

        try {
            $file->move($this->shareWallImagesDirectory, $fileName);
        } catch (FileException $e) {
            return $this->json(['error' => 'Upload failed: ' . $e->getMessage()], 500);
        }

        return $this->json(['url' => '/uploads/share_wall/' . $fileName]);
    }

    #[Route('/featured', name: 'api_wall_featured', methods: ['GET'])]
    public function featured(Request $request, ShareWallPostRepository $repo): JsonResponse
    {
        $limit = min((int) $request->query->get('limit', 6), 20);
        $posts = $repo->findFeatured($limit);

        return $this->json([
            'posts' => array_map(fn($p) => $this->serializePost($p, $request), $posts),
        ]);
    }

    #[Route('/posts', name: 'api_wall_posts_list', methods: ['GET'])]
    public function list(Request $request, ShareWallPostRepository $repo): JsonResponse
    {
        $limit = min((int) $request->query->get('limit', 30), 50);
        $offset = max((int) $request->query->get('offset', 0), 0);

        $posts = $repo->findPublished($limit, $offset);
        $total = $repo->countPublished();

        return $this->json([
            'posts' => array_map(fn($p) => $this->serializePost($p, $request), $posts),
            'total' => $total,
        ]);
    }

    #[Route('/posts', name: 'api_wall_posts_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true) ?? [];

        $caption = trim($data['caption'] ?? '');
        if ($caption === '') {
            return $this->json(['error' => 'Caption is required.'], 422);
        }

        $imageUri = $data['imageUri'] ?? null;
        // Reject local device paths — mobile must upload via /api/wall/upload-image first
        if ($imageUri && (str_starts_with($imageUri, 'file://') || str_starts_with($imageUri, 'content://'))) {
            $imageUri = null;
        }

        $post = new ShareWallPost();
        $post->setAuthor($user);
        $post->setCaption($caption);
        $post->setImageUri($imageUri);
        $post->setServiceTag($data['serviceTag'] ?? null);
        $post->setReservationId(isset($data['reservationId']) ? (int) $data['reservationId'] : null);
        $post->setServiceId(isset($data['serviceId']) ? (int) $data['serviceId'] : null);
        $post->setStatus('published');

        $em->persist($post);
        $em->flush();

        return $this->json(['post' => $this->serializePost($post, $request)], 201);
    }

    #[Route('/posts/mine', name: 'api_wall_posts_mine', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function mine(Request $request, ShareWallPostRepository $repo): JsonResponse
    {
        $user  = $this->getUser();
        $posts = $repo->findBy(['author' => $user], ['createdAt' => 'DESC']);

        return $this->json([
            'posts' => array_map(fn($p) => $this->serializePost($p, $request), $posts),
        ]);
    }

    #[Route('/reviews', name: 'api_wall_reviews', methods: ['GET'])]
    public function reviews(Request $request, ShareWallPostRepository $repo): JsonResponse
    {
        $serviceTag = $request->query->get('serviceTag') ?? $request->query->get('serviceType');
        $serviceId  = $request->query->getInt('serviceId') ?: null;

        $criteria = ['status' => 'published'];
        if ($serviceTag) $criteria['serviceTag'] = $serviceTag;
        if ($serviceId)  $criteria['serviceId']  = $serviceId;

        $posts   = $repo->findBy($criteria, ['createdAt' => 'DESC'], 20);
        $reviews = array_map(fn($p) => [
            'id'        => (string) $p->getId(),
            'authorName'=> $p->getAuthor() ? ($p->getAuthor()->getFullName() ?: $p->getAuthor()->getUsername()) : 'Guest',
            'rating'    => 5,
            'comment'   => $p->getCaption(),
            'imageUri'  => $p->getImageUri(),
            'serviceId' => $p->getServiceId(),
            'createdAt' => $p->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ], $posts);

        return $this->json(['reviews' => $reviews, 'total' => count($reviews)]);
    }

    #[Route('/posts/{id}/like', name: 'api_wall_posts_like', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function toggleLike(ShareWallPost $post, EntityManagerInterface $em): JsonResponse
    {
        if (!$post->isPublished()) {
            return $this->json(['error' => 'Post not found.'], 404);
        }

        $userId = $this->getUser()->getId();
        $post->toggleLike($userId);
        $em->flush();

        return $this->json(['likedBy' => $post->getLikedByIds()]);
    }

    #[Route('/posts/{id}/comments', name: 'api_wall_posts_comment', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function addComment(ShareWallPost $post, Request $request, EntityManagerInterface $em): JsonResponse
    {
        if (!$post->isPublished()) {
            return $this->json(['error' => 'Post not found.'], 404);
        }

        $user = $this->getUser();
        $data = json_decode($request->getContent(), true) ?? [];
        $text = trim($data['text'] ?? '');
        if ($text === '') {
            return $this->json(['error' => 'Comment text is required.'], 422);
        }

        $comment = new ShareWallComment();
        $comment->setPost($post);
        $comment->setAuthor($user);
        $comment->setAuthorName($user->getFullName() ?: $user->getUsername());
        $comment->setText($text);

        $em->persist($comment);
        $em->flush();

        return $this->json(['comment' => $this->serializeComment($comment)], 201);
    }

    #[Route('/posts/{id}', name: 'api_wall_posts_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function delete(ShareWallPost $post, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        $isOwner = $post->getAuthor()?->getId() === $user->getId();
        $isStaff = $this->isGranted('ROLE_STAFF');

        if (!$isOwner && !$isStaff) {
            return $this->json(['error' => 'Forbidden.'], 403);
        }

        $em->remove($post);
        $em->flush();

        return $this->json(['success' => true]);
    }

    private function serializePost(ShareWallPost $post, ?Request $request = null): array
    {
        $author = $post->getAuthor();
        $roles = $author ? $author->getRoles() : [];

        $authorRole = 'ROLE_GUEST';
        if (in_array('ROLE_ADMIN', $roles, true)) {
            $authorRole = 'ROLE_ADMIN';
        } elseif (in_array('ROLE_STAFF', $roles, true)) {
            $authorRole = 'ROLE_STAFF';
        } elseif (in_array('ROLE_USER', $roles, true)) {
            $authorRole = 'ROLE_USER';
        }

        $imageUri = $post->getImageUri();
        // Normalize relative paths to absolute so mobile clients can load them
        if ($imageUri && str_starts_with($imageUri, '/') && $request) {
            $imageUri = $request->getSchemeAndHttpHost() . $imageUri;
        }

        return [
            'id'            => (string) $post->getId(),
            'authorId'      => $author?->getId() ?? 0,
            'authorName'    => $post->getAuthorNameOverride() ?? ($author ? ($author->getFullName() ?: $author->getUsername()) : 'Guest'),
            'authorRole'    => $authorRole,
            'caption'       => $post->getCaption(),
            'imageUri'      => $imageUri,
            'serviceTag'    => $post->getServiceTag(),
            'reservationId' => $post->getReservationId(),
            'serviceId'     => $post->getServiceId(),
            'isPinned'      => $post->isPinned(),
            'likedBy'       => $post->getLikedByIds(),
            'comments'      => array_map(fn($c) => $this->serializeComment($c), $post->getComments()->toArray()),
            'createdAt'     => $post->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    private function serializeComment(ShareWallComment $comment): array
    {
        return [
            'id'         => (string) $comment->getId(),
            'authorId'   => $comment->getAuthor()?->getId() ?? 0,
            'authorName' => $comment->getAuthorName(),
            'text'       => $comment->getText(),
            'createdAt'  => $comment->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
