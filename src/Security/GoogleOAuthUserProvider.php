<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\User\OAuthUserProvider as BaseOAuthUserProvider;
use Symfony\Component\Security\Core\User\UserInterface;

class GoogleOAuthUserProvider extends BaseOAuthUserProvider
{
    public function __construct(
        ClientRegistry $clientRegistry,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct($clientRegistry);
    }

    public function loadUserByUsername($username): UserInterface
    {
        return $this->loadUserByIdentifier($username);
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $identifier]);

        if (!$user) {
            throw new \RuntimeException(sprintf('User with email "%s" not found.', $identifier));
        }

        return $user;
    }

    public function loadUserByOAuthUserResponse($response): UserInterface
    {
        // Get email from OAuth response (works for any OAuth provider)
        $email = $response->getEmail() ?? $response->claim('email');

        if (!$email) {
            throw new \RuntimeException('Could not extract email from OAuth response');
        }

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user) {
            // Create new user for staff
            $user = new User();
            $user->setEmail($email);
            $user->setFullName($response->getName() ?? $response->claim('name') ?? $email);
            $user->setUsername($email);
            $user->setRoles(['ROLE_STAFF']);
            $user->setIsVerified(true);
            $user->setPassword('');

            $this->entityManager->persist($user);
            $this->entityManager->flush();
        } else {
            // Ensure staff role
            if (!in_array('ROLE_STAFF', $user->getRoles())) {
                $roles = $user->getRoles();
                $roles[] = 'ROLE_STAFF';
                $user->setRoles(array_unique($roles));
                $this->entityManager->flush();
            }
            // Set verified
            if (!$user->isVerified()) {
                $user->setIsVerified(true);
                $this->entityManager->flush();
            }
        }

        return $user;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    // Inherits supportsClass from parent class
}
