-- ============================================
-- Migración 001 - Mejoras de seguridad
-- EJECUTAR UNA SOLA VEZ en tu base "camping_sonrisas"
-- (phpMyAdmin -> pestaña SQL, o importando este archivo)
-- ============================================

-- 1) Registro de intentos de login fallidos (límite de intentos)
CREATE TABLE IF NOT EXISTS login_intentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    usuario VARCHAR(50) NOT NULL,
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_fecha (ip, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Auditoría de anulaciones: quién anuló y cuándo
--    (si da error "Duplicate column name", ya estaba aplicada)
ALTER TABLE entradas
    ADD COLUMN anulado_por INT NULL,
    ADD COLUMN fecha_anulacion DATETIME NULL;
