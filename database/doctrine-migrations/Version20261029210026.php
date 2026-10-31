<?php

declare(strict_types=1);

namespace Database\DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261029210026 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE articles ALTER is_published SET DEFAULT false
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX uniq_84f605faa76ed395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_permissions DROP is_admin
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_permissions ALTER role_id SET NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_84F605FAA76ED395 ON user_permissions (user_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE articles ALTER is_published SET DEFAULT true
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_84F605FAA76ED395
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_permissions ADD is_admin BOOLEAN DEFAULT false NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_permissions ALTER role_id DROP NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_84f605faa76ed395 ON user_permissions (user_id)
        SQL);
    }
}
