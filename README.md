# FacturaScripts on Alpine Linux

[![Docker Pulls](https://img.shields.io/docker/pulls/erseco/alpine-facturascripts.svg)](https://hub.docker.com/r/erseco/alpine-facturascripts/)
![Docker Image Size](https://img.shields.io/docker/image-size/erseco/alpine-facturascripts)
![nginx 1.28](https://img.shields.io/badge/nginx-1.28-brightgreen.svg)
![php 8.4](https://img.shields.io/badge/php-8.4-brightgreen.svg)
![License MIT](https://img.shields.io/badge/license-MIT-blue.svg)
![Build Status](https://img.shields.io/github/actions/workflow/status/erseco/alpine-facturascripts/build.yml?branch=main)

A lightweight FacturaScripts Docker image built on Alpine Linux. (~145MB)

Repository: https://github.com/erseco/alpine-facturascripts

## Key Features

- **Built on** the lightweight image [erseco/alpine-php-webserver](https://github.com/erseco/alpine-php-webserver)
- **Compact** Docker image size (~145MB)
- **Uses PHP 8.4 FPM** for better performance, lower CPU usage & memory footprint
- **Unattended Installation** - skip the web installer with environment variables
- **Configurable** via environment variables (see Configuration section)
- **Multi-arch Support:** `amd64`, `arm/v6`, `arm/v7`, `arm64`, `ppc64le`, `s390x`
- **Optimized** to only use resources when there's traffic (by using PHP-FPM's ondemand process manager)
- **Uses runit** instead of supervisord to reduce memory footprint
- **Services run** under a non-privileged user (`nobody`) for improved security
- **Logs** are sent to container's STDOUT (`docker logs -f <container>`)
- **Extensible** via pre/post configuration hooks
- **Follows the KISS principle** (Keep It Simple, Stupid) to make it easy to understand and adjust

## What is FacturaScripts?

FacturaScripts is a free and open-source accounting and billing software for small and medium-sized businesses. It offers features like:

- Invoicing and billing
- Inventory management
- Customer and supplier management
- Reports and statistics
- Plugin system for extensibility
- Multi-company support

Learn more at [https://facturascripts.com](https://facturascripts.com)

## Important Notes

- **Change default credentials:** Always override `FS_INITIAL_USER` and `FS_INITIAL_PASS` with secure values.
- **First startup:** The first time you start the container, FacturaScripts will rebuild its dynamic classes. This may take a few seconds.
- **Database permissions:** Ensure the database user has permissions to create tables and modify the database structure.

## Usage

### From Docker Hub

```bash
docker compose up
```

Log in using the credentials defined by environment variables.

### From GHCR

```yaml
services:
  facturascripts:
    image: ghcr.io/erseco/alpine-facturascripts
    # rest of your config
```

### Running Commands as Root

In certain situations, you might need to run commands as root within your FacturaScripts container, for example, to install additional packages. You can do this using the `docker compose exec` command with the `--user root` option:

```bash
docker compose exec --user root facturascripts sh
```

## Minimal docker-compose.yml Example

Here is a minimal `docker-compose.yml` example with **unattended installation**:

```yaml
---
services:
  mariadb:
    image: mariadb:lts
    restart: unless-stopped
    environment:
      - MYSQL_ROOT_PASSWORD=facturascripts
      - MYSQL_DATABASE=facturascripts
      - MYSQL_USER=facturascripts
      - MYSQL_PASSWORD=facturascripts
    volumes:
      - mariadb_data:/var/lib/mysql

  facturascripts:
    image: erseco/alpine-facturascripts:latest
    restart: unless-stopped
    ports:
      - "8080:8080"
    environment:
      # Database Connection
      DB_HOST: mariadb
      DB_NAME: facturascripts
      DB_USER: facturascripts
      DB_PASSWORD: facturascripts
      # Initial Setup (for unattended installation)
      FS_INITIAL_USER: admin
      FS_INITIAL_PASS: ChangeMe123!
      FS_LANG: es_ES
      FS_TIMEZONE: Europe/Madrid
    volumes:
      - facturascripts_data:/var/www/html/volume
    depends_on:
      - mariadb

volumes:
  mariadb_data: null
  facturascripts_data: null
```

With this configuration, FacturaScripts will be **automatically installed** and ready to use. Just access `http://localhost:8080` and log in with `admin` / `ChangeMe123!`

To start the services, run:
```bash
docker compose up
```
Once the container is running, FacturaScripts will be accessible at `http://localhost:8080`.

## First Time Setup

This image supports **unattended installation**, which means FacturaScripts will be automatically configured and ready to use without manual intervention.

### Automatic Installation (Recommended)

When you provide the `FS_INITIAL_USER` and `FS_INITIAL_PASS` environment variables, the installation wizard will be completely skipped:

1. Start the container: `docker compose up`
2. Wait for the database initialization (first time only)
3. Access `http://localhost:8080` and log in with your credentials
4. **Done!** FacturaScripts is ready to use

The container automatically creates:
- Complete `config.php` with all necessary settings
- `.htaccess` file for URL rewriting
- Required folders (`MyFiles`, `Plugins`, `Dinamic`)
- Database tables and initial user

### Manual Installation

If you don't provide `FS_INITIAL_USER` and `FS_INITIAL_PASS`, the standard installation wizard will appear on first access:

1. Open your browser and navigate to `http://localhost:8080`
2. The installation wizard will guide you through:
   - Database verification (already configured via environment variables)
   - Creating an administrator user
   - Language and timezone selection
   - Initial configuration

## Configuration

You can configure the container using the following environment variables in your `docker-compose.yml` file.

### Database Connection

| Variable Name   | Description                      | Default   |
|-----------------|----------------------------------|-----------|
| `DB_TYPE`       | Database type (mysql/postgresql) | `mysql`   |
| `DB_HOST`       | Database host                    | `null`    |
| `DB_PORT`       | Database port                    | `3306`    |
| `DB_USER`       | Database user                    | `null`    |
| `DB_PASSWORD`   | Database password                | `null`    |
| `DB_NAME`       | Database name                    | `null`    |

### FacturaScripts Initial Setup (Unattended Installation)

| Variable Name     | Description                           | Default     | Required for Auto-Install |
|-------------------|---------------------------------------|-------------|---------------------------|
| `FS_INITIAL_USER` | Initial admin username                | `null`      | **Yes**                   |
| `FS_INITIAL_PASS` | Initial admin password                | `null`      | **Yes**                   |
| `FS_LANG`         | Interface language (es_ES, en_EN, etc)| `es_ES`     | No                        |
| `FS_TIMEZONE`     | Timezone (e.g., Europe/Madrid, UTC)   | `UTC`       | No                        |

**Important:** To enable unattended installation, you **must** set both `FS_INITIAL_USER` and `FS_INITIAL_PASS`. If these variables are not set, the web installer will appear.

### FacturaScripts Advanced Configuration

| Variable Name           | Description                                 | Default                    |
|-------------------------|---------------------------------------------|----------------------------|
| `FS_COOKIES_EXPIRE`     | Cookie expiration time in seconds           | `31536000` (1 year)        |
| `FS_ROUTE`              | Subdirectory path if not in root            | `""` (root)                |
| `FS_DB_FOREIGN_KEYS`    | Enable foreign key constraints              | `true`                     |
| `FS_DB_TYPE_CHECK`      | Enable database type checking               | `true`                     |
| `FS_MYSQL_CHARSET`      | MySQL character set (MySQL only)            | `utf8mb4`                  |
| `FS_MYSQL_COLLATE`      | MySQL collation (MySQL only)                | `utf8mb4_unicode_520_ci`   |
| `FS_PGSQL_SSL`          | PostgreSQL SSL mode (PostgreSQL only)       | `""`                       |
| `FS_PGSQL_ENDPOINT`     | PostgreSQL endpoint (PostgreSQL only)       | `""`                       |
| `FS_DEBUG`              | Enable debug mode                           | `false`                    |
| `FS_HIDDEN_PLUGINS`     | Comma-separated list of hidden plugins      | `""`                       |
| `FS_DISABLE_RM_PLUGINS` | Disable plugin removal                      | `false`                    |
| `FS_DISABLE_ADD_PLUGINS`| Disable plugin installation                 | `false`                    |
| `FS_DISABLE_RM_USERS`   | Disable user removal                        | `false`                    |

### PHP & Webserver

| Variable Name         | Description                               | Default      |
|-----------------------|-------------------------------------------|--------------|
| `APPLICATION_ENV`     | Set to `development` for debug mode       | `production` |
| `memory_limit`        | PHP memory limit                          | `512M`       |
| `upload_max_filesize` | Max size for uploaded files               | `64M`        |
| `post_max_size`       | Max size of POST data                     | `64M`        |
| `max_execution_time`  | PHP max execution time in seconds         | `300`        |

### Other Configuration Variables

| Variable Name               | Description                                       | Default |
|-----------------------------|---------------------------------------------------|---------|
| `PRE_CONFIGURE_COMMANDS`    | Commands to run before starting the configuration |         |
| `POST_CONFIGURE_COMMANDS`   | Commands to run after finishing the configuration |         |

## Advanced Features

### 1. Using Different FacturaScripts Versions

Calling `docker compose build` uses the latest stable version of FacturaScripts (2025.5). If you need to use a specific FacturaScripts version, you can specify it using the `FS_VERSION` build argument.

To use a specific version, edit the build section for the facturascripts service in your `docker-compose.yml` file:

```yaml
facturascripts:
  image: erseco/alpine-facturascripts
  build:
    context: .
    args:
      FS_VERSION: 2025.4  # Replace with your desired version
```

Available versions can be found at [https://facturascripts.com/descargas](https://facturascripts.com/descargas)

After changing the version, rebuild the image:
```bash
docker compose build facturascripts
```

### 2. Pre/Post Configuration Hooks

You can define commands to be executed before and after the configuration of FacturaScripts using the `PRE_CONFIGURE_COMMANDS` and `POST_CONFIGURE_COMMANDS` environment variables. These can be useful for tasks such as installing additional packages or running scripts.

```yaml
environment:
  PRE_CONFIGURE_COMMANDS: "cat /var/www/html/htaccess-sample"
  POST_CONFIGURE_COMMANDS: |
    echo 'FacturaScripts configured successfully'
    # Add any post-installation tasks here
```

### 3. Installing Plugins

FacturaScripts plugins can be installed through the web interface:

1. Log in to your FacturaScripts installation
2. Go to **Admin Panel > Plugins**
3. Search for the plugin you want to install
4. Click "Install"

Alternatively, you can manually place plugin files in the `/var/www/html/Plugins` directory:

```bash
# Copy plugin to the container
docker cp my-plugin.zip facturascripts:/var/www/html/Plugins/

# Extract if needed
docker compose exec facturascripts unzip /var/www/html/Plugins/my-plugin.zip -d /var/www/html/Plugins/
```

### 4. Persistent Data

The container uses volumes to persist important data:

- `/var/www/html/volume/MyFiles` - Uploaded files, documents, and configuration
- `/var/www/html/volume/Plugins` - Installed plugins

**Important:** Make sure to properly back up these volumes to prevent data loss.

Example backup command:
```bash
# Backup MyFiles
docker run --rm -v alpine-facturascripts_facturascripts_volume:/data -v $(pwd):/backup alpine tar czf /backup/facturascripts-backup-$(date +%Y%m%d).tar.gz -C /data .

# Backup database
docker compose exec mariadb mysqldump -u facturascripts -pfacturascripts facturascripts > facturascripts-db-$(date +%Y%m%d).sql
```

## Supported Databases

FacturaScripts supports the following databases:

- MySQL / MariaDB (recommended)
- PostgreSQL

To use PostgreSQL, change the `DB_TYPE` environment variable to `postgresql` and adjust the connection parameters accordingly.

## Security Considerations

- **Change default passwords:** Always change the default database passwords in production environments.
- **Use HTTPS:** For production deployments, use a reverse proxy (like nginx or Traefik) with SSL/TLS certificates.
- **Regular backups:** Implement a backup strategy for both the database and the volume data.
- **Keep updated:** Regularly update to the latest FacturaScripts version to get security patches.

## Maintenance Tips

### Install Additional Alpine Packages (as root)

```bash
docker compose exec --user root facturascripts sh -c "apk update && apk add nano"
```

### Access FacturaScripts Logs

```bash
docker compose logs -f facturascripts
```

### Manual Database Rebuild

If you need to manually rebuild FacturaScripts dynamic classes:

```bash
# Access the rebuild endpoint
curl "http://localhost:8080/deploy?action=rebuild&token=$(docker compose exec facturascripts grep -o 'token=[^"]*' /var/www/html/Core/Controller/Deploy.php | cut -d'=' -f2 | head -1)"
```

### Clear Cache

```bash
docker compose exec facturascripts rm -rf /var/www/html/MyFiles/Cache/*
```

### Database Console Access

```bash
# MySQL/MariaDB
docker compose exec mariadb mysql -u facturascripts -pfacturascripts facturascripts

# PostgreSQL
docker compose exec postgres psql -U facturascripts -d facturascripts
```

## Troubleshooting

### FacturaScripts shows a database connection error

Make sure the database container is running and the environment variables are correctly set. You can check the logs:

```bash
docker compose logs facturascripts
docker compose logs mariadb
```

### Permission errors

The container runs as the `nobody` user for security. If you encounter permission issues, ensure the volumes have the correct permissions.

### Installation wizard doesn't appear

If the database is already configured, FacturaScripts will skip the installation wizard. Check if there's an existing `config.php` file in the root directory (`/var/www/html/config.php`).

### Error: Class "FacturaScripts\Dinamic\Model\..." not found

This error occurs when FacturaScripts needs to rebuild its dynamic classes. Access the rebuild endpoint:

```bash
curl "http://localhost:8080/deploy?action=rebuild"
```

Or restart the container:

```bash
docker compose restart facturascripts
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Acknowledgments

- Based on [erseco/alpine-php-webserver](https://github.com/erseco/alpine-php-webserver)
- FacturaScripts by [FacturaScripts](https://facturascripts.com)

---

## About

FacturaScripts docker image based on Alpine Linux.

**Docker Hub:** [hub.docker.com/r/erseco/alpine-facturascripts](https://hub.docker.com/r/erseco/alpine-facturascripts)

### Topics
- `docker`
- `nginx`
- `lightweight`
- `alpine`
- `accounting`
- `billing`
- `facturascripts`
- `erp`
- `php8`

### Resources
- 📖 [README](README.md)
- 📜 [License: MIT](LICENSE)
- 🐛 [Issues](https://github.com/erseco/alpine-facturascripts/issues)
- 🔀 [Pull Requests](https://github.com/erseco/alpine-facturascripts/pulls)
