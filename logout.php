<?php
require_once __DIR__ . '/php/seguridad.php';
iniciar_sesion_segura();
destruir_sesion();
header("Location: login.html");
exit();
