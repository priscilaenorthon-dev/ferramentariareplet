# Ferramentaria Vercel Neon

Aplicacao web full-stack para gestao de ferramentas e emprestimos, construida com React + Vite no frontend, Express no backend e Drizzle ORM sobre PostgreSQL.

## Principais recursos
- Catalogo de ferramentas com classes, modelos, quantidade disponivel e status.
- Perfis de usuario (admin, operator, user) com autenticacao local baseada em sessao.
- Registro de auditoria e seed com dados de exemplo para comecar rapido.
- Build unico: API e client sao servidos pela mesma porta.

## Pre-requisitos
- Node.js 20 ou superior (recomendado 20.x LTS)
- npm
- Banco PostgreSQL acessivel por URL de conexao (Neon, Supabase ou instancia propria)

## Configuracao do ambiente
1. Instale as dependências:
   ```bash
   npm install
   ```
2. Crie um arquivo `.env` na raiz com as variaveis abaixo:
   ```bash
   DATABASE_URL=postgres://usuario:senha@host:5432/database
   SESSION_SECRET=chave-secreta-aleatoria
   PORT=5000
   ```
   - `SESSION_SECRET`: string aleatoria usada para assinar sessoes.
   - `PORT`: porta unica usada para API e client (padrao 5000).
3. Aplique o schema do banco:
   ```bash
   npm run db:push
   ```
4. (Opcional) Popular o banco com dados de exemplo:
   ```bash
   npm run seed
   ```
   O seed nao sobrescreve registros existentes; ele so insere o que estiver faltando.

## Uso
- Ambiente de desenvolvimento (Express + Vite):
  ```bash
  npm run dev
  ```
- Build de producao:
  ```bash
  npm run build
  ```
- Iniciar o build gerado:
  ```bash
  npm start
  ```

## Scripts uteis
- `npm run check`: verificacao de tipos com TypeScript.
- `npm run db:push`: aplica o schema do Drizzle no PostgreSQL configurado.
- `npm run seed`: cria usuarios, classes, modelos e ferramentas de exemplo sem duplicar dados.

## Estrutura do projeto
- `client/`: frontend React + Vite.
- `server/`: API Express, configuracao do Vite, seeds e middlewares.
- `shared/`: tipos e schema do Drizzle compartilhados entre client e servidor.
- `docs/`: anotacoes adicionais (ex.: planos de deploy).

## Deploy no Vercel com Neon
1. Crie um projeto no Neon e copie a `DATABASE_URL` do PostgreSQL.
   Se você estiver usando a extensão do Neon ou o MCP do Neon, use a connection string gerada por eles.
2. No Vercel, importe este repositório.
3. Configure as variáveis de ambiente:
   ```bash
   DATABASE_URL=postgres://...
   SESSION_SECRET=uma-string-longa-e-aleatoria
   NODE_ENV=production
   ```
4. Defina `VITE_BASE_PATH` apenas se o app for publicado em subpasta. Em deploy raiz, deixe sem valor.
5. Antes do primeiro deploy, aplique o schema no banco Neon:
   ```bash
   npm install
   npm run db:push
   npm run seed
   ```
6. Execute o deploy. O frontend será publicado de `dist/public` e a API rodará como função serverless em `api/index.ts`, com rewrite de `/api/*` para esse entrypoint.
7. `VITE_API_BASE` só é necessário se frontend e backend ficarem em domínios diferentes. No Vercel com mesma origem, não precisa definir.

## Fluxo rapido para teste com MCP Neon
1. Crie/seleciona o projeto no Neon.
2. Execute o schema com `npm run db:push` ou migre via MCP Neon.
3. Rode `npm run seed` para dados de exemplo.
4. Inicie com `npm run dev` e valide login, cadastro e emprestimos.

## Licença
Distribuido sob a licenca MIT.
