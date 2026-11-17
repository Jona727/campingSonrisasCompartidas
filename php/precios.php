<?php
// ============================================
// ÚNICA fuente de verdad de los precios de entrada.
// Si cambia un precio, se cambia SOLO acá: lo usan
// el servidor (control_acceso.php) y la pantalla (index.php).
// ============================================

function precios_entradas(): array
{
    return [
        'turista_adulto'   => 8000,
        'turista_niño'     => 4000,
        'turista_jubilado' => 4800,
        'local_adulto'     => 4000,
        'local_niño'       => 0,
        'local_jubilado'   => 2400,
        'local'            => 3000,   // Compatibilidad con registros antiguos
    ];
}

// "$8.000" o "GRATIS"
function precio_ar(string $tipo): string
{
    $p = precios_entradas()[$tipo] ?? 0;
    return $p === 0 ? 'GRATIS' : '$' . number_format($p, 0, ',', '.');
}
