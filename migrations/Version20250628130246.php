<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250628130246 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE place DROP place_api_id, DROP description, DROP rating, DROP image_url');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE place ADD place_api_id VARCHAR(255) NOT NULL, ADD description LONGTEXT DEFAULT NULL, ADD rating DOUBLE PRECISION DEFAULT NULL, ADD image_url VARCHAR(255) DEFAULT NULL');
    }
}
