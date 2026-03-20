<?php

declare (strict_types=1);
namespace tubalmartin\Css_Min;

class Utils
{
    /**
     * Clamps a number between a minimum and a maximum value.
     * @param int|float $n the number to clamp
     * @param int|float $min the lower end number allowed
     * @param int|float $max the higher end number allowed
     * @return int|float
     */
    public static function clamp_number($n, $min, $max)
    {
        return min(max($n, $min), $max);
    }
    /**
     * Clamps a RGB color number outside the sRGB color space
     * @param int|float $n the number to clamp
     * @return int|float
     */
    public static function clamp_number_srgb($n)
    {
        return self::clamp_number($n, 0, 255);
    }
    /**
     * Converts a HSL color into a RGB color
     * @return array
     */
    public static function hsl_to_rgb(array $hsl_values)
    {
        $h = floatval($hsl_values[0]);
        $s = floatval(str_replace('%', '', $hsl_values[1]));
        $l = floatval(str_replace('%', '', $hsl_values[2]));
        // Wrap and clamp, then fraction!
        $h = ($h % 360 + 360) % 360 / 360;
        $s = self::clamp_number($s, 0, 100) / 100;
        $l = self::clamp_number($l, 0, 100) / 100;
        if ($s == 0) {
            $r = $g = $b = self::round_number(255 * $l);
        } else {
            $v2 = $l < 0.5 ? $l * (1 + $s) : $l + $s - $s * $l;
            $v1 = 2 * $l - $v2;
            $r = self::round_number(255 * self::hue_to_rgb($v1, $v2, $h + 1 / 3));
            $g = self::round_number(255 * self::hue_to_rgb($v1, $v2, $h));
            $b = self::round_number(255 * self::hue_to_rgb($v1, $v2, $h - 1 / 3));
        }
        return [$r, $g, $b];
    }
    /**
     * Tests and selects the correct formula for each RGB color channel
     * @param $v1
     * @param $v2
     * @param $vh
     * @return mixed
     */
    public static function hue_to_rgb($v1, $v2, $vh)
    {
        $vh = $vh < 0 ? $vh + 1 : ($vh > 1 ? $vh - 1 : $vh);
        if ($vh * 6 < 1) {
            return $v1 + ($v2 - $v1) * 6 * $vh;
        }
        if ($vh * 2 < 1) {
            return $v2;
        }
        if ($vh * 3 < 2) {
            return $v1 + ($v2 - $v1) * (2 / 3 - $vh) * 6;
        }
        return $v1;
    }
    /**
     * Convert strings like "64M" or "30" to int values
     * @param mixed $size
     * @return int
     */
    public static function normalize_int($size)
    {
        if (is_string($size)) {
            $letter = substr($size, -1);
            $size = intval($size);
            switch ($letter) {
                case 'M':
                case 'm':
                    return $size * 1048576;
                case 'K':
                case 'k':
                    return $size * 1024;
                case 'G':
                case 'g':
                    return $size * 1073741824;
            }
        }
        return (int) $size;
    }
    /**
     * Converts a string containing and RGB percentage value into a RGB integer value i.e. '90%' -> 229.5
     * @param $rgbPercentage
     * @return int
     */
    public static function rgb_percentage_to_rgb_integer($rgb_percentage)
    {
        if (strpos($rgb_percentage, '%') !== false) {
            $rgb_percentage = self::round_number(floatval(str_replace('%', '', $rgb_percentage)) * 2.55);
        }
        return intval($rgb_percentage, 10);
    }
    /**
     * Converts a RGB color into a HEX color
     * @return array
     */
    public static function rgb_to_hex(array $rgb_colors)
    {
        $hex_colors = [];
        // Values outside the sRGB color space should be clipped (0-255)
        for ($i = 0, $l = count($rgb_colors); $i < $l; $i++) {
            $hex_colors[$i] = sprintf('%02x', self::clamp_number_srgb(self::rgb_percentage_to_rgb_integer($rgb_colors[$i])));
        }
        return $hex_colors;
    }
    /**
     * Rounds a number to its closest integer
     * @param $n
     * @return int
     */
    public static function round_number($n)
    {
        return intval(round(floatval($n)), 10);
    }
}