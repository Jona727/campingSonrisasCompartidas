// ============================================
// Utilidades compartidas (seguridad)
// ============================================

// Token CSRF: viene en <meta name="csrf-token"> (páginas PHP) o se pide a php/csrf.php (login)
let _csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || null;

async function obtenerCsrf(forzar = false) {
    if (_csrfToken && !forzar) return _csrfToken;
    const r = await fetch('php/csrf.php', { credentials: 'same-origin', cache: 'no-store' });
    const d = await r.json();
    _csrfToken = d.token;
    return _csrfToken;
}

// POST protegido con token CSRF. Devuelve el JSON de la respuesta.
// Si la sesión expiró (401) lleva al login.
async function postSeguro(url, formData) {
    const enviar = async (token) => {
        formData.set('csrf_token', token);
        return fetch(url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: { 'X-CSRF-Token': token }
        });
    };

    let respuesta = await enviar(await obtenerCsrf());

    // Token vencido: pedir uno nuevo y reintentar una sola vez
    if (respuesta.status === 403) {
        respuesta = await enviar(await obtenerCsrf(true));
    }

    if (respuesta.status === 401) {
        window.location.href = 'login.html';
        throw new Error('Sesión expirada');
    }

    return respuesta.json();
}

// Escapa texto para insertarlo de forma segura dentro de HTML (previene XSS)
function escaparHtml(valor) {
    return String(valor ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}
