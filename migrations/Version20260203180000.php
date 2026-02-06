<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajout de la colonne discriminator (discr) pour l’héritage SINGLE_TABLE User/Admin/Patient/Medcin/ParentUser.
 */
final class Version20260203180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout colonne discr sur user (Single Table Inheritance)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD discr VARCHAR(255) NOT NULL DEFAULT \'patient\'');
        $this->addSql("UPDATE user SET discr = 'admin' WHERE role = 'ROLE_ADMIN'");
        $this->addSql("UPDATE user SET discr = 'medcin' WHERE role = 'ROLE_MEDECIN'");
        $this->addSql("UPDATE user SET discr = 'parent_user' WHERE role = 'ROLE_PARENT'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user DROP discr');
    }
}
