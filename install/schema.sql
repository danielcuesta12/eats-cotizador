-- ============================================================
-- SISTEMA DE COTIZACIÓN - EL GRINGO BURGER JOINT
-- Schema v1.0 | MySQL 5.7+ / MariaDB 10.3+
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- TABLA: users
-- Usuarios del sistema (admin y asistente)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(100)    NOT NULL,
  `email`        VARCHAR(150)    NOT NULL UNIQUE,
  `password`     VARCHAR(255)    NOT NULL,             -- bcrypt
  `role`         ENUM('admin','asistente') NOT NULL DEFAULT 'asistente',
  `active`       TINYINT(1)      NOT NULL DEFAULT 1,
  `remember_token` VARCHAR(64)   NULL DEFAULT NULL,
  `remember_expires` DATETIME    NULL DEFAULT NULL,
  `last_login`   DATETIME        NULL DEFAULT NULL,
  `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABLA: company_settings
-- Datos de la empresa (logo, info, T&C, config global)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `company_settings` (
  `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `key`             VARCHAR(100)  NOT NULL UNIQUE,
  `value`           TEXT          NULL,
  `updated_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABLA: categories
-- Categorías de productos (Burgers, Crispy, Salchipapas, etc.)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100)    NOT NULL,
  `description` TEXT            NULL,
  `sort_order`  SMALLINT        NOT NULL DEFAULT 0,
  `active`      TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABLA: products
-- Productos con precio dual (por persona / por evento / libre)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `products` (
  `id`                INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `category_id`       INT UNSIGNED    NULL,
  `name`              VARCHAR(200)    NOT NULL,
  `description`       TEXT            NULL,
  `price_per_person`  DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
  `price_per_event`   DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
  `image`             VARCHAR(255)    NULL,             -- ruta relativa al archivo
  `active`            TINYINT(1)      NOT NULL DEFAULT 1,
  `sort_order`        SMALLINT        NOT NULL DEFAULT 0,
  `created_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_products_category`
    FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABLA: packages
-- Combos o paquetes (agrupación de productos con precio especial)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `packages` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(200)    NOT NULL,
  `description`   TEXT            NULL,
  `price`         DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
  `active`        TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABLA: package_products
-- Relación N:N entre paquetes y productos
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `package_products` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `package_id`  INT UNSIGNED    NOT NULL,
  `product_id`  INT UNSIGNED    NOT NULL,
  `quantity`    DECIMAL(10,2)   NOT NULL DEFAULT 1,
  `notes`       VARCHAR(255)    NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_pp_package`
    FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pp_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABLA: clients
-- Clientes (empresa o persona natural)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `clients` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `type`          ENUM('empresa','persona') NOT NULL DEFAULT 'persona',
  `name`          VARCHAR(200)    NOT NULL,             -- razón social o nombre completo
  `ruc_dni`       VARCHAR(20)     NULL,                 -- RUC (empresa) o DNI (persona)
  `contact_name`  VARCHAR(150)    NULL,                 -- contacto dentro de la empresa
  `email`         VARCHAR(150)    NULL,
  `phone`         VARCHAR(30)     NULL,
  `address`       TEXT            NULL,
  `notes`         TEXT            NULL,
  `active`        TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABLA: quote_templates
-- Plantillas de términos y condiciones reutilizables
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `quote_templates` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(150)    NOT NULL,
  `terms`       TEXT            NULL,
  `observations`TEXT            NULL,
  `is_default`  TINYINT(1)      NOT NULL DEFAULT 0,
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABLA: quotes
-- Cotizaciones (cabecera)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `quotes` (
  `id`              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `quote_number`    VARCHAR(20)     NOT NULL UNIQUE,    -- EG-2024-001
  `client_id`       INT UNSIGNED    NOT NULL,
  `user_id`         INT UNSIGNED    NOT NULL,           -- quién la creó
  `event_type`      VARCHAR(100)    NULL,               -- Corporativo, Boda, etc.
  `event_date`      DATE            NULL,
  `event_location`  VARCHAR(255)    NULL,
  `num_people`      INT UNSIGNED    NULL DEFAULT 0,

  -- Totales calculados (se guardan para historial)
  `subtotal`        DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `discount_pct`    DECIMAL(5,2)    NOT NULL DEFAULT 0.00,  -- % global de descuento
  `discount_amount` DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `extras_amount`   DECIMAL(12,2)   NOT NULL DEFAULT 0.00,  -- costos extra (movilidad, etc.)
  `igv_type`        ENUM('none','10.5','18') NOT NULL DEFAULT 'none',
  `igv_amount`      DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `total`           DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `price_per_person`DECIMAL(12,2)   NOT NULL DEFAULT 0.00,  -- calculado automáticamente

  -- Textos
  `observations`    TEXT            NULL,
  `terms`           TEXT            NULL,
  `extras_detail`   VARCHAR(500)    NULL,               -- descripción de costos extra

  -- Estado y seguimiento
  `status`          ENUM('borrador','enviada','aceptada','rechazada') NOT NULL DEFAULT 'borrador',
  `status_note`     VARCHAR(255)    NULL,               -- nota al cambiar estado
  `sent_at`         DATETIME        NULL,
  `accepted_at`     DATETIME        NULL,
  `rejected_at`     DATETIME        NULL,

  -- Link público
  `public_token`    VARCHAR(64)     NOT NULL,           -- token único para link público
  `valid_until`     DATE            NULL,               -- vencimiento del link/cotización

  `created_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_quotes_status` (`status`),
  INDEX `idx_quotes_client` (`client_id`),
  INDEX `idx_quotes_date` (`event_date`),
  INDEX `idx_quotes_token` (`public_token`),
  CONSTRAINT `fk_quotes_client`
    FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_quotes_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABLA: quote_items
-- Ítems de cada cotización (productos + modo precio)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `quote_items` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `quote_id`      INT UNSIGNED    NOT NULL,
  `product_id`    INT UNSIGNED    NULL,                 -- NULL si es ítem manual
  `package_id`    INT UNSIGNED    NULL,                 -- NULL si no es paquete
  `name`          VARCHAR(200)    NOT NULL,             -- copia del nombre al momento
  `description`   TEXT            NULL,
  `price_mode`    ENUM('per_person','per_event','custom') NOT NULL DEFAULT 'per_person',
  `unit_price`    DECIMAL(10,2)   NOT NULL DEFAULT 0.00,-- precio unitario aplicado
  `quantity`      DECIMAL(10,2)   NOT NULL DEFAULT 1.00,
  `discount_pct`  DECIMAL(5,2)    NOT NULL DEFAULT 0.00,-- descuento por ítem %
  `subtotal`      DECIMAL(12,2)   NOT NULL DEFAULT 0.00,-- calculado
  `sort_order`    SMALLINT        NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  INDEX `idx_items_quote` (`quote_id`),
  CONSTRAINT `fk_items_quote`
    FOREIGN KEY (`quote_id`) REFERENCES `quotes` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_items_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABLA: quote_status_log
-- Historial de cambios de estado (auditoría)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `quote_status_log` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `quote_id`    INT UNSIGNED    NOT NULL,
  `user_id`     INT UNSIGNED    NOT NULL,
  `from_status` VARCHAR(20)     NULL,
  `to_status`   VARCHAR(20)     NOT NULL,
  `note`        VARCHAR(255)    NULL,
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_log_quote`
    FOREIGN KEY (`quote_id`) REFERENCES `quotes` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- DATOS INICIALES
-- ============================================================

-- Usuario admin por defecto (contraseña: Admin2024! — cambiar al instalar)
INSERT INTO `users` (`name`, `email`, `password`, `role`) VALUES
('Administrador', 'admin@elgringo.pe', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uXkjvW.wW', 'admin');
-- NOTA: la contraseña hash es 'password' — el setup.php genera la real

-- Configuración inicial de la empresa
INSERT INTO `company_settings` (`key`, `value`) VALUES
('company_name',     'El Gringo Burger Joint'),
('company_ruc',      ''),
('company_address',  'Lima, Perú'),
('company_phone',    ''),
('company_email',    ''),
('company_website',  ''),
('company_logo',     ''),
('quote_prefix',     'EG'),
('quote_validity_days', '15'),
('default_igv',      'none'),
('default_terms',    'El presente presupuesto tiene una vigencia de 15 días calendario.\nLos precios están expresados en Soles (S/) y no incluyen IGV salvo indicación contraria.\nSe requiere un adelanto del 50% para confirmar la reserva del evento.\nEl saldo restante debe ser cancelado antes del inicio del evento.\nCualquier cambio en la cantidad de personas debe ser comunicado con 72 horas de anticipación.'),
('default_observations', ''),
('whatsapp_number',  ''),
('pdf_primary_color','#C8102E'),
('pdf_secondary_color','#1A1A1A');

-- Categorías iniciales
INSERT INTO `categories` (`name`, `description`, `sort_order`) VALUES
('Smash Burgers',   'Hamburguesas al estilo smash, crujientes y jugosas', 1),
('Pollo Crispy',    'Pollos y sándwiches de pollo crujiente', 2),
('Salchipapas',     'Salchipapas y papas fritas especiales', 3),
('Bebidas',         'Bebidas frías, jugos y gaseosas', 4),
('Extras & Servicios', 'Servicios adicionales, personal, equipos', 5);

-- Plantilla de T&C por defecto
INSERT INTO `quote_templates` (`name`, `terms`, `is_default`) VALUES
('Estándar Eventos',
'1. Esta cotización tiene validez de 15 días desde su emisión.\n2. Se requiere un adelanto del 50% para confirmar la fecha.\n3. El saldo se cancela antes del inicio del evento.\n4. Cambios en cantidad de comensales deben comunicarse con 72h de anticipación.\n5. Los precios incluyen montaje y desmontaje del puesto.\n6. No nos hacemos responsables por daños ajenos a nuestra operación.',
1);
