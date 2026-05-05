<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260505094500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalize ingredient nutrition columns to DOUBLE PRECISION for PostgreSQL compatibility';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ingredient ALTER COLUMN energy_100g TYPE DOUBLE PRECISION USING NULLIF(energy_100g::text, \'\')::double precision');
        $this->addSql('ALTER TABLE ingredient ALTER COLUMN fat_100g TYPE DOUBLE PRECISION USING NULLIF(fat_100g::text, \'\')::double precision');
        $this->addSql('ALTER TABLE ingredient ALTER COLUMN "saturated-fat_100g" TYPE DOUBLE PRECISION USING NULLIF("saturated-fat_100g"::text, \'\')::double precision');
        $this->addSql('ALTER TABLE ingredient ALTER COLUMN carbohydrates_100g TYPE DOUBLE PRECISION USING NULLIF(carbohydrates_100g::text, \'\')::double precision');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ingredient ALTER COLUMN energy_100g TYPE NUMERIC(10, 2) USING energy_100g::numeric(10, 2)');
        $this->addSql('ALTER TABLE ingredient ALTER COLUMN fat_100g TYPE NUMERIC(10, 2) USING fat_100g::numeric(10, 2)');
        $this->addSql('ALTER TABLE ingredient ALTER COLUMN "saturated-fat_100g" TYPE NUMERIC(10, 2) USING "saturated-fat_100g"::numeric(10, 2)');
        $this->addSql('ALTER TABLE ingredient ALTER COLUMN carbohydrates_100g TYPE NUMERIC(10, 2) USING carbohydrates_100g::numeric(10, 2)');
    }
}
