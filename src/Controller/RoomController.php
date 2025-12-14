<?php

namespace App\Controller;

use App\Entity\Room;
use App\Form\RoomType;
use App\Repository\RoomRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/room')]
final class RoomController extends AbstractController
{
    #[Route(name: 'app_room_index', methods: ['GET'])]
    public function index(RoomRepository $roomRepository): Response
    {
        return $this->render('room/index.html.twig', [
            'rooms' => $roomRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_room_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $room = new Room();
        $form = $this->createForm(RoomType::class, $room);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            
            $mainImageFile = $form->get('mainImageFile')->getData();
            if ($mainImageFile) {
                $newFilename = $this->uploadImage($mainImageFile, $slugger, 'room_main');
                if ($newFilename) {
                    $room->setMainImage($newFilename);
                }
            }

            $galleryImageFiles = $form->get('galleryImageFiles')->getData();
            if ($galleryImageFiles) {
                $galleryImages = [];
                foreach ($galleryImageFiles as $galleryFile) {
                    $newFilename = $this->uploadImage($galleryFile, $slugger, 'room_gallery');
                    if ($newFilename) {
                        $galleryImages[] = $newFilename;
                    }
                }
                $room->setGalleryImages($galleryImages);
            }

            $entityManager->persist($room);
            $entityManager->flush();

            $this->addFlash('success', 'Room "' . $room->getRoomNumber() . '" has been created successfully!');

            return $this->redirectToRoute('app_room_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('room/new.html.twig', [
            'room' => $room,
            'form' => $form,
        ]);
    }

    #[Route('/show/{id}', name: 'app_room_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(RoomRepository $roomRepository, int $id): Response
    {
        $room = $roomRepository->find($id);
        if (!$room) {
            throw $this->createNotFoundException('Room not found.');
        }

        return $this->render('room/show.html.twig', [
            'room' => $room,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_room_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, RoomRepository $roomRepository, int $id, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $room = $roomRepository->find($id);
        if (!$room) {
            throw $this->createNotFoundException('Room not found.');
        }

        $form = $this->createForm(RoomType::class, $room);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            

            $mainImageFile = $form->get('mainImageFile')->getData();
            if ($mainImageFile) {

                $this->deleteImage($room->getMainImage());
                
                $newFilename = $this->uploadImage($mainImageFile, $slugger, 'room_main');
                if ($newFilename) {
                    $room->setMainImage($newFilename);
                }
            }

            $galleryImageFiles = $form->get('galleryImageFiles')->getData();
            if ($galleryImageFiles) {
                $galleryImages = $room->getGalleryImages() ?? [];
                foreach ($galleryImageFiles as $galleryFile) {
                    $newFilename = $this->uploadImage($galleryFile, $slugger, 'room_gallery');
                    if ($newFilename) {
                        $galleryImages[] = $newFilename;
                    }
                }
                $room->setGalleryImages($galleryImages);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Room "' . $room->getRoomNumber() . '" has been updated successfully!');

            return $this->redirectToRoute('app_room_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('room/edit.html.twig', [
            'room' => $room,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_room_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, RoomRepository $roomRepository, int $id, EntityManagerInterface $entityManager): Response
    {
        $room = $roomRepository->find($id);
        if (!$room) {
            throw $this->createNotFoundException('Room not found.');
        }

        if ($this->isCsrfTokenValid('delete'.$room->getId(), $request->getPayload()->getString('_token'))) {
            
            $this->deleteImage($room->getMainImage());
            foreach ($room->getGalleryImages() ?? [] as $galleryImage) {
                $this->deleteImage($galleryImage);
            }

            $entityManager->remove($room);
            $entityManager->flush();

            $this->addFlash('success', 'Room has been deleted successfully!');
        }

        return $this->redirectToRoute('app_room_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/delete-image/{type}/{index}', name: 'app_room_delete_image', methods: ['POST'], requirements: ['id' => '\d+', 'index' => '\d+'])]
    public function deleteRoomImage(Request $request, RoomRepository $roomRepository, int $id, string $type, int $index, EntityManagerInterface $entityManager): Response
    {
        $room = $roomRepository->find($id);
        if (!$room) {
            throw $this->createNotFoundException('Room not found.');
        }

        if ($this->isCsrfTokenValid('delete-image'.$room->getId(), $request->request->get('_token'))) {
            if ($type === 'main') {
                $this->deleteImage($room->getMainImage());
                $room->setMainImage(null);
            } elseif ($type === 'gallery') {
                $galleryImages = $room->getGalleryImages() ?? [];
                if (isset($galleryImages[$index])) {
                    $this->deleteImage($galleryImages[$index]);
                    unset($galleryImages[$index]);
                    $room->setGalleryImages(array_values($galleryImages));
                }
            }
            
            $entityManager->flush();
            $this->addFlash('success', 'Image deleted successfully!');
        }

        return $this->redirectToRoute('app_room_edit', ['id' => $room->getId()]);
    }

    private function uploadImage($file, SluggerInterface $slugger, string $prefix): ?string
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename);
        $newFilename = $prefix . '_' . $safeFilename . '_' . uniqid() . '.' . $file->guessExtension();

        try {
            $file->move(
                $this->getParameter('room_images_directory'),
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
            $filepath = $this->getParameter('room_images_directory') . '/' . $filename;
            if (file_exists($filepath)) {
                unlink($filepath);
            }
        }
    }
}
