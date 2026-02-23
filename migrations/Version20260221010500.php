<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260221010500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email verification fields for user registration confirmation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD email_verification_token VARCHAR(64) DEFAULT NULL, ADD email_verification_expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD email_verified_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649AFD509CC ON user (email_verification_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_8D93D649AFD509CC ON user');
        $this->addSql('ALTER TABLE user DROP email_verification_token, DROP email_verification_expires_at, DROP email_verified_at');
    }
}

