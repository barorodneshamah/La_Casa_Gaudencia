<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260524174807 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reservation RENAME INDEX idx_reservation_extension_of TO IDX_42C84955E9498740');
        $this->addSql('ALTER TABLE share_wall_comment RENAME INDEX idx_share_wall_comment_post TO IDX_FB6D31694B89032C');
        $this->addSql('ALTER TABLE share_wall_comment RENAME INDEX idx_share_wall_comment_author TO IDX_FB6D3169F675F31B');
        $this->addSql('ALTER TABLE share_wall_post ADD reservation_id INT DEFAULT NULL, ADD service_id INT DEFAULT NULL, CHANGE liked_by_ids liked_by_ids JSON NOT NULL');
        $this->addSql('ALTER TABLE share_wall_post RENAME INDEX idx_share_wall_post_author TO IDX_77D0D520F675F31B');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reservation RENAME INDEX idx_42c84955e9498740 TO IDX_reservation_extension_of');
        $this->addSql('ALTER TABLE share_wall_comment RENAME INDEX idx_fb6d3169f675f31b TO IDX_share_wall_comment_author');
        $this->addSql('ALTER TABLE share_wall_comment RENAME INDEX idx_fb6d31694b89032c TO IDX_share_wall_comment_post');
        $this->addSql('ALTER TABLE share_wall_post DROP reservation_id, DROP service_id, CHANGE liked_by_ids liked_by_ids JSON NOT NULL');
        $this->addSql('ALTER TABLE share_wall_post RENAME INDEX idx_77d0d520f675f31b TO IDX_share_wall_post_author');
    }
}
