# Tester l’API des rappels d’événements

L’API envoie un e-mail (et éventuellement un SMS via Email-to-SMS) aux inscrits des événements dont le **début est dans les 24 prochaines heures**.

## 1. Prérequis

- **Token** : définir `REMINDER_SECRET` dans `.env` (ou l’API utilisera `APP_SECRET`).
- **Données** :
  - Au moins un **événement** dont la date + heure de début est entre **maintenant** et **dans 24 h**.
  - Au moins un **inscrit** à cet événement (avec e-mail renseigné). Pour le SMS : numéro au format pris en charge et `REMINDER_SMS_GATEWAYS` configuré pour le pays (ex. `{"33":"orange.fr"}`).

## 2. Appeler l’API

**URL :** `GET` ou `POST`  
`https://votre-domaine.com/api/cron/event-reminders`

**Authentification** (une des deux) :
- En query : `?token=VOTRE_SECRET`
- En header : `X-Cron-Token: VOTRE_SECRET`

### Exemples

**Avec le token en paramètre (navigateur ou curl) :**
```bash
curl "http://localhost:8000/api/cron/event-reminders?token=VOTRE_REMINDER_SECRET"
```

**Avec le token en en-tête :**
```bash
curl -H "X-Cron-Token: VOTRE_REMINDER_SECRET" "http://localhost:8000/api/cron/event-reminders"
```

Sous Windows (PowerShell) :
```powershell
Invoke-WebRequest -Uri "http://localhost:8000/api/cron/event-reminders?token=VOTRE_REMINDER_SECRET" -Method GET
```

## 3. Réponse attendue

**Succès (200) :**
```json
{
  "ok": true,
  "events_checked": 1,
  "reminders_email_sent": 2,
  "reminders_sms_sent": 0,
  "sms_method": "email_to_sms",
  "errors": []
}
```

**Token invalide ou manquant (401) :**
```json
{
  "error": "Unauthorized",
  "message": "Token invalide ou manquant."
}
```

## 4. Vérifications

- **E-mail** : consulter la boîte mail des utilisateurs inscrits (et éventuellement les spams).
- **SMS** : uniquement si `REMINDER_SMS_GATEWAYS` est défini pour le pays du numéro (ex. `{"33":"orange.fr"}`) ; le SMS passe par le gateway Email-to-SMS de l’opérateur.
- **Cache** : un même inscrit ne reçoit qu’un rappel par événement toutes les 24 h ; pour retester, attendre ou vider le cache (ex. `php bin/console cache:clear` en dev).

## 5. Test sans événement dans 24 h

Pour vérifier uniquement que l’API répond et que le token est accepté :
- Appelez l’API comme ci-dessus : vous devriez obtenir `events_checked: 0`, `reminders_email_sent: 0`, `reminders_sms_sent: 0`.
- Pour tester l’envoi réel, créez un événement dont la date et l’heure de début sont dans les 24 prochaines heures et inscrivez-y un utilisateur avec e-mail (et optionnellement téléphone + gateway configuré).
