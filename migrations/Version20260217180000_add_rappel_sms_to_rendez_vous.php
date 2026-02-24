<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260217180000_add_rappel_sms_to_rendez_vous extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute rappel_sms_envoye_at sur rendez_vous pour les rappels SMS.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE rendez_vous ADD rappel_sms_envoye_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE rendez_vous DROP rappel_sms_envoye_at');
    }
}
