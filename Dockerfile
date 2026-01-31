# Base image using alpine-php-webserver
ARG ARCH=
FROM ${ARCH}erseco/alpine-php-webserver:3.23.0

LABEL maintainer="Ernesto Serrano <info@ernesto.es>"

LABEL org.opencontainers.image.source="https://github.com/erseco/alpine-facturascripts" \
      org.opencontainers.image.licenses="MIT" \
      org.opencontainers.image.title="Alpine FacturaScripts"

# Install system dependencies as root
USER root
SHELL ["/bin/ash", "-eo", "pipefail", "-c"]
RUN apk add --no-cache \
    unzip jq netcat-openbsd \
    php84-bcmath \
    php84-ctype \
    php84-dom \
    php84-gd \
    php84-gmp \
    php84-intl \
    php84-mysqli \
    php84-pdo \
    php84-pdo \
    php84-pdo_mysql \
    php84-pdo_pgsql \
    php84-pgsql \
    php84-soap \
    php84-tokenizer \
    php84-xml \
    php84-xmlreader \
    php84-xmlwriter \
    php84-zip \
    dcron \
    libcap && \
# Install Composer manually (latest version compatible with PHP 8.4)
    curl -sS https://getcomposer.org/installer | php84 -d allow_url_fopen=1 && \
    mv composer.phar /usr/local/bin/composer && \
    chmod +x /usr/local/bin/composer && \
# Clean APK cache
    rm -rf /var/cache/apk/*

# FacturaScripts version (tag like v2025.81, or "latest" for most recent release)
ARG FS_VERSION=latest

# Default environment variables
ENV APPLICATION_ENV=production \
    memory_limit=512M \
    upload_max_filesize=64M \
    post_max_size=64M \
    max_execution_time=300 \
    HOME=/tmp \
    FS_VERSION=${FS_VERSION}

# Download FacturaScripts CORE.zip from GitHub Releases
RUN set -x && \
    # Normalize version: add 'v' prefix if missing (except for 'latest')
    if [ "$FS_VERSION" != "latest" ] && [ "${FS_VERSION#v}" = "$FS_VERSION" ]; then \
      FS_VERSION="v${FS_VERSION}"; \
    fi && \
    if [ "$FS_VERSION" = "latest" ]; then \
      FS_URL="https://github.com/NeoRazorX/facturascripts/releases/latest/download/CORE.zip"; \
    else \
      FS_URL="https://github.com/NeoRazorX/facturascripts/releases/download/${FS_VERSION}/CORE.zip"; \
    fi && \
    echo "Downloading FacturaScripts from: $FS_URL" && \
    curl -fsSL -o /tmp/facturascripts.zip "$FS_URL" && \
    unzip -q /tmp/facturascripts.zip -d /tmp/ && \
    rm -f /tmp/facturascripts.zip && \
    rm -rf /var/www/html/* && \
    mv /tmp/facturascripts/* /tmp/facturascripts/.[!.]* /var/www/html/ 2>/dev/null || true && \
    rm -rf /tmp/facturascripts && \
    \
    # Create the volume structure for persistent data
    mkdir -p /var/www/html/volume/MyFiles /var/www/html/volume/Plugins && \
    rm -rf /var/www/html/MyFiles /var/www/html/Plugins && \
    ln -s volume/MyFiles /var/www/html/MyFiles && \
    ln -s volume/Plugins /var/www/html/Plugins && \
    chown -R nobody:nobody /var/www/html

# Copy custom entrypoint scripts
COPY --chown=nobody rootfs/ /

# Configure crond to run as nobody user
# crond needs root, so set capabilities on dcron binary
# https://github.com/inter169/systs/blob/master/alpine/crond/README.md
RUN chown nobody:nobody /usr/sbin/crond && \
    setcap cap_setgid=ep /usr/sbin/crond

# Switch to non-privileged user
USER nobody

HEALTHCHECK --interval=30s --timeout=5s --retries=10 CMD curl -fsS http://127.0.0.1:8080/ >/dev/null || exit 1
