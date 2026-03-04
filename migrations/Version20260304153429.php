<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260304153429 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE recipe ADD difficulty VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE recipe ADD time INT DEFAULT NULL');
        $this->addSql('ALTER TABLE recipe ADD dish_type VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE recipe DROP difficulty');
        $this->addSql('ALTER TABLE recipe DROP time');
        $this->addSql('ALTER TABLE recipe DROP dish_type');
    }
}
