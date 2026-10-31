<?php

declare(strict_types=1);

namespace Database\DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261030024224 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE users ADD birth_date DATE DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE users ADD gender VARCHAR(10) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE users ADD about TEXT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE users ADD contacts JSON DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE users ADD socials JSON DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE users ADD profile_visibility JSON DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE users ADD last_name VARCHAR(100) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE users ADD first_name VARCHAR(100) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE users ADD middle_name VARCHAR(100) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE users ADD avatar_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE users ADD CONSTRAINT FK_1483A5E986383B10 FOREIGN KEY (avatar_id) REFERENCES images (id) ON DELETE SET NULL NOT DEFERRABLE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_1483A5E986383B10 ON users (avatar_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE users DROP CONSTRAINT FK_1483A5E986383B10
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_1483A5E986383B10
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE users DROP birth_date
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE users DROP gender
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE users DROP about
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE users DROP contacts
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE users DROP socials
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE users DROP profile_visibility
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE users DROP last_name
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE users DROP first_name
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE users DROP middle_name
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE users DROP avatar_id
        SQL);
    }
}
