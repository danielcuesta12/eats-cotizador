-- Agregar tabla de cuentas bancarias
CREATE TABLE IF NOT EXISTS `bank_accounts` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bank_name`    VARCHAR(100) NOT NULL,
  `account_type` VARCHAR(50)  NOT NULL DEFAULT 'ahorros',
  `currency`     VARCHAR(10)  NOT NULL DEFAULT 'soles',
  `account_number` VARCHAR(50) NOT NULL,
  `cci`          VARCHAR(50)  NULL,
  `active`       TINYINT(1)   NOT NULL DEFAULT 1,
  `sort_order`   INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Setting para mostrar/ocultar en PDF
INSERT INTO company_settings (`key`, `value`)
VALUES ('show_bank_accounts', '1')
ON DUPLICATE KEY UPDATE `value` = `value`;
