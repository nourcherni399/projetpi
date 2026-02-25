# Rappels SMS automatiques

La commande `app:rappel-rdv-sms` envoie les SMS de rappel pour les rendez-vous dont la date/heure est dans la fenêtre configurée (par défaut : entre 22 h et 24 h avant le RDV).

Pour que les rappels partent **automatiquement** sans exécuter la commande à la main, il faut planifier son exécution.

---

## Windows (Planificateur de tâches)

1. Ouvrir le **Planificateur de tâches** (taper `taskschd.msc` dans Exécuter ou Recherche).
2. **Créer une tâche** (pas « Créer une tâche simple » pour plus d’options).
3. **Général** : nommer la tâche (ex. « AutiCare – Rappels SMS RDV »).
4. **Déclencheurs** : **Nouveau** → Répéter toutes les **1 heure** (ou 30 min), pendant une durée **illimitée**.
5. **Actions** : **Nouvelle** → Action **Démarrer un programme** :
   - Programme : `C:\Users\user\Desktop\pi\run_rappel_sms.bat`
   - Ou indiquer le chemin complet vers `php.exe` et comme argument : `bin/console app:rappel-rdv-sms --hours=24 --window=2`, en définissant le dossier de démarrage sur le projet.
6. Enregistrer. La tâche lancera la commande toutes les heures ; les RDV dans la fenêtre 22–24 h recevront leur rappel au prochain passage.

**Alternative (script .bat)**  
Double-cliquer sur `run_rappel_sms.bat` à la racine du projet pour un test manuel. Pour l’automatisation, planifier ce fichier dans le Planificateur de tâches comme ci-dessus.

---

## Linux / serveur (cron)

Exécuter la commande toutes les heures :

```bash
0 * * * * cd /chemin/vers/pi && php bin/console app:rappel-rdv-sms --hours=24 --window=2 >> /var/log/rappel-sms.log 2>&1
```

Ou toutes les 30 minutes pour des rappels plus réactifs :

```bash
*/30 * * * * cd /chemin/vers/pi && php bin/console app:rappel-rdv-sms --hours=24 --window=2 >> /var/log/rappel-sms.log 2>&1
```

Remplacer `/chemin/vers/pi` par le chemin réel du projet (ex. `/var/www/pi`).

---

## Paramètres utiles

- `--hours=24` : envoyer le rappel pour les RDV dans **24 h** (par défaut).
- `--window=2` : fenêtre de **2 h** (RDV entre 22 h et 24 h avant).
- Pour une fenêtre plus large (ex. tous les RDV dans les 24 prochaines heures) : `--hours=24 --window=24`.

Une fois la tâche planifiée (Windows ou cron), les rappels SMS sont envoyés automatiquement sans exécuter la commande à la main.
