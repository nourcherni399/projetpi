<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260225220000_add_email_to_rendez_vous extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les colonnes email, token_annulation, rappel_sms_envoye_at à rendez_vous';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('rendez_vous');

        if (!$table->hasColumn('email')) {
            $this->addSql('ALTER TABLE rendez_vous ADD COLUMN email VARCHAR(255) DEFAULT NULL');
        }
        if (!$table->hasColumn('token_annulation')) {
            $this->addSql('ALTER TABLE rendez_vous ADD COLUMN token_annulation VARCHAR(64) DEFAULT NULL');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_65E8AA0AEAA36C3A ON rendez_vous (token_annulation)');
        }
        if (!$table->hasColumn('rappel_sms_envoye_at')) {
            $this->addSql('ALTER TABLE rendez_vous ADD COLUMN rappel_sms_envoye_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('rendez_vous');
        if ($table->hasColumn('email')) {
            $this->addSql('ALTER TABLE rendez_vous DROP COLUMN email');
        }
        if ($table->hasColumn('token_annulation')) {
            $this->addSql('DROP INDEX UNIQ_65E8AA0AEAA36C3A ON rendez_vous');
            $this->addSql('ALTER TABLE rendez_vous DROP COLUMN token_annulation');
        }
        if ($table->hasColumn('rappel_sms_envoye_at')) {
            $this->addSql('ALTER TABLE rendez_vous DROP COLUMN rappel_sms_envoye_at');
        }
    }
}
