-- Migración: agregar columna show_in_calendar a quotes
-- Ejecutar en producción antes de desplegar los cambios del cotizador EATS

ALTER TABLE quotes
  ADD COLUMN IF NOT EXISTS show_in_calendar TINYINT(1) NOT NULL DEFAULT 0;
