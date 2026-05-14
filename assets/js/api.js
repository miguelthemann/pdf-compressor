// Desenvolvido pelo Sr. Engenheiro João

const JSON_HEADERS = { 'Content-Type': 'application/json; charset=utf-8' };

function parseJsonSafe(text) {
    try {
        return JSON.parse(text);
    } catch {
        return null;
    }
}

/**
 * Upload com barra de progresso (XMLHttpRequest).
 * @param {File[]} files
 * @param {(pct: number) => void} onProgress
 */
export function uploadPdfs(files, onProgress) {
    return new Promise((resolve, reject) => {
        const fd = new FormData();
        for (const f of files) {
            fd.append('pdfs[]', f, f.name);
        }
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'upload.php');
        xhr.responseType = 'text';
        xhr.withCredentials = true;

        xhr.upload.onprogress = (e) => {
            if (e.lengthComputable) {
                const pct = Math.round((e.loaded / e.total) * 100);
                onProgress(pct);
            } else {
                onProgress(50);
            }
        };

        xhr.onload = () => {
            const raw = xhr.responseText || '';
            const data = parseJsonSafe(raw);
            if (xhr.status >= 200 && xhr.status < 300 && data && data.ok) {
                resolve(data);
                return;
            }
            if (data && data.error) {
                reject(new Error(data.error));
                return;
            }
            const snippet = raw.replace(/\s+/g, ' ').trim().slice(0, 180);
            const hint =
                xhr.status < 200 || xhr.status >= 300
                    ? `Resposta HTTP ${xhr.status}${snippet ? `: ${snippet}` : ''}`
                    : snippet
                      ? `Resposta inválida (não é JSON): ${snippet}`
                      : 'Resposta vazia ou inválida do servidor.';
            reject(new Error(`Falha no envio. ${hint}`));
        };

        xhr.onerror = () => reject(new Error('Erro de rede no envio.'));
        xhr.send(fd);
    });
}

/**
 * @param {string[]} ids
 * @param {'low'|'medium'|'high'} level
 */
export async function compressPdfs(ids, level) {
    const res = await fetch('compress.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: JSON_HEADERS,
        body: JSON.stringify({ ids, level }),
    });
    const text = await res.text();
    const data = parseJsonSafe(text);
    if (!res.ok || !data) {
        throw new Error(data && data.error ? data.error : 'Falha na compressão.');
    }
    if (!data.ok) {
        throw new Error(data.error || 'Falha na compressão.');
    }
    return data;
}

/**
 * @param {string[]} ids
 */
export async function deleteServerFiles(ids) {
    if (ids.length === 0) return;
    const res = await fetch('delete-temp.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: JSON_HEADERS,
        body: JSON.stringify({ ids }),
    });
    const data = parseJsonSafe(await res.text());
    if (!res.ok || !data || !data.ok) {
        throw new Error(data && data.error ? data.error : 'Não foi possível remover os ficheiros.');
    }
}

/**
 * Descarrega ZIP com os IDs indicados.
 * @param {string[]} ids
 */
export async function downloadZip(ids) {
    const res = await fetch('download.php?action=zip', {
        method: 'POST',
        credentials: 'same-origin',
        headers: JSON_HEADERS,
        body: JSON.stringify({ ids }),
    });
    if (!res.ok) {
        const t = await res.text();
        throw new Error(t.trim() || 'Não foi possível criar o arquivo.');
    }
    return res.blob();
}
