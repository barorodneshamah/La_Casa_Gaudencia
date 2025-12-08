<?php

namespace App\Controller;

use App\Entity\Tour;
use App\Form\TourType;
use App\Repository\TourRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/tour')]
final class TourController extends AbstractController
{
    private string $tourImagesDirectory;

    public function __construct(string $tourImagesDirectory)
    {
        $this->tourImagesDirectory = $tourImagesDirectory;
    }

    #[Route(name: 'app_tour_index', methods: ['GET'])]
    public function index(TourRepository $tourRepository): Response
    {
        return $this->render('tour/index.html.twig', [
            'tours' => $tourRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_tour_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $tour = new Tour();
        $form = $this->createForm(TourType::class, $tour);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle main image upload
            $mainImageFile = $form->get('mainImageFile')->getData();
            if ($mainImageFile) {
                $mainImageFileName = $this->uploadFile($mainImageFile, $slugger);
                if ($mainImageFileName) {
                    $tour->setMainImage($mainImageFileName);
                }
            }

            // Handle gallery images upload
            $galleryImageFiles = $form->get('galleryImageFiles')->getData();
            if ($galleryImageFiles) {
                $galleryImageNames = [];
                foreach ($galleryImageFiles as $galleryImageFile) {
                    $galleryImageFileName = $this->uploadFile($galleryImageFile, $slugger);
                    if ($galleryImageFileName) {
                        $galleryImageNames[] = $galleryImageFileName;
                    }
                }
                $tour->setGalleryImages($galleryImageNames);
            }

            $entityManager->persist($tour);
            $entityManager->flush();

            $this->addFlash('success', 'Tour created successfully!');

            return $this->redirectToRoute('app_tour_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('tour/new.html.twig', [
            'tour' => $tour,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_tour_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Tour $tour): Response
    {
        return $this->render('tour/show.html.twig', [
            'tour' => $tour,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_tour_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Tour $tour, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(TourType::class, $tour);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle main image upload
            $mainImageFile = $form->get('mainImageFile')->getData();
            if ($mainImageFile) {
                // Delete old main image if exists
                if ($tour->getMainImage()) {
                    $this->deleteFile($tour->getMainImage());
                }
                $mainImageFileName = $this->uploadFile($mainImageFile, $slugger);
                if ($mainImageFileName) {
                    $tour->setMainImage($mainImageFileName);
                }
            }

            // Handle gallery images upload
            $galleryImageFiles = $form->get('galleryImageFiles')->getData();
            if ($galleryImageFiles) {
                $existingGalleryImages = $tour->getGalleryImages();
                $newGalleryImageNames = [];
                
                foreach ($galleryImageFiles as $galleryImageFile) {
                    $galleryImageFileName = $this->uploadFile($galleryImageFile, $slugger);
                    if ($galleryImageFileName) {
                        $newGalleryImageNames[] = $galleryImageFileName;
                    }
                }
                
                // Merge existing and new gallery images
                $allGalleryImages = array_merge($existingGalleryImages, $newGalleryImageNames);
                $tour->setGalleryImages($allGalleryImages);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Tour updated successfully!');

            return $this->redirectToRoute('app_tour_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('tour/edit.html.twig', [
            'tour' => $tour,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_tour_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Tour $tour, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$tour->getId(), $request->getPayload()->getString('_token'))) {
            // Delete main image
            if ($tour->getMainImage()) {
                $this->deleteFile($tour->getMainImage());
            }

            // Delete gallery images
            $galleryImages = $tour->getGalleryImages();
            foreach ($galleryImages as $galleryImage) {
                $this->deleteFile($galleryImage);
            }

            $entityManager->remove($tour);
            $entityManager->flush();

            $this->addFlash('success', 'Tour deleted successfully!');
        }

        return $this->redirectToRoute('app_tour_index', [], Response::HTTP_SEE_OTHER);
    }


    #[Route('/{id}/delete-image/{type}/{index}', name: 'app_tour_delete_image', methods: ['POST'], requirements: ['id' => '\d+', 'index' => '\d+'])]
    public function deleteImage(
        Request $request, 
        Tour $tour, 
        string $type, 
        int $index, 
        EntityManagerInterface $entityManager
    ): Response {
        // Validate CSRF token
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete-image' . $tour->getId(), $token)) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 403);
            }
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('app_tour_edit', ['id' => $tour->getId()]);
        }

        $success = false;
        $message = '';

        if ($type === 'main') {
            // Delete main image
            if ($tour->getMainImage()) {
                $this->deleteFile($tour->getMainImage());
                $tour->setMainImage(null);
                $success = true;
                $message = 'Main image deleted successfully.';
            } else {
                $message = 'No main image to delete.';
            }
        } elseif ($type === 'gallery') {

            $galleryImages = $tour->getGalleryImages() ?? [];
            
            if (isset($galleryImages[$index])) {
                $this->deleteFile($galleryImages[$index]);
                array_splice($galleryImages, $index, 1);
                $tour->setGalleryImages($galleryImages);
                $success = true;
                $message = 'Gallery image deleted successfully.';
            } else {
                $message = 'Gallery image not found.';
            }
        } else {
            $message = 'Invalid image type.';
        }

        if ($success) {
            $entityManager->flush();
        }

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => $success, 
                'message' => $message
            ], $success ? 200 : 400);
        }


        if ($success) {
            $this->addFlash('success', $message);
        } else {
            $this->addFlash('error', $message);
        }

        return $this->redirectToRoute('app_tour_edit', ['id' => $tour->getId()]);
    }


    private function uploadFile($file, SluggerInterface $slugger): ?string
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename);
        $fileName = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();

        try {
            $file->move($this->tourImagesDirectory, $fileName);
            return $fileName;
        } catch (FileException $e) {
            $this->addFlash('error', 'Failed to upload image: ' . $e->getMessage());
            return null;
        }
    }


    private function deleteFile(string $filename): void
    {
        $filePath = $this->tourImagesDirectory . '/' . $filename;
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
}