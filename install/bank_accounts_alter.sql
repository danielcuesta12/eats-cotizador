-- Agregar columnas account_holder y tax_id a bank_accounts
ALTER TABLE `bank_accounts`
  ADD COLUMN IF NOT EXISTS `account_holder` VARCHAR(150) NULL AFTER `bank_name`,
  ADD COLUMN IF NOT EXISTS `tax_id`         VARCHAR(20)  NULL AFTER `account_holder`;
