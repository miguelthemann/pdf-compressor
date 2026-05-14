// Desenvolvido pelo Sr. Engenheiro João

/**
 * Formata bytes para texto legível (pt-PT).
 */
export function formatBytes(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    const n = bytes / Math.pow(k, i);
    const dec = i === 0 ? 0 : n < 10 ? 2 : 1;
    return `${n.toLocaleString('pt-PT', { maximumFractionDigits: dec })} ${sizes[i]}`;
}

export function randomId() {
    return crypto.randomUUID?.() || `local_${Date.now()}_${Math.random().toString(16).slice(2)}`;
}

/**
 * Verifica assinatura %PDF- no cliente (pré-validação).
 */
export async function fileLooksLikePdf(file) {
    const buf = await file.slice(0, 5).arrayBuffer();
    const s = new TextDecoder().decode(buf);
    return s.startsWith('%PDF-');
}
