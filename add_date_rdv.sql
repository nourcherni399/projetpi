-- Ajoute la colonne date_rdv à la table rendez_vous (corrige l'erreur "Unknown column 'r0_.date_rdv'")
-- À exécuter sur la base pidb si la migration Doctrine n'a pas été exécutée.

ALTER TABLE rendez_vous ADD date_rdv DATE DEFAULT NULL;
