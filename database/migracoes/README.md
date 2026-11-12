# Migrações

Faça backup da base antes de executar qualquer migração. Em uma base existente, importe no phpMyAdmin, com a base correta selecionada, o script aditivo correspondente aos recursos que ainda não foram aplicados:

- `20261112_avisos_perfil.sql`: cria a tabela `avisos_perfil`, usada para registrar e exibir uma única vez os avisos de conversão/ativação de perfis.
- `20261030_recebimentos_pix_estatico.sql`: adiciona os campos de recebimento Pix estático à tabela `configuracoes_profissional`, caso essa atualização ainda não tenha sido executada.

Os scripts são repetíveis e não apagam nem recriam os dados existentes. Não reimporte `database/estrutura.sql` sobre uma base já utilizada.

Para uma instalação nova, importe somente `../estrutura.sql`; o schema completo inclui as tabelas e campos atuais.

A série de cobranças continua usando internamente `planos_cobranca`. Esse vínculo técnico não é um cadastro de plano feito pelo profissional. Não execute scripts legados de outro release sem revisar o schema e fazer backup.
