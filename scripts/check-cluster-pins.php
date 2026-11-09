<?php

$expected = array();

$repoRoot = dirname(__DIR__);
$installedPhpPath = $repoRoot . '/vendor/composer/installed.php';
$installedJsonPath = $repoRoot . '/vendor/composer/installed.json';

if (!is_readable($installedPhpPath)) {
    fwrite(STDERR, "check-cluster-pins: vendor/composer/installed.php not found; run composer update first.\n");
    exit(1);
}

if (!is_readable($installedJsonPath)) {
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
 * @param array{versions: array<string, array<string, mixed>>} $installedPhp
 * @param array<string, string> $jsonRefs
 * @param array<string, array{reference: string, alias?: string}> $wantMap
 * @param string $side
 * @param bool $quiet
 * @return bool
 */
function pins_ok_for_side($installedPhp, $jsonRefs, $wantMap, $side, $quiet = false)
{
    $versions = isset($installedPhp['versions']) ? $installedPhp['versions'] : array();
    $failed = false;

    foreach ($wantMap as $name => $want) {
        $wantRef = strtolower($want['reference']);

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

            if (!hash_equals($wantRef, strtolower($reference))) {
                if (!$quiet) {
                    fwrite(STDERR, sprintf(
                        "check-cluster-pins [%s]: %s reference mismatch (expected %s, got %s).\n",
                        $side,
                        $name,
                        $wantRef,
                        strtolower($reference)
                    ));
                }
                $failed = true;
            }
        } else {
            if (!isset($jsonRefs[$name])) {
                if (!$quiet) {
                    fwrite(STDERR, sprintf("check-cluster-pins [%s]: %s is not installed.\n", $side, $name));
                }
                $failed = true;
                continue;
            }

            if (!hash_equals($wantRef, $jsonRefs[$name])) {
                if (!$quiet) {
                    fwrite(STDERR, sprintf(
                        "check-cluster-pins [%s]: %s reference mismatch (expected %s, got %s).\n",
                        $side,
                        $name,
                        $wantRef,
                        $jsonRefs[$name]
                    ));
                }
                $failed = true;
            }
        }
    }

    return !$failed;
}

$installedPhp = load_installed_php($installedPhpPath);
$jsonRefs = load_installed_json_references($installedJsonPath);

if (!pins_ok_for_side($installedPhp, $jsonRefs, $expected, 'installed.php')
    || !pins_ok_for_side($installedPhp, $jsonRefs, $expected, 'installed.json')) {
    exit(1);
}

/**
 * @param array{versions: array<string, array<string, mixed>>} $installedPhp
 * @return array{versions: array<string, array<string, mixed>>}
 */
function copy_installed_php($installedPhp)
{
    $versions = array();
    foreach ($installedPhp['versions'] as $name => $meta) {
        $versions[$name] = $meta;
    }

    return array('versions' => $versions);
}

$demoExpected = array(
    'demo/cluster-pkg' => array(
        'reference' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'alias' => '1.0.0',
    ),
);
$wrongSha = '0000000000000000000000000000000000000000';

$phpWrong = copy_installed_php($installedPhp);
$phpWrong['versions']['demo/cluster-pkg'] = array(
    'reference' => $wrongSha,
    'aliases' => array('1.0.0'),
);
$jsonOk = $jsonRefs;
$jsonOk['demo/cluster-pkg'] = strtolower($demoExpected['demo/cluster-pkg']['reference']);

$phpDemoPhpSideFailed = !pins_ok_for_side($phpWrong, $jsonOk, $demoExpected, 'installed.php', true);
$phpDemoJsonSidePassed = pins_ok_for_side($phpWrong, $jsonOk, $demoExpected, 'installed.json', true);

$phpOk = copy_installed_php($installedPhp);
$phpOk['versions']['demo/cluster-pkg'] = array(
    'reference' => strtolower($demoExpected['demo/cluster-pkg']['reference']),
    'aliases' => array('1.0.0'),
);
$jsonWrong = $jsonRefs;
$jsonWrong['demo/cluster-pkg'] = $wrongSha;

$jsonDemoJsonSideFailed = !pins_ok_for_side($phpOk, $jsonWrong, $demoExpected, 'installed.json', true);
$jsonDemoPhpSidePassed = pins_ok_for_side($phpOk, $jsonWrong, $demoExpected, 'installed.php', true);

if (!$phpDemoPhpSideFailed || !$phpDemoJsonSidePassed || !$jsonDemoJsonSideFailed || !$jsonDemoPhpSidePassed) {
    fwrite(STDERR, "check-cluster-pins: wrong-SHA demo did not behave as expected.\n");
    exit(1);
}

fwrite(
    STDOUT,
    'check-cluster-pins: OK (0 pinned packages; installed.php wrong-SHA demo failed; installed.json wrong-SHA demo failed)' . "\n"
);
exit(0);
