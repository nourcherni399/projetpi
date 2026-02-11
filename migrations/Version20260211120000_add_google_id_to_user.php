<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260211120000_add_google_id_to_user extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add google_id to user for Google OAuth login.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD google_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649A4D60762 ON user (google_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_8D93D649A4D60762 ON user');
        $this->addSql('ALTER TABLE user DROP google_id');
    }
}
