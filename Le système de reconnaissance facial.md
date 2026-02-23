Le système de reconnaissance faciale repose sur face-api.js côté navigateur et une API interne Symfony.



**APIs et bibliothèques utilisées**



face-api.js (côté navigateur) : permet de détecter le visage, d’analyser ses caractéristiques et de générer un descripteur numérique représentant le visage.



API interne Symfony – POST /face-recognition : reçoit les descripteurs calculés par le frontend et les compare avec ceux stockés en base de données.





**Modèles utilisés (face-api.js)**



tinyFaceDetector : détecte rapidement la présence et la position du visage dans une image ou une vidéo, optimisé pour le temps réel.



faceLandmark68Net : identifie 68 points caractéristiques du visage afin de mieux l’aligner avant l’analyse.



faceRecognitionNet : transforme le visage en un vecteur numérique de 128 valeurs appelé descripteur facial, utilisé pour la comparaison.



Fonctionnement de la reconnaissance faciale



**1) Inscription**

L’utilisateur envoie une photo.

face-api.js détecte le visage, extrait les landmarks puis génère un descripteur de 128 valeurs.

Ce descripteur est stocké en base de données dans User.dataFaceApi (et non une biométrie cloud).



**2) Connexion faciale**

L’utilisateur saisit son email et active la webcam.

Le frontend capture plusieurs images (généralement 3), calcule un descripteur pour chacune, puis envoie l’email et les descripteurs à l’endpoint POST /face-recognition.



**3) Vérification côté serveur**

Symfony récupère l’utilisateur à partir de l’email et son descripteur stocké.

Il calcule la distance euclidienne entre le descripteur stocké et ceux capturés, puis conserve la distance minimale.

Cette distance est comparée à un seuil (0.52) :



distance ≤ seuil → visage reconnu



distance > seuil → accès refusé



**4) Connexion finale**

Si le visage est reconnu et que le compte est actif (isActive = true), Symfony authentifie l’utilisateur et le redirige selon son rôle.

