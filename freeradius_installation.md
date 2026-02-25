# FreeRADIUS Installation and Configuration Guide

This guide details how to install and configure FreeRADIUS to act as the AAA server for the ZimaRadius system. It is written assuming a **Ubuntu/Debian** Linux server environment, which is standard for FreeRADIUS deployments.

## 1. Prerequisites

- A Linux server (Ubuntu 20.04/22.04 recommended).
- Root or sudo access.
- MySQL or MariaDB access (either local or remote) where the `zimaradius` database resides.

## 2. Installation

Install FreeRADIUS and the MySQL module:

```bash
sudo apt update
sudo apt install freeradius freeradius-mysql freeradius-utils
```

## 3. Database Setup

Ensure the following tables exist in your database. These tables are required for the system to function as defined in `nodejs/01-DATABASE-SCHEMA.md`.

You can run this SQL on your database server:

```sql
-- RADIUS Tables

CREATE TABLE IF NOT EXISTS radcheck (
  id INT PRIMARY KEY AUTO_INCREMENT,
  username VARCHAR(64) NOT NULL,
  attribute VARCHAR(64) NOT NULL,
  op VARCHAR(2) DEFAULT ':=',
  value VARCHAR(253),
  INDEX idx_username (username),
  UNIQUE KEY idx_username_attr (username, attribute)
);

CREATE TABLE IF NOT EXISTS radreply (
  id INT PRIMARY KEY AUTO_INCREMENT,
  username VARCHAR(64) NOT NULL,
  attribute VARCHAR(64) NOT NULL,
  op VARCHAR(2) DEFAULT ':=',
  value VARCHAR(253),
  INDEX idx_username (username),
  UNIQUE KEY idx_username_attr (username, attribute)
);

CREATE TABLE IF NOT EXISTS radusergroup (
  username VARCHAR(64) NOT NULL,
  groupname VARCHAR(64) NOT NULL,
  priority INT DEFAULT 1,
  UNIQUE KEY idx_username_groupname (username, groupname)
);

CREATE TABLE IF NOT EXISTS nas (
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

## 4. FreeRADIUS Configuration

### 4.1. Configure SQL Module

Edit the SQL module configuration to point to your database.

```bash
sudo nano /etc/freeradius/3.0/mods-available/sql
```

Find the `driver` section and ensure it is set to `rlm_sql_mysql`:

```text
driver = "rlm_sql_mysql"
```

Find the `sql` block and configure your connection details (match these with your `.env` or database configuration):

```text
sql {
    dialect = "mysql"
    driver = "rlm_sql_mysql"

    # Connection info:
    server = "localhost" # Or your DB IP
    port = 3306
    login = "radius"     # Your DB User
    password = "radius_password"
    radius_db = "radius_db_name"

    # Set to 'yes' to read radius tables
    read_clients = yes

    # Table names (defaults should match our schema, but verify)
    client_table = "nas"

    # Table Definitions (Required for queries.conf)
    authcheck_table = "radcheck"
    authreply_table = "radreply"
    groupcheck_table = "radgroupcheck"
    groupreply_table = "radgroupreply"
    usergroup_table = "radusergroup"
}
```

Enable the SQL module:

```bash
sudo ln -s /etc/freeradius/3.0/mods-available/sql /etc/freeradius/3.0/mods-enabled/
```

### 4.2. Configure Default Site

Replace the default site configuration with the system-specific configuration found in `radius_default_site.conf`.

1. Backup the original file:
   ```bash
   sudo mv /etc/freeradius/3.0/sites-available/default /etc/freeradius/3.0/sites-available/default.bak
   ```

2. Create the new file:
   ```bash
   sudo nano /etc/freeradius/3.0/sites-available/default
   ```

3. Paste the following content (derived from your `radius_default_site.conf`):

```text
server default {
    listen {
        type = auth
        ipaddr = *
        port = 1812
    }

    listen {
        type = acct
        ipaddr = *
        port = 1813
    }

    authorize {
        filter_username
        preprocess
        chap
        mschap
        digest
        suffix
        eap {
            ok = return
        }
        files
        sql # Look in SQL database
        pap
        expiration
        logintime
    }

    authenticate {
        Auth-Type PAP {
            pap
        }
        Auth-Type CHAP {
            chap
        }
        Auth-Type MS-CHAP {
            mschap
        }
        mschap
        digest
        eap
    }

    preacct {
        preprocess
        acct_unique
        suffix
        files
    }

    accounting {
        detail
        unix
        sql # Log traffic to SQL database
        exec
        attr_filter.accounting_response
    }

    session {
        sql # Check for Simultaneous-Use
    }

    post-auth {
        if (session-state:User-Name && reply:User-Name && request:User-Name && (reply:User-Name == request:User-Name)) {
            update reply {
                &User-Name !* ANY
            }
        }
        update {
            &reply: += &session-state:
        }

        sql # Log authentication attempts
        exec
        remove_reply_message_if_eap

        Post-Auth-Type REJECT {
            sql # Log failed attempts
            attr_filter.access_reject
            eap
            remove_reply_message_if_eap
        }
    }
}
```

**Note**: In the original `radius_default_site.conf`, the listen IP was `10.100.0.1` (WireGuard IP). I have changed this to `*` to listen on all interfaces for easier initial setup, but you should change it back to your specific VPN IP if required for security.

## 5. Start and Test

### 5.1. Start the Service

Stop the service if running, and run in debug mode to check for errors:

```bash
sudo systemctl stop freeradius
sudo freeradius -X
```

If everything looks good (Ready to process requests), start the service normally:

```bash
Ctrl+C
sudo systemctl start freeradius
sudo systemctl enable freeradius
```

### 5.2. Create a Test User

Insert a test user into your database:

```sql
INSERT INTO radcheck (username, attribute, op, value) VALUES ('testuser', 'Cleartext-Password', ':=', 'testpass');
```

### 5.3. Test Authentication

Use `radtest` from the localhost (assuming NAS secret is 'testing123' or you added localhost to nas table):

```bash
radtest testuser testpass localhost 0 testing123
```

You should receive an `Access-Accept` packet.
