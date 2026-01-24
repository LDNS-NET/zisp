# Node.js ISP System - Database Schema

## Overview
This document outlines the database schema required for the Node.js implementation of the ZISP ISP management system, specifically focusing on user management, package assignment, RADIUS authentication, and Mikrotik integration.

## Database Tables

### 1. Network Users Table (`network_users`)

```sql
CREATE TABLE network_users (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  account_number VARCHAR(50) UNIQUE NOT NULL,
  full_name VARCHAR(255),
  username VARCHAR(255) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  web_password VARCHAR(255),
  phone VARCHAR(15) NOT NULL,
  location VARCHAR(255),
  type ENUM('hotspot', 'pppoe', 'static') NOT NULL,
  package_id BIGINT,
  hotspot_package_id BIGINT,
  pending_package_id BIGINT,
  pending_hotspot_package_id BIGINT,
  pending_package_activation_at TIMESTAMP,
  status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
  registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  online BOOLEAN DEFAULT FALSE,
  expires_at TIMESTAMP,
  expiry_notified_at TIMESTAMP,
  expiry_warning_sent_at TIMESTAMP,
  mac_address VARCHAR(17),
  created_by BIGINT,
  tenant_id BIGINT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (package_id) REFERENCES packages(id),
  FOREIGN KEY (hotspot_package_id) REFERENCES tenant_hotspots(id),
  FOREIGN KEY (created_by) REFERENCES users(id),
  FOREIGN KEY (tenant_id) REFERENCES tenants(id),
  INDEX idx_tenant_id (tenant_id),
  INDEX idx_username (username),
  INDEX idx_account_number (account_number),
  INDEX idx_type (type)
);
```

### 2. Packages Table (`packages`)

```sql
CREATE TABLE packages (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  description TEXT,
  type ENUM('hotspot', 'pppoe', 'static') NOT NULL,
  duration_unit ENUM('minutes', 'hours', 'days', 'weeks', 'months') DEFAULT 'days',
  duration_value INT DEFAULT 1,
  upload_speed INT NOT NULL, -- in Mbps
  download_speed INT NOT NULL, -- in Mbps
  device_limit INT DEFAULT 1,
  price DECIMAL(10, 2),
  tenant_id BIGINT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (tenant_id) REFERENCES tenants(id),
  INDEX idx_tenant_id (tenant_id),
  INDEX idx_type (type)
);
```

### 3. RADIUS Tables (Using Separate Connection)

#### 3.1 Radcheck Table (`radcheck`)

```sql
CREATE TABLE radcheck (
  id INT PRIMARY KEY AUTO_INCREMENT,
  username VARCHAR(64) NOT NULL,
  attribute VARCHAR(64) NOT NULL,
  op VARCHAR(2) DEFAULT ':=',
  value VARCHAR(253),
  
  INDEX idx_username (username),
  UNIQUE KEY idx_username_attr (username, attribute)
);
```

#### 3.2 Radreply Table (`radreply`)

```sql
CREATE TABLE radreply (
  id INT PRIMARY KEY AUTO_INCREMENT,
  username VARCHAR(64) NOT NULL,
  attribute VARCHAR(64) NOT NULL,
  op VARCHAR(2) DEFAULT ':=',
  value VARCHAR(253),
  
  INDEX idx_username (username),
  UNIQUE KEY idx_username_attr (username, attribute)
);
```

#### 3.3 Radusergroup Table (`radusergroup`)

```sql
CREATE TABLE radusergroup (
  username VARCHAR(64) NOT NULL,
  groupname VARCHAR(64) NOT NULL,
  priority INT DEFAULT 1,
  
  UNIQUE KEY idx_username_groupname (username, groupname)
);
```

#### 3.4 NAS Table (`nas`)

```sql
CREATE TABLE nas (
  id INT PRIMARY KEY AUTO_INCREMENT,
  nasname VARCHAR(128) NOT NULL UNIQUE,
  shortname VARCHAR(32),
  type VARCHAR(30),
  ports INT,
  secret VARCHAR(60) NOT NULL,
  server VARCHAR(64),
  community VARCHAR(64),
  description VARCHAR(200),
  dynamic VARCHAR(1),
  
  INDEX idx_nasname (nasname)
);
```

### 4. Tenant Mikrotiks Table (`tenant_mikrotiks`)

```sql
CREATE TABLE tenant_mikrotiks (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  tenant_id BIGINT NOT NULL,
  name VARCHAR(255) NOT NULL,
  status ENUM('pending', 'online', 'offline') DEFAULT 'pending',
  api_port INT DEFAULT 8728,
  ssh_port INT DEFAULT 22,
  openvpn_profile_id BIGINT,
  router_username VARCHAR(255),
  router_password VARCHAR(255),
  api_username VARCHAR(255),
  api_password VARCHAR(255),
  connection_type ENUM('api', 'ssh', 'ovpn') DEFAULT 'api',
  last_seen_at TIMESTAMP,
  model VARCHAR(255),
  os_version VARCHAR(255),
  uptime BIGINT,
  cpu_usage DECIMAL(5, 2),
  memory_usage DECIMAL(5, 2),
  temperature DECIMAL(5, 2),
  notes TEXT,
  sync_token VARCHAR(64),
  wireguard_public_key VARCHAR(255),
  wireguard_allowed_ips VARCHAR(255),
  wireguard_address VARCHAR(15),
  wireguard_port INT,
  wireguard_status VARCHAR(50),
  wireguard_last_handshake TIMESTAMP,
  online BOOLEAN DEFAULT FALSE,
  cpu DECIMAL(5, 2),
  memory DECIMAL(5, 2),
  public_ip VARCHAR(15),
  winbox_port INT,
  online_since TIMESTAMP,
  created_by BIGINT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at TIMESTAMP,
  
  FOREIGN KEY (tenant_id) REFERENCES tenants(id),
  FOREIGN KEY (created_by) REFERENCES users(id),
  INDEX idx_tenant_id (tenant_id),
  INDEX idx_status (status)
);
```

## Connection Strategy

### Main Database Connection
- Used for: `network_users`, `packages`, `tenant_mikrotiks`, and other application tables
- Default MySQL/PostgreSQL connection

### RADIUS Database Connection
- Used for: `radcheck`, `radreply`, `radusergroup`, `nas`
- Separate connection configured in environment variables
- Can be on same or separate database server

### Example Connection Configuration

```javascript
// config/database.js
module.exports = {
  main: {
    host: process.env.DB_HOST || 'localhost',
    port: process.env.DB_PORT || 3306,
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASSWORD || '',
    database: process.env.DB_NAME || 'zisp',
  },
  
  radius: {
    host: process.env.RADIUS_DB_HOST || 'localhost',
    port: process.env.RADIUS_DB_PORT || 3306,
    user: process.env.RADIUS_DB_USER || 'radius',
    password: process.env.RADIUS_DB_PASSWORD || 'radius',
    database: process.env.RADIUS_DB_NAME || 'radius',
  },
};
```

## Key Relationships

1. **network_users → packages**: One-to-Many (user has one active package)
2. **network_users → tenant_hotspots**: One-to-Many (user has one active hotspot package)
3. **network_users → tenants**: Many-to-One (users belong to a tenant)
4. **tenant_mikrotiks → tenants**: Many-to-One (routers belong to a tenant)
5. **radcheck ↔ radreply ↔ radusergroup**: All linked via `username` field

## Indexes for Performance

Critical indexes for frequently queried fields:
- `network_users.tenant_id` - Tenant scoping
- `network_users.username` - User lookup during auth
- `network_users.account_number` - Account number searches
- `tenant_mikrotiks.tenant_id` - Tenant router filtering
- `tenant_mikrotiks.status` - Status filtering
- `radcheck.username` - Quick RADIUS lookups
- `nas.nasname` - Router identification

