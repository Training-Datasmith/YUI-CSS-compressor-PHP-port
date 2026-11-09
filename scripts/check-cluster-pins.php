<?php

$expected = array();

$repo_root = dirname(__DIR__);
$installed_php_path = $repo_root . '/vendor/composer/installed.php';
$installed_json_path = $repo_root . '/vendor/composer/installed.json';

if (!is_readable($installed_php_path)) {
    fwrite(STDERR, "check-cluster-pins: vendor/composer/installed.php not found; run composer update first.\n");
    exit(1);
}

if (!is_readable($installed_json_path)) {
    fwrite(STDERR, "check-cluster-pins: vendor/composer/installed.json not found; run composer update first.\n");
    exit(1);
}

/**
 * @return array{versions: array<string, array<string, mixed>>}
 */
function load_installed_php($path)
{
    return require $path;
}

/**
 * @return array<string, string>
 */
function load_installed_json_references($path)
{
    $raw = file_get_contents($path);
    if ($raw === false) {
        fwrite(STDERR, "check-cluster-pins: could not read installed.json.\n");
        exit(1);
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        fwrite(STDERR, "check-cluster-pins: invalid installed.json.\n");
        exit(1);
    }

    $packages = array_merge(
        isset($data['packages']) ? $data['packages'] : array(),
        isset($data['packages-dev']) ? $data['packages-dev'] : array()
    );

    $installed = array();
    foreach ($packages as $package) {
        if (!isset($package['name'], $package['source']['reference'])) {
            continue;
        }
        $installed[$package['name']] = strtolower($package['source']['reference']);
    }

    return $installed;
}

/**
 * @param array{versions: array<string, array<string, mixed>>} $installed_php
 * @param array<string, string> $json_refs
 * @param array<string, array{reference: string, alias?: string}> $want_map
 * @param string $side
 * @param bool $quiet
 * @return bool
 */
function pins_ok_for_side($installed_php, $json_refs, $want_map, $side, $quiet = false)
{
    $versions = isset($installed_php['versions']) ? $installed_php['versions'] : array();
    $failed = false;

    foreach ($want_map as $name => $want) {
        $want_ref = strtolower($want['reference']);

        if ($side === 'installed.php') {
            if (!isset($versions[$name])) {
                if (!$quiet) {
                    fwrite(STDERR, sprintf("check-cluster-pins [%s]: %s is not installed.\n", $side, $name));
                }
                $failed = true;
                continue;
            }

            $pkg = $versions[$name];
            if (isset($want['alias'])) {
                $aliases = isset($pkg['aliases']) ? $pkg['aliases'] : array();
                if (!in_array($want['alias'], $aliases, true)) {
                    if (!$quiet) {
                        fwrite(STDERR, sprintf(
                            "check-cluster-pins [%s]: %s alias mismatch (expected %s in aliases %s).\n",
                            $side,
                            $name,
                            $want['alias'],
                            json_encode($aliases)
                        ));
                    }
                    $failed = true;
                }
            }

            $reference = isset($pkg['reference']) ? $pkg['reference'] : null;
            if (!is_string($reference) || $reference === '') {
                if (!$quiet) {
                    fwrite(STDERR, sprintf("check-cluster-pins [%s]: %s has no reference.\n", $side, $name));
                }
                $failed = true;
                continue;
            }

            if (!hash_equals($want_ref, strtolower($reference))) {
                if (!$quiet) {
                    fwrite(STDERR, sprintf(
                        "check-cluster-pins [%s]: %s reference mismatch (expected %s, got %s).\n",
                        $side,
                        $name,
                        $want_ref,
                        strtolower($reference)
                    ));
                }
                $failed = true;
            }
        } else {
            if (!isset($json_refs[$name])) {
                if (!$quiet) {
                    fwrite(STDERR, sprintf("check-cluster-pins [%s]: %s is not installed.\n", $side, $name));
                }
                $failed = true;
                continue;
            }

            if (!hash_equals($want_ref, $json_refs[$name])) {
                if (!$quiet) {
                    fwrite(STDERR, sprintf(
                        "check-cluster-pins [%s]: %s reference mismatch (expected %s, got %s).\n",
                        $side,
                        $name,
                        $want_ref,
                        $json_refs[$name]
                    ));
                }
                $failed = true;
            }
        }
    }

    return !$failed;
}

$installed_php = load_installed_php($installed_php_path);
$json_refs = load_installed_json_references($installed_json_path);

if (!pins_ok_for_side($installed_php, $json_refs, $expected, 'installed.php')
    || !pins_ok_for_side($installed_php, $json_refs, $expected, 'installed.json')) {
    exit(1);
}

/**
 * @param array{versions: array<string, array<string, mixed>>} $installed_php
 * @return array{versions: array<string, array<string, mixed>>}
 */
function copy_installed_php($installed_php)
{
    $versions = array();
    foreach ($installed_php['versions'] as $name => $meta) {
        $versions[$name] = $meta;
    }

    return array('versions' => $versions);
}

$demo_expected = array(
    'demo/cluster-pkg' => array(
        'reference' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'alias' => '1.0.0',
    ),
);
$wrong_sha = '0000000000000000000000000000000000000000';

$php_wrong = copy_installed_php($installed_php);
$php_wrong['versions']['demo/cluster-pkg'] = array(
    'reference' => $wrong_sha,
    'aliases' => array('1.0.0'),
);
$json_ok = $json_refs;
$json_ok['demo/cluster-pkg'] = strtolower($demo_expected['demo/cluster-pkg']['reference']);

$php_demo_php_side_failed = !pins_ok_for_side($php_wrong, $json_ok, $demo_expected, 'installed.php', true);
$php_demo_json_side_passed = pins_ok_for_side($php_wrong, $json_ok, $demo_expected, 'installed.json', true);

$php_ok = copy_installed_php($installed_php);
$php_ok['versions']['demo/cluster-pkg'] = array(
    'reference' => strtolower($demo_expected['demo/cluster-pkg']['reference']),
    'aliases' => array('1.0.0'),
);
$json_wrong = $json_refs;
$json_wrong['demo/cluster-pkg'] = $wrong_sha;

$json_demo_json_side_failed = !pins_ok_for_side($php_ok, $json_wrong, $demo_expected, 'installed.json', true);
$json_demo_php_side_passed = pins_ok_for_side($php_ok, $json_wrong, $demo_expected, 'installed.php', true);

if (!$php_demo_php_side_failed || !$php_demo_json_side_passed || !$json_demo_json_side_failed || !$json_demo_php_side_passed) {
    fwrite(STDERR, "check-cluster-pins: wrong-SHA demo did not behave as expected.\n");
    exit(1);
}

fwrite(
    STDOUT,
    'check-cluster-pins: OK (0 pinned packages; installed.php wrong-SHA demo failed; installed.json wrong-SHA demo failed)' . "\n"
);
exit(0);
