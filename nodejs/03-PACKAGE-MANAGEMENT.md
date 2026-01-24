# Node.js ISP System - Package Management

## Overview
This document covers package creation, updates, and their relationship with user RADIUS synchronization.

## Package Structure

### Package Types

```javascript
// types/packages.ts
interface Package {
  id: number;
  name: string;
  description: string;
  type: 'hotspot' | 'pppoe' | 'static';
  duration_unit: 'minutes' | 'hours' | 'days' | 'weeks' | 'months';
  duration_value: number;
  upload_speed: number; // Mbps
  download_speed: number; // Mbps
  device_limit: number;
  price: number;
  tenant_id: number;
  created_at: Date;
  updated_at: Date;
}
```

---

## Package CRUD Operations

### 1. Create Package

```javascript
// controllers/packageController.js
class PackageController {
  /**
   * Create a new package
   */
  async store(req, res) {
    const connection = await db.main.getConnection();

    try {
      const {
        name,
        description,
        type, // 'hotspot', 'pppoe', 'static'
        duration_unit,
        duration_value,
        upload_speed,
        download_speed,
        device_limit,
        price,
      } = req.body;

      const tenantId = req.user.tenant_id;

      // Validation
      if (!name || !type || !upload_speed || !download_speed) {
        throw new Error('Missing required fields');
      }

      if (!['hotspot', 'pppoe', 'static'].includes(type)) {
        throw new Error('Invalid package type');
      }

      if (!['minutes', 'hours', 'days', 'weeks', 'months'].includes(duration_unit)) {
        throw new Error('Invalid duration unit');
      }

      // Check if package name is unique for this tenant
      const [existing] = await connection.query(
        'SELECT id FROM packages WHERE name = ? AND tenant_id = ? AND type = ?',
        [name, tenantId, type]
      );

      if (existing.length > 0) {
        throw new Error('Package with this name already exists for your tenant');
      }

      // Create package
      const [result] = await connection.query(
        `INSERT INTO packages 
        (name, description, type, duration_unit, duration_value, 
         upload_speed, download_speed, device_limit, price, tenant_id, 
         created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())`,
        [
          name,
          description || null,
          type,
          duration_unit || 'days',
          duration_value || 1,
          upload_speed,
          download_speed,
          device_limit || 1,
          price || 0,
          tenantId,
        ]
      );

      res.status(201).json({
        success: true,
        message: 'Package created successfully',
        package: {
          id: result.insertId,
          name,
          type,
          upload_speed,
          download_speed,
        },
      });

    } catch (error) {
      res.status(400).json({
        success: false,
        message: error.message,
      });
    } finally {
      connection.release();
    }
  }

  /**
   * Update an existing package
   * NOTE: Changes affect all users currently on this package
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
        name,
        description,
        duration_unit,
        duration_value,
        upload_speed,
        download_speed,
        device_limit,
        price,
      } = req.body;

      // Get current package
      const [packages] = await connection.query(
        'SELECT * FROM packages WHERE id = ? AND tenant_id = ?',
        [id, tenantId]
      );

      if (packages.length === 0) {
        throw new Error('Package not found');
      }

      const oldPackage = packages[0];

      // Update package
      const updateFields = [];
      const updateValues = [];

      if (name !== undefined) updateFields.push('name = ?'), updateValues.push(name);
      if (description !== undefined) updateFields.push('description = ?'), updateValues.push(description);
      if (duration_unit) updateFields.push('duration_unit = ?'), updateValues.push(duration_unit);
      if (duration_value !== undefined) updateFields.push('duration_value = ?'), updateValues.push(duration_value);
      if (upload_speed !== undefined) updateFields.push('upload_speed = ?'), updateValues.push(upload_speed);
      if (download_speed !== undefined) updateFields.push('download_speed = ?'), updateValues.push(download_speed);
      if (device_limit !== undefined) updateFields.push('device_limit = ?'), updateValues.push(device_limit);
      if (price !== undefined) updateFields.push('price = ?'), updateValues.push(price);

      updateFields.push('updated_at = ?');
      updateValues.push(new Date());
      updateValues.push(id);
      updateValues.push(tenantId);

      await connection.query(
        `UPDATE packages SET ${updateFields.join(', ')} WHERE id = ? AND tenant_id = ?`,
        updateValues
      );

      // ===== SYNC ALL AFFECTED USERS IN RADIUS =====
      // Get all users with this package
      const [users] = await connection.query(
        `SELECT username FROM network_users 
         WHERE (package_id = ? OR hotspot_package_id = ?) AND tenant_id = ?`,
        [id, id, tenantId]
      );

      const newRateValue = `${upload_speed || oldPackage.upload_speed}M/${download_speed || oldPackage.download_speed}M`;
      const newDeviceLimit = device_limit !== undefined ? device_limit : oldPackage.device_limit;

      for (const user of users) {
        // Update rate limit for all affected users
        await radiusConnection.query(
          `INSERT INTO radreply (username, attribute, op, value)
           VALUES (?, ?, ?, ?)
           ON DUPLICATE KEY UPDATE value = VALUES(value)`,
          [user.username, 'Mikrotik-Rate-Limit', ':=', newRateValue]
        );

        // Update device limit
        await radiusConnection.query(
          `INSERT INTO radreply (username, attribute, op, value)
           VALUES (?, ?, ?, ?)
           ON DUPLICATE KEY UPDATE value = VALUES(value)`,
          [user.username, 'Simultaneous-Use', ':=', String(newDeviceLimit)]
        );

        // Update session timeout for hotspot packages
        if (oldPackage.type === 'hotspot' && duration_value !== undefined) {
          const seconds = this.calculateSessionTimeout({
            duration_unit: duration_unit || oldPackage.duration_unit,
            duration_value: duration_value || oldPackage.duration_value,
          });
          if (seconds > 0) {
            await radiusConnection.query(
              `INSERT INTO radreply (username, attribute, op, value)
               VALUES (?, ?, ?, ?)
               ON DUPLICATE KEY UPDATE value = VALUES(value)`,
              [user.username, 'Session-Timeout', ':=', String(seconds)]
            );
          }
        }
      }

      await connection.commit();
      await radiusConnection.commit();

      res.json({
        success: true,
        message: 'Package updated successfully and synced to all affected users',
        affectedUsers: users.length,
      });

    } catch (error) {
      await connection.rollback();
      await radiusConnection.rollback();
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
   * Delete a package
   * NOTE: Cannot delete if users are assigned to it
   */
  async destroy(req, res) {
    const connection = await db.main.getConnection();

    try {
      const { id } = req.params;
      const tenantId = req.user.tenant_id;

      // Check if any users have this package
      const [users] = await connection.query(
        `SELECT COUNT(*) as count FROM network_users 
         WHERE (package_id = ? OR hotspot_package_id = ?) AND tenant_id = ?`,
        [id, id, tenantId]
      );

      if (users[0].count > 0) {
        throw new Error(`Cannot delete package: ${users[0].count} user(s) are assigned to it`);
      }

      // Delete package
      await connection.query(
        'DELETE FROM packages WHERE id = ? AND tenant_id = ?',
        [id, tenantId]
      );

      res.json({
        success: true,
        message: 'Package deleted successfully',
      });

    } catch (error) {
      res.status(400).json({
        success: false,
        message: error.message,
      });
    } finally {
      connection.release();
    }
  }

  /**
   * Get all packages for tenant
   */
  async index(req, res) {
    const connection = await db.main.getConnection();

    try {
      const tenantId = req.user.tenant_id;
      const type = req.query.type; // Optional filter by type

      let query = 'SELECT * FROM packages WHERE tenant_id = ?';
      const params = [tenantId];

      if (type && ['hotspot', 'pppoe', 'static'].includes(type)) {
        query += ' AND type = ?';
        params.push(type);
      }

      query += ' ORDER BY type, name';

      const [packages] = await connection.query(query, params);

      res.json({
        success: true,
        packages,
      });

    } catch (error) {
      res.status(400).json({
        success: false,
        message: error.message,
      });
    } finally {
      connection.release();
    }
  }

  /**
   * Assign package to user
   * This is called during user creation/update
   */
  async assignToUser(connection, radiusConnection, userId, packageId, userType) {
    if (!packageId) return;

    const [packages] = await connection.query(
      'SELECT * FROM packages WHERE id = ?',
      [packageId]
    );

    if (packages.length === 0) {
      throw new Error('Package not found');
    }

    const packageInfo = packages[0];

    // Get user
    const [users] = await connection.query(
      'SELECT username FROM network_users WHERE id = ?',
      [userId]
    );

    if (users.length === 0) {
      throw new Error('User not found');
    }

    const { username } = users[0];

    // Update rate limit
    const rateValue = `${packageInfo.upload_speed}M/${packageInfo.download_speed}M`;
    await radiusConnection.query(
      `INSERT INTO radreply (username, attribute, op, value)
       VALUES (?, ?, ?, ?)
       ON DUPLICATE KEY UPDATE value = VALUES(value)`,
      [username, 'Mikrotik-Rate-Limit', ':=', rateValue]
    );

    // Update device limit
    await radiusConnection.query(
      `INSERT INTO radreply (username, attribute, op, value)
       VALUES (?, ?, ?, ?)
       ON DUPLICATE KEY UPDATE value = VALUES(value)`,
      [username, 'Simultaneous-Use', ':=', String(packageInfo.device_limit || 1)]
    );

    // Session timeout for hotspot
    if (userType === 'hotspot') {
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

    // User group (non-hotspot)
    if (userType !== 'hotspot') {
      await radiusConnection.query(
        `INSERT INTO radusergroup (username, groupname, priority)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE groupname = VALUES(groupname)`,
        [username, packageInfo.name || 'default', 1]
      );
    }
  }

  /**
   * Calculate session timeout in seconds
   */
  calculateSessionTimeout(packageInfo) {
    const value = packageInfo.duration_value || 1;
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
}

module.exports = new PackageController();
```

---

## Package Impact on RADIUS

### Rate Limiting
- **Format**: `UPLOADMbps/DOWNLOADMbps` (e.g., `10M/20M`)
- **Attribute**: `Mikrotik-Rate-Limit`
- **Applied to**: All user types
- **When**: During user creation and package assignment

### Device Limit
- **Attribute**: `Simultaneous-Use`
- **Applied to**: All user types
- **Default**: 1 device
- **When**: During user creation and package assignment

### Session Duration (Hotspot Only)
- **Attribute**: `Session-Timeout`
- **Format**: Seconds
- **Applied to**: Hotspot users only
- **When**: During hotspot user creation or package change
- **Conversion**:
  - Minutes: `value * 60`
  - Hours: `value * 3600`
  - Days: `value * 86400`
  - Weeks: `value * 604800`
  - Months: `value * 2592000`

### User Groups (Non-Hotspot Only)
- **Attribute**: User group name
- **Applied to**: PPPoE and Static users
- **Default**: Package name or 'default'
- **When**: During non-hotspot user creation

---

## Key Implementation Notes

### Transaction Safety
- Package updates affect multiple users
- Use transactions to ensure consistency
- Rollback on any RADIUS operation failure

### Bulk User Updates
- When package is updated, all affected users are automatically synced
- This ensures no user experiences outdated package settings
- Performance consideration: Large packages may affect many users

### Package Assignment Logic
```javascript
// During user creation/update
if (type === 'hotspot') {
  assignPackage(hotspot_package_id, userType);
} else {
  assignPackage(package_id, userType);
}
```

### Package Constraints
- Cannot delete packages with assigned users
- Package name must be unique per tenant per type
- Type cannot be changed after creation

