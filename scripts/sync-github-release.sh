#!/bin/sh
set -eu

VERSION=${1:-}
CHANNEL=${2:-version}
TARGET_SHA=${3:-${GITHUB_SHA:-}}
REPOSITORY=${GITHUB_REPOSITORY:-erseco/alpine-facturascripts}
IMAGE_DIGEST=${IMAGE_DIGEST:-unknown}

if ! printf '%s\n' "$VERSION" | grep -Eq '^[0-9]{4}(\.[0-9]+)?$'; then
  echo "Invalid FacturaScripts version: $VERSION" >&2
  exit 1
fi

case "$CHANNEL" in
  version|stable|beta|stable-beta) ;;
  *)
    echo "Invalid release channel: $CHANNEL" >&2
    exit 1
    ;;
esac

if [ -z "$TARGET_SHA" ]; then
  echo "A target commit SHA is required" >&2
  exit 1
fi

tag="v$VERSION"
prerelease=false
latest=false
case "$CHANNEL" in
  beta)
    prerelease=true
    ;;
  stable|stable-beta)
    latest=true
    ;;
esac

if ! gh api "repos/$REPOSITORY/git/ref/tags/$tag" >/dev/null 2>&1; then
  gh api --method POST "repos/$REPOSITORY/git/refs" \
    -f "ref=refs/tags/$tag" \
    -f "sha=$TARGET_SHA" >/dev/null
fi

notes_file=$(mktemp)
trap 'rm -f "$notes_file"' EXIT
{
  printf 'Docker image for upstream FacturaScripts %s.\n\n' "$VERSION"
  # Backticks below are Markdown delimiters, not shell substitutions.
  # shellcheck disable=SC2016
  printf -- '- Docker Hub: `erseco/alpine-facturascripts:%s`\n' "$VERSION"
  # shellcheck disable=SC2016
  printf -- '- GHCR: `ghcr.io/erseco/alpine-facturascripts:%s`\n' "$VERSION"
  # shellcheck disable=SC2016
  printf -- '- Channel at publication: `%s`\n' "$CHANNEL"
  # shellcheck disable=SC2016
  printf -- '- Multi-platform digest: `%s`\n\n' "$IMAGE_DIGEST"
  printf '```sh\n'
  printf 'docker pull erseco/alpine-facturascripts:%s\n' "$VERSION"
  printf '```\n\n'
  printf 'Upstream build: https://facturascripts.com/DownloadBuild/1/%s\n' "$VERSION"
} > "$notes_file"

if gh release view "$tag" --repo "$REPOSITORY" >/dev/null 2>&1; then
  gh release edit "$tag" \
    --repo "$REPOSITORY" \
    --title "FacturaScripts $VERSION Docker image" \
    --notes-file "$notes_file" \
    --prerelease="$prerelease" \
    --latest="$latest"
else
  gh release create "$tag" \
    --repo "$REPOSITORY" \
    --verify-tag \
    --title "FacturaScripts $VERSION Docker image" \
    --notes-file "$notes_file" \
    --prerelease="$prerelease" \
    --latest="$latest"
fi
