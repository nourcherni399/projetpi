# Où trouver la météo ?

La **météo** (prévision pour le jour de l’événement) s’affiche sur la **fiche publique d’un événement**, pas dans l’admin.

## Accès

1. Ouvrez le **site public** (ex. `http://127.0.0.1:8000`).
2. Allez dans **Événements** (menu ou page d’accueil).
3. Cliquez sur **un événement** pour ouvrir sa fiche.
4. La météo apparaît sous la date/heure/lieu, dans un bloc bleu (températures, prévision horaire).

**URL directe** : `http://127.0.0.1:8000/evenements/{id}` (remplacez `{id}` par l’ID de l’événement, ex. `1`).

## Quand la météo ne s’affiche pas

Elle n’est affichée que si :

- l’événement est en mode **Présentiel** ou **Hybride** (pas uniquement en ligne) ;
- le **lieu** a été **géolocalisé** (latitude/longitude renseignées).

Pour qu’elle apparaisse :

1. En **admin** → **Événements** → ouvrir l’événement → **Modifier**.
2. Renseigner le champ **Lieu** avec une adresse (ex. une ville ou une adresse complète).
3. Enregistrer : la géolocalisation se fait à l’enregistrement si le lieu est valide.
4. Aller sur la **fiche publique** de cet événement : la météo doit s’afficher.

Si le lieu n’a pas de coordonnées (latitude/longitude), un message explicatif s’affiche à la place du bloc météo.
