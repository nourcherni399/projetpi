# Démarrer le serveur (Windows)

## Utiliser `symfony serve` (recommandé si possible)

Pour que **`symfony serve`** ne affiche pas « TerminateProcess: Access is denied » :

### 1. Ne pas lancer le terminal en tant qu’administrateur

L’erreur apparaît souvent quand Cursor ou PowerShell est ouvert **en mode Administrateur**.

- Fermez Cursor.
- Lancez Cursor **sans** « Exécuter en tant qu’administrateur » (clic droit sur Cursor → ouvrir normalement).
- Ouvrez le terminal dans Cursor et exécutez :  
  `symfony serve`

### 2. Mettre à jour le CLI Symfony

Dans un terminal :

```bat
symfony version
symfony self:update
```

Puis relancez `symfony serve`.

### 3. Si le port 8000 est déjà utilisé

Vérifiez quel processus utilise le port :

```bat
netstat -ano | findstr :8000
```

Fermez l’autre application qui utilise le port, ou lancez le serveur sur un autre port :

```bat
symfony serve --port=8080
```

### 4. Lancer avec le script fourni

À la racine du projet vous pouvez aussi lancer :

```bat
symfony_serve.bat
```

Cela exécute `symfony serve` (en partant du principe que le terminal n’est pas en administrateur).

---

## Alternative : serveur PHP intégré

Si l’erreur persiste avec `symfony serve`, utilisez le serveur PHP :

| Méthode        | Commande / action |
|----------------|-------------------|
| Script         | `start_server.bat` |
| Terminal       | `php -S 127.0.0.1:8000 -t public` |
| Composer       | `composer serve` |

Pour arrêter le serveur : **Ctrl+C** dans le terminal.
