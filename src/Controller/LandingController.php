<?php

namespace App\Controller;

use App\Entity\ContactMessage;
use App\Form\ContactMessageType;
use App\Repository\RoomRepository;
use App\Repository\TourRepository;
use App\Repository\FoodRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LandingController extends AbstractController
{
    public function __construct(
        private RoomRepository $roomRepo,
        private TourRepository $tourRepo,
        private FoodRepository $foodRepo
    ) {}

    #[Route('/', name: 'app_home')]
    #[Route('/landing', name: 'app_landing')]
    public function index(): Response
    {
        // Get minimum prices from database
        $minRoomPrice = $this->roomRepo->findMinPrice();
        $minTourPrice = $this->tourRepo->findMinPrice();
        $minFoodPrice = $this->foodRepo->findMinPrice();

        // Get total counts for dynamic badges
        $totalRooms = $this->roomRepo->countActiveRooms();
        $totalTours = $this->tourRepo->countActiveTours();
        $totalFoodItems = $this->foodRepo->countActiveFoodItems();

        // Get sample data for reference and display
        $featuredRooms = $this->roomRepo->findForLandingPage(3);
        $featuredTours = $this->tourRepo->findForLandingPage(3);
        $featuredFoodItems = $this->foodRepo->findForLandingPage(3);

        return $this->render('landing/index.html.twig', [
            'minRoomPrice' => $minRoomPrice,
            'minTourPrice' => $minTourPrice,
            'minFoodPrice' => $minFoodPrice,
            'totalRooms' => $totalRooms,
            'totalTours' => $totalTours,
            'totalFoodItems' => $totalFoodItems,
            'featuredRooms' => $featuredRooms,
            'featuredTours' => $featuredTours,
            'featuredFoodItems' => $featuredFoodItems,
        ]);
    }

    #[Route('/about', name: 'app_about')]
    public function about(): Response
    {
        return $this->render('about/index.html.twig');
    }

    #[Route('/contact', name: 'app_contact')]
    public function contact(Request $request, EntityManagerInterface $em): Response
    {
        $contactMessage = new ContactMessage();
        $form = $this->createForm(ContactMessageType::class, $contactMessage);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $contactMessage->setIpAddress($request->getClientIp());
            $em->persist($contactMessage);
            $em->flush();

            $this->addFlash('contact_success', 'Thank you! We will get back to you within 24 hours.');
            return $this->redirectToRoute('app_contact');
        }

        return $this->render('contact/index.html.twig', [
            'contactForm' => $form->createView()
        ]);
    }
}