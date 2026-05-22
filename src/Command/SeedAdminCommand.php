<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:seed-admin', description: 'Seed default admin and staff accounts')]
class SeedAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $repo = $this->em->getRepository(User::class);

        $seeds = [
            [
                'username' => 'admin',
                'email'    => 'admin@lacasagaudencia.com',
                'fullName' => 'Administrator',
                'roles'    => [User::ROLE_ADMIN],
                'password' => 'admin123',
            ],
            [
                'username' => 'staff',
                'email'    => 'staff@lacasagaudencia.com',
                'fullName' => 'Staff Member',
                'roles'    => [User::ROLE_STAFF],
                'password' => 'staff123',
            ],
        ];

        foreach ($seeds as $seed) {
            $user = $repo->findOneBy(['username' => $seed['username']]);

            if (!$user) {
                $user = new User();
                $user->setUsername($seed['username']);
                $user->setEmail($seed['email']);
                $user->setFullName($seed['fullName']);
                $user->setRoles($seed['roles']);
                $this->em->persist($user);
                $output->writeln("Created user: {$seed['username']}");
            } else {
                $output->writeln("Updating password for: {$seed['username']}");
            }

            $user->setPassword($this->hasher->hashPassword($user, $seed['password']));
        }

        $this->em->flush();
        $output->writeln('Done.');

        return Command::SUCCESS;
    }
}