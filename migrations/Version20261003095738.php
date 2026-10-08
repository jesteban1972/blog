<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261003095738 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE copulationes (id INT UNSIGNED AUTO_INCREMENT NOT NULL, post_id INT UNSIGNED NOT NULL, category_id INT UNSIGNED NOT NULL, INDEX IDX_F5D60F3E4B89032C (post_id), INDEX IDX_F5D60F3E12469DE2 (category_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE copulationes ADD CONSTRAINT FK_F5D60F3E4B89032C FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE copulationes ADD CONSTRAINT FK_F5D60F3E12469DE2 FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE posts DROP FOREIGN KEY `FK_885DBAFA12469DE2`');
        $this->addSql('ALTER TABLE posts DROP FOREIGN KEY `FK_885DBAFAA76ED395`');
        $this->addSql('DROP INDEX IDX_885DBAFA12469DE2 ON posts');
        $this->addSql('DROP INDEX IDX_885DBAFAA76ED395 ON posts');
        $this->addSql('ALTER TABLE posts DROP category_id, DROP user_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE copulationes DROP FOREIGN KEY FK_F5D60F3E4B89032C');
        $this->addSql('ALTER TABLE copulationes DROP FOREIGN KEY FK_F5D60F3E12469DE2');
        $this->addSql('DROP TABLE copulationes');
        $this->addSql('ALTER TABLE posts ADD category_id INT UNSIGNED DEFAULT NULL, ADD user_id INT UNSIGNED NOT NULL');
        $this->addSql('ALTER TABLE posts ADD CONSTRAINT `FK_885DBAFA12469DE2` FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE posts ADD CONSTRAINT `FK_885DBAFAA76ED395` FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_885DBAFA12469DE2 ON posts (category_id)');
        $this->addSql('CREATE INDEX IDX_885DBAFAA76ED395 ON posts (user_id)');
    }
}
