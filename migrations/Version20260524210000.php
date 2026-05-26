<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260524210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add extension_of_id to reservation for extension requests';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation ADD extension_of_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_reservation_extension_of FOREIGN KEY (extension_of_id) REFERENCES reservation (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_reservation_extension_of ON reservation (extension_of_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_reservation_extension_of');
        $this->addSql('DROP INDEX IDX_reservation_extension_of ON reservation');
        $this->addSql('ALTER TABLE reservation DROP extension_of_id');
    }
}
