#!/bin/bash

# ========================================
# Script de Exportação: Replit → Locaweb
# Sistema JOMAGA
# ========================================

echo "======================================"
echo "EXPORTAÇÃO SISTEMA JOMAGA"
echo "Replit → Locaweb"
echo "======================================"
echo ""

# Cores para output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Diretório de exportação
EXPORT_DIR="export-locaweb"
DATE=$(date +%Y%m%d_%H%M%S)

echo -e "${YELLOW}[1/5] Criando diretório de exportação...${NC}"
mkdir -p $EXPORT_DIR
cd $EXPORT_DIR

echo -e "${GREEN}✓ Diretório criado: $EXPORT_DIR${NC}"
echo ""

# ========================================
# PASSO 1: Exportar Código-Fonte
# ========================================
echo -e "${YELLOW}[2/5] Exportando código-fonte...${NC}"

cd ..
tar -czf $EXPORT_DIR/sistema-jomaga-codigo.tar.gz \
  --exclude=node_modules \
  --exclude=.git \
  --exclude=.replit \
  --exclude=replit.nix \
  --exclude=$EXPORT_DIR \
  --exclude=export-para-locaweb.sh \
  .

CODIGO_SIZE=$(du -h $EXPORT_DIR/sistema-jomaga-codigo.tar.gz | cut -f1)
echo -e "${GREEN}✓ Código exportado: sistema-jomaga-codigo.tar.gz ($CODIGO_SIZE)${NC}"
echo ""

# ========================================
# PASSO 2: Exportar Banco de Dados
# ========================================
echo -e "${YELLOW}[3/5] Exportando banco de dados PostgreSQL...${NC}"

if [ -z "$DATABASE_URL" ]; then
    echo -e "${RED}✗ ERRO: DATABASE_URL não encontrado${NC}"
    echo "Configure a variável de ambiente DATABASE_URL"
    exit 1
fi

# Exportar estrutura e dados
pg_dump $DATABASE_URL > $EXPORT_DIR/sistema-jomaga-banco.sql

if [ $? -eq 0 ]; then
    BANCO_SIZE=$(du -h $EXPORT_DIR/sistema-jomaga-banco.sql | cut -f1)
    BANCO_LINES=$(wc -l < $EXPORT_DIR/sistema-jomaga-banco.sql)
    echo -e "${GREEN}✓ Banco exportado: sistema-jomaga-banco.sql ($BANCO_SIZE, $BANCO_LINES linhas)${NC}"
else
    echo -e "${RED}✗ ERRO ao exportar banco de dados${NC}"
    exit 1
fi

# Verificar conteúdo
echo ""
echo "Estatísticas do banco exportado:"
grep -E "^COPY.*FROM stdin" $EXPORT_DIR/sistema-jomaga-banco.sql | while read line; do
    table=$(echo $line | awk '{print $2}')
    echo "  - Tabela: $table"
done
echo ""

# ========================================
# PASSO 3: Criar arquivo .env.production
# ========================================
echo -e "${YELLOW}[4/5] Criando arquivo de configuração...${NC}"

cat > $EXPORT_DIR/.env.production << 'EOF'
# ========================================
# Configuração de Produção - Locaweb
# Sistema JOMAGA
# ========================================

# IMPORTANTE: Atualizar estas variáveis no servidor!

# Banco de dados PostgreSQL
# Formato: postgresql://usuario:senha@host:porta/database
DATABASE_URL=postgresql://jomaga_user:ALTERAR_SENHA_AQUI@localhost:5432/jomaga_db

# Session Secret (GERAR NOVA CHAVE!)
# Use: openssl rand -base64 32
SESSION_SECRET=GERAR_CHAVE_ALEATORIA_SEGURA_32_CARACTERES

# Ambiente
NODE_ENV=production

# Porta da aplicação (interna)
PORT=5000

# Domínio público (opcional)
# DOMAIN=ferramentas.suaempresa.com.br

# Configurações opcionais
# LOG_LEVEL=info
# MAX_UPLOAD_SIZE=10485760
EOF

echo -e "${GREEN}✓ Arquivo .env.production criado${NC}"
echo ""

# ========================================
# PASSO 4: Criar arquivo README
# ========================================
echo -e "${YELLOW}[5/5] Criando documentação...${NC}"

cat > $EXPORT_DIR/README.txt << 'EOF'
====================================
SISTEMA JOMAGA - EXPORTAÇÃO
====================================

Este pacote contém todos os arquivos necessários para migrar
o Sistema JOMAGA do Replit para o servidor Locaweb.

ARQUIVOS INCLUÍDOS:
-------------------
1. sistema-jomaga-codigo.tar.gz
   - Código-fonte completo da aplicação
   - Frontend e Backend
   - Configurações

2. sistema-jomaga-banco.sql
   - Backup completo do banco de dados PostgreSQL
   - Estrutura (tabelas, índices, constraints)
   - Dados (usuários, ferramentas, empréstimos, etc.)

3. .env.production
   - Arquivo de configuração para produção
   - IMPORTANTE: Atualizar variáveis antes de usar!

4. MIGRACAO_LOCAWEB.md
   - Guia passo a passo completo
   - Instruções detalhadas de instalação
   - Troubleshooting

PRÓXIMOS PASSOS:
----------------
1. Baixar todos os arquivos deste diretório
2. Seguir o guia MIGRACAO_LOCAWEB.md
3. Contratar VPS na Locaweb (mínimo: 4GB RAM, 2 vCPUs)
4. Fazer upload dos arquivos para o servidor
5. Executar instalação seguindo o guia

REQUISITOS NO SERVIDOR LOCAWEB:
--------------------------------
- Ubuntu 22.04 LTS
- 4 GB RAM (mínimo)
- 2 vCPUs
- 40 GB SSD
- Node.js 20
- PostgreSQL 15
- Nginx

SUPORTE:
--------
Email: [seu-email]
WhatsApp: [seu-telefone]

Instalação assistida disponível: R$ 1.500

Data da exportação: EXPORT_DATE
====================================
EOF

# Substituir data no README
sed -i "s/EXPORT_DATE/$(date '+%d\/%m\/%Y %H:%M:%S')/" $EXPORT_DIR/README.txt

echo -e "${GREEN}✓ README.txt criado${NC}"
echo ""

# ========================================
# PASSO 5: Copiar guia de migração
# ========================================
if [ -f "MIGRACAO_LOCAWEB.md" ]; then
    cp MIGRACAO_LOCAWEB.md $EXPORT_DIR/
    echo -e "${GREEN}✓ Guia de migração copiado${NC}"
fi

# ========================================
# RESUMO FINAL
# ========================================
echo ""
echo "======================================"
echo -e "${GREEN}EXPORTAÇÃO CONCLUÍDA!${NC}"
echo "======================================"
echo ""
echo "Arquivos criados em: $EXPORT_DIR/"
echo ""
ls -lh $EXPORT_DIR/
echo ""
echo -e "${YELLOW}PRÓXIMOS PASSOS:${NC}"
echo ""
echo "1. Baixar todos os arquivos do diretório '$EXPORT_DIR/'"
echo "   - Clique com botão direito em cada arquivo → Download"
echo ""
echo "2. Contratar VPS na Locaweb:"
echo "   - Plano: Cloud Server VPS 4 ou VPS 8"
echo "   - Sistema: Ubuntu 22.04 LTS"
echo "   - Mínimo: 4 GB RAM, 2 vCPUs, 40 GB SSD"
echo ""
echo "3. Seguir o guia: MIGRACAO_LOCAWEB.md"
echo ""
echo "4. Atualizar .env.production com:"
echo "   - Senha do banco de dados"
echo "   - Nova SESSION_SECRET (gerar com: openssl rand -base64 32)"
echo "   - Domínio da aplicação"
echo ""
echo -e "${YELLOW}IMPORTANTE:${NC}"
echo "- Guarde estes arquivos em local seguro"
echo "- NÃO compartilhe o arquivo .sql (contém dados sensíveis)"
echo "- Faça backup antes de qualquer alteração no servidor"
echo ""
echo "======================================"
