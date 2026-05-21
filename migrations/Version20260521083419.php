<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260521083419 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reservation ADD spa_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C84955DF3CB247 FOREIGN KEY (spa_id) REFERENCES spa (id)');
        $this->addSql('CREATE INDEX IDX_42C84955DF3CB247 ON reservation (spa_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C84955DF3CB247');
        $this->addSql('DROP INDEX IDX_42C84955DF3CB247 ON reservation');
        $this->addSql('ALTER TABLE reservation DROP spa_id');
    }
}
