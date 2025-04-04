<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250224225933 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE item CHANGE importance_level importance_level VARCHAR(20) DEFAULT \'optional\' NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1F1B251E5E237E06 ON item (name)');
        $this->addSql('ALTER TABLE item_request ADD user_id INT NOT NULL, CHANGE status status VARCHAR(20) DEFAULT \'pending\' NOT NULL');
        $this->addSql('ALTER TABLE item_request ADD CONSTRAINT FK_30CAA9CBA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_30CAA9CBA76ED395 ON item_request (user_id)');
        $this->addSql('ALTER TABLE trip ADD user_id INT NOT NULL, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE trip ADD CONSTRAINT FK_7656F53BA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_7656F53BA76ED395 ON trip (user_id)');
        $this->addSql('ALTER TABLE trip_item ADD trip_id INT NOT NULL, ADD item_id INT NOT NULL, CHANGE is_checked is_checked TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE trip_item ADD CONSTRAINT FK_3423B675A5BC2E0E FOREIGN KEY (trip_id) REFERENCES trip (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE trip_item ADD CONSTRAINT FK_3423B675126F525E FOREIGN KEY (item_id) REFERENCES item (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_3423B675A5BC2E0E ON trip_item (trip_id)');
        $this->addSql('CREATE INDEX IDX_3423B675126F525E ON trip_item (item_id)');
        $this->addSql('ALTER TABLE user ADD created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', DROP createdat, CHANGE role role VARCHAR(20) DEFAULT \'user\' NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649F85E0677 ON user (username)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON user (email)');
        $this->addSql('ALTER TABLE weather DROP FOREIGN KEY FK_4CD0D36EA5BC2E0E');
        $this->addSql('ALTER TABLE weather CHANGE trip_id trip_id INT NOT NULL, CHANGE updated_at updated_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE weather ADD CONSTRAINT FK_4CD0D36EA5BC2E0E FOREIGN KEY (trip_id) REFERENCES trip (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_1F1B251E5E237E06 ON item');
        $this->addSql('ALTER TABLE item CHANGE importance_level importance_level VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE item_request DROP FOREIGN KEY FK_30CAA9CBA76ED395');
        $this->addSql('DROP INDEX IDX_30CAA9CBA76ED395 ON item_request');
        $this->addSql('ALTER TABLE item_request DROP user_id, CHANGE status status VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE trip DROP FOREIGN KEY FK_7656F53BA76ED395');
        $this->addSql('DROP INDEX IDX_7656F53BA76ED395 ON trip');
        $this->addSql('ALTER TABLE trip DROP user_id, CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('DROP INDEX UNIQ_8D93D649F85E0677 ON user');
        $this->addSql('DROP INDEX UNIQ_8D93D649E7927C74 ON user');
        $this->addSql('ALTER TABLE user ADD createdat DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', DROP created_at, CHANGE role role VARCHAR(20) NOT NULL');
        $this->addSql('ALTER TABLE trip_item DROP FOREIGN KEY FK_3423B675A5BC2E0E');
        $this->addSql('ALTER TABLE trip_item DROP FOREIGN KEY FK_3423B675126F525E');
        $this->addSql('DROP INDEX IDX_3423B675A5BC2E0E ON trip_item');
        $this->addSql('DROP INDEX IDX_3423B675126F525E ON trip_item');
        $this->addSql('ALTER TABLE trip_item DROP trip_id, DROP item_id, CHANGE is_checked is_checked TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE weather DROP FOREIGN KEY FK_4CD0D36EA5BC2E0E');
        $this->addSql('ALTER TABLE weather CHANGE trip_id trip_id INT DEFAULT NULL, CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE weather ADD CONSTRAINT FK_4CD0D36EA5BC2E0E FOREIGN KEY (trip_id) REFERENCES trip (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
    }
}
