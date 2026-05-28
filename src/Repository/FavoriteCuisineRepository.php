<?php

namespace App\Repository;

use App\Entity\FavoriteCuisine;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FavoriteCuisineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FavoriteCuisine::class);
    }

    public function findAll(): array
    {
        return $this->createQueryBuilder('fc')
            ->orderBy('fc.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}