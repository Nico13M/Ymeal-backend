<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260527120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Active l\'extension pg_trgm pour la recherche par similarité trigramme';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE EXTENSION IF NOT EXISTS pg_trgm');
    }

    public function down(Schema $schema): void
    {
        // Intentionnellement vide — on ne désactive pas une extension PostgreSQL
    }
}
