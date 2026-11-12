-- Aditiva, não destrutiva: manter nomes físicos históricos por compatibilidade.
-- Faça backup antes de aplicar em produção. Não executada nesta implementação.
INSERT IGNORE INTO perfis (nome) VALUES ('profissional'),('cliente');
INSERT IGNORE INTO usuarios_perfis (usuario_id,perfil_id)
 SELECT up.usuario_id,pnovo.id FROM usuarios_perfis up JOIN perfis pantigo ON pantigo.id=up.perfil_id
 JOIN perfis pnovo ON pnovo.nome=CASE pantigo.nome WHEN 'professor' THEN 'profissional' WHEN 'aluno' THEN 'cliente' END
 WHERE pantigo.nome IN ('professor','aluno');
DELETE up FROM usuarios_perfis up JOIN perfis p ON p.id=up.perfil_id WHERE p.nome IN ('professor','aluno');
DELETE FROM perfis WHERE nome IN ('professor','aluno');
CREATE TABLE IF NOT EXISTS tipos_servico (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 profissional_id BIGINT UNSIGNED NOT NULL,
 nome VARCHAR(100) NOT NULL,
 ativo TINYINT(1) NOT NULL DEFAULT 1,
 criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (id), UNIQUE KEY uq_tipo_servico_prof_nome (profissional_id,nome), KEY ix_tipo_servico_prof (profissional_id,ativo),
 CONSTRAINT fk_tipo_servico_prof FOREIGN KEY (profissional_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO tipos_servico (profissional_id,nome)
 SELECT DISTINCT pc.professor_id,'Aula' FROM planos_cobranca pc WHERE pc.modalidade='por_aula';
SET @q=IF(NOT EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='planos_cobranca' AND column_name='tipo_servico_id'),'ALTER TABLE planos_cobranca ADD COLUMN tipo_servico_id BIGINT UNSIGNED NULL','DO 0'); PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @q=IF(NOT EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='planos_cobranca' AND index_name='ix_planos_tipo_servico'),'ALTER TABLE planos_cobranca ADD KEY ix_planos_tipo_servico (tipo_servico_id)','DO 0'); PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @q=IF(NOT EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='cobrancas' AND column_name='tipo_servico_id'),'ALTER TABLE cobrancas ADD COLUMN tipo_servico_id BIGINT UNSIGNED NULL','DO 0'); PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @q=IF(NOT EXISTS(SELECT 1 FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='cobrancas' AND column_name='quantidade_atividades'),'ALTER TABLE cobrancas ADD COLUMN quantidade_atividades INT UNSIGNED NULL','DO 0'); PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
UPDATE planos_cobranca pc JOIN tipos_servico ts ON ts.profissional_id=pc.professor_id AND ts.nome='Aula' SET pc.tipo_servico_id=ts.id,pc.modalidade='por_atividade' WHERE pc.modalidade='por_aula';
UPDATE planos_cobranca SET frequencia='mensal',intervalo=1 WHERE frequencia<>'mensal';
UPDATE cobrancas c JOIN planos_cobranca pc ON pc.id=c.plano_id SET c.tipo_servico_id=pc.tipo_servico_id,c.quantidade_atividades=1 WHERE pc.modalidade='por_atividade' AND c.tipo_servico_id IS NULL;
SET @q=IF(NOT EXISTS(SELECT 1 FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='notificacoes_email' AND index_name='ix_notificacao_email_status'),'ALTER TABLE notificacoes_email ADD KEY ix_notificacao_email_status (status,id)','DO 0'); PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
SET @q=IF((SELECT character_maximum_length FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='notificacoes_email' AND column_name='status')<32,'ALTER TABLE notificacoes_email MODIFY status VARCHAR(32) NOT NULL DEFAULT ''pendente''','DO 0'); PREPARE s FROM @q; EXECUTE s; DEALLOCATE PREPARE s;
