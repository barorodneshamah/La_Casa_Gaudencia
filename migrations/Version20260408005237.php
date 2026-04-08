<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260408005237 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE contact_reply (id INT AUTO_INCREMENT NOT NULL, contact_message_id INT NOT NULL, replied_by_id INT NOT NULL, reply_message LONGTEXT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_D082D6EF94C34ABE (contact_message_id), INDEX IDX_D082D6EFD6FBBEB5 (replied_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE contact_reply ADD CONSTRAINT FK_D082D6EF94C34ABE FOREIGN KEY (contact_message_id) REFERENCES contact_message (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE contact_reply ADD CONSTRAINT FK_D082D6EFD6FBBEB5 FOREIGN KEY (replied_by_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contact_reply DROP FOREIGN KEY FK_D082D6EF94C34ABE');
        $this->addSql('ALTER TABLE contact_reply DROP FOREIGN KEY FK_D082D6EFD6FBBEB5');
        $this->addSql('DROP TABLE contact_reply');
    }
}
