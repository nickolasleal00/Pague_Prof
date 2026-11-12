-- PagueProf: avisos de ativação de perfis, exibidos uma única vez ao usuário.
-- Importar uma vez em base existente, depois de fazer backup.
CREATE TABLE IF NOT EXISTS avisos_perfil (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id BIGINT UNSIGNED NOT NULL,
  tipo VARCHAR(24) NOT NULL,
  profissional_id BIGINT UNSIGNED NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  lido_em DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY ix_avisos_perfil_pendentes (usuario_id, lido_em, id),
  KEY ix_avisos_perfil_profissional (profissional_id),
  CONSTRAINT fk_avisos_perfil_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_avisos_perfil_profissional FOREIGN KEY (profissional_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
