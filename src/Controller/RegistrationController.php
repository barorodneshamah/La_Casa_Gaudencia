<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        EmailVerificationService $emailVerificationService
    ): Response {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Get the user data from the form
            $user = $form->getData();

            // Get plain password
            $plainPassword = $form->get('plainPassword')->getData();

            // Check if user already exists
            $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $user->getEmail()]);
            if ($existingUser) {
                $this->addFlash('error', 'Email already registered.');
                return $this->redirectToRoute('app_register');
            }

            $existingUsername = $entityManager->getRepository(User::class)->findOneBy(['username' => $user->getUsername()]);
            if ($existingUsername) {
                $this->addFlash('error', 'Username already taken.');
                return $this->redirectToRoute('app_register');
            }

            // Hash password
            $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);

            // Generate verification token
            $verificationToken = $emailVerificationService->generateVerificationToken();
            $user->setVerificationToken($verificationToken);
            $user->setIsVerified(false);

            try {
                // Generate verification URL (before saving to DB, for email sending)
                $verificationUrl = $this->generateUrl(
                    'app_verify_email',
                    ['token' => $verificationToken],
                    UrlGeneratorInterface::ABSOLUTE_URL
                   );

                // Send verification email BEFORE saving to DB
                // This way we don't create a user record if email sending fails
                try {
                    $emailVerificationService->sendVerificationEmail($user, $verificationUrl);
                } catch (\Exception) {
                    $this->addFlash('error', 'Registration failed: Could not send verification email. Please check your email address and try again.');
                    return $this->redirectToRoute('app_register');
                }

                // Save user only after email is sent successfully
                $entityManager->persist($user);
                $entityManager->flush();

                $this->addFlash('success', 'Registration successful! Please check your email to verify your account.');
                return $this->redirectToRoute('customer_use_app');
            } catch (\Exception) {
                $this->addFlash('error', 'Registration failed. Please try again.');
                return $this->redirectToRoute('app_register');
            }
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    // legacy react‑native endpoint – duplicate of AuthController::register
    #[Route('/api/signup', name: 'api_signup', methods: ['POST'])]
    public function apiRegister(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
        EmailVerificationService $emailVerificationService
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

        // Generate verification token
        $verificationToken = $emailVerificationService->generateVerificationToken();
        $user->setVerificationToken($verificationToken);
        $user->setIsVerified(false);

        try {
            $entityManager->persist($user);
            $entityManager->flush();

            // Generate verification URL
            $verificationUrl = $this->generateUrl(
                'app_verify_email',
                ['token' => $verificationToken],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            // Send verification email
            $emailVerificationService->sendVerificationEmail($user, $verificationUrl);

            return new JsonResponse([
                'status' => 'success',
                'message' => 'User registered successfully! Please check your email to verify your account.',
                'requiresVerification' => true
            ], 201);
        } catch (\Exception $e) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Registration failed: ' . $e->getMessage()
            ], 500);
        }
    }
}