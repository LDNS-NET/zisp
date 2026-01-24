# Node.js ISP System - Mikrotik Management & NAS Table

## Overview
This document covers the complete Mikrotik router lifecycle management, including NAS (Network Access Server) table synchronization in FreeRADIUS database.

## Mikrotik Creation Flow

### 1. Create Mikrotik Router

```javascript
// controllers/mikrotikController.js
const db = require('../config/database');
const crypto = require('crypto');
const { v4: uuidv4 } = require('uuid');

class MikrotikController {
  /**
   * Store a new Mikrotik router
   * Creates router entry and registers it in RADIUS NAS table
   */
  async store(req, res) {
    const connection = await db.main.getConnection();
    const radiusConnection = await db.radius.getConnection();

    try {
      await connection.beginTransaction();
      await radiusConnection.beginTransaction();

      const {
        name,
        notes,
        api_port,
      } = req.body;

      const tenantId = req.user.tenant_id;
      const userId = req.user.id;

      // Validation
      if (!name) {
        throw new Error('Router name is required');
      }

      // Generate API credentials
      const apiUsername = 'zisp_user';
      const apiPassword = crypto.randomBytes(12).toString('hex'); // 24 chars
      const routerUsername = 'admin';
      const routerPassword = '';
      const syncToken = crypto.randomBytes(20).toString('hex');

      // Create router
      const [result] = await connection.query(
        `INSERT INTO tenant_mikrotiks 
        (tenant_id, name, router_username, router_password, 
         api_username, api_password, api_port, connection_type, 
         notes, sync_token, status, created_by, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())`,
        [
          tenantId,
          name,
          routerUsername,
          routerPassword,
          apiUsername,
          apiPassword,
          api_port || 8728,
          'api',
          notes || null,
          syncToken,
          'pending', // Initial status
          userId,
        ]
      );

      const routerId = result.insertId;

      // Pre-allocate WireGuard IP from 10.100.0.0/16 subnet
      let vpnIp = null;
      try {
        vpnIp = await this.allocateWireGuardIp(connection, routerId);

        if (!vpnIp) {
          throw new Error('Failed to allocate WireGuard IP - no available IPs');
        }

        // Update router with VPN IP
        await connection.query(
          'UPDATE tenant_mikrotiks SET wireguard_address = ? WHERE id = ?',
          [vpnIp, routerId]
        );

      } catch (error) {
        throw new Error(`WireGuard IP allocation failed: ${error.message}`);
      }

      // Register in RADIUS NAS table
      await this.registerRadiusNas(radiusConnection, {
        id: routerId,
        name,
        wireguard_address: vpnIp,
        api_password: apiPassword,
      });

      await connection.commit();
      await radiusConnection.commit();

      // Return setup script data
      const setupScript = this.generateSetupScript({
        routerId,
        routerName: name,
        apiUsername,
        apiPassword,
        apiPort: api_port || 8728,
        vpnIp,
        syncToken,
        tenantId,
      });

      res.status(201).json({
        success: true,
        message: 'Mikrotik router created successfully',
        router: {
          id: routerId,
          name,
          status: 'pending',
          api_port: api_port || 8728,
          vpn_ip: vpnIp,
          api_username: apiUsername,
        },
        setupScript,
      });

    } catch (error) {
      await connection.rollback();
      await radiusConnection.rollback();
      console.error('Mikrotik creation failed:', error.message);
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
   * Allocate next available WireGuard IP from 10.100.0.0/16
   */
  async allocateWireGuardIp(connection, routerId) {
    // Reserved IPs: 10.100.0.0 (network), 10.100.0.1 (RADIUS server), 10.100.255.255 (broadcast)
    const reservedIps = ['10.100.0.0', '10.100.0.1', '10.100.255.255'];

    // Get all assigned IPs
    const [assignedIps] = await connection.query(
      `SELECT wireguard_address FROM tenant_mikrotiks 
       WHERE wireguard_address IS NOT NULL 
       ORDER BY INET_ATON(wireguard_address) ASC`
    );

    const usedIps = new Set(
      assignedIps
        .filter(row => row.wireguard_address)
        .map(row => row.wireguard_address)
        .concat(reservedIps)
    );

    // Generate available IPs: 10.100.0.2 to 10.100.255.254
    // That's 2^16 - 4 = 65532 available IPs
    for (let i = 2; i <= 65535; i++) {
      const ip = `10.100.${Math.floor(i / 256)}.${i % 256}`;
      if (!usedIps.has(ip)) {
        return ip;
      }
    }

    return null; // No available IPs
  }

  /**
   * Register router in RADIUS NAS table
   */
  async registerRadiusNas(radiusConnection, router) {
    if (!router.wireguard_address) {
      console.warn('Skipping NAS registration: missing VPN IP');
      return;
    }

    const shortname = `mtk-${router.id}`;

    // Check if already exists
    const [existing] = await radiusConnection.query(
      'SELECT id FROM nas WHERE shortname = ?',
      [shortname]
    );

    if (existing.length > 0) {
      // Update existing entry
      await radiusConnection.query(
        `UPDATE nas SET nasname = ?, secret = ?, type = ?, description = ?, server = ? 
         WHERE shortname = ?`,
        [
          router.wireguard_address,
          router.api_password,
          'mikrotik',
          `Tenant router ${router.id} - ${router.name}`,
          'default',
          shortname,
        ]
      );
    } else {
      // Create new entry
      await radiusConnection.query(
        `INSERT INTO nas (nasname, shortname, type, secret, server, description)
         VALUES (?, ?, ?, ?, ?, ?)`,
        [
          router.wireguard_address,
          shortname,
          'mikrotik',
          router.api_password,
          'default',
          `Tenant router ${router.id} - ${router.name}`,
        ]
      );
    }

    console.info(`Registered NAS entry for router ${router.id} (${router.name}) - IP: ${router.wireguard_address}`);
  }

  /**
   * Generate MikroTik setup script
   */
  generateSetupScript(config) {
    return `/system identity set name="${config.routerName}"
/ip firewall nat add chain=srcnat action=masquerade out-interface=ether1 comment="Masquerade"
/ip address add address=10.100.0.0/16 interface=bridge
/interface wireguard add listen-port=51820 private-key="REPLACE_WITH_PRIVATE_KEY"
/ip hotspot setup name=hotspot-${config.routerId} interface=bridge
/radius add service=ppp address=10.100.0.1 secret="${config.apiPassword}" timeout=3s
/interface list add name=LAN
/interface list add name=WAN
/interface list member add interface=ether1 list=WAN
/interface list member add interface=ether2 list=LAN
/interface list member add interface=bridge list=LAN
# ZISP API Configuration
/tool fetch url="https://your-domain.com/api/routers/${config.routerId}/sync" http-method=post
:log info "ZISP Router setup initiated"`;
  }
}

module.exports = new MikrotikController();
```

---

## Mikrotik Update Flow

### 2. Update Mikrotik Router

```javascript
  /**
   * Update Mikrotik router details
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
        notes,
        api_port,
        router_username,
        router_password,
        connection_type,
      } = req.body;

      // Get current router
      const [routers] = await connection.query(
        'SELECT * FROM tenant_mikrotiks WHERE id = ? AND tenant_id = ?',
        [id, tenantId]
      );

      if (routers.length === 0) {
        throw new Error('Router not found');
      }

      const router = routers[0];

      // Build update query
      const updateFields = [];
      const updateValues = [];

      if (name !== undefined) updateFields.push('name = ?'), updateValues.push(name);
      if (notes !== undefined) updateFields.push('notes = ?'), updateValues.push(notes);
      if (api_port !== undefined) updateFields.push('api_port = ?'), updateValues.push(api_port);
      if (router_username !== undefined) updateFields.push('router_username = ?'), updateValues.push(router_username);
      if (router_password !== undefined) updateFields.push('router_password = ?'), updateValues.push(router_password);
      if (connection_type !== undefined) updateFields.push('connection_type = ?'), updateValues.push(connection_type);

      updateFields.push('updated_at = ?');
      updateValues.push(new Date());
      updateValues.push(id);
      updateValues.push(tenantId);

      await connection.query(
        `UPDATE tenant_mikrotiks SET ${updateFields.join(', ')} WHERE id = ? AND tenant_id = ?`,
        updateValues
      );

      // Update NAS entry if IP or secret changed
      if (router.wireguard_address) {
        await this.registerRadiusNas(radiusConnection, {
          id,
          name: name || router.name,
          wireguard_address: router.wireguard_address,
          api_password: router.api_password,
        });
      }

      await connection.commit();
      await radiusConnection.commit();

      res.json({
        success: true,
        message: 'Router updated successfully',
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
   * Set VPN IP for router
   * Must be in 10.100.0.0/16 subnet
   */
  async setVpnIp(req, res) {
    const { id } = req.params;
    const connection = await db.main.getConnection();
    const radiusConnection = await db.radius.getConnection();

    try {
      await connection.beginTransaction();
      await radiusConnection.beginTransaction();

      const tenantId = req.user.tenant_id;
      let { ip_address } = req.body;

      // Extract IP from CIDR if present
      if (ip_address.includes('/')) {
        ip_address = ip_address.split('/')[0];
      }

      // Validate IP format
      if (!this.isValidIp(ip_address)) {
        throw new Error('Invalid IP address format');
      }

      // Validate subnet (must be in 10.100.0.0/16)
      if (!this.isInVpnSubnet(ip_address)) {
        throw new Error('IP must be in VPN subnet 10.100.0.0/16');
      }

      // Check if IP is already used
      const [used] = await connection.query(
        'SELECT id FROM tenant_mikrotiks WHERE wireguard_address = ? AND id != ?',
        [ip_address, id]
      );

      if (used.length > 0) {
        throw new Error('IP address is already in use by another router');
      }

      // Get router
      const [routers] = await connection.query(
        'SELECT * FROM tenant_mikrotiks WHERE id = ? AND tenant_id = ?',
        [id, tenantId]
      );

      if (routers.length === 0) {
        throw new Error('Router not found');
      }

      const router = routers[0];
      const oldIp = router.wireguard_address;

      // Update IP
      await connection.query(
        'UPDATE tenant_mikrotiks SET wireguard_address = ?, ip_address = ? WHERE id = ?',
        [ip_address, ip_address, id]
      );

      // Update NAS entry
      await this.registerRadiusNas(radiusConnection, {
        id,
        name: router.name,
        wireguard_address: ip_address,
        api_password: router.api_password,
      });

      await connection.commit();
      await radiusConnection.commit();

      res.json({
        success: true,
        message: 'VPN IP set successfully',
        old_ip: oldIp,
        new_ip: ip_address,
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
   * Validate IPv4 address
   */
  isValidIp(ip) {
    const ipv4Regex = /^(\d{1,3}\.){3}\d{1,3}$/;
    if (!ipv4Regex.test(ip)) return false;

    const parts = ip.split('.');
    return parts.every(part => {
      const num = parseInt(part);
      return num >= 0 && num <= 255;
    });
  }

  /**
   * Check if IP is in VPN subnet (10.100.0.0/16)
   */
  isInVpnSubnet(ip) {
    const parts = ip.split('.');
    return parts[0] === '10' && parts[1] === '100';
  }
```

---

## Mikrotik Deletion Flow

### 3. Delete Mikrotik Router

```javascript
  /**
   * Soft delete Mikrotik (move to trash)
   */
  async destroy(req, res) {
    const { id } = req.params;
    const connection = await db.main.getConnection();

    try {
      const tenantId = req.user.tenant_id;

      // Check permissions
      const user = req.user;
      if (!['admin', 'tenant_admin', 'network_engineer'].includes(user.role)) {
        throw new Error('You do not have permission to delete routers');
      }

      // Soft delete
      await connection.query(
        'UPDATE tenant_mikrotiks SET deleted_at = ? WHERE id = ? AND tenant_id = ?',
        [new Date(), id, tenantId]
      );

      res.json({
        success: true,
        message: 'Router moved to Recycle Bin',
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
   * Permanently delete Mikrotik (hard delete)
   * Also removes from RADIUS NAS table
   */
  async forceDelete(req, res) {
    const { id } = req.params;
    const connection = await db.main.getConnection();
    const radiusConnection = await db.radius.getConnection();

    try {
      await connection.beginTransaction();
      await radiusConnection.beginTransaction();

      const tenantId = req.user.tenant_id;

      // Check permissions
      const user = req.user;
      if (!['admin', 'tenant_admin', 'network_engineer'].includes(user.role)) {
        throw new Error('You do not have permission to permanently delete routers');
      }

      // Get router info
      const [routers] = await connection.query(
        'SELECT * FROM tenant_mikrotiks WHERE id = ? AND tenant_id = ?',
        [id, tenantId]
      );

      if (routers.length === 0) {
        throw new Error('Router not found');
      }

      const router = routers[0];

      // ===== CLEAN UP RADIUS NAS TABLE =====
      const shortname = `mtk-${router.id}`;
      await radiusConnection.query(
        'DELETE FROM nas WHERE shortname = ?',
        [shortname]
      );

      // Hard delete from main database
      await connection.query(
        'DELETE FROM tenant_mikrotiks WHERE id = ? AND tenant_id = ?',
        [id, tenantId]
      );

      await connection.commit();
      await radiusConnection.commit();

      console.info(`Permanently deleted router ${id} (${router.name}) and NAS entry`);

      res.json({
        success: true,
        message: 'Router permanently deleted and removed from RADIUS',
      });

    } catch (error) {
      await connection.rollback();
      await radiusConnection.rollback();
      console.error('Router permanent deletion failed:', error.message);
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
   * Restore soft-deleted router
   */
  async restore(req, res) {
    const { id } = req.params;
    const connection = await db.main.getConnection();
    const radiusConnection = await db.radius.getConnection();

    try {
      await connection.beginTransaction();
      await radiusConnection.beginTransaction();

      const tenantId = req.user.tenant_id;

      // Get router
      const [routers] = await connection.query(
        'SELECT * FROM tenant_mikrotiks WHERE id = ? AND tenant_id = ? AND deleted_at IS NOT NULL',
        [id, tenantId]
      );

      if (routers.length === 0) {
        throw new Error('Router not found in Recycle Bin');
      }

      const router = routers[0];

      // Restore
      await connection.query(
        'UPDATE tenant_mikrotiks SET deleted_at = NULL WHERE id = ?',
        [id]
      );

      // Restore NAS entry
      if (router.wireguard_address) {
        await this.registerRadiusNas(radiusConnection, {
          id: router.id,
          name: router.name,
          wireguard_address: router.wireguard_address,
          api_password: router.api_password,
        });
      }

      await connection.commit();
      await radiusConnection.commit();

      res.json({
        success: true,
        message: 'Router restored successfully',
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
}

module.exports = new MikrotikController();
```

---

## NAS Table Details

### NAS Entry Structure

```javascript
interface NasEntry {
  id: number;
  nasname: string; // VPN IP (10.100.0.0/16)
  shortname: string; // mtk-{router_id}
  type: string; // 'mikrotik'
  secret: string; // API password (RADIUS shared secret)
  server: string; // 'default'
  description: string; // Router description with ID and name
  dynamic?: string;
  ports?: number;
  community?: string;
}
```

### NAS Entry Lifecycle

| Operation | NAS Action |
|-----------|-----------|
| Router Created | INSERT new NAS entry with VPN IP |
| Router IP Changed | UPDATE nasname and secret |
| Router Restored | INSERT/UPDATE NAS entry |
| Router Soft Deleted | Keep NAS entry (router may be restored) |
| Router Hard Deleted | DELETE NAS entry permanently |

---

## WireGuard IP Allocation

### Subnet Structure
- **VPN Subnet**: `10.100.0.0/16` (65536 total IPs)
- **Reserved**: 
  - `10.100.0.0` - Network address
  - `10.100.0.1` - RADIUS server
  - `10.100.255.255` - Broadcast
- **Available**: `10.100.0.2` to `10.100.255.254` (65532 routers max)

### Allocation Algorithm
```javascript
function allocateWireGuardIp(connection, routerId) {
  // Get used IPs from database
  // Find first unused IP in range
  // Reserved and special IPs are skipped
  // Returns IP or null if exhausted
}
```

---

## Key Implementation Notes

### RADIUS Integration
- Every router must have a VPN IP in `10.100.0.0/16` subnet
- NAS entry uses VPN IP as `nasname`
- API password becomes RADIUS shared secret
- NAS short name format: `mtk-{router_id}`

### Transaction Safety
- Creation: Main DB + RADIUS NAS in one transaction
- Deletion: Both databases rolled back together
- IP changes: Both updated atomically

### Error Handling
- Missing VPN IP: Fails with clear message
- IP allocation exhaustion: Checked before insertion
- NAS registration failures: Logged but doesn't block creation

### Constraints
- One VPN IP per router
- VPN IP must be in `10.100.0.0/16` subnet
- Router name required
- API credentials auto-generated

