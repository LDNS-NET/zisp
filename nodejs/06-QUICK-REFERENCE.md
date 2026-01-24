# Node.js ISP System - Implementation Quick Reference

## Quick Start Checklist

### Prerequisites
- Node.js >= 14.x
- MySQL >= 5.7 or MariaDB >= 10.3
- Two separate databases (or same server, different DBs):
  - Main database: `zisp`
  - RADIUS database: `radius`

### Installation

```bash
npm install express mysql2 bcrypt dotenv cors
npm install --save-dev nodemon

# Optional for production
npm install pm2 helmet express-validator
```

---

## Core Implementation Steps

### Step 1: Database Setup

```bash
# Create databases
mysql -u root -p << EOF
CREATE DATABASE zisp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE radius CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EOF

# Import tables (see 01-DATABASE-SCHEMA.md for SQL)
# Run all CREATE TABLE statements for both databases
```

### Step 2: Configuration Files

```javascript
// .env
NODE_ENV=development
PORT=3000

DB_HOST=localhost
DB_PORT=3306
DB_USER=root
DB_PASSWORD=your_password
DB_NAME=zisp

RADIUS_DB_HOST=localhost
RADIUS_DB_PORT=3306
RADIUS_DB_USER=radius
RADIUS_DB_PASSWORD=radius_password
RADIUS_DB_NAME=radius

JWT_SECRET=your_jwt_secret_key_here
```

### Step 3: Project Structure

```
project/
├── config/
│   └── database.js          # DB connection pools
├── controllers/
│   ├── userController.js
│   ├── packageController.js
│   ├── mikrotikController.js
│   └── authController.js
├── middleware/
│   ├── auth.js              # JWT authentication
│   └── errorHandler.js      # Global error handler
├── routes/
│   ├── users.js
│   ├── packages.js
│   ├── mikrotiks.js
│   └── health.js
├── utils/
│   ├── DatabaseTransaction.js
│   └── validators.js        # Input validation
├── server.js                # Main entry point
└── .env
```

---

## API Endpoints Reference

### User Management

#### Create User
```
POST /api/users
Content-Type: application/json

{
  "full_name": "John Doe",
  "username": "johndoe",
  "password": "secure_password",
  "phone": "254712345678",
  "location": "Nairobi",
  "type": "pppoe",           // hotspot, pppoe, or static
  "package_id": 5,
  "expires_at": "2024-12-31T23:59:59Z"
}

Response (201):
{
  "success": true,
  "message": "User created successfully",
  "user": {
    "id": 123,
    "account_number": "JD001",
    "username": "johndoe",
    "type": "pppoe",
    "status": "active"
  }
}
```

#### Update User
```
PUT /api/users/123
{
  "password": "new_password",
  "phone": "254714567890",
  "package_id": 6,
  "expires_at": "2025-06-30T23:59:59Z"
}

Response (200):
{
  "success": true,
  "message": "User updated successfully"
}
```

#### Delete User
```
DELETE /api/users/123

Response (200):
{
  "success": true,
  "message": "User deleted successfully"
}
```

---

### Package Management

#### Create Package
```
POST /api/packages
{
  "name": "Standard 10Mbps",
  "description": "10Mbps download, 5Mbps upload",
  "type": "pppoe",
  "upload_speed": 5,
  "download_speed": 10,
  "device_limit": 2,
  "duration_unit": "days",
  "duration_value": 30,
  "price": 999.99
}

Response (201):
{
  "success": true,
  "package": {
    "id": 5,
    "name": "Standard 10Mbps",
    "type": "pppoe"
  }
}
```

#### Update Package
```
PUT /api/packages/5
{
  "download_speed": 15,
  "upload_speed": 7,
  "device_limit": 3
}

Response (200):
{
  "success": true,
  "message": "Package updated successfully and synced to 42 affected users"
}
```

#### Get All Packages
```
GET /api/packages?type=pppoe

Response (200):
{
  "success": true,
  "packages": [
    {
      "id": 5,
      "name": "Standard 10Mbps",
      "type": "pppoe",
      "upload_speed": 5,
      "download_speed": 10,
      "device_limit": 2,
      "duration_unit": "days",
      "duration_value": 30,
      "price": 999.99
    }
  ]
}
```

---

### Mikrotik Management

#### Create Mikrotik
```
POST /api/mikrotiks
{
  "name": "Main Router",
  "notes": "Primary gateway router",
  "api_port": 8728
}

Response (201):
{
  "success": true,
  "message": "Mikrotik router created successfully",
  "router": {
    "id": 42,
    "name": "Main Router",
    "status": "pending",
    "api_port": 8728,
    "vpn_ip": "10.100.0.2",
    "api_username": "zisp_user"
  },
  "setupScript": "... RouterOS script to run on router ..."
}
```

#### Set Router VPN IP
```
POST /api/mikrotiks/42/vpn-ip
{
  "ip_address": "10.100.0.5"
}

Response (200):
{
  "success": true,
  "message": "VPN IP set successfully",
  "old_ip": "10.100.0.2",
  "new_ip": "10.100.0.5"
}
```

#### Update Mikrotik
```
PUT /api/mikrotiks/42
{
  "name": "Main Router - Updated",
  "notes": "New description",
  "api_port": 8729
}

Response (200):
{
  "success": true,
  "message": "Router updated successfully"
}
```

#### Delete Mikrotik (Soft)
```
DELETE /api/mikrotiks/42

Response (200):
{
  "success": true,
  "message": "Router moved to Recycle Bin"
}
```

#### Permanently Delete Mikrotik
```
POST /api/mikrotiks/42/force-delete
{
  "password": "user_current_password"
}

Response (200):
{
  "success": true,
  "message": "Router permanently deleted and removed from RADIUS"
}
```

---

## Sample Server Implementation

```javascript
// server.js
const express = require('express');
const cors = require('cors');
require('dotenv').config();

const userRoutes = require('./routes/users');
const packageRoutes = require('./routes/packages');
const mikrotikRoutes = require('./routes/mikrotiks');
const healthRoutes = require('./routes/health');

const app = express();

// Middleware
app.use(express.json());
app.use(cors());

// Routes
app.use('/api/users', userRoutes);
app.use('/api/packages', packageRoutes);
app.use('/api/mikrotiks', mikrotikRoutes);
app.use('/api/health', healthRoutes);

// Global error handler
app.use((err, req, res, next) => {
  console.error('Error:', err.message);
  res.status(err.status || 500).json({
    success: false,
    message: err.message || 'Internal server error',
  });
});

// 404 handler
app.use((req, res) => {
  res.status(404).json({
    success: false,
    message: 'Endpoint not found',
  });
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
  console.log(`Server running on port ${PORT}`);
  console.log(`Environment: ${process.env.NODE_ENV}`);
});
```

---

## RADIUS Synchronization Checklist

When implementing user operations, ensure:

### User Creation
- [ ] Insert into `network_users`
- [ ] Create `radcheck` entry (password)
- [ ] Create `radreply` entry (rate limit)
- [ ] Create `radreply` entry (simultaneous use)
- [ ] Create `radcheck` entry (expiration) if expires_at set
- [ ] Create `radusergroup` entry if non-hotspot
- [ ] Create `radcheck` entry (MAC auth) if hotspot with MAC

### User Update
- [ ] Update `network_users`
- [ ] Update `radcheck` (password) if changed
- [ ] Update `radreply` entries if package changed
- [ ] Update `radusergroup` if type changed
- [ ] Update `radcheck` (expiration) if expires_at changed
- [ ] Handle MAC address changes

### User Deletion
- [ ] Delete from `network_users`
- [ ] Delete all `radcheck` entries
- [ ] Delete all `radreply` entries
- [ ] Delete all `radusergroup` entries

### Mikrotik Creation
- [ ] Insert into `tenant_mikrotiks`
- [ ] Allocate VPN IP from 10.100.0.0/16
- [ ] Create `nas` entry in RADIUS database

### Mikrotik Deletion
- [ ] Delete from `tenant_mikrotiks`
- [ ] Delete corresponding `nas` entry

---

## Common Error Messages

```javascript
// User errors
'Username already exists'
'Invalid user type - must be hotspot, pppoe, or static'
'Missing required fields: username, phone, type'
'Package not found - it may have been deleted'

// Package errors
'Package with this name already exists for your tenant'
'Cannot delete package: X user(s) are assigned to it'
'Invalid package type'

// Mikrotik errors
'Router name is required'
'Failed to allocate WireGuard IP - no available IPs'
'IP address is already in use by another router'
'IP must be in VPN subnet 10.100.0.0/16'
'Invalid IP address format'

// Permission errors
'You do not have permission to delete routers'
'Access denied: user not found or unauthorized'
```

---

## Testing Checklist

```javascript
// User Creation Test
1. Create user with valid data
2. Verify RADIUS entries created
3. Create user with duplicate username - should fail
4. Create user without package - should succeed
5. Create user with MAC address - should create MAC auth entry

// User Update Test
1. Update password - verify RADIUS updated
2. Update package - verify RADIUS rate limit updated
3. Update expires_at - verify expiration entry synced
4. Update type (hotspot to pppoe) - verify group sync

// User Deletion Test
1. Delete user - verify removed from main DB
2. Verify all RADIUS entries deleted
3. Verify no orphaned RADIUS data

// Package Update Test
1. Update package - verify all users with that package synced
2. Count affected users in response
3. Verify rate limit updated for all users

// Mikrotik Creation Test
1. Create router - verify VPN IP allocated
2. Verify NAS entry created in RADIUS
3. Verify router gets sequential IPs

// Mikrotik Deletion Test
1. Soft delete - verify deleted_at set
2. Hard delete - verify NAS entry removed
3. Verify WireGuard IP freed for reuse
```

---

## Performance Optimization Tips

### 1. Connection Pooling
```javascript
connectionLimit: 10  // Increase for high traffic
waitForConnections: true
queueLimit: 0  // Unlimited queue
```

### 2. Query Optimization
```javascript
// Use indexes on frequently queried fields
CREATE INDEX idx_tenant_id ON network_users(tenant_id);
CREATE INDEX idx_username ON network_users(username);
CREATE INDEX idx_radcheck_username ON radcheck(username);
```

### 3. Batch Operations
```javascript
// For bulk user updates (e.g., expiry notifications)
// Use batch queries instead of individual updates
const userIds = [1, 2, 3, 4, 5];
await connection.query(
  'UPDATE network_users SET status = ? WHERE id IN (?)',
  ['suspended', userIds]
);
```

### 4. Caching
```javascript
// Cache package details to reduce queries
const packageCache = new Map();

async function getPackage(id) {
  if (packageCache.has(id)) {
    return packageCache.get(id);
  }
  const [packages] = await connection.query(
    'SELECT * FROM packages WHERE id = ?',
    [id]
  );
  packageCache.set(id, packages[0]);
  return packages[0];
}
```

---

## Security Best Practices

1. **Password Storage**
   - Hash web passwords with bcrypt (cost factor 10+)
   - Store plain password only for RADIUS (required for Cleartext-Password attribute)
   - Never log passwords

2. **API Security**
   - Use JWT authentication on all endpoints
   - Validate all user input
   - Implement rate limiting
   - Use HTTPS only

3. **Database Security**
   - Use strong passwords for both databases
   - Limit database user permissions to necessary tables
   - Enable query logging for debugging
   - Regular backups

4. **Transaction Security**
   - Always use transactions for multi-database operations
   - Properly handle connection pooling
   - Clean up connections even on errors

