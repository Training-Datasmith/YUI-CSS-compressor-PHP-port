#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TEST_IMAGE_TAG="cssmin-system-test:remote-test-2026-11-09"
LINT_IMAGE_TAG="cssmin-php-lint:remote-test-2026-11-09"
USE_SUDO_DOCKER="${USE_SUDO_DOCKER:-0}"

docker_cmd() {
    if [[ "${USE_SUDO_DOCKER}" == "1" ]]; then
        sudo docker "$@"
    else
        docker "$@"
    fi
}

docker_cmd build -f "${REPO_ROOT}/docker/php-lint/Dockerfile" -t "${LINT_IMAGE_TAG}" "${REPO_ROOT}"

docker_cmd build -f "${REPO_ROOT}/docker/system-test/Dockerfile" -t "${TEST_IMAGE_TAG}" "${REPO_ROOT}"

docker_cmd run --rm \
    -v "${REPO_ROOT}:/app" \
    -w /app \
    "${LINT_IMAGE_TAG}" \
    bash /app/scripts/system-test-inner.sh lint

docker_cmd run --rm \
    -v "${REPO_ROOT}:/app" \
    -w /app \
    "${TEST_IMAGE_TAG}" \
    bash /app/scripts/system-test-inner.sh test

echo 'system-test: OK'
