-- Ajoute les colonnes mode et meeting_url à la table evenement
-- À exécuter dans phpMyAdmin (onglet SQL) ou en ligne de commande MySQL si la migration n'a pas été appliquée.

ALTER TABLE evenement
  ADD COLUMN mode VARCHAR(20) NOT NULL DEFAULT 'presentiel',
  ADD COLUMN meeting_url VARCHAR(500) DEFAULT NULL;
