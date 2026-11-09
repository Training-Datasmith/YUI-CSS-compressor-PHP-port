<?php

namespace tubalmartin\Css_Min\Tests;

use tubalmartin\Css_Min\Minifier;
use PHPUnit\Framework\TestCase;

class Minifier_Test extends TestCase
{
    protected $cssmin;

    protected function setUp(): void
    {
        $this->cssmin = new Minifier;
    }

    protected function get_expectation($name)
    {
        return file_get_contents(__DIR__ .'/expectations/'. $name .'.css');
    }

    protected function get_fixture($name)
    {
        return file_get_contents(__DIR__ .'/fixtures/'. $name .'.css');
    }

    protected function exec_test($expectation_name, $fixture_name = null)
    {
        $this->assertEquals(
            $this->get_expectation($expectation_name),
            $this->cssmin->run($this->get_fixture(is_null($fixture_name) ? $expectation_name : $fixture_name))
        );
    }

    public function test_at_rules()
    {
        $this->exec_test('at-rules');
    }

    public function test_attribute_selectors()
    {
        $this->exec_test('attribute-selectors');
    }

    public function test_background_property()
    {
        $this->exec_test('background');
    }

    public function test_background_position_property()
    {
        $this->exec_test('background-position');
    }

    public function test_border_property()
    {
        $this->exec_test('border');
    }

    public function test_calc_function()
    {
        $this->exec_test('calc');
    }

    public function test_colors()
    {
        $this->exec_test('colors');
    }

    public function test_comments()
    {
        $this->exec_test('comments');
    }

    public function test_data_url_base64_double_quotes()
    {
        $this->exec_test('dataurl-base64-doublequotes');
    }

    public function test_data_url_base64_eof()
    {
        $this->exec_test('dataurl-base64-eof');
    }

    public function test_data_url_base64_line_break_in_data()
    {
        $this->exec_test('dataurl-base64-linebreakindata');
    }

    public function test_data_url_base64_no_quotes()
    {
        $this->exec_test('dataurl-base64-noquotes');
    }

    public function test_data_url_base64_single_quotes()
    {
        $this->exec_test('dataurl-base64-singlequotes');
    }

    public function test_data_url_base64_two_urls()
    {
        $this->exec_test('dataurl-base64-twourls');
    }

    public function test_data_url_db_quote_font()
    {
        $this->exec_test('dataurl-dbquote-font');
    }

    public function test_data_url_inline_svg()
    {
        $this->exec_test('dataurl-inline-svg');
    }

    public function test_data_url_non_base64_double_quotes()
    {
        $this->exec_test('dataurl-nonbase64-doublequotes');
    }

    public function test_data_url_non_base64_no_quotes()
    {
        $this->exec_test('dataurl-nonbase64-noquotes');
    }

    public function test_data_url_non_base64_single_quotes()
    {
        $this->exec_test('dataurl-nonbase64-singlequotes');
    }

    public function test_data_url_no_quote_multiline_font()
    {
        $this->exec_test('dataurl-noquote-multiline-font');
    }

    public function test_data_url_real_data_double_quotes()
    {
        $this->exec_test('dataurl-realdata-doublequotes');
    }

    public function test_data_url_real_data_no_quotes()
    {
        $this->exec_test('dataurl-realdata-noquotes');
    }

    public function test_data_url_real_data_single_quotes()
    {
        $this->exec_test('dataurl-realdata-singlequotes');
    }

    public function test_data_url_real_data_yui_app()
    {
        $this->exec_test('dataurl-realdata-yuiapp');
    }

    public function test_data_url_single_quote_font()
    {
        $this->exec_test('dataurl-singlequote-font');
    }

    public function test_empty_rules()
    {
        $this->exec_test('empty-rules');
    }

    public function test_flex_property()
    {
        $this->exec_test('flex');
    }

    public function test_font_weight_property()
    {
        $this->exec_test('font-weight');
    }

    public function test_important_rule()
    {
        $this->exec_test('important');
    }

    public function test_keep_sourcemap_comment()
    {
        $this->cssmin->keep_source_map_comment();
        $this->exec_test('sourcemap-comment-preserve', 'sourcemap-comment');
    }

    public function test_linebreak_position()
    {
        $this->cssmin->set_line_break_position(10);
        $this->exec_test('linebreak-position');
    }

    public function test_linebreak_position_double_newline()
    {
        $this->cssmin->set_line_break_position(1);
        $this->cssmin->keep_source_map_comment();
        $this->exec_test('sourcemap-comment-preserve', 'sourcemap-comment');
    }

    public function test_lowercasing()
    {
        $this->exec_test('lowercasing');
    }

    public function test_numbers()
    {
        $this->exec_test('numbers');
    }

    public function test_old_ie_filters()
    {
        $this->exec_test('old-ie-filters');
    }

    public function test_preserve_case()
    {
        $this->exec_test('preserve-case');
    }

    public function test_pseudo_classes()
    {
        $this->exec_test('pseudo-classes');
    }

    public function test_pseudo_elements()
    {
        $this->exec_test('pseudo-elements');
    }

    public function test_remove_important_comments()
    {
        $this->cssmin->remove_important_comments();
        $this->exec_test('important-comments-remove', 'comments');
    }

    public function test_remove_sourcemap_comment()
    {
        $this->exec_test('sourcemap-comment-remove', 'sourcemap-comment');
    }

    public function test_semicolons()
    {
        $this->exec_test('semicolons');
    }

    public function test_shortenable_properties()
    {
        $this->exec_test('shortenable-properties');
    }

    public function test_star_underscore_hacks()
    {
        $this->exec_test('star-underscore-hacks');
    }

    public function test_strings()
    {
        $this->exec_test('strings');
    }

    public function test_text_shadow_property()
    {
        $this->exec_test('text-shadow');
    }

    public function test_unit_ms()
    {
        $this->exec_test('unit-ms');
    }

    public function test_webkit_transform_origin()
    {
        $this->exec_test('webkit-transform-origin');
    }

    // Frameworks

    public function test_bootstrap()
    {
        $this->exec_test('bootstrap');
    }

    public function test_bulma()
    {
        $this->exec_test('bulma');
    }

    public function test_foundation()
    {
        $this->exec_test('foundation');
    }

    public function test_kube()
    {
        $this->exec_test('kube');
    }

    public function test_materialize()
    {
        $this->exec_test('materialize');
    }

    public function test_mui()
    {
        $this->exec_test('mui');
    }

    public function test_pure()
    {
        $this->exec_test('pure');
    }

}
