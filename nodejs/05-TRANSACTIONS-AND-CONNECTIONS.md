# Node.js ISP System - Transaction Management & Database Connection Pooling

## Overview
This document explains transaction management, connection pooling, and best practices for maintaining data consistency across main and RADIUS databases.

---

## Database Connection Setup

### 1. Connection Pool Configuration

```javascript
// config/database.js
const mysql = require('mysql2/promise');
require('dotenv').config();

const mainPool = mysql.createPool({
  host: process.env.DB_HOST || 'localhost',
  port: process.env.DB_PORT || 3306,
  user: process.env.DB_USER || 'root',
  password: process.env.DB_PASSWORD || '',
  database: process.env.DB_NAME || 'zisp',
  waitForConnections: true,
  connectionLimit: 10, // Number of connections in pool
  queueLimit: 0, // Unlimited queue
  enableKeepAlive: true,
  keepAliveInitialDelayMs: 0,
});

const radiusPool = mysql.createPool({
  host: process.env.RADIUS_DB_HOST || 'localhost',
  port: process.env.RADIUS_DB_PORT || 3306,
  user: process.env.RADIUS_DB_USER || 'radius',
  password: process.env.RADIUS_DB_PASSWORD || 'radius',
  database: process.env.RADIUS_DB_NAME || 'radius',
  waitForConnections: true,
  connectionLimit: 5, // Fewer connections for RADIUS
  queueLimit: 0,
  enableKeepAlive: true,
  keepAliveInitialDelayMs: 0,
});

module.exports = {
  main: mainPool,
  radius: radiusPool,
};
```

### 2. Environment Configuration

```bash
# .env
DB_HOST=localhost
DB_PORT=3306
DB_USER=root
DB_PASSWORD=password
DB_NAME=zisp

RADIUS_DB_HOST=localhost
RADIUS_DB_PORT=3306
RADIUS_DB_USER=radius
RADIUS_DB_PASSWORD=radius
RADIUS_DB_NAME=radius
```

---

## Transaction Management Pattern

### 1. Basic Transaction Pattern

```javascript
// Utility function for transaction management
class DatabaseTransaction {
  /**
   * Execute operations in transaction across both databases
   */
  static async executeInTransaction(callback) {
    const mainConnection = await db.main.getConnection();
    const radiusConnection = await db.radius.getConnection();

    try {
      await mainConnection.beginTransaction();
      await radiusConnection.beginTransaction();

      // Execute callback with both connections
      const result = await callback({
        main: mainConnection,
        radius: radiusConnection,
      });

      await mainConnection.commit();
      await radiusConnection.commit();

      return {
        success: true,
        data: result,
      };

    } catch (error) {
      // Rollback both databases on any error
      try {
        await mainConnection.rollback();
      } catch (e) {
        console.error('Error rolling back main DB:', e.message);
      }

      try {
        await radiusConnection.rollback();
      } catch (e) {
        console.error('Error rolling back RADIUS DB:', e.message);
      }

      return {
        success: false,
        error: error.message,
      };

    } finally {
      mainConnection.release();
      radiusConnection.release();
    }
  }

  /**
   * Execute operations in main database only
   */
  static async executeInMainTransaction(callback) {
    const connection = await db.main.getConnection();

    try {
      await connection.beginTransaction();

      const result = await callback(connection);

      await connection.commit();

      return {
        success: true,
        data: result,
      };

    } catch (error) {
      try {
        await connection.rollback();
      } catch (e) {
        console.error('Rollback error:', e.message);
      }

      return {
        success: false,
        error: error.message,
      };

    } finally {
      connection.release();
    }
  }

  /**
   * Execute operations in RADIUS database only
   */
  static async executeInRadiusTransaction(callback) {
    const connection = await db.radius.getConnection();

    try {
      await connection.beginTransaction();

      const result = await callback(connection);

      await connection.commit();

      return {
        success: true,
        data: result,
      };

    } catch (error) {
      try {
        await connection.rollback();
      } catch (e) {
        console.error('Rollback error:', e.message);
      }

      return {
        success: false,
        error: error.message,
      };

    } finally {
      connection.release();
    }
  }
}

module.exports = DatabaseTransaction;
```

---

## User Creation Transaction Example

```javascript
// controllers/userController.js
const DatabaseTransaction = require('../database/DatabaseTransaction');

class UserController {
  async store(req, res) {
    try {
      const result = await DatabaseTransaction.executeInTransaction(async (connections) => {
        const { main, radius } = connections;

        // Step 1: Validate
        const { username, password, phone, type, package_id, expires_at } = req.body;
        const tenantId = req.user.tenant_id;
        const userId = req.user.id;

        if (!username || !password || !phone || !type) {
          throw new Error('Missing required fields');
        }

        // Step 2: Check if username exists
        const [existing] = await main.query(
          'SELECT id FROM network_users WHERE username = ? AND tenant_id = ?',
          [username, tenantId]
        );

        if (existing.length > 0) {
          throw new Error('Username already exists');
        }

        // Step 3: Generate account number
        const accountNumber = await this.generateAccountNumber(main, tenantId);

        // Step 4: Hash password
        const bcrypt = require('bcrypt');
        const hashedPassword = await bcrypt.hash(password, 10);
        const webPassword = await bcrypt.hash(password, 10);

        // Step 5: Create user in main DB
        const [userResult] = await main.query(
          `INSERT INTO network_users 
          (account_number, full_name, username, password, web_password, phone, type, 
           package_id, status, expires_at, created_by, tenant_id, created_at, updated_at)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())`,
          [
            accountNumber,
            username,
            username,
            password, // Plain for RADIUS
            webPassword,
            phone,
            type,
            package_id || null,
            'active',
            expires_at ? new Date(expires_at) : null,
            userId,
            tenantId,
          ]
        );

        const newUserId = userResult.insertId;

        // Step 6: Sync with RADIUS
        // Create password entry
        await radius.query(
          `INSERT INTO radcheck (username, attribute, op, value)
           VALUES (?, ?, ?, ?)`,
          [username, 'Cleartext-Password', ':=', password]
        );

        // Get and apply package
        if (package_id) {
          const [packages] = await main.query(
            'SELECT * FROM packages WHERE id = ?',
            [package_id]
          );

          if (packages.length > 0) {
            const pkg = packages[0];

            // Rate limit
            await radius.query(
              `INSERT INTO radreply (username, attribute, op, value)
               VALUES (?, ?, ?, ?)`,
              [username, 'Mikrotik-Rate-Limit', ':=', 
               `${pkg.upload_speed}M/${pkg.download_speed}M`]
            );

            // Device limit
            await radius.query(
              `INSERT INTO radreply (username, attribute, op, value)
               VALUES (?, ?, ?, ?)`,
              [username, 'Simultaneous-Use', ':=', String(pkg.device_limit || 1)]
            );

            // Expiration
            if (expires_at) {
              const formatted = this.formatRadiusDate(new Date(expires_at));
              await radius.query(
                `INSERT INTO radcheck (username, attribute, op, value)
                 VALUES (?, ?, ?, ?)`,
                [username, 'Expiration', ':=', formatted]
              );
            }

            // Group for non-hotspot
            if (type !== 'hotspot') {
              await radius.query(
                `INSERT INTO radusergroup (username, groupname, priority)
                 VALUES (?, ?, ?)`,
                [username, pkg.name || 'default', 1]
              );
            }
          }
        }

        return {
          userId: newUserId,
          accountNumber,
          username,
        };
      });

      if (result.success) {
        res.status(201).json({
          success: true,
          message: 'User created successfully',
          user: result.data,
        });
      } else {
        res.status(400).json({
          success: false,
          message: result.error,
        });
      }

    } catch (error) {
      console.error('Unexpected error:', error.message);
      res.status(500).json({
        success: false,
        message: 'Internal server error',
      });
    }
  }

  async generateAccountNumber(connection, tenantId) {
    const [tenant] = await connection.query(
      'SELECT business_name, name FROM tenants WHERE id = ?',
      [tenantId]
    );

    let name = tenant?.[0]?.business_name || tenant?.[0]?.name || 'System';
    let prefix = name
      .replace(/[\sOIoi]/g, '')
      .substring(0, 2)
      .toUpperCase();

    if (prefix.length < 2) {
      prefix = prefix.padEnd(2, 'X');
    }
    if (!prefix || prefix === 'XX') {
      prefix = 'NU';
    }

    const [lastUser] = await connection.query(
      `SELECT account_number FROM network_users 
       WHERE account_number LIKE ? 
       ORDER BY LENGTH(account_number) DESC, account_number DESC 
       LIMIT 1`,
      [`${prefix}%`]
    );

    let nextNumber = 1;
    if (lastUser?.length > 0) {
      const numberPart = lastUser[0].account_number.substring(prefix.length);
      if (!isNaN(numberPart)) {
        nextNumber = parseInt(numberPart) + 1;
      }
    }

    return prefix + String(nextNumber).padStart(3, '0');
  }

  formatRadiusDate(date) {
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                    'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const d = String(date.getDate()).padStart(2, '0');
    const M = months[date.getMonth()];
    const Y = date.getFullYear();
    const h = String(date.getHours()).padStart(2, '0');
    const i = String(date.getMinutes()).padStart(2, '0');
    const s = String(date.getSeconds()).padStart(2, '0');
    return `${d} ${M} ${Y} ${h}:${i}:${s}`;
  }
}

module.exports = new UserController();
```

---

## Mikrotik Deletion Transaction Example

```javascript
class MikrotikController {
  async forceDelete(req, res) {
    try {
      const result = await DatabaseTransaction.executeInTransaction(async (connections) => {
        const { main, radius } = connections;
        const { id } = req.params;
        const tenantId = req.user.tenant_id;

        // Step 1: Get router
        const [routers] = await main.query(
          'SELECT * FROM tenant_mikrotiks WHERE id = ? AND tenant_id = ?',
          [id, tenantId]
        );

        if (routers.length === 0) {
          throw new Error('Router not found');
        }

        const router = routers[0];

        // Step 2: Delete from main DB
        await main.query(
          'DELETE FROM tenant_mikrotiks WHERE id = ?',
          [id]
        );

        // Step 3: Remove NAS entry from RADIUS
        await radius.query(
          'DELETE FROM nas WHERE shortname = ?',
          [`mtk-${router.id}`]
        );

        return {
          routerId: router.id,
          routerName: router.name,
        };
      });

      if (result.success) {
        res.json({
          success: true,
          message: 'Router permanently deleted',
          router: result.data,
        });
      } else {
        res.status(400).json({
          success: false,
          message: result.error,
        });
      }

    } catch (error) {
      console.error('Unexpected error:', error.message);
      res.status(500).json({
        success: false,
        message: 'Internal server error',
      });
    }
  }
}
```

---

## Error Handling & Rollback

### Automatic Rollback Triggers

```javascript
// Any of these errors will trigger automatic rollback:

// 1. Query execution error
await connection.query('INVALID SQL'); // Throws error

// 2. Validation error
if (!username) throw new Error('Username required');

// 3. Business logic error
if (duplicate) throw new Error('User already exists');

// 4. External service error
try {
  await externalService.call();
} catch (error) {
  throw new Error(`External service failed: ${error.message}`);
}
```

### Error Message Best Practices

```javascript
// DO: Specific, actionable errors
throw new Error('Username already exists for this tenant');
throw new Error('IP address is in use by router ID 5');
throw new Error('Package not found - it may have been deleted');

// DON'T: Generic errors
throw new Error('Database error');
throw new Error('Invalid input');
throw new Error('Something went wrong');
```

---

## Connection Pool Monitoring

### Monitor Connection Health

```javascript
// middleware/monitorConnections.js
class ConnectionMonitor {
  static async logPoolStats() {
    setInterval(() => {
      console.log('Main DB Pool Stats:', {
        openConnections: db.main._pool?.length || 0,
        queueLength: db.main._queue?.length || 0,
      });

      console.log('RADIUS DB Pool Stats:', {
        openConnections: db.radius._pool?.length || 0,
        queueLength: db.radius._queue?.length || 0,
      });
    }, 60000); // Log every minute
  }

  static async testConnections() {
    try {
      const mainConn = await db.main.getConnection();
      const result = await mainConn.query('SELECT 1');
      mainConn.release();
      console.log('Main DB connection: OK');
    } catch (error) {
      console.error('Main DB connection: FAILED', error.message);
    }

    try {
      const radiusConn = await db.radius.getConnection();
      await radiusConn.query('SELECT 1');
      radiusConn.release();
      console.log('RADIUS DB connection: OK');
    } catch (error) {
      console.error('RADIUS DB connection: FAILED', error.message);
    }
  }
}

module.exports = ConnectionMonitor;
```

### Health Check Endpoint

```javascript
// routes/health.js
router.get('/health/db', async (req, res) => {
  try {
    const mainConn = await db.main.getConnection();
    const radiusConn = await db.radius.getConnection();

    const [mainResult] = await mainConn.query('SELECT 1 as status');
    const [radiusResult] = await radiusConn.query('SELECT 1 as status');

    mainConn.release();
    radiusConn.release();

    res.json({
      success: true,
      databases: {
        main: mainResult[0].status === 1 ? 'connected' : 'disconnected',
        radius: radiusResult[0].status === 1 ? 'connected' : 'disconnected',
      },
    });

  } catch (error) {
    res.status(500).json({
      success: false,
      error: error.message,
    });
  }
});
```

---

## Key Transaction Principles

### 1. **ACID Compliance**
- **Atomicity**: All operations succeed or all fail
- **Consistency**: Database never in inconsistent state
- **Isolation**: Concurrent transactions don't interfere
- **Durability**: Committed data persists

### 2. **Two-Phase Commit Pattern**
```
Phase 1: Prepare
├─ Validate all operations
├─ Acquire locks
└─ No actual changes yet

Phase 2: Commit
├─ Execute all changes
├─ Release locks
└─ Make visible to others

Rollback (on error):
├─ Undo all changes
├─ Release locks
└─ Return to initial state
```

### 3. **Connection Lifecycle**
```
1. Get from pool
2. Begin transaction
3. Execute queries
4. Commit (or rollback on error)
5. Release back to pool
```

### 4. **Error Recovery**
- All errors automatically rollback both databases
- Partial updates never occur
- Related data always stays synchronized

