<?php

namespace App\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

class VectorType extends Type
{
    public const NAME = 'vector';

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'vector';
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?array
    {
        if ($value === null) {
            return null;
        }

        // Convertir la chaîne PostgreSQL vector en array PHP
        // Format PostgreSQL: "[0.1, 0.2, 0.3]"
        if (is_string($value)) {
            // Supprimer les crochets et diviser par virgule
            $value = trim($value, '[]');
            return array_map('floatval', array_map('trim', explode(',', $value)));
        }

        return $value;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            // Convertir array PHP en format PostgreSQL vector
            return '[' . implode(', ', $value) . ']';
        }

        return $value;
    }
}
