<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250628125354 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE item_request DROP description, DROP created_at, CHANGE status status VARCHAR(20) DEFAULT \'pending\' NOT NULL');
        $this->addSql('ALTER TABLE place ADD lat DOUBLE PRECISION NOT NULL, ADD lng DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE trajet CHANGE route_data route_data JSON NOT NULL, CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE trajet CHANGE route_data route_data LONGTEXT NOT NULL, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE place DROP lat, DROP lng');
        $this->addSql('ALTER TABLE item_request ADD description LONGTEXT DEFAULT NULL, ADD created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE status status VARCHAR(20) NOT NULL');
    }
}
