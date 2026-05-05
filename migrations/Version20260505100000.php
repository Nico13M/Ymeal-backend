<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260505100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalize remaining ingredient nutrition NUMERIC columns without precision to DOUBLE PRECISION';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ingredient ALTER COLUMN sugars_100g TYPE DOUBLE PRECISION USING NULLIF(sugars_100g::text, \'\')::double precision');
        $this->addSql('ALTER TABLE ingredient ALTER COLUMN fiber_100g TYPE DOUBLE PRECISION USING NULLIF(fiber_100g::text, \'\')::double precision');
        $this->addSql('ALTER TABLE ingredient ALTER COLUMN proteins_100g TYPE DOUBLE PRECISION USING NULLIF(proteins_100g::text, \'\')::double precision');
        $this->addSql('ALTER TABLE ingredient ALTER COLUMN salt_100g TYPE DOUBLE PRECISION USING NULLIF(salt_100g::text, \'\')::double precision');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ingredient ALTER COLUMN sugars_100g TYPE NUMERIC(10, 2) USING sugars_100g::numeric(10, 2)');
        $this->addSql('ALTER TABLE ingredient ALTER COLUMN fiber_100g TYPE NUMERIC(10, 2) USING fiber_100g::numeric(10, 2)');
        $this->addSql('ALTER TABLE ingredient ALTER COLUMN proteins_100g TYPE NUMERIC(10, 2) USING proteins_100g::numeric(10, 2)');
        $this->addSql('ALTER TABLE ingredient ALTER COLUMN salt_100g TYPE NUMERIC(10, 2) USING salt_100g::numeric(10, 2)');
    }
}
