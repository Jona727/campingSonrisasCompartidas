// Función para manejar el formulario de registro de ingresos
document.getElementById('formulario-ingreso')?.addEventListener('submit', function (e) {
    e.preventDefault();

    const formData = new FormData(this);

    postSeguro('php/control_acceso.php', formData)
        .then(data => {
            const alerta = document.getElementById('alerta');
            alerta.classList.remove('d-none');

            if (data.success) {
                alerta.textContent = "Ingreso registrado correctamente.";
                alerta.classList.add('alert-success');
                alerta.classList.remove('alert-danger');
            } else {
                alerta.textContent = data.message;
                alerta.classList.add('alert-danger');
                alerta.classList.remove('alert-success');
            }
        })
        .catch(error => console.error('Error:', error));
});

// Función para manejar el formulario de reserva de quincho
document.getElementById('reserva-quincho')?.addEventListener('submit', function (e) {
    e.preventDefault();

    const formData = new FormData(this);

    postSeguro('php/alquileres_backend.php', formData)
        .then(data => {
            const alerta = document.getElementById('alerta');
            alerta.classList.remove('d-none');

            if (data.success) {
                alerta.textContent = data.message;
                alerta.classList.add('alert-success');
                alerta.classList.remove('alert-danger');
            } else {
                alerta.textContent = data.message;
                alerta.classList.add('alert-danger');
                alerta.classList.remove('alert-success');
            }
        })
        .catch(error => console.error('Error:', error));
});

// Función para manejar el formulario de alquiler de reposera
document.getElementById('alquiler-reposera')?.addEventListener('submit', function (e) {
    e.preventDefault();

    const formData = new FormData(this);

    postSeguro('php/alquileres_backend.php', formData)
        .then(data => {
            const alerta = document.getElementById('alerta');
            alerta.classList.remove('d-none');

            if (data.success) {
                alerta.textContent = data.message;
                alerta.classList.add('alert-success');
                alerta.classList.remove('alert-danger');
            } else {
                alerta.textContent = data.message;
                alerta.classList.add('alert-danger');
                alerta.classList.remove('alert-success');
            }
        })
        .catch(error => console.error('Error:', error));
});