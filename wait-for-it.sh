#!/bin/bash
# wait-for-it.sh
#
# Use this script to test if a given TCP host/port are available

set -e

TIMEOUT=15
STRICT=false
HOST=""
PORT=""
CMD=""

usage() {
  echo "Usage: $0 host:port [--timeout=15] [--strict] [-- <command> [args]]"
  exit 1
}

# Parse arguments
while [[ $# -gt 0 ]]; do
  case "$1" in
    --timeout=*)
      TIMEOUT="${1#*=}"
      ;;
    --strict)
      STRICT=true
      ;;
    --)
      shift
      CMD="$*"
      break
      ;;
    *)
      if [[ "$HOST" == "" ]]; then
        HOST="${1%%:*}"
        PORT="${1#*:}"
      else
        usage
      fi
      ;;
  esac
  shift
done

if [[ "$HOST" == "" || "$PORT" == "" ]]; then
  usage
fi

for ((i=0;i<TIMEOUT;i++)); do
  if nc -z "$HOST" "$PORT"; then
    if [[ -n "$CMD" ]]; then
      exec $CMD
    else
      exit 0
    fi
  fi
  sleep 1
  echo "Waiting for $HOST:$PORT..."
done

if [[ "$STRICT" == "true" ]]; then
  echo "Timeout after $TIMEOUT seconds waiting for $HOST:$PORT"
  exit 1
fi

exit 0
