<?php

namespace App\Controller;

use App\Entity\Package;
use App\Form\PackageType;
use App\Repository\PackageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/package')]
class PackageController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private SluggerInterface $slugger,
        private string $uploadDir = 'uploads/packages'
    ) {}

    #[Route('/', name: 'app_package_index', methods: ['GET'])]
    public function index(PackageRepository $repo): Response
    {
        $packages = $repo->findBy([], ['createdAt' => 'DESC']);

        return $this->render('package/index.html.twig', [
            'packages' => $packages,
            'stats' => [
                'total' => count($packages),
                'active' => count(array_filter($packages, fn($p) => $p->getStatus() === 'Active')),
                'featured' => count(array_filter($packages, fn($p) => $p->isFeatured())),
            ],
        ]);
    }

    #[Route('/new', name: 'app_package_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $package = new Package();
        $form = $this->createForm(PackageType::class, $package);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleImageUploads($form, $package);
            $package->calculateOriginalPrice();

            $this->em->persist($package);
            $this->em->flush();

            $this->addFlash('success', 'Package "' . $package->getName() . '" created successfully!');
            return $this->redirectToRoute('app_package_index');
        }

        return $this->render('package/new.html.twig', [
            'form' => $form,
            'package' => $package,
        ]);
    }

    #[Route('/{id}', name: 'app_package_show', methods: ['GET'])]
    public function show(Package $package): Response
    {
        return $this->render('package/show.html.twig', [
            'package' => $package,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_package_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Package $package): Response
    {
        $form = $this->createForm(PackageType::class, $package);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleImageUploads($form, $package);
            $package->calculateOriginalPrice();

            $this->em->flush();

            $this->addFlash('success', 'Package "' . $package->getName() . '" updated successfully!');
            return $this->redirectToRoute('app_package_index');
        }

        return $this->render('package/edit.html.twig', [
            'form' => $form,
            'package' => $package,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_package_delete', methods: ['POST'])]
    public function delete(Request $request, Package $package): Response
    {
        if ($this->isCsrfTokenValid('delete' . $package->getId(), $request->request->get('_token'))) {
            // Delete images
            $this->deleteImageFile($package->getMainImage());
            foreach ($package->getGalleryImages() as $img) {
                $this->deleteImageFile($img);
            }

            $name = $package->getName();
            $this->em->remove($package);
            $this->em->flush();

            $this->addFlash('success', 'Package "' . $name . '" deleted!');
        }

        return $this->redirectToRoute('app_package_index');
    }

    #[Route('/{id}/toggle-status', name: 'app_package_toggle_status', methods: ['POST'])]
    public function toggleStatus(Request $request, Package $package): Response
    {
        if ($this->isCsrfTokenValid('toggle' . $package->getId(), $request->request->get('_token'))) {
            $package->setStatus($package->getStatus() === 'Active' ? 'Inactive' : 'Active');
            $this->em->flush();
            $this->addFlash('success', 'Status updated to ' . $package->getStatus());
        }

        return $this->redirectToRoute('app_package_index');
    }

    #[Route('/{id}/toggle-featured', name: 'app_package_toggle_featured', methods: ['POST'])]
    public function toggleFeatured(Request $request, Package $package): Response
    {
        if ($this->isCsrfTokenValid('featured' . $package->getId(), $request->request->get('_token'))) {
            $package->setIsFeatured(!$package->isFeatured());
            $this->em->flush();
            $this->addFlash('success', $package->isFeatured() ? 'Marked as featured' : 'Removed from featured');
        }

        return $this->redirectToRoute('app_package_index');
    }

    #[Route('/{id}/duplicate', name: 'app_package_duplicate', methods: ['POST'])]
    public function duplicate(Request $request, Package $package): Response
    {
        if ($this->isCsrfTokenValid('duplicate' . $package->getId(), $request->request->get('_token'))) {
            $newPackage = new Package();
            $newPackage->setName($package->getName() . ' (Copy)');
            $newPackage->setDescription($package->getDescription());
            $newPackage->setPackagePrice($package->getPackagePrice());
            $newPackage->setPackageType($package->getPackageType());
            $newPackage->setStatus('Draft');
            $newPackage->setDurationDays($package->getDurationDays());
            $newPackage->setDurationNights($package->getDurationNights());
            $newPackage->setMaxGuests($package->getMaxGuests());
            $newPackage->setInclusions($package->getInclusions());
            $newPackage->setExclusions($package->getExclusions());
            $newPackage->setTermsAndConditions($package->getTermsAndConditions());

            foreach ($package->getRooms() as $room) {
                $newPackage->addRoom($room);
            }
            foreach ($package->getTours() as $tour) {
                $newPackage->addTour($tour);
            }
            foreach ($package->getFoods() as $food) {
                $newPackage->addFood($food);
            }

            $newPackage->calculateOriginalPrice();

            $this->em->persist($newPackage);
            $this->em->flush();

            $this->addFlash('success', 'Package duplicated! Edit to customize.');
            return $this->redirectToRoute('app_package_edit', ['id' => $newPackage->getId()]);
        }

        return $this->redirectToRoute('app_package_index');
    }

    #[Route('/{id}/remove-gallery-image', name: 'app_package_remove_gallery_image', methods: ['POST'])]
    public function removeGalleryImage(Request $request, Package $package): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $imageName = $data['image'] ?? null;

        if ($imageName && $this->isCsrfTokenValid('remove-image' . $package->getId(), $data['_token'] ?? '')) {
            $package->removeGalleryImage($imageName);
            $this->deleteImageFile($imageName);
            $this->em->flush();

            return new JsonResponse(['success' => true]);
        }

        return new JsonResponse(['success' => false], 400);
    }

    /**
     * Delete package image (main or gallery)
     */
    #[Route('/{id}/delete-image/{type}/{index}', name: 'app_package_delete_image', methods: ['POST'], requirements: ['id' => '\d+', 'index' => '\d+'])]
    public function deleteImage(Request $request, Package $package, string $type, int $index): Response
    {
        if (!$this->isCsrfTokenValid('delete-image' . $package->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token');
            return $this->redirectToRoute('app_package_edit', ['id' => $package->getId()]);
        }

        if ($type === 'main') {
            // Delete main image
            $mainImage = $package->getMainImage();
            if ($mainImage) {
                $this->deleteImageFile($mainImage);
                $package->setMainImage(null);
                $this->em->flush();
                $this->addFlash('success', 'Main image deleted successfully');
            }
        } elseif ($type === 'gallery') {
            // Delete gallery image at index
            $galleryImages = $package->getGalleryImages();
            if (isset($galleryImages[$index])) {
                $imageName = $galleryImages[$index];
                $this->deleteImageFile($imageName);
                $package->removeGalleryImage($imageName);
                $this->em->flush();
                $this->addFlash('success', 'Gallery image deleted successfully');
            }
        }

        return $this->redirectToRoute('app_package_edit', ['id' => $package->getId()]);
    }

    // ========== HELPER METHODS ==========

    private function handleImageUploads($form, Package $package): void
    {
        // Main image
        $mainFile = $form->get('mainImageFile')->getData();
        if ($mainFile) {
            $this->deleteImageFile($package->getMainImage());
            $newName = $this->uploadImage($mainFile);
            if ($newName) {
                $package->setMainImage($newName);
            }
        }

        // Gallery images
        $galleryFiles = $form->get('galleryImageFiles')->getData();
        if ($galleryFiles) {
            foreach ($galleryFiles as $file) {
                $newName = $this->uploadImage($file);
                if ($newName) {
                    $package->addGalleryImage($newName);
                }
            }
        }
    }

    private function uploadImage($file): ?string
    {
        if (!$file) {
            return null;
        }

        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        try {
            $uploadPath = $this->getParameter('kernel.project_dir') . '/public/' . $this->uploadDir;

            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }

            $file->move($uploadPath, $newFilename);
            return $newFilename;
        } catch (FileException $e) {
            $this->addFlash('error', 'Failed to upload image: ' . $e->getMessage());
            return null;
        }
    }

    private function deleteImageFile(?string $filename): void
    {
        if (!$filename) {
            return;
        }

        $filepath = $this->getParameter('kernel.project_dir') . '/public/' . $this->uploadDir . '/' . $filename;
        if (file_exists($filepath)) {
            unlink($filepath);
        }
    }
}