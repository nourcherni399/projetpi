-- Crée la table note (corrige l'erreur "Table 'pidb.note' doesn't exist")
-- À exécuter sur la base pidb si la migration Doctrine n'a pas été exécutée.

CREATE TABLE note (
    id INT AUTO_INCREMENT NOT NULL,
    medecin_id INT NOT NULL,
    patient_id INT NOT NULL,
    contenu LONGTEXT NOT NULL,
    date_creation DATETIME NOT NULL,
    INDEX IDX_CFBDFA14F42A439 (medecin_id),
    INDEX IDX_CFBDFA146B899279 (patient_id),
    PRIMARY KEY(id),
    CONSTRAINT FK_CFBDFA14F42A439 FOREIGN KEY (medecin_id) REFERENCES user (id) ON DELETE CASCADE,
    CONSTRAINT FK_CFBDFA146B899279 FOREIGN KEY (patient_id) REFERENCES user (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
