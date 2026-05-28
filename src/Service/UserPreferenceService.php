<?php

namespace App\Service;

use App\Entity\Allergy;
use App\Entity\Diet;
use App\Entity\FavoriteCuisine;
use App\Entity\Ingredient;
use App\Entity\MonthlyBudget;
use App\Entity\User;
use App\Entity\UserPersonCount;
use Doctrine\ORM\EntityManagerInterface;

class UserPreferenceService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {}

    // ============================================================
    // RÉGIMES ALIMENTAIRES
    // ============================================================

    public function getDiets(User $user): array
    {
        return array_values(
            $user->getDiets()->map(fn($diet) => [
                'id'   => $diet->getId(),
                'name' => $diet->getName(),
            ])->toArray()
        );
    }

    public function setDiets(User $user, array $dietIds): void
    {
        foreach ($user->getDiets() as $diet) {
            $user->removeDiet($diet);
        }

        foreach ($dietIds as $id) {
            $diet = $this->em->getRepository(Diet::class)->find($id);
            if ($diet) $user->addDiet($diet);
        }

        $this->em->flush();
    }

    // ============================================================
    // BUDGET MENSUEL
    // ============================================================

    public function getBudget(User $user): array
    {
        $budget = $user->getMonthlyBudget();

        return ['amount' => $budget ? (float) $budget->getAmount() : null];
    }

    public function setBudget(User $user, float $amount): array
    {
        $budget = $user->getMonthlyBudget() ?? new MonthlyBudget();
        $budget->setUser($user);
        $budget->setAmount((string) $amount);

        $this->em->persist($budget);
        $this->em->flush();

        return ['amount' => (float) $budget->getAmount()];
    }

    // ============================================================
    // NOMBRE DE PERSONNES
    // ============================================================

    public function getPersonCount(User $user): array
    {
        return ['count' => $user->getPersonCount()?->getCount()];
    }

    public function setPersonCount(User $user, int $count): array
    {
        $personCount = $user->getPersonCount() ?? new UserPersonCount();
        $personCount->setUser($user);
        $personCount->setCount($count);

        $this->em->persist($personCount);
        $this->em->flush();

        return ['count' => $personCount->getCount()];
    }

    // ============================================================
    // CUISINES FAVORITES
    // ============================================================

    public function getFavoriteCuisines(User $user): array
    {
        return array_values(
            $user->getFavoriteCuisines()->map(fn($c) => [
                'id'   => $c->getId(),
                'name' => $c->getName(),
            ])->toArray()
        );
    }

    public function setFavoriteCuisines(User $user, array $cuisineIds): void
    {
        foreach ($user->getFavoriteCuisines() as $cuisine) {
            $user->removeFavoriteCuisine($cuisine);
        }

        foreach ($cuisineIds as $id) {
            $cuisine = $this->em->getRepository(FavoriteCuisine::class)->find($id);
            if ($cuisine) $user->addFavoriteCuisine($cuisine);
        }

        $this->em->flush();
    }

    // ============================================================
    // INGRÉDIENTS À ÉVITER (BLACKLIST)
    // ============================================================

    public function getBlacklist(User $user): array
    {
        return array_values(
            $user->getUserIngredientsBlacklist()->map(fn($ing) => [
                'id'   => $ing->getId(),
                'name' => $ing->getName(),
            ])->toArray()
        );
    }

    public function setBlacklist(User $user, array $ingredientIds): void
    {
        foreach ($user->getUserIngredientsBlacklist() as $ingredient) {
            $user->removeUserIngredientsBlacklist($ingredient);
        }

        foreach ($ingredientIds as $id) {
            $ingredient = $this->em->getRepository(Ingredient::class)->find($id);
            if ($ingredient) $user->addUserIngredientsBlacklist($ingredient);
        }

        $this->em->flush();
    }

    // ============================================================
    // ALLERGIES
    // ============================================================

    public function getAllergies(User $user): array
    {
        return array_values(
            $user->getAllergies()->map(fn($a) => [
                'id'   => $a->getId(),
                'name' => $a->getName(),
            ])->toArray()
        );
    }

    public function setAllergies(User $user, array $allergyIds): void
    {
        foreach ($user->getAllergies() as $allergy) {
            $user->removeAllergy($allergy);
        }

        foreach ($allergyIds as $id) {
            $allergy = $this->em->getRepository(Allergy::class)->find($id);
            if ($allergy) $user->addAllergy($allergy);
        }

        $this->em->flush();
    }
}