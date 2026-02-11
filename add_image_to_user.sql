-- Ajoute la colonne image à la table user (photo de profil).
-- À exécuter manuellement dans ton outil SQL (phpMyAdmin, MySQL Workbench, ligne de commande).
--
-- MySQL / MariaDB (table "user" = mot réservé, d'où les backticks) :
ALTER TABLE `user` ADD COLUMN image VARCHAR(255) DEFAULT NULL;

-- Si erreur "Duplicate column name 'image'", la colonne existe déjà, tu peux ignorer.
