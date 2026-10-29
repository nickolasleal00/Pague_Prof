-- PagueProf: estrutura completa do MVP.
-- Selecione primeiro a base da hospedagem no phpMyAdmin e importe este arquivo.
-- Todas as tabelas são IF NOT EXISTS: não apaga nem altera dados existentes.
-- Compatibilidade MySQL/MariaDB: somente criado_em usa TIMESTAMP automático.

CREATE TABLE IF NOT EXISTS usuarios (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome VARCHAR(140) NOT NULL,
  email VARCHAR(190) NOT NULL,
  senha VARCHAR(255) NOT NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (id), UNIQUE KEY uq_usuarios_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS perfis (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome VARCHAR(30) NOT NULL,
  PRIMARY KEY (id), UNIQUE KEY uq_perfis_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuarios_perfis (
  usuario_id BIGINT UNSIGNED NOT NULL,
  perfil_id SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (usuario_id, perfil_id),
  CONSTRAINT fk_usuarios_perfis_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_usuarios_perfis_perfil FOREIGN KEY (perfil_id) REFERENCES perfis(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO perfis (nome) VALUES ('profissional'), ('cliente');

CREATE TABLE IF NOT EXISTS clientes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  profissional_id BIGINT UNSIGNED NOT NULL,
  usuario_id BIGINT UNSIGNED NULL,
  nome VARCHAR(140) NOT NULL,
  email VARCHAR(190) NOT NULL,
  telefone VARCHAR(40) NULL,
  area_atuacao VARCHAR(100) NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cliente_profissional_email (profissional_id, email),
  KEY ix_clientes_usuario (usuario_id),
  CONSTRAINT fk_clientes_profissional FOREIGN KEY (profissional_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_clientes_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tipos_servico (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  profissional_id BIGINT UNSIGNED NOT NULL,
  nome VARCHAR(100) NOT NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY uq_tipo_servico_profissional_nome (profissional_id,nome),
  KEY ix_tipo_servico_profissional (profissional_id,ativo),
  CONSTRAINT fk_tipo_servico_profissional FOREIGN KEY (profissional_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS planos_cobranca (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  profissional_id BIGINT UNSIGNED NOT NULL,
  cliente_id BIGINT UNSIGNED NOT NULL,
  modalidade VARCHAR(20) NOT NULL DEFAULT 'mensal',
  valor DECIMAL(10,2) NOT NULL,
  frequencia VARCHAR(20) NOT NULL DEFAULT 'mensal',
  tipo_servico_id BIGINT UNSIGNED NULL,
  intervalo INT UNSIGNED NOT NULL DEFAULT 1,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY ix_planos_profissional_cliente (profissional_id, cliente_id, ativo),
  CONSTRAINT fk_planos_profissional FOREIGN KEY (profissional_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_planos_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
  CONSTRAINT fk_planos_tipo_servico FOREIGN KEY (tipo_servico_id) REFERENCES tipos_servico(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS configuracoes_profissional (
  profissional_id BIGINT UNSIGNED NOT NULL,
  instrucoes_pagamento VARCHAR(1000) NULL,
  atualizado_em DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (profissional_id),
  CONSTRAINT fk_config_profissional FOREIGN KEY (profissional_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cobrancas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  referencia_externa VARCHAR(80) NOT NULL,
  profissional_id BIGINT UNSIGNED NOT NULL,
  cliente_id BIGINT UNSIGNED NOT NULL,
  plano_id BIGINT UNSIGNED NULL,
  valor DECIMAL(10,2) NOT NULL,
  tipo_servico_id BIGINT UNSIGNED NULL,
  quantidade_atividades INT UNSIGNED NULL,
  data_vencimento DATE NOT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'pendente',
  data_pagamento DATETIME NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cobrancas_referencia (referencia_externa),
  KEY ix_cobrancas_profissional_status_data (profissional_id, status, data_vencimento),
  KEY ix_cobrancas_cliente_data (cliente_id, data_vencimento),
  UNIQUE KEY uq_cobrancas_plano_data (plano_id, data_vencimento),
  CONSTRAINT fk_cobrancas_profissional FOREIGN KEY (profissional_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_cobrancas_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
  CONSTRAINT fk_cobrancas_plano FOREIGN KEY (plano_id) REFERENCES planos_cobranca(id) ON DELETE SET NULL,
  CONSTRAINT fk_cobrancas_tipo_servico FOREIGN KEY (tipo_servico_id) REFERENCES tipos_servico(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comprovantes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cobranca_id BIGINT UNSIGNED NOT NULL,
  cliente_usuario_id BIGINT UNSIGNED NOT NULL,
  arquivo VARCHAR(255) NOT NULL,
  nome_original VARCHAR(255) NOT NULL,
  tipo_arquivo VARCHAR(80) NOT NULL,
  tamanho_bytes INT UNSIGNED NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'em_analise',
  observacao_profissional VARCHAR(500) NULL,
  enviado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  avaliado_em DATETIME NULL,
  PRIMARY KEY (id),
  KEY ix_comprovantes_cobranca (cobranca_id, enviado_em),
  CONSTRAINT fk_comprovantes_cobranca FOREIGN KEY (cobranca_id) REFERENCES cobrancas(id) ON DELETE CASCADE,
  CONSTRAINT fk_comprovantes_cliente FOREIGN KEY (cliente_usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notificacoes_email (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cobranca_id BIGINT UNSIGNED NOT NULL,
  comprovante_id BIGINT UNSIGNED NULL,
  tipo VARCHAR(50) NOT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'pendente',
  tentativas TINYINT UNSIGNED NOT NULL DEFAULT 0,
  ultimo_erro VARCHAR(250) NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  enviada_em DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_notificacao_cobranca_tipo (cobranca_id,tipo),
  KEY ix_notificacao_fila (status,tentativas,id),
  KEY ix_notificacao_email_status (status,id),
  KEY ix_notificacao_comprovante (comprovante_id),
  CONSTRAINT fk_notificacao_cobranca FOREIGN KEY (cobranca_id) REFERENCES cobrancas(id) ON DELETE CASCADE,
  CONSTRAINT fk_notificacao_comprovante FOREIGN KEY (comprovante_id) REFERENCES comprovantes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recuperacao_senha (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expira_em DATETIME NOT NULL,
  usado_em DATETIME NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_recuperacao_token (token_hash),
  KEY ix_recuperacao_usuario_data (usuario_id, criado_em),
  KEY ix_recuperacao_expira (expira_em),
  CONSTRAINT fk_recuperacao_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
