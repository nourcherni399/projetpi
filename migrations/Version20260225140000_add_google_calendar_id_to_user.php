<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260225140000_add_google_calendar_id_to_user extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add google_calendar_id column to user table (Medcin entity)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD google_calendar_id VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP google_calendar_id');
    }
}
