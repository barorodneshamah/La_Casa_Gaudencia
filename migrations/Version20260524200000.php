<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260524200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create share_wall_post and share_wall_comment tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE share_wall_post (
            id INT AUTO_INCREMENT NOT NULL,
            author_id INT NOT NULL,
            caption LONGTEXT NOT NULL,
            image_uri VARCHAR(255) DEFAULT NULL,
            service_tag VARCHAR(30) DEFAULT NULL,
            status VARCHAR(20) NOT NULL,
            is_pinned TINYINT(1) NOT NULL,
            liked_by_ids LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\',
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_share_wall_post_author (author_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE share_wall_comment (
            id INT AUTO_INCREMENT NOT NULL,
            post_id INT NOT NULL,
            author_id INT NOT NULL,
            author_name VARCHAR(100) NOT NULL,
            text LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_share_wall_comment_post (post_id),
            INDEX IDX_share_wall_comment_author (author_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE share_wall_post
            ADD CONSTRAINT FK_swp_author FOREIGN KEY (author_id) REFERENCES user (id)');

        $this->addSql('ALTER TABLE share_wall_comment
            ADD CONSTRAINT FK_swc_post FOREIGN KEY (post_id) REFERENCES share_wall_post (id) ON DELETE CASCADE,
            ADD CONSTRAINT FK_swc_author FOREIGN KEY (author_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE share_wall_comment DROP FOREIGN KEY FK_swc_post');
        $this->addSql('ALTER TABLE share_wall_comment DROP FOREIGN KEY FK_swc_author');
        $this->addSql('ALTER TABLE share_wall_post DROP FOREIGN KEY FK_swp_author');
        $this->addSql('DROP TABLE share_wall_comment');
        $this->addSql('DROP TABLE share_wall_post');
    }
}
