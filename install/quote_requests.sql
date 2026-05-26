-- ============================================================
-- Tabla: quote_requests
-- Solicitudes públicas de cotización
-- Ejecutar en phpMyAdmin sobre ebakxdhm_cotizador
-- ============================================================

CREATE TABLE IF NOT EXISTS `quote_requests` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type`           ENUM('empresa','persona') NOT NULL DEFAULT 'empresa',
  `name`           VARCHAR(200) NOT NULL,
  `ruc_dni`        VARCHAR(20)  NULL,
  `contact_name`   VARCHAR(150) NULL,
  `email`          VARCHAR(150) NULL,
  `phone`          VARCHAR(30)  NULL,
  `event_date`     DATE         NULL,
  `event_time`     VARCHAR(10)  NULL,
  `event_duration` VARCHAR(50)  NULL,
  `event_location` VARCHAR(255) NULL,
  `num_people`     INT UNSIGNED NULL DEFAULT 0,
  `comments`       TEXT         NULL,
  `status`         ENUM('pendiente','aceptada','rechazada') NOT NULL DEFAULT 'pendiente',
  `reviewed_by`    INT UNSIGNED NULL,
  `reviewed_at`    DATETIME     NULL,
  `client_id`      INT UNSIGNED NULL,
  `ip_address`     VARCHAR(45)  NULL,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_qr_status` (`status`),
  INDEX `idx_qr_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
