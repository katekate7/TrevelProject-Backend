<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250301231553 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE place ADD trip_id INT NOT NULL, CHANGE place_api_id place_api_id VARCHAR(255) NOT NULL, CHANGE imag_url image_url VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE place ADD CONSTRAINT FK_741D53CDA5BC2E0E FOREIGN KEY (trip_id) REFERENCES trip (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_741D53CDA5BC2E0E ON place (trip_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE place DROP FOREIGN KEY FK_741D53CDA5BC2E0E');
        $this->addSql('DROP INDEX IDX_741D53CDA5BC2E0E ON place');
        $this->addSql('ALTER TABLE place DROP trip_id, CHANGE place_api_id place_api_id INT NOT NULL, CHANGE image_url imag_url VARCHAR(255) DEFAULT NULL');
    }
}
