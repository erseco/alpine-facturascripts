# Copilot Instructions for alpine-facturascripts

## Project Overview

This repository contains a lightweight Docker image for **FacturaScripts** (open-source accounting and billing software) built on **Alpine Linux**. The image is optimized for minimal size (~30MB) and resource efficiency while maintaining full functionality.

**Key Technologies:**
- **Base OS:** Alpine Linux
- **Web Server:** nginx 1.28
- **PHP:** PHP 8.4 FPM
- **Process Manager:** runit (instead of supervisord for lower memory footprint)
- **Databases Supported:** MySQL/MariaDB, PostgreSQL
- **Container Registry:** Docker Hub and GitHub Container Registry (GHCR)

**Parent Image:** [erseco/alpine-php-webserver](https://github.com/erseco/alpine-php-webserver)

## Architecture

### Directory Structure
- `/rootfs/` - Contains overlay files copied into the container:
  - `docker-entrypoint-init.d/` - Initialization scripts run on container startup
  - `etc/` - Configuration files (nginx, cron, runit services)
  - `usr/local/bin/` - Helper scripts for FacturaScripts operations
- `/var/www/html/` - FacturaScripts installation directory (in container)
- `/var/www/html/volume/` - Persistent volume mount point containing:
  - `MyFiles/` - Uploaded files and documents
  - `Plugins/` - Installed plugins

### Key Features
1. **Unattended Installation** - Automatic setup via environment variables
2. **Automatic Plugin Installation** - Install plugins on first startup via `FS_PLUGINS`
3. **Multi-architecture Support** - Builds for amd64, arm/v6, arm/v7, arm64, ppc64le, s390x
4. **Security** - Services run as non-privileged `nobody` user
5. **Cron Tasks** - Hourly scheduled tasks via dcron (configurable)

## Building and Testing

### Build the Image
```bash
docker compose build
# Or for a specific FacturaScripts version:
docker compose build --build-arg FS_VERSION=2025.4
```

### Run Tests
The project uses a simple integration test:
```bash
# Run the test suite
docker compose --file docker-compose.test.yml up --exit-code-from sut --timeout 10 --build

# Or use the test script directly
./run_tests.sh
```

**Test validates:**
- Container starts successfully
- Database connection is established
- FacturaScripts login page is accessible and contains "facturascripts" text

### Manual Testing
```bash
# Start the development environment
docker compose up

# Access FacturaScripts at http://localhost:8080
# Default credentials (from docker-compose.yml): admin / Admin1234

# Check logs
docker compose logs -f facturascripts

# Execute commands in container
docker compose exec facturascripts sh
```

## Development Guidelines

### Code Style
- **Shell Scripts:** Use `/bin/sh` (not bash) for Alpine Linux compatibility
- **Line Endings:** LF (Unix-style), not CRLF
- **Indentation:** Consistent with existing files (2 spaces for YAML, 4 for shell scripts)
- **Comments:** Provide clear comments for complex logic

### Docker Best Practices
1. **Minimize Layers:** Combine RUN commands where logical
2. **Clean Cache:** Always `rm -rf /var/cache/apk/*` after `apk add`
3. **User Permissions:** Services run as `nobody:nobody` (UID/GID configurable via parent image)
4. **Multi-stage Builds:** Not currently used, but consider for future optimizations
5. **Hadolint Compliance:** Dockerfile is linted via hadolint in CI

### Environment Variables
All configurable options should use environment variables with sensible defaults:
- Database connection: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_TYPE`
- FacturaScripts setup: `FS_INITIAL_USER`, `FS_INITIAL_PASS`, `FS_LANG`, `FS_TIMEZONE`
- PHP settings: `memory_limit`, `upload_max_filesize`, `post_max_size`, `max_execution_time`
- Advanced: See README.md for complete list

### Initialization Process
The container initialization follows this sequence:
1. **Database Wait:** Check database availability (via netcat)
2. **Folder Creation:** Ensure required directories exist
3. **Config Generation:** Create `config.php` from environment variables
4. **htaccess Setup:** Copy and configure `.htaccess`
5. **Unattended Install:** If `FS_INITIAL_USER` and `FS_INITIAL_PASS` are set, run automated installation
6. **Plugin Installation:** If `FS_PLUGINS` is set, install specified plugins
7. **Dynamic Rebuild:** Rebuild FacturaScripts dynamic classes
8. **Service Start:** Launch nginx, PHP-FPM, and cron (if enabled)

See `rootfs/docker-entrypoint-init.d/02-configure-facturascripts.sh` for implementation details.

## CI/CD Pipeline

### GitHub Actions Workflow (`.github/workflows/build.yml`)
1. **Checkout** code
2. **Docker metadata** generation (tags based on branches/tags)
3. **QEMU setup** for multi-platform builds
4. **Buildx setup** for Docker multi-arch
5. **Login** to Docker Hub and GHCR
6. **Hadolint** Dockerfile linting (SARIF upload to GitHub Security)
7. **FS Version determination** (from git tag or default to 2025.5)
8. **Test build** (for PRs only)
9. **Integration tests** (using `docker-compose.test.yml`)
10. **Build and push** to registries (for main branch and tags)
11. **Trivy scan** for vulnerabilities
12. **Docker Hub description** update (on tags)

### Versioning Strategy
- **Git Tags:** `v2025.5` → Docker tags `2025.5`, `2025`, `latest`
- **Main Branch:** Docker tags `main`, `beta`
- **FacturaScripts Version:** Controlled via `FS_VERSION` build arg (default: 2025.5)

## Security Considerations

1. **Non-root Execution:** All services run as `nobody` user
2. **Capability-based Permissions:** `crond` uses Linux capabilities instead of root
3. **No Hardcoded Secrets:** All credentials via environment variables
4. **Vulnerability Scanning:** Trivy runs on all builds
5. **Dockerfile Linting:** Hadolint enforces best practices
6. **Minimal Attack Surface:** Alpine Linux base with only required packages

### Security Updates
- Regularly update base image: `erseco/alpine-php-webserver`
- Monitor FacturaScripts releases: https://facturascripts.com/descargas
- Review Dependabot PRs for action updates

## Common Modifications

### Adding a New Environment Variable
1. Add to `Dockerfile` ENV section (if default needed)
2. Add to `rootfs/docker-entrypoint-init.d/02-configure-facturascripts.sh` (if used in config)
3. Update `docker-compose.yml` example
4. Document in README.md "Configuration" section

### Adding a PHP Extension
1. Edit `Dockerfile` RUN section (around line 13)
2. Add `php84-<extension>` to the apk add command
3. Test the build and ensure the extension loads
4. Update README.md if it's a notable feature

### Modifying Initialization Logic
1. Edit `rootfs/docker-entrypoint-init.d/02-configure-facturascripts.sh`
2. Follow existing function structure
3. Ensure idempotency (script can run multiple times safely)
4. Test with fresh container and existing installation

### Changing FacturaScripts Default Version
1. Update `FS_VERSION` ARG in `Dockerfile` (line 43)
2. Update `.github/workflows/build.yml` fallback version (line 93)
3. Update README.md badges and references

## Troubleshooting Common Issues

### Build Failures
- **FacturaScripts download error:** Check if version exists at facturascripts.com/descargas
- **Multi-arch build timeout:** QEMU emulation is slow; be patient
- **Hadolint failures:** Run `docker run --rm -i hadolint/hadolint < Dockerfile` locally

### Runtime Issues
- **Database connection refused:** Ensure database container is fully initialized (check `depends_on`)
- **Permission denied:** Check volume ownership (`chown -R 1000:1000` if needed)
- **Dynamic class errors:** Run rebuild: `docker compose exec facturascripts php84 /usr/local/bin/rebuild-facturascripts.php`
- **Plugin installation fails:** Check FS_DISABLE_ADD_PLUGINS is not set to true

## Additional Resources

- **FacturaScripts Documentation:** https://facturascripts.com/documentacion
- **Parent Image Repo:** https://github.com/erseco/alpine-php-webserver
- **Alpine Linux Packages:** https://pkgs.alpinelinux.org/packages
- **Docker Multi-platform:** https://docs.docker.com/build/building/multi-platform/

## Testing Checklist for Pull Requests

Before submitting a PR, ensure:
- [ ] Code follows existing style and conventions
- [ ] Dockerfile passes hadolint (run in CI)
- [ ] Integration tests pass (`docker compose -f docker-compose.test.yml up`)
- [ ] Manual test: Fresh install works with default configuration
- [ ] Manual test: Existing installation upgrades cleanly (if applicable)
- [ ] Documentation updated (README.md, this file if needed)
- [ ] Environment variables documented if added
- [ ] Multi-arch build succeeds (verify in CI for PRs)
- [ ] No secrets or credentials committed

## Notes for Copilot

- This is a Docker infrastructure project, not application code development
- Focus on shell scripts, Dockerfile, and YAML configuration
- Prioritize compatibility with Alpine Linux (use `apk`, not `apt`)
- Always maintain backwards compatibility with existing environment variables
- The `nobody` user has UID/GID from the parent image (usually 65534)
- FacturaScripts is a PHP application - we package it, not modify its core code
- When suggesting changes, consider impact on all supported architectures
