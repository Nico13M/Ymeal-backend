<?php

namespace App\Repository;

use App\Entity\Ingredient;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ingredient>
 */
class IngredientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ingredient::class);
    }
    
    public function ingredientFindOnly10(): array
    {
        return $this->createQueryBuilder('i')
          ->select('i.id', 'i.name', 'i.slug')
                ->orderBy('i.name', 'ASC')
                ->setMaxResults(10)
                ->getQuery()
                ->getArrayResult();
    }
    
    public function ingredientFindByName(string $name): array
    {
        return $this->createQueryBuilder('i')
            ->select('i.id', 'i.name', 'i.slug')
            ->where('LOWER(i.name) LIKE LOWER(:name)')
            ->setParameter('name', '%' . $name . '%')
            ->orderBy('i.name', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getArrayResult();
    }

    public function searchByName(string $query, int $limit = 20): array
    {
        return $this->createQueryBuilder('i')
            ->where('LOWER(i.name) LIKE LOWER(:query)')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('i.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    //    /**
    //     * @return Ingredient[] Returns an array of Ingredient objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('i')
    //            ->andWhere('i.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('i.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Ingredient
    //    {
    //        return $this->createQueryBuilder('i')
    //            ->andWhere('i.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    /**
     * Retourne l'ingrédient dont le nom est le plus similaire par trigramme pg_trgm.
     * Requiert CREATE EXTENSION pg_trgm sur la base de données.
     */
    public function findBySimilarity(string $name, float $threshold = 0.25): ?Ingredient
    {
        $sql = "
            SELECT id
            FROM ingredient
            WHERE similarity(LOWER(name), LOWER(:name)) > :threshold
            ORDER BY similarity(LOWER(name), LOWER(:name)) DESC
            LIMIT 1
        ";

        $conn   = $this->getEntityManager()->getConnection();
        $result = $conn->fetchOne($sql, ['name' => $name, 'threshold' => $threshold]);

        return $result ? $this->find((int) $result) : null;
    }

    /**
     * @param array $ids
     * @return Ingredient[]
     */
    public function findByIds(array $ids): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('i.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
