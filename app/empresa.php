<?php
function oj_emp_upgrade(PDO $pdo): void {
    try { $cols = $pdo->query('SHOW COLUMNS FROM empresa')->fetchAll(PDO::FETCH_COLUMN); }
    catch (Throwable $e) { return; }
    foreach (['banco' => 'VARCHAR(80) NULL', 'cuenta' => 'VARCHAR(40) NULL', 'clabe' => 'VARCHAR(30) NULL'] as $c => $def)
        if (!in_array($c, $cols, true)) { $pdo->exec("ALTER TABLE empresa ADD COLUMN `$c` $def"); $cols[] = $c; }
}
function oj_emp_leer(PDO $pdo): array {
    oj_emp_upgrade($pdo);
    $r = $pdo->query('SELECT * FROM empresa ORDER BY id ASC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
    if (!$r) {
        $r = ['nombre' => 'Ojo Jarocho', 'telefono' => '', 'email' => '', 'web' => '', 'direccion' => '', 'logo' => 'assets/logo.png', 'banco' => '', 'cuenta' => '', 'clabe' => ''];
    }
    foreach (['banco', 'cuenta', 'clabe'] as $c) if (!array_key_exists($c, $r)) $r[$c] = '';
    return $r;
}
function oj_emp_datos_banco(array $empresa): string {
    $partes = [];
    foreach (['banco' => 'Banco', 'cuenta' => 'Cuenta', 'clabe' => 'Clabe interbancaria'] as $c => $etiqueta) {
        $v = trim((string)($empresa[$c] ?? ''));
        if ($v !== '') $partes[] = $etiqueta . ': ' . $v;
    }
    return $partes ? implode('. ', $partes) . '.' : '';
}
