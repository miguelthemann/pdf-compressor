<?php

declare(strict_types=1);

/**
 * Configuração central — ajuste conforme o servidor.
 */
return [
    // Caminho absoluto ou relativo ao documento web (public/)
    'base_path' => dirname(__DIR__),

    'uploads' => [
        'temp' => dirname(__DIR__) . '/uploads/temp',
        'compressed' => dirname(__DIR__) . '/uploads/compressed',
    ],

    /** Tamanho máximo por ficheiro (bytes) — ex.: 50 MB */
    'max_file_bytes' => 50 * 1024 * 1024,

    /** Total máximo por pedido de upload (bytes) */
    'max_batch_bytes' => 5 * 1024 * 1024 * 1024,

    /** Máximo de ficheiros num único upload */
    'max_files_per_upload' => 1000,

    /**
     * Ficheiros apagados automaticamente após este tempo (minutos)
     * se não forem descarregados.
     */
    'ttl_minutes' => 30,

    /** Binário Ghostscript (Linux: normalmente "gs") */
    'ghostscript_bin' => 'C:/Programas/gs/gs10.07.0/bin/gswin64c.exe',

    /**
     * Níveis → -dPDFSETTINGS do Ghostscript
     */
    'pdf_settings' => [
        'low' => '/printer',   // baixa qualidade (ficheiro maior, melhor qualidade)
        'medium' => '/ebook',
        'high' => '/screen', // alta compressão (ficheiro menor)
    ],

    'session_name' => 'pdfcompress_sid',
];
