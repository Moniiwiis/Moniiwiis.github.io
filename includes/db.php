<?php
$host = '127.0.0.1';
$port = '3307';
$db   = 'pnk_inmobiliaria';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
     
     // Migración automática: verificar si la columna es_principal existe
     try {
         $stmt = $pdo->query("SHOW COLUMNS FROM propiedades_imagenes LIKE 'es_principal'");
         if (!$stmt->fetch()) {
             $pdo->exec("ALTER TABLE propiedades_imagenes ADD COLUMN es_principal TINYINT(1) NOT NULL DEFAULT 0");
         }
     } catch (\PDOException $e) {
         // Silencioso en caso de que la tabla no esté creada aún
     }

     // Migración automática: verificar y agregar motivo_rechazo a usuarios
     try {
         $stmt = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'motivo_rechazo'");
         if (!$stmt->fetch()) {
             $pdo->exec("ALTER TABLE usuarios ADD COLUMN motivo_rechazo TEXT NULL");
         }
     } catch (\PDOException $e) {
         // Silencioso
     }

     // Migración automática: verificar y agregar motivo_rechazo a propiedades
     try {
         $stmt = $pdo->query("SHOW COLUMNS FROM propiedades LIKE 'motivo_rechazo'");
         if (!$stmt->fetch()) {
             $pdo->exec("ALTER TABLE propiedades ADD COLUMN motivo_rechazo TEXT NULL");
         }
     } catch (\PDOException $e) {
         // Silencioso
     }
} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>
