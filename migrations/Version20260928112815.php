<?php
declare(strict_types=1);
// file ~/Sites/blog/migrations/Version20260928112815.php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260928112815 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'refactor category id and foreign keys from varchar(10) to unsigned int auto_increment';
    }

    public function up(Schema $schema): void
    {
        // 1. drop existing foreign keys pointing to categories(id)
        $this->addSql('ALTER TABLE categories DROP FOREIGN KEY `fk_category_parent`');
        $this->addSql('ALTER TABLE posts DROP FOREIGN KEY `fk_posts_category`');

        // 2. drop old parent_code index on categories
        $this->addSql('DROP INDEX IDX_3AF346684AF6062C ON categories');

        // 3. update columns on categories and posts
        $this->addSql('ALTER TABLE categories ADD parent_id INT UNSIGNED DEFAULT NULL, DROP parent_code, CHANGE id id INT UNSIGNED AUTO_INCREMENT NOT NULL');$this->addSql('ALTER TABLE posts CHANGE category_id category_id INT UNSIGNED DEFAULT NULL');

        // 4. recreate foreign keys and indexes
        $this->addSql('ALTER TABLE categories ADD CONSTRAINT FK_3AF34668727ACA70 FOREIGN KEY (parent_id) REFERENCES categories (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_3AF34668727ACA70 ON categories (parent_id)');$this->addSql('ALTER TABLE posts ADD CONSTRAINT FK_885DBAFAF100C1A FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE categories DROP FOREIGN KEY FK_3AF34668727ACA70');
        $this->addSql('ALTER TABLE posts DROP FOREIGN KEY FK_885DBAFAF100C1A');$this->addSql('DROP INDEX IDX_3AF34668727ACA70 ON categories');

        $this->addSql('ALTER TABLE categories ADD parent_code VARCHAR(10) DEFAULT NULL, DROP parent_id, CHANGE id id VARCHAR(10) NOT NULL');$this->addSql('ALTER TABLE posts CHANGE category_id category_id VARCHAR(10) DEFAULT NULL');

        $this->addSql('ALTER TABLE categories ADD CONSTRAINT `fk_category_parent` FOREIGN KEY (parent_code) REFERENCES categories (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_3AF346684AF6062C ON categories (parent_code)');$this->addSql('ALTER TABLE posts ADD CONSTRAINT `fk_posts_category` FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL');
    }
}
