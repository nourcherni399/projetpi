-- Ajoute la colonne date à disponibilite et supprime jour (à exécuter si la migration Doctrine échoue)
-- MySQL: exécuter ce fichier dans votre base (ex. pidb)

-- 1. Ajouter la colonne date
ALTER TABLE disponibilite ADD COLUMN date DATE DEFAULT NULL;

-- 2. Remplir date à partir de jour (prochaine occurrence du jour de la semaine)
-- WEEKDAY() en MySQL : lundi=0, mardi=1, ..., dimanche=6
SET @w = WEEKDAY(CURDATE());

UPDATE disponibilite
SET date = DATE_ADD(CURDATE(), INTERVAL (
    CASE jour
        WHEN 'lundi'    THEN IF(MOD((0 - @w + 7), 7) = 0, 7, MOD((0 - @w + 7), 7))
        WHEN 'mardi'    THEN IF(MOD((1 - @w + 7), 7) = 0, 7, MOD((1 - @w + 7), 7))
        WHEN 'mercredi' THEN IF(MOD((2 - @w + 7), 7) = 0, 7, MOD((2 - @w + 7), 7))
        WHEN 'jeudi'    THEN IF(MOD((3 - @w + 7), 7) = 0, 7, MOD((3 - @w + 7), 7))
        WHEN 'vendredi' THEN IF(MOD((4 - @w + 7), 7) = 0, 7, MOD((4 - @w + 7), 7))
        WHEN 'samedi'    THEN IF(MOD((5 - @w + 7), 7) = 0, 7, MOD((5 - @w + 7), 7))
        WHEN 'dimanche' THEN IF(MOD((6 - @w + 7), 7) = 0, 7, MOD((6 - @w + 7), 7))
        ELSE 0
    END
) DAY)
WHERE jour IS NOT NULL;

-- Si des lignes ont encore date NULL (jour inconnu), mettre aujourd'hui
UPDATE disponibilite SET date = CURDATE() WHERE date IS NULL;

-- 3. Rendre date obligatoire
ALTER TABLE disponibilite MODIFY COLUMN date DATE NOT NULL;

-- 4. Supprimer la colonne jour
ALTER TABLE disponibilite DROP COLUMN jour;
