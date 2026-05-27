<?php

namespace App\Service;

class AiRecipeParserService
{
    private const DIFFICULTY_MAP = [
        'facile'    => 'easy',
        'débutant'  => 'easy',
        'debutant'  => 'easy',
        'moyen'     => 'medium',
        'intermédiaire' => 'medium',
        'intermediaire' => 'medium',
        'difficile' => 'hard',
        'avancé'    => 'hard',
        'avance'    => 'hard',
    ];

    public function parse(string $text): array
    {
        return [
            'name'        => $this->extractName($text),
            'description' => $this->extractIngredients($text),
            'duration'    => $this->extractDuration($text),
            'difficulty'  => $this->extractDifficulty($text),
            'servings'    => $this->extractServings($text),
            'steps'       => $this->extractSteps($text),
        ];
    }

    private function extractName(string $text): string
    {
        if (preg_match('/[-*]\s*Nom\s*:\s*(.+)/iu', $text, $m)) {
            return trim($m[1]);
        }

        // Fallback : première ligne non-vide et non-titre
        foreach (explode("\n", $text) as $line) {
            $line = trim($line, " \t#*-");
            if ($line !== '' && !str_starts_with($line, 'Recette')) {
                return $line;
            }
        }

        return 'Recette IA';
    }

    private function extractDuration(string $text): ?int
    {
        if (preg_match('/Temps\s+total\s*:\s*(\d+)\s*min/iu', $text, $m)) {
            return (int) $m[1];
        }

        if (preg_match('/(\d+)\s*min/iu', $text, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    private function extractDifficulty(string $text): ?string
    {
        if (preg_match('/Niveau\s*:\s*(\S+)/iu', $text, $m)) {
            $raw = mb_strtolower(trim($m[1]));
            return self::DIFFICULTY_MAP[$raw] ?? 'easy';
        }

        return 'easy';
    }

    private function extractServings(string $text): int
    {
        if (preg_match('/Portions?\s*:\s*(\d+)/iu', $text, $m)) {
            return max(1, (int) $m[1]);
        }

        if (preg_match('/pour\s+(\d+)\s+personne/iu', $text, $m)) {
            return max(1, (int) $m[1]);
        }

        return 2;
    }

    private function extractIngredients(string $text): string
    {
        if (preg_match('/###\s+Ingr[eé]dients.*?\n(.*?)(?=###|\z)/isu', $text, $m)) {
            return trim($m[1]);
        }

        return trim($text);
    }

    private function extractSteps(string $text): array
    {
        if (!preg_match('/###\s+[Ée]tapes.*?\n(.*?)(?=###|\z)/isu', $text, $m)) {
            return [];
        }

        $block = trim($m[1]);
        $steps = [];

        foreach (explode("\n", $block) as $line) {
            // Lignes de type "1) ...", "1. ...", "- ..."
            $clean = preg_replace('/^\s*(\d+[.)]\s*|[-*]\s*)/', '', $line);
            $clean = trim($clean);
            if ($clean !== '') {
                $steps[] = $clean;
            }
        }

        return $steps;
    }
}
