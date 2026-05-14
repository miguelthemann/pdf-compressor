<?php
// Desenvolvido pelo Sr. Engenheiro João

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    appJsonResponse(['ok' => false, 'error' => 'Método não permitido.'], 405);
}

$raw = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) {
    appJsonResponse(['ok' => false, 'error' => 'Pedido inválido.'], 400);
}

$ids = $data['ids'] ?? null;
if (!is_array($ids)) {
    appJsonResponse(['ok' => false, 'error' => 'Lista inválida.'], 400);
}

foreach ($ids as $id) {
    if (!is_string($id) || !preg_match('/^[a-f0-9]{32}$/', $id)) {
        continue;
    }
    if (!isset($_SESSION['files'][$id]) || !is_array($_SESSION['files'][$id])) {
        continue;
    }
    $meta = $_SESSION['files'][$id];
    unlinkIfExists($meta['original_path'] ?? null);
    unlinkIfExists($meta['compressed_path'] ?? null);
    unset($_SESSION['files'][$id]);
}

appJsonResponse(['ok' => true]);
