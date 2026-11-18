<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Sécurité : rôles utilisateur (ROLE_ADMIN) et unicité de l'e-mail.
 *
 * ATTENTION : si deux comptes existants ont le même e-mail (à la casse près), la création
 * de l'index unique échoue. Dans ce cas, fusionner ou renommer les doublons puis relancer.
 */
final class Version20261117100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute user.roles et un index unique sur user.email (e-mails normalisés en minuscules)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD roles JSON NOT NULL DEFAULT \'[]\'');
        $this->addSql('ALTER TABLE "user" ALTER roles DROP DEFAULT');
        // L'application normalise les e-mails en minuscules : on aligne les comptes existants.
        $this->addSql('UPDATE "user" SET email = LOWER(TRIM(email))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON "user" (email)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_8D93D649E7927C74');
        $this->addSql('ALTER TABLE "user" DROP roles');
    }
}
