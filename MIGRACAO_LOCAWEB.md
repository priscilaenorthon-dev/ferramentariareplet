# Guia de Migração: Replit → Locaweb
## Sistema JOMAGA - Tool Management System

---

## 📋 PRÉ-REQUISITOS

### No Replit:
- Acesso ao terminal
- Dados de produção no banco PostgreSQL
- Sistema funcionando

### Na Locaweb:
- VPS Cloud Server contratado (Ubuntu 22.04 LTS)
- Acesso SSH (root ou sudo)
- IP público do servidor

---

## FASE 1: EXPORTAR DO REPLIT

### 1.1 - Exportar Código-Fonte

```bash
# No terminal do Replit, comprimir todo o projeto
cd /home/runner/workspace
tar -czf sistema-jomaga-export.tar.gz \
  --exclude=node_modules \
  --exclude=.git \
  --exclude=.replit \
  --exclude=replit.nix \
  .

# Verificar tamanho do arquivo
ls -lh sistema-jomaga-export.tar.gz
```

### 1.2 - Exportar Banco de Dados PostgreSQL

```bash
# Exportar banco completo (estrutura + dados)
pg_dump $DATABASE_URL > sistema-jomaga-backup.sql

# Verificar se exportou corretamente
wc -l sistema-jomaga-backup.sql
head -20 sistema-jomaga-backup.sql
```

**Arquivo gerado:** `sistema-jomaga-backup.sql`

### 1.3 - Criar Arquivo de Variáveis de Ambiente

```bash
# Criar arquivo .env.production com as variáveis necessárias
cat > .env.production << 'EOF'
# Banco de dados (será atualizado na Locaweb)
DATABASE_URL=postgresql://usuario:senha@localhost:5432/jomaga_db

# Session Secret (gerar novo!)
SESSION_SECRET=GERAR_NOVA_CHAVE_ALEATORIA_32_CARACTERES

# Ambiente
NODE_ENV=production
PORT=5000

# Domínio (atualizar com domínio real)
# DOMAIN=ferramentas.suaempresa.com.br
EOF
```

### 1.4 - Baixar Arquivos para seu Computador

**Opção A - Via Interface do Replit:**
1. Clique com botão direito em `sistema-jomaga-export.tar.gz` → Download
2. Clique com botão direito em `sistema-jomaga-backup.sql` → Download
3. Clique com botão direito em `.env.production` → Download

**Opção B - Via Terminal (se tiver acesso):**
```bash
# Usar SCP/SFTP para baixar os arquivos
# (necessita credenciais SSH do Replit)
```

---

## FASE 2: PREPARAR SERVIDOR LOCAWEB

### 2.1 - Conectar ao Servidor VPS

```bash
# No seu computador (Linux/Mac) ou PuTTY (Windows)
ssh root@SEU_IP_LOCAWEB

# Primeira vez pedirá para aceitar fingerprint: digite 'yes'
# Digite a senha fornecida pela Locaweb
```

### 2.2 - Atualizar Sistema Operacional

```bash
# Atualizar pacotes
sudo apt update && sudo apt upgrade -y

# Configurar timezone para Brasil
sudo timedatectl set-timezone America/Sao_Paulo
```

### 2.3 - Instalar Dependências Necessárias

```bash
# Node.js 20 LTS
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# Verificar instalação
node -v  # Deve mostrar v20.x.x
npm -v   # Deve mostrar v10.x.x

# PostgreSQL 15
sudo apt install -y postgresql postgresql-contrib

# Verificar instalação
sudo systemctl status postgresql

# Nginx (reverse proxy)
sudo apt install -y nginx

# PM2 (gerenciador de processos)
sudo npm install -g pm2

# Ferramentas úteis
sudo apt install -y git curl wget unzip htop
```

---

## FASE 3: CONFIGURAR BANCO DE DADOS

### 3.1 - Configurar PostgreSQL

```bash
# Entrar no PostgreSQL como superusuário
sudo -u postgres psql

# No prompt do PostgreSQL, executar:
```

```sql
-- Criar usuário para o sistema
CREATE USER jomaga_user WITH PASSWORD 'SENHA_FORTE_SEGURA_123';

-- Criar banco de dados
CREATE DATABASE jomaga_db OWNER jomaga_user;

-- Conceder privilégios
GRANT ALL PRIVILEGES ON DATABASE jomaga_db TO jomaga_user;

-- Sair
\q
```

### 3.2 - Configurar Acesso Local ao PostgreSQL

```bash
# Editar arquivo de configuração
sudo nano /etc/postgresql/15/main/pg_hba.conf

# Adicionar esta linha (se não existir):
# local   all             jomaga_user                             md5

# Reiniciar PostgreSQL
sudo systemctl restart postgresql
```

---

## FASE 4: FAZER UPLOAD DOS ARQUIVOS

### 4.1 - Enviar Arquivos para o Servidor

**No seu computador:**

```bash
# Enviar código-fonte
scp sistema-jomaga-export.tar.gz root@SEU_IP_LOCAWEB:/root/

# Enviar backup do banco
scp sistema-jomaga-backup.sql root@SEU_IP_LOCAWEB:/root/

# Enviar .env.production
scp .env.production root@SEU_IP_LOCAWEB:/root/
```

**No servidor Locaweb:**

```bash
# Verificar se arquivos chegaram
ls -lh /root/sistema-jomaga-*
```

---

## FASE 5: INSTALAR APLICAÇÃO

### 5.1 - Extrair Código-Fonte

```bash
# Criar diretório para aplicação
sudo mkdir -p /opt/sistema-jomaga
cd /opt/sistema-jomaga

# Extrair código
sudo tar -xzf /root/sistema-jomaga-export.tar.gz -C /opt/sistema-jomaga

# Copiar arquivo .env
sudo cp /root/.env.production /opt/sistema-jomaga/.env

# Ajustar permissões
sudo chown -R www-data:www-data /opt/sistema-jomaga
```

### 5.2 - Editar Variáveis de Ambiente

```bash
sudo nano /opt/sistema-jomaga/.env

# Atualizar:
# DATABASE_URL=postgresql://jomaga_user:SENHA_FORTE_SEGURA_123@localhost:5432/jomaga_db
# SESSION_SECRET=(gerar nova chave aleatória de 32 caracteres)
# NODE_ENV=production
# PORT=5000

# Salvar: Ctrl+O, Enter
# Sair: Ctrl+X
```

**Gerar SESSION_SECRET seguro:**
```bash
# No servidor, gerar chave aleatória
openssl rand -base64 32
# Copiar resultado e colar no .env
```

### 5.3 - Instalar Dependências Node.js

```bash
cd /opt/sistema-jomaga
sudo -u www-data npm install --production

# Tempo estimado: 2-5 minutos
```

### 5.4 - Build da Aplicação

```bash
cd /opt/sistema-jomaga
sudo -u www-data npm run build

# Verificar se build foi criado
ls -la dist/
```

---

## FASE 6: RESTAURAR BANCO DE DADOS

### 6.1 - Importar Dados

```bash
# Importar backup do Replit
sudo -u postgres psql -d jomaga_db -f /root/sistema-jomaga-backup.sql

# Verificar se importou corretamente
sudo -u postgres psql -d jomaga_db -c "SELECT COUNT(*) FROM users;"
sudo -u postgres psql -d jomaga_db -c "SELECT COUNT(*) FROM tools;"
sudo -u postgres psql -d jomaga_db -c "SELECT COUNT(*) FROM loans;"
```

### 6.2 - Ajustar Permissões

```bash
sudo -u postgres psql -d jomaga_db << 'EOF'
-- Garantir que jomaga_user tenha acesso às tabelas
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO jomaga_user;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO jomaga_user;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO jomaga_user;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO jomaga_user;
EOF
```

---

## FASE 7: CONFIGURAR PM2 (Processo em Background)

### 7.1 - Criar Script de Inicialização

```bash
cd /opt/sistema-jomaga

# Criar arquivo ecosystem.config.js
sudo nano ecosystem.config.js
```

```javascript
module.exports = {
  apps: [{
    name: 'sistema-jomaga',
    script: 'npm',
    args: 'start',
    cwd: '/opt/sistema-jomaga',
    instances: 1,
    autorestart: true,
    watch: false,
    max_memory_restart: '1G',
    env: {
      NODE_ENV: 'production',
      PORT: 5000
    },
    error_file: '/var/log/sistema-jomaga/error.log',
    out_file: '/var/log/sistema-jomaga/out.log',
    log_date_format: 'YYYY-MM-DD HH:mm:ss Z'
  }]
};
```

### 7.2 - Criar Diretório de Logs

```bash
sudo mkdir -p /var/log/sistema-jomaga
sudo chown -R www-data:www-data /var/log/sistema-jomaga
```

### 7.3 - Iniciar Aplicação com PM2

```bash
cd /opt/sistema-jomaga

# Iniciar como www-data
sudo -u www-data pm2 start ecosystem.config.js

# Verificar status
sudo -u www-data pm2 status

# Ver logs em tempo real
sudo -u www-data pm2 logs sistema-jomaga

# Salvar configuração PM2
sudo -u www-data pm2 save

# Configurar PM2 para iniciar no boot
sudo env PATH=$PATH:/usr/bin pm2 startup systemd -u www-data --hp /var/www
```

### 7.4 - Testar Aplicação

```bash
# Verificar se está rodando na porta 5000
curl http://localhost:5000

# Deve retornar o HTML da aplicação
# Se retornar erro, verificar logs:
sudo -u www-data pm2 logs sistema-jomaga --lines 50
```

---

## FASE 8: CONFIGURAR NGINX (Reverse Proxy)

### 8.1 - Criar Configuração do Site

```bash
sudo nano /etc/nginx/sites-available/sistema-jomaga
```

```nginx
server {
    listen 80;
    listen [::]:80;
    
    server_name SEU_DOMINIO.COM.BR;  # Ou IP público
    
    # Logs
    access_log /var/log/nginx/jomaga-access.log;
    error_log /var/log/nginx/jomaga-error.log;
    
    # Proxy para Node.js
    location / {
        proxy_pass http://localhost:5000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_cache_bypass $http_upgrade;
        
        # Timeouts
        proxy_connect_timeout 60s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;
    }
    
    # Limite de upload (para PDFs, etc)
    client_max_body_size 10M;
}
```

### 8.2 - Ativar Site

```bash
# Criar link simbólico
sudo ln -s /etc/nginx/sites-available/sistema-jomaga /etc/nginx/sites-enabled/

# Remover site padrão (opcional)
sudo rm /etc/nginx/sites-enabled/default

# Testar configuração
sudo nginx -t

# Se OK, reiniciar Nginx
sudo systemctl restart nginx
sudo systemctl enable nginx
```

---

## FASE 9: CONFIGURAR FIREWALL

```bash
# Permitir HTTP, HTTPS e SSH
sudo ufw allow 22/tcp   # SSH
sudo ufw allow 80/tcp   # HTTP
sudo ufw allow 443/tcp  # HTTPS (para quando instalar SSL)

# Ativar firewall
sudo ufw --force enable

# Verificar status
sudo ufw status verbose
```

---

## FASE 10: INSTALAR CERTIFICADO SSL (HTTPS)

### 10.1 - Instalar Certbot (Let's Encrypt - Gratuito)

```bash
sudo apt install -y certbot python3-certbot-nginx
```

### 10.2 - Obter Certificado SSL

```bash
# Substituir por seu domínio real
sudo certbot --nginx -d ferramentas.suaempresa.com.br

# Seguir instruções:
# 1. Informar email
# 2. Aceitar termos
# 3. Certbot configurará automaticamente o Nginx
```

### 10.3 - Renovação Automática

```bash
# Testar renovação
sudo certbot renew --dry-run

# Cron job já é criado automaticamente pelo certbot
```

---

## FASE 11: CONFIGURAR BACKUPS AUTOMÁTICOS

### 11.1 - Criar Script de Backup

```bash
sudo nano /usr/local/bin/backup-jomaga.sh
```

```bash
#!/bin/bash

# Configurações
BACKUP_DIR="/var/backups/jomaga"
DATE=$(date +%Y%m%d_%H%M%S)
RETENTION_DAYS=30

# Criar diretório se não existir
mkdir -p $BACKUP_DIR

# Backup do banco de dados
sudo -u postgres pg_dump jomaga_db | gzip > $BACKUP_DIR/db_backup_$DATE.sql.gz

# Backup dos arquivos da aplicação (sem node_modules)
tar -czf $BACKUP_DIR/app_backup_$DATE.tar.gz \
  --exclude='node_modules' \
  --exclude='dist' \
  -C /opt/sistema-jomaga .

# Remover backups antigos
find $BACKUP_DIR -name "db_backup_*.sql.gz" -mtime +$RETENTION_DAYS -delete
find $BACKUP_DIR -name "app_backup_*.tar.gz" -mtime +$RETENTION_DAYS -delete

# Log
echo "[$(date)] Backup realizado com sucesso" >> /var/log/jomaga-backup.log
```

```bash
# Dar permissão de execução
sudo chmod +x /usr/local/bin/backup-jomaga.sh

# Testar
sudo /usr/local/bin/backup-jomaga.sh
ls -lh /var/backups/jomaga/
```

### 11.2 - Agendar Backup Diário (Cron)

```bash
sudo crontab -e

# Adicionar linha (backup diário às 2h da manhã):
0 2 * * * /usr/local/bin/backup-jomaga.sh

# Salvar e sair
```

---

## FASE 12: VERIFICAÇÃO FINAL

### 12.1 - Checklist de Verificação

```bash
# 1. Aplicação rodando?
sudo -u www-data pm2 status
curl -I http://localhost:5000

# 2. Nginx funcionando?
sudo systemctl status nginx
curl -I http://SEU_IP_OU_DOMINIO

# 3. Banco de dados populado?
sudo -u postgres psql -d jomaga_db -c "SELECT COUNT(*) FROM users;"
sudo -u postgres psql -d jomaga_db -c "SELECT COUNT(*) FROM tools;"

# 4. SSL ativo? (se configurou)
curl -I https://SEU_DOMINIO

# 5. Backup configurado?
ls -lh /var/backups/jomaga/

# 6. Firewall ativo?
sudo ufw status
```

### 12.2 - Testar Login no Navegador

```
1. Abrir navegador
2. Acessar: http://SEU_IP_OU_DOMINIO
3. Login com testadmin / admin123
4. Verificar dashboard, ferramentas, empréstimos
```

---

## MONITORAMENTO E MANUTENÇÃO

### Ver Logs em Tempo Real

```bash
# Logs da aplicação
sudo -u www-data pm2 logs sistema-jomaga

# Logs do Nginx
sudo tail -f /var/log/nginx/jomaga-access.log
sudo tail -f /var/log/nginx/jomaga-error.log

# Logs do PostgreSQL
sudo tail -f /var/log/postgresql/postgresql-15-main.log
```

### Reiniciar Serviços

```bash
# Reiniciar aplicação
sudo -u www-data pm2 restart sistema-jomaga

# Reiniciar Nginx
sudo systemctl restart nginx

# Reiniciar PostgreSQL
sudo systemctl restart postgresql
```

### Atualizar Aplicação (Futuras Atualizações)

```bash
cd /opt/sistema-jomaga

# Fazer backup antes
sudo /usr/local/bin/backup-jomaga.sh

# Parar aplicação
sudo -u www-data pm2 stop sistema-jomaga

# Atualizar código (via git ou upload)
# ... fazer alterações ...

# Reinstalar dependências
sudo -u www-data npm install --production

# Rebuild
sudo -u www-data npm run build

# Reiniciar
sudo -u www-data pm2 restart sistema-jomaga
```

---

## TROUBLESHOOTING

### Aplicação não inicia

```bash
# Ver logs de erro
sudo -u www-data pm2 logs sistema-jomaga --err --lines 100

# Testar manualmente
cd /opt/sistema-jomaga
sudo -u www-data npm start
```

### Erro de conexão com banco

```bash
# Verificar se PostgreSQL está rodando
sudo systemctl status postgresql

# Testar conexão manual
psql -U jomaga_user -d jomaga_db -h localhost

# Verificar .env
cat /opt/sistema-jomaga/.env | grep DATABASE_URL
```

### Nginx retorna 502 Bad Gateway

```bash
# Verificar se aplicação está rodando
curl http://localhost:5000

# Se não responder, verificar PM2
sudo -u www-data pm2 status
```

---

## CUSTOS ESTIMADOS (Locaweb)

| Item | Custo Mensal |
|------|--------------|
| VPS Cloud Server 4 | R$ 80-120 |
| Domínio .com.br | R$ 3-5 |
| SSL Let's Encrypt | Grátis |
| **TOTAL** | **R$ 83-125/mês** |

---

## SUPORTE

Se encontrar dificuldades durante a migração:
- Email: [seu-email]
- WhatsApp: [seu-telefone]
- Instalação assistida disponível: R$ 1.500

---

**Tempo estimado de migração:** 2-4 horas (primeira vez)

**Dificuldade:** Intermediária (requer conhecimento básico de Linux)
