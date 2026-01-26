# GenieACS Installation Guide for ZISP

This guide provides step-by-step instructions to install and configure GenieACS as a service on your Ubuntu server (`213.199.41.117`). This will enable the TR-069 device management features in your ZISP dashboard.

## 1. Install Dependencies
GenieACS requires Node.js, MongoDB, and Redis.

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install MongoDB 7.0 (Correct for Ubuntu 24.04)
sudo apt install -y gnupg curl
curl -fsSL https://www.mongodb.org/static/pgp/server-7.0.asc | sudo gpg -o /usr/share/keyrings/mongodb-server-7.0.gpg --dearmor
echo "deb [ arch=amd64,arm64 signed-by=/usr/share/keyrings/mongodb-server-7.0.gpg ] https://repo.mongodb.org/apt/ubuntu jammy/mongodb-org/7.0 multiverse" | sudo tee /etc/apt/sources.list.d/mongodb-org-7.0.list
sudo apt update
sudo apt install -y mongodb-org

# Install Redis
sudo apt install -y redis-server
sudo systemctl enable --now mongod redis-server

# Install Node.js (v20+ LTS recommended)
curl -sL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

## 2. Install GenieACS
Install the stable version of GenieACS globally via NPM.

```bash
sudo npm install -g genieacs@1.2.9
```

## 3. Create Systemd Service Files
GenieACS runs as four separate processes. You need to create a service file for each. 

### Important: Verify Binary Path
Before creating the files, check where GenieACS was installed by running:
```bash
which genieacs-nbi
```
If the output is `/usr/local/bin/genieacs-nbi`, update the `ExecStart` lines in the blocks below to match. If it's `/usr/bin/genieacs-nbi`, you are good to go.

### How to use the blocks below:
Simply **Copy the entire block** (from `sudo` to `EOF`) and paste it into your server terminal. It will automatically create the correct file with the right content.

### A. CWMP (Port 7547) - Device Communication
```bash
sudo tee /etc/systemd/system/genieacs-cwmp.service <<EOF
[Unit]
Description=GenieACS CWMP
After=network.target mongod.service redis-server.service

[Service]
User=root
Environment="GENIEACS_MONGODB_CONNECTION_URL=mongodb://127.0.0.1/genieacs"
Environment="GENIEACS_REDIS_CONNECTION_URL=redis://127.0.0.1"
ExecStart=$(which genieacs-cwmp)
Restart=always

[Install]
WantedBy=multi-user.target
EOF
```

### B. NBI (Port 7557) - API for ZISP
```bash
sudo tee /etc/systemd/system/genieacs-nbi.service <<EOF
[Unit]
Description=GenieACS NBI
After=network.target mongod.service redis-server.service

[Service]
User=root
Environment="GENIEACS_MONGODB_CONNECTION_URL=mongodb://127.0.0.1/genieacs"
Environment="GENIEACS_REDIS_CONNECTION_URL=redis://127.0.0.1"
ExecStart=$(which genieacs-nbi)
Restart=always

[Install]
WantedBy=multi-user.target
EOF
```

### C. FS (Port 7567) - File Server for Firmware
```bash
sudo tee /etc/systemd/system/genieacs-fs.service <<EOF
[Unit]
Description=GenieACS FS
After=network.target mongod.service redis-server.service

[Service]
User=root
Environment="GENIEACS_MONGODB_CONNECTION_URL=mongodb://127.0.0.1/genieacs"
Environment="GENIEACS_REDIS_CONNECTION_URL=redis://127.0.0.1"
ExecStart=$(which genieacs-fs)
Restart=always

[Install]
WantedBy=multi-user.target
EOF
```

### D. UI (Port 3000) - Management Dashboard
```bash
sudo tee /etc/systemd/system/genieacs-ui.service <<EOF
[Unit]
Description=GenieACS UI
After=network.target mongod.service redis-server.service

[Service]
User=root
Environment="GENIEACS_MONGODB_CONNECTION_URL=mongodb://127.0.0.1/genieacs"
Environment="GENIEACS_REDIS_CONNECTION_URL=redis://127.0.0.1"
ExecStart=$(which genieacs-ui)
Restart=always

[Install]
WantedBy=multi-user.target
EOF
```

## 4. Enable and Start Services
```bash
sudo systemctl daemon-reload

# Enable services to start on boot
sudo systemctl enable genieacs-cwmp genieacs-nbi genieacs-fs genieacs-ui

# Start services now
sudo systemctl start genieacs-cwmp genieacs-nbi genieacs-fs genieacs-ui
```

## 5. Configure Firewall
Ensure you open the management port for your routers to report in.

```bash
# Allow CWMP (CPEs to ACS)
sudo ufw allow 7547/tcp

# Allow NBI (API access - recommended to restrict this to localhost or ZISP server IP)
sudo ufw allow from 127.0.0.1 to any port 7557

# Allow FS (Firmware downloads)
sudo ufw allow 7567/tcp

# Optional: Allow GenieACS UI (Port 3000)
sudo ufw allow 3000/tcp
```

## 6. Verify Installation
Check the status of the processes:
```bash
systemctl status genieacs-*
```

You should now be able to access the GenieACS UI at `http://213.199.41.117:3000`.

---

## Final Step in ZISP
Once installed, clear your ZISP cache to establish the connection:
```bash
cd /var/www/zisp
php artisan optimize:clear
```
