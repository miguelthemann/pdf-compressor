<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    appJsonResponse(['ok' => false, 'error' => 'Método não permitido.'], 405);
}

if (!isset($_FILES['pdfs'])) {
    appJsonResponse(['ok' => false, 'error' => 'Nenhum ficheiro recebido.'], 400);
}

$files = $_FILES['pdfs'];
$maxFile = (int) $config['max_file_bytes'];
$maxBatch = (int) $config['max_batch_bytes'];
$maxCount = (int) $config['max_files_per_upload'];

if (!is_array($files['name'])) {
    appJsonResponse(['ok' => false, 'error' => 'Formato de upload inválido.'], 400);
}

$n = count($files['name']);
if ($n > $maxCount) {
    appJsonResponse([
        'ok' => false,
        'error' => sprintf('Máximo de %d ficheiros por envio.', $maxCount),
    ], 400);
}

$tempDir = $config['uploads']['temp'];
ensureDir($tempDir);

$batchSize = 0;
$results = [];

for ($i = 0; $i < $n; $i++) {
    $err = (int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_NO_FILE) {
        continue;
    }
    if ($err !== UPLOAD_ERR_OK) {
        $results[] = [
            'ok' => false,
            'name' => (string) ($files['name'][$i] ?? ''),
            'error' => match ($err) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ficheiro demasiado grande.',
                default => 'Erro no envio do ficheiro.',
            },
        ];
        continue;
    }

    $size = (int) ($files['size'][$i] ?? 0);
    if ($size > $maxFile) {
        $results[] = [
            'ok' => false,
            'name' => (string) ($files['name'][$i] ?? ''),
            'error' => 'Ficheiro excede o limite permitido.',
        ];
        continue;
    }
    if ($batchSize + $size > $maxBatch) {
        $results[] = [
            'ok' => false,
            'name' => (string) ($files['name'][$i] ?? ''),
            'error' => 'Limite total do lote excedido.',
        ];
        continue;
    }

    $origName = (string) ($files['name'][$i] ?? 'documento.pdf');
    $tmpPath = (string) ($files['tmp_name'][$i] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        $results[] = ['ok' => false, 'name' => $origName, 'error' => 'Upload inválido.'];
        continue;
    }

    $mime = mime_content_type($tmpPath);
    if ($mime !== false && $mime !== 'application/pdf' && $mime !== 'application/octet-stream') {
        $results[] = ['ok' => false, 'name' => $origName, 'error' => 'Tipo de ficheiro não aceite.'];
        continue;
    }

    if (!isPdfMagic($tmpPath)) {
        $results[] = ['ok' => false, 'name' => $origName, 'error' => 'O ficheiro não parece ser um PDF válido.'];
        continue;
    }

    $id = generateFileId();
    $safeBase = sanitizeStoredBasename($origName);
    $destName = $id . '_' . $safeBase;
    $destPath = $tempDir . DIRECTORY_SEPARATOR . $destName;

    if (!@move_uploaded_file($tmpPath, $destPath)) {
        $results[] = ['ok' => false, 'name' => $origName, 'error' => 'Não foi possível guardar o ficheiro.'];
        continue;
    }

    $batchSize += (int) (filesize($destPath) ?: $size);

    @chmod($destPath, 0640);

    $_SESSION['files'][$id] = [
        'original_path' => $destPath,
        'compressed_path' => null,
        'original_name' => $origName,
        'stored_name' => $safeBase,
        'created_at' => time(),
        'original_size' => filesize($destPath) ?: $size,
    ];

    $results[] = [
        'ok' => true,
        'id' => $id,
        'name' => $origName,
        'size' => (int) $_SESSION['files'][$id]['original_size'],
    ];
}

appJsonResponse(['ok' => true, 'files' => $results]);
