<?php
// Desenvolvido pelo Sr. Engenheiro João

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$id = isset($_GET['id']) ? (string) $_GET['id'] : '';
$action = isset($_GET['action']) ? (string) $_GET['action'] : '';

if ($id !== '' && !preg_match('/^[a-f0-9]{32}$/', $id)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Pedido inválido.';
    exit;
}

if ($action === 'zip' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    handleZipDownload($config);
}

if ($id === '') {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Identificador em falta.';
    exit;
}

if (!isset($_SESSION['files'][$id]) || !is_array($_SESSION['files'][$id])) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Ficheiro não encontrado ou já foi removido.';
    exit;
}

$meta = $_SESSION['files'][$id];
$type = isset($_GET['type']) ? (string) $_GET['type'] : 'compressed';

if ($type === 'compressed') {
    $path = $meta['compressed_path'] ?? null;
    $downloadName = preg_replace('/\.pdf$/i', '', (string) ($meta['original_name'] ?? 'documento')) . '_comprimido.pdf';
} else {
    $path = $meta['original_path'] ?? null;
    $downloadName = (string) ($meta['original_name'] ?? 'documento.pdf');
}

if (!is_string($path) || !is_file($path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Ficheiro não disponível.';
    exit;
}

$downloadName = sanitizeStoredBasename($downloadName);

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . (string) filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

readfile($path);

// Após descarga: remover temporários deste item
unlinkIfExists($meta['original_path'] ?? null);
unlinkIfExists($meta['compressed_path'] ?? null);
unset($_SESSION['files'][$id]);
exit;

/**
 * @param array<string, mixed> $config
 */
function handleZipDownload(array $config): void
{
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['ids']) || !is_array($data['ids'])) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Pedido inválido.';
        exit;
    }

    if (!class_exists(ZipArchive::class)) {
        http_response_code(501);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'A extensão ZIP do PHP não está disponível no servidor.';
        exit;
    }

    $pathsToDelete = [];
    $zip = new ZipArchive();
    $zipPath = $config['uploads']['temp'] . DIRECTORY_SEPARATOR . 'bundle_' . bin2hex(random_bytes(8)) . '.zip';
    ensureDir($config['uploads']['temp']);

    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        http_response_code(500);
        echo 'Não foi possível criar o arquivo.';
        exit;
    }

    $zipIndex = 0;
    foreach ($data['ids'] as $rid) {
        if (!is_string($rid) || !preg_match('/^[a-f0-9]{32}$/', $rid)) {
            continue;
        }
        if (!isset($_SESSION['files'][$rid]) || !is_array($_SESSION['files'][$rid])) {
            continue;
        }
        $m = $_SESSION['files'][$rid];
        $cp = $m['compressed_path'] ?? null;
        if (!is_string($cp) || !is_file($cp)) {
            continue;
        }
        // $base = preg_replace('/\.pdf$/i', '', (string) ($m['original_name'] ?? 'documento')) . '_comprimido.pdf';
        $base = (string) ($m['original_name'] ?? 'documento.pdf');
        $base = sanitizeStoredBasename($base);
        $zipIndex++;
        // $entryName = sprintf('%03d_%s', $zipIndex, $base);
        $entryName = $base;
        $zip->addFile($cp, $entryName);
        $pathsToDelete[$rid] = $m;
    }

    if ($zip->numFiles === 0) {
        $zip->close();
        @unlink($zipPath);
        http_response_code(400);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Nenhum PDF comprimido disponível para descarregar.';
        exit;
    }

    $zip->close();

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="pdfs_comprimidos.zip"');
    header('Content-Length: ' . (string) filesize($zipPath));
    header('Cache-Control: no-store');
    readfile($zipPath);
    @unlink($zipPath);

    foreach ($pathsToDelete as $rid => $m) {
        unlinkIfExists(is_array($m) ? ($m['original_path'] ?? null) : null);
        unlinkIfExists(is_array($m) ? ($m['compressed_path'] ?? null) : null);
        unset($_SESSION['files'][$rid]);
    }
    exit;
}
