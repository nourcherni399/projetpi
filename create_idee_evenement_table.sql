-- Crée la table idee_evenement (Idées IA) si elle n'existe pas.
-- À exécuter dans la base pidb (phpMyAdmin ou ligne de commande MySQL).

CREATE TABLE IF NOT EXISTS idee_evenement (
    id INT AUTO_INCREMENT NOT NULL,
    titre VARCHAR(255) NOT NULL,
    description LONGTEXT DEFAULT NULL,
    theme VARCHAR(100) DEFAULT NULL,
    pourquoi VARCHAR(500) DEFAULT NULL,
    mots_cle VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
