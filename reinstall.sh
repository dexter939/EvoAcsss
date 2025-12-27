#!/bin/bash
#
# ACS Reinstallazione Pulita
# Rimuove completamente l'installazione esistente e reinstalla da zero
#
# Uso: sudo ./reinstall.sh
#

set -e

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info() { echo -e "${BLUE}[INFO]${NC} $1"; }
log_success() { echo -e "${GREEN}[OK]${NC} $1"; }
log_warning() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

# Verifica esecuzione come root
if [ "$EUID" -ne 0 ]; then
    log_error "Esegui come root: sudo ./reinstall.sh"
    exit 1
fi

echo ""
echo "=========================================="
echo "   ACS - Reinstallazione Pulita"
echo "=========================================="
echo ""
log_warning "Questo script rimuoverà COMPLETAMENTE l'installazione esistente!"
echo ""
read -p "Sei sicuro di voler continuare? (yes/no): " CONFIRM
if [ "$CONFIRM" != "yes" ]; then
    log_info "Operazione annullata."
    exit 0
fi

# ============================================
# FASE 1: BACKUP
# ============================================
log_info "FASE 1: Backup configurazione..."

BACKUP_DIR="/root/acs_backup_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"

if [ -f /opt/acs/.env ]; then
    cp /opt/acs/.env "$BACKUP_DIR/.env.backup"
    log_success "File .env salvato in $BACKUP_DIR"
else
    log_warning "Nessun file .env trovato"
fi

# ============================================
# FASE 2: STOP SERVIZI
# ============================================
log_info "FASE 2: Fermando tutti i servizi..."

# Supervisor
if command -v supervisorctl &> /dev/null; then
    supervisorctl stop all 2>/dev/null || true
    log_success "Supervisor fermato"
fi

# Nginx
systemctl stop nginx 2>/dev/null || true
log_success "Nginx fermato"

# PHP-FPM (prova diverse versioni)
for phpver in php8.3-fpm php8.2-fpm php8.1-fpm php-fpm; do
    systemctl stop $phpver 2>/dev/null || true
done
log_success "PHP-FPM fermato"

# Mosquitto (opzionale)
systemctl stop mosquitto 2>/dev/null || true
systemctl disable mosquitto 2>/dev/null || true
log_success "Mosquitto fermato e disabilitato"

# Prosody (opzionale)
systemctl stop prosody 2>/dev/null || true
log_success "Prosody fermato"

# Redis
systemctl stop redis-server 2>/dev/null || systemctl stop redis 2>/dev/null || true
log_success "Redis fermato"

# ============================================
# FASE 3: PULIZIA DATABASE
# ============================================
log_info "FASE 3: Rimozione database PostgreSQL..."

# Ottieni credenziali dal backup .env se esistente
DB_NAME="acs_production"
DB_USER="dexter939"

if [ -f "$BACKUP_DIR/.env.backup" ]; then
    source_db=$(grep "^DB_DATABASE=" "$BACKUP_DIR/.env.backup" | cut -d'=' -f2)
    source_user=$(grep "^DB_USERNAME=" "$BACKUP_DIR/.env.backup" | cut -d'=' -f2)
    [ -n "$source_db" ] && DB_NAME="$source_db"
    [ -n "$source_user" ] && DB_USER="$source_user"
fi

# Termina connessioni attive
sudo -u postgres psql -c "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '$DB_NAME' AND pid <> pg_backend_pid();" 2>/dev/null || true

# Rimuovi database e ruolo
sudo -u postgres psql << EOF 2>/dev/null || true
DROP DATABASE IF EXISTS $DB_NAME;
DROP ROLE IF EXISTS $DB_USER;
EOF

log_success "Database e ruolo PostgreSQL rimossi"

# ============================================
# FASE 4: PULIZIA FILESYSTEM
# ============================================
log_info "FASE 4: Rimozione file applicazione..."

# Rimuovi directory applicazione
rm -rf /opt/acs
log_success "Directory /opt/acs rimossa"

# Rimuovi configurazioni Nginx
rm -f /etc/nginx/sites-enabled/acs*
rm -f /etc/nginx/sites-available/acs*
log_success "Configurazioni Nginx rimosse"

# Rimuovi configurazioni Supervisor
rm -f /etc/supervisor/conf.d/acs*.conf
supervisorctl reread 2>/dev/null || true
supervisorctl update 2>/dev/null || true
log_success "Configurazioni Supervisor rimosse"

# Rimuovi utente sistema (opzionale, ricrea durante install)
userdel -r acs 2>/dev/null || true
log_success "Utente sistema 'acs' rimosso"

# ============================================
# FASE 5: RESET SERVIZI OPZIONALI
# ============================================
log_info "FASE 5: Reset servizi opzionali..."

# Reset Mosquitto config minima (se installato)
if command -v mosquitto &> /dev/null; then
    rm -f /etc/mosquitto/conf.d/*
    cat > /etc/mosquitto/conf.d/acs.conf << 'MQTTCONF'
listener 1883
allow_anonymous true
MQTTCONF
    chown mosquitto:mosquitto /etc/mosquitto/conf.d/acs.conf 2>/dev/null || true
    log_success "Configurazione Mosquitto resettata"
fi

# ============================================
# FASE 6: REINSTALLAZIONE
# ============================================
log_info "FASE 6: Clonazione repository..."

# Crea utente sistema
useradd -r -s /bin/bash -d /opt/acs acs 2>/dev/null || true

# Clone repository
git clone https://github.com/dexter939/EvoAcsss.git /opt/acs
chown -R acs:acs /opt/acs
log_success "Repository clonato"

# ============================================
# FASE 7: CONFIGURAZIONE DATABASE
# ============================================
log_info "FASE 7: Creazione database..."

# Genera password sicura
DB_PASSWORD=$(openssl rand -base64 24 | tr -dc 'a-zA-Z0-9' | head -c 24)

# Crea ruolo e database
sudo -u postgres psql << EOF
CREATE ROLE $DB_USER WITH LOGIN PASSWORD '$DB_PASSWORD';
CREATE DATABASE $DB_NAME OWNER $DB_USER;
GRANT ALL PRIVILEGES ON DATABASE $DB_NAME TO $DB_USER;
EOF

log_success "Database PostgreSQL creato"

# ============================================
# FASE 8: CONFIGURAZIONE AMBIENTE
# ============================================
log_info "FASE 8: Configurazione ambiente..."

cd /opt/acs

# Crea file .env
cp .env.example .env 2>/dev/null || true

# Aggiorna configurazioni database
sed -i "s/DB_DATABASE=.*/DB_DATABASE=$DB_NAME/" .env
sed -i "s/DB_USERNAME=.*/DB_USERNAME=$DB_USER/" .env
sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=$DB_PASSWORD/" .env
sed -i "s/DB_HOST=.*/DB_HOST=127.0.0.1/" .env

# Ripristina configurazioni custom dal backup
if [ -f "$BACKUP_DIR/.env.backup" ]; then
    # Estrai e ripristina solo alcune chiavi importanti
    for key in APP_URL APP_NAME MAIL_HOST MAIL_PORT MAIL_USERNAME MAIL_PASSWORD; do
        value=$(grep "^$key=" "$BACKUP_DIR/.env.backup" | cut -d'=' -f2-)
        if [ -n "$value" ]; then
            sed -i "s|^$key=.*|$key=$value|" .env
        fi
    done
    log_success "Configurazioni custom ripristinate"
fi

log_success "File .env configurato"

# ============================================
# FASE 9: INSTALLAZIONE DIPENDENZE
# ============================================
log_info "FASE 9: Installazione dipendenze..."

# Composer
sudo -u acs composer install --no-dev --optimize-autoloader
log_success "Dipendenze PHP installate"

# NPM
sudo -u acs npm install
sudo -u acs npm run build
log_success "Dipendenze Node.js installate e build completata"

# ============================================
# FASE 10: SETUP LARAVEL
# ============================================
log_info "FASE 10: Setup Laravel..."

# Genera chiave applicazione
sudo -u acs php artisan key:generate --force

# Migrazioni e seed
sudo -u acs php artisan migrate --force
sudo -u acs php artisan db:seed --force

# Cache configurazioni
sudo -u acs php artisan config:cache
sudo -u acs php artisan route:cache
sudo -u acs php artisan view:cache

# Storage link
sudo -u acs php artisan storage:link 2>/dev/null || true

log_success "Laravel configurato"

# ============================================
# FASE 11: CREAZIONE TENANT DEFAULT
# ============================================
log_info "FASE 11: Verifica tenant default..."

sudo -u acs php artisan tinker --execute="
\$tenant = App\Models\Tenant::firstOrCreate(
    ['slug' => 'default'],
    [
        'name' => 'Default Tenant',
        'domain' => null,
        'is_active' => true
    ]
);

\$user = App\Models\User::first();
if (\$user && !\$user->tenant_id) {
    \$user->tenant_id = \$tenant->id;
    \$user->save();
    echo 'Admin user associato al tenant default';
} else {
    echo 'Tenant e utente già configurati';
}
"

log_success "Tenant default verificato"

# ============================================
# FASE 12: CONFIGURAZIONE SERVIZI
# ============================================
log_info "FASE 12: Configurazione servizi..."

# Configurazione Nginx
cat > /etc/nginx/sites-available/acs << 'NGINXCONF'
server {
    listen 80;
    server_name _;
    root /opt/acs/public;
    index index.php;

    client_max_body_size 100M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINXCONF

ln -sf /etc/nginx/sites-available/acs /etc/nginx/sites-enabled/acs
rm -f /etc/nginx/sites-enabled/default
log_success "Nginx configurato"

# Configurazione Supervisor per Horizon
cat > /etc/supervisor/conf.d/acs-horizon.conf << 'SUPCONF'
[program:acs-horizon]
process_name=%(program_name)s
command=php /opt/acs/artisan horizon
autostart=true
autorestart=true
user=acs
redirect_stderr=true
stdout_logfile=/opt/acs/storage/logs/horizon.log
stopwaitsecs=3600
SUPCONF

log_success "Supervisor configurato"

# ============================================
# FASE 13: AVVIO SERVIZI
# ============================================
log_info "FASE 13: Avvio servizi..."

# Redis
systemctl start redis-server 2>/dev/null || systemctl start redis 2>/dev/null || true
systemctl enable redis-server 2>/dev/null || systemctl enable redis 2>/dev/null || true

# PHP-FPM
systemctl start php8.3-fpm
systemctl enable php8.3-fpm

# Nginx
nginx -t && systemctl start nginx
systemctl enable nginx

# Supervisor
supervisorctl reread
supervisorctl update
supervisorctl start all

# Mosquitto (opzionale, non blocca se fallisce)
systemctl start mosquitto 2>/dev/null || log_warning "Mosquitto non avviato (opzionale)"

log_success "Servizi avviati"

# ============================================
# COMPLETATO
# ============================================
echo ""
echo "=========================================="
echo -e "${GREEN}   REINSTALLAZIONE COMPLETATA!${NC}"
echo "=========================================="
echo ""
echo "Credenziali Database:"
echo "  - Database: $DB_NAME"
echo "  - Utente: $DB_USER"
echo "  - Password: $DB_PASSWORD"
echo ""
echo "Credenziali Admin:"
echo "  - Email: admin@acs.local"
echo "  - Password: password"
echo ""
echo "Backup salvato in: $BACKUP_DIR"
echo ""
echo "Verifica con:"
echo "  sudo supervisorctl status"
echo "  systemctl status nginx"
echo "  curl -I http://localhost"
echo ""
log_success "Installazione completata con successo!"
