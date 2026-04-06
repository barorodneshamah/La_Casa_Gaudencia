<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Security\LoginAuthenticator;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        EmailVerificationService $emailService
    ): JsonResponse {

        $user = new User();

        // Example (adjust to your form)
        $user->setEmail($request->request->get('email'));
        $user->setUsername($request->request->get('username'));

        $hashedPassword = $passwordHasher->hashPassword(
            $user,
            $request->request->get('password')
        );

        $user->setPassword($hashedPassword);

        // Generate token
        $token = $emailService->generateVerificationToken();
        $user->setVerificationToken($token);
        $user->setIsVerified(false);

        // SAVE FIRST
        $entityManager->persist($user);
        $entityManager->flush();

        // Generate URL
        $verificationUrl = $this->generateUrl(
            'app_verify_email',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        // Send email
        $emailService->sendVerificationEmail($user, $verificationUrl);

        return new JsonResponse([
            'success' => true,
            'message' => 'Registration successful. Please check your email to verify your account.',
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'isVerified' => $user->isVerified(),
                'roles' => $user->getRoles()
            ]
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