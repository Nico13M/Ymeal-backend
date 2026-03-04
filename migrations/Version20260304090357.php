<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260304090357 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ingredient ADD units_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE ingredient ADD CONSTRAINT FK_6BAF787099387CE8 FOREIGN KEY (units_id) REFERENCES units (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_6BAF787099387CE8 ON ingredient (units_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ingredient DROP CONSTRAINT FK_6BAF787099387CE8');
        $this->addSql('DROP INDEX IDX_6BAF787099387CE8');
        $this->addSql('ALTER TABLE ingredient DROP units_id');
    }
}
