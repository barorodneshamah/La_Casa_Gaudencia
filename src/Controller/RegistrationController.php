<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Security\LoginAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        Security $security,
        EntityManagerInterface $entityManager
    ): Response {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            // encode the plain password
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

            $entityManager->persist($user);
            $entityManager->flush();

            // do anything else you need here, like send an email

            return $security->login($user, LoginAuthenticator::class, 'main');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    // legacy react‑native endpoint – duplicate of AuthController::register
    #[Route('/api/signup', name: 'api_signup', methods: ['POST'])]
    public function apiRegister(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        // 1. Parse input
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['error' => 'Invalid JSON'], 400);
        }

        $email = $data['email'] ?? null;
        $username = $data['username'] ?? $email;
        $plainPassword = $data['password'] ?? ($data['plainPassword'] ?? null);

        if (!$email || !$plainPassword) {
            return new JsonResponse([
                'error' => 'Email and password are required'
            ], 400);
        }

        // ensure uniqueness (mirrors AuthController rules)
        $repo = $entityManager->getRepository(User::class);
        if ($repo->findOneBy(['email' => $email])) {
            return new JsonResponse(['error' => 'Email already in use'], 409);
        }
        if ($username && $repo->findOneBy(['username' => $username])) {
            return new JsonResponse(['error' => 'Username already taken'], 409);
        }

        // create and persist user
        $user = new User();
        $user->setEmail($email);
        if (method_exists($user, 'setUsername')) {
            $user->setUsername($username);
        }
        $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

        try {
            $entityManager->persist($user);
            $entityManager->flush();

            return new JsonResponse([
                'status' => 'success',
                'message' => 'User registered successfully!'
            ], 201);
        } catch (\Exception $e) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Registration failed: ' . $e->getMessage()
            ], 500);
        }
    }
}