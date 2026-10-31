<?php

declare(strict_types=1);

namespace Database\DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261029162913 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create Laravel service tables';
    }

    public function up(Schema $schema): void
    {
        $tokens = $schema->createTable('password_reset_tokens');
        $tokens->addColumn('email', 'string', ['length' => 255]);
        $tokens->addColumn('token', 'string', ['length' => 255]);
        $tokens->addColumn('created_at', 'datetime', ['notnull' => false]);
        $tokens->setPrimaryKey(['email']);

        $failed = $schema->createTable('failed_jobs');
        $failed->addColumn('id', 'bigint', ['autoincrement' => true]);
        $failed->addColumn('uuid', 'string', ['length' => 255]);
        $failed->addColumn('connection', 'text');
        $failed->addColumn('queue', 'text');
        $failed->addColumn('payload', 'text');
        $failed->addColumn('exception', 'text');
        $failed->addColumn('failed_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP']);
        $failed->setPrimaryKey(['id']);
        $failed->addUniqueIndex(['uuid']);

        $batches = $schema->createTable('job_batches');
        $batches->addColumn('id', 'string', ['length' => 255]);
        $batches->addColumn('name', 'string', ['length' => 255]);
        $batches->addColumn('total_jobs', 'integer');
        $batches->addColumn('pending_jobs', 'integer');
        $batches->addColumn('failed_jobs', 'integer');
        $batches->addColumn('failed_job_ids', 'text');
        $batches->addColumn('options', 'text', ['notnull' => false]);
        $batches->addColumn('cancelled_at', 'integer', ['notnull' => false]);
        $batches->addColumn('created_at', 'integer');
        $batches->addColumn('finished_at', 'integer', ['notnull' => false]);
        $batches->setPrimaryKey(['id']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('password_reset_tokens');
        $schema->dropTable('failed_jobs');
        $schema->dropTable('job_batches');
    }
}
