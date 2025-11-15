# Base image using alpine-php-webserver
ARG ARCH=
FROM ${ARCH}erseco/alpine-php-webserver:3.22.2

LABEL maintainer="Ernesto Serrano <info@ernesto.es>"

LABEL org.opencontainers.image.source="https://github.com/erseco/alpine-facturascripts" \
      org.opencontainers.image.licenses="MIT" \
      org.opencontainers.image.title="Alpine FacturaScripts"

# Install system dependencies as root
USER root
RUN apk add --no-cache \
    unzip wget jq netcat-openbsd \
    php84-bcmath php84-gd php84-gmp php84-intl php84-mysqli php84-pdo php84-pdo_mysql php84-pdo_pgsql php84-pgsql php84-soap php84-zip \
    && rm -rf /var/cache/apk/*

# FacturaScripts version configuration
ARG FS_VERSION=2025.5

# Default environment variables
ENV APPLICATION_ENV=production \
    memory_limit=512M \
    upload_max_filesize=64M \
    post_max_size=64M \
    max_execution_time=300 \
    HOME=/tmp \
    FS_VERSION=${FS_VERSION}

# Download, extract, and configure FacturaScripts in a single layer
RUN set -x && \
    \
    # 1. Download and extract FacturaScripts
    echo "Downloading FacturaScripts version: $FS_VERSION" && \
    wget -q -O /tmp/facturascripts.zip "https://facturascripts.com/DownloadBuild/1/${FS_VERSION}" && \
    unzip -q /tmp/facturascripts.zip -d /tmp/ && \
    rm -f /tmp/facturascripts.zip && \
    \
    # 2. Move FacturaScripts content to web root
    rm -rf /var/www/html/* && \
    mv /tmp/facturascripts/* /tmp/facturascripts/.[!.]* /var/www/html/ 2>/dev/null || true && \
    rm -rf /tmp/facturascripts && \
    \
    # 3. Create the volume structure for persistent data
    mkdir -p /var/www/html/volume/MyFiles \
             /var/www/html/volume/Plugins && \
    \
    # 4. Create symbolic links to the volume directories
    rm -rf /var/www/html/MyFiles \
           /var/www/html/Plugins && \
    cd /var/www/html && \
    ln -s volume/MyFiles . && \
    ln -s volume/Plugins . && \
    \
    # 5. Set final permissions
    chown -R nobody:nobody /var/www/html

# Copy custom entrypoint scripts
COPY --chown=nobody rootfs/ /

# Switch to non-privileged user
USER nobody

HEALTHCHECK --interval=30s --timeout=5s --retries=10 CMD curl -fsS http://127.0.0.1:8080/ >/dev/null || exit 1
