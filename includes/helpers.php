<?php
// Desenvolvido pelo Sr. Engenheiro João

declare(strict_types=1);

function appJsonResponse(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function ghostscriptAvailable(string $bin): bool
{
    if ($bin === '') {
        return false;
    }
    $cmd = escapeshellarg($bin) . ' --version 2>&1';
    $out = @shell_exec($cmd);
    return is_string($out) && trim($out) !== '';
}

/**
 * Resolve o executável do Ghostscript (PATH ou caminhos habituais em Linux).
 *
 * @return array{0: string, 1: bool} [caminho ou preferido, disponível]
 */
function resolveGhostscriptBinary(string $configured): array
{
    $configured = trim($configured);
    if ($configured === '') {
        $configured = 'gs';
    }

    $candidates = [$configured];
    if ($configured === 'gs') {
        $candidates[] = '/usr/bin/gs';
        $candidates[] = '/usr/local/bin/gs';
    }

    foreach (array_unique($candidates) as $bin) {
        if (ghostscriptAvailable($bin)) {
            return [$bin, true];
        }
    }

    return [$configured, false];
}

function sanitizeStoredBasename(string $name): string
{
    $base = basename($name);
    $base = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $base) ?? 'document.pdf';
    if (!str_ends_with(strtolower($base), '.pdf')) {
        $base .= '.pdf';
    }
    return substr($base, 0, 200);
}

function isPdfMagic(string $path): bool
{
    $h = @fopen($path, 'rb');
    if ($h === false) {
        return false;
    }
    $sig = fread($h, 5);
    fclose($h);
    return $sig === '%PDF-';
}

function generateFileId(): string
{
    return bin2hex(random_bytes(16));
}

function ensureDir(string $path): void
{
    if (!is_dir($path)) {
        mkdir($path, 0750, true);
    }
}

/**
 * Remove ficheiros órfãos mais antigos que TTL em temp e compressed.
 */
function cleanupExpiredFiles(array $config): void
{
    $ttl = (int) ($config['ttl_minutes'] ?? 30);
    if ($ttl < 1) {
        $ttl = 30;
    }
    $maxAge = $ttl * 60;

    foreach (['temp', 'compressed'] as $key) {
        $dir = $config['uploads'][$key] ?? null;
        if (!is_string($dir) || !is_dir($dir)) {
            continue;
        }
        $it = new DirectoryIterator($dir);
        foreach ($it as $file) {
            if ($file->isDot() || !$file->isFile()) {
                continue;
            }
            if (time() - $file->getMTime() > $maxAge) {
                @unlink($file->getPathname());
            }
        }
    }

    // Limpar entradas de sessão cujo ficheiro já não existe
    if (isset($_SESSION['files']) && is_array($_SESSION['files'])) {
        foreach ($_SESSION['files'] as $id => $meta) {
            if (!is_array($meta)) {
                unset($_SESSION['files'][$id]);
                continue;
            }
            $orig = $meta['original_path'] ?? '';
            $comp = $meta['compressed_path'] ?? '';
            $hasOrig = is_string($orig) && $orig !== '' && is_file($orig);
            $hasComp = is_string($comp) && $comp !== '' && is_file($comp);
            if (!$hasOrig && !$hasComp) {
                unset($_SESSION['files'][$id]);
            }
        }
    }
}

function unlinkIfExists(?string $path): void
{
    if (is_string($path) && $path !== '' && is_file($path)) {
        @unlink($path);
    }
}
