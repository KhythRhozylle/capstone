<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/** @extends ServiceEntityRepository<User> */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /** @return list<User> */
    public function findByRole(string $role): array
    {
        $users = 'ROLE_USER' === $role ? $this->findAll() : $this->roleCandidates([$role]);

        return array_values(array_filter($users, static fn (User $user) => \in_array($role, $user->getRoles(), true)));
    }

    /** @return list<User> */
    public function findStaff(): array
    {
        return array_values(array_filter($this->roleCandidates([
            'ROLE_STAFF', 'ROLE_GUIDANCE', 'ROLE_DIRECTOR', 'ROLE_VPSAS', 'ROLE_VPSAS_SECRETARIAT',
        ]), static fn (User $user) => $user->isStaff()));
    }

    /**
     * Narrow candidates in SQL before hydrating users. Keep the exact PHP role check
     * because JSON text comparisons can use a case-insensitive database collation.
     *
     * @param list<string> $roles
     * @return list<User>
     */
    private function roleCandidates(array $roles): array
    {
        $query = $this->createQueryBuilder('u')
            ->leftJoin('u.studentProfile', 'profile')->addSelect('profile')
            ->leftJoin('u.staffUnit', 'unit')->addSelect('unit')
            ->leftJoin('unit.directorate', 'directorate')->addSelect('directorate');
        $conditions = [];
        foreach ($roles as $index => $role) {
            $conditions[] = "u.roles LIKE :role$index ESCAPE '!'";
            $query->setParameter('role'.$index, '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], json_encode($role, JSON_THROW_ON_ERROR)).'%');
        }

        return $query->andWhere(implode(' OR ', $conditions))->getQuery()->getResult();
    }
}
