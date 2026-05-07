<?php
namespace App\Repository;

use App\Entity\FrigoIngredient;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FrigoIngredientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FrigoIngredient::class);
    }

    public function countByFrigo(int $frigoId): int
    {
        return (int) $this->createQueryBuilder('fi')
            ->select('COUNT(fi.id)')
            ->where('fi.frigo = :frigoId')
            ->setParameter('frigoId', $frigoId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}