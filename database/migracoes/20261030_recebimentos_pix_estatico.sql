-- Adiciona os dados do QR Pix estático aos recebimentos do profissional.
-- Pode ser executado mais de uma vez; cada coluna só é criada se estiver ausente.

SET @ddl := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='configuracoes_profissional' AND COLUMN_NAME='nome_recebedor')=0,
  'ALTER TABLE configuracoes_profissional ADD COLUMN nome_recebedor VARCHAR(100) NULL AFTER instrucoes_pagamento',
  'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='configuracoes_profissional' AND COLUMN_NAME='chave_pix_tipo')=0,
  'ALTER TABLE configuracoes_profissional ADD COLUMN chave_pix_tipo VARCHAR(12) NULL AFTER nome_recebedor',
  'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='configuracoes_profissional' AND COLUMN_NAME='chave_pix')=0,
  'ALTER TABLE configuracoes_profissional ADD COLUMN chave_pix VARCHAR(100) NULL AFTER chave_pix_tipo',
  'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ddl := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='configuracoes_profissional' AND COLUMN_NAME='cidade_recebedor')=0,
  'ALTER TABLE configuracoes_profissional ADD COLUMN cidade_recebedor VARCHAR(50) NULL AFTER chave_pix',
  'SELECT 1'
);
PREPARE stmt FROM @ddl; EXECUTE stmt; DEALLOCATE PREPARE stmt;
