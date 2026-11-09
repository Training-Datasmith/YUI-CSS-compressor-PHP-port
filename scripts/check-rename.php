<?php

$repo_root = dirname(__DIR__);
$baseline = require __DIR__ . '/upstream-baseline.php';

$scan_paths = array(
    $repo_root . '/src',
    $repo_root . '/tests',
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
);

$css_data_markers = array('sourceMappingURL', 'DXImageTransform');

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
        foreach ($iterator as $fileInfo) {
            /** @var SplFileInfo $fileInfo */
            if (!$fileInfo->isFile()) {
                continue;
            }
            $file = $fileInfo->getPathname();
            if (!file_is_scanned($file, $repo_root)) {
                continue;
            }
            $ext = $fileInfo->getExtension();
            if (in_array($ext, array('css', 'js', 'min', 'css', 'md'))) {
                // js and md included explicitly; css under tests already skipped
                if ($ext === 'css') {
                    continue;
                }
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
 * @param string $line
 * @param string $method
 * @return bool
 */
function line_allows_get_opcodes($line)
{
    return (bool) preg_match('/\$diff\s*->\s*getOpcodes\s*\(/', $line);
}

/**
 * @param string $line
 * @return bool
 */
function line_allows_assert_equals($line, $in_minifier_test)
{
    if (!$in_minifier_test) {
        return false;
    }

    return (bool) preg_match('/\$this\s*->\s*assertEquals\s*\(/', $line);
}

/**
 * @param string $content
 * @param bool $scan_php_blocks_only
 * @return array<int, string>
 */
function find_violations_in_content($content, $scan_php_blocks_only)
{
    global $css_data_markers, $old_names;

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
                if ($name === 'setUp') {
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
 * @return array<int, string>
 */
function scan_php_file($path)
{
    $violations = array();
    $code = file_get_contents($path);
    if ($code === false) {
        return array('unreadable file');
    }

    $tokens = token_get_all($code);
    $in_minifier_test = strpos($path, 'Minifier_Test.php') !== false;

    for ($i = 0, $count = count($tokens); $i < $count; $i++) {
        $token = $tokens[$i];

        if (is_array($token)) {
            if ($token[0] === T_STRING) {
                $name = $token[1];
                if (identifier_has_camel_hump($name)) {
                    $allowed = false;
                    if ($name === 'getOpcodes') {
                        $lineStart = $token[2];
                        $line = '';
                        foreach (explode("\n", $code) as $lineNo => $lineText) {
                            if ($lineNo + 1 === $lineStart) {
                                $line = $lineText;
                                break;
                            }
                        }
                        if (line_allows_get_opcodes($line)) {
                            $allowed = true;
                        }
                    }
                    if ($name === 'assertEquals' && $in_minifier_test) {
                        $lineStart = $token[2];
                        foreach (explode("\n", $code) as $lineNo => $lineText) {
                            if ($lineNo + 1 === $lineStart && line_allows_assert_equals($lineText, true)) {
                                $allowed = true;
                            }
                        }
                    }
                    if ($name === 'setUp' && $in_minifier_test) {
                        $allowed = true;
                    }
                    if (!$allowed && !in_array($name, array('TestCase', 'FineDiff', 'Diff', 'Renderer', 'CliRenderer'), true)) {
                        $violations[] = "token {$name} at line {$token[2]} in {$path}";
                    }
                }
            }
        }
    }

    return $violations;
}

function verify_allowlist_demo()
{
    $snippet = "<?php\n\$foo = new stdClass();\n\$foo->getOpcodes(1, 2);\n";
    $violations = array();
    $tokens = token_get_all($snippet);
    for ($i = 0, $count = count($tokens); $i < $count; $i++) {
        if (!is_array($tokens[$i])) {
            continue;
        }
        if ($tokens[$i][0] === T_STRING && $tokens[$i][1] === 'getOpcodes') {
            $line = '$foo->getOpcodes(1, 2);';
            if (!line_allows_get_opcodes($line)) {
                $violations[] = 'getOpcodes';
            }
        }
    }

    if (count($violations) === 0) {
        fwrite(STDERR, "rename-check: allowlist demo failed (expected rejection of misplaced getOpcodes).\n");
        return false;
    }

    $allowed = line_allows_get_opcodes('$diff->getOpcodes($a, $b);');
    if (!$allowed) {
        fwrite(STDERR, "rename-check: allowlist demo failed (expected acceptance at Diff site).\n");
        return false;
    }

    return true;
}

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
    $expectedSorted = $expected;
    sort($expectedSorted);

    if ($found !== $expectedSorted) {
        fwrite(STDERR, "rename-check: test method baseline mismatch.\n");
        fwrite(STDERR, ' missing: ' . implode(', ', array_diff($expectedSorted, $found)) . "\n");
        fwrite(STDERR, ' extra: ' . implode(', ', array_diff($found, $expectedSorted)) . "\n");
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
        $content = file_get_contents($file);
        if ($content === false) {
            continue;
        }
        if (preg_match_all('/\b([a-z][a-z0-9]*[A-Z][A-Za-z0-9_]*)\b/', $content, $m)) {
            foreach ($m[1] as $ident) {
                $allowed = in_array($ident, array('toCSS', 'slideUp', 'slideDown', 'button', 'preventDefault', 'parseInt'), true);
                if (!$allowed && identifier_has_camel_hump($ident)) {
                    $all_violations[] = "js identifier {$ident} in {$file}";
                }
            }
        }
        continue;
    }

    if (substr($file, -4) === '.php' || basename($file) === 'runner' || basename($file) === 'cssmin') {
        $all_violations = array_merge($all_violations, scan_php_file($file));
        $content = file_get_contents($file);
        if ($content !== false) {
            $all_violations = array_merge($all_violations, find_violations_in_content($content, false));
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

if (!verify_allowlist_demo()) {
    exit(1);
}

fwrite(
    STDOUT,
    'rename-check: OK (no camelCase repo-owned names; baseline 54 tests, 54 assertions; allowlist demo rejected misplaced getOpcodes)' . "\n"
);
exit(0);
