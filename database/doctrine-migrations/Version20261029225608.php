<?php

declare(strict_types=1);

namespace Database\DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261029225608 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE article_sections ADD slug VARCHAR(140) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE article_sections ADD lft INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE article_sections ADD lvl INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE article_sections ADD rgt INT NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE article_sections ADD tree_root INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE article_sections ADD parent_id INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE article_sections ADD CONSTRAINT FK_7E7EB7F3A977936C FOREIGN KEY (tree_root) REFERENCES article_sections (id) ON DELETE CASCADE NOT DEFERRABLE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE article_sections ADD CONSTRAINT FK_7E7EB7F3727ACA70 FOREIGN KEY (parent_id) REFERENCES article_sections (id) ON DELETE CASCADE NOT DEFERRABLE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_7E7EB7F3989D9B62 ON article_sections (slug)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_7E7EB7F3A977936C ON article_sections (tree_root)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_7E7EB7F3727ACA70 ON article_sections (parent_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE article_sections DROP CONSTRAINT FK_7E7EB7F3A977936C
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE article_sections DROP CONSTRAINT FK_7E7EB7F3727ACA70
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX UNIQ_7E7EB7F3989D9B62
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_7E7EB7F3A977936C
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_7E7EB7F3727ACA70
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE article_sections DROP slug
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE article_sections DROP lft
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE article_sections DROP lvl
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE article_sections DROP rgt
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE article_sections DROP tree_root
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE article_sections DROP parent_id
        SQL);
    }
}
