<?php

namespace App\Repository;

use App\Entity\Rating;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RatingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Rating::class);
    }

    public function findByRecipe(int $recipeId)
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.recipe = :recipe_id')
            ->setParameter('recipe_id', $recipeId)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByRecipeAndUser(int $recipeId, int $userId)
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.recipe = :recipe_id')
            ->andWhere('r.user = :user_id')
            ->setParameter('recipe_id', $recipeId)
            ->setParameter('user_id', $userId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
