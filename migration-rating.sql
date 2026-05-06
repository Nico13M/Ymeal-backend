-- Migration: Add Rating entity and ratings relationship to Recipe
-- Date: 2026-05-06

-- Créer la table rating
CREATE TABLE rating (
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
);

-- Créer les indices
CREATE INDEX IDX_DC3B0178A76ED395 ON rating (user_id);
CREATE INDEX IDX_DC3B017859D8A214 ON rating (recipe_id);

-- Insérer la migration dans la table doctrine_migration_versions
INSERT INTO doctrine_migration_versions (version, executed_at, execution_time, status)
VALUES ('DoctrineMigrations\\Version20260506000000', NOW(), 0, 'executed');
