<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251211205709 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE package DROP item_configurations, DROP sort_order, CHANGE original_price original_price NUMERIC(10, 2) DEFAULT NULL, CHANGE is_featured is_featured TINYINT(1) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE package ADD item_configurations JSON DEFAULT NULL, ADD sort_order INT DEFAULT NULL, CHANGE original_price original_price NUMERIC(10, 2) NOT NULL, CHANGE is_featured is_featured TINYINT(1) DEFAULT NULL');
    }
}
