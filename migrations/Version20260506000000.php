<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260506000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Rating entity and ratings relationship to Recipe';
    }

    public function up(Schema $schema): void
    {
        // Créer la table rating
        $this->addSql('CREATE TABLE rating (
            id SERIAL PRIMARY KEY,
            user_id INT NOT NULL,
            recipe_id INT NOT NULL,
            rating SMALLINT NOT NULL,
            comment TEXT,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES "user"(id) ON DELETE CASCADE,
            FOREIGN KEY (recipe_id) REFERENCES recipe(id) ON DELETE CASCADE,
            UNIQUE(user_id, recipe_id)
        )');

        // Créer les indices
        $this->addSql('CREATE INDEX IDX_DC3B0178A76ED395 ON rating (user_id)');
        $this->addSql('CREATE INDEX IDX_DC3B017859D8A214 ON rating (recipe_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS rating');
    }
}
