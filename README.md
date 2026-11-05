# 📚 PagueProf

> **Plataforma para auxiliar professores autônomos no gerenciamento de alunos, aulas e pagamentos.**

<p align="center">

**Organize seus alunos. Controle seus pagamentos. Simplifique sua rotina.**

</p>

---

## 📌 Sobre o projeto

O **PagueProf** é uma plataforma desenvolvida para auxiliar **professores autônomos** que trabalham com aulas particulares ou pequenos grupos.

A solução é voltada para profissionais de diferentes áreas, como:

- 🎵 Professores de música;
- 🎨 Professores de desenho e pintura;
- 📖 Professores particulares;
- 🌎 Professores de idiomas;
- 💻 Professores de programação;
- 🏋️ Instrutores e professores que trabalham com aulas individuais.

O projeto surgiu a partir de uma dificuldade comum enfrentada por esses profissionais: **o controle manual de alunos, aulas e pagamentos**.

Muitos professores utilizam planilhas, anotações ou conversas pelo WhatsApp para acompanhar pagamentos, aulas contratadas e cobranças. Esse processo pode gerar esquecimentos, retrabalho e dificuldade para visualizar a situação financeira.

O **PagueProf** busca centralizar essas informações em um único ambiente, tornando o gerenciamento mais simples, rápido e organizado.

---

## 🎯 Objetivo

O principal objetivo do PagueProf é **facilitar a gestão financeira e administrativa de professores autônomos**, permitindo acompanhar seus alunos, pacotes de aulas e pagamentos de maneira rápida e intuitiva.

A plataforma também busca facilitar o contato com alunos ou responsáveis em situações de pagamento pendente ou atrasado.

### Objetivos específicos

- 👨‍🏫 Gerenciar alunos;
- 📚 Controlar pacotes e planos de aulas;
- 💰 Acompanhar pagamentos;
- 📊 Visualizar informações por meio de um dashboard;
- 🔔 Facilitar cobranças e lembretes;
- 📱 Centralizar informações dos alunos;
- 🗂️ Manter um histórico organizado.

---

## 💡 Problema

Professores autônomos frequentemente precisam controlar manualmente informações como:

- Quais alunos possuem aulas;
- Qual modalidade de aula cada aluno realiza;
- Quantas aulas foram contratadas;
- Quais pagamentos já foram realizados;
- Quais pagamentos estão pendentes;
- Quais pagamentos estão atrasados;
- Quando cada pagamento deve ser realizado;
- Quem precisa receber uma cobrança.

Além disso, realizar cobranças individualmente por mensagens pode ser uma tarefa repetitiva, demorada e pouco prática.

### ❓ Como o PagueProf ajuda?

A proposta é substituir controles espalhados em diferentes ferramentas por uma plataforma centralizada, permitindo que o professor visualize rapidamente a situação dos seus alunos e pagamentos.

---

## 🔐 Autenticação de usuário

O sistema possui uma estrutura de acesso por login e senha, permitindo que o professor entre na plataforma e acesse as funcionalidades disponíveis.

## 👥 Gerenciamento de alunos

O professor pode cadastrar e consultar seus alunos, mantendo informações necessárias para o acompanhamento das aulas e pagamentos.

Entre as informações previstas estão:

- Nome;
- Modalidade da aula;
- Contato;
- Pacote contratado;
- Quantidade de aulas;
- Situação dos pagamentos;
- Histórico.

## 📚 Planos e pacotes de aulas

A plataforma contempla planos de cobrança que permitem organizar os valores e as condições de pagamento dos alunos.

Exemplos de pacotes:

| Pacote | Quantidade de aulas |
|---|---:|
| 📘 Básico | 4 aulas |
| 📗 Intermediário | 8 aulas |
| 📕 Completo | 12 aulas |
| ⚙️ Personalizado | Definido pelo professor |

Os pacotes podem contemplar informações como quantidade de aulas, valor, modalidade e período de utilização.

## 💰 Controle de pagamentos e cobranças

O sistema permite acompanhar a situação financeira dos alunos, facilitando a identificação de pagamentos realizados e pendentes.

### Exemplo de visualização

| Aluno | Aula | Valor | Status |
|---|---|---:|---|
| João Silva | Violão | R$ 400,00 | 🟢 Pago |
| Maria Souza | Desenho | R$ 250,00 | 🟡 Pendente |
| Vitor | Piano | R$ 250,00 | 🔴 Atrasado |

### Status dos pagamentos

- 🟢 **Pago:** pagamento realizado;
- 🟡 **Pendente:** pagamento aguardando confirmação;
- 🔴 **Atrasado:** pagamento fora do prazo.

## 📊 Dashboard

O dashboard apresenta um resumo das principais informações do sistema, facilitando o acompanhamento da rotina do professor.

Entre os indicadores estão:

- 👥 Total de alunos;
- 🟢 Pagamentos realizados;
- 🟡 Pagamentos pendentes;
- 🔴 Pagamentos atrasados;
- 💰 Valores a receber.

## 📱 Detalhes do aluno

Ao selecionar um aluno, o professor poderá consultar informações individuais, como:

```text
Aluno
├── Modalidade da aula
├── Pacote contratado
├── Quantidade de aulas
├── Aulas realizadas
├── Aulas restantes
├── Próximo pagamento
├── Histórico de pagamentos
└── Informações de contato
```

## 🔔 Cobranças e notificações

O sistema contempla recursos para facilitar o acompanhamento e a comunicação sobre pagamentos.

Entre as funcionalidades previstas e desenvolvidas estão:

- 📧 Lembretes de pagamento por e-mail;
- 💬 Possibilidade de contato pelo WhatsApp;
- 📝 Mensagens de cobrança;
- 🧾 Acompanhamento de comprovantes de pagamento.

A implementação e o aprimoramento desses recursos fazem parte da evolução do projeto.

## 💳 Integração com PIX

A integração com o PIX está em fase de estudo e levantamento de requisitos.

A proposta é avaliar a possibilidade de gerar cobranças e identificar pagamentos automaticamente, atualizando a situação financeira dos alunos.

Essa integração ainda não deve ser considerada uma funcionalidade concluída.

---

# 🖥️ Interface

A interface do PagueProf busca oferecer uma experiência:

- Simples;
- Limpa;
- Intuitiva;
- Organizada;
- Focada nas informações mais importantes.

O dashboard apresenta um resumo dos alunos e pagamentos, enquanto as demais áreas permitem acessar informações específicas.

---

# 🔄 Fluxo básico do sistema

O fluxo geral da plataforma contempla as seguintes etapas:

```text
                    👨‍🏫 Professor
                         │
                         ▼
                  🔐 Login / Cadastro
                         │
                         ▼
                    📊 Dashboard
                         │
          ┌──────────────┼──────────────┐
          ▼              ▼              ▼
       👥 Alunos      💰 Pagamentos   ⚙️ Configurações
          │              │
          ▼              ├── 🟢 Pagos
   Informações do aluno   ├── 🟡 Pendentes
          │              └── 🔴 Atrasados
          ├── 📚 Pacotes
          ├── 📅 Aulas
          ├── 💳 Pagamentos
          └── 📱 Contato
```

---

# 🛠️ Tecnologias utilizadas

As tecnologias definidas para o desenvolvimento do PagueProf são:

| Área | Tecnologia |
|---|---|
| 🎨 Front-end | HTML, CSS e JavaScript |
| ⚙️ Back-end | PHP |
| 🗄️ Banco de dados | MySQL |
| 🔀 Versionamento | Git e GitHub |
| 📋 Organização | Trello |
| 🎨 Design / Prototipação | Protótipo visual conceitual |

---

# 📋 Status do projeto

<aside>
🟡 **Em desenvolvimento — Sprint 3**
</aside>

O PagueProf evoluiu da etapa de planejamento e prototipação para a implementação de suas funcionalidades principais.

### ✅ Sprint 1 — Planejamento e prototipação

**Situação: Concluída**

- Definição das tecnologias;
- Organização dos papéis da equipe;
- Definição inicial da proposta do sistema;
- Criação do protótipo visual.

### ✅ Sprint 2 — Estrutura e implementação inicial

**Situação: Concluída**

- Definição e utilização da stack tecnológica;
- Desenvolvimento das telas principais;
- Implementação do acesso por login e senha;
- Criação das telas de cadastro e listagem de alunos;
- Desenvolvimento do dashboard;
- Visualização das seções de alunos, pagamentos, aulas e configurações;
- Criação da aba de Planos de Cobrança;
- Criação e configuração da estrutura inicial do banco de dados MySQL.

**Resultado:** o usuário já consegue acessar o sistema e navegar pelas principais áreas desenvolvidas. A integração completa entre as partes do sistema e os ajustes de funcionamento continuam sendo aprimorados.

### 🔄 Sprint 3 — Cobranças e aprimoramentos

**Situação: Em andamento**

**Concluído:**
- Planos de cobrança;
- Cobranças;
- Comprovantes;
- Lembretes por e-mail.

**Em andamento:**
- Testes de uso;
- Validação dos fluxos do sistema;
- Estudo dos requisitos para uma possível integração com PIX.

### 🎯 Sprint 4 — Próximos passos

**Meta:** demonstrar o ciclo de cobrança funcionando e testado por professores reais.

Entre os objetivos estão:

- Aprimorar o fluxo de cobranças;
- Validar o funcionamento das funcionalidades;
- Realizar testes com professores;
- Identificar possíveis melhorias a partir dos testes;
- Avaliar a evolução da integração com meios de pagamento.

---

# 👥 Equipe

Projeto desenvolvido por alunos do curso de **Análise e Desenvolvimento de Sistemas (ADS)**.

| Integrante | Responsabilidade |
|---|---|
| Alan | Back-end |
| Fernando | Transferido para o turno da noite |
| Filipe | Back-end e Banco de Dados |
| Henrique | Front-end |
| Nickolas | Back-end e Front-end |
| Talita | Scrum Master e Analista de QA |
| Vitor | Back-end e Front-end |

**Scrum Master da Sprint 2:** Talita.

---

# 🔀 Organização e versionamento

O projeto utiliza Git e GitHub para controle de versão e organização do desenvolvimento em equipe.

As branches principais de trabalho são:

- `main`: versão principal e estável do projeto;
- `feature/frontend`: desenvolvimento do Front-end;
- `feature/backend`: desenvolvimento do Back-end.

As alterações são organizadas por meio de commits e integração das branches, buscando manter o código organizado e facilitar o trabalho colaborativo.

---

# 📌 Repositório e organização

- **GitHub:** https://github.com/Vitorkp/PagueProf
- **Trello:** https://trello.com/b/XPZeyPlK/projeto-e-desenvolvimento-1

O Trello é utilizado para organizar as atividades, acompanhar o andamento das tarefas e planejar as entregas das Sprints.

O GitHub centraliza o código-fonte e o histórico de desenvolvimento.

---

<p align="center">
  <strong>PagueProf</strong><br>
  <i>Organizando a rotina de quem ensina.</i>
</p>
