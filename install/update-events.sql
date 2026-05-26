-- ============================================================
-- update-events.sql
-- Agregar soporte para eventos directos
-- Ejecutar en phpMyAdmin sobre ebakxdhm_cotizador
-- ============================================================

-- 1. Agregar columna origin a quotes (si no existe)
ALTER TABLE `quotes`
  ADD COLUMN IF NOT EXISTS `origin` ENUM('quote','event') NOT NULL DEFAULT 'quote',
  ADD COLUMN IF NOT EXISTS `event_time` VARCHAR(10) NULL AFTER `event_date`,
  ADD COLUMN IF NOT EXISTS `event_duration` VARCHAR(50) NULL AFTER `event_time`,
  ADD COLUMN IF NOT EXISTS `event_location` VARCHAR(255) NULL AFTER `event_duration`;

-- 2. Prefijo EV para eventos directos (ya manejado por PHP)
-- No requiere cambios en la tabla quotes_number
