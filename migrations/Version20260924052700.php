<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260924052700 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'drop results_per_page column from users table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP results_per_page');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD results_per_page SMALLINT DEFAULT 25');
    }
}
