# Vérification Google Custom Search (recherche mondiale)

Ce guide vous aide à corriger l’erreur **HTTP 400** et à faire fonctionner la « Recherche mondiale d’événements à venir ».

---

## Étape 1 : Vérifier l’API Custom Search

1. Ouvrez **Google Cloud Console** : https://console.cloud.google.com/
2. En haut, vérifiez que le **bon projet** est sélectionné (menu déroulant à côté de « Google Cloud »).
3. Dans le menu de gauche (☰), allez dans **« APIs et services »** → **« Bibliothèque »** (ou directement : https://console.cloud.google.com/apis/library).
4. Dans la recherche, tapez **« Custom Search API »**.
5. Cliquez sur **Custom Search API**, puis sur **« Activer »** (ou « Gérer » si déjà activée).
6. Si vous voyez un message du type « La facturation doit être activée », notez-le et passez à l’étape 4.

**À retenir :** l’API doit être **activée** pour le même projet que celui où vous avez créé la clé API.

---

## Étape 2 : Vérifier la clé API (GOOGLE_CSE_API_KEY)

1. Toujours dans Google Cloud Console : **APIs et services** → **Identifiants** (ou https://console.cloud.google.com/apis/credentials).
2. Dans la section **« Clés API »**, trouvez la clé que vous utilisez dans le `.env` (vous pouvez en créer une nouvelle si besoin).
3. Cliquez sur la clé pour l’ouvrir.
4. Vérifiez :
   - **Restrictions d’application** : si des restrictions sont définies, assurez-vous qu’elles autorisent les appels depuis votre serveur (pour le dev, « Aucune » fonctionne).
   - **Restrictions d’API** : soit « Ne pas restreindre la clé », soit la liste doit contenir **« Custom Search API »**.
5. Si vous avez créé une **nouvelle** clé, copiez-la, puis dans votre projet :
   - Ouvrez le fichier **`.env`**.
   - Remplacez la valeur de `GOOGLE_CSE_API_KEY=` par la nouvelle clé (sans espaces, sans guillemets).
   - Enregistrez le fichier.

**Exemple dans `.env` :**
```env
GOOGLE_CSE_API_KEY=AIzaSy...votre_cle_ici
```

---

## Étape 3 : Vérifier le moteur de recherche (GOOGLE_CSE_CX)

1. Allez sur **Programmable Search Engine** : https://programmablesearchengine.google.com/
2. Connectez-vous avec le même compte Google que pour Cloud Console.
3. Vous devez voir au moins **un moteur de recherche**. Cliquez dessus (ou créez-en un : **« Add »** → **« Search the entire web »** → donnez un nom → **Create**).
4. Dans les réglages du moteur, trouvez **« Search engine ID »** (ou **« ID du moteur »**). C’est une chaîne du type : `e079fcb8d7b164dc7`.
5. Copiez cet ID.
6. Dans votre fichier **`.env`**, vérifiez que `GOOGLE_CSE_CX` a exactement cette valeur (pas d’espace avant/après) :

**Exemple dans `.env` :**
```env
GOOGLE_CSE_CX=e079fcb8d7b164dc7
```

7. Si le moteur est configuré pour chercher **un seul site** (et non « Search the entire web »), l’API peut renvoyer des erreurs. Créez si besoin un **nouveau** moteur avec l’option **« Search the entire web »**.

---

## Étape 4 : Facturation (souvent requis pour éviter le 400)

Google exige souvent qu’un **compte de facturation** soit activé sur le projet, même pour utiliser le quota gratuit (~100 requêtes/jour).

1. Dans **Google Cloud Console** : menu ☰ → **Facturation** (ou https://console.cloud.google.com/billing).
2. Si aucun projet n’est lié à un compte de facturation :
   - Cliquez sur **« Associer un compte de facturation »**.
   - Choisissez ou créez un compte (carte bancaire demandée ; le quota gratuit ne déclenche en général pas de débit).
3. Vérifiez que le **projet** utilisé pour Custom Search (celui de votre clé API) est bien associé à ce compte.

Sans facturation activée, l’API Custom Search peut renvoyer **400** même avec une clé et un moteur corrects.

---

## Étape 5 : Vider le cache Symfony

Après toute modification du `.env` ou de la configuration :

1. À la racine du projet (dossier `pi`), ouvrez un terminal.
2. Lancez :
   ```bash
   php bin/console cache:clear
   ```
3. Rechargez la page admin **Idées d’événements (IA)** et refaites une recherche « Rechercher dans le monde ».

---

## Récapitulatif des variables dans `.env`

| Variable              | Où la trouver / quoi mettre |
|-----------------------|-----------------------------|
| `GOOGLE_CSE_API_KEY`  | Cloud Console → Identifiants → Clés API |
| `GOOGLE_CSE_CX`       | programmablesearchengine.google.com → votre moteur → Search engine ID |

Les deux doivent être renseignées, sans `#` devant, sans espaces autour du `=`.

---

## Si ça ne marche toujours pas

- Vérifiez dans la console navigateur (F12 → Console) qu’il n’y a pas d’erreur JavaScript.
- Vérifiez les logs Symfony : `var/log/dev.log` (en environnement `dev`).
- Attendez quelques minutes après avoir activé l’API ou la facturation (propagation côté Google).

Une fois ces points vérifiés, l’erreur 400 disparaît en général et les résultats de la recherche mondiale s’affichent.
