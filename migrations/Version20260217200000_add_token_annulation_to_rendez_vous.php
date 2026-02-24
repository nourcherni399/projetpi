<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260217200000_add_token_annulation_to_rendez_vous extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute token_annulation sur rendez_vous pour annulation/report par le patient (lien email/SMS).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE rendez_vous ADD token_annulation VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_RDV_TOKEN ON rendez_vous (token_annulation)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_RDV_TOKEN ON rendez_vous');
        $this->addSql('ALTER TABLE rendez_vous DROP token_annulation');
    }
}
