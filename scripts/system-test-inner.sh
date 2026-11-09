#!/usr/bin/env bash
set -euo pipefail

MODE="${1:-test}"
export COMPOSER_ALLOW_SUPERUSER=1

strip_ansi_file() {
    local src="$1"
    local dest="$2"
    sed -E 's/\x1b\[[0-9;]*[[:alpha:]]//g' "${src}" >"${dest}"
}

assert_clean_stripped_capture() {
    local label="$1"
    local stripped="$2"

    if grep -E '(^|[[:space:]])(PHP[[:space:]]+)?(Warning|Deprecated|Notice):' "${stripped}" >/dev/null; then
        echo "${label}: output contains Warning, Deprecated, or Notice lines" >&2
        grep -E '(^|[[:space:]])(PHP[[:space:]]+)?(Warning|Deprecated|Notice):' "${stripped}" >&2 || true
        exit 1
    fi
}

emit_and_strip_gate() {
    local label="$1"
    local raw="$2"
    local stripped="$3"

    strip_ansi_file "${raw}" "${stripped}"
    cat "${raw}"
    assert_clean_stripped_capture "${label}" "${stripped}"
}

if [[ "${MODE}" == "lint" ]]; then
    PHP_BIN="$(command -v php)"
    if [[ -x /opt/php-5.3.29/bin/php ]]; then
        PHP_BIN="/opt/php-5.3.29/bin/php"
    fi
    "${PHP_BIN}" -v | tee /tmp/php-version-lint.txt
    if ! grep -Eq '^PHP 5\.3\.29' /tmp/php-version-lint.txt; then
        echo 'PHP floor lint: unexpected PHP version (want 5.3.29)' >&2
        exit 1
    fi

    lint_files=(src/Colors.php src/Command.php src/Minifier.php src/Utils.php)
    for lint_file in "${lint_files[@]}"; do
        lint_out="$(mktemp)"
        lint_stripped="$(mktemp)"
        set +e
        "${PHP_BIN}" -l "${lint_file}" >"${lint_out}" 2>&1
        lint_status=$?
        set -e
        emit_and_strip_gate "php -l ${lint_file}" "${lint_out}" "${lint_stripped}"
        if [[ ${lint_status} -ne 0 ]]; then
            rm -f "${lint_out}" "${lint_stripped}"
            exit "${lint_status}"
        fi
        if ! grep -Eq "^No syntax errors detected in ${lint_file//\//\\/}$" "${lint_stripped}"; then
            echo "php -l: missing success line for ${lint_file}" >&2
            rm -f "${lint_out}" "${lint_stripped}"
            exit 1
        fi
        rm -f "${lint_out}" "${lint_stripped}"
    done

    echo 'php-lint: OK (4 files)'
    exit 0
fi

php -v | tee /tmp/php-version-test.txt
if ! grep -Eq '^PHP 7\.2\.34 \(cli\) ' /tmp/php-version-test.txt; then
    echo 'system-test: unexpected PHP version (want 7.2.34)' >&2
    exit 1
fi

for ext in dom json libxml mbstring pcre xml xmlwriter; do
    if ! php -m | grep -qi "^${ext}$"; then
        echo "Missing required PHP extension: ${ext}" >&2
        exit 1
    fi
done

COMPOSER_URL="https://getcomposer.org/download/2.8.10/composer.phar"
COMPOSER_SHA256="28dbb6bd8bef31479c7985b774c130a8bda37dbe63c35b56f6cb6bc377427573"
curl -fsSL -o /tmp/composer.phar "${COMPOSER_URL}"
checksum_verify_out="$(mktemp)"
checksum_stripped="$(mktemp)"
set +e
echo "${COMPOSER_SHA256}  /tmp/composer.phar" | sha256sum -c - >"${checksum_verify_out}" 2>&1
checksum_verify_status=$?
set -e
emit_and_strip_gate "composer checksum" "${checksum_verify_out}" "${checksum_stripped}"
if [[ ${checksum_verify_status} -ne 0 ]]; then
    rm -f "${checksum_verify_out}" "${checksum_stripped}"
    exit "${checksum_verify_status}"
fi
if ! grep -Fxq '/tmp/composer.phar: OK' "${checksum_stripped}"; then
    echo 'composer checksum: missing /tmp/composer.phar: OK' >&2
    rm -f "${checksum_verify_out}" "${checksum_stripped}"
    exit 1
fi
rm -f "${checksum_verify_out}" "${checksum_stripped}"

composer_out="$(mktemp)"
composer_stripped="$(mktemp)"
set +e
php /tmp/composer.phar update --no-interaction --prefer-dist --no-progress >"${composer_out}" 2>&1
composer_status=$?
set -e
emit_and_strip_gate "composer update" "${composer_out}" "${composer_stripped}"
if [[ ${composer_status} -ne 0 ]]; then
    rm -f "${composer_out}" "${composer_stripped}"
    exit "${composer_status}"
fi
if ! grep -Fxq 'Generating autoload files' "${composer_stripped}"; then
    echo 'composer update: missing Generating autoload files' >&2
    rm -f "${composer_out}" "${composer_stripped}"
    exit 1
fi
rm -f "${composer_out}" "${composer_stripped}"

pin_out="$(mktemp)"
pin_stripped="$(mktemp)"
set +e
php scripts/check-cluster-pins.php >"${pin_out}" 2>&1
pin_status=$?
set -e
emit_and_strip_gate "check-cluster-pins" "${pin_out}" "${pin_stripped}"
if [[ ${pin_status} -ne 0 ]]; then
    rm -f "${pin_out}" "${pin_stripped}"
    exit "${pin_status}"
fi
if ! grep -Fxq 'check-cluster-pins: OK (0 pinned packages; installed.php wrong-SHA demo failed; installed.json wrong-SHA demo failed)' "${pin_stripped}"; then
    echo 'check-cluster-pins: missing success line' >&2
    rm -f "${pin_out}" "${pin_stripped}"
    exit 1
fi
rm -f "${pin_out}" "${pin_stripped}"

audit_out="$(mktemp)"
audit_stripped="$(mktemp)"
set +e
php /tmp/composer.phar audit --abandoned=report >"${audit_out}" 2>&1
audit_status=$?
set -e
emit_and_strip_gate "composer audit" "${audit_out}" "${audit_stripped}"
if [[ ${audit_status} -ne 0 ]]; then
    rm -f "${audit_out}" "${audit_stripped}"
    exit "${audit_status}"
fi
if ! grep -Fxq 'No security vulnerability advisories found.' "${audit_stripped}"; then
    echo 'composer audit: missing exact success line' >&2
    rm -f "${audit_out}" "${audit_stripped}"
    exit 1
fi
rm -f "${audit_out}" "${audit_stripped}"

rename_out="$(mktemp)"
rename_stripped="$(mktemp)"
set +e
php scripts/check-rename.php >"${rename_out}" 2>&1
rename_status=$?
set -e
emit_and_strip_gate "check-rename" "${rename_out}" "${rename_stripped}"
if [[ ${rename_status} -ne 0 ]]; then
    rm -f "${rename_out}" "${rename_stripped}"
    exit "${rename_status}"
fi
if ! grep -Fxq 'rename-check: OK (no camelCase repo-owned names; baseline 54 tests, 54 assertions; allowlist demo rejected misplaced getOpcodes)' "${rename_stripped}"; then
    echo 'check-rename: missing success line' >&2
    rm -f "${rename_out}" "${rename_stripped}"
    exit 1
fi
rm -f "${rename_out}" "${rename_stripped}"

phpunit_out="$(mktemp)"
phpunit_stripped="$(mktemp)"
set +e
vendor/bin/phpunit --colors=never --no-coverage >"${phpunit_out}" 2>&1
phpunit_status=$?
set -e
emit_and_strip_gate "phpunit" "${phpunit_out}" "${phpunit_stripped}"
if [[ ${phpunit_status} -ne 0 ]]; then
    rm -f "${phpunit_out}" "${phpunit_stripped}"
    exit "${phpunit_status}"
fi
if ! grep -Fxq 'OK (54 tests, 54 assertions)' "${phpunit_stripped}"; then
    echo 'phpunit: missing strict OK success line' >&2
    rm -f "${phpunit_out}" "${phpunit_stripped}"
    exit 1
fi
rm -f "${phpunit_out}" "${phpunit_stripped}"

echo 'system-test-inner: OK (test phase)'
