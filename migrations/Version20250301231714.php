<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250301231714 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE trajet ADD trip_id INT NOT NULL, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE trajet ADD CONSTRAINT FK_2B5BA98CA5BC2E0E FOREIGN KEY (trip_id) REFERENCES trip (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_2B5BA98CA5BC2E0E ON trajet (trip_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE trajet DROP FOREIGN KEY FK_2B5BA98CA5BC2E0E');
        $this->addSql('DROP INDEX IDX_2B5BA98CA5BC2E0E ON trajet');
        $this->addSql('ALTER TABLE trajet DROP trip_id, CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }
}
