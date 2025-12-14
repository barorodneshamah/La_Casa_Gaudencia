<?php

namespace App\Controller;

use App\Entity\Food;
use App\Form\FoodType;
use App\Repository\FoodRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;


#[Route('/food')]
final class FoodController extends AbstractController
{
    #[Route(name: 'app_food_index', methods: ['GET'])]
    public function index(FoodRepository $foodRepository): Response
    {
        return $this->render('food/index.html.twig', [
            'foods' => $foodRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_food_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $food = new Food();
        $form = $this->createForm(FoodType::class, $food);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            
            $mainImageFile = $form->get('mainImageFile')->getData();
            if ($mainImageFile) {
                $newFilename = $this->uploadImage($mainImageFile, $slugger, 'food_main');
                if ($newFilename) {
                    $food->setMainImage($newFilename);
                }
            }

            $galleryImageFiles = $form->get('galleryImageFiles')->getData();
            if ($galleryImageFiles) {
                $galleryImages = [];
                foreach ($galleryImageFiles as $galleryFile) {
                    $newFilename = $this->uploadImage($galleryFile, $slugger, 'food_gallery');
                    if ($newFilename) {
                        $galleryImages[] = $newFilename;
                    }
                }
                $food->setGalleryImages($galleryImages);
            }

            $entityManager->persist($food);
            $entityManager->flush();

            $this->addFlash('success', 'Food item "' . $food->getName() . '" has been created successfully!');

            return $this->redirectToRoute('app_food_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('food/new.html.twig', [
            'food' => $food,
            'form' => $form,
        ]);
    }

    #[Route('/show/{id}', name: 'app_food_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(FoodRepository $foodRepository, int $id): Response
    {
        $food = $foodRepository->find($id);
        if (!$food) {
            throw $this->createNotFoundException('Food item not found.');
        }

        return $this->render('food/show.html.twig', [
            'food' => $food,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_food_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, FoodRepository $foodRepository, int $id, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $food = $foodRepository->find($id);
        if (!$food) {
            throw $this->createNotFoundException('Food item not found.');
        }

        $form = $this->createForm(FoodType::class, $food);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            
            $mainImageFile = $form->get('mainImageFile')->getData();
            if ($mainImageFile) {
                // Delete old main image
                $this->deleteImage($food->getMainImage());
                
                $newFilename = $this->uploadImage($mainImageFile, $slugger, 'food_main');
                if ($newFilename) {
                    $food->setMainImage($newFilename);
                }
            }

            $galleryImageFiles = $form->get('galleryImageFiles')->getData();
            if ($galleryImageFiles) {
                $galleryImages = $food->getGalleryImages() ?? [];
                foreach ($galleryImageFiles as $galleryFile) {
                    $newFilename = $this->uploadImage($galleryFile, $slugger, 'food_gallery');
                    if ($newFilename) {
                        $galleryImages[] = $newFilename;
                    }
                }
                $food->setGalleryImages($galleryImages);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Food item "' . $food->getName() . '" has been updated successfully!');

            return $this->redirectToRoute('app_food_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('food/edit.html.twig', [
            'food' => $food,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_food_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, FoodRepository $foodRepository, int $id, EntityManagerInterface $entityManager): Response
    {
        $food = $foodRepository->find($id);
        if (!$food) {
            throw $this->createNotFoundException('Food item not found.');
        }

        if ($this->isCsrfTokenValid('delete' . $food->getId(), $request->getPayload()->getString('_token'))) {
            // Delete main image
            $this->deleteImage($food->getMainImage());
            
            // Delete gallery images
            foreach ($food->getGalleryImages() ?? [] as $galleryImage) {
                $this->deleteImage($galleryImage);
            }

            $entityManager->remove($food);
            $entityManager->flush();

            $this->addFlash('success', 'Food item "' . $food->getName() . '" has been deleted successfully!');
        }

        return $this->redirectToRoute('app_food_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/delete-image/{type}/{index}', name: 'app_food_delete_image', methods: ['POST'], requirements: ['id' => '\d+', 'index' => '\d+'])]
    public function deleteFoodImage(Request $request, FoodRepository $foodRepository, int $id, string $type, int $index, EntityManagerInterface $entityManager): Response
    {
        $food = $foodRepository->find($id);
        if (!$food) {
            throw $this->createNotFoundException('Food item not found.');
        }

        if ($this->isCsrfTokenValid('delete-image' . $food->getId(), $request->request->get('_token'))) {
            if ($type === 'main') {
                $this->deleteImage($food->getMainImage());
                $food->setMainImage(null);
            } elseif ($type === 'gallery') {
                $galleryImages = $food->getGalleryImages() ?? [];
                if (isset($galleryImages[$index])) {
                    $this->deleteImage($galleryImages[$index]);
                    unset($galleryImages[$index]);
                    $food->setGalleryImages(array_values($galleryImages));
                }
            }
            
            $entityManager->flush();
            $this->addFlash('success', 'Image deleted successfully!');
        }

        return $this->redirectToRoute('app_food_edit', ['id' => $food->getId()]);
    }

    private function uploadImage($file, SluggerInterface $slugger, string $prefix): ?string
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename);
        $newFilename = $prefix . '_' . $safeFilename . '_' . uniqid() . '.' . $file->guessExtension();

        try {
            $file->move(
                $this->getParameter('food_images_directory'),
                $newFilename
            );
            return $newFilename;
        } catch (FileException $e) {
            $this->addFlash('error', 'Failed to upload image: ' . $e->getMessage());
            return null;
        }
    }

    private function deleteImage(?string $filename): void
    {
        if ($filename) {
            $filepath = $this->getParameter('food_images_directory') . '/' . $filename;
            if (file_exists($filepath)) {
                unlink($filepath);
            }
        }
    }
}