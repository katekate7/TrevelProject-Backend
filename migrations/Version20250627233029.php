<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Додаємо опис і колонку created_at коректно.
 */
final class Version20250627233029 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add description & created_at to item_request (з дефолтним CURRENT_TIMESTAMP).';
    }

    public function up(Schema $schema): void
    {
        // ➊  description — як і було  
        // ➋  created_at — NOT NULL + DEFAULT CURRENT_TIMESTAMP
        // ➌  status — без змін
        $this->addSql(<<<'SQL'
ALTER TABLE item_request
    ADD description LONGTEXT DEFAULT NULL,
    ADD created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        COMMENT '(DC2Type:datetime_immutable)',
    CHANGE status status VARCHAR(20) NOT NULL
SQL);
    }

    public function down(Schema $schema): void
    {
        // скасовуємо зміни
        $this->addSql(<<<'SQL'
ALTER TABLE item_request
    DROP description,
    DROP created_at,
    CHANGE status status VARCHAR(20) DEFAULT 'pending' NOT NULL
SQL);
    }
}
