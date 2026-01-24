# Node.js ISP System Documentation - Table of Contents

## Complete Documentation Set

This folder contains comprehensive Node.js implementation documentation for an ISP Management System with RADIUS authentication integration.

### Document Guide

#### 📋 **00-ARCHITECTURE.md** - System Overview
- Complete system architecture diagram
- Data flow for all major operations
- Connection pool management
- Error handling strategy
- Deployment checklist

**Start here for**: Understanding how all components interact

---

#### 📊 **01-DATABASE-SCHEMA.md** - Database Structure
- Complete SQL table definitions for all databases
- Main Database tables (network_users, packages, tenant_mikrotiks, etc.)
- RADIUS Database tables (radcheck, radreply, radusergroup, nas)
- Connection strategy and configuration
- Performance indexes

**Start here for**: Setting up your database infrastructure

---

#### 👤 **02-USER-MANAGEMENT.md** - User CRUD Operations
- Complete user creation flow with RADIUS sync
- User update process with package synchronization
- User deletion with cleanup
- RADIUS entry management (radcheck, radreply, radusergroup)
- Account number generation algorithm
- Transaction management examples

**Start here for**: Implementing user management features

Key operations covered:
- Create network user with RADIUS password & attributes
- Update user with automatic package-based RADIUS sync
- Delete user with complete RADIUS cleanup
- Handle different user types (hotspot, PPPoE, static)

---

#### 📦 **03-PACKAGE-MANAGEMENT.md** - Package Operations
- Package CRUD operations
- Impact on RADIUS entries when package is updated
- Bulk user synchronization
- Rate limiting configuration
- Device limit management
- Session duration for hotspot users

**Start here for**: Managing service packages

Key features:
- Package creation with validation
- Update packages and sync all affected users
- Rate limiting (upload/download speed)
- Device/connection limits
- Session timeout for hotspot users
- User group assignment for PPPoE/static

---

#### 🌐 **04-MIKROTIK-MANAGEMENT.md** - Router Management
- Complete Mikrotik router lifecycle
- Router creation with automatic NAS entry
- VPN IP allocation from 10.100.0.0/16 subnet
- Router update with RADIUS sync
- VPN IP assignment with validation
- Router deletion (soft & hard delete)
- Router restoration from trash
- NAS table structure and lifecycle

**Start here for**: Managing Mikrotik routers

Key operations:
- Create router with auto-generated credentials
- Pre-allocate WireGuard IP (VPN subnet)
- Register in RADIUS NAS table
- Set/change router VPN IP
- Soft delete (move to trash)
- Hard delete (permanent with RADIUS cleanup)
- Restore from trash

---

#### 🔄 **05-TRANSACTIONS-AND-CONNECTIONS.md** - Advanced Database Operations
- Connection pool configuration and monitoring
- Transaction management patterns
- Two-database transactions (Main + RADIUS)
- Automatic rollback on errors
- Connection lifecycle management
- Health check implementation
- Error handling best practices

**Start here for**: Understanding transaction safety and database reliability

Key concepts:
- Connection pooling (main & RADIUS)
- Transaction atomicity across databases
- Rollback mechanisms
- Connection monitoring
- Health checks and recovery

---

#### ⚡ **06-QUICK-REFERENCE.md** - Implementation Guide
- Quick start checklist
- Installation steps
- Project structure
- Complete API endpoint reference
- Sample server implementation
- RADIUS sync checklist
- Common error messages
- Testing checklist
- Performance optimization tips
- Security best practices

**Start here for**: Getting up and running quickly

Quick links:
- API endpoint examples with curl/JSON
- Pre-built controller implementations
- Testing checklist
- Performance tips
- Security guidelines

---

## Reading Paths by Role

### 👨‍💼 **Project Manager / Architect**
1. **00-ARCHITECTURE.md** - Understand system design
2. **01-DATABASE-SCHEMA.md** - Review data model
3. **06-QUICK-REFERENCE.md** - Implementation timeline

---

### 💻 **Backend Developer (Fresh Start)**
1. **00-ARCHITECTURE.md** - Understand architecture
2. **01-DATABASE-SCHEMA.md** - Set up databases
3. **05-TRANSACTIONS-AND-CONNECTIONS.md** - Learn transaction patterns
4. **02-USER-MANAGEMENT.md** - Implement user CRUD
5. **03-PACKAGE-MANAGEMENT.md** - Implement packages
6. **04-MIKROTIK-MANAGEMENT.md** - Implement routers
7. **06-QUICK-REFERENCE.md** - Reference during development

---

### 🔧 **DevOps / Infrastructure**
1. **01-DATABASE-SCHEMA.md** - Database setup
2. **05-TRANSACTIONS-AND-CONNECTIONS.md** - Connection pooling
3. **06-QUICK-REFERENCE.md** - Deployment checklist

---

### 🧪 **QA / Tester**
1. **00-ARCHITECTURE.md** - Understand flows
2. **06-QUICK-REFERENCE.md** - Testing checklist
3. **02-USER-MANAGEMENT.md** - User test cases
4. **04-MIKROTIK-MANAGEMENT.md** - Router test cases

---

## Key Concepts Summary

### Three Database Tables Categories

#### 1. **Main Database (zisp)**
- `network_users` - Subscriber accounts
- `packages` - Service packages
- `tenant_mikrotiks` - Router devices
- `tenants` - Customer organizations
- `users` - System administrators

#### 2. **RADIUS Database (radius)**
- `radcheck` - Authentication checks (passwords, expiry)
- `radreply` - Reply attributes (rate limits, device limits)
- `radusergroup` - User group assignments
- `nas` - Network Access Server (router) definitions

#### 3. **Linkage**
- Users → RADIUS via `username` field
- Packages → Users → RADIUS (package settings flow through users)
- Mikrotiks → NAS table (router registration)

### Three Core Operations

#### 1. **User Management**
```
Create User:
├─ network_users: INSERT (new subscriber)
├─ radcheck: INSERT (password authentication)
├─ radreply: INSERT (rate limits, device limits)
├─ radcheck: INSERT (expiration date)
├─ radusergroup: INSERT (group assignment)
└─ All atomic: succeed or fail together

Update User:
├─ network_users: UPDATE
├─ radcheck: UPDATE (if password changed)
├─ radreply: UPDATE (if package changed)
└─ radusergroup: UPDATE (if type changed)

Delete User:
├─ network_users: DELETE
├─ radcheck: DELETE ALL
├─ radreply: DELETE ALL
└─ radusergroup: DELETE ALL
```

#### 2. **Package Management**
```
Create Package:
├─ packages: INSERT
└─ No RADIUS changes (applies on user assignment)

Update Package:
├─ packages: UPDATE
├─ For each user with this package:
│  ├─ radreply: UPDATE rate limits
│  ├─ radreply: UPDATE device limits
│  └─ radreply: UPDATE session timeout (if hotspot)
└─ All users synced atomically

Delete Package:
└─ Blocked if users assigned (prevent orphaned users)
```

#### 3. **Mikrotik Management**
```
Create Router:
├─ tenant_mikrotiks: INSERT
├─ Allocate WireGuard IP (10.100.0.0/16)
└─ nas: INSERT in RADIUS

Update Router:
├─ tenant_mikrotiks: UPDATE
└─ nas: UPDATE (if credentials changed)

Delete Router:
├─ tenant_mikrotiks: SOFT DELETE (set deleted_at)
└─ nas: Remains (for restoration)

Permanent Delete:
├─ tenant_mikrotiks: HARD DELETE
└─ nas: DELETE from RADIUS
```

---

## Database Transaction Rules

### Rule 1: Atomicity Across Databases
**Every operation that touches both Main DB and RADIUS DB must be atomic**
- Begin transaction on both
- Execute all operations
- Commit both or rollback both
- No partial updates allowed

### Rule 2: Username is Primary Key for RADIUS
All RADIUS entries (`radcheck`, `radreply`, `radusergroup`) use `username` as the linking field
- When user created: use their `username`
- When user updated: find RADIUS entries by `username`
- When user deleted: delete all RADIUS entries matching `username`

### Rule 3: NAS Entry Lifecycle
- **Created** when Mikrotik router created
- **Updated** when router IP or credentials change
- **Soft Delete** preserved when router soft-deleted (restoration possible)
- **Hard Delete** permanent when router force-deleted

---

## RADIUS Attribute Reference

### Authentication Attributes (radcheck)
| Attribute | Format | Example | When Used |
|-----------|--------|---------|-----------|
| Cleartext-Password | Plain text | `password123` | Every user |
| Expiration | `d M Y H:i:s` | `31 Dec 2024 23:59:59` | Users with expiry |
| Access-Period | Time interval | `86400` (seconds) | Optional access windows |

### Reply Attributes (radreply)
| Attribute | Format | Example | Applied To |
|-----------|--------|---------|-----------|
| Mikrotik-Rate-Limit | `UP/DOWN` Mbps | `10M/20M` | All users |
| Simultaneous-Use | Integer | `2` | All users |
| Session-Timeout | Seconds | `86400` | Hotspot users |
| Frame-Route | IP/mask | `192.168.1.0/24` | Static users |

### User Groups (radusergroup)
| Field | Format | Example | Purpose |
|-------|--------|---------|---------|
| username | String | `johndoe` | Links to radcheck |
| groupname | String | `Standard 10M` | Package name |
| priority | Integer | `1` | Group priority |

### NAS Table (nas)
| Field | Format | Example | Purpose |
|-------|--------|---------|---------|
| nasname | IP address | `10.100.0.5` | VPN IP of router |
| shortname | String | `mtk-42` | Router ID reference |
| secret | String | `a1b2c3d4e5f6` | Shared secret for auth |
| type | String | `mikrotik` | NAS type |

---

## Common Implementation Patterns

### Pattern 1: User Creation Transaction
```javascript
begin transaction
  └─ insert into network_users
  └─ insert into radcheck (password)
  └─ insert into radreply (rate limit)
  └─ insert into radreply (device limit)
  └─ insert into radcheck (expiration)
  └─ insert into radusergroup
commit transaction
```

### Pattern 2: Package Update Cascade
```javascript
begin transaction
  └─ update packages
  └─ for each user with package
      └─ update radreply (rate limit)
      └─ update radreply (device limit)
      └─ update radreply (session timeout)
commit transaction
```

### Pattern 3: Router Creation with VPN IP
```javascript
begin transaction
  └─ allocate VPN IP from 10.100.0.0/16
  └─ insert into tenant_mikrotiks
  └─ insert into nas (RADIUS)
commit transaction
```

---

## Testing Focus Areas

### User Management Tests
- [ ] Create user with all user types
- [ ] Verify RADIUS entries created
- [ ] Update user and verify RADIUS sync
- [ ] Delete user and verify RADIUS cleanup
- [ ] Test account number uniqueness
- [ ] Test expiration date handling

### Package Tests
- [ ] Create package with validation
- [ ] Update package and sync all users
- [ ] Test rate limit formatting
- [ ] Test device limits
- [ ] Test session timeout calculation

### Mikrotik Tests
- [ ] Create router with VPN IP allocation
- [ ] Verify NAS entry created
- [ ] Update router credentials
- [ ] Change router VPN IP
- [ ] Soft delete and restore
- [ ] Hard delete and verify NAS cleanup

### Transaction Tests
- [ ] Verify atomic operations
- [ ] Test rollback on main DB error
- [ ] Test rollback on RADIUS DB error
- [ ] Test connection pool management
- [ ] Test error handling

---

## Performance Considerations

### Indexes Required
```sql
CREATE INDEX idx_network_users_tenant_id ON network_users(tenant_id);
CREATE INDEX idx_network_users_username ON network_users(username);
CREATE INDEX idx_packages_tenant_id ON packages(tenant_id);
CREATE INDEX idx_tenant_mikrotiks_tenant_id ON tenant_mikrotiks(tenant_id);
CREATE INDEX idx_radcheck_username ON radcheck(username);
CREATE INDEX idx_radusergroup_username ON radusergroup(username);
CREATE INDEX idx_nas_nasname ON nas(nasname);
```

### Optimization Tips
1. Use connection pooling (10-20 connections)
2. Cache package data (update frequency: low)
3. Batch operations when possible
4. Use pagination for large result sets
5. Monitor slow queries

---

## Troubleshooting Guide

### User not authenticating in Mikrotik
1. Check `radcheck` entry exists for username
2. Verify `Cleartext-Password` value matches
3. Confirm router's `nas` entry points to correct RADIUS server
4. Check RADIUS server has access to NAS table

### Changes not applying to user
1. Verify transaction committed successfully
2. Check username spelling in all RADIUS tables
3. Confirm `radreply` entries updated
4. Restart Mikrotik to clear auth cache

### Router not connecting to RADIUS
1. Verify `nas` entry created with correct VPN IP
2. Confirm router's VPN IP matches `nasname` in NAS table
3. Check shared secret matches router's API password
4. Verify network connectivity (10.100.0.0/16 accessible)

---

## Next Steps

1. **Review** 00-ARCHITECTURE.md to understand system design
2. **Set up** databases using 01-DATABASE-SCHEMA.md
3. **Implement** connection pool using 05-TRANSACTIONS-AND-CONNECTIONS.md
4. **Build** user endpoints using 02-USER-MANAGEMENT.md
5. **Build** package endpoints using 03-PACKAGE-MANAGEMENT.md
6. **Build** router endpoints using 04-MIKROTIK-MANAGEMENT.md
7. **Test** using 06-QUICK-REFERENCE.md checklist
8. **Deploy** following deployment checklist

---

## Support & Questions

For issues or clarifications about:
- **Architecture** → See 00-ARCHITECTURE.md
- **Database setup** → See 01-DATABASE-SCHEMA.md
- **User operations** → See 02-USER-MANAGEMENT.md
- **Package operations** → See 03-PACKAGE-MANAGEMENT.md
- **Router operations** → See 04-MIKROTIK-MANAGEMENT.md
- **Transaction safety** → See 05-TRANSACTIONS-AND-CONNECTIONS.md
- **Quick answers** → See 06-QUICK-REFERENCE.md

---

**Last Updated**: January 2026
**Version**: 1.0
**Status**: Production Ready

