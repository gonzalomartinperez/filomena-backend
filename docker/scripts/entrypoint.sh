#!/usr/bin/env sh
set -e

say() { printf "\033[1;96m» %s\033[0m\n" "$*"; }

# --- PHP CLI seguro ---
PHP_BIN="${PHP_BIN:-/usr/local/bin/php}"
ARTISAN="/app/artisan"
artisan() { TELESCOPE_ENABLED=false "$PHP_BIN" "$ARTISAN" "$@"; }

cd /app || (echo "ERROR: /app no existe"; ls -la /; exit 1)

# --------------------------------------------------------------------
# 1) Guardas mínimas
# --------------------------------------------------------------------
wait_for_artisan() {
  i=0; timeout="${WAIT_ARTISAN_TIMEOUT:-60}"
  while [ ! -f /app/artisan ]; do
    i=$((i+1)); [ "$i" -gt "$timeout" ] && { echo "ERROR: /app/artisan no apareció en ${timeout}s"; ls -la /app; exit 1; }
    sleep 1
  done
}

say "Esperando a que /app/artisan esté disponible…"
wait_for_artisan

need() { echo ",${LOG_STACK:-}," | grep -qi ",$1,"; }
mkdir -p /app/storage/logs
need daily    && mkdir -p /app/storage/logs/daily
need activity && mkdir -p /app/storage/logs/activity

# --------------------------------------------------------------------
# 2) APP_KEY
# --------------------------------------------------------------------
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "null" ]; then
  say "ERROR: APP_KEY faltante o inválida. Debe definirse en .env antes de arrancar."
  exec sleep infinity
fi

# --------------------------------------------------------------------
# 3) Limpieza de cachés
# --------------------------------------------------------------------
say "Limpiando cachés de Laravel…"
artisan optimize:clear --no-ansi || true

# --------------------------------------------------------------------
# 4) Dependencias de dev (solo local)
# --------------------------------------------------------------------
if [ "${APP_ENV:-production}" = "local" ]; then
  git config --global --add safe.directory "$(pwd)" || true
  composer install
  npm ci
fi

# --------------------------------------------------------------------
# 5) Migraciones
# --------------------------------------------------------------------
if [ "${APP_ENV:-production}" = "local" ]; then
  say "Ejecutando migraciones en desarrollo…"
  artisan migrate:fresh --seed --force --no-interaction
else
  if [ "${MIGRATE_ON_BOOT:-false}" = "true" ]; then
    say "Ejecutando migraciones en producción (MIGRATE_ON_BOOT=true)…"
    artisan migrate --seed --force --no-interaction || true
  else
    say "Saltando migraciones en producción (MIGRATE_ON_BOOT=false)"
  fi
fi

# --------------------------------------------------------------------
# 6) Storage link (idempotente)
# --------------------------------------------------------------------
say "Enlazando storage…"
artisan storage:link --force --no-ansi || true

# --------------------------------------------------------------------
# 7) Cachés optimizadas en prod (opcional)
# --------------------------------------------------------------------
if [ "${APP_ENV:-production}" = "production" ] && [ "${OPTIMIZE_ON_BOOT:-true}" = "true" ]; then
  say "Generando cachés optimizadas…"
  artisan optimize --no-ansi --no-interaction || true
fi

mkdir -p /app/storage/logs

# --------------------------------------------------------------------
# 8) Supervisor (Octane + Horizon + Scheduler)
# --------------------------------------------------------------------
say "Iniciando supervisord (Octane + Horizon + Scheduler)…"
exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf
