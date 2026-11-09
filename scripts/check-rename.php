<?php

$repo_root = dirname(__DIR__);
$baseline = require __DIR__ . '/upstream-baseline.php';

$scan_paths = array(
    $repo_root . '/src',
    $repo_root . '/tests',
    $repo_root . '/scripts',
    $repo_root . '/cssmin',
    $repo_root . '/gui/index.php',
    $repo_root . '/gui/scripts.js',
    $repo_root . '/README.md',
);

$old_names = array(
    'CssMin',
    'MinifierTest',
    'PHPUnit_Framework_TestCase',
    'getExpectation',
    'getFixture',
    'execTest',
    'keepSourceMapComment',
    'setLineBreakPosition',
    'removeImportantComments',
    'raisePhpLimits',
    'setMemoryLimit',
    'setMaxExecutionTime',
    'setPcreBacktrackLimit',
    'setPcreRecursionLimit',
    'keepSourceMap',
    'setChunkLength',
    'chunkLength',
    'processCommentsCallback',
    'CliRenderer',
);

/**
 * File-keyed allowlist: repo-relative path + exact line/call pattern + allowed identifiers on that line.
 *
 * @return array<int, array{path: string, pattern: string, names: array<int, string>}>
 */
function site_allowlist()
{
    return array(
        array(
            'path' => 'tests/Minifier_Test.php',
            'pattern' => '/^\s*use\s+PHPUnit\\\\Framework\\\\TestCase\s*;/',
            'names' => array('PHPUnit', 'Framework', 'TestCase'),
        ),
        array(
            'path' => 'tests/Minifier_Test.php',
            'pattern' => '/extends\s+TestCase\b/',
            'names' => array('TestCase'),
        ),
        array(
            'path' => 'tests/Minifier_Test.php',
            'pattern' => '/function\s+setUp\s*\(/',
            'names' => array('setUp'),
        ),
        array(
            'path' => 'tests/Minifier_Test.php',
            'pattern' => '/\$this\s*->\s*assertEquals\s*\(/',
            'names' => array('assertEquals'),
        ),
        array(
            'path' => 'tests/bin/runner',
            'pattern' => '/use\s+cogpowered\\\\FineDiff\\\\Diff\s*;/',
            'names' => array('FineDiff', 'Diff'),
        ),
        array(
            'path' => 'tests/bin/runner',
            'pattern' => '/new\s+Diff\s*\(/',
            'names' => array('Diff'),
        ),
        array(
            'path' => 'tests/bin/runner',
            'pattern' => '/\$diff\s*->\s*getOpcodes\s*\(/',
            'names' => array('getOpcodes'),
        ),
        array(
            'path' => 'tests/Fine_Diff/Render/Cli.php',
            'pattern' => '/use\s+cogpowered\\\\FineDiff\\\\Render\\\\Renderer\s*;/',
            'names' => array('FineDiff', 'Render', 'Renderer'),
        ),
        array(
            'path' => 'tests/Fine_Diff/Render/Cli.php',
            'pattern' => '/extends\s+Renderer\b/',
            'names' => array('Renderer'),
        ),
        array(
            'path' => 'scripts/check-rename.php',
            'pattern' => '/new\s+RecursiveIteratorIterator\s*\(/',
            'names' => array('RecursiveIteratorIterator'),
        ),
        array(
            'path' => 'scripts/check-rename.php',
            'pattern' => '/new\s+RecursiveDirectoryIterator\s*\(/',
            'names' => array('RecursiveDirectoryIterator'),
        ),
        array(
            'path' => 'scripts/check-rename.php',
            'pattern' => '/FilesystemIterator::SKIP_DOTS/',
            'names' => array('FilesystemIterator'),
        ),
        array(
            'path' => 'scripts/check-rename.php',
            'pattern' => '/\$file_info\s*->\s*isFile\s*\(/',
            'names' => array('isFile'),
        ),
        array(
            'path' => 'scripts/check-rename.php',
            'pattern' => '/\$file_info\s*->\s*getPathname\s*\(/',
            'names' => array('getPathname'),
        ),
        array(
            'path' => 'scripts/check-rename.php',
            'pattern' => '/\$file_info\s*->\s*getExtension\s*\(/',
            'names' => array('getExtension'),
        ),
        array(
            'path' => 'gui/scripts.js',
            'pattern' => '/\.slideDown\s*\(/',
            'names' => array('slideDown'),
        ),
        array(
            'path' => 'gui/scripts.js',
            'pattern' => '/\.slideUp\s*\(/',
            'names' => array('slideUp'),
        ),
        array(
            'path' => 'gui/scripts.js',
            'pattern' => '/\.preventDefault\s*\(/',
            'names' => array('preventDefault'),
        ),
        array(
            'path' => 'gui/scripts.js',
            'pattern' => '/\bparseInt\s*\(/',
            'names' => array('parseInt'),
        ),
        array(
            'path' => 'gui/scripts.js',
            'pattern' => '/\.toCSS\s*\(/',
            'names' => array('toCSS'),
        ),
        array(
            'path' => 'gui/scripts.js',
            'pattern' => '/\.button\s*\(/',
            'names' => array('button'),
        ),
    );
}

/**
 * @param string $path
 * @param string $repo_root
 * @return string
 */
function relative_repo_path($path, $repo_root)
{
    $prefix = rtrim($repo_root, '/') . '/';
    if (strpos($path, $prefix) === 0) {
        return substr($path, strlen($prefix));
    }

    return $path;
}

function file_is_scanned($path, $repo_root)
{
    if (strpos($path, $repo_root . '/tests/fixtures') === 0) {
        return false;
    }
    if (strpos($path, $repo_root . '/tests/expectations') === 0) {
        return false;
    }
    if (strpos($path, '/third-party/') !== false) {
        return false;
    }

    return true;
}

function collect_files($paths, $repo_root)
{
    $files = array();
    foreach ($paths as $path) {
        if (is_file($path)) {
            if (file_is_scanned($path, $repo_root)) {
                $files[] = $path;
            }
            continue;
        }
        if (!is_dir($path)) {
            continue;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file_info) {
            if (!$file_info->isFile()) {
                continue;
            }
            $file = $file_info->getPathname();
            if (!file_is_scanned($file, $repo_root)) {
                continue;
            }
            $ext = $file_info->getExtension();
            if ($ext === 'css') {
                continue;
            }
            if (preg_match('/\.(php|js|md)$/', $file) || substr($file, -strlen('/cssmin')) === '/cssmin' || basename($file) === 'runner') {
                $files[] = $file;
            }
        }
    }

    return array_values(array_unique($files));
}

/**
 * @param string $identifier
 * @return bool
 */
function identifier_has_camel_hump($identifier)
{
    if ($identifier === '') {
        return false;
    }

    return (bool) preg_match('/[a-z][A-Z]/', $identifier);
}

/**
 * @param string $code
 * @param int $line_no
 * @return string
 */
function get_line_text($code, $line_no)
{
    foreach (explode("\n", $code) as $idx => $line_text) {
        if ($idx + 1 === $line_no) {
            return $line_text;
        }
    }

    return '';
}

/**
 * @param string $name
 * @param string $relative_path
 * @param string $line_text
 * @return bool
 */
function identifier_allowed_at_site($name, $relative_path, $line_text)
{
    foreach (site_allowlist() as $entry) {
        if ($entry['path'] !== $relative_path) {
            continue;
        }
        if (!preg_match($entry['pattern'], $line_text)) {
            continue;
        }
        if (in_array($name, $entry['names'], true)) {
            return true;
        }
    }

    return false;
}

/**
 * @param string $content
 * @param bool $scan_php_blocks_only
 * @return array<int, string>
 */
function find_violations_in_content($content, $scan_php_blocks_only, $relative_path = '')
{
    global $old_names;

    $violations = array();
    $segments = array($content);

    if ($scan_php_blocks_only) {
        $segments = array();
        if (preg_match_all('/```php\s*(.*?)```/s', $content, $matches)) {
            foreach ($matches[1] as $block) {
                $segments[] = $block;
            }
        }
        if (preg_match_all('/`([^`\n]+)`/', $content, $inline)) {
            foreach ($inline[1] as $piece) {
                if (preg_match('/[a-zA-Z_\$\\\\]/', $piece)) {
                    $segments[] = $piece;
                }
            }
        }
    }

    foreach ($segments as $segment) {
        foreach ($old_names as $old) {
            if (strpos($segment, $old) !== false) {
                $violations[] = "legacy identifier {$old}";
            }
        }

        if (preg_match_all("/array\s*\(\s*\\\$this\s*,\s*'([^']+)'\s*\)/", $segment, $cb)) {
            foreach ($cb[1] as $method) {
                if (identifier_has_camel_hump($method)) {
                    $violations[] = "callback {$method}";
                }
            }
        }

        if (preg_match_all('/(?:function|class)\s+([A-Za-z_][A-Za-z0-9_]*)/', $segment, $defs)) {
            foreach ($defs[1] as $name) {
                if ($name === 'setUp' && $relative_path === 'tests/Minifier_Test.php') {
                    continue;
                }
                if (identifier_has_camel_hump($name)) {
                    $violations[] = "definition {$name}";
                }
            }
        }

        if (preg_match_all('/\$([A-Za-z_][A-Za-z0-9_]*)/', $segment, $vars)) {
            foreach ($vars[1] as $name) {
                if (identifier_has_camel_hump($name)) {
                    $violations[] = "variable \${$name}";
                }
            }
        }
    }

    return $violations;
}

/**
 * @param string $path
 * @param string|null $code
 * @param string|null $repo_root
 * @return array<int, string>
 */
function scan_php_file($path, $code = null, $repo_root = null)
{
    if ($repo_root === null) {
        $repo_root = dirname(__DIR__);
    }

    $violations = array();
    if ($code === null) {
        $code = file_get_contents($path);
    }
    if ($code === false) {
        return array('unreadable file');
    }

    $relative_path = relative_repo_path($path, $repo_root);
    $tokens = token_get_all($code);

    for ($i = 0, $count = count($tokens); $i < $count; $i++) {
        $token = $tokens[$i];

        if (!is_array($token) || $token[0] !== T_STRING) {
            continue;
        }

        $name = $token[1];
        if (!identifier_has_camel_hump($name)) {
            continue;
        }

        $line_text = get_line_text($code, $token[2]);
        if (identifier_allowed_at_site($name, $relative_path, $line_text)) {
            continue;
        }

        $violations[] = "token {$name} at line {$token[2]} in {$path}";
    }

    return $violations;
}

/**
 * @param string $path
 * @param string|null $content
 * @param string|null $repo_root
 * @return array<int, string>
 */
function scan_js_file($path, $content = null, $repo_root = null)
{
    if ($repo_root === null) {
        $repo_root = dirname(__DIR__);
    }

    if ($content === null) {
        $content = file_get_contents($path);
    }
    if ($content === false) {
        return array('unreadable js file');
    }

    $relative_path = relative_repo_path($path, $repo_root);
    $violations = array();
    $lines = explode("\n", $content);
    foreach ($lines as $line_no => $line) {
        if (!preg_match_all('/\b([a-z][a-z0-9]*[A-Z][A-Za-z0-9_]*)\b/', $line, $matches)) {
            continue;
        }
        foreach ($matches[1] as $ident) {
            if (!identifier_has_camel_hump($ident)) {
                continue;
            }
            if (identifier_allowed_at_site($ident, $relative_path, $line)) {
                continue;
            }
            $violations[] = "js identifier {$ident} at line " . ($line_no + 1) . " in {$path}";
        }
    }

    return $violations;
}

/**
 * @param array<int, string> $violations
 * @param string $needle
 * @return bool
 */
function violations_contain($violations, $needle)
{
    foreach ($violations as $violation) {
        if (strpos($violation, $needle) !== false) {
            return true;
        }
    }

    return false;
}

/**
 * @param string $repo_root
 * @return bool
 */
function verify_allowlist_demo($repo_root)
{
    $tmpdir = sys_get_temp_dir() . '/cssmin-rename-demo-' . getmypid();
    if (!is_dir($tmpdir) && !mkdir($tmpdir) && !is_dir($tmpdir)) {
        fwrite(STDERR, "rename-check: could not create demo temp dir.\n");
        return false;
    }

    $bad_foo = $tmpdir . '/bad_foo.php';
    file_put_contents($bad_foo, "<?php\n\$foo = new stdClass();\n\$foo->getOpcodes(1, 2);\n");
    if (!violations_contain(scan_php_file($bad_foo, null, $repo_root), 'getOpcodes')) {
        fwrite(STDERR, "rename-check: allowlist demo failed (expected rejection of \$foo->getOpcodes).\n");
        return false;
    }

    $bad_camel = $tmpdir . '/bad_camel.php';
    file_put_contents($bad_camel, "<?php\nfunction myBadCallback() {}\n");
    if (count(scan_php_file($bad_camel, null, $repo_root)) === 0) {
        fwrite(STDERR, "rename-check: allowlist demo failed (expected rejection of generic repo-owned camelCase).\n");
        return false;
    }

    $get_opcodes_line = "\$diff->getOpcodes(\$a, \$b);";
    $snippet = "<?php\n" . $get_opcodes_line . "\n";
    $runner_path = $repo_root . '/tests/bin/runner';
    if (violations_contain(scan_php_file($runner_path, $snippet, $repo_root), 'getOpcodes')) {
        fwrite(STDERR, "rename-check: allowlist demo failed (expected getOpcodes accepted under tests/bin/runner).\n");
        return false;
    }

    $other_path = $repo_root . '/src/__rename_demo_get_opcodes__.php';
    if (!violations_contain(scan_php_file($other_path, $snippet, $repo_root), 'getOpcodes')) {
        fwrite(STDERR, "rename-check: allowlist demo failed (expected identical getOpcodes line rejected outside tests/bin/runner).\n");
        return false;
    }

    $use_finediff_line = 'use cogpowered\\FineDiff\\Diff;';
    $use_snippet = "<?php\n" . $use_finediff_line . "\n";
    if (violations_contain(scan_php_file($runner_path, $use_snippet, $repo_root), 'FineDiff')) {
        fwrite(STDERR, "rename-check: allowlist demo failed (expected FineDiff accepted on use line under tests/bin/runner).\n");
        return false;
    }
    fwrite(STDOUT, "rename-check: allowlist demo OK (accepted FineDiff use at tests/bin/runner)\n");

    $other_use_path = $repo_root . '/src/__rename_demo_finediff_use__.php';
    if (!violations_contain(scan_php_file($other_use_path, $use_snippet, $repo_root), 'FineDiff')) {
        fwrite(STDERR, "rename-check: allowlist demo failed (expected identical FineDiff use line rejected outside tests/bin/runner).\n");
        return false;
    }
    fwrite(STDOUT, "rename-check: allowlist demo OK (rejected FineDiff use outside tests/bin/runner)\n");

    $bad_finediff = $tmpdir . '/bad_finediff.php';
    file_put_contents($bad_finediff, "<?php\n\$x = new FineDiff();\n");
    if (!violations_contain(scan_php_file($bad_finediff, null, $repo_root), 'FineDiff')) {
        fwrite(STDERR, "rename-check: allowlist demo failed (expected rejection of new FineDiff outside listed site).\n");
        return false;
    }
    fwrite(STDOUT, "rename-check: allowlist demo OK (rejected new FineDiff outside listed site)\n");

    $slide_line = "less_console.slideDown('fast');\n";
    $scripts_js = $repo_root . '/gui/scripts.js';
    $other_js = $repo_root . '/gui/__rename_demo__.js';
    if (violations_contain(scan_js_file($scripts_js, $slide_line, $repo_root), 'slideDown')) {
        fwrite(STDERR, "rename-check: allowlist demo failed (expected slideDown accepted in gui/scripts.js).\n");
        return false;
    }
    if (!violations_contain(scan_js_file($other_js, $slide_line, $repo_root), 'slideDown')) {
        fwrite(STDERR, "rename-check: allowlist demo failed (expected slideDown rejected outside gui/scripts.js).\n");
        return false;
    }

    @unlink($bad_foo);
    @unlink($bad_camel);
    @unlink($bad_finediff);
    @rmdir($tmpdir);

    return true;
}

/**
 * @param string $repo_root
 * @param array<string, mixed> $baseline
 * @return bool
 */
function verify_baseline_tests($repo_root, $baseline)
{
    $test_file = $repo_root . '/tests/Minifier_Test.php';
    $code = file_get_contents($test_file);
    if ($code === false) {
        fwrite(STDERR, "rename-check: cannot read Minifier_Test.php.\n");
        return false;
    }

    if (strpos($code, 'markTestSkipped') !== false || strpos($code, 'markTestIncomplete') !== false) {
        fwrite(STDERR, "rename-check: skipped/incomplete tests are not allowed.\n");
        return false;
    }

    if (strpos($code, 'function exec_test') === false || substr_count($code, 'assertEquals') !== 1) {
        fwrite(STDERR, "rename-check: exec_test must contain exactly one assertEquals.\n");
        return false;
    }

    preg_match_all('/public function (test_[a-z0-9_]+)\s*\(/', $code, $methods);
    $found = $methods[1];
    $expected = $baseline['methods'];

    sort($found);
    $expected_sorted = $expected;
    sort($expected_sorted);

    if ($found !== $expected_sorted) {
        fwrite(STDERR, "rename-check: test method baseline mismatch.\n");
        fwrite(STDERR, ' missing: ' . implode(', ', array_diff($expected_sorted, $found)) . "\n");
        fwrite(STDERR, ' extra: ' . implode(', ', array_diff($found, $expected_sorted)) . "\n");
        return false;
    }

    if (count($found) !== (int) $baseline['tests']) {
        fwrite(STDERR, "rename-check: test count mismatch.\n");
        return false;
    }

    return true;
}

$files = collect_files($scan_paths, $repo_root);
$all_violations = array();

foreach ($files as $file) {
    if (substr($file, -3) === '.md') {
        $content = file_get_contents($file);
        if ($content !== false) {
            $all_violations = array_merge($all_violations, find_violations_in_content($content, true));
        }
        continue;
    }

    if (substr($file, -3) === '.js') {
        $all_violations = array_merge($all_violations, scan_js_file($file, null, $repo_root));
        continue;
    }

    if (substr($file, -4) === '.php' || basename($file) === 'runner' || basename($file) === 'cssmin') {
        $all_violations = array_merge($all_violations, scan_php_file($file, null, $repo_root));
        if (strpos($file, $repo_root . '/scripts/') !== 0) {
            $content = file_get_contents($file);
            if ($content !== false) {
                $relative_path = relative_repo_path($file, $repo_root);
                $all_violations = array_merge(
                    $all_violations,
                    find_violations_in_content($content, false, $relative_path)
                );
            }
        }
    }
}

if (count($all_violations) > 0) {
    foreach (array_unique($all_violations) as $violation) {
        fwrite(STDERR, "rename-check: {$violation}\n");
    }
    exit(1);
}

if (!verify_baseline_tests($repo_root, $baseline)) {
    exit(1);
}

if (!verify_allowlist_demo($repo_root)) {
    exit(1);
}

fwrite(
    STDOUT,
    'rename-check: OK (no camelCase repo-owned names; baseline 54 tests, 54 assertions; allowlist demo rejected misplaced getOpcodes)' . "\n"
);
exit(0);
