<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Corrige les valeurs invalides dans public_cible et niveau_difficulte
 * (ex. saisie erronée "efzbybbi") pour éviter MappingException à l'hydratation.
 */
final class Version20260205150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Corriger valeurs invalides public_cible et niveau_difficulte (remplacer par NULL)';
    }

    public function up(Schema $schema): void
    {
        // Mettre à NULL les public_cible qui ne sont pas dans la liste autorisée
        $this->addSql("UPDATE thematique SET public_cible = NULL WHERE public_cible IS NOT NULL AND public_cible NOT IN ('Enfant', 'Parent', 'Médecin', 'Éducateur', 'Aidant', 'Autre')");

        // Idem pour niveau_difficulte
        $this->addSql("UPDATE thematique SET niveau_difficulte = NULL WHERE niveau_difficulte IS NOT NULL AND niveau_difficulte NOT IN ('Débutant', 'Intermédiaire', 'Avancé')");
    }

    public function down(Schema $schema): void
    {
        // Irreversible : on ne peut pas retrouver les anciennes valeurs invalides
    }
}
