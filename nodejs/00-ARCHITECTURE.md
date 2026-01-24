# Node.js ISP System - Architecture Overview

## System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                       Client Application                     │
│                   (Web Browser / Mobile App)                 │
└────────────────────────────┬────────────────────────────────┘
                             │ HTTP/HTTPS
                             ▼
┌─────────────────────────────────────────────────────────────┐
│                    Node.js API Server                        │
│  ┌──────────────────────────────────────────────────────┐   │
│  │              Express.js Middleware                   │   │
│  │  ┌─────────────────────────────────────────────┐     │   │
│  │  │ Authentication (JWT), Error Handler, Logger │     │   │
│  │  └─────────────────────────────────────────────┘     │   │
│  └──────────────────────────────────────────────────────┘   │
│                             │                                 │
│  ┌──────────────────────────┴──────────────────────────┐     │
│  │            Route Handlers & Controllers             │     │
│  │  ┌─────────────┬─────────────┬────────────────┐     │     │
│  │  │   Users     │  Packages   │   Mikrotiks    │     │     │
│  │  └─────────────┴─────────────┴────────────────┘     │     │
│  └─────────────────────────────────────────────────────┘     │
│                             │                                 │
│  ┌──────────────────────────┴──────────────────────────┐     │
│  │         Database Transaction Manager               │     │
│  │  (Handles atomicity across Main + RADIUS DBs)      │     │
│  └─────────────────────────────────────────────────────┘     │
└──────────────────┬──────────────────────────────────┬────────┘
                   │                                  │
                   │                                  │
        ┌──────────▼──────────┐          ┌───────────▼────────┐
        │   Main Database     │          │  RADIUS Database   │
        │  (MySQL/MariaDB)    │          │  (MySQL/MariaDB)   │
        │                     │          │                    │
        │  ┌──────────────┐   │          │  ┌──────────────┐  │
        │  │network_users │   │          │  │  radcheck    │  │
        │  └──────────────┘   │          │  ├──────────────┤  │
        │  ┌──────────────┐   │          │  │  radreply    │  │
        │  │  packages    │   │          │  ├──────────────┤  │
        │  └──────────────┘   │          │  │radusergroup  │  │
        │  ┌──────────────┐   │          │  ├──────────────┤  │
        │  │tenant_mikrot-│   │          │  │     nas      │  │
        │  │      iks     │   │          │  └──────────────┘  │
        │  └──────────────┘   │          │  ┌──────────────┐  │
        │  ┌──────────────┐   │          │  │  radacct     │  │
        │  │   tenants    │   │          │  │ (accounting) │  │
        │  └──────────────┘   │          │  └──────────────┘  │
        │  ┌──────────────┐   │          │                    │
        │  │    users     │   │          │                    │
        │  │  (system)    │   │          │                    │
        │  └──────────────┘   │          │                    │
        │                     │          │                    │
        └─────────┬───────────┘          └────────┬───────────┘
                  │                               │
                  └───────────┬───────────────────┘
                              │
                              ▼
                      ┌─────────────────┐
                      │  FreeRADIUS     │
                      │   Server        │
                      │                 │
                      │ ┌─────────────┐ │
                      │ │ Auth Module │ │
                      │ └─────────────┘ │
                      └────────┬────────┘
                               │
                    ┌──────────┴──────────┐
                    │                     │
                    ▼                     ▼
            ┌──────────────┐      ┌──────────────┐
            │  MikroTik    │      │  MikroTik    │
            │  Router #1   │      │  Router #2   │
            │              │      │              │
            │ - Hotspot    │      │ - PPPoE      │
            │ - PPPoE      │      │ - Static IP  │
            │ - Static IP  │      └──────────────┘
            └──────────────┘
```

---

## Data Flow Diagrams

### User Creation Flow

```
Client Request
    │
    ▼
POST /api/users
    │
    ▼
┌─────────────────────────────────────────┐
│  Validate Input                          │
│  - Check required fields                │
│  - Verify type (hotspot/pppoe/static)  │
└────────────┬────────────────────────────┘
             │
             ▼
    ┌────────────────────────────────────────────┐
    │ Generate Account Number                    │
    │ - Query last account_number for prefix    │
    │ - Generate unique sequence                 │
    └────────────┬─────────────────────────────┘
                 │
                 ▼
    ┌──────────────────────────────────────────────┐
    │ Begin Transaction (Main DB)                   │
    │ 1. Hash password for web_password           │
    │ 2. INSERT INTO network_users                │
    │ 3. Get package details                      │
    └────────────┬─────────────────────────────────┘
                 │
                 ▼
    ┌────────────────────────────────────────────────┐
    │ Begin Transaction (RADIUS DB)                   │
    │ 1. INSERT INTO radcheck (password)            │
    │ 2. INSERT INTO radreply (rate limit)          │
    │ 3. INSERT INTO radreply (device limit)        │
    │ 4. INSERT INTO radreply (session timeout)     │
    │ 5. INSERT INTO radcheck (expiration)          │
    │ 6. INSERT INTO radusergroup (if non-hotspot)  │
    └────────────┬─────────────────────────────────┘
                 │
                 ▼
    ┌─────────────────────────────────┐
    │ Commit Both Transactions         │
    │ - Main DB commit()              │
    │ - RADIUS DB commit()            │
    └────────────┬────────────────────┘
                 │
                 ▼
    ┌─────────────────────────────────┐
    │ Return Success Response          │
    │ - User ID                       │
    │ - Account Number                │
    │ - Username                      │
    └─────────────────────────────────┘
```

### Mikrotik Creation Flow

```
Client Request
    │
    ▼
POST /api/mikrotiks
    │
    ▼
┌──────────────────────────┐
│ Validate Input           │
│ - Router name required  │
│ - Check API port       │
└────────────┬────────────┘
             │
             ▼
┌──────────────────────────────────────────┐
│ Generate API Credentials                 │
│ - Generate api_password (random 24 char)|
│ - Generate sync_token (random 40 char)  │
└────────────┬─────────────────────────────┘
             │
             ▼
┌──────────────────────────────────────────┐
│ Allocate WireGuard IP (10.100.0.0/16)   │
│ 1. Query all assigned IPs               │
│ 2. Find first available IP              │
│ 3. Validate not in reserved set         │
└────────────┬─────────────────────────────┘
             │
             ▼
┌────────────────────────────────────────────┐
│ Begin Transaction (Main DB)                │
│ INSERT INTO tenant_mikrotiks              │
│ - Store router_name                      │
│ - Store api_credentials                  │
│ - Store wireguard_address (VPN IP)       │
└────────────┬──────────────────────────────┘
             │
             ▼
┌────────────────────────────────────────────┐
│ Begin Transaction (RADIUS DB)              │
│ INSERT INTO nas                            │
│ - nasname = wireguard_address (VPN IP)   │
│ - shortname = mtk-{router_id}             │
│ - secret = api_password (shared secret)  │
│ - type = 'mikrotik'                      │
└────────────┬──────────────────────────────┘
             │
             ▼
┌──────────────────────────┐
│ Commit Both Transactions │
└────────────┬─────────────┘
             │
             ▼
┌───────────────────────────────────────┐
│ Generate & Return Setup Script        │
│ - Router configuration commands      │
│ - WireGuard peer config              │
│ - RADIUS server settings             │
└───────────────────────────────────────┘
```

### User Deletion Flow

```
Client Request
    │
    ▼
DELETE /api/users/{id}
    │
    ▼
┌──────────────────────────┐
│ Get User Details         │
│ - Query network_users   │
│ - Verify tenant scoping │
└────────────┬─────────────┘
             │
             ▼
┌──────────────────────────────────────┐
│ Begin Transaction (Main DB)           │
│ DELETE FROM network_users WHERE id=? │
└────────────┬───────────────────────────┘
             │
             ▼
┌──────────────────────────────────────────┐
│ Begin Transaction (RADIUS DB)            │
│ 1. DELETE FROM radcheck                 │
│    WHERE username = ?                    │
│ 2. DELETE FROM radreply                 │
│    WHERE username = ?                    │
│ 3. DELETE FROM radusergroup             │
│    WHERE username = ?                    │
└────────────┬──────────────────────────────┘
             │
             ▼
┌──────────────────────────┐
│ Commit Both Transactions │
└────────────┬─────────────┘
             │
             ▼
┌─────────────────────────┐
│ Return Success Response │
└─────────────────────────┘
```

---

## Database Connection Management

### Connection Pool Architecture

```
┌──────────────────────────────────────┐
│    Connection Pool (Main DB)         │
│  ┌────────────────────────────────┐  │
│  │ Connections:                   │  │
│  │ • Conn 1 [Available]          │  │
│  │ • Conn 2 [In Use - User API]  │  │
│  │ • Conn 3 [Available]          │  │
│  │ • Conn 4 [In Use - User API]  │  │
│  │ • Conn 5 [Available]          │  │
│  │ • Conn 6 [In Use - Package]   │  │
│  │ • Conn 7 [Available]          │  │
│  │ • Conn 8 [In Use - Mikrotik]  │  │
│  │ • Conn 9 [Available]          │  │
│  │ • Conn 10 [Available]         │  │
│  │                                │  │
│  │ Wait Queue: [Req1, Req2, Req3]│  │
│  └────────────────────────────────┘  │
│                                       │
│ Config: connectionLimit: 10          │
│         queueLimit: 0 (unlimited)    │
│         waitForConnections: true     │
└──────────────────────────────────────┘
```

### Transaction Lifecycle

```
1. Get Connection from Pool
   └─ If unavailable, wait in queue

2. Begin Transaction
   ├─ Lock required resources
   └─ Isolation level: READ COMMITTED

3. Execute Queries
   ├─ Query 1: INSERT/UPDATE/DELETE
   ├─ Query 2: INSERT/UPDATE/DELETE
   ├─ Query 3: INSERT/UPDATE/DELETE
   └─ All operations isolated from others

4. Commit or Rollback
   ├─ Success: COMMIT
   │  └─ Changes become visible
   └─ Error: ROLLBACK
      └─ All changes reverted

5. Release Connection to Pool
   └─ Available for next request
```

---

## Key Design Principles

### 1. **Separation of Concerns**
- **Main Database**: Application data (users, packages, routers)
- **RADIUS Database**: Authentication data (passwords, permissions, attributes)
- **Loose coupling**: Each DB can be replaced or upgraded independently

### 2. **Atomicity Across Multiple Databases**
- All operations are transactional
- Both databases commit together or both rollback
- No partial updates

### 3. **Data Consistency**
```
User Creation:
  Main DB: network_users INSERT ✓
  RADIUS DB: radcheck INSERT ✓
  Both succeed → User fully created
  
  Main DB: network_users INSERT ✓
  RADIUS DB: radcheck INSERT ✗ (error)
  → Both rollback → User NOT created
```

### 4. **Scalability**
- Connection pooling reduces DB connection overhead
- Horizontal scaling: Multiple Node.js servers sharing same DBs
- Load balancing: Distribute requests across servers

### 5. **Fault Tolerance**
- Automatic connection recovery
- Exponential backoff on connection failures
- Dead connection detection and replacement

---

## Data Consistency Rules

### When User is Created:
```
✓ network_users row created
✓ radcheck entry (password) created
✓ radreply entries (rate limit, device limit) created
✓ radcheck entry (expiration) created
✓ radusergroup entry (group assignment) created
✓ All changes visible to RADIUS server
```

### When Package is Updated:
```
✓ packages table updated
✓ ALL users with that package synced
✓ radreply entries updated for all affected users
✓ Changes immediately effective
```

### When Mikrotik is Created:
```
✓ tenant_mikrotiks row created
✓ WireGuard IP allocated and stored
✓ nas entry created in RADIUS
✓ Router can immediately authenticate connections
```

### When Mikrotik is Deleted (Hard):
```
✓ tenant_mikrotiks row deleted
✓ nas entry deleted from RADIUS
✓ WireGuard IP freed for reuse
✓ Router no longer authenticated
```

---

## Error Handling Strategy

```
                    ┌─────────────────────┐
                    │  Request Received   │
                    └──────────┬──────────┘
                               │
                    ┌──────────▼──────────┐
                    │  Validation Check   │
                    └──────────┬──────────┘
                               │
              ┌────────────────┴────────────────┐
              │                                 │
         ┌────▼─────┐                   ┌──────▼──────┐
         │   Valid  │                   │   Invalid   │
         └────┬─────┘                   └──────┬──────┘
              │                                │
         ┌────▼─────────────┐           ┌──────▼──────────────┐
         │ Begin Transaction│           │ Return 400 Error    │
         └────┬─────────────┘           │ - Message           │
              │                         │ - Validation Details│
    ┌─────────▼─────────────┐           └──────────────────────┘
    │ Execute Operations    │
    │ (Main DB + RADIUS DB) │
    └─────────┬─────────────┘
              │
    ┌─────────┴──────────┐
    │                    │
┌───▼────┐          ┌────▼───┐
│ Success│          │ Error  │
└───┬────┘          └────┬───┘
    │                    │
┌───▼──────────────┐ ┌───▼──────────────┐
│ Commit Both DBs  │ │ Rollback Both DBs│
├──────────────────┤ ├──────────────────┤
│ • Main DB commit │ │ • Main DB rollback
│ • RADIUS commit  │ │ • RADIUS rollback
└───┬──────────────┘ └───┬──────────────┘
    │                    │
┌───▼──────────────────┐ ┌───▼──────────────────┐
│ Return 200 Success   │ │ Return 400/500 Error │
│ - Data created      │ │ - Error message     │
│ - Confirmation      │ │ - Rollback complete  │
└────────────────────┘ └─────────────────────┘
```

---

## Monitoring & Observability

### Logging Levels
```
ERROR:   Failed operations, database errors, critical issues
WARN:    Unusual situations, retries, incomplete operations
INFO:    API calls, user operations, state changes
DEBUG:   Query details, variable values, execution flow
```

### Health Check Endpoints
```
GET /api/health/db
├─ Main DB connection status
├─ RADIUS DB connection status
└─ Response time

GET /api/health/status
├─ Server uptime
├─ Memory usage
├─ Request count
└─ Error count
```

### Metrics to Monitor
```
Database:
  - Connection pool utilization
  - Query execution time
  - Failed transactions
  - Dead connections

Application:
  - API response time
  - Request throughput
  - Error rate
  - User creation success rate
  - Package sync success rate
```

---

## Deployment Checklist

- [ ] Create both databases (main + RADIUS)
- [ ] Run all migrations/create table scripts
- [ ] Set up environment variables (.env)
- [ ] Configure connection pools
- [ ] Test database connectivity
- [ ] Implement authentication middleware
- [ ] Set up error logging
- [ ] Configure HTTPS certificates
- [ ] Set up FreeRADIUS server
- [ ] Configure radius/nas table access
- [ ] Run integration tests
- [ ] Set up monitoring/alerting
- [ ] Configure backups (both databases)
- [ ] Document API endpoints
- [ ] Train team on system

