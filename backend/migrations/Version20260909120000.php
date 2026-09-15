<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20260909120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the link table for the URL shortener MVP.';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('link');

        $table->addColumn('id', Types::INTEGER, [
            'autoincrement' => true,
            'notnull' => true,
        ]);
        $table->setPrimaryKey(['id']);

        $table->addColumn('slug', Types::STRING, [
            'length' => 64,
            'notnull' => true,
        ]);
        $table->addUniqueIndex(['slug'], 'uniq_link_slug');

        $table->addColumn('original_url', Types::TEXT, [
            'notnull' => true,
        ]);

        $table->addColumn('clicks', Types::INTEGER, [
            'notnull' => true,
            'default' => 0,
        ]);

        $table->addColumn('created_at', Types::DATETIME_IMMUTABLE, [
            'notnull' => true,
        ]);

        $table->addColumn('updated_at', Types::DATETIME_IMMUTABLE, [
            'notnull' => true,
        ]);

        $table->addIndex(['created_at'], 'idx_link_created_at');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('link');
    }
}
