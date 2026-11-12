# Men's Salon Management System (MSMS) 💈
### Production-Ready Render Cloud & Aiven MySQL Deployment (PHP 8.2 / Apache Docker)

A complete, modern PHP-based web application for managing salon appointments, barber services, and administrator operations. This project has been fully containerized and upgraded for seamless deployment on **Render** using **PHP 8.2-Apache** with **Aiven Cloud MySQL (SSL TLS Enforcement)**.

---

## 🚀 Live Cloud Deployment Summary

| Parameter | Details |
| :--- | :--- |
| **Hosting Platform** | [Render Cloud](https://render.com) (Docker Web Service) |
| **Database Platform** | [Aiven Cloud MySQL](https://aiven.io) (SSL / TLS Enforced) |
| **PHP Runtime** | PHP 8.2 (Apache 2.4, mysqli, pdo_mysql, gd, zip, mod_rewrite) |
| **Web Installer** | `/db_setup.php` (1-Click SQL Importer & Connection Doctor) |
| **Admin Panel** | `/admin/login.php` |
| **Website Frontend** | `/index.php` |

---

## 🔑 Default Login Credentials

Once your database is imported using `db_setup.php`, use the following credentials to access the system:

| Role | Username | Password | Dashboard URL |
| :--- | :--- | :--- | :--- |
| **Administrator** | `admin` | `admin123` | `https://<your-app>.onrender.com/admin/login.php` |
| **Staff Member** | `jsmith` | `jsmith123` | `https://<your-app>.onrender.com/index.php` |

---

## ⚙️ Environment Variables (Render & Aiven)

Configure these variables in your **Render Service Dashboard** (`Environment` tab) or via `render.yaml`:

| Variable | Required | Default / Example | Purpose |
| :--- | :---: | :--- | :--- |
| `PORT` | Yes | `10000` | Port on which Apache listens internally inside Render container |
| `DB_SERVER` | Yes | `mysql-xxxx-project.aivencloud.com` | Aiven Cloud MySQL Service URI / Hostname |
| `DB_PORT` | Yes | `26284` | Aiven 5-digit custom MySQL port |
| `DB_USERNAME` | Yes | `avnadmin` | Aiven MySQL admin username |
| `DB_PASSWORD` | Yes | `AVNS_xxxxxxxxx` | Aiven MySQL password |
| `DB_NAME` | Yes | `defaultdb` (or `msms_db`) | Target database name |
| `DB_SSL` | Yes | `true` | Enforces `MYSQLI_CLIENT_SSL` for Aiven secure transport |
| `BASE_URL` | Optional | `https://msms-salon-app.onrender.com/` | Public URL of your deployed app (auto-detected if omitted) |

---

## 🛠️ Critical Upgrades & Fixes Applied

### 1. Aiven Cloud MySQL & PHP 8.x Compatibility
- **SSL Encryption Enforcement**: Aiven Cloud MySQL enforces `require_secure_transport=ON`. Upgraded `classes/DBConnection.php` using `mysqli_init()`, `MYSQLI_OPT_SSL_VERIFY_SERVER_CERT`, and `MYSQLI_CLIENT_SSL` flags so connections are never rejected.
- **Custom 5-Digit Ports**: Added full integer port routing (`DB_PORT`) for Aiven's dynamic ports.
- **PHP 8.2 Class Properties**: Initialized dynamic connection variables inside `__construct()` to eliminate PHP 8 fatal syntax errors.
- **Null & Type Safety**: Added `instanceof mysqli_result` guards in `classes/SystemSettings.php`, `classes/Login.php`, `classes/Master.php`, and `classes/Users.php` so empty databases or un-run queries fail gracefully instead of crashing Apache.

### 2. Render Reverse Proxy & Port 10000 Leak Prevention
- **Canonical Port Leak**: Behind Render's reverse proxy, requests to directories without a trailing slash (e.g. `/admin`) previously caused Apache's `mod_dir` to redirect to internal port `https://your-domain.com:10000/admin/` (which fails).
- **Fix**: Configured `UseCanonicalName Off`, `UseCanonicalPhysicalPort Off`, and `ServerName localhost` in Apache.
- **Direct File Routing**: Updated JS AJAX handlers (`dist/js/script.js`), navbar links (`admin/inc/navigation.php`, `inc/topBarNav.php`), and session auth (`admin/inc/sess_auth.php`) to directly target `index.php` and `admin/login.php`.
- **Smart `.htaccess`**: Added rewrite rules that detect `X-Forwarded-Proto` and strip `:10000` from host headers automatically.

### 3. 1-Click Database Setup (`db_setup.php`)
- Visit `https://<your-render-url>/db_setup.php` in your browser.
- Displays live connection health, MySQL server version, and active TLS cipher.
- Executes `database/msms_db.sql` with `SET FOREIGN_KEY_CHECKS=0` in a single click.
- Verifies tables and admin account, then offers direct login navigation.

---

## 📦 Deployment Instructions

### Method A: Deploy via GitHub & Render Dashboard

1. **Push to GitHub**:
   ```bash
   git init
   git add .
   git commit -m "Production release for Render and Aiven MySQL"
   git branch -M main
   git remote add origin https://github.com/<your-username>/<your-repo-name>.git
   git push -u origin main
   ```

2. **Create Web Service on Render**:
   - Go to [dashboard.render.com](https://dashboard.render.com).
   - Click **New +** > **Web Service**.
   - Connect your GitHub repository.
   - Select **Docker** environment.
   - Enter your Service Name (e.g., `msms-salon-app`).
   - Add the Environment Variables listed in the table above.
   - Click **Create Web Service**.

3. **Initialize Database**:
   - Once the deployment shows **Live**, open:
     `https://<your-app-name>.onrender.com/db_setup.php`
   - Click **1-Click Import Database**.
   - Click **Admin Login** and log in with `admin` / `admin123`.

---

## 📂 Project Structure

```text
├── admin/                     # AdminLTE Admin Management Panel
│   ├── appointments/          # Appointment schedules & verification
│   ├── inc/                   # Admin headers, navigation & session auth
│   ├── services/              # Salon service management
│   ├── system_info/           # Global site settings & logos
│   ├── user/                  # User accounts management
│   ├── index.php              # Admin main entrypoint
│   └── login.php              # Admin login page
├── classes/                   # Core PHP OOP Classes
│   ├── DBConnection.php       # Aiven MySQL SSL Connection Handler
│   ├── Login.php              # Admin & client authentication
│   ├── Master.php             # Service & appointment data logic
│   ├── SystemSettings.php     # System metadata & flash sessions
│   └── Users.php              # User accounts controller
├── database/                  # SQL database schemas
│   └── msms_db.sql            # Master database dump
├── dist/                      # Assets (CSS, JS, AdminLTE)
├── inc/                       # Public frontend headers & navigation
├── uploads/                   # Uploaded media (logos, avatars, covers)
├── .htaccess                  # Smart Apache rewrite & proxy rules
├── config.php                 # Core runtime config
├── db_setup.php               # 1-Click Cloud Database Installer Tool
├── Dockerfile                 # PHP 8.2-Apache production container definition
├── index.php                  # Public salon homepage
├── initialize.php             # Dynamic constants & Cloud Env Loader
├── render.yaml                # Render Blueprint infrastructure as code
├── start.sh                   # Dynamic port & Apache container entrypoint
└── README.md                  # Comprehensive documentation
```

---

## 🛡️ Original File Backups
As per safety protocols, all original source files modified during containerization have been preserved with `.original` extensions in their respective directories (e.g., `initialize.php.original`, `classes/DBConnection.php.original`, etc.).
