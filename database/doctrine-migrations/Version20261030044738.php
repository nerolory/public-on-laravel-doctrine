<?php

declare(strict_types=1);

namespace Database\DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261030044738 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE article_sections ADD seo_title VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE article_sections ADD seo_description VARCHAR(320) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE articles ADD seo_title VARCHAR(255) DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE articles ADD seo_description VARCHAR(320) DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE article_sections DROP seo_title
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE article_sections DROP seo_description
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE articles DROP seo_title
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE articles DROP seo_description
        SQL);
    }
}
