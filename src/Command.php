<?php

declare (strict_types=1);
namespace tubalmartin\Css_Min;

class Command
{
    public const SUCCESS_EXIT = 0;
    public const FAILURE_EXIT = 1;
    protected $stats = [];
    public static function main()
    {
        $command = new self();
        $command->run();
    }
    public function run()
    {
        $opts = getopt('hi:o:', ['help', 'input:', 'output:', 'dry-run', 'keep-sourcemap', 'keep-sourcemap-comment', 'linebreak-position:', 'memory-limit:', 'pcre-backtrack-limit:', 'pcre-recursion-limit:', 'remove-important-comments']);
        $help = $this->get_opt(['h', 'help'], $opts);
        $input = $this->get_opt(['i', 'input'], $opts);
        $output = $this->get_opt(['o', 'output'], $opts);
        $dryrun = $this->get_opt('dry-run', $opts);
        $keep_source_map_comment = $this->get_opt(['keep-sourcemap', 'keep-sourcemap-comment'], $opts);
        $linebreak_position = $this->get_opt('linebreak-position', $opts);
        $memory_limit = $this->get_opt('memory-limit', $opts);
        $backtrack_limit = $this->get_opt('pcre-backtrack-limit', $opts);
        $recursion_limit = $this->get_opt('pcre-recursion-limit', $opts);
        $remove_important_comments = $this->get_opt('remove-important-comments', $opts);
        if (!is_null($help)) {
            $this->show_help();
            die(self::SUCCESS_EXIT);
        }
        if (is_null($input)) {
            fwrite(STDERR, '-i <file> argument is missing' . PHP_EOL);
            $this->show_help();
            die(self::FAILURE_EXIT);
        }
        if (!is_readable($input)) {
            fwrite(STDERR, 'Input file is not readable' . PHP_EOL);
            die(self::FAILURE_EXIT);
        }
        $css = file_get_contents($input);
        if ($css === false) {
            fwrite(STDERR, 'Input CSS code could not be retrieved from input file' . PHP_EOL);
            die(self::FAILURE_EXIT);
        }
        $this->set_stat('original-size', strlen($css));
        $cssmin = new Minifier();
        if (!is_null($keep_source_map_comment)) {
            $cssmin->keep_source_map_comment();
        }
        if (!is_null($remove_important_comments)) {
            $cssmin->remove_important_comments();
        }
        if (!is_null($linebreak_position)) {
            $cssmin->set_line_break_position($linebreak_position);
        }
        if (!is_null($memory_limit)) {
            $cssmin->set_memory_limit($memory_limit);
        }
        if (!is_null($backtrack_limit)) {
            $cssmin->set_pcre_backtrack_limit($backtrack_limit);
        }
        if (!is_null($recursion_limit)) {
            $cssmin->set_pcre_recursion_limit($recursion_limit);
        }
        $this->set_stat('compression-time-start', microtime(true));
        $css = $cssmin->run($css);
        $this->set_stat('compression-time-end', microtime(true));
        $this->set_stat('peak-memory-usage', memory_get_peak_usage(true));
        $this->set_stat('compressed-size', strlen($css));
        if (!is_null($dryrun)) {
            $this->show_stats();
            die(self::SUCCESS_EXIT);
        }
        if (is_null($output)) {
            fwrite(STDOUT, $css . PHP_EOL);
            $this->show_stats();
            die(self::SUCCESS_EXIT);
        }
        if (!is_writable(dirname($output))) {
            fwrite(STDERR, 'Output file is not writable' . PHP_EOL);
            die(self::FAILURE_EXIT);
        }
        if (file_put_contents($output, $css) === false) {
            fwrite(STDERR, 'Compressed CSS code could not be saved to output file' . PHP_EOL);
            die(self::FAILURE_EXIT);
        }
        $this->show_stats();
        die(self::SUCCESS_EXIT);
    }
    protected function get_opt($opts, array $options)
    {
        $value = null;
        if (is_string($opts)) {
            $opts = [$opts];
        }
        foreach ($opts as $opt) {
            if (array_key_exists($opt, $options)) {
                $value = $options[$opt];
                break;
            }
        }
        return $value;
    }
    protected function set_stat($stat_name, $stat_value)
    {
        $this->stats[$stat_name] = $stat_value;
    }
    protected function format_bytes($size, $precision = 2)
    {
        $base = log($size, 1024);
        $suffixes = ['B', 'K', 'M', 'G', 'T'];
        return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
    }
    protected function format_micro_seconds($micro_secs, $precision = 2)
    {
        // ms
        $time = round($micro_secs * 1000, $precision);
        if ($time >= 60 * 1000) {
            $time = round($time / 60 * 1000, $precision) . ' m';
            // m
        } elseif ($time >= 1000) {
            $time = round($time / 1000, $precision) . ' s';
            // s
        } else {
            $time .= ' ms';
        }
        return $time;
    }
    protected function show_stats()
    {
        $space_savings = round((1 - $this->stats['compressed-size'] / $this->stats['original-size']) * 100, 2);
        $compression_ratio = round($this->stats['original-size'] / $this->stats['compressed-size'], 2);
        $compression_time = $this->format_micro_seconds($this->stats['compression-time-end'] - $this->stats['compression-time-start']);
        $peak_memory_usage = $this->format_bytes($this->stats['peak-memory-usage']);
        print <<<EOT
                
        ------------------------------
        CSSMIN STATS        
        ------------------------------ 
        Space savings:       {$space_savings} %       
        Compression ratio:   {$compression_ratio}:1
        Compression time:    {$compression_time}
        Peak memory usage:   {$peak_memory_usage}
        
        
        EOT;
    }
    protected function show_help()
    {
        print <<<'EOT'
        Usage: cssmin [options] -i <file> [-o <file>]
          
          -i|--input <file>              File containing uncompressed CSS code.
          -o|--output <file>             File to use to save compressed CSS code.
            
        Options:
            
          -h|--help                      Prints this usage information.
          --dry-run                      Performs a dry run displaying statistics.
          --keep-sourcemap[-comment]     Keeps the sourcemap special comment in the output.
          --linebreak-position <pos>     Splits long lines after a specific column in the output.
          --memory-limit <limit>         Sets the memory limit for this script.
          --pcre-backtrack-limit <limit> Sets the PCRE backtrack limit for this script.
          --pcre-recursion-limit <limit> Sets the PCRE recursion limit for this script.
          --remove-important-comments    Removes !important comments from output.
        
        EOT;
    }
}