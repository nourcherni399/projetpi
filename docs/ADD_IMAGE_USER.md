# Pourquoi la colonne `image` n’est pas dans la table `user` ?

## Causes possibles

1. **La migration n’a jamais été exécutée**  
   La colonne n’est ajoutée qu’après avoir lancé :
   ```bash
   php bin/console doctrine:migrations:migrate
   ```
   Si tu n’as pas fait cette commande (ou si elle a échoué), la base n’a pas été modifiée.

2. **La migration est marquée comme exécutée mais le SQL a échoué**  
   Par exemple à cause du mot réservé `user` en MySQL. Dans ce cas, la version est enregistrée dans `doctrine_migration_versions` mais l’`ALTER TABLE` n’a pas réellement été appliqué.

3. **Tu regardes une autre base**  
   Vérifier que l’application utilise bien la base indiquée dans `.env` / `DATABASE_URL` et que c’est celle que tu interroges avec ton outil SQL.

---

## Solution : ajouter la colonne à la main

Exécute le script SQL suivant **sur la même base que l’application** :

**Fichier : `add_image_to_user.sql`** (à la racine du projet)

```sql
ALTER TABLE `user` ADD COLUMN image VARCHAR(255) DEFAULT NULL;
```

### Comment l’exécuter

- **phpMyAdmin** : onglet « SQL », coller la requête, exécuter.
- **Ligne de commande MySQL** (remplace `utilisateur`, `motdepasse`, `nom_base` par tes valeurs) :
  ```bash
  mysql -u utilisateur -p nom_base < add_image_to_user.sql
  ```
- **MySQL Workbench / DBeaver** : ouvrir un script SQL, coller la requête, exécuter.

Si tu as le message « Duplicate column name 'image' », la colonne existe déjà.

---

## Vérifier que la colonne est bien là

```sql
SHOW COLUMNS FROM `user` LIKE 'image';
```

Tu dois voir une ligne avec le champ `image`, type `varchar(255)`, `NULL` autorisé.
