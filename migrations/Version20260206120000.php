<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260206120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout confirmation_pin et confirmation_pin_expires_at sur user (activation par email)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD confirmation_pin VARCHAR(10) DEFAULT NULL, ADD confirmation_pin_expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP confirmation_pin, DROP confirmation_pin_expires_at');
    }
}
