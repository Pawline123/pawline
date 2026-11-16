#!/usr/bin/env bash
# Private, vorübergehende WordPress-Vorschau in GitHub Codespaces.
# Passwörter bleiben in wordpress/.codespaces/preview.env, nie in Git/Logs.
set -euo pipefail

fail() { printf 'FEHLER: %s\n' "$*" >&2; exit 1; }
action="${1:-start}"
case "$action" in start|stop|status|zugang) ;; *) fail 'Befehl: start, stop, status oder zugang' ;; esac

# Dieses Skript soll insbesondere keinen lokalen Windows-/Live-Datenbestand ändern.
[[ "${CODESPACES:-}" == true ]] || fail 'Dieses Skript ist nur für GitHub Codespaces gedacht.'
[[ "${CODESPACE_NAME:-}" =~ ^[a-z0-9][a-z0-9-]*$ ]] || fail 'Gültiger CODESPACE_NAME fehlt.'
forward_domain="${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN:-app.github.dev}"
[[ "$forward_domain" =~ ^[a-z0-9]+([.-][a-z0-9]+)*$ ]] || fail 'Ungültige Port-Weiterleitungsdomain.'
preview_url="https://${CODESPACE_NAME}-8080.${forward_domain}"
mail_url="https://${CODESPACE_NAME}-8025.${forward_domain}"
export PAWLINE_PREVIEW_URL="$preview_url"

wp_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$wp_dir"
runtime_dir="$wp_dir/.codespaces"
env_file="$runtime_dir/preview.env"
marker="$runtime_dir/setup-complete"

links() {
  printf '\nShop:       %s\nVerwaltung: %s/wp-admin/\nTestmails:  %s\n' "$preview_url" "$preview_url" "$mail_url"
  printf 'In PORTS müssen 8080 und 8025 auf PRIVATE stehen. Zugriff nur mit deinem GitHub-Login.\n'
}

if [[ "$action" == zugang ]]; then
  [[ -f "$env_file" ]] || fail 'Vorschau zuerst mit start einrichten.'
  printf 'Benutzer: pawline-admin\nPasswort: Feld WP_ADMIN_PASSWORD in wordpress/.codespaces/preview.env öffnen.\n'
  links
  exit 0
fi

command -v docker >/dev/null || fail 'Docker fehlt. Codespace mit .devcontainer/devcontainer.json neu erstellen bzw. rebuilden.'
docker compose version >/dev/null || fail 'Docker Compose fehlt.'

if [[ "$action" == start ]]; then
  umask 077
  mkdir -p "$runtime_dir"
  chmod 700 "$runtime_dir"
  if [[ ! -e "$env_file" ]]; then
    temporary_env="$(mktemp "$runtime_dir/env.XXXXXX")"
    trap '[[ -z "${temporary_env:-}" ]] || rm -f -- "$temporary_env"' EXIT
    password() { od -An -N24 -tx1 /dev/urandom | tr -d ' \n'; }
    {
      printf 'WP_PORT=8080\nMAILPIT_PORT=8025\n'
      printf 'DB_PASSWORD=%s\n' "$(password)"
      printf 'DB_ROOT_PASSWORD=%s\n' "$(password)"
      printf 'WP_ADMIN_USER=pawline-admin\nWP_ADMIN_PASSWORD=%s\n' "$(password)"
      printf 'WP_ADMIN_EMAIL=admin@pawline.test\n'
    } > "$temporary_env"
    chmod 600 "$temporary_env"
    mv -- "$temporary_env" "$env_file"
    temporary_env=''
  fi
fi
[[ -f "$env_file" ]] || fail 'Vorschau noch nicht eingerichtet. Zuerst start ausführen.'
chmod 600 "$env_file"

# Absichtlich eine andere env-Datei und ein anderer Projektname als lokal/CI.
compose() {
  docker compose --project-name pawline-preview --env-file "$env_file" \
    -f "$wp_dir/docker-compose.yml" -f "$wp_dir/docker-compose.codespaces.yml" "$@"
}

if [[ "$action" == stop ]]; then
  compose stop
  printf 'WordPress-Container gestoppt, Daten erhalten. Zusätzlich den Codespace auf github.com/codespaces stoppen.\n'
  exit 0
fi
if [[ "$action" == status ]]; then
  compose ps
  links
  exit 0
fi

printf 'Docker wird vorbereitet …\n'
docker_ready=false
for ((attempt=0; attempt<60; attempt++)); do
  if docker info >/dev/null 2>&1; then docker_ready=true; break; fi
  sleep 2
done
[[ "$docker_ready" == true ]] || fail 'Docker startet nicht. Build-/Startprotokoll des Codespaces prüfen.'

compose up -d --wait db wordpress mailpit
wordpress_ready=false
for ((attempt=0; attempt<60; attempt++)); do
  if compose exec -T wordpress test -f /var/www/html/wp-load.php; then wordpress_ready=true; break; fi
  sleep 2
done
[[ "$wordpress_ready" == true ]] || fail 'WordPress-Dateien sind noch nicht bereit.'
compose exec -T --user root wordpress sh -c \
  'mkdir -p /var/pawline-private /var/www/html/wp-content/uploads && chown 33:33 /var/pawline-private /var/www/html/wp-content/uploads'

if [[ ! -f "$marker" ]] || ! compose run --rm wpcli wp core is-installed >/dev/null 2>&1; then
  printf 'Erste Einrichtung und gekennzeichnete Demoprodukte …\n'
  compose run --rm wpcli sh /project/scripts/setup-wp.sh
  compose run --rm wpcli sh /project/scripts/demo-data.sh
  # Erst nach vollständig erfolgreicher Einrichtung markieren.
  touch "$marker"
fi

# Beim Wiederaufnehmen nur Adressen prüfen/anpassen, nicht Inhalte/Editor-Änderungen zurücksetzen.
compose run --rm wpcli wp option update home "$preview_url"
compose run --rm wpcli wp option update siteurl "$preview_url"
compose run --rm wpcli wp option update blog_public 0
compose run --rm wpcli wp eval \
  '$e = \Pawline\Dropship\Private_Storage::instance()->location_error(); if (null !== $e) { WP_CLI::error($e); }'

links
printf '\nAnmeldung: pawline-admin. Passwort steht nur in wordpress/.codespaces/preview.env.\n'
printf 'Nur Vorschau mit Demodaten: keine Stripe-Schlüssel, keine echten Zahlungen, keine gültigen Kundenrechnungen.\n'
