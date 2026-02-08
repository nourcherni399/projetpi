<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * @return User[]
     */
    public function findAllOrdered(string $order = 'asc'): array
    {
        $dir = strtolower($order) === 'desc' ? 'DESC' : 'ASC';
        return $this->createQueryBuilder('u')
            ->orderBy('u.nom', $dir)
            ->addOrderBy('u.prenom', $dir)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array{total: int, admins: int, medecins: int, patients: int, parents: int, actifs: int}
     */
    public function getStats(): array
    {
        $qb = $this->createQueryBuilder('u');
        $total = (int) $qb->select('COUNT(u.id)')->getQuery()->getSingleScalarResult();

        $admins = (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.role = :role')
            ->setParameter('role', UserRole::ADMIN)
            ->getQuery()
            ->getSingleScalarResult();
        $medecins = (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.role = :role')
            ->setParameter('role', UserRole::MEDECIN)
            ->getQuery()
            ->getSingleScalarResult();
        $patients = (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.role = :role')
            ->setParameter('role', UserRole::PATIENT)
            ->getQuery()
            ->getSingleScalarResult();
        $parents = (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.role = :role')
            ->setParameter('role', UserRole::PARENT)
            ->getQuery()
            ->getSingleScalarResult();
        $actifs = (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.isActive = :actif')
            ->setParameter('actif', true)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $total,
            'admins' => $admins,
            'medecins' => $medecins,
            'patients' => $patients,
            'parents' => $parents,
            'actifs' => $actifs,
        ];
    }
}
