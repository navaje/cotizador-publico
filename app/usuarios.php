<?php
require_once __DIR__ . '/usuarios_schema.php';
function oj_h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function oj_password_error(string $password, string $confirm): string {
    if (strlen($password) < 12 || strlen($password) > 72) return 'Usa una contraseña de 12 a 72 bytes (letras, números o símbolos).';
    if ($password !== $confirm) return 'Las contraseñas no coinciden.';
    return '';
}
function oj_admin(PDO $pdo): void {
    $stmt = $pdo->prepare('SELECT rol FROM usuarios WHERE id = ? AND activo = 1');
    $stmt->execute([(int)($_SESSION['usuario_id'] ?? 0)]);
    if ($stmt->fetchColumn() !== 'admin') { http_response_code(403); exit('Solo el administrador puede gestionar usuarios.'); }
}
function oj_roles(PDO $pdo): array {
    $col = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'rol'")->fetch(PDO::FETCH_ASSOC);
    if (strpos($col['Type'], 'enum(') === 0) {
        preg_match_all("/'([^']+)'/", $col['Type'], $m); return $m[1];
    }
    $roles = $pdo->query('SELECT DISTINCT rol FROM usuarios')->fetchAll(PDO::FETCH_COLUMN);
    return array_values(array_unique(array_merge(['admin','usuario'], $roles)));
}
function oj_limit(PDO $pdo, string $scope, string $identity, int $max): bool {
    $key = hash('sha256', $scope . ':' . $identity);
    $window = (int)floor(time()/3600);
    $sql = 'INSERT INTO oj_acceso_intentos (clave, ventana, intentos) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE intentos = IF(ventana = VALUES(ventana), intentos + 1, 1), ventana = VALUES(ventana)';
    $pdo->prepare($sql)->execute([$key, $window]);
    $s = $pdo->prepare('SELECT intentos FROM oj_acceso_intentos WHERE clave = ?'); $s->execute([$key]);
    return (int)$s->fetchColumn() <= $max;
}
function oj_recovery_send(string $email, string $token): bool {
    // URL fija: no se construye con Host ni con encabezados del visitante.
    $url = 'https://ojojarocho.online/cotizador/restablecer_password.php?token=' . rawurlencode($token);
    $body = "Solicitaste restablecer tu contraseña del cotizador Ojo Jarocho.\n\nAbre este enlace (válido durante 30 minutos):\n" . $url . "\n\nSi no hiciste esta solicitud, ignora este mensaje. Tu contraseña no se ha cambiado.";
    $wp = dirname(__DIR__, 2) . '/wp-load.php';
    if (is_file($wp)) require_once $wp;
    if (function_exists('wp_mail')) return wp_mail($email, 'Recuperar acceso - Ojo Jarocho', $body);
    return mail($email, 'Recuperar acceso - Ojo Jarocho', $body, "From: Ojo Jarocho <ventas@ojojarocho.online>\r\nContent-Type: text/plain; charset=UTF-8");
}
function oj_token(PDO $pdo, string $raw, bool $lock = false) {
    if (!preg_match('/^[a-f0-9]{64}$/D', $raw)) return false;
    $s = $pdo->prepare('SELECT t.id AS token_id, t.usuario_id FROM oj_password_tokens t JOIN usuarios u ON u.id=t.usuario_id WHERE t.token_hash=? AND t.used_at IS NULL AND t.expires_at > UTC_TIMESTAMP() AND u.activo=1' . ($lock ? ' FOR UPDATE' : ''));
    $s->execute([hash('sha256',$raw)]); return $s->fetch(PDO::FETCH_ASSOC);
}
function oj_invalidate_tokens(PDO $pdo, int $id): void {
    $pdo->prepare('UPDATE oj_password_tokens SET used_at=UTC_TIMESTAMP() WHERE usuario_id=? AND used_at IS NULL')->execute([$id]);
}
function oj_page_start(string $title): void {
    header('Cache-Control: no-store'); header('Referrer-Policy: no-referrer');
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="referrer" content="no-referrer"><title>'.oj_h($title).' - Ojo Jarocho</title><link rel="stylesheet" href="assets/usuarios.css"></head><body><main><h1>'.oj_h($title).'</h1>';
}
function oj_page_end(): void { echo '</main></body></html>'; }
