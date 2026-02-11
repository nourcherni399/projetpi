-- Création des tables panier (cart et cart_item) pour la base pidb
-- Exécuter ce script dans MySQL si la migration Symfony n'a pas été lancée.

USE pidb;

CREATE TABLE IF NOT EXISTS cart (
    id INT AUTO_INCREMENT NOT NULL,
    user_id INT NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    INDEX IDX_BA388B7A76ED395 (user_id),
    CONSTRAINT FK_BA388B7A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS cart_item (
    id INT AUTO_INCREMENT NOT NULL,
    cart_id INT NOT NULL,
    produit_id INT NOT NULL,
    quantite INT NOT NULL,
    prix DOUBLE PRECISION NOT NULL,
    PRIMARY KEY (id),
    INDEX IDX_F0FE25271AD5CDBF (cart_id),
    INDEX IDX_F0FE2527F347EFB (produit_id),
    CONSTRAINT FK_F0FE25271AD5CDBF FOREIGN KEY (cart_id) REFERENCES cart (id) ON DELETE CASCADE,
    CONSTRAINT FK_F0FE2527F347EFB FOREIGN KEY (produit_id) REFERENCES produit (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
