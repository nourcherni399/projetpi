<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260205140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normaliser public_cible et niveau_difficulte (casse/accents) pour correspondre aux enums PHP';
    }

    public function up(Schema $schema): void
    {
        // Étape 1 : étendre les ENUM pour accepter les deux écritures (éviter perte de données)
        $this->addSql("ALTER TABLE thematique MODIFY public_cible ENUM('enfant', 'parent', 'medecin', 'educateur', 'aidant', 'autre', 'Enfant', 'Parent', 'Médecin', 'Éducateur', 'Aidant', 'Autre') DEFAULT NULL");
        $this->addSql("ALTER TABLE thematique MODIFY niveau_difficulte ENUM('debutant', 'intermediaire', 'avance', 'Débutant', 'Intermédiaire', 'Avancé') DEFAULT NULL");

        // Étape 2 : normaliser les données existantes
        $this->addSql("UPDATE thematique SET public_cible = 'Enfant' WHERE public_cible = 'enfant'");
        $this->addSql("UPDATE thematique SET public_cible = 'Parent' WHERE public_cible = 'parent'");
        $this->addSql("UPDATE thematique SET public_cible = 'Médecin' WHERE public_cible = 'medecin'");
        $this->addSql("UPDATE thematique SET public_cible = 'Éducateur' WHERE public_cible = 'educateur'");
        $this->addSql("UPDATE thematique SET public_cible = 'Aidant' WHERE public_cible = 'aidant'");
        $this->addSql("UPDATE thematique SET public_cible = 'Autre' WHERE public_cible = 'autre'");

        $this->addSql("UPDATE thematique SET niveau_difficulte = 'Débutant' WHERE niveau_difficulte = 'debutant'");
        $this->addSql("UPDATE thematique SET niveau_difficulte = 'Intermédiaire' WHERE niveau_difficulte = 'intermediaire'");
        $this->addSql("UPDATE thematique SET niveau_difficulte = 'Avancé' WHERE niveau_difficulte = 'avance'");

        // Étape 3 : ENUM finaux (alignés sur les enums PHP)
        $this->addSql("ALTER TABLE thematique MODIFY public_cible ENUM('Enfant', 'Parent', 'Médecin', 'Éducateur', 'Aidant', 'Autre') DEFAULT NULL");
        $this->addSql("ALTER TABLE thematique MODIFY niveau_difficulte ENUM('Débutant', 'Intermédiaire', 'Avancé') DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE thematique MODIFY public_cible ENUM('enfant', 'parent', 'medecin', 'educateur', 'aidant', 'autre') DEFAULT NULL");
        $this->addSql("ALTER TABLE thematique MODIFY niveau_difficulte ENUM('debutant', 'intermediaire', 'avance') DEFAULT NULL");
    }
}
