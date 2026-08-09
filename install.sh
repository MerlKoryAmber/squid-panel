#!/bin/bash
# CentOS 9 Stream Squid Proxy Manager installation script
set -euo pipefail

# Ensure we are in the project root
WORKDIR="/opt/squid-panel"

echo "Installing dependencies..."
# Update system packages and install build tools, Python 3.11+, pip, gcc, squid, krb5-workstation, samba-winbind-clients, sudo, nginx, openssl
yum -y update && yum -y install epel-release && yum -y install dnf-plugins-core
dnf config-manager --set-enabled powertools
rpm -ivh https://dl.fedoraproject.org/pub/epel/epel-release-latest-9.noarch.rpm

dnf -y install python3-devel python3-pip gcc squid krb5-workstation samba-winbind-clients sudo nginx openssl

# Install Python virtual environment and required packages
python3 -m pip install --no-cache-dir virtualenv flask sqlalchemy flask-login gunicorn psycopg2-binary psutil werkzeug

echo "Creating directory structure..."
mkdir -p "$WORKDIR/app" "$WORKDIR/static" "$WORKDIR/backups" "$WORKDIR/venv"

# Copy application files to /opt/squid-panel
cp -r $(pwd)/* "$WORKDIR"/

# Create and activate virtual environment, install requirements
cd "$WORKDIR"
virtenv -p python3 venv
source venv/bin/activate
cp requirements.txt .
pip install --no-cache-dir -r requirements.txt deactivate

# Initialize database and import existing config (if present)
echo "Initializing SQLite database..."
python run.py init_db

# Read existing Squid configuration, parse it into JSON and save as instance/config.json
existing_config=$(sudo -u squidmgr cat /etc/squid/squid.conf 2>/dev/null || true)
echo "$existing_config" | python app/utils/squid_parser.py import_existing_config

# Create self-signed SSL certificate for Nginx (valid 365 days)
if [ ! -f /etc/ssl/certs/nginx-selfsigned.crt ]
then
    openssl req -newkey rsa:2048 -nodes -sha256 -keyout /etc/ssl/private/nginx-selfsigned.key -outform PEM -subj "/C=US/ST=None/L=None/O=None/CN=localhost" -days 365 -x509 > /dev/null 2>&1
    openssl req -noout -text < /etc/ssl/certs/nginx-selfsigned.crt | grep "Subject:" && echo "Certificate created"
fi
ln -sf /etc/ssl/certs/nginx-selfsigned.crt /etc/nginx/conf.d/default.conf
ln -sf /etc/ssl/private/nginx-selfsigned.key /etc/nginx/conf.d/default.conf
sed -i 's|server {|# SSL Server (self-signed cert)\nserver {\n    listen 443 ssl;\n    ssl_certificate /etc/ssl/certs/nginx-selfsigned.crt;\n    ssl_certificate_key /etc/ssl/private/nginx-selfsigned.key;|' /etc/nginx/conf.d/default.conf
sed -i 's|index index.html index.htm;|listen 80 default_server; listen 443 ssl; server_name _;|' /etc/nginx/conf.d/default.conf
sed -i 's|server {|# HTTP Server (redirect)\nserver {\n    listen 80;\n    server_name localhost 127.0.0.1;\n    return 301 https://$host$request_uri;\n}|' /etc/nginx/conf.d/default.conf
sed -i 's|location / {|location / {\n    root   html;\n    index  index.html index.htm;\n}|' /etc/nginx/conf.d/default.conf

echo "Creating Squid Manager user..."
sudo bash -c "useradd --system --home /opt/squid-panel --shell /bin/bash squidmgr"
sudo bash -c "usermod -aG wheel,nginx,systemd-journal squash"
# Restrict sudoers to only allow the specific commands used by Nginx
sudo bash -c "/usr/sbin/visudo" <<'EOF'
%squidmgr ALL=(root) NOPASSWD: /bin/systemctl start squid,
               /bin/systemctl stop squid,
               /bin/systemctl restart squid,
               /bin/systemctl status squid,
               /usr/sbin/squid -k parse -f /etc/squid/squid.conf,
               /usr/sbin/squid -k reconfigure,
               /bin/cat /etc/squid/squid.conf,
               /bin/tee /etc/squid/squid.conf,
               /bin/chown squid:squid /etc/squid/squid.conf,
               /usr/bin/cp /etc/squid/squid.conf /opt/squid-panel/backups/
%EOF

echo "Configuring Nginx..."
sudo bash -c "/usr/local/bin/nginx -t && systemctl restart nginx"

# Set ownership of Squid configuration to squid:squid with 640 permissions
sudo chown squid:squid /etc/squid/squid.conf && sudo chmod 640 /etc/squid/squid.conf

echo "Installing systemd service for Gunicorn..."
cat > /usr/lib/systemd/system/squidmgr.service <<'EOF'
[Unit]
Description=Squid Proxy Manager (Gunicorn)
After=network.target
Wants=network-online.target
Requires=network-online.target
[Service]
User=squidmgr
Group=nginx
eExecStart=/opt/squid-panel/venv/bin/gunicorn --workers 4 --bind 127.0.0.1:8000 run:app
Restart=always
TimeoutSec=10
Environment=PATH=/opt/squid-panel/venv/bin/:$PATH
[Install]
WantedBy=multi-user.target
EOF
sudo systemctl daemon-reload && sudo systemctl enable --now squidmgr.service

echo "Squid Proxy Manager is installed at /opt/squid-panel"