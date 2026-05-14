<?php
// Desenvolvido pelo Sr. Engenheiro João

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    appJsonResponse(['ok' => false, 'error' => 'Método não permitido.'], 405);
}

$data = readJsonRequestBody();
if ($data === null) {
    appJsonResponse(['ok' => false, 'error' => 'Pedido inválido.'], 400);
}

$level = (string) ($data['level'] ?? 'medium');
$map = $config['pdf_settings'] ?? [];
if (!isset($map[$level])) {
    appJsonResponse(['ok' => false, 'error' => 'Nível de compressão inválido.'], 400);
}
$pdfSetting = $map[$level];

$ids = $data['ids'] ?? null;
if (!is_array($ids) || $ids === []) {
    appJsonResponse(['ok' => false, 'error' => 'Nenhum ficheiro selecionado.'], 400);
}

$maxBatchIds = (int) ($config['max_files_per_upload'] ?? 20);
if (count($ids) > $maxBatchIds) {
    appJsonResponse(['ok' => false, 'error' => 'Demasiados ficheiros num único pedido.'], 400);
}

[$gsBin, $gsOk] = resolveGhostscriptBinary((string) ($config['ghostscript_bin'] ?? 'gs'));
if (!$gsOk) {
    appJsonResponse([
        'ok' => false,
        'error' => 'O Ghostscript não está disponível no servidor. Em Ubuntu: sudo apt install ghostscript',
    ], 503);
}

$outDir = $config['uploads']['compressed'];
$tempDir = $config['uploads']['temp'];
ensureDir($outDir);
ensureDir($tempDir);
$rpOutDir = realpath($outDir);
$rpTempDir = realpath($tempDir);
if ($rpOutDir === false || $rpTempDir === false) {
    appJsonResponse(['ok' => false, 'error' => 'Diretórios de trabalho indisponíveis.'], 500);
}

$results = [];

foreach ($ids as $id) {
    if (!is_string($id) || !preg_match('/^[a-f0-9]{32}$/', $id)) {
        $results[] = ['ok' => false, 'id' => $id, 'error' => 'Identificador inválido.'];
        continue;
    }

    if (!isset($_SESSION['files'][$id]) || !is_array($_SESSION['files'][$id])) {
        $results[] = ['ok' => false, 'id' => $id, 'error' => 'Ficheiro não encontrado ou expirado.'];
        continue;
    }

    $meta = $_SESSION['files'][$id];
    $input = $meta['original_path'] ?? '';
    if (!is_string($input) || !pathIsFileInsideDir($input, $rpTempDir)) {
        $results[] = ['ok' => false, 'id' => $id, 'error' => 'Ficheiro original em falta.'];
        continue;
    }

    // Remover compressão anterior se existir
    unlinkUploadPathIfExists(isset($meta['compressed_path']) ? (string) $meta['compressed_path'] : null, $config);

    $outName = $id . '_compressed.pdf';
    if (!preg_match('/^[a-f0-9]{32}_compressed\.pdf$/', $outName)) {
        $results[] = ['ok' => false, 'id' => $id, 'error' => 'Estado interno inválido.'];
        continue;
    }
    $output = $rpOutDir . DIRECTORY_SEPARATOR . $outName;

    $cmd = escapeshellarg($gsBin)
        . ' -sDEVICE=pdfwrite'
        . ' -dCompatibilityLevel=1.4'
        . ' -dSAFER'
        . ' -dPDFSETTINGS=' . $pdfSetting
        . ' -dNOPAUSE -dQUIET -dBATCH'
        . ' -sOutputFile=' . escapeshellarg($output)
        . ' ' . escapeshellarg($input)
        . ' 2>&1';

    $outputLog = [];
    $code = 0;
    exec($cmd, $outputLog, $code);   

    if ($code !== 0 || !is_file($output) || is_link($output) || !pathIsFileInsideDir($output, $rpOutDir) || filesize($output) === 0) {
        unlinkUploadPathIfExists(is_file($output) ? $output : null, $config);
        $results[] = [
            'ok' => false,
            'id' => $id,
            'name' => (string) ($meta['original_name'] ?? ''),
            'error' => 'Falha ao executar o Ghostscript. Verifique o ficheiro e tente novamente.',
        ];
        continue;
    }

    @chmod($output, 0640);

    $origSize = (int) ($meta['original_size'] ?? filesize($input) ?: 0);
    $newSize = (int) filesize($output);
    $reduction = $origSize > 0 ? round((1 - $newSize / $origSize) * 100, 1) : 0.0;

    $_SESSION['files'][$id]['compressed_path'] = $output;
    $_SESSION['files'][$id]['compressed_size'] = $newSize;

    $results[] = [
        'ok' => true,
        'id' => $id,
        'name' => (string) ($meta['original_name'] ?? ''),
        'original_size' => $origSize,
        'compressed_size' => $newSize,
        'reduction_percent' => $reduction,
    ];
}

appJsonResponse(['ok' => true, 'results' => $results]);
