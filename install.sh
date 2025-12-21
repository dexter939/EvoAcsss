#!/bin/bash

###############################################################################
# ACS (Auto Configuration Server) - Installazione Sistema Linux
# Carrier-grade TR-069/TR-369 CPE Management Platform
# 
# Requisiti:
# - Ubuntu 22.04+ / Debian 11+ / CentOS 8+
# - Accesso root o sudo
# - Connessione internet
#
# Uso:
#   sudo ./install.sh                    # Installazione con valori default
#   sudo ./install.sh --interactive      # Installazione interattiva
#   sudo ./install.sh --help             # Mostra aiuto
###############################################################################

set -e

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

###############################################################################
# CONFIGURAZIONE PERSONALIZZABILE
# Modifica questi valori PRIMA di eseguire lo script, oppure usa --interactive
###############################################################################

# Dominio del server ACS (usato per Nginx e URL applicazione)
DOMAIN="${DOMAIN:-acs.tuodominio.com}"

# Configurazione Database PostgreSQL
DB_NAME="${DB_NAME:-acs_production}"
DB_USER="${DB_USER:-acs_user}"
DB_PASSWORD="${DB_PASSWORD:-}"  # Se vuoto, verrà generata automaticamente

# Porta dell'applicazione Laravel
APP_PORT="${APP_PORT:-5000}"

# Repository Git (lascia vuoto per usare il repository ufficiale)
REPO_URL="${REPO_URL:-https://github.com/dexter939/EvoAcs.git}"

# Abilita SSL automatico con Let's Encrypt (true/false)
ENABLE_SSL="${ENABLE_SSL:-false}"

# Email per Let's Encrypt (obbligatorio se ENABLE_SSL=true)
SSL_EMAIL="${SSL_EMAIL:-}"

###############################################################################
# FINE CONFIGURAZIONE - Non modificare sotto questa linea
###############################################################################

# Variabili di sistema (non modificare)
APP_NAME="ACS Server"
APP_DIR="/opt/acs"
APP_USER="acs"
PHP_VERSION="8.3"
POSTGRES_VERSION="16"
REDIS_VERSION="7.0"

# Funzioni helper
print_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_header() {
    echo -e "${CYAN}$1${NC}"
}

show_help() {
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo -e "${GREEN}   ACS (Auto Configuration Server) - Script di Installazione${NC}"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
    echo "Uso:"
    echo "  sudo ./install.sh [OPZIONI]"
    echo ""
    echo "Opzioni:"
    echo "  --interactive    Modalità interattiva (richiede input utente)"
    echo "  --help, -h       Mostra questo messaggio di aiuto"
    echo "  --dry-run        Mostra cosa verrebbe installato senza eseguire"
    echo ""
    echo "Variabili d'ambiente (alternativa alla modifica dello script):"
    echo "  DOMAIN           Dominio del server (default: acs.tuodominio.com)"
    echo "  DB_NAME          Nome database PostgreSQL (default: acs_production)"
    echo "  DB_USER          Utente database (default: acs_user)"
    echo "  DB_PASSWORD      Password database (generata se vuota)"
    echo "  APP_PORT         Porta applicazione (default: 5000)"
    echo "  REPO_URL         URL repository Git"
    echo "  ENABLE_SSL       Abilita SSL con Let's Encrypt (true/false)"
    echo "  SSL_EMAIL        Email per Let's Encrypt"
    echo ""
    echo "Esempi:"
    echo "  # Installazione con configurazione personalizzata"
    echo "  sudo DOMAIN=acs.miodominio.com DB_PASSWORD=MiaPassword123 ./install.sh"
    echo ""
    echo "  # Installazione interattiva"
    echo "  sudo ./install.sh --interactive"
    echo ""
    echo "  # Installazione con SSL"
    echo "  sudo DOMAIN=acs.miodominio.com ENABLE_SSL=true SSL_EMAIL=admin@miodominio.com ./install.sh"
    echo ""
    exit 0
}

interactive_setup() {
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo -e "${GREEN}   ACS - Configurazione Interattiva${NC}"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
    
    # Dominio
    read -p "Dominio del server [${DOMAIN}]: " input
    DOMAIN="${input:-$DOMAIN}"
    
    # Porta
    read -p "Porta applicazione [${APP_PORT}]: " input
    APP_PORT="${input:-$APP_PORT}"
    
    # Database
    echo ""
    print_header "Configurazione Database PostgreSQL:"
    read -p "Nome database [${DB_NAME}]: " input
    DB_NAME="${input:-$DB_NAME}"
    
    read -p "Utente database [${DB_USER}]: " input
    DB_USER="${input:-$DB_USER}"
    
    read -sp "Password database (lascia vuoto per generare automaticamente): " input
    echo ""
    DB_PASSWORD="${input:-$DB_PASSWORD}"
    
    # SSL
    echo ""
    print_header "Configurazione SSL:"
    read -p "Abilitare SSL con Let's Encrypt? (s/n) [n]: " input
    if [[ "$input" =~ ^[Ss]$ ]]; then
        ENABLE_SSL="true"
        read -p "Email per Let's Encrypt: " SSL_EMAIL
        if [ -z "$SSL_EMAIL" ]; then
            print_error "Email obbligatoria per Let's Encrypt"
            exit 1
        fi
    fi
    
    # Repository
    echo ""
    read -p "URL repository Git [${REPO_URL}]: " input
    REPO_URL="${input:-$REPO_URL}"
    
    # Conferma
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    print_header "Riepilogo Configurazione:"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  Dominio:        $DOMAIN"
    echo "  Porta:          $APP_PORT"
    echo "  Database:       $DB_NAME"
    echo "  Utente DB:      $DB_USER"
    echo "  Password DB:    ${DB_PASSWORD:-[generata automaticamente]}"
    echo "  SSL:            $ENABLE_SSL"
    echo "  Repository:     $REPO_URL"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
    
    read -p "Procedere con l'installazione? (s/n) [s]: " confirm
    if [[ "$confirm" =~ ^[Nn]$ ]]; then
        print_warning "Installazione annullata dall'utente"
        exit 0
    fi
}

dry_run() {
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo -e "${GREEN}   ACS - Dry Run (Simulazione)${NC}"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
    echo "Configurazione che verrà utilizzata:"
    echo "  • Dominio: $DOMAIN"
    echo "  • Porta: $APP_PORT"
    echo "  • Database: $DB_NAME (utente: $DB_USER)"
    echo "  • Directory installazione: $APP_DIR"
    echo "  • Utente sistema: $APP_USER"
    echo "  • PHP: $PHP_VERSION"
    echo "  • PostgreSQL: $POSTGRES_VERSION"
    echo "  • SSL: $ENABLE_SSL"
    echo ""
    echo "Pacchetti che verranno installati:"
    echo "  • PHP $PHP_VERSION con estensioni (fpm, cli, pgsql, redis, etc.)"
    echo "  • PostgreSQL $POSTGRES_VERSION"
    echo "  • Redis"
    echo "  • Nginx"
    echo "  • Prosody XMPP Server"
    echo "  • Supervisor"
    echo "  • Composer"
    if [ "$ENABLE_SSL" = "true" ]; then
        echo "  • Certbot (Let's Encrypt)"
    fi
    echo ""
    echo "Servizi che verranno configurati:"
    echo "  • acs-server.service (Laravel application)"
    echo "  • nginx (reverse proxy)"
    echo "  • postgresql (database)"
    echo "  • redis (cache/queue)"
    echo "  • prosody (XMPP per TR-369)"
    echo "  • supervisor (queue workers)"
    echo ""
    exit 0
}

parse_args() {
    while [[ $# -gt 0 ]]; do
        case $1 in
            --interactive)
                INTERACTIVE_MODE=true
                shift
                ;;
            --help|-h)
                show_help
                ;;
            --dry-run)
                dry_run
                ;;
            *)
                print_error "Opzione sconosciuta: $1"
                echo "Usa --help per vedere le opzioni disponibili"
                exit 1
                ;;
        esac
    done
}

check_root() {
    if [[ $EUID -ne 0 ]]; then
        print_error "Questo script deve essere eseguito come root o con sudo"
        exit 1
    fi
}

detect_os() {
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        OS=$ID
        OS_VERSION=$VERSION_ID
        print_info "OS rilevato: $NAME $VERSION"
        
        # Verifica versioni Ubuntu supportate
        if [ "$OS" = "ubuntu" ]; then
            # Estrai major version
            MAJOR_VERSION=$(echo "$OS_VERSION" | cut -d. -f1)
            
            # Versioni supportate: 20.04, 22.04, 24.04 (solo LTS)
            case "$OS_VERSION" in
                20.04|22.04|24.04)
                    print_success "Versione Ubuntu supportata: $OS_VERSION LTS"
                    ;;
                *)
                    echo ""
                    print_error "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
                    print_error "VERSIONE UBUNTU NON SUPPORTATA: $OS_VERSION"
                    print_error "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
                    echo ""
                    echo -e "${YELLOW}Il PPA ondrej/php (necessario per PHP 8.3) supporta solo versioni LTS.${NC}"
                    echo ""
                    echo -e "${BLUE}Versioni supportate:${NC}"
                    echo "  • Ubuntu 20.04 LTS (Focal Fossa)"
                    echo "  • Ubuntu 22.04 LTS (Jammy Jellyfish) - Consigliata"
                    echo "  • Ubuntu 24.04 LTS (Noble Numbat)"
                    echo ""
                    echo -e "${YELLOW}Soluzione:${NC}"
                    echo "  1. Reinstalla il server con Ubuntu 22.04 LTS o 24.04 LTS"
                    echo "  2. Esegui nuovamente questo script"
                    echo ""
                    echo -e "${RED}Ubuntu 25.04 (Plucky Puffin) è una versione di sviluppo,${NC}"
                    echo -e "${RED}NON una release stabile LTS.${NC}"
                    echo ""
                    exit 1
                    ;;
            esac
        fi
        
        # Verifica versioni Debian supportate
        if [ "$OS" = "debian" ]; then
            MAJOR_VERSION=$(echo "$OS_VERSION" | cut -d. -f1)
            if [ "$MAJOR_VERSION" -lt 11 ]; then
                print_error "Debian $OS_VERSION non supportato. Richiesto Debian 11+."
                exit 1
            fi
            print_success "Versione Debian supportata: $OS_VERSION"
        fi
    else
        print_error "Impossibile rilevare il sistema operativo"
        exit 1
    fi
}

install_dependencies_ubuntu() {
    print_info "Installazione dipendenze per Ubuntu..."
    
    # Update package lists
    apt-get update -y
    
    # Installazione pacchetti di sistema
    apt-get install -y \
        software-properties-common \
        apt-transport-https \
        ca-certificates \
        curl \
        wget \
        git \
        unzip \
        supervisor \
        nginx \
        gnupg \
        lsb-release
    
    # Repository PHP (ondrej PPA per Ubuntu)
    add-apt-repository ppa:ondrej/php -y
    apt-get update -y
    
    # Installazione PHP
    print_info "Installazione PHP ${PHP_VERSION}..."
    apt-get install -y \
        php${PHP_VERSION} \
        php${PHP_VERSION}-fpm \
        php${PHP_VERSION}-cli \
        php${PHP_VERSION}-common \
        php${PHP_VERSION}-mysql \
        php${PHP_VERSION}-pgsql \
        php${PHP_VERSION}-zip \
        php${PHP_VERSION}-gd \
        php${PHP_VERSION}-mbstring \
        php${PHP_VERSION}-curl \
        php${PHP_VERSION}-xml \
        php${PHP_VERSION}-bcmath \
        php${PHP_VERSION}-redis \
        php${PHP_VERSION}-intl
    
    # PostgreSQL
    print_info "Installazione PostgreSQL ${POSTGRES_VERSION}..."
    sh -c 'echo "deb http://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" > /etc/apt/sources.list.d/pgdg.list'
    wget --quiet -O - https://www.postgresql.org/media/keys/ACCC4CF8.asc | apt-key add -
    apt-get update -y
    apt-get install -y postgresql-${POSTGRES_VERSION} postgresql-client-${POSTGRES_VERSION}
    
    # Redis
    print_info "Installazione Redis..."
    apt-get install -y redis-server
    
    # Prosody XMPP Server
    print_info "Installazione Prosody XMPP Server..."
    apt-get install -y prosody lua-dbi-postgresql
    
    # Salva percorso socket PHP-FPM per Nginx
    PHP_FPM_SOCKET="/var/run/php/php${PHP_VERSION}-fpm.sock"
    
    # Abilita e avvia PHP-FPM
    systemctl enable php${PHP_VERSION}-fpm
    systemctl start php${PHP_VERSION}-fpm
    
    # Composer
    install_composer
    
    print_success "Dipendenze Ubuntu installate con successo"
}

install_dependencies_debian() {
    print_info "Installazione dipendenze per Debian..."
    
    # Update package lists
    apt-get update -y
    
    # Installazione pacchetti di sistema
    apt-get install -y \
        software-properties-common \
        apt-transport-https \
        ca-certificates \
        curl \
        wget \
        git \
        unzip \
        supervisor \
        nginx \
        gnupg \
        lsb-release
    
    # Repository PHP Sury per Debian (NON Ubuntu PPA!)
    print_info "Configurazione repository Sury PHP per Debian..."
    curl -sSLo /usr/share/keyrings/deb.sury.org-php.gpg https://packages.sury.org/php/apt.gpg
    echo "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list
    apt-get update -y
    
    # Installazione PHP
    print_info "Installazione PHP ${PHP_VERSION}..."
    apt-get install -y \
        php${PHP_VERSION} \
        php${PHP_VERSION}-fpm \
        php${PHP_VERSION}-cli \
        php${PHP_VERSION}-common \
        php${PHP_VERSION}-mysql \
        php${PHP_VERSION}-pgsql \
        php${PHP_VERSION}-zip \
        php${PHP_VERSION}-gd \
        php${PHP_VERSION}-mbstring \
        php${PHP_VERSION}-curl \
        php${PHP_VERSION}-xml \
        php${PHP_VERSION}-bcmath \
        php${PHP_VERSION}-redis \
        php${PHP_VERSION}-intl
    
    # PostgreSQL
    print_info "Installazione PostgreSQL ${POSTGRES_VERSION}..."
    sh -c 'echo "deb http://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" > /etc/apt/sources.list.d/pgdg.list'
    wget --quiet -O - https://www.postgresql.org/media/keys/ACCC4CF8.asc | apt-key add -
    apt-get update -y
    apt-get install -y postgresql-${POSTGRES_VERSION} postgresql-client-${POSTGRES_VERSION}
    
    # Redis
    print_info "Installazione Redis..."
    apt-get install -y redis-server
    
    # Prosody XMPP Server
    print_info "Installazione Prosody XMPP Server..."
    apt-get install -y prosody lua-dbi-postgresql
    
    # Salva percorso socket PHP-FPM per Nginx
    PHP_FPM_SOCKET="/var/run/php/php${PHP_VERSION}-fpm.sock"
    
    # Abilita e avvia PHP-FPM
    systemctl enable php${PHP_VERSION}-fpm
    systemctl start php${PHP_VERSION}-fpm
    
    # Composer
    install_composer
    
    print_success "Dipendenze Debian installate con successo"
}

install_dependencies_centos() {
    print_info "Installazione dipendenze per CentOS/RHEL..."
    
    # EPEL repository
    dnf install -y epel-release
    dnf update -y
    
    # Remi repository per PHP
    dnf install -y https://rpms.remirepo.net/enterprise/remi-release-8.rpm || dnf install -y https://rpms.remirepo.net/enterprise/remi-release-9.rpm
    dnf module reset php -y
    dnf module enable php:remi-${PHP_VERSION} -y
    
    # Installazione pacchetti
    dnf install -y \
        git \
        curl \
        wget \
        unzip \
        nginx \
        supervisor \
        php \
        php-fpm \
        php-cli \
        php-pgsql \
        php-mbstring \
        php-xml \
        php-gd \
        php-curl \
        php-zip \
        php-bcmath \
        php-redis \
        php-intl
    
    # PostgreSQL - installa dal repository ufficiale
    dnf install -y https://download.postgresql.org/pub/repos/yum/reporpms/EL-8-x86_64/pgdg-redhat-repo-latest.noarch.rpm || true
    dnf -qy module disable postgresql
    dnf install -y postgresql${POSTGRES_VERSION}-server postgresql${POSTGRES_VERSION}
    /usr/pgsql-${POSTGRES_VERSION}/bin/postgresql-${POSTGRES_VERSION}-setup initdb
    
    # Nome servizio PostgreSQL su CentOS/RHEL
    PG_SERVICE="postgresql-${POSTGRES_VERSION}"
    systemctl enable $PG_SERVICE
    systemctl start $PG_SERVICE
    
    # Redis
    dnf install -y redis
    systemctl enable redis
    systemctl start redis
    
    # Prosody
    dnf install -y prosody || print_warning "Prosody non disponibile nei repository, installalo manualmente"
    
    # Salva percorso socket PHP-FPM per Nginx (CentOS usa path diverso)
    PHP_FPM_SOCKET="/run/php-fpm/www.sock"
    
    # Abilita e avvia PHP-FPM
    systemctl enable php-fpm
    systemctl start php-fpm
    
    install_composer
    
    print_success "Dipendenze CentOS/RHEL installate con successo"
}

install_composer() {
    print_info "Installazione Composer..."
    EXPECTED_CHECKSUM="$(wget -q -O - https://composer.github.io/installer.sig)"
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"
    
    if [ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]; then
        print_error "Checksum Composer non valido"
        rm composer-setup.php
        exit 1
    fi
    
    php composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer
    rm composer-setup.php
    print_success "Composer installato"
}

create_app_user() {
    print_info "Creazione utente applicazione..."
    
    if id "$APP_USER" &>/dev/null; then
        print_warning "Utente $APP_USER già esistente"
    else
        useradd -r -m -s /bin/bash $APP_USER
        print_success "Utente $APP_USER creato"
    fi
}

setup_database() {
    print_info "Configurazione database PostgreSQL..."
    
    # Determina il nome del servizio PostgreSQL in base al sistema operativo
    if [ "$OS" = "centos" ] || [ "$OS" = "rhel" ] || [ "$OS" = "rocky" ] || [ "$OS" = "almalinux" ]; then
        PG_SERVICE="postgresql-${POSTGRES_VERSION}"
    else
        PG_SERVICE="postgresql"
    fi
    
    # Avvia PostgreSQL se non attivo
    systemctl start $PG_SERVICE || systemctl start postgresql || print_warning "Impossibile avviare PostgreSQL"
    systemctl enable $PG_SERVICE || systemctl enable postgresql || true
    
    # Genera password casuale se non fornita (solo caratteri alfanumerici per evitare problemi)
    if [ -z "$DB_PASSWORD" ]; then
        DB_PASSWORD=$(openssl rand -hex 16)
        print_info "Password DB generata automaticamente"
    fi
    
    # Sanitizza input per prevenire SQL injection
    # Rimuovi caratteri speciali potenzialmente pericolosi
    DB_NAME_SAFE=$(echo "$DB_NAME" | tr -cd 'a-zA-Z0-9_')
    DB_USER_SAFE=$(echo "$DB_USER" | tr -cd 'a-zA-Z0-9_')
    DB_PASSWORD_SAFE=$(echo "$DB_PASSWORD" | tr -cd 'a-zA-Z0-9_')
    
    # Configura pg_hba.conf per autenticazione password
    PG_HBA_FILE=$(sudo -u postgres psql -t -c "SHOW hba_file;" 2>/dev/null | tr -d ' ' || echo "")
    if [ -n "$PG_HBA_FILE" ] && [ -f "$PG_HBA_FILE" ]; then
        # Backup originale
        cp "$PG_HBA_FILE" "${PG_HBA_FILE}.backup"
        
        # Verifica se la riga per autenticazione md5/scram esiste
        if ! grep -q "host.*all.*all.*127.0.0.1.*md5\|scram-sha-256" "$PG_HBA_FILE"; then
            print_info "Configurazione pg_hba.conf per autenticazione password..."
            # Aggiungi autenticazione md5 per connessioni locali
            sed -i '/^# IPv4 local connections:/a host    all             all             127.0.0.1/32            md5' "$PG_HBA_FILE" || \
            echo "host    all             all             127.0.0.1/32            md5" >> "$PG_HBA_FILE"
        fi
    else
        print_warning "pg_hba.conf non trovato, configurazione manuale potrebbe essere necessaria"
    fi
    
    # Ricarica configurazione PostgreSQL
    systemctl reload $PG_SERVICE 2>/dev/null || systemctl reload postgresql 2>/dev/null || true
    sleep 2
    
    # Elimina utente e database se esistono (reinstallazione pulita)
    print_info "Creazione utente e database..."
    sudo -u postgres psql -c "DROP DATABASE IF EXISTS ${DB_NAME_SAFE};" 2>/dev/null || true
    sudo -u postgres psql -c "DROP USER IF EXISTS ${DB_USER_SAFE};" 2>/dev/null || true
    
    # Crea utente con password (usando formato sicuro)
    sudo -u postgres psql -c "CREATE USER ${DB_USER_SAFE} WITH PASSWORD '${DB_PASSWORD_SAFE}';"
    
    # Crea database
    sudo -u postgres psql -c "CREATE DATABASE ${DB_NAME_SAFE} OWNER ${DB_USER_SAFE};"
    
    # Grant privilegi
    sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE ${DB_NAME_SAFE} TO ${DB_USER_SAFE};"
    
    # Abilita estensioni
    sudo -u postgres psql -d ${DB_NAME_SAFE} -c "CREATE EXTENSION IF NOT EXISTS \"uuid-ossp\";"
    
    # Aggiorna variabili con valori sanitizzati
    DB_NAME="$DB_NAME_SAFE"
    DB_USER="$DB_USER_SAFE"
    DB_PASSWORD="$DB_PASSWORD_SAFE"
    
    # Verifica connessione
    print_info "Verifica connessione database..."
    if PGPASSWORD="$DB_PASSWORD" psql -h 127.0.0.1 -U $DB_USER -d $DB_NAME -c "SELECT 1;" >/dev/null 2>&1; then
        print_success "Connessione database verificata!"
    else
        print_warning "Impossibile verificare la connessione, riavvio PostgreSQL..."
        systemctl restart $PG_SERVICE 2>/dev/null || systemctl restart postgresql 2>/dev/null
        sleep 3
    fi
    
    print_success "Database configurato: $DB_NAME"
    
    # Salva credenziali
    cat > /root/.acs_db_credentials <<EOF
DB_NAME=$DB_NAME
DB_USER=$DB_USER
DB_PASSWORD=$DB_PASSWORD
DB_HOST=127.0.0.1
DB_PORT=5432
PG_SERVICE=$PG_SERVICE
EOF
    chmod 600 /root/.acs_db_credentials
    print_info "Credenziali salvate in /root/.acs_db_credentials"
}

clone_repository() {
    print_info "Clonazione repository applicazione..."
    
    if [ -z "$REPO_URL" ]; then
        REPO_URL="https://github.com/dexter939/EvoAcs.git"
        print_info "Usando repository default: $REPO_URL"
    fi
    
    # Rimuovi directory esistente se presente
    if [ -d "$APP_DIR" ]; then
        print_warning "Directory $APP_DIR già esistente, rimozione..."
        rm -rf $APP_DIR
    fi
    
    git clone $REPO_URL $APP_DIR
    chown -R $APP_USER:$APP_USER $APP_DIR
    
    print_success "Repository clonato in $APP_DIR"
}

install_php_dependencies() {
    print_info "Installazione dipendenze PHP..."
    
    cd $APP_DIR
    sudo -u $APP_USER composer install --no-dev --optimize-autoloader
    
    print_success "Dipendenze PHP installate"
}

configure_environment() {
    print_info "Configurazione file .env..."
    
    cd $APP_DIR
    
    # Carica credenziali DB
    . /root/.acs_db_credentials
    
    # Genera APP_KEY
    APP_KEY=$(php artisan key:generate --show)
    
    # Genera SESSION_SECRET
    SESSION_SECRET=$(openssl rand -base64 32)
    
    cat > .env <<EOF
APP_NAME="ACS Server"
APP_ENV=production
APP_KEY=$APP_KEY
APP_DEBUG=false
APP_URL=http://$DOMAIN

LOG_CHANNEL=stack
LOG_LEVEL=info

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=$DB_NAME
DB_USERNAME=$DB_USER
DB_PASSWORD=$DB_PASSWORD

BROADCAST_DRIVER=log
CACHE_DRIVER=redis
FILESYSTEM_DISK=local
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_SECRET=$SESSION_SECRET

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@acs.local"
MAIL_FROM_NAME="${APP_NAME}"

# TR-069 Configuration
TR069_ACS_URL=http://$DOMAIN/acs
TR069_CONNECTION_REQUEST_PORT=7547

# TR-369 USP Configuration
USP_ENDPOINT_ID=acs::controller
USP_XMPP_SERVER=localhost
USP_XMPP_PORT=5222
EOF
    
    chown $APP_USER:$APP_USER .env
    chmod 600 .env
    
    print_success "File .env configurato"
}

run_migrations() {
    print_info "Esecuzione migrazioni database..."
    
    cd $APP_DIR
    sudo -u $APP_USER php artisan migrate --force
    
    print_success "Migrazioni completate"
}

seed_database() {
    print_info "Popolamento database iniziale..."
    
    cd $APP_DIR
    sudo -u $APP_USER php artisan db:seed --force
    
    print_success "Database popolato"
}

optimize_laravel() {
    print_info "Ottimizzazione Laravel..."
    
    cd $APP_DIR
    sudo -u $APP_USER php artisan config:cache
    sudo -u $APP_USER php artisan route:cache
    sudo -u $APP_USER php artisan view:cache
    sudo -u $APP_USER php artisan event:cache
    
    print_success "Laravel ottimizzato"
}

configure_nginx() {
    print_info "Configurazione Nginx..."
    
    # Determina il socket PHP-FPM in base al sistema operativo
    if [ "$OS" = "centos" ] || [ "$OS" = "rhel" ] || [ "$OS" = "rocky" ] || [ "$OS" = "almalinux" ]; then
        PHP_FPM_SOCKET="/run/php-fpm/www.sock"
        NGINX_CONF_DIR="/etc/nginx/conf.d"
        NGINX_SITE_FILE="$NGINX_CONF_DIR/acs.conf"
    else
        PHP_FPM_SOCKET="/var/run/php/php${PHP_VERSION}-fpm.sock"
        NGINX_CONF_DIR="/etc/nginx/sites-available"
        NGINX_SITE_FILE="$NGINX_CONF_DIR/acs"
    fi
    
    # Crea directory se non esiste
    mkdir -p "$NGINX_CONF_DIR"
    
    cat > "$NGINX_SITE_FILE" <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN;
    
    root $APP_DIR/public;
    index index.php index.html;
    
    client_max_body_size 100M;
    
    # Disable caching for development/iframe compatibility
    add_header Cache-Control "no-cache, no-store, must-revalidate";
    add_header Pragma "no-cache";
    add_header Expires "0";
    
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:${PHP_FPM_SOCKET};
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }
    
    location ~ /\.(?!well-known).* {
        deny all;
    }
    
    # TR-069 ACS endpoint
    location /acs {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
    
    # WebSocket per Laravel Reverb
    location /app {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_read_timeout 86400;
    }
    
    # Logs
    access_log /var/log/nginx/acs-access.log;
    error_log /var/log/nginx/acs-error.log;
}
EOF
    
    # Abilita site (solo per Debian/Ubuntu che usano sites-enabled)
    if [ -d "/etc/nginx/sites-enabled" ]; then
        ln -sf /etc/nginx/sites-available/acs /etc/nginx/sites-enabled/
        rm -f /etc/nginx/sites-enabled/default
    fi
    
    # Test configurazione
    nginx -t
    
    # Restart Nginx
    systemctl restart nginx
    systemctl enable nginx
    
    print_success "Nginx configurato"
}

configure_supervisor() {
    print_info "Configurazione Supervisor..."
    
    # Determina il nome del servizio Supervisor in base al sistema operativo
    if [ "$OS" = "centos" ] || [ "$OS" = "rhel" ] || [ "$OS" = "rocky" ] || [ "$OS" = "almalinux" ]; then
        SUPERVISOR_SERVICE="supervisord"
        SUPERVISOR_CONF_DIR="/etc/supervisord.d"
        SUPERVISOR_MAIN_CONF="/etc/supervisord.conf"
    else
        SUPERVISOR_SERVICE="supervisor"
        SUPERVISOR_CONF_DIR="/etc/supervisor/conf.d"
        SUPERVISOR_MAIN_CONF="/etc/supervisor/supervisord.conf"
    fi
    
    # Crea directory
    mkdir -p /var/log/supervisor
    mkdir -p "$SUPERVISOR_CONF_DIR"
    
    # Assicurati che la configurazione principale di Supervisor sia corretta
    if [ ! -f "$SUPERVISOR_MAIN_CONF" ]; then
        cat > "$SUPERVISOR_MAIN_CONF" <<MAINCONF
[unix_http_server]
file=/var/run/supervisor.sock
chmod=0700

[supervisord]
logfile=/var/log/supervisor/supervisord.log
pidfile=/var/run/supervisord.pid
childlogdir=/var/log/supervisor

[rpcinterface:supervisor]
supervisor.rpcinterface_factory = supervisor.rpcinterface:make_main_rpcinterface

[supervisorctl]
serverurl=unix:///var/run/supervisor.sock

[include]
files = ${SUPERVISOR_CONF_DIR}/*.conf
MAINCONF
    fi
    
    # Verifica che [supervisorctl] sia presente nel file principale
    if ! grep -q "\[supervisorctl\]" "$SUPERVISOR_MAIN_CONF" 2>/dev/null; then
        print_warning "Aggiunta sezione [supervisorctl] alla configurazione..."
        cat >> "$SUPERVISOR_MAIN_CONF" <<ADDCONF

[supervisorctl]
serverurl=unix:///var/run/supervisor.sock
ADDCONF
    fi
    
    # Configurazione programmi ACS
    cat > "$SUPERVISOR_CONF_DIR/acs.conf" <<EOF
[program:acs-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php $APP_DIR/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=$APP_USER
numprocs=4
redirect_stderr=true
stdout_logfile=/var/log/supervisor/acs-queue.log
stopwaitsecs=3600

[program:acs-horizon]
process_name=%(program_name)s
command=php $APP_DIR/artisan horizon
autostart=true
autorestart=true
user=$APP_USER
redirect_stderr=true
stdout_logfile=/var/log/supervisor/acs-horizon.log
stopwaitsecs=3600

[program:acs-reverb]
process_name=%(program_name)s
command=php $APP_DIR/artisan reverb:start --host=0.0.0.0 --port=8080
autostart=true
autorestart=true
user=$APP_USER
redirect_stderr=true
stdout_logfile=/var/log/supervisor/acs-reverb.log
stopwaitsecs=10
EOF
    
    # Abilita e avvia Supervisor service PRIMA di usare supervisorctl
    systemctl enable $SUPERVISOR_SERVICE 2>/dev/null || systemctl enable supervisor 2>/dev/null || true
    systemctl restart $SUPERVISOR_SERVICE 2>/dev/null || systemctl restart supervisor 2>/dev/null || true
    
    # Attendi che Supervisor sia pronto
    sleep 3
    
    # Ricarica configurazione
    supervisorctl reread 2>/dev/null || print_warning "supervisorctl reread fallito"
    supervisorctl update 2>/dev/null || print_warning "supervisorctl update fallito"
    supervisorctl start all 2>/dev/null || print_warning "Alcuni processi potrebbero non essere avviati"
    
    print_success "Supervisor configurato"
}

configure_prosody() {
    print_info "Configurazione Prosody XMPP Server..."
    
    cat > /etc/prosody/prosody.cfg.lua <<EOF
-- Prosody XMPP Server Configuration for ACS TR-369 USP

admins = { }

modules_enabled = {
    "roster";
    "saslauth";
    "tls";
    "dialback";
    "disco";
    "carbons";
    "pep";
    "private";
    "blocklist";
    "vcard4";
    "vcard_legacy";
    "version";
    "uptime";
    "time";
    "ping";
    "register";
    "admin_adhoc";
}

modules_disabled = {
}

allow_registration = true

c2s_require_encryption = false
s2s_require_encryption = false

authentication = "internal_plain"

log = {
    info = "/var/log/prosody/prosody.log";
    error = "/var/log/prosody/prosody.err";
}

certificates = "certs"

VirtualHost "localhost"

Component "conference.localhost" "muc"
EOF
    
    # Restart Prosody
    systemctl restart prosody
    systemctl enable prosody
    
    print_success "Prosody configurato"
}

setup_systemd_service() {
    print_info "Verifica servizi PHP-FPM e Nginx..."
    
    # In produzione l'applicazione viene servita da Nginx + PHP-FPM
    # NON usiamo artisan serve che è solo per sviluppo
    
    # Determina il nome del servizio PHP-FPM
    if [ "$OS" = "centos" ] || [ "$OS" = "rhel" ] || [ "$OS" = "rocky" ] || [ "$OS" = "almalinux" ]; then
        PHP_FPM_SERVICE="php-fpm"
    else
        PHP_FPM_SERVICE="php${PHP_VERSION}-fpm"
    fi
    
    # Abilita e avvia PHP-FPM
    systemctl enable $PHP_FPM_SERVICE 2>/dev/null || true
    systemctl restart $PHP_FPM_SERVICE 2>/dev/null || true
    
    # Verifica che i servizi siano attivi
    if systemctl is-active --quiet nginx && systemctl is-active --quiet $PHP_FPM_SERVICE; then
        print_success "Nginx e PHP-FPM attivi e funzionanti"
    else
        print_warning "Verifica manuale dei servizi consigliata"
        systemctl status nginx --no-pager || true
        systemctl status $PHP_FPM_SERVICE --no-pager || true
    fi
    
    print_success "Configurazione servizi completata"
}

setup_firewall() {
    print_info "Configurazione firewall..."
    
    if command -v ufw &> /dev/null; then
        ufw allow 80/tcp
        ufw allow 443/tcp
        ufw allow 7547/tcp  # TR-069 Connection Request
        ufw allow 5222/tcp  # XMPP
        print_success "Firewall UFW configurato"
    elif command -v firewall-cmd &> /dev/null; then
        firewall-cmd --permanent --add-service=http
        firewall-cmd --permanent --add-service=https
        firewall-cmd --permanent --add-port=7547/tcp
        firewall-cmd --permanent --add-port=5222/tcp
        firewall-cmd --reload
        print_success "Firewall firewalld configurato"
    else
        print_warning "Nessun firewall rilevato, configura manualmente le porte"
    fi
}

create_admin_user() {
    print_info "Creazione utente amministratore..."
    
    cd $APP_DIR
    
    # Crea utente admin di default
    sudo -u $APP_USER php artisan db:seed --class=TestUserSeeder --force
    
    print_success "Utente admin creato: admin@acs.local / password"
    print_warning "IMPORTANTE: Cambia la password al primo login!"
}

setup_cron() {
    print_info "Configurazione cron jobs..."
    
    # Aggiungi cron job per Laravel scheduler
    (crontab -u $APP_USER -l 2>/dev/null; echo "* * * * * cd $APP_DIR && php artisan schedule:run >> /dev/null 2>&1") | crontab -u $APP_USER -
    
    print_success "Cron jobs configurati"
}

set_permissions() {
    print_info "Impostazione permessi..."
    
    cd $APP_DIR
    
    chown -R $APP_USER:www-data .
    chmod -R 755 .
    chmod -R 775 storage bootstrap/cache
    
    print_success "Permessi impostati"
}

setup_ssl() {
    if [ "$ENABLE_SSL" != "true" ]; then
        return 0
    fi
    
    print_info "Configurazione SSL con Let's Encrypt..."
    
    if [ -z "$SSL_EMAIL" ]; then
        print_error "Email obbligatoria per Let's Encrypt (SSL_EMAIL)"
        return 1
    fi
    
    # Installa Certbot
    if [ "$OS" = "ubuntu" ] || [ "$OS" = "debian" ]; then
        apt-get install -y certbot python3-certbot-nginx
    else
        dnf install -y certbot python3-certbot-nginx
    fi
    
    # Ottieni certificato
    certbot --nginx -d $DOMAIN --non-interactive --agree-tos -m $SSL_EMAIL
    
    # Configura rinnovo automatico
    systemctl enable certbot.timer
    systemctl start certbot.timer
    
    # Aggiorna .env con HTTPS
    sed -i "s|APP_URL=http://$DOMAIN|APP_URL=https://$DOMAIN|g" $APP_DIR/.env
    
    # Ricarica configurazione
    cd $APP_DIR
    sudo -u $APP_USER php artisan config:cache
    
    print_success "SSL configurato per $DOMAIN"
}

display_summary() {
    local PROTOCOL="http"
    if [ "$ENABLE_SSL" = "true" ]; then
        PROTOCOL="https"
    fi
    
    # Determina i nomi dei servizi in base al sistema operativo
    if [ "$OS" = "centos" ] || [ "$OS" = "rhel" ] || [ "$OS" = "rocky" ] || [ "$OS" = "almalinux" ]; then
        PHP_FPM_SERVICE="php-fpm"
        SUPERVISOR_SERVICE="supervisord"
        PG_SERVICE_NAME="postgresql-${POSTGRES_VERSION}"
    else
        PHP_FPM_SERVICE="php${PHP_VERSION}-fpm"
        SUPERVISOR_SERVICE="supervisor"
        PG_SERVICE_NAME="postgresql"
    fi
    
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    print_success "Installazione completata con successo!"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
    echo -e "${BLUE}Informazioni Sistema:${NC}"
    echo "  • URL applicazione: ${PROTOCOL}://$DOMAIN"
    echo "  • Directory installazione: $APP_DIR"
    echo "  • Utente applicazione: $APP_USER"
    echo "  • SSL: $ENABLE_SSL"
    echo ""
    echo -e "${BLUE}Credenziali Default:${NC}"
    echo "  • Email: admin@acs.local"
    echo "  • Password: password"
    echo -e "  ${RED}⚠ CAMBIA LA PASSWORD AL PRIMO LOGIN!${NC}"
    echo ""
    echo -e "${BLUE}Database:${NC}"
    echo "  • Nome: $DB_NAME"
    echo "  • Utente: $DB_USER"
    echo "  • Credenziali salvate in: /root/.acs_db_credentials"
    echo ""
    echo -e "${BLUE}Servizi Attivi:${NC}"
    echo "  • PHP-FPM: systemctl status $PHP_FPM_SERVICE"
    echo "  • Nginx: systemctl status nginx"
    echo "  • Queue Workers: systemctl status $SUPERVISOR_SERVICE"
    echo "  • PostgreSQL: systemctl status $PG_SERVICE_NAME"
    echo "  • Redis: systemctl status redis"
    echo "  • Prosody XMPP: systemctl status prosody"
    echo ""
    echo -e "${BLUE}Log Files:${NC}"
    echo "  • Laravel: $APP_DIR/storage/logs/laravel.log"
    echo "  • Nginx Access: /var/log/nginx/acs-access.log"
    echo "  • Nginx Error: /var/log/nginx/acs-error.log"
    echo "  • Queue Workers: /var/log/supervisor/acs-queue.log"
    echo "  • WebSocket: /var/log/supervisor/acs-reverb.log"
    echo ""
    echo -e "${BLUE}Comandi Utili:${NC}"
    echo "  • Restart servizi: systemctl restart nginx $PHP_FPM_SERVICE"
    echo "  • Clear cache: cd $APP_DIR && php artisan cache:clear"
    echo "  • View logs: tail -f $APP_DIR/storage/logs/laravel.log"
    echo "  • Restart queues: supervisorctl restart all"
    if [ "$ENABLE_SSL" = "true" ]; then
        echo "  • Rinnovo SSL: certbot renew"
    fi
    echo ""
    echo -e "${BLUE}Protocolli TR Supportati:${NC}"
    echo "  • TR-069, TR-104, TR-106, TR-111, TR-135"
    echo "  • TR-140, TR-157, TR-181, TR-262, TR-369"
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
}

main() {
    # Parse argomenti
    parse_args "$@"
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo -e "${GREEN}   ACS (Auto Configuration Server) - Installazione${NC}"
    echo -e "${BLUE}   Carrier-grade TR-069/TR-369 CPE Management Platform${NC}"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
    
    check_root
    detect_os
    
    # Modalità interattiva se richiesta
    if [ "$INTERACTIVE_MODE" = "true" ]; then
        interactive_setup
    fi
    
    # Mostra configurazione attuale
    echo ""
    print_info "Configurazione corrente:"
    echo "  • Dominio: $DOMAIN"
    echo "  • Porta: $APP_PORT"
    echo "  • Database: $DB_NAME (utente: $DB_USER)"
    echo "  • SSL: $ENABLE_SSL"
    echo ""
    
    # Installazione dipendenze OS-specific
    case $OS in
        ubuntu)
            install_dependencies_ubuntu
            ;;
        debian)
            install_dependencies_debian
            ;;
        centos|rhel|rocky|almalinux)
            install_dependencies_centos
            ;;
        *)
            print_error "Sistema operativo non supportato: $OS"
            exit 1
            ;;
    esac
    
    create_app_user
    setup_database
    clone_repository
    install_php_dependencies
    configure_environment
    run_migrations
    seed_database
    optimize_laravel
    set_permissions
    configure_nginx
    configure_supervisor
    configure_prosody
    setup_systemd_service
    setup_firewall
    setup_cron
    create_admin_user
    setup_ssl
    
    display_summary
}

# Esegui installazione
main "$@"
