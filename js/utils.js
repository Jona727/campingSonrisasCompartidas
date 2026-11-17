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


// ============================================
// Experiencia de usuario
// ============================================

// GET que devuelve JSON; si la sesión expiró (401) lleva al login
async function fetchJson(url, opciones = {}) {
    const r = await fetch(url, { credentials: 'same-origin', ...opciones });
    if (r.status === 401) {
        window.location.href = 'login.html';
        throw new Error('Sesión expirada');
    }
    return r.json();
}

// Aviso flotante (reemplaza a alert()). tipo: success | danger | warning | info
function mostrarToast(mensaje, tipo = 'info', duracion = 5000) {
    let contenedor = document.getElementById('toast-container');
    if (!contenedor) {
        contenedor = document.createElement('div');
        contenedor.id = 'toast-container';
        contenedor.className = 'toast-container position-fixed top-0 end-0 p-3';
        contenedor.style.zIndex = '2000';
        contenedor.setAttribute('aria-live', 'polite');
        contenedor.setAttribute('aria-atomic', 'true');
        document.body.appendChild(contenedor);
    }

    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-bg-${tipo} border-0`;
    toast.setAttribute('role', tipo === 'danger' || tipo === 'warning' ? 'alert' : 'status');
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Cerrar aviso"></button>
        </div>`;
    toast.querySelector('.toast-body').textContent = mensaje;
    contenedor.appendChild(toast);

    if (window.bootstrap?.Toast) {
        const t = new bootstrap.Toast(toast, { delay: duracion });
        toast.addEventListener('hidden.bs.toast', () => toast.remove());
        t.show();
    } else {
        toast.classList.add('show');
        setTimeout(() => toast.remove(), duracion);
    }
}

// Diálogo de confirmación (reemplaza a confirm()). Devuelve una Promise<boolean>.
function confirmarAccion({ titulo = 'Confirmar', mensaje = '', textoConfirmar = 'Aceptar', tipo = 'danger' } = {}) {
    return new Promise(resolve => {
        const el = document.createElement('div');
        el.className = 'modal fade';
        el.tabIndex = -1;
        el.setAttribute('aria-hidden', 'true');
        el.innerHTML = `
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-4">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title fw-bold"></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body"></div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-${tipo}" data-confirmar>${escaparHtml(textoConfirmar)}</button>
                    </div>
                </div>
            </div>`;
        el.querySelector('.modal-title').textContent = titulo;
        el.querySelector('.modal-body').textContent = mensaje;
        document.body.appendChild(el);

        const modal = new bootstrap.Modal(el);
        let confirmado = false;
        el.querySelector('[data-confirmar]').addEventListener('click', () => {
            confirmado = true;
            modal.hide();
        });
        el.addEventListener('hidden.bs.modal', () => {
            el.remove();
            resolve(confirmado);
        });
        el.addEventListener('shown.bs.modal', () => el.querySelector('[data-confirmar]').focus());
        modal.show();
    });
}

// Valida un rango de fechas (AAAA-MM-DD). Avisa con un toast si hay un problema.
function rangoFechasValido(desde, hasta) {
    if (!desde || !hasta) {
        mostrarToast('Elegí las fechas "Desde" y "Hasta".', 'warning');
        return false;
    }
    if (desde > hasta) {
        mostrarToast('La fecha "Desde" no puede ser posterior a "Hasta".', 'warning');
        return false;
    }
    return true;
}
