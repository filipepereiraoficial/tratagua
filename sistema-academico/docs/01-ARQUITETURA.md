# 1. Arquitetura do Sistema

## 1.1 Visão geral

O **Painel Pedagógico** é uma aplicação web PHP independente, hospedada dentro do
mesmo servidor do site institucional (`/tratagua/sistema-academico/`). Ela não
depende do WordPress: possui banco de dados, autenticação, rotas e interface
próprios, evitando acoplamento com plugins/temas e permitindo evolução isolada.

Escolha técnica e motivação:

| Camada | Tecnologia | Motivo |
|---|---|---|
| Runtime | PHP 8.1+ (testado em 8.4) | Já disponível na hospedagem atual |
| Banco | MySQL 5.7+/MariaDB **ou** SQLite 3 | MySQL em produção; SQLite permite rodar sem configurar nada |
| Acesso a dados | PDO com *prepared statements* | Portabilidade + proteção contra SQL Injection |
| Front-end | HTML5 + CSS3 próprio + JS vanilla | Sem build step, sem Node, fácil de publicar por FTP |
| Gráficos | Chart.js 4 (CDN, com degradação suave) | Biblioteca madura, responsiva e acessível |
| Exportação | CSV nativo + impressão/PDF via `window.print()` | Sem dependências binárias no servidor |

Nenhum passo de compilação é necessário: basta copiar a pasta e rodar o
instalador.

## 1.2 Estilo arquitetural

Arquitetura em camadas (MVC + camada de serviços de domínio):

```
  Navegador
     │  HTTP
     ▼
┌──────────────────────────────────────────────────────────────┐
│ index.php  (Front Controller)                                │
│  · bootstrap, autoload, sessão, tratamento de erros          │
└───────────────┬──────────────────────────────────────────────┘
                ▼
┌──────────────────────────────────────────────────────────────┐
│ Core\Router  → casa método + URI com um Controller           │
│  middlewares: auth, role, csrf                               │
│ Core\Scope   → recorta o que o perfil pode enxergar          │
└───────────────┬──────────────────────────────────────────────┘
                ▼
┌──────────────────────────────────────────────────────────────┐
│ Controllers  (HTTP)                                          │
│  · validam entrada (Core\Validator)                          │
│  · orquestram Models/Services                                │
│  · devolvem View (HTML) ou JSON (endpoints de gráficos)      │
└───────┬──────────────────────────────────┬───────────────────┘
        ▼                                  ▼
┌───────────────────────────┐   ┌──────────────────────────────┐
│ Services (domínio)        │   │ Models (persistência)        │
│ · AnalyticsService        │   │ Student, Teacher, ClassGroup,│
│ · RankingService          │   │ Subject, Topic, Lesson,      │
│ · AlertService            │   │ Attendance, Assessment,      │
│ · ReportService           │   │ Question, Answer, Grade,     │
│                           │   │ Intervention, ActivityLog,   │
│                           │   │ User, Setting                │
└───────────────────────────┘   └──────────────────────────────┘
                ▼
┌──────────────────────────────────────────────────────────────┐
│ Views (templates PHP) + layout + partials + assets           │
└──────────────────────────────────────────────────────────────┘
```

**Regra de dependência:** Views ← Controllers ← Services ← Models ← Core.
Nenhuma camada inferior conhece a superior. Toda regra de cálculo pedagógico
vive em `Services/`, nunca nas Views — isso mantém os números idênticos em
telas, relatórios e exportações.

## 1.3 Estrutura de diretórios

```
sistema-academico/
├── index.php                 Front controller (único ponto de entrada)
├── instalar.php              Instalador web (cria schema + dados iniciais)
├── .htaccess                 Rewrite para o front controller + hardening
├── config/
│   ├── config.php            Configuração padrão (lê config.local.php se existir)
│   └── config.local.example.php
├── src/
│   ├── Core/                 Database, Router, Request, View, Auth, Csrf,
│   │                         Scope, Migrator, Validator, Session, Flash,
│   │                         Input, Controller
│   ├── Models/               Uma classe por entidade (repositório + regras simples)
│   ├── Services/             Analytics, Ranking, Alerts, Reports, Settings
│   └── Controllers/          Um por módulo do menu + ApiController (JSON)
├── views/                    Templates (layouts, partials, telas)
├── assets/                   CSS, JS, ícones
├── database/
│   ├── schema.mysql.sql      DDL MySQL/MariaDB
│   ├── schema.sqlite.sql     DDL SQLite
│   ├── migrations/           NNN_nome.{sqlite,mysql}.sql — atualiza instalações
│   ├── migrate.php           CLI: cria/atualiza o schema, aplica migrações
│   └── seed.php              CLI: admin inicial + curso/turma/disciplina
├── storage/                  Banco SQLite, logs (bloqueado por .htaccess)
└── docs/                     Esta documentação
```

## 1.4 Segurança

| Risco | Mitigação implementada |
|---|---|
| SQL Injection | 100% dos acessos via PDO com *prepared statements*; nomes de coluna em ordenação passam por *whitelist* |
| XSS | Escape obrigatório na saída (`e()` = `htmlspecialchars` com `ENT_QUOTES`) |
| CSRF | Token por sessão exigido em **todo** POST (`Core\Csrf`), validado no router |
| Sessão | Cookie `HttpOnly`, `SameSite=Lax`, `Secure` quando em HTTPS; `session_regenerate_id` no login; expiração por inatividade |
| Senhas | `password_hash()` (bcrypt/Argon conforme PHP), nunca em log; troca obrigatória sinalizada no 1º acesso |
| Força bruta | Limite de tentativas por e-mail+IP com bloqueio temporário progressivo |
| Autorização | Middleware `role:` por rota (admin, professor, aluno) + `Core\Scope` recortando **dados** por responsabilidade, aplicado tanto nas listagens quanto no acesso direto por URL |
| Rastreabilidade | `activity_log` registra toda operação que altera estado, com autor, entidade e detalhe; consultável em Auditoria |
| Exposição de arquivos | `.htaccess` nega acesso a `src/`, `views/`, `config/`, `storage/`, `database/` |
| Erros | `display_errors` desligado em produção; exceções logadas em `storage/logs/` e página 500 genérica |
| Integridade | Chaves estrangeiras com `ON DELETE` explícito + validações de negócio antes da escrita |

## 1.5 Escalabilidade e evolução

- **Três perfis em operação**: administrador, professor (recortado nas próprias
  ofertas) e aluno. Ver [docs/05](05-PERFIS-E-PAINEIS.md).
- **Multi-professor**: `class_subjects.teacher_user_id` define o responsável por
  cada par turma × disciplina; `subjects.teacher_user_id` é o padrão quando a
  oferta não tem professor próprio.
- **Migrações versionadas** em `database/migrations/`: instalações existentes
  sobem de versão sem perder dados, e a aplicação aplica pendências no boot.
- **Índices** nas colunas de junção e de filtro por período garantem consultas
  rápidas com dezenas de milhares de respostas.
- **Cálculos configuráveis**: faixas de classificação e pesos do Índice de
  Desenvolvimento ficam em `settings`, alteráveis pela interface sem deploy.
- **API JSON** (`/api/*`) já separada dos controladores de tela — é o ponto de
  extensão natural para um app móvel ou integrações futuras.
