<?php

/**
 * Script one-shot : ajoute la colonne commande_id à la table notification
 * si elle n'existe pas. À lancer depuis la racine du projet :
 *   php fix_notification_commande_column.php
 */

$autoload = __DIR__ . '/vendor/autoload.php';
if (!is_file($autoload)) {
    echo "Erreur: vendor/autoload.php introuvable. Lancez 'composer install'.\n";
    exit(1);
}
require $autoload;

use Symfony\Component\Dotenv\Dotenv;

if (is_file(__DIR__ . '/.env')) {
    (new Dotenv())->loadEnv(__DIR__);
}
$url = $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL');
if (!$url || !str_starts_with($url, 'mysql:')) {
    echo "Erreur: DATABASE_URL (MySQL) non trouvée dans .env\n";
    exit(1);
}

// Parse DATABASE_URL: mysql://user:pass@host:port/dbname
$parsed = parse_url($url);
$host = $parsed['host'] ?? '127.0.0.1';
$port = isset($parsed['port']) ? (int) $parsed['port'] : 3306;
$user = $parsed['user'] ?? 'root';
$pass = $parsed['pass'] ?? '';
$dbname = isset($parsed['path']) ? ltrim($parsed['path'], '/') : '';

$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $dbname);
try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    echo "Connexion MySQL impossible: " . $e->getMessage() . "\n";
    exit(1);
}

// Vérifier si la colonne existe déjà
$stmt = $pdo->query("SHOW COLUMNS FROM notification LIKE 'commande_id'");
if ($stmt->rowCount() > 0) {
    echo "La colonne notification.commande_id existe déjà. Rien à faire.\n";
    exit(0);
}

echo "Ajout de la colonne commande_id à la table notification...\n";

try {
    $pdo->exec('ALTER TABLE notification ADD commande_id INT DEFAULT NULL');
    $pdo->exec('CREATE INDEX IDX_BF5476CA82EA2E54 ON notification (commande_id)');
    $pdo->exec('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA82EA2E54 FOREIGN KEY (commande_id) REFERENCES commande (id) ON DELETE CASCADE');
    echo "Colonne, index et clé étrangère ajoutés avec succès. Rechargez la page.\n";
} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
    exit(1);
}
