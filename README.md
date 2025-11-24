# Ferramentaria Management

Aplicação web full-stack para gestão de ferramentas e empréstimos, construída com React + Vite no frontend, Express no backend e Drizzle ORM sobre PostgreSQL.

## Principais recursos
- Catálogo de ferramentas com classes, modelos, quantidade disponível e status.
- Perfis de usuário (admin, operator, user) com autenticação local ou via Replit OIDC.
- Registro de auditoria e seed com dados de exemplo para começar rápido.
- Build único: API e client são servidos pela mesma porta.

## Pré-requisitos
- Node.js 20 ou superior (recomendado 20.x LTS)
- npm
- Banco PostgreSQL acessível por URL de conexão (Neon, Supabase ou instância própria)

## Configuração do ambiente
1. Instale as dependências:
   ```bash
   npm install
   ```
2. Crie um arquivo `.env` na raiz com as variáveis abaixo:
   ```bash
   DATABASE_URL=postgres://usuario:senha@host:5432/database
   SESSION_SECRET=chave-secreta-aleatoria
   REPL_ID=opcional-em-ambientes-replit
   ISSUER_URL=https://replit.com/oidc
   PORT=5000
   ```
   - `SESSION_SECRET`: string aleatória usada para assinar sessões.
   - `REPL_ID` e `ISSUER_URL`: necessários somente se a autenticação via Replit estiver habilitada.
   - `PORT`: porta única usada para API e client (padrão 5000).
3. Aplique o schema do banco:
   ```bash
   npm run db:push
   ```
4. (Opcional) Popular o banco com dados de exemplo:
   ```bash
   npm run seed
   ```
   O seed não sobrescreve registros existentes; ele só insere o que estiver faltando.

## Uso
- Ambiente de desenvolvimento (Express + Vite):
  ```bash
  npm run dev
  ```
- Build de produção:
  ```bash
  npm run build
  ```
- Iniciar o build gerado:
  ```bash
  npm start
  ```

## Scripts úteis
- `npm run check`: verificação de tipos com TypeScript.
- `npm run db:push`: aplica o schema do Drizzle no PostgreSQL configurado.
- `npm run seed`: cria usuários, classes, modelos e ferramentas de exemplo sem duplicar dados.

## Estrutura do projeto
- `client/`: frontend React + Vite.
- `server/`: API Express, configuração do Vite, seeds e middlewares.
- `shared/`: tipos e schema do Drizzle compartilhados entre client e servidor.
- `docs/`: anotações adicionais (ex.: uso no Replit).

## Licença
Distribuído sob a licença MIT.
