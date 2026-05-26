-- Actualizar color secundario a blanco (color de texto en cajas)
-- Solo si aún tiene el valor por defecto antiguo
UPDATE company_settings 
SET value = '#ffffff' 
WHERE `key` = 'pdf_secondary_color' 
AND (value = '#1A1A1A' OR value = '#1a1a1a' OR value = '');
