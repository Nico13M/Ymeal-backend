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

    /**
     * Récupère les ratings d'une recette avec les informations utilisateur
     */
    public function findByRecipeWithUser(\App\Entity\Recipe $recipe): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.user', 'u')
            ->addSelect('u')
            ->where('r.recipe = :recipe')
            ->setParameter('recipe', $recipe)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère le rating d'un utilisateur pour une recette
     */
    public function findOneByUserAndRecipe(\App\Entity\User $user, \App\Entity\Recipe $recipe): ?\App\Entity\Rating
    {
        return $this->createQueryBuilder('r')
            ->where('r.user = :user AND r.recipe = :recipe')
            ->setParameter('user', $user)
            ->setParameter('recipe', $recipe)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Récupère les stats d'une recette (moyenne, count, distribution)
     */
    public function getStatsForRecipe(\App\Entity\Recipe $recipe): array
    {
        $qb = $this->createQueryBuilder('r')
            ->where('r.recipe = :recipe')
            ->setParameter('recipe', $recipe);

        $ratings = $qb->getQuery()->getResult();

        if (empty($ratings)) {
            return [
                'average' => 0,
                'count' => 0,
                'distribution' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
            ];
        }

        // Calculer la distribution
        $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        $sum = 0;

        foreach ($ratings as $rating) {
            $value = $rating->getRating();
            $distribution[$value]++;
            $sum += $value;
        }

        return [
            'average' => round($sum / count($ratings), 2),
            'count' => count($ratings),
            'distribution' => $distribution,
        ];
    }
}