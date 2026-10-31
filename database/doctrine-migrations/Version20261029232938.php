<?php

declare(strict_types=1);

namespace Database\DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261029232938 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE article_section_links (article_id INT NOT NULL, section_id INT NOT NULL, PRIMARY KEY (article_id, section_id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_C10E29D97294869C ON article_section_links (article_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_C10E29D9D823E37A ON article_section_links (section_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE article_section_links ADD CONSTRAINT FK_C10E29D97294869C FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE NOT DEFERRABLE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE article_section_links ADD CONSTRAINT FK_C10E29D9D823E37A FOREIGN KEY (section_id) REFERENCES article_sections (id) ON DELETE CASCADE NOT DEFERRABLE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE article_section_links DROP CONSTRAINT FK_C10E29D97294869C
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE article_section_links DROP CONSTRAINT FK_C10E29D9D823E37A
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE article_section_links
        SQL);
    }
}
