# Status Report da Sprint 2

## 1. Identificação

- **Projeto:** PagueProf
- **Número da Sprint:** 2
- **Período:** 17/09 a 08/10/2026
- **Data:** 08/10/2026
- **Scrum Master:** Talita
- **Integrantes e Funções:**
  - **Alan:** Back-End
  - **Fernando:** *(Transferido para o turno da noite)*
  - **Filipe:** Back-End e Banco de Dados
  - **Henrique:** Front-End
  - **Nickolas:** Back-End e Front-End
  - **Talita:** Scrum Master e Analista de QA
  - **Vitor:** Back-End e Front-End

### Meta da Sprint

> **Meta:** Dar continuidade ao desenvolvimento do código, implementando as tecnologias definidas (PHP no Back-End, HTML/CSS/JavaScript no Front-End e MySQL no Banco de Dados).

---

## 2. Resultado da Sprint 2

### Evolução da Sprint

- **Primeira Semana:** Definimos com clareza a stack tecnológica (PHP, HTML/CSS/JS, MySQL), avançamos na codificação das telas do Front-End e na lógica do Back-End, consolidamos a divisão de papéis da equipe e organizamos a documentação no Trello.
- **Segunda Semana:** Atualizamos a interface do sistema, implementamos as telas de cadastro e listagem de alunos, a visualização de pagamentos no dashboard e criamos a aba **Planos de Cobrança**, permitindo ao usuário configurar alertas de cobrança automática.

### Situação da meta

- [x] Alcançada
- [ ] Parcialmente alcançada
- [ ] Não alcançada

### Resultado alcançado

O usuário já consegue criar login e senha de acesso, navegar pelo dashboard e visualizar as seções de alunos, pagamentos, aulas e configurações. A interface visual e a estrutura inicial do banco de dados estão prontas, restando ajustar a integração completa de pagamentos e correções pontuais de erros.

### Principal dificuldade ou impedimento

Nenhum impedimento crítico na Sprint 2. A principal dificuldade residiu no refinamento da escolha das tecnologias e na organização das entregas do período.

---

## 3. Itens planejados e situação final

| User Story ou item | Responsável(is) | Situação final | Observação |
|---|---|---|---|
| US01 - Implementação do Banco de Dados | Toda a equipe | Concluído | Estrutura básica do MySQL criada e configurada. |
| US02 - Área para adicionar/gerenciar alunos | Toda a equipe | Concluído | Interface e cadastro funcional de alunos. |
| US03 - Criação e evolução do protótipo visual | Front-End / Equipe | Concluído | Telas principais do sistema finalizadas e atualizadas. |

---

## 4. Evidências e qualidade

### Evidências

- **Repositório:** [https://github.com/Vitorkp/PagueProf](https://github.com/Vitorkp/PagueProf)
- **Quadro Kanban:** [https://trello.com/b/XPZeyPlK/projeto-e-desenvolvimento-1](https://trello.com/b/XPZeyPlK/projeto-e-desenvolvimento-1)

### Checklist de qualidade

- [x] Os itens marcados como concluídos atendem aos critérios de aceite.
- [x] As funcionalidades entregues foram testadas pela equipe.
- [x] O código atualizado está no repositório oficial.
- [ ] Os problemas conhecidos estão registrados no Kanban ou no repositório.

### Problemas conhecidos

A integração completa da autenticação com a interface do Front-End ainda precisa ser finalizada na próxima etapa.

---

## 5. Retrospectiva da Sprint

### Manter
Alinhamento contínuo do time em relação ao design visual das telas e definições arquiteturais das tecnologias.

### Melhorar
Detalhamento dos critérios e regras de negócio no fluxo geral do sistema.

### Agir
Avançar no ajuste e validação dos ambientes PHP/MySQL e finalizar a integração completa das telas de autenticação.

---

## 6. Planejamento da próxima Sprint

### Meta da próxima Sprint

> **Meta:** Concluir a integração total do Banco de Dados com o Front-End via PHP, realizar testes de usabilidade e fluxo no sistema, e iniciar o levantamento de requisitos para integração de meios de pagamento (ex.: PIX).

### Itens inicialmente selecionados

| User Story ou item | Responsável(is) | Situação final | Observação |
|---|---|---|---|
| US04 - Estrutura do Front-End (HTML/CSS) | Vitor e Nickolas | Em andamento | Refinamento e conversão de protótipos em telas funcionais. |
| US05 - Modelagem do Banco de Dados | Alan e Filipe | Em andamento | Finalização do esquema relacional de usuários, turmas e pagamentos. |
| US06 - Conexão PHP com BD e validação | Alan e Filipe | Em andamento | Validação completa do fluxo de autenticação e sessão de usuário. |

### Riscos ou impedimentos previstos

Existe o risco de certas funcionalidades necessitarem de adaptações durante a implementação devido a limitações técnicas ou ajustes de requisitos. Para mitigar esse risco, a equipe continuará priorizando a entrega incremental das funcionalidades mais simples e essenciais antes de avançar para integrações mais complexas.