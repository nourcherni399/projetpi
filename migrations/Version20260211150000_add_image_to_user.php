<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260211150000_add_image_to_user extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add image column to user (photo de profil).';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $table = $platform->quoteIdentifier('user');
        $this->addSql(sprintf('ALTER TABLE %s ADD image VARCHAR(255) DEFAULT NULL', $table));
    }

    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $table = $platform->quoteIdentifier('user');
        $this->addSql(sprintf('ALTER TABLE %s DROP image', $table));
    }
}
