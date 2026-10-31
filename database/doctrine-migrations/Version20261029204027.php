<?php

declare(strict_types=1);

namespace Database\DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261029204027 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename user_access to user_permissions while preserving data';
    }

    public function up(Schema $schema): void
    {
        $this->addSql($this->connection->getDatabasePlatform()->getRenameTableSQL('user_access', 'user_permissions'));
    }

    public function down(Schema $schema): void
    {
        $this->addSql($this->connection->getDatabasePlatform()->getRenameTableSQL('user_permissions', 'user_access'));
    }
}
