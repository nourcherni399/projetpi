# Envoi d’emails (confirmation d’inscription)

L’application utilise **Symfony Mailer**. La configuration se fait via `MAILER_DSN` et `APP_MAILER_FROM` dans `.env` ou `.env.local`.

## 1. Gmail (production ou dev avec envoi réel)

1. Activer la **validation en 2 étapes** sur le compte Google.
2. Créer un **mot de passe d’application** : [https://myaccount.google.com/apppasswords](https://myaccount.google.com/apppasswords)
3. Dans `.env` ou `.env.local` :

```env
MAILER_DSN="gmail://VOTRE_EMAIL:MOT_DE_PASSE_APPLICATION@default"
APP_MAILER_FROM="VOTRE_EMAIL@gmail.com"
```

Les espaces dans le mot de passe d’application doivent être encodés en `%20` dans l’URL.

**Erreur 535 (Username and Password not accepted)** : le mot de passe d’application est invalide ou révoqué. En générer un nouveau et mettre à jour `MAILER_DSN`.

---

## 2. Transport « null » (développement sans SMTP)

Aucun email n’est envoyé ; aucun serveur SMTP n’est contacté. Utile pour développer sans configurer Gmail.

Dans `.env.local` :

```env
MAILER_DSN=null://null
APP_MAILER_FROM=noreply@auticare.local
```

L’inscription fonctionne, le compte est créé, et le message de confirmation n’est pas envoyé (pas d’erreur).

---

## 3. Mailtrap (développement – capture des emails)

[Mailtrap](https://mailtrap.io) fournit une boîte SMTP factice : les emails sont « envoyés » mais restent dans Mailtrap (inbox de test). Pratique pour vérifier le contenu sans envoyer de vrais emails.

1. Créer un compte sur [mailtrap.io](https://mailtrap.io).
2. Récupérer les identifiants SMTP (Inboxes → votre inbox → SMTP Settings).
3. Dans `.env.local` :

```env
MAILER_DSN="smtp://USER:PASSWORD@sandbox.smtp.mailtrap.io:2525"
APP_MAILER_FROM=noreply@auticare.local
```

Vous verrez les emails dans l’inbox Mailtrap.

---

## Résumé

| Objectif              | MAILER_DSN |
|-----------------------|------------|
| Envoyer en vrai (Gmail) | `gmail://user:app_password@default` |
| Dev sans mail         | `null://null` |
| Dev + voir le contenu | Mailtrap (smtp://...) |

Les tests (`phpunit`) utilisent automatiquement `null://null` (voir `.env.test`).
