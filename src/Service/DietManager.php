<?php

namespace App\Service;

use App\Entity\Diet;
use Doctrine\ORM\EntityManagerInterface;

class DietManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Get all diets
     */
    public function getAllDiets(): array
    {
        return $this->entityManager
            ->getRepository(Diet::class)
            ->findAll();
    }

    /**
     * Get a diet by id
     */
    public function getDietById(int $id): ?Diet
    {
        return $this->entityManager
            ->getRepository(Diet::class)
            ->find($id);
    }

    /**
     * Create a new diet
     */
    public function createDiet(string $name): Diet
    {
        $diet = new Diet();
        $diet->setName(trim($name));

        $this->entityManager->persist($diet);
        $this->entityManager->flush();

        return $diet;
    }

    /**
     * Update an existing diet
     */
    public function updateDiet(Diet $diet, string $name): Diet
    {
        $diet->setName(trim($name));

        $this->entityManager->flush();

        return $diet;
    }

    /**
     * Delete a diet
     */
    public function deleteDiet(Diet $diet): void
    {
        $this->entityManager->remove($diet);
        $this->entityManager->flush();
    }

    /**
     * Serialize a diet entity to array
     */
    public function serializeDiet(Diet $diet): array
    {
        return [
            'id' => $diet->getId(),
            'name' => $diet->getName(),
        ];
    }

    /**
     * Serialize multiple diets
     */
    public function serializeDiets(array $diets): array
    {
        return array_map(fn(Diet $diet) => $this->serializeDiet($diet), $diets);
    }

    /**
     * Associe un régime à un utilisateur
     */
    public function assignDietToUser(Diet $diet, $user): void
    {
        if (!$diet->getUserHasDiet()->contains($user)) {
            $diet->addUserHasDiet($user);
            $this->entityManager->flush();
        }
    }

    /**
     * Dissocie un régime d'un utilisateur
     */
    public function removeDietFromUser(Diet $diet, $user): void
    {
        if ($diet->getUserHasDiet()->contains($user)) {
            $diet->removeUserHasDiet($user);
            $this->entityManager->flush();
        }
    }
}
