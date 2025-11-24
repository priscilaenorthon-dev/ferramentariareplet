# Backend PHP (XAMPP)

API compativel com o frontend React existente, usando PHP + MySQL.

## Como rodar no XAMPP
1. Copie a pasta `php-api/` para dentro do `htdocs` do XAMPP.
2. Crie o banco MySQL `replit` e rode o schema (via phpMyAdmin ou terminal):
   ```sql
   CREATE DATABASE replit;
   USE replit;
   SOURCE /caminho/para/php-api/schema-mysql.sql;
   ```
   No phpMyAdmin: abra `http://localhost/phpmyadmin`, crie o banco `replit` (collation `utf8mb4_general_ci`) e importe o arquivo `schema-mysql.sql`.
3. Ajuste credenciais em `php-api/config.php` se precisar (host/porta/usuario/senha/banco). O padrao ja aponta para `127.0.0.1:3306`, usuario `root` sem senha, banco `replit` e session `replit_session`.
4. Suba Apache/MySQL no XAMPP. A API responde em `http://localhost/replit/php-api/index.php` (ou `/php-api` se estiver na raiz). As rotas usam o prefixo `/api/...`, mesmo servindo de uma subpasta: exemplo `http://localhost/replit/php-api/api/auth/login`.
5. Para testar pelo navegador sem usar curl/Postman, abra `http://localhost/replit/php-api/test-client.html` e use os botoes de login/check.

## Rotas principais
- Autenticacao: `POST /api/auth/login`, `POST /api/auth/logout`, `GET /api/auth/user`, `POST /api/auth/register` (admin), `POST /api/auth/validate-qrcode`.
- Classes de ferramentas: CRUD em `/api/classes` (operator/admin).
- Modelos: CRUD em `/api/models` (operator/admin).
- Ferramentas: CRUD em `/api/tools` (operator/admin), com calculo de proxima calibracao.
- Emprestimos: `GET/POST /api/loans`, `PATCH /api/loans/:id/return` (operator/admin).
- Usuarios: `GET /api/users`, `PATCH/DELETE /api/users/:id` (admin).
- Auditoria: `GET /api/audit/logs` (admin).
- Dashboard: `GET /api/dashboard/stats` (respeita filtro de usuario comum).

## Notas
- Sessions PHP cuidam do login; o React pode continuar chamando `/api/...` no mesmo dominio para compartilhar cookies.
- Campos JSON sao armazenados como `JSON` no MySQL; se sua versao nao suportar, altere para `TEXT`.
- A geracao de UUID usa `UUID()` do MySQL; se nao estiver habilitado, troque a coluna `id` para `CHAR(36)` e preencha no aplicativo.
