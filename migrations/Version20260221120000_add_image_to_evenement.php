<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260221120000_add_image_to_evenement extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute image (affiche générée IA) à evenement.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE evenement ADD image VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE evenement DROP image');
    }
}
