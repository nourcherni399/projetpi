<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Attribue les couleurs aux thématiques par nom.
 * Couleurs : #845EC2 (violet), #D65DB1 (magenta), #FF6F91 (corail), #FF9671 (orange clair), #FFC75F (jaune-orange).
 */
final class Version20260224140000_thematique_couleurs extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Attribue les couleurs de la palette aux thématiques (Culture & Arts, Famille & Loisirs, Éducation, Sensoriel, Technologie).';
    }

    public function up(Schema $schema): void
    {
        $map = [
            'Culture & Arts' => '#845EC2',
            'Famille & Loisirs' => '#D65DB1',
            'Éducation' => '#FFC75F',
            'Sensoriel' => '#FF6F91',
            'Technologie' => '#FF9671',
        ];

        foreach ($map as $nom => $couleur) {
            $this->addSql(
                'UPDATE thematique SET couleur = :couleur WHERE nom_thematique = :nom',
                ['couleur' => $couleur, 'nom' => $nom],
                ['couleur' => 'string', 'nom' => 'string']
            );
        }
    }

    public function down(Schema $schema): void
    {
        $noms = [
            'Culture & Arts',
            'Famille & Loisirs',
            'Éducation',
            'Sensoriel',
            'Technologie',
        ];
        foreach ($noms as $nom) {
            $this->addSql(
                'UPDATE thematique SET couleur = NULL WHERE nom_thematique = :nom',
                ['nom' => $nom],
                ['nom' => 'string']
            );
        }
    }
}
