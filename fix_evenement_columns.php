<?php

/**
 * Script pour ajouter les colonnes mode et meeting_url à la table evenement.
 * À lancer une fois : php fix_evenement_columns.php
 * Lit DATABASE_URL depuis .env (même base que l'app).
 */

(function () {
    $envFile = __DIR__ . '/.env';
    if (!is_file($envFile)) {
        echo "Fichier .env introuvable.\n";
        exit(1);
    }
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $databaseUrl = null;
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        if (preg_match('/^DATABASE_URL=(.+)$/', $line, $m)) {
            $databaseUrl = trim($m[1], " \t\"'");
            break;
        }
    }
    if ($databaseUrl === null || $databaseUrl === '') {
        echo "DATABASE_URL non trouvé dans .env.\n";
        exit(1);
    }

    $params = parse_url($databaseUrl);
    if (!isset($params['scheme'], $params['host'], $params['path'])) {
        echo "DATABASE_URL invalide.\n";
        exit(1);
    }
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $params['host'],
        $params['port'] ?? 3306,
        ltrim($params['path'], '/')
    );
    $user = $params['user'] ?? '';
    $pass = $params['pass'] ?? '';

    try {
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } catch (PDOException $e) {
        echo "Connexion impossible : " . $e->getMessage() . "\n";
        exit(1);
    }

    $sql = "ALTER TABLE evenement
        ADD COLUMN mode VARCHAR(20) NOT NULL DEFAULT 'presentiel',
        ADD COLUMN meeting_url VARCHAR(500) DEFAULT NULL";

    try {
        $pdo->exec($sql);
        echo "Colonnes 'mode' et 'meeting_url' ajoutées à la table 'evenement' avec succès.\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "Les colonnes existent déjà. Rien à faire.\n";
        } else {
            echo "Erreur : " . $e->getMessage() . "\n";
            exit(1);
        }
    }
})();
