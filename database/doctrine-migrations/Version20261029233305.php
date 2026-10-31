<?php

declare(strict_types=1);

namespace Database\DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261029233305 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE articles DROP CONSTRAINT fk_bfdd3168d823e37a
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_bfdd3168d823e37a
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE articles DROP section_id
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE articles ADD section_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE articles ADD CONSTRAINT fk_bfdd3168d823e37a FOREIGN KEY (section_id) REFERENCES article_sections (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_bfdd3168d823e37a ON articles (section_id)
        SQL);
    }
}
