<?php

namespace App\Controller;

use App\Entity\Spa;
use App\Form\SpaType;
use App\Repository\SpaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/spa')]
#[IsGranted('ROLE_ADMIN')]
final class SpaController extends AbstractController
{
    public function __construct(private string $spaImagesDirectory) {}

    #[Route(name: 'app_spa_index', methods: ['GET'])]
    public function index(SpaRepository $spaRepository): Response
    {
        return $this->render('spa/index.html.twig', [
            'spas' => $spaRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_spa_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $spa  = new Spa();
        $form = $this->createForm(SpaType::class, $spa);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $mainImageFile = $form->get('mainImageFile')->getData();
            if ($mainImageFile) {
                $fileName = $this->uploadFile($mainImageFile, $slugger);
                if ($fileName) {
                    $spa->setMainImage($fileName);
                }
            }

            $galleryFiles = $form->get('galleryImageFiles')->getData();
            if ($galleryFiles) {
                $gallery = [];
                foreach ($galleryFiles as $file) {
                    $name = $this->uploadFile($file, $slugger);
                    if ($name) {
                        $gallery[] = $name;
                    }
                }
                $spa->setGalleryImages($gallery);
            }

            $em->persist($spa);
            $em->flush();

            $this->addFlash('success', 'Spa & Wellness service created successfully!');

            return $this->redirectToRoute('app_spa_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('spa/new.html.twig', [
            'spa'  => $spa,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_spa_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Spa $spa): Response
    {
        return $this->render('spa/show.html.twig', ['spa' => $spa]);
    }

    #[Route('/{id}/edit', name: 'app_spa_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Spa $spa, EntityManagerInterface $em, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(SpaType::class, $spa);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $mainImageFile = $form->get('mainImageFile')->getData();
            if ($mainImageFile) {
                if ($spa->getMainImage()) {
                    $this->deleteFile($spa->getMainImage());
                }
                $fileName = $this->uploadFile($mainImageFile, $slugger);
                if ($fileName) {
                    $spa->setMainImage($fileName);
                }
            }

            $galleryFiles = $form->get('galleryImageFiles')->getData();
            if ($galleryFiles) {
                $existing = $spa->getGalleryImages();
                $newNames = [];
                foreach ($galleryFiles as $file) {
                    $name = $this->uploadFile($file, $slugger);
                    if ($name) {
                        $newNames[] = $name;
                    }
                }
                $spa->setGalleryImages(array_merge($existing, $newNames));
            }

            $em->flush();

            $this->addFlash('success', 'Spa & Wellness service updated successfully!');

            return $this->redirectToRoute('app_spa_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('spa/edit.html.twig', [
            'spa'  => $spa,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_spa_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Spa $spa, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$spa->getId(), $request->getPayload()->getString('_token'))) {
            if ($spa->getMainImage()) {
                $this->deleteFile($spa->getMainImage());
            }
            foreach ($spa->getGalleryImages() as $img) {
                $this->deleteFile($img);
            }
            $em->remove($spa);
            $em->flush();
            $this->addFlash('success', 'Spa & Wellness service deleted successfully!');
        }

        return $this->redirectToRoute('app_spa_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/delete-image/{type}/{index}', name: 'app_spa_delete_image', methods: ['POST'], requirements: ['id' => '\d+', 'index' => '\d+'])]
    public function deleteImage(Request $request, Spa $spa, string $type, int $index, EntityManagerInterface $em): Response
    {
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete-image'.$spa->getId(), $token)) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 403);
            }
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_spa_edit', ['id' => $spa->getId()]);
        }

        $success = false;
        $message = '';

        if ($type === 'main') {
            if ($spa->getMainImage()) {
                $this->deleteFile($spa->getMainImage());
                $spa->setMainImage(null);
                $success = true;
                $message = 'Main image deleted successfully.';
            } else {
                $message = 'No main image to delete.';
            }
        } elseif ($type === 'gallery') {
            $gallery = $spa->getGalleryImages() ?? [];
            if (isset($gallery[$index])) {
                $this->deleteFile($gallery[$index]);
                array_splice($gallery, $index, 1);
                $spa->setGalleryImages($gallery);
                $success = true;
                $message = 'Gallery image deleted successfully.';
            } else {
                $message = 'Gallery image not found.';
            }
        } else {
            $message = 'Invalid image type.';
        }

        if ($success) {
            $em->flush();
        }

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => $success, 'message' => $message], $success ? 200 : 400);
        }

        $this->addFlash($success ? 'success' : 'error', $message);
        return $this->redirectToRoute('app_spa_edit', ['id' => $spa->getId()]);
    }

    private function uploadFile($file, SluggerInterface $slugger): ?string
    {
        $safeFilename = $slugger->slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $fileName     = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();
        try {
            $file->move($this->spaImagesDirectory, $fileName);
            return $fileName;
        } catch (FileException $e) {
            $this->addFlash('error', 'Failed to upload image: '.$e->getMessage());
            return null;
        }
    }

    private function deleteFile(string $filename): void
    {
        $path = $this->spaImagesDirectory.'/'.$filename;
        if (file_exists($path)) {
            unlink($path);
        }
    }
}