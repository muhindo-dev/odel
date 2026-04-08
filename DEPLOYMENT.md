# MRU ODEL — VPS Deployment Documentation

**Date:** 8 April 2026  
**Domain:** https://mruodel.com  
**Platform:** Moodle 5.1.3+ (Build: 20260306)

---

## 1. Server Infrastructure

| Component | Details |
|---|---|
| **Provider** | Namecheap VPS Quasar |
| **OS** | AlmaLinux 9.7 |
| **Control Panel** | Webuzo |
| **Primary IP** | 209.74.87.69 |
| **RAM** | 6 GB |
| **CPUs** | 4 |
| **Disk** | 118 GB |
| **SSH** | Port 22, root access |

### Access Points

| Service | URL / Address |
|---|---|
| **Website** | https://mruodel.com |
| **SSH** | `ssh root@209.74.87.69` |
| **Webuzo Panel** | https://209.74.87.69:2005/ |
| **VPS Management** | https://vpspanel.web-hosting.com |

---

## 2. Software Stack

| Software | Version | Details |
|---|---|---|
| **Apache** | 2.4.65 | MPM prefork, mod_rewrite, mod_ssl, mod_proxy_fcgi |
| **PHP** | 8.3.25 | PHP-FPM, socket-based |
| **MariaDB** | 11.4.2 | UTF8MB4 Unicode CI |
| **Certbot** | Latest (EPEL) | Auto-renewal enabled |
| **Git** | 2.47.3 | Used for deployment |
| **BIND (named)** | System default | Local DNS server |

---

## 3. Directory Structure

```
/home/muhindo/
├── odel_repo/                  ← Git repository (full Moodle codebase)
│   ├── config.php              ← Production Moodle config (DB, paths, settings)
│   ├── lib/
│   │   └── setup.php           ← Moodle bootstrap
│   ├── admin/
│   │   └── cli/
│   │       └── cron.php        ← Moodle cron script
│   └── public/                 ← Web-accessible files (DocumentRoot target)
│       ├── config.php          ← Loader → loads ../config.php
│       ├── index.php           ← Moodle front page
│       ├── login/
│       ├── local/mru/          ← MRU ODEL custom plugin
│       └── ...
├── public_html → odel_repo/public/   ← Symlink (Apache DocumentRoot)
├── www → public_html/                ← Symlink (Webuzo convention)
└── moodledata/                       ← Moodle data directory (not web-accessible)
    ├── filedir/
    ├── cache/
    ├── sessions/
    ├── temp/
    └── ...
```

---

## 4. Configuration Files

### 4.1 Moodle config.php

**Location:** `/home/muhindo/odel_repo/config.php`

```php
$CFG->dbtype    = 'mariadb';
$CFG->dbhost    = 'localhost';
$CFG->dbname    = 'odel';
$CFG->dbuser    = 'odel';
$CFG->dbpass    = 'Mru0d3l_2026!Sec';
$CFG->prefix    = 'mdl_';
$CFG->wwwroot   = 'https://mruodel.com';
$CFG->dataroot  = '/home/muhindo/moodledata';
$CFG->admin     = 'admin';
$CFG->directorypermissions = 02770;
$CFG->sslproxy  = true;
```

### 4.2 PHP-FPM Pool

**Location:** `/usr/local/apps/php83/etc/php-fpm.conf`

- Pool name: `muhindo`
- User/Group: `muhindo:muhindo`
- Socket: `/usr/local/apps/php83/var/fpm-muhindo.sock`
- Socket owner: `nobody:nobody` (Apache user)

### 4.3 PHP Settings (Moodle-specific)

**Location:** `/usr/local/apps/php83/etc/php.d/moodle.ini`

| Setting | Value |
|---|---|
| `memory_limit` | 512M |
| `max_input_vars` | 5000 |
| `max_execution_time` | 300 |
| `post_max_size` | 256M |
| `upload_max_filesize` | 256M |
| `max_file_uploads` | 100 |
| `date.timezone` | Africa/Kampala |
| `opcache.enable` | 1 |
| `opcache.memory_consumption` | 128 |
| `opcache.max_accelerated_files` | 10000 |
| `opcache.revalidate_freq` | 60 |

### 4.4 Apache VirtualHost

**Location:** `/usr/local/apps/apache2/etc/conf.d/webuzoVH.conf`

**HTTP (port 80):**
- ServerName: `mruodel.com`
- ServerAlias: `www.mruodel.com`, `mail.mruodel.com`
- DocumentRoot: `/home/muhindo/public_html`
- PHP handler: `proxy:unix:/usr/local/apps/php83/var/fpm-muhindo.sock|fcgi://localhost`
- Redirects all HTTP → HTTPS (301)

**HTTPS (port 443):**
- Same ServerName/Alias/DocumentRoot
- SSLEngine on
- SSLCertificateFile: `/etc/letsencrypt/live/mruodel.com/fullchain.pem`
- SSLCertificateKeyFile: `/etc/letsencrypt/live/mruodel.com/privkey.pem`

### 4.5 Custom Apache Config

**Location:** `/var/webuzo-data/apache2/custom/domains/mruodel.com.conf`

```apache
<Directory "/home/muhindo/odel_repo/public">
    Options FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>

<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteCond %{REQUEST_URI} !^/.well-known/ [NC]
    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</IfModule>
```

---

## 5. Database

| Property | Value |
|---|---|
| **Engine** | MariaDB 11.4.2 |
| **Database** | `odel` |
| **User** | `odel@localhost` |
| **Password** | `Mru0d3l_2026!Sec` |
| **Collation** | `utf8mb4_unicode_ci` |
| **Tables** | 494 |
| **Table prefix** | `mdl_` |

The database was exported from the local MAMP development environment using:
```bash
mysqldump -u root -proot --single-transaction --routines --triggers odel | gzip > odel_dump.sql.gz
```
Then uploaded via `scp` and imported on the VPS:
```bash
gunzip < odel_dump.sql.gz | mysql -u odel -p'...' odel
```

---

## 6. DNS Configuration

**Registrar:** Namecheap  
**DNS Servers:** Namecheap BasicDNS (`dns1.registrar-servers.com`, `dns2.registrar-servers.com`)

| Type | Host | Value |
|---|---|---|
| A | @ | 209.74.87.69 |
| A | www | 209.74.87.69 |

The VPS also runs a local BIND DNS server with a zone file at `/var/named/mruodel.com.zone`, but the domain's authoritative DNS is handled by Namecheap.

---

## 7. SSL Certificate

| Property | Value |
|---|---|
| **Provider** | Let's Encrypt |
| **Domains** | `mruodel.com`, `www.mruodel.com` |
| **Certificate** | `/etc/letsencrypt/live/mruodel.com/fullchain.pem` |
| **Private Key** | `/etc/letsencrypt/live/mruodel.com/privkey.pem` |
| **Issued** | 8 April 2026 |
| **Expires** | 7 July 2026 |
| **Auto-renewal** | Yes (certbot systemd timer) |

HTTP requests are automatically redirected to HTTPS via a 301 redirect.

---

## 8. Cron Job

**User:** `muhindo`  
**Schedule:** Every minute

```cron
*/1 * * * * /usr/local/apps/php83/bin/php /home/muhindo/odel_repo/admin/cli/cron.php > /dev/null 2>&1
```

This runs Moodle's scheduled task system, which handles:
- Email notifications
- Cache cleanup
- Scheduled reports
- Plugin tasks (including local_mru registration processing)

---

## 9. File Permissions

| Path | Owner | Permissions |
|---|---|---|
| `/home/muhindo/odel_repo/` | `muhindo:muhindo` | Dirs: 755, Files: 644 |
| `/home/muhindo/odel_repo/config.php` | `muhindo:muhindo` | 644 |
| `/home/muhindo/moodledata/` | `muhindo:muhindo` | 2770 (setgid) |
| `/home/muhindo/moodledata/*` (dirs) | `muhindo:muhindo` | 770 |
| `/home/muhindo/moodledata/*` (files) | `muhindo:muhindo` | 660 |

Apache runs PHP-FPM as user `muhindo` (via ruid2 module), so all file operations use the `muhindo` user context.

---

## 10. Deployment Process (How Code Was Deployed)

### Step-by-step:

1. **GitHub repo made temporarily public** (`muhindo-dev/odel`)

2. **Cloned on VPS:**
   ```bash
   cd /home/muhindo
   git clone --depth 1 https://github.com/muhindo-dev/odel.git odel_repo
   ```

3. **Symlinked webroot:**
   ```bash
   rmdir /home/muhindo/public_html
   ln -s /home/muhindo/odel_repo/public /home/muhindo/public_html
   ```

4. **Created production `config.php`** at `/home/muhindo/odel_repo/config.php`

5. **Fixed ownership:**
   ```bash
   chown -R muhindo:muhindo /home/muhindo/odel_repo
   chown -R muhindo:muhindo /home/muhindo/moodledata
   find /home/muhindo/odel_repo -type f -exec chmod 644 {} +
   find /home/muhindo/odel_repo -type d -exec chmod 755 {} +
   ```

6. **Imported database** from local MAMP export

7. **Purged Moodle caches:**
   ```bash
   /usr/local/apps/php83/bin/php /home/muhindo/odel_repo/admin/cli/purge_caches.php
   ```

8. **GitHub repo made private again**

---

## 11. Future Updates (How to Redeploy)

To pull updated code from GitHub:

```bash
# SSH into the VPS
ssh root@209.74.87.69

# Pull latest code (repo must be public or use deploy key)
cd /home/muhindo/odel_repo
sudo -u muhindo git pull origin main

# Purge caches
/usr/local/apps/php83/bin/php /home/muhindo/odel_repo/admin/cli/purge_caches.php

# If database schema changed, run upgrade
/usr/local/apps/php83/bin/php /home/muhindo/odel_repo/admin/cli/upgrade.php
```

---

## 12. Useful Commands

| Task | Command |
|---|---|
| **Restart Apache** | `/usr/local/apps/apache2/bin/apachectl graceful` |
| **Restart PHP-FPM** | `kill -USR2 $(pgrep -f 'php-fpm.*php83.*master')` |
| **Purge Moodle caches** | `/usr/local/apps/php83/bin/php /home/muhindo/odel_repo/admin/cli/purge_caches.php` |
| **Run Moodle upgrade** | `/usr/local/apps/php83/bin/php /home/muhindo/odel_repo/admin/cli/upgrade.php` |
| **Check Moodle health** | `/usr/local/apps/php83/bin/php /home/muhindo/odel_repo/admin/cli/checks.php` |
| **Enable maintenance** | `/usr/local/apps/php83/bin/php /home/muhindo/odel_repo/admin/cli/maintenance.php --enable` |
| **Disable maintenance** | `/usr/local/apps/php83/bin/php /home/muhindo/odel_repo/admin/cli/maintenance.php --disable` |
| **View Apache error log** | `tail -f /usr/local/apps/apache2/logs/mruodel.com.err` |
| **View Apache access log** | `tail -f /usr/local/apps/apache2/logs/mruodel.com.log` |
| **Renew SSL manually** | `certbot renew` |
| **Check SSL cert expiry** | `openssl x509 -in /etc/letsencrypt/live/mruodel.com/fullchain.pem -noout -dates` |
| **Edit cron** | `crontab -u muhindo -e` |

---

## 13. SMTP / Email Configuration

Configured via Moodle admin settings (in database):

| Setting | Value |
|---|---|
| **SMTP Host** | `smtp.gmail.com:465` |
| **SMTP Security** | SSL |
| **SMTP User** | `noreply@mru.ac.ug` |
| **SMTP Password** | *(stored in Moodle admin)* |
| **No-reply Address** | `noreply@mru.ac.ug` |

---

## 14. Custom Plugin: local_mru

The MRU ODEL plugin (`local/mru/`) provides:

- **5-step registration wizard** with OTP email verification
- **Campus Dynamics API integration** (v2.2) for student/staff identity verification
- **Custom login page** with MRU branding
- **Theme customizations** (mru_odel Boost child theme)

### API Integration

| Setting | Value |
|---|---|
| **Base URL** | `https://eadmin.mru.ac.ug/API/v2` |
| **Auth** | Username/password → token |
| **Admin config** | Site Admin → Plugins → Local plugins → MRU ODEL Settings |

---

## 15. Security Notes

- `config.php` (with DB credentials) is excluded from git via `.gitignore`
- VPS credentials are stored locally in `.vps-credentials` (git-ignored)
- HTTP → HTTPS redirect is enforced server-wide
- `$CFG->sslproxy = true` tells Moodle it's behind HTTPS
- Moodledata directory is not web-accessible (outside DocumentRoot)
- SSL auto-renewal is configured via certbot

---

## 16. Database Migration System

The plugin includes a Laravel-style migration system for managing database schema changes safely and reproducibly.

### Architecture

| Component | Path | Purpose |
|---|---|---|
| Base class | `classes/migration/base_migration.php` | Abstract class with XMLDB helper methods |
| Runner | `classes/migration/runner.php` | Discovers, executes, rolls back, tracks migrations |
| CLI tool | `cli/migrate.php` | Command-line interface (6 actions) |
| Web admin | `migrations.php` | Browser-based status & actions page |
| Tracking table | `local_mru_migrations` | Records migration state, batch, checksum, timing |
| Event | `classes/event/migration_executed.php` | Moodle event for audit logging |
| Migration files | `db/migrations/YYYYMMDDHHMMSS_name.php` | Individual migration classes |

### CLI Usage

```bash
# Show status of all migrations
php local/mru/cli/migrate.php --action=status

# Run all pending migrations
php local/mru/cli/migrate.php --action=migrate

# Rollback the last batch
php local/mru/cli/migrate.php --action=rollback

# Rollback the last N batches
php local/mru/cli/migrate.php --action=rollback --steps=3

# Reset: rollback all + re-run (development only — requires confirmation)
php local/mru/cli/migrate.php --action=reset

# Retry a specific failed migration
php local/mru/cli/migrate.php --action=retry --migration=20260408120100_add_phone_to_registrations

# Create a new migration file from template
php local/mru/cli/migrate.php --action=create --name=add_status_column
```

On the VPS, prefix with the full PHP path:
```bash
/usr/local/apps/php83/bin/php /home/muhindo/odel_repo/public/local/mru/cli/migrate.php --action=status
```

### Web Admin

Navigate to **Site administration → MRU ODEL → Migrations** (or visit `/local/mru/migrations.php`).
Requires the `local/mru:manage` capability. Supports "Run pending" and "Rollback last" actions.

### Creating a New Migration

1. Generate the stub:
   ```bash
   php local/mru/cli/migrate.php --action=create --name=add_status_column
   ```
2. Edit the generated file in `db/migrations/`. Implement `up()` and `down()`:
   ```php
   public function up(): void {
       $table = new xmldb_table('local_mru_registrations');
       $field = new xmldb_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');
       $this->add_field($table, $field);
   }

   public function down(): void {
       $table = new xmldb_table('local_mru_registrations');
       $field = new xmldb_field('status');
       $this->drop_field($table, $field);
   }
   ```
3. Commit and push. On the server, run `--action=migrate`.

### Available Helper Methods (base_migration)

| Method | Purpose |
|---|---|
| `table_exists($table)` | Check if a table exists |
| `field_exists($table, $field)` | Check if a field exists |
| `index_exists($table, $index)` | Check if an index exists |
| `create_table($table)` | Create a new table |
| `drop_table($table)` | Drop a table |
| `add_field($table, $field)` | Add a column |
| `drop_field($table, $field)` | Remove a column |
| `rename_field($table, $field, $newname)` | Rename a column |
| `change_field($table, $field)` | Change column type/default/null |
| `add_index($table, $index)` | Add an index |
| `drop_index($table, $index)` | Remove an index |
| `add_key($table, $key)` | Add a key (foreign, unique) |
| `drop_key($table, $key)` | Remove a key |
| `execute_sql($sql, $params)` | Execute raw SQL |
| `insert_record($table, $record)` | Insert a data record |
| `count_records($table, $conditions)` | Count records |

### How It Works

1. **Discovery**: The runner scans `db/migrations/` for files matching `YYYYMMDDHHMMSS_name.php` and sorts them chronologically.
2. **Execution**: Each migration's `up()` runs inside a database transaction. Success/failure is recorded in `local_mru_migrations`.
3. **Batching**: All migrations in a single `--action=migrate` run share the same batch number, enabling batch rollback.
4. **Checksums**: SHA-256 file checksums detect if a migration file changed after being applied (warns during rollback).
5. **Resilience**: If a migration fails, the runner uses upsert logic so the same migration can be retried without duplicate-key errors.
6. **Audit**: Every migrate/rollback action fires a Moodle event for the standard logs.

### Deployment Workflow

```
Local: php cli/migrate.php --action=create --name=my_change
Local: # edit the migration file
Local: php cli/migrate.php --action=migrate  (test locally)
Local: git add -A && git commit && git push
VPS:   cd /home/muhindo/odel_repo && git pull origin main
VPS:   /usr/local/apps/php83/bin/php public/local/mru/cli/migrate.php --action=migrate
```

---

*Document updated: 8 April 2026*
