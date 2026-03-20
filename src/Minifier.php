<?php

declare (strict_types=1);
/*!
 * CssMin
 * Author: Tubal Martin - http://tubalmartin.me/
 * Repo: https://github.com/tubalmartin/YUI-CSS-compressor-PHP-port
 *
 * This is a PHP port of the CSS minification tool distributed with YUICompressor,
 * itself a port of the cssmin utility by Isaac Schlueter - http://foohack.com/
 * Permission is hereby granted to use the PHP version under the same
 * conditions as the YUICompressor.
 */
/*!
 * YUI Compressor
 * http://developer.yahoo.com/yui/compressor/
 * Author: Julien Lecomte - http://www.julienlecomte.net/
 * Copyright (c) 2013 Yahoo! Inc. All rights reserved.
 * The copyrights embodied in the content of this file are licensed
 * by Yahoo! Inc. under the BSD (revised) open source license.
 */
namespace tubalmartin\Css_Min;

class Minifier
{
    public const QUERY_FRACTION = '_CSSMIN_QF_';
    public const COMMENT_TOKEN = '_CSSMIN_CMT_%d_';
    public const COMMENT_TOKEN_START = '_CSSMIN_CMT_';
    public const RULE_BODY_TOKEN = '_CSSMIN_RBT_%d_';
    public const PRESERVED_TOKEN = '_CSSMIN_PTK_%d_';
    // Token lists
    private $comments = [];
    private $rule_bodies = [];
    private $preserved_tokens = [];
    // Output options
    private $keep_important_comments = true;
    private $keep_source_map_comment = false;
    private $linebreak_position = 0;
    // PHP ini limits
    private $raise_php_limits;
    private $memory_limit;
    private $max_execution_time = 60;
    // 1 min
    private $pcre_backtrack_limit;
    private $pcre_recursion_limit;
    // Color maps
    private $hex_to_named_colors_map;
    private $named_to_hex_colors_map;
    // Regexes
    private $num_regex;
    private $charset_regex = '/@charset [^;]+;/Si';
    private $import_regex = '/@import [^;]+;/Si';
    private $namespace_regex = '/@namespace [^;]+;/Si';
    private $named_to_hex_colors_regex;
    private $shorten_one_zeroes_regex;
    private $shorten_two_zeroes_regex;
    private $shorten_three_zeroes_regex;
    private $shorten_four_zeroes_regex;
    private $units_group_regex = '(?:ch|cm|em|ex|gd|in|mm|px|pt|pc|q|rem|vh|vmax|vmin|vw|%)';
    /**
     * @param bool|int $raisePhpLimits If true, PHP settings will be raised if needed
     */
    public function __construct($raise_php_limits = true)
    {
        $this->raise_php_limits = (bool) $raise_php_limits;
        $this->memory_limit = 128 * 1048576;
        // 128MB in bytes
        $this->pcre_backtrack_limit = 1000 * 1000;
        $this->pcre_recursion_limit = 500 * 1000;
        $this->hex_to_named_colors_map = Colors::get_hex_to_named_map();
        $this->named_to_hex_colors_map = Colors::get_named_to_hex_map();
        $this->named_to_hex_colors_regex = sprintf('/([:,( ])(%s)( |,|\)|;|$)/Si', implode('|', array_keys($this->named_to_hex_colors_map)));
        $this->num_regex = sprintf('-?\d*\.?\d+%s?', $this->units_group_regex);
        $this->set_shorten_zero_values_regexes();
    }
    /**
     * Parses & minifies the given input CSS string
     * @param string $css
     * @return string
     */
    public function run($css = '')
    {
        if (empty($css) || !is_string($css)) {
            return '';
        }
        $this->reset_run_properties();
        if ($this->raise_php_limits) {
            $this->do_raise_php_limits();
        }
        return $this->minify($css);
    }
    /**
     * Sets whether to keep or remove sourcemap special comment.
     * Sourcemap comments are removed by default.
     * @param bool $keepSourceMapComment
     */
    public function keep_source_map_comment($keep_source_map_comment = true)
    {
        $this->keep_source_map_comment = (bool) $keep_source_map_comment;
    }
    /**
     * Sets whether to keep or remove important comments.
     * Important comments outside of a declaration block are kept by default.
     * @param bool $removeImportantComments
     */
    public function remove_important_comments($remove_important_comments = true)
    {
        $this->keep_important_comments = !(bool) $remove_important_comments;
    }
    /**
     * Sets the approximate column after which long lines will be splitted in the output
     * with a linebreak.
     * @param int $position
     */
    public function set_line_break_position($position)
    {
        $this->linebreak_position = (int) $position;
    }
    /**
     * Sets the memory limit for this script
     * @param int|string $limit
     */
    public function set_memory_limit($limit)
    {
        $this->memory_limit = Utils::normalize_int($limit);
    }
    /**
     * Sets the maximum execution time for this script
     * @param int|string $seconds
     */
    public function set_max_execution_time($seconds)
    {
        $this->max_execution_time = (int) $seconds;
    }
    /**
     * Sets the PCRE backtrack limit for this script
     * @param int $limit
     */
    public function set_pcre_backtrack_limit($limit)
    {
        $this->pcre_backtrack_limit = (int) $limit;
    }
    /**
     * Sets the PCRE recursion limit for this script
     * @param int $limit
     */
    public function set_pcre_recursion_limit($limit)
    {
        $this->pcre_recursion_limit = (int) $limit;
    }
    /**
     * Builds regular expressions needed for shortening zero values
     */
    private function set_shorten_zero_values_regexes()
    {
        $zero_regex = '0' . $this->units_group_regex;
        $num_or_pos_regex = '(' . $this->num_regex . '|top|left|bottom|right|center) ';
        $one_zero_safe_properties = ['(?:line-)?height', '(?:(?:min|max)-)?width', 'top', 'left', 'background-position', 'bottom', 'right', 'border(?:-(?:top|left|bottom|right))?(?:-width)?', 'border-(?:(?:top|bottom)-(?:left|right)-)?radius', 'column-(?:gap|width)', 'margin(?:-(?:top|left|bottom|right))?', 'outline-width', 'padding(?:-(?:top|left|bottom|right))?'];
        // First zero regex
        $regex = '/(^|;)(' . implode('|', $one_zero_safe_properties) . '):%s/Si';
        $this->shorten_one_zeroes_regex = sprintf($regex, $zero_regex);
        // Multiple zeroes regexes
        $regex = '/(^|;)(margin|padding|border-(?:width|radius)|background-position):%s/Si';
        $this->shorten_two_zeroes_regex = sprintf($regex, $num_or_pos_regex . $zero_regex);
        $this->shorten_three_zeroes_regex = sprintf($regex, $num_or_pos_regex . $num_or_pos_regex . $zero_regex);
        $this->shorten_four_zeroes_regex = sprintf($regex, $num_or_pos_regex . $num_or_pos_regex . $num_or_pos_regex . $zero_regex);
    }
    /**
     * Resets properties whose value may change between runs
     */
    private function reset_run_properties()
    {
        $this->comments = [];
        $this->rule_bodies = [];
        $this->preserved_tokens = [];
    }
    /**
     * Tries to configure PHP to use at least the suggested minimum settings
     * @return void
     */
    private function do_raise_php_limits()
    {
        $php_limits = ['memory_limit' => $this->memory_limit, 'max_execution_time' => $this->max_execution_time, 'pcre.backtrack_limit' => $this->pcre_backtrack_limit, 'pcre.recursion_limit' => $this->pcre_recursion_limit];
        // If current settings are higher respect them.
        foreach ($php_limits as $name => $suggested) {
            $current = Utils::normalize_int(ini_get($name));
            if ($current >= $suggested) {
                continue;
            }
            // memoryLimit exception: allow -1 for "no memory limit".
            if ($name === 'memory_limit' && $current === -1) {
                continue;
            }
            // maxExecutionTime exception: allow 0 for "no memory limit".
            if ($name === 'max_execution_time' && $current === 0) {
                continue;
            }
            ini_set($name, $suggested);
        }
    }
    /**
     * Registers a preserved token
     * @param string $token
     * @return string The token ID string
     */
    private function register_preserved_token($token)
    {
        $token_id = sprintf(self::PRESERVED_TOKEN, count($this->preserved_tokens));
        $this->preserved_tokens[$token_id] = $token;
        return $token_id;
    }
    /**
     * Registers a candidate comment token
     * @param string $comment
     * @return string The comment token ID string
     */
    private function register_comment_token($comment)
    {
        $token_id = sprintf(self::COMMENT_TOKEN, count($this->comments));
        $this->comments[$token_id] = $comment;
        return $token_id;
    }
    /**
     * Registers a rule body token
     * @param string $body the minified rule body
     * @return string The rule body token ID string
     */
    private function register_rule_body_token($body)
    {
        if (empty($body)) {
            return '';
        }
        $token_id = sprintf(self::RULE_BODY_TOKEN, count($this->rule_bodies));
        $this->rule_bodies[$token_id] = $body;
        return $token_id;
    }
    /**
     * Parses & minifies the given input CSS string
     * @param string $css
     * @return string
     */
    private function minify($css)
    {
        // Process data urls
        $css = $this->process_data_urls($css);
        // Process comments
        $css = preg_replace_callback('/(?<!\\\\)\/\*(.*?)\*(?<!\\\\)\//Ss', [$this, 'processCommentsCallback'], $css);
        // IE7: Process Microsoft matrix filters (whitespaces between Matrix parameters). Can contain strings inside.
        $css = preg_replace_callback('/filter:\s*progid:DXImageTransform\.Microsoft\.Matrix\(([^)]+)\)/Ss', [$this, 'processOldIeSpecificMatrixDefinitionCallback'], $css);
        // Process quoted unquotable attribute selectors to unquote them. Covers most common cases.
        // Likelyhood of a quoted attribute selector being a substring in a string: Very very low.
        $css = preg_replace('/\[\s*([a-z][a-z-]+)\s*([\*\|\^\$~]?=)\s*[\'"](-?[a-z_][a-z0-9-_]+)[\'"]\s*\]/Ssi', '[$1$2$3]', $css);
        // Process strings so their content doesn't get accidentally minified
        $css = preg_replace_callback('/(?:"(?:[^\\\\"]|\\\\.|\\\\)*")|' . "(?:'(?:[^\\\\']|\\\\.|\\\\)*')/S", [$this, 'processStringsCallback'], $css);
        // Normalize all whitespace strings to single spaces. Easier to work with that way.
        $css = preg_replace('/\s+/S', ' ', $css);
        // Process import At-rules with unquoted URLs so URI reserved characters such as a semicolon may be used safely.
        $css = preg_replace_callback('/@import url\(([^\'"]+?)\)( |;)/Si', [$this, 'processImportUnquotedUrlAtRulesCallback'], $css);
        // Process comments
        $css = $this->process_comments($css);
        // Process rule bodies
        $css = $this->process_rule_bodies($css);
        // Process at-rules and selectors
        $css = $this->process_at_rules_and_selectors($css);
        // Restore preserved rule bodies before splitting
        $css = strtr($css, $this->rule_bodies);
        // Split long lines in output if required
        $css = $this->process_long_line_splitting($css);
        // Restore preserved comments and strings
        $css = strtr($css, $this->preserved_tokens);
        return trim($css);
    }
    /**
     * Searches & replaces all data urls with tokens before we start compressing,
     * to avoid performance issues running some of the subsequent regexes against large string chunks.
     * @param string $css
     * @return string
     */
    private function process_data_urls($css)
    {
        $ret = '';
        $search_offset = $substr_offset = 0;
        // Since we need to account for non-base64 data urls, we need to handle
        // ' and ) being part of the data string.
        while (preg_match('/url\(\s*(["\']?)data:/Si', $css, $m, PREG_OFFSET_CAPTURE, $search_offset)) {
            $match_start_index = $m[0][1];
            $data_start_index = $match_start_index + 4;
            // url( length
            $search_offset = $match_start_index + strlen($m[0][0]);
            $terminator = $m[1][0];
            // ', " or empty (not quoted)
            $terminator_regex = '/(?<!\\\\)' . (strlen($terminator) === 0 ? '' : $terminator . '\s*') . '(\))/S';
            $ret .= substr($css, $substr_offset, $match_start_index - $substr_offset);
            // Terminator found
            if (preg_match($terminator_regex, $css, $matches, PREG_OFFSET_CAPTURE, $search_offset)) {
                $match_end_index = $matches[1][1];
                $search_offset = $match_end_index + 1;
                $token = substr($css, $data_start_index, $match_end_index - $data_start_index);
                // Remove all spaces only for base64 encoded URLs.
                if (stripos($token, 'base64,') !== false) {
                    $token = preg_replace('/\s+/S', '', $token);
                }
                $ret .= 'url(' . $this->register_preserved_token(trim($token)) . ')';
                // No end terminator found, re-add the whole match. Should we throw/warn here?
            } else {
                $ret .= substr($css, $match_start_index, $search_offset - $match_start_index);
            }
            $substr_offset = $search_offset;
        }
        return $ret . substr($css, $substr_offset);
    }
    /**
     * Registers all comments found as candidates to be preserved.
     * @return string
     */
    private function process_comments_callback(array $matches)
    {
        return '/*' . $this->register_comment_token($matches[1]) . '*/';
    }
    /**
     * Preserves old IE Matrix string definition
     * @return string
     */
    private function process_old_ie_specific_matrix_definition_callback(array $matches)
    {
        return 'filter:progid:DXImageTransform.Microsoft.Matrix(' . $this->register_preserved_token($matches[1]) . ')';
    }
    /**
     * Preserves strings found
     * @return string
     */
    private function process_strings_callback(array $matches)
    {
        $match = $matches[0];
        $quote = substr($match, 0, 1);
        $match = substr($match, 1, -1);
        // maybe the string contains a comment-like substring?
        // one, maybe more? put'em back then
        if (strpos($match, self::COMMENT_TOKEN_START) !== false) {
            $match = strtr($match, $this->comments);
        }
        // minify alpha opacity in filter strings
        $match = str_ireplace('progid:DXImageTransform.Microsoft.Alpha(Opacity=', 'alpha(opacity=', $match);
        return $quote . $this->register_preserved_token($match) . $quote;
    }
    /**
     * Searches & replaces all import at-rule unquoted urls with tokens so URI reserved characters such as a semicolon
     * may be used safely in a URL.
     * @return string
     */
    private function process_import_unquoted_url_at_rules_callback(array $matches)
    {
        return '@import url(' . $this->register_preserved_token($matches[1]) . ')' . $matches[2];
    }
    /**
     * Preserves or removes comments found.
     * @param string $css
     * @return string
     */
    private function process_comments($css)
    {
        foreach ($this->comments as $comment_id => $comment) {
            $comment_id_string = '/*' . $comment_id . '*/';
            // ! in the first position of the comment means preserve
            // so push to the preserved tokens keeping the !
            if ($this->keep_important_comments && strpos($comment, '!') === 0) {
                $preserved_token_id = $this->register_preserved_token($comment);
                // Put new lines before and after /*! important comments
                $css = str_replace($comment_id_string, "\n/*{$preserved_token_id}*/\n", $css);
                continue;
            }
            // # sourceMappingURL= in the first position of the comment means sourcemap
            // so push to the preserved tokens if {$this->keepSourceMapComment} is truthy.
            if ($this->keep_source_map_comment && strpos($comment, '# sourceMappingURL=') === 0) {
                $preserved_token_id = $this->register_preserved_token($comment);
                // Add new line before the sourcemap comment
                $css = str_replace($comment_id_string, "\n/*{$preserved_token_id}*/", $css);
                continue;
            }
            // Keep empty comments after child selectors (IE7 hack)
            // e.g. html >/**/ body
            if (strlen($comment) === 0 && strpos($css, '>/*' . $comment_id) !== false) {
                $css = str_replace($comment_id, $this->register_preserved_token(''), $css);
                continue;
            }
            // in all other cases kill the comment
            $css = str_replace($comment_id_string, '', $css);
        }
        // Normalize whitespace again
        $css = preg_replace('/ +/S', ' ', $css);
        return $css;
    }
    /**
     * Finds, minifies & preserves all rule bodies.
     * @param string $css the whole stylesheet.
     * @return string
     */
    private function process_rule_bodies($css)
    {
        $ret = '';
        $search_offset = $substr_offset = 0;
        while (($block_start_pos = strpos($css, '{', $search_offset)) !== false) {
            $block_end_pos = strpos($css, '}', $block_start_pos);
            $next_block_start_pos = strpos($css, '{', $block_start_pos + 1);
            $ret .= substr($css, $substr_offset, $block_start_pos - $substr_offset);
            if ($next_block_start_pos !== false && $next_block_start_pos < $block_end_pos) {
                $ret .= substr($css, $block_start_pos, $next_block_start_pos - $block_start_pos);
                $search_offset = $next_block_start_pos;
            } else {
                $rule_body = substr($css, $block_start_pos + 1, $block_end_pos - $block_start_pos - 1);
                $rule_body_token = $this->register_rule_body_token($this->process_rule_body($rule_body));
                $ret .= '{' . $rule_body_token . '}';
                $search_offset = $block_end_pos + 1;
            }
            $substr_offset = $search_offset;
        }
        return $ret . substr($css, $substr_offset);
    }
    /**
     * Compresses non-group rule bodies.
     * @param string $body The rule body without curly braces
     * @return string
     */
    private function process_rule_body($body)
    {
        $body = trim($body);
        // Remove spaces before the things that should not have spaces before them.
        $body = preg_replace('/ ([:=,)*\/;\n])/S', '$1', $body);
        // Remove the spaces after the things that should not have spaces after them.
        $body = preg_replace('/([:=,(*\/!;\n]) /S', '$1', $body);
        // Replace multiple semi-colons in a row by a single one
        $body = preg_replace('/;;+/S', ';', $body);
        // Remove semicolon before closing brace except when:
        // - The last property is prefixed with a `*` (lte IE7 hack) to avoid issues on Symbian S60 3.x browsers.
        if (!preg_match('/\*[a-z0-9-]+:[^;]+;$/Si', $body)) {
            $body = rtrim($body, ';');
        }
        // Remove important comments inside a rule body (because they make no sense here).
        if (strpos($body, '/*') !== false) {
            $body = preg_replace('/\n?\/\*[A-Z0-9_]+\*\/\n?/S', '', $body);
        }
        // Empty rule body? Exit :)
        if (empty($body)) {
            return '';
        }
        // Shorten font-weight values
        $body = preg_replace(['/(font-weight:)bold\b/Si', '/(font-weight:)normal\b/Si'], ['${1}700', '${1}400'], $body);
        // Shorten background property
        $body = preg_replace('/(background:)(?:none|transparent)( !|;|$)/Si', '${1}0 0$2', $body);
        // Shorten opacity IE filter
        $body = str_ireplace('progid:DXImageTransform.Microsoft.Alpha(Opacity=', 'alpha(opacity=', $body);
        // Shorten colors from rgb(51,102,153) to #336699, rgb(100%,0%,0%) to #ff0000 (sRGB color space)
        // Shorten colors from hsl(0, 100%, 50%) to #ff0000 (sRGB color space)
        // This makes it more likely that it'll get further compressed in the next step.
        $body = preg_replace_callback('/(rgb|hsl)\(([0-9,.% -]+)\)(.|$)/Si', [$this, 'shortenHslAndRgbToHexCallback'], $body);
        // Shorten colors from #AABBCC to #ABC or shorter color name:
        // - Look for hex colors which don't have a "=" in front of them (to avoid MSIE filters)
        $body = preg_replace_callback('/(?<!=)#([0-9a-f]{3,6})( |,|\)|;|$)/Si', [$this, 'shortenHexColorsCallback'], $body);
        // Shorten long named colors with a shorter HEX counterpart: white -> #fff.
        // Run at least 2 times to cover most cases
        $body = preg_replace_callback([$this->named_to_hex_colors_regex, $this->named_to_hex_colors_regex], [$this, 'shortenNamedColorsCallback'], $body);
        // Replace positive sign from numbers before the leading space is removed.
        // +1.2em to 1.2em, +.8px to .8px, +2% to 2%
        $body = preg_replace('/([ :,(])\+(\.?\d+)/S', '$1$2', $body);
        // shorten ms to s
        $body = preg_replace_callback('/([ :,(])(-?)(\d{3,})ms/Si', function (array $matches) {
            return $matches[1] . $matches[2] . (int) $matches[3] / 1000 . 's';
        }, $body);
        // Remove leading zeros from integer and float numbers.
        // 000.6 to .6, -0.8 to -.8, 0050 to 50, -01.05 to -1.05
        $body = preg_replace('/([ :,(])(-?)0+([1-9]?\.?\d+)/S', '$1$2$3', $body);
        // Remove trailing zeros from float numbers.
        // -6.0100em to -6.01em, .0100 to .01, 1.200px to 1.2px
        $body = preg_replace('/([ :,(])(-?\d?\.\d+?)0+([^\d])/S', '$1$2$3', $body);
        // Remove trailing .0 -> -9.0 to -9
        $body = preg_replace('/([ :,(])(-?\d+)\.0([^\d])/S', '$1$2$3', $body);
        // Replace 0 length numbers with 0
        $body = preg_replace('/([ :,(])-?\.?0+([^\d])/S', '${1}0$2', $body);
        // Shorten zero values for safe properties only
        $body = preg_replace([$this->shorten_one_zeroes_regex, $this->shorten_two_zeroes_regex, $this->shorten_three_zeroes_regex, $this->shorten_four_zeroes_regex], ['$1$2:0', '$1$2:$3 0', '$1$2:$3 $4 0', '$1$2:$3 $4 $5 0'], $body);
        // Replace 0 0 0; or 0 0 0 0; with 0 0 for background-position property.
        $body = preg_replace('/(background-position):0(?: 0){2,3}( !|;|$)/Si', '$1:0 0$2', $body);
        // Shorten suitable shorthand properties with repeated values
        $body = preg_replace(['/(margin|padding|border-(?:width|radius)):(' . $this->num_regex . ')(?: \2)+( !|;|$)/Si', '/(border-(?:style|color)):([#a-z0-9]+)(?: \2)+( !|;|$)/Si'], '$1:$2$3', $body);
        $body = preg_replace(['/(margin|padding|border-(?:width|radius)):' . '(' . $this->num_regex . ') (' . $this->num_regex . ') \2 \3( !|;|$)/Si', '/(border-(?:style|color)):([#a-z0-9]+) ([#a-z0-9]+) \2 \3( !|;|$)/Si'], '$1:$2 $3$4', $body);
        $body = preg_replace(['/(margin|padding|border-(?:width|radius)):' . '(' . $this->num_regex . ') (' . $this->num_regex . ') (' . $this->num_regex . ') \3( !|;|$)/Si', '/(border-(?:style|color)):([#a-z0-9]+) ([#a-z0-9]+) ([#a-z0-9]+) \3( !|;|$)/Si'], '$1:$2 $3 $4$5', $body);
        // Lowercase some common functions that can be values
        $body = preg_replace_callback('/(?:attr|blur|brightness|circle|contrast|cubic-bezier|drop-shadow|ellipse|from|grayscale|' . 'hsla?|hue-rotate|inset|invert|local|minmax|opacity|perspective|polygon|rgba?|rect|repeat|saturate|sepia|' . 'steps|to|url|var|-webkit-gradient|' . '(?:-(?:atsc|khtml|moz|ms|o|wap|webkit)-)?(?:calc|(?:repeating-)?(?:linear|radial)-gradient))\(/Si', [$this, 'strtolowerCallback'], $body);
        // Lowercase all uppercase properties
        $body = preg_replace_callback('/(?:^|;)[A-Z-]+:/S', [$this, 'strtolowerCallback'], $body);
        return $body;
    }
    /**
     * Compresses At-rules and selectors.
     * @param string $css the whole stylesheet with rule bodies tokenized.
     * @return string
     */
    private function process_at_rules_and_selectors($css)
    {
        $charset = '';
        $imports = '';
        $namespaces = '';
        // Remove spaces before the things that should not have spaces before them.
        $css = preg_replace('/ ([@{};>+)\]~=,\/\n])/S', '$1', $css);
        // Remove the spaces after the things that should not have spaces after them.
        $css = preg_replace('/([{}:;>+(\[~=,\/\n]) /S', '$1', $css);
        // Shorten shortable double colon (CSS3) pseudo-elements to single colon (CSS2)
        $css = preg_replace('/::(before|after|first-(?:line|letter))(\{|,)/Si', ':$1$2', $css);
        // Retain space for special IE6 cases
        $css = preg_replace_callback('/:first-(line|letter)(\{|,)/Si', function (array $matches) {
            return ':first-' . strtolower($matches[1]) . ' ' . $matches[2];
        }, $css);
        // Find a fraction that may used in some @media queries such as: (min-aspect-ratio: 1/1)
        // Add token to add the "/" back in later
        $css = preg_replace('/\(([a-z-]+):([0-9]+)\/([0-9]+)\)/Si', '($1:$2' . self::QUERY_FRACTION . '$3)', $css);
        // Remove empty rule blocks up to 2 levels deep.
        $css = preg_replace(array_fill(0, 2, '/(\{)[^{};\/\n]+\{\}/S'), '$1', $css);
        $css = preg_replace('/[^{};\/\n]+\{\}/S', '', $css);
        // Two important comments next to each other? Remove extra newline.
        if ($this->keep_important_comments) {
            $css = str_replace("\n\n", "\n", $css);
        }
        // Restore fraction
        $css = str_replace(self::QUERY_FRACTION, '/', $css);
        // Lowercase some popular @directives
        $css = preg_replace_callback('/(?<!\\\\)@(?:charset|document|font-face|import|(?:-(?:atsc|khtml|moz|ms|o|wap|webkit)-)?keyframes|media|' . 'namespace|page|supports|viewport)/Si', [$this, 'strtolowerCallback'], $css);
        // Lowercase some popular media types
        $css = preg_replace_callback('/[ ,](?:all|aural|braille|handheld|print|projection|screen|tty|tv|embossed|speech)[ ,;{]/Si', [$this, 'strtolowerCallback'], $css);
        // Lowercase some common pseudo-classes & pseudo-elements
        $css = preg_replace_callback('/(?<!\\\\):(?:active|after|before|checked|default|disabled|empty|enabled|first-(?:child|of-type)|' . 'focus(?:-within)?|hover|indeterminate|in-range|invalid|lang\(|last-(?:child|of-type)|left|link|not\(|' . 'nth-(?:child|of-type)\(|nth-last-(?:child|of-type)\(|only-(?:child|of-type)|optional|out-of-range|' . 'read-(?:only|write)|required|right|root|:selection|target|valid|visited)/Si', [$this, 'strtolowerCallback'], $css);
        // @charset handling
        if (preg_match($this->charset_regex, $css, $matches)) {
            // Keep the first @charset at-rule found
            $charset = $matches[0];
            // Delete all @charset at-rules
            $css = preg_replace($this->charset_regex, '', $css);
        }
        // @import handling
        $css = preg_replace_callback($this->import_regex, function (array $matches) use (&$imports) {
            // Keep all @import at-rules found for later
            $imports .= $matches[0];
            // Delete all @import at-rules
            return '';
        }, $css);
        // @namespace handling
        $css = preg_replace_callback($this->namespace_regex, function (array $matches) use (&$namespaces) {
            // Keep all @namespace at-rules found for later
            $namespaces .= $matches[0];
            // Delete all @namespace at-rules
            return '';
        }, $css);
        // Order critical at-rules:
        // 1. @charset first
        // 2. @imports below @charset
        // 3. @namespaces below @imports
        $css = $charset . $imports . $namespaces . $css;
        return $css;
    }
    /**
     * Splits long lines after a specific column.
     *
     * Some source control tools don't like it when files containing lines longer
     * than, say 8000 characters, are checked in. The linebreak option is used in
     * that case to split long lines after a specific column.
     *
     * @param string $css the whole stylesheet.
     * @return string
     */
    private function process_long_line_splitting($css)
    {
        if ($this->linebreak_position > 0) {
            $l = strlen($css);
            $offset = $this->linebreak_position;
            while (preg_match('/(?<!\\\\)\}(?!\n)/S', $css, $matches, PREG_OFFSET_CAPTURE, $offset)) {
                $match_index = $matches[0][1];
                $css = substr_replace($css, "\n", $match_index + 1, 0);
                $offset = $match_index + 2 + $this->linebreak_position;
                $l += 1;
                if ($offset > $l) {
                    break;
                }
            }
        }
        return $css;
    }
    /**
     * Converts hsl() & rgb() colors to HEX format.
     * @param $matches
     * @return string
     */
    private function shorten_hsl_and_rgb_to_hex_callback($matches)
    {
        $type = $matches[1];
        $values = explode(',', $matches[2]);
        $terminator = $matches[3];
        if ($type === 'hsl') {
            $values = Utils::hsl_to_rgb($values);
        }
        $hex_colors = Utils::rgb_to_hex($values);
        // Restore space after rgb() or hsl() function in some cases such as:
        // background-image: linear-gradient(to bottom, rgb(210,180,140) 10%, rgb(255,0,0) 90%);
        if (!empty($terminator) && !preg_match('/[ ,);]/S', $terminator)) {
            $terminator = ' ' . $terminator;
        }
        return '#' . implode('', $hex_colors) . $terminator;
    }
    /**
     * Compresses HEX color values of the form #AABBCC to #ABC or short color name.
     * @param $matches
     * @return string
     */
    private function shorten_hex_colors_callback($matches)
    {
        $hex = $matches[1];
        // Shorten suitable 6 chars HEX colors
        if (strlen($hex) === 6 && preg_match('/^([0-9a-f])\1([0-9a-f])\2([0-9a-f])\3$/Si', $hex, $m)) {
            $hex = $m[1] . $m[2] . $m[3];
        }
        // Lowercase
        $hex = '#' . strtolower($hex);
        // Replace Hex colors with shorter color names
        $color = array_key_exists($hex, $this->hex_to_named_colors_map) ? $this->hex_to_named_colors_map[$hex] : $hex;
        return $color . $matches[2];
    }
    /**
     * Shortens all named colors with a shorter HEX counterpart for a set of safe properties
     * e.g. white -> #fff
     * @return string
     */
    private function shorten_named_colors_callback(array $matches)
    {
        return $matches[1] . $this->named_to_hex_colors_map[strtolower($matches[2])] . $matches[3];
    }
    /**
     * Makes a string lowercase
     * @return string
     */
    private function strtolower_callback(array $matches)
    {
        return strtolower($matches[0]);
    }
}