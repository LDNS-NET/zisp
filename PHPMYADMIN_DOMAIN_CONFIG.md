## Moving phpMyAdmin off the central domain

Currently, phpMyAdmin is exposed on the same domain as the main ZISP app via the nginx snippet:

```nginx
include snippets/phpmyadmin.conf;
```

in the `zispbilling.cloud` server block.

For better security and separation of concerns, you can serve phpMyAdmin on a **different domain or subdomain** (for example `dbadmin.yourisp.com`) instead of the central app domain.

### 1. Create DNS record for the new domain

- **Create an A record** (or CNAME) in your DNS provider for the domain/subdomain you want to use for phpMyAdmin, pointing it to this server's public IP.
  - Example:
    - `dbadmin.yourisp.com -> 213.199.41.117`

### 2. Create a dedicated nginx server block for phpMyAdmin

1. Create a new nginx vhost file, for example:

```bash
sudo nano /etc/nginx/sites-available/phpmyadmin
```

2. Use a minimal config that **only** serves phpMyAdmin on the new domain:

```nginx
server {
    server_name dbadmin.yourisp.com;

    root /usr/share;
    index index.php index.html index.htm;

    location / {
        return 302 /phpmyadmin/;
    }

    include snippets/phpmyadmin.conf;

    listen 80;
}
```

3. Enable the site and reload nginx:

```bash
sudo ln -s /etc/nginx/sites-available/phpmyadmin /etc/nginx/sites-enabled/phpmyadmin
sudo nginx -t
sudo systemctl reload nginx
```

At this point, phpMyAdmin should be reachable at:

- `http://dbadmin.yourisp.com/phpmyadmin`

### 3. Secure phpMyAdmin with HTTPS (Certbot)

Use Certbot to obtain a TLS certificate for the new domain:

```bash
sudo certbot --nginx -d dbadmin.yourisp.com
```

Certbot will:

- Add `listen 443 ssl` and certificate directives to the `phpmyadmin` server block.
- Optionally configure HTTP → HTTPS redirects.

After that, you will access phpMyAdmin via:

- `https://dbadmin.yourisp.com/phpmyadmin`

### 4. Remove phpMyAdmin from the central ZISP vhost

Once you've confirmed phpMyAdmin works correctly on the new domain:

1. Edit the main ZISP nginx site (central app domain), for example:

```bash
sudo nano /etc/nginx/sites-available/zisp
```

2. **Remove or comment out** the line:

```nginx
include snippets/phpmyadmin.conf;
```

3. Test and reload nginx:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

This will ensure phpMyAdmin is **no longer accessible** on the central domain (`zispbilling.cloud`), and is only served on the new dedicated domain.

### 5. (Recommended) Further lock down phpMyAdmin

Once moved to its own domain, consider tightening security:

- Restrict access to specific IPs in the phpMyAdmin server block:

```nginx
location /phpmyadmin {
    allow YOUR.PUBLIC.IP.ADDRESS;
    deny all;
}
```

- Or protect it with HTTP Basic Auth (`auth_basic`) in addition to MySQL credentials.

