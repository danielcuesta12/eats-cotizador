-- Orden de categorías en el cotizador
UPDATE categories SET sort_order = 1 WHERE name = 'Desayuno';
UPDATE categories SET sort_order = 2 WHERE name = 'Almuerzo';
UPDATE categories SET sort_order = 3 WHERE name = 'Cena';
UPDATE categories SET sort_order = 4 WHERE name = 'Rancho Frio';
