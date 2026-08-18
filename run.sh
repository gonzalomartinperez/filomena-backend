#!/usr/bin/env bash
set -euo pipefail

COMPOSE_DEV="docker-compose-dev.yaml"
COMPOSE_PROD="docker-compose-prod.yaml"
ENV_FILE=".env"
ENV_TEMPLATE=".env.example.docker"

usage() {
  cat <<'EOF'
Uso: ./run.sh <comando> [--build]

Comandos:
  dev     Levanta el stack de desarrollo.
  prod    Levanta el stack de producción.
  off     Detiene los stacks sin borrar sus volúmenes.
  reset   Detiene los stacks y borra únicamente sus volúmenes y redes huérfanas.

Opciones:
  --build Fuerza la reconstrucción de las imágenes.

Ejemplos:
  ./run.sh dev
  ./run.sh dev --build
  ./run.sh prod --build
  ./run.sh off
  ./run.sh reset
EOF
  exit 1
}

ensure_env() {
  if [ ! -f "$ENV_FILE" ]; then
    cp "$ENV_TEMPLATE" "$ENV_FILE"
    echo "Se creó $ENV_FILE desde $ENV_TEMPLATE. Configurá los valores requeridos y repetí el comando."
    exit 1
  fi
}

env_value() {
  local key=$1
  grep -E "^${key}=" "$ENV_FILE" | tail -n 1 | cut -d= -f2- | tr -d '\r' || true
}

require_env() {
  local key=$1 value
  value="$(env_value "$key")"

  if [ -z "$value" ] || [ "$value" = "null" ] || [ "$value" = "change-me" ]; then
    echo "Falta configurar $key en $ENV_FILE." >&2
    return 1
  fi
}

validate_required_env() {
  local mode=$1 missing=0
  local required=(
    DB_PASSWORD
    PROMETHEUS_BEARER_TOKEN
    GRAFANA_PASSWORD
    INITIAL_ADMIN_DNI
    INITIAL_ADMIN_RECORD_ID
    INITIAL_ADMIN_USERNAME
    INITIAL_ADMIN_EMAIL
    INITIAL_ADMIN_FIRST_NAME
    INITIAL_ADMIN_LAST_NAME
    INITIAL_ADMIN_PASSWORD
  )

  if [ "$mode" = "dev" ]; then
    required+=(DEMO_USER_PASSWORD)
  fi

  for key in "${required[@]}"; do
    require_env "$key" || missing=1
  done

  if [ "$missing" -ne 0 ]; then
    echo "Completá las variables requeridas antes de iniciar el stack." >&2
    exit 1
  fi
}

ensure_app_key() {
  local current new_key
  current="$(env_value APP_KEY)"

  if [ -z "$current" ] || [ "$current" = "null" ]; then
    echo "Generando APP_KEY local..."
    new_key="$(docker run --rm alpine:latest sh -c 'head -c 32 /dev/urandom | base64')"

    if grep -qE '^APP_KEY=' "$ENV_FILE"; then
      sed -i.bak "s#^APP_KEY=.*#APP_KEY=base64:${new_key}#" "$ENV_FILE"
    else
      printf "\nAPP_KEY=base64:%s\n" "$new_key" >> "$ENV_FILE"
    fi

    rm -f "$ENV_FILE.bak"
  fi
}

load_compose_project_name() {
  if [ -z "${COMPOSE_PROJECT_NAME-}" ]; then
    COMPOSE_PROJECT_NAME="$(env_value COMPOSE_PROJECT_NAME)"
  fi

  COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME:-filomena}"
  export COMPOSE_PROJECT_NAME
}

compose_file_for() {
  case "$1" in
    dev) echo "$COMPOSE_DEV" ;;
    prod) echo "$COMPOSE_PROD" ;;
    *) return 1 ;;
  esac
}

project_name() {
  echo "${COMPOSE_PROJECT_NAME}-$1"
}

docker_down_all() {
  local mode file

  for mode in dev prod; do
    file="$(compose_file_for "$mode")"
    docker compose -p "$(project_name "$mode")" -f "$file" down "$@" || true
  done
}

[ $# -lt 1 ] && usage
MODE=$1
shift

BUILD=false
if [ "${1-}" = "--build" ]; then
  BUILD=true
  shift
fi

[ $# -gt 0 ] && usage

ensure_env
load_compose_project_name

case "$MODE" in
  off)
    docker_down_all
    exit 0
    ;;
  reset)
    docker_down_all -v --remove-orphans
    exit 0
    ;;
  dev|prod)
    validate_required_env "$MODE"
    ensure_app_key
    TARGET_FILE="$(compose_file_for "$MODE")"
    ;;
  *)
    usage
    ;;
esac

echo "Cerrando entornos previos..."
docker_down_all

echo "Iniciando $MODE..."
UP_ARGS=(-d)
[ "$BUILD" = true ] && UP_ARGS+=(--build)
docker compose -p "$(project_name "$MODE")" -f "$TARGET_FILE" up "${UP_ARGS[@]}"

echo "Entorno $MODE listo: $(project_name "$MODE")."
