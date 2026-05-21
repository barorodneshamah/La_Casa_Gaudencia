<?php

namespace App\ApiPlatform;

use App\Entity\Reservation;
use App\Entity\User;
use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

class ReservationCollectionExtension implements QueryCollectionExtensionInterface
{
    public function __construct(private Security $security) {}

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = []
    ): void {
        if ($resourceClass !== Reservation::class) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        // Admins and staff see all reservations
        if ($this->security->isGranted('ROLE_ADMIN') || $this->security->isGranted('ROLE_STAFF')) {
            return;
        }

        // Guests only see their own reservations
        $rootAlias = $queryBuilder->getRootAliases()[0];
        $queryBuilder
            ->andWhere(sprintf('%s.guest = :current_user', $rootAlias))
            ->setParameter('current_user', $user);
    }
}
