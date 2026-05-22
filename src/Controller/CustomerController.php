<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CustomerController extends AbstractController
{
    #[Route('/use-app', name: 'customer_use_app')]
    public function useApp(): Response
    {
        return $this->render('customer/use_app.html.twig');
    }
}
