# Migrações

A versão atual adiciona dados de recebimento Pix estático à tabela `configuracoes_profissional`.

Para uma base existente, faça backup e importe uma vez o arquivo `20261030_recebimentos_pix_estatico.sql` no phpMyAdmin, com a base correta selecionada. O script verifica cada coluna e pode ser executado novamente sem tentar recriar colunas já presentes. Não apague dados nem reimporte `database/estrutura.sql` sobre uma base existente.

Para uma instalação realmente nova, importe somente `../estrutura.sql`; o schema completo já inclui os quatro campos adicionados nesta migração.

A série de cobranças continua usando internamente `planos_cobranca`. Esse vínculo técnico não é um cadastro de plano feito pelo profissional. Não execute scripts legados de outro release sem revisar o schema e fazer backup.
