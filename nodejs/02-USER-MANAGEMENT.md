# Node.js ISP System - User Management with RADIUS Sync

## Overview
This document covers the complete user lifecycle management in Node.js, including RADIUS database synchronization for authentication. Users can be of three types: **hotspot**, **pppoe**, or **static**.

## User Creation Flow

### 1. User Creation Process

```javascript
// controllers/userController.js
const db = require('../config/database');
const { v4: uuidv4 } = require('uuid');
const bcrypt = require('bcrypt');

class UserController {
  /**
   * Store a new network user with RADIUS sync
   * Uses transaction to ensure atomicity
   */
  async store(req, res) {
    const connection = await db.main.getConnection();
    const radiusConnection = await db.radius.getConnection();
    
    try {
      await connection.beginTransaction();
      await radiusConnection.beginTransaction();
      
      const {
        full_name,
        username,
        password,
        phone,
        location,
        type, // 'hotspot', 'pppoe', 'static'
        package_id,
        hotspot_package_id,
        expires_at,
        mac_address,
      } = req.body;

      const tenantId = req.user.tenant_id;
      const userId = req.user.id;

      // Validation
      if (!username || !phone || !type) {
        throw new Error('Missing required fields: username, phone, type');
      }

      if (!['hotspot', 'pppoe', 'static'].includes(type)) {
        throw new Error('Invalid user type');
      }

      // Check if username already exists
      const existingUser = await connection.query(
        'SELECT id FROM network_users WHERE username = ? AND tenant_id = ?',
        [username, tenantId]
      );

      if (existingUser.length > 0) {
        throw new Error('Username already exists');
      }

      // Generate account number
      const accountNumber = await this.generateAccountNumber(connection, tenantId);

      // Hash password
      const hashedPassword = await bcrypt.hash(password, 10);
      const webPassword = await bcrypt.hash(password, 10);

      // Create user in main database
      const [userResult] = await connection.query(
        `INSERT INTO network_users 
        (account_number, full_name, username, password, web_password, phone, location, 
         type, package_id, hotspot_package_id, status, registered_at, 
         expires_at, mac_address, created_by, tenant_id, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())`,
        [
          accountNumber,
          full_name || username,
          username,
          password, // Store plain password for RADIUS
          webPassword,
          phone,
          location || null,
          type,
          package_id || null,
          hotspot_package_id || null,
          'active',
          new Date(),
          expires_at ? new Date(expires_at) : null,
          mac_address || null,
          userId,
          tenantId,
        ]
      );

      const newUserId = userResult.insertId;

      // Sync with RADIUS - Create radcheck entry (password)
      await radiusConnection.query(
        `INSERT INTO radcheck (username, attribute, op, value)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE value = VALUES(value)`,
        [username, 'Cleartext-Password', ':=', password]
      );

      // Get package and create RADIUS reply entries
      const packageInfo = type === 'hotspot' && hotspot_package_id
        ? await this.getPackage(connection, hotspot_package_id)
        : await this.getPackage(connection, package_id);

      if (packageInfo) {
        // Rate limit
        const rateValue = `${packageInfo.upload_speed}M/${packageInfo.download_speed}M`;
        await radiusConnection.query(
          `INSERT INTO radreply (username, attribute, op, value)
           VALUES (?, ?, ?, ?)
           ON DUPLICATE KEY UPDATE value = VALUES(value)`,
          [username, 'Mikrotik-Rate-Limit', ':=', rateValue]
        );

        // Simultaneous use (device limit)
        const deviceLimit = packageInfo.device_limit || 1;
        await radiusConnection.query(
          `INSERT INTO radreply (username, attribute, op, value)
           VALUES (?, ?, ?, ?)
           ON DUPLICATE KEY UPDATE value = VALUES(value)`,
          [username, 'Simultaneous-Use', ':=', String(deviceLimit)]
        );

        // Session timeout for hotspot users
        if (type === 'hotspot') {
          const seconds = this.calculateSessionTimeout(packageInfo);
          if (seconds > 0) {
            await radiusConnection.query(
              `INSERT INTO radreply (username, attribute, op, value)
               VALUES (?, ?, ?, ?)
               ON DUPLICATE KEY UPDATE value = VALUES(value)`,
              [username, 'Session-Timeout', ':=', String(seconds)]
            );
          }
        }

        // Expiration date for all users
        if (expires_at) {
          const formattedDate = this.formatRadiusDate(new Date(expires_at));
          await radiusConnection.query(
            `INSERT INTO radcheck (username, attribute, op, value)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value)`,
            [username, 'Expiration', ':=', formattedDate]
          );
        }

        // MAC address authentication for hotspot
        if (type === 'hotspot' && mac_address) {
          await radiusConnection.query(
            `INSERT INTO radcheck (username, attribute, op, value)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value)`,
            [mac_address, 'Cleartext-Password', ':=', mac_address]
          );
        }

        // User group assignment (non-hotspot only)
        if (type !== 'hotspot') {
          await radiusConnection.query(
            `INSERT INTO radusergroup (username, groupname, priority)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE priority = VALUES(priority)`,
            [username, packageInfo.name || 'default', 1]
          );
        }
      }

      await connection.commit();
      await radiusConnection.commit();

      res.status(201).json({
        success: true,
        message: 'User created successfully',
        user: {
          id: newUserId,
          account_number: accountNumber,
          username,
          type,
          status: 'active',
        },
      });

    } catch (error) {
      await connection.rollback();
      await radiusConnection.rollback();
      console.error('User creation failed:', error.message);
      res.status(400).json({
        success: false,
        message: error.message,
      });
    } finally {
      connection.release();
      radiusConnection.release();
    }
  }

  /**
   * Generate unique account number
   * Format: XX### (2 letters + 3+ digits)
   */
  async generateAccountNumber(connection, tenantId) {
    const [tenant] = await connection.query(
      'SELECT business_name, name FROM tenants WHERE id = ?',
      [tenantId]
    );

    let name = tenant?.[0]?.business_name || tenant?.[0]?.name || 'System';

    // Remove spaces and O, I characters
    let prefix = name
      .replace(/[\sOIoi]/g, '')
      .substring(0, 2)
      .toUpperCase();

    // Fallback
    if (prefix.length < 2) {
      prefix = prefix.padEnd(2, 'X');
    }
    if (!prefix || prefix === 'XX') {
      prefix = 'NU';
    }

    // Find last account number with this prefix
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

  /**
   * Calculate session timeout in seconds
   */
  calculateSessionTimeout(packageInfo) {
    const value = packageInfo.duration_value || packageInfo.duration || 1;
    const unit = packageInfo.duration_unit || 'days';

    const conversions = {
      minutes: 60,
      hours: 3600,
      days: 86400,
      weeks: 604800,
      months: 2592000,
    };

    return value * (conversions[unit] || 86400);
  }

  /**
   * Format date for RADIUS (d M Y H:i:s)
   */
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

  /**
   * Get package details
   */
  async getPackage(connection, packageId) {
    if (!packageId) return null;
    const [result] = await connection.query(
      `SELECT * FROM packages WHERE id = ?`,
      [packageId]
    );
    return result?.[0] || null;
  }
}

module.exports = new UserController();
```

---

## User Update Flow

### 2. User Update Process

```javascript
  /**
   * Update an existing user and sync RADIUS
   */
  async update(req, res) {
    const { id } = req.params;
    const connection = await db.main.getConnection();
    const radiusConnection = await db.radius.getConnection();

    try {
      await connection.beginTransaction();
      await radiusConnection.beginTransaction();

      const tenantId = req.user.tenant_id;
      const {
        full_name,
        password,
        phone,
        location,
        package_id,
        hotspot_package_id,
        expires_at,
        mac_address,
        status,
      } = req.body;

      // Get current user
      const [users] = await connection.query(
        'SELECT * FROM network_users WHERE id = ? AND tenant_id = ?',
        [id, tenantId]
      );

      if (users.length === 0) {
        throw new Error('User not found');
      }

      const user = users[0];
      const oldUsername = user.username;
      const oldMacAddress = user.mac_address;

      // Prepare update data
      const updateData = {
        full_name: full_name || user.full_name,
        phone: phone || user.phone,
        location: location !== undefined ? location : user.location,
        mac_address: mac_address !== undefined ? mac_address : user.mac_address,
        status: status || user.status,
        expires_at: expires_at ? new Date(expires_at) : user.expires_at,
        updated_at: new Date(),
      };

      // Handle password update
      if (password) {
        const hashedPassword = await bcrypt.hash(password, 10);
        updateData.web_password = hashedPassword;
        // For RADIUS, we store plain text
        updateData.password = password;
      }

      // Handle package update
      if (package_id !== undefined) {
        updateData.package_id = package_id || null;
      }
      if (hotspot_package_id !== undefined) {
        updateData.hotspot_package_id = hotspot_package_id || null;
      }

      // Build update query
      let updateQuery = 'UPDATE network_users SET ';
      const updateValues = [];
      const updateFields = [];

      Object.entries(updateData).forEach(([key, value]) => {
        updateFields.push(`${key} = ?`);
        updateValues.push(value);
      });

      updateQuery += updateFields.join(', ') + ' WHERE id = ? AND tenant_id = ?';
      updateValues.push(id, tenantId);

      await connection.query(updateQuery, updateValues);

      // ===== SYNC RADIUS =====

      // Update password if changed
      if (password) {
        await radiusConnection.query(
          `INSERT INTO radcheck (username, attribute, op, value)
           VALUES (?, ?, ?, ?)
           ON DUPLICATE KEY UPDATE value = VALUES(value)`,
          [oldUsername, 'Cleartext-Password', ':=', password]
        );
      }

      // Get updated package
      const packageInfo = user.type === 'hotspot' && hotspot_package_id
        ? await this.getPackage(connection, hotspot_package_id)
        : await this.getPackage(connection, package_id || user.package_id);

      if (packageInfo) {
        // Update rate limit
        const rateValue = `${packageInfo.upload_speed}M/${packageInfo.download_speed}M`;
        await radiusConnection.query(
          `INSERT INTO radreply (username, attribute, op, value)
           VALUES (?, ?, ?, ?)
           ON DUPLICATE KEY UPDATE value = VALUES(value)`,
          [oldUsername, 'Mikrotik-Rate-Limit', ':=', rateValue]
        );

        // Update device limit
        const deviceLimit = packageInfo.device_limit || 1;
        await radiusConnection.query(
          `INSERT INTO radreply (username, attribute, op, value)
           VALUES (?, ?, ?, ?)
           ON DUPLICATE KEY UPDATE value = VALUES(value)`,
          [oldUsername, 'Simultaneous-Use', ':=', String(deviceLimit)]
        );

        // Handle session timeout (hotspot)
        if (user.type === 'hotspot') {
          const seconds = this.calculateSessionTimeout(packageInfo);
          if (seconds > 0) {
            await radiusConnection.query(
              `INSERT INTO radreply (username, attribute, op, value)
               VALUES (?, ?, ?, ?)
               ON DUPLICATE KEY UPDATE value = VALUES(value)`,
              [oldUsername, 'Session-Timeout', ':=', String(seconds)]
            );
          }
        } else {
          // Remove session timeout for non-hotspot
          await radiusConnection.query(
            'DELETE FROM radreply WHERE username = ? AND attribute = ?',
            [oldUsername, 'Session-Timeout']
          );
        }

        // Update expiration
        if (expires_at) {
          const formattedDate = this.formatRadiusDate(new Date(expires_at));
          await radiusConnection.query(
            `INSERT INTO radcheck (username, attribute, op, value)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value)`,
            [oldUsername, 'Expiration', ':=', formattedDate]
          );
        } else {
          await radiusConnection.query(
            'DELETE FROM radcheck WHERE username = ? AND attribute = ?',
            [oldUsername, 'Expiration']
          );
        }

        // Update MAC address auth
        if (user.type === 'hotspot') {
          if (mac_address) {
            await radiusConnection.query(
              `INSERT INTO radcheck (username, attribute, op, value)
               VALUES (?, ?, ?, ?)
               ON DUPLICATE KEY UPDATE value = VALUES(value)`,
              [mac_address, 'Cleartext-Password', ':=', mac_address]
            );
          }
          // Remove group assignment
          await radiusConnection.query(
            'DELETE FROM radusergroup WHERE username = ?',
            [oldUsername]
          );
        } else {
          // Update group assignment for non-hotspot
          await radiusConnection.query(
            `INSERT INTO radusergroup (username, groupname, priority)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE groupname = VALUES(groupname)`,
            [oldUsername, packageInfo.name || 'default', 1]
          );
        }
      }

      await connection.commit();
      await radiusConnection.commit();

      res.json({
        success: true,
        message: 'User updated successfully',
      });

    } catch (error) {
      await connection.rollback();
      await radiusConnection.rollback();
      console.error('User update failed:', error.message);
      res.status(400).json({
        success: false,
        message: error.message,
      });
    } finally {
      connection.release();
      radiusConnection.release();
    }
  }
```

---

## User Deletion Flow

### 3. User Deletion Process

```javascript
  /**
   * Delete a user and clean up RADIUS entries
   */
  async destroy(req, res) {
    const { id } = req.params;
    const connection = await db.main.getConnection();
    const radiusConnection = await db.radius.getConnection();

    try {
      await connection.beginTransaction();
      await radiusConnection.beginTransaction();

      const tenantId = req.user.tenant_id;

      // Get user
      const [users] = await connection.query(
        'SELECT username FROM network_users WHERE id = ? AND tenant_id = ?',
        [id, tenantId]
      );

      if (users.length === 0) {
        throw new Error('User not found');
      }

      const { username } = users[0];

      // Delete from main database
      await connection.query(
        'DELETE FROM network_users WHERE id = ? AND tenant_id = ?',
        [id, tenantId]
      );

      // ===== CLEAN UP RADIUS =====
      // Delete all RADIUS entries for this user
      await radiusConnection.query(
        'DELETE FROM radcheck WHERE username = ?',
        [username]
      );

      await radiusConnection.query(
        'DELETE FROM radreply WHERE username = ?',
        [username]
      );

      await radiusConnection.query(
        'DELETE FROM radusergroup WHERE username = ?',
        [username]
      );

      await connection.commit();
      await radiusConnection.commit();

      res.json({
        success: true,
        message: 'User deleted successfully',
      });

    } catch (error) {
      await connection.rollback();
      await radiusConnection.rollback();
      console.error('User deletion failed:', error.message);
      res.status(400).json({
        success: false,
        message: error.message,
      });
    } finally {
      connection.release();
      radiusConnection.release();
    }
  }
}
```

---

## Key Implementation Notes

### Transaction Management
- **Atomicity**: All operations use database transactions
- **Rollback**: If any RADIUS operation fails, entire transaction rolls back
- **Error Handling**: Clear error messages for debugging

### RADIUS Synchronization
- **Cleartext-Password**: Stored in `radcheck` for authentication
- **Mikrotik-Rate-Limit**: Format `UPLOADMbps/DOWNLOADMbps`
- **Simultaneous-Use**: Device/connection limit
- **Session-Timeout**: Duration in seconds (hotspot only)
- **Expiration**: Absolute user expiry date
- **User Groups**: Package-based group assignment (non-hotspot)

### Password Handling
- Store plain text in `password` field for RADIUS
- Hash password with bcrypt for web login (`web_password`)
- Update both simultaneously during changes

### MAC Address Sync
- Hotspot users: MAC address acts as second auth factor
- Create dual entry: one for username, one for MAC
- Automatic cleanup on user deletion

### Account Number Generation
- Unique across all tenants
- Format: 2-letter prefix + sequential numbers
- Automatically excludes letters O and I to avoid confusion

