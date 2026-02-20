<?php
/**
 * Ajoute la colonne description_courte à la table evenement si elle n'existe pas.
 * À lancer depuis la racine du projet : php add_description_courte.php
 */

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

if (!$databaseUrl || !preg_match('#^mysql://([^:]*):([^@]*)@([^/]+)/([^?]+)#', $databaseUrl, $m)) {
    echo "DATABASE_URL non trouvée ou format non supporté dans .env.\n";
    exit(1);
}

$user = $m[1];
$pass = $m[2];
$host = $m[3];
$db   = $m[4];

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    echo "Connexion impossible : " . $e->getMessage() . "\n";
    exit(1);
}

// Vérifier si la colonne existe déjà
$stmt = $pdo->query("SHOW COLUMNS FROM evenement LIKE 'description_courte'");
if ($stmt->rowCount() > 0) {
    echo "La colonne description_courte existe déjà. Rien à faire.\n";
    exit(0);
}

$pdo->exec('ALTER TABLE evenement ADD description_courte VARCHAR(500) DEFAULT NULL');
echo "Colonne description_courte ajoutée avec succès.\n";
