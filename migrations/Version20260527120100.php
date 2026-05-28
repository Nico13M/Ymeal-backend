<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260527120100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la colonne unit_label sur recipe_ingredient pour les unités libres générées par l\'IA';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recipe_ingredient ADD unit_label VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE recipe_ingredient DROP COLUMN unit_label');
    }
}
