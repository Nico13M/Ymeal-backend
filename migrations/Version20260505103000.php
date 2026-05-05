<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260505103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add/fill pseudo on user and enforce NOT NULL safely';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_name = 'user' AND column_name = 'pseudo'
    ) THEN
        ALTER TABLE "user" ADD COLUMN pseudo VARCHAR(45);
    END IF;

    UPDATE "user"
    SET pseudo = LEFT(COALESCE(NULLIF(firstname, ''), 'user') || '_' || id::text, 45)
    WHERE pseudo IS NULL OR BTRIM(pseudo) = '';

    ALTER TABLE "user" ALTER COLUMN pseudo SET NOT NULL;
END $$;
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_name = 'user' AND column_name = 'pseudo'
    ) THEN
        ALTER TABLE "user" ALTER COLUMN pseudo DROP NOT NULL;
    END IF;
END $$;
SQL);
    }
}
