<?php
require_once __DIR__ . '/../auth.php';
require_post_csrf();
header('Content-Type: application/json');

if (!isset($_POST['imagen'])) {
    echo json_encode([
        "status" => "error",
        "mensaje" => "No se recibió imagen"
    ]);
    exit;
}

$imagen = $_POST['imagen'];
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if (strlen($imagen) > 8 * 1024 * 1024) {
    http_response_code(413);
    echo json_encode(["status" => "error", "mensaje" => "La imagen es demasiado grande"]);
    exit;
}

if (strpos($imagen, 'data:image/png;base64,') !== 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "mensaje" => "Formato de imagen no permitido"]);
    exit;
}

$imagen = str_replace('data:image/png;base64,', '', $imagen);
$imagen = str_replace(' ', '+', $imagen);

$data = base64_decode($imagen);

if ($data === false) {
    echo json_encode([
        "status" => "error",
        "mensaje" => "Imagen inválida"
    ]);
    exit;
}

if (!is_dir('planos')) {
    mkdir('planos', 0755, true);
}

if ($id > 0) {
    $nombre = 'plano_' . $id . '.png';
} else {
    $nombre = 'plano_' . date('Ymd_His') . '.png';
}

$ruta = 'planos/' . $nombre;

if (file_put_contents($ruta, $data) === false) {
    echo json_encode([
        "status" => "error",
        "mensaje" => "No se pudo guardar el archivo"
    ]);
    exit;
}

echo json_encode([
    "status" => "ok",
    "archivo" => 'ver.php?archivo=' . rawurlencode($nombre),
    "id" => $id
]);
