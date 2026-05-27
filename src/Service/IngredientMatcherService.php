<?php

namespace App\Service;

use App\Entity\Ingredient;
use App\Repository\IngredientRepository;

class IngredientMatcherService
{
    public function __construct(
        private readonly IngredientRepository $ingredientRepository,
    ) {}

    /**
     * Parse le bloc Markdown des ingrédients d'une recette IA et retourne les correspondances.
     *
     * @return array<array{ingredient: Ingredient|null, quantity: float, unit: string}>
     */
    public function matchFromText(string $text): array
    {
        $lines   = $this->parseIngredientLines($text);
        $results = [];

        foreach ($lines as $line) {
            $results[] = [
                'ingredient' => $this->ingredientRepository->findBySimilarity($line['name']),
                'quantity'   => $line['quantity'],
                'unit'       => $line['unit'],
            ];
        }

        return $results;
    }

    /**
     * Extrait les lignes d'ingrédients du bloc ### Ingrédients du texte IA.
     * Format attendu : "- Blancs de poulet : 2 pièces" ou "- Riz basmati : 150g"
     *
     * @return array<array{name: string, quantity: float, unit: string}>
     */
    private function parseIngredientLines(string $text): array
    {
        if (preg_match('/###\s+Ingr[eé]dients.*?\n([\s\S]*?)(?=###|$)/i', $text, $blockMatch)) {
            $block = $blockMatch[1];
        } else {
            $block = $text;
        }

        $results = [];

        foreach (explode("\n", $block) as $line) {
            $line = trim($line);
            if (!preg_match(
                '/^[-*]\s*(.+?)\s*:\s*([\d.,]+)\s*([a-zA-Zàéèêëîïôùûü°\s\/]+)?/u',
                $line,
                $m
            )) {
                continue;
            }

            $results[] = [
                'name'     => trim($m[1]),
                'quantity' => (float) str_replace(',', '.', $m[2]),
                'unit'     => isset($m[3]) ? trim($m[3]) : '',
            ];
        }

        return $results;
    }
}
