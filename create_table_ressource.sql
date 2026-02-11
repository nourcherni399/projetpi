-- Création de la table ressource (corrige l'erreur Table 'pidb.ressource' doesn't exist)
-- À exécuter dans phpMyAdmin > base pidb > onglet SQL : coller et exécuter.

-- Étape 1 : créer la table
CREATE TABLE IF NOT EXISTS ressource (
    id INT AUTO_INCREMENT NOT NULL,
    module_id INT NOT NULL,
    titre VARCHAR(255) NOT NULL,
    type_ressource VARCHAR(50) DEFAULT NULL,
    fichier VARCHAR(255) DEFAULT NULL,
    contenu VARCHAR(255) DEFAULT NULL,
    date_creation DATETIME NOT NULL,
    datemodif DATETIME NOT NULL,
    ordre INT DEFAULT NULL,
    is_active TINYINT(1) NOT NULL,
    PRIMARY KEY(id),
    INDEX IDX_ressource_module (module_id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

-- Étape 2 : clé étrangère vers module (si la table module existe)
ALTER TABLE ressource
ADD CONSTRAINT FK_ressource_module FOREIGN KEY (module_id) REFERENCES module (id);
