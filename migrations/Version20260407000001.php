<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260407000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create contact_message table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE contact_message (id INT AUTO_INCREMENT NOT NULL, full_name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, phone VARCHAR(50) DEFAULT NULL, subject VARCHAR(255) NOT NULL, message LONGTEXT NOT NULL, status VARCHAR(20) NOT NULL DEFAULT \'unread\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', read_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', notes LONGTEXT DEFAULT NULL, ip_address VARCHAR(45) DEFAULT NULL, user_seen_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', push_sent_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE contact_message');
    }
}