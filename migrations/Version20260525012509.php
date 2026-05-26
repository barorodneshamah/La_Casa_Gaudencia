<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260525012509 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE food ADD is_offer TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE room ADD is_offer TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE spa ADD is_offer TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE tour ADD is_offer TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE food DROP is_offer');
        $this->addSql('ALTER TABLE room DROP is_offer');
        $this->addSql('ALTER TABLE spa DROP is_offer');
        $this->addSql('ALTER TABLE tour DROP is_offer');
    }
}
