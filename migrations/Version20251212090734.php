<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251212090734 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE package ADD item_config JSON DEFAULT NULL');
        $this->addSql('DROP INDEX UNIQ_42C84955DE6156CF ON reservation');
        $this->addSql('ALTER TABLE reservation ADD tour_id INT DEFAULT NULL, ADD package_id INT DEFAULT NULL, ADD approved_by_id INT DEFAULT NULL, ADD tour_participants INT DEFAULT NULL, ADD tour_date DATE DEFAULT NULL, ADD food_items JSON DEFAULT NULL, ADD contact_phone VARCHAR(100) DEFAULT NULL, ADD approved_at DATETIME DEFAULT NULL, DROP service_type, DROP service_name, DROP service_details, DROP amount_paid, CHANGE check_in_date check_in_date DATE DEFAULT NULL, CHANGE number_of_guests number_of_guests INT DEFAULT NULL, CHANGE service_id room_id INT DEFAULT NULL, CHANGE reservation_number reservation_code VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C8495554177093 FOREIGN KEY (room_id) REFERENCES room (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C8495515ED8D43 FOREIGN KEY (tour_id) REFERENCES tour (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C84955F44CABFF FOREIGN KEY (package_id) REFERENCES package (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C849552D234F6A FOREIGN KEY (approved_by_id) REFERENCES user (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_42C84955612CFDF0 ON reservation (reservation_code)');
        $this->addSql('CREATE INDEX IDX_42C8495554177093 ON reservation (room_id)');
        $this->addSql('CREATE INDEX IDX_42C8495515ED8D43 ON reservation (tour_id)');
        $this->addSql('CREATE INDEX IDX_42C84955F44CABFF ON reservation (package_id)');
        $this->addSql('CREATE INDEX IDX_42C849552D234F6A ON reservation (approved_by_id)');
        $this->addSql('ALTER TABLE room ADD item_config JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE package DROP item_config');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C8495554177093');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C8495515ED8D43');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C84955F44CABFF');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C849552D234F6A');
        $this->addSql('DROP INDEX UNIQ_42C84955612CFDF0 ON reservation');
        $this->addSql('DROP INDEX IDX_42C8495554177093 ON reservation');
        $this->addSql('DROP INDEX IDX_42C8495515ED8D43 ON reservation');
        $this->addSql('DROP INDEX IDX_42C84955F44CABFF ON reservation');
        $this->addSql('DROP INDEX IDX_42C849552D234F6A ON reservation');
        $this->addSql('ALTER TABLE reservation ADD service_type VARCHAR(30) NOT NULL, ADD service_id INT DEFAULT NULL, ADD service_name VARCHAR(150) DEFAULT NULL, ADD service_details LONGTEXT DEFAULT NULL, ADD amount_paid NUMERIC(12, 2) NOT NULL, DROP room_id, DROP tour_id, DROP package_id, DROP approved_by_id, DROP tour_participants, DROP tour_date, DROP food_items, DROP contact_phone, DROP approved_at, CHANGE check_in_date check_in_date DATE NOT NULL, CHANGE number_of_guests number_of_guests INT NOT NULL, CHANGE reservation_code reservation_number VARCHAR(50) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_42C84955DE6156CF ON reservation (reservation_number)');
        $this->addSql('ALTER TABLE room DROP item_config');
    }
}
