# Sprint 4 — Área do Cliente, Comprovantes e Renovação

## 1. Identificação da Sprint

- **Projeto:** PagueProf
- **Sprint:** 4
- **Período:** 29/10/2026 a 05/11/2026
- **Data de início:** 29/10/2026
- **Data de encerramento:** 05/11/2026
- **Scrum Master:** Talita

### Integrantes da equipe

| Integrante | Responsabilidade |
|---|---|
| Alan | Back-end |
| Fernando | Transferido para o período noturno |
| Filipe | Back-end e Banco de Dados |
| Henrique | Front-end |
| Nickolas | Front-end e Back-end |
| Talita | Scrum Master e QA Analyst |
| Vitor | Front-end e Back-end |

## 2. Meta da Sprint

Exibir as cobranças ao cliente, oferecer a possibilidade de confirmação manual de pagamento por meio do envio de comprovantes e sugerir a renovação do plano quando não existirem cobranças futuras.

## 3. Histórias de Usuário

**US10 — Ver meus pagamentos**

Como cliente, quero visualizar minhas cobranças pagas, a pagar e vencidas, para acompanhar minha situação financeira.

**US11 — Enviar comprovante**

Como cliente, quero anexar um comprovante de pagamento para que o profissional possa conferir.

**US12 — Conferir comprovante**

Como profissional, quero aprovar ou recusar um comprovante e registrar uma observação, para controlar a confirmação dos pagamentos.

**US13 — Renovar plano**

Como profissional, quero receber uma sugestão de novas cobranças na frequência anterior quando não houver vencimentos futuros, para facilitar a renovação do plano.

## 4. Tarefas da Sprint

- Desenvolver a tela de pagamentos vinculada à conta do cliente pelo e-mail.
- Implementar o upload de comprovantes nos formatos PDF, JPG e PNG, com limite de 5 MB.
- Armazenar os arquivos fora do acesso HTTP direto.
- Implementar a aprovação ou recusa dos comprovantes, com registro da decisão.
- Realizar a atualização segura do status das cobranças.
- Manter o histórico de comprovantes e decisões.
- Desenvolver a sugestão de renovação do plano, sem gerar cobranças até a confirmação do profissional.

## 5. Responsáveis pelas Atividades

| Integrantes | Atividades |
|---|---|
| Vitor e Nickolas | Desenvolvimento do portal do cliente e estados responsivos. |
| Alan | Implementação de autorização, upload e consultas. |
| Filipe | Desenvolvimento dos estados financeiros e regras de negócio. |
| Talita | Testes ponta a ponta, acessibilidade e validação dos critérios de aceite. |

## 6. Critérios de Aceite

- O cliente deve visualizar somente suas próprias cobranças.
- O comprovante só poderá ser anexado a uma cobrança do próprio cliente que ainda não esteja paga.
- Arquivos fora dos formatos permitidos devem ser rejeitados.
- Somente o profissional responsável pela cobrança poderá abrir e analisar o comprovante.
- A aprovação do comprovante deve registrar a confirmação do pagamento e dar baixa na cobrança.
- A recusa deve devolver a cobrança à fila de pendências.
- A sugestão de renovação deve respeitar a frequência do plano anterior.
- Nenhuma cobrança deve ser gerada automaticamente sem a confirmação do profissional.

## 7. Resultado Esperado

  o PagueProf permitiu ao cliente consultar suas cobranças e enviar comprovantes de pagamento. O profissional deverá conseguir analisar os comprovantes, aprovar ou recusar pagamentos e receber sugestões de renovação de planos, mantendo o controle sobre a geração de novas cobranças.
