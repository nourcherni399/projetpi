-- Crée la table notification (corrige l'erreur "Table 'pidb.notification' doesn't exist")
-- À exécuter sur la base pidb si la migration Doctrine n'a pas été exécutée.

CREATE TABLE notification (
    id INT AUTO_INCREMENT NOT NULL,
    destinataire_id INT NOT NULL,
    rendez_vous_id INT DEFAULT NULL,
    type VARCHAR(50) NOT NULL,
    lu TINYINT(1) DEFAULT 0 NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX IDX_BF5476CAA4F84F6E (destinataire_id),
    INDEX IDX_BF5476CA91EF7EAA (rendez_vous_id),
    PRIMARY KEY(id),
    CONSTRAINT FK_BF5476CAA4F84F6E FOREIGN KEY (destinataire_id) REFERENCES user (id) ON DELETE CASCADE,
    CONSTRAINT FK_BF5476CA91EF7EAA FOREIGN KEY (rendez_vous_id) REFERENCES rendez_vous (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
