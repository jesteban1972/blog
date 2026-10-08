<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260928073302 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE community_comments CHANGE content content LONGTEXT NOT NULL, CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE community_comments ADD CONSTRAINT FK_ADD8530D727ACA70 FOREIGN KEY (parent_id) REFERENCES community_comments (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE community_comments RENAME INDEX idx_comment_user TO IDX_ADD8530DA76ED395');
        $this->addSql('ALTER TABLE community_comments RENAME INDEX idx_comment_parent TO IDX_ADD8530D727ACA70');
        $this->addSql('ALTER TABLE posts ADD rating SMALLINT UNSIGNED DEFAULT 0 NOT NULL, ADD is_favorite TINYINT DEFAULT 0 NOT NULL, CHANGE diffusio diffusio INT DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE posts RENAME INDEX fk_posts_category TO IDX_885DBAFA12469DE2');
        $this->addSql('ALTER TABLE posts RENAME INDEX fk_posts_user TO IDX_885DBAFAA76ED395');
        $this->addSql('ALTER TABLE users CHANGE lists_order lists_order SMALLINT DEFAULT NULL, CHANGE prefer_markdown prefer_markdown TINYINT DEFAULT 1 NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE community_comments DROP FOREIGN KEY FK_ADD8530D727ACA70');
        $this->addSql('ALTER TABLE community_comments CHANGE content content TEXT NOT NULL, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE community_comments RENAME INDEX idx_add8530d727aca70 TO idx_comment_parent');
        $this->addSql('ALTER TABLE community_comments RENAME INDEX idx_add8530da76ed395 TO idx_comment_user');
        $this->addSql('ALTER TABLE posts DROP rating, DROP is_favorite, CHANGE diffusio diffusio TINYINT DEFAULT NULL, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE updated_at updated_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE posts RENAME INDEX idx_885dbafa12469de2 TO fk_posts_category');
        $this->addSql('ALTER TABLE posts RENAME INDEX idx_885dbafaa76ed395 TO fk_posts_user');
        $this->addSql('ALTER TABLE users CHANGE lists_order lists_order SMALLINT DEFAULT 1, CHANGE prefer_markdown prefer_markdown TINYINT DEFAULT 1, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE updated_at updated_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
    }
}
