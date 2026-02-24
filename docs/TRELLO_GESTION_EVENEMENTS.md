# Trello – Gestion des événements AutiCare

Ce document décrit comment organiser un tableau Trello pour piloter la **gestion des événements** en lien avec l’application (idées → création → inscriptions → suivi).

---

## 1. Structure du tableau (listes)

Créez un tableau Trello nommé **« Gestion événements »** avec les listes suivantes, de gauche à droite :

| Ordre | Liste | Rôle |
|-------|--------|------|
| 1 | **Idées / Backlog** | Idées d’événements, propositions IA, recherches « dans le monde » |
| 2 | **À planifier** | Événements validés en idée, à transformer en brouillon puis en événement |
| 3 | **En préparation** | Événements créés : à compléter (lieu, date, image, Zoom, thématique) |
| 4 | **Publiés / À venir** | Événements finalisés, visibles, inscriptions ouvertes |
| 5 | **En cours (J–J)** | Événements dont la date est proche : rappels, messages, dernier check |
| 6 | **Terminés** | Événements passés : bilan, export PDF participants, stats |
| 7 | **Annulés / Reportés** | Événements annulés ou reportés (archiver les cartes si besoin) |

---

## 2. Cartes par liste – Exemples et checklist

### Liste 1 – Idées / Backlog

- **Carte type :** « Idée : [titre] »  
  - Description : thème, pourquoi, mots-clés (comme dans **Idées d’événements** de l’app).  
  - **Checklist :**  
    - [ ] Vérifier cohérence avec les thématiques existantes  
    - [ ] Recherche « dans le monde » si besoin (admin événements)  
    - [ ] Décision : garder en idée ou passer en « À planifier »

- **Labels suggérés :** `Idée IA` | `Recherche web` | `Manuel` | `Thématique: Sensoriel/Inclusion/…`

---

### Liste 2 – À planifier

- **Carte type :** « Événement : [titre] »  
  - Lien vers l’idée (ou copier titre/description).  
  - **Checklist :**  
    - [ ] Créer le brouillon depuis **Admin → Idées d’événements → Créer un brouillon** (ou nouvel événement)  
    - [ ] Choisir date, créneau horaire, lieu ou mode (présentiel / en ligne / hybride)  
    - [ ] Assigner une thématique  
    - [ ] Déplacer la carte vers « En préparation » quand l’événement est créé dans l’app

- **Labels :** `Priorité haute` | `Priorité basse` | Nom de la thématique

---

### Liste 3 – En préparation

- **Carte type :** « [Titre événement] – [date] »  
  - Lien direct vers **Admin → Événements → Modifier** (URL de l’événement).  
  - **Checklist :**  
    - [ ] Lieu ou lien de réunion (Zoom / meetingUrl) renseigné  
    - [ ] Coordonnées GPS ou lien carte si présentiel  
    - [ ] Image / affiche uploadée si utilisée  
    - [ ] Description et infos pratiques à jour  
    - [ ] Passer en « Publiés / À venir » quand tout est prêt

- **Labels :** `Présentiel` | `En ligne` | `Hybride` | `Thématique: …`

---

### Liste 4 – Publiés / À venir

- **Carte type :** « [Titre] – [date] »  
  - Lien vers la fiche événement (admin ou front).  
  - **Checklist :**  
    - [ ] Vérifier visibilité côté public  
    - [ ] Suivi des inscriptions (en attente / acceptées) dans l’app  
    - [ ] Répondre aux messages (Messages événement) si besoin  
    - [ ] Déplacer en « En cours (J–J) » à J–7 ou J–1 selon votre règle

- **Checklist optionnelle :** Nombre d’inscrits (à mettre à jour en description ou en commentaire)

---

### Liste 5 – En cours (J–J)

- **Carte type :** « [Titre] – [date] »  
  - **Checklist :**  
    - [ ] Envoyer les rappels (bouton **Envoyer les rappels** dans l’app si configuré)  
    - [ ] Vérifier lien Zoom / lieu et messages aux participants  
    - [ ] Dernière relance inscriptions en attente (accepter/refuser)  
    - [ ] Jour J : animation, suivi  
    - [ ] Déplacer en « Terminés » après la date

- **Labels :** `Rappels envoyés` | `À relancer`

---

### Liste 6 – Terminés

- **Carte type :** « [Titre] – [date] »  
  - **Checklist :**  
    - [ ] Exporter la liste des participants (PDF depuis l’app si disponible)  
    - [ ] Noter stats : nombre d’inscrits, présents, thématique  
    - [ ] Bilan rapide en commentaire Trello (optionnel)  
    - [ ] Archiver la carte après un délai ou la garder pour historique

---

### Liste 7 – Annulés / Reportés

- **Carte type :** « [Titre] – [date] (annulé/reporté) »  
  - Description : raison, nouvelle date si report.  
  - **Checklist :**  
    - [ ] Participants prévenus (message ou rappel si possible)  
    - [ ] Événement désactivé ou supprimé dans l’app selon votre process  
    - [ ] Archiver la carte après traitement

---

## 3. Correspondance avec l’application

| Fonctionnalité app | Où ça se reflète dans Trello |
|--------------------|------------------------------|
| **Admin → Idées d’événements** (recherche, IA, créer brouillon) | Listes **Idées / Backlog** et **À planifier** |
| **Admin → Événements** (CRUD, recherche, tri, stats) | Listes **En préparation**, **Publiés**, **En cours**, **Terminés** |
| **Inscriptions** (en_attente, accepte, refuse, desinscrit) | Suivi dans les cartes **Publiés** et **En cours** |
| **Messages événement** | À traiter ; noter « messages non lus » en titre ou description de la carte |
| **Rappels (EventReminderService)** | Checklist **En cours (J–J)** : « Rappels envoyés » |
| **PDF participants** | Checklist **Terminés** : « Export PDF » |
| **Recherche avancée « événements dans le monde »** | Idées générées → cartes en **Idées / Backlog** |

---

## 4. Labels recommandés

- **Type :** `Idée IA` | `Recherche web` | `Manuel`  
- **Mode :** `Présentiel` | `En ligne` | `Hybride`  
- **Priorité :** `Priorité haute` | `Priorité basse`  
- **Thématique :** reprendre les noms de vos thématiques (ex. Sensoriel, Inclusion, Familles, Ateliers, Sensibilisation)  
- **Statut suivi :** `Rappels envoyés` | `Messages non lus` | `À relancer`

---

## 5. Utilisation au quotidien

1. **Nouvelles idées** : créer une carte dans **Idées / Backlog** (depuis l’app via « Générer des idées avec l’IA » ou recherche mondiale, puis noter les titres dans Trello).  
2. **Création d’un événement** : carte **À planifier** → créer l’événement dans l’app (brouillon ou nouvel élément) → déplacer la carte en **En préparation**.  
3. **Finalisation** : compléter la checklist **En préparation** → déplacer en **Publiés / À venir**.  
4. **À J–7 / J–1** : déplacer en **En cours (J–J)** et cocher « Rappels envoyés » après utilisation du bouton dans l’app.  
5. **Après la date** : déplacer en **Terminés**, faire l’export PDF et le bilan, puis archiver si besoin.

Vous pouvez dupliquer ce document comme base pour votre équipe et adapter les listes ou les checklists à votre rythme (trimestre, mois, etc.).
