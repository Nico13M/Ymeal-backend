<?php

namespace App\Service;

use App\Entity\Ingredient;
use App\Entity\Units;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class IngredientService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Create a new ingredient from data
     *
     * @param array $data
     * @return Ingredient|JsonResponse
     */
    public function createIngredient(array $data): Ingredient|JsonResponse
    {
        if (empty($data['name'])) {
            return new JsonResponse(['error' => 'Le nom est obligatoire'], 400);
        }

        $existing = $this->entityManager->getRepository(Ingredient::class)->findOneBy(['name' => $data['name']]);
        if ($existing) {
          return $existing;
        }

        $ingredient = new Ingredient();
        $ingredient->setName(trim($data['name']));

        if (!empty($data['units_id'])) {
            $unit = $this->entityManager->getRepository(Units::class)->find($data['units_id']);
            if (!$unit) {
                return new JsonResponse(['error' => 'Unité introuvable'], 404);
            }
            $ingredient->setUnits($unit);
        }

        $this->entityManager->persist($ingredient);
        $this->entityManager->flush();

        return $ingredient;
    }

    /**
     * Update an existing ingredient with provided data
     *
     * @param Ingredient $ingredient
     * @param array $data
     * @return Ingredient|JsonResponse
     */
    public function updateIngredient(Ingredient $ingredient, array $data): Ingredient|JsonResponse
    {
        if (isset($data['name']) && !empty($data['name'])) {
            $ingredient->setName(trim($data['name']));
        }

        if (array_key_exists('units_id', $data)) {
            if ($data['units_id'] === null) {
                $ingredient->setUnits(null);
            } else {
                $unit = $this->entityManager->getRepository(Units::class)->find($data['units_id']);
                if (!$unit) {
                    return new JsonResponse(['error' => 'Unité introuvable'], 404);
                }
                $ingredient->setUnits($unit);
            }
        }

        $this->entityManager->flush();

        return $ingredient;
    }

    /**
     * Delete an ingredient
     */
    public function deleteIngredient(Ingredient $ingredient): void
    {
        $this->entityManager->remove($ingredient);
        $this->entityManager->flush();
    }

    /**
     * Serialize an ingredient to array
     */
    public function serializeIngredient(Ingredient $ingredient): array
    {
        return [
            'id' => $ingredient->getId(),
            'name' => $ingredient->getName(),
            'slug' => $ingredient->getSlug(),
            'image' => $ingredient->getImageSmallUrl(),
            'category' => $ingredient->getCategoriesTags(),
            'units' => $ingredient->getUnits() ? [
                'id' => $ingredient->getUnits()->getId(),
                'name' => $ingredient->getUnits()->getName(),
                'symbol' => $ingredient->getUnits()->getSymbol(),
            ] : null,
            'created_at' => $ingredient->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updated_at' => $ingredient->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
