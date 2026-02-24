# Google Calendar API – Configuration

## Ce qui est en place

- **Lien "Ajouter à Google Calendar"** dans l’email de confirmation du RDV : toujours actif, ne nécessite aucune clé.
- **Création automatique d’un événement** dans un calendrier Google lorsque le médecin accepte un RDV (optionnel, nécessite la config ci‑dessous).

## Configuration optionnelle (création d’événements dans un calendrier)

1. **Google Cloud Console**
   - Aller sur [Google Cloud Console](https://console.cloud.google.com/).
   - Créer ou sélectionner un projet.
   - Activer **Google Calendar API** : APIs & Services → Enable APIs → "Google Calendar API" → Enable.

2. **Compte de service**
   - APIs & Services → Credentials → Create Credentials → **Service account**.
   - Donner un nom (ex. `auticare-calendar`), puis Create.
   - Dans la fiche du compte de service : onglet **Keys** → Add Key → Create new key → **JSON**. Télécharger le fichier.

3. **Placer le fichier**
   - Mettre le JSON dans le projet (hors dépôt git), ex. : `config/google-calendar-credentials.json`.
   - Ajouter `config/google-calendar-credentials.json` dans `.gitignore`.

4. **Calendrier à utiliser**
   - Créer un calendrier (ou utiliser un existant) dans Google Calendar.
   - Partager ce calendrier avec **l’email du compte de service** (du type `xxx@votrep-rojet.iam.gserviceaccount.com`) avec le droit **« Modifier les événements »** (ou équivalent).
   - Récupérer l’**ID du calendrier** : paramètres du calendrier → "Integrate calendar" → Calendar ID (souvent de la forme `xxx@group.calendar.google.com`).

5. **Variables d’environnement**
   - Dans `.env` ou `.env.local` :
   ```env
   GOOGLE_CALENDAR_CREDENTIALS=config/google-calendar-credentials.json
   GOOGLE_CALENDAR_ID=votre_calendar_id@group.calendar.google.com
   ```
   - Pour le calendrier principal d’un compte Google, vous pouvez utiliser `primary` (si ce compte a partagé le calendrier avec le compte de service, ce qui est plus rare).

Sans ces variables, l’envoi d’email et le lien "Ajouter à Google Calendar" fonctionnent toujours ; seuls les créations d’événements via l’API sont désactivées.
