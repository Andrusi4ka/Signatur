<?php
/**
 * Checks without WordPress or a database: settings cleaning, rendering,
 * roles, the WPBakery element, versions and translations. Run with
 * `php tests/run.php` (CI: .github/workflows/tests.yml); exits 1 on a
 * failure.
 */

define('ABSPATH', __DIR__ . '/');
define('SIGNATUR_PLUGIN_FILE', dirname(__DIR__) . '/signatur.php');
define('SIGNATUR_PLUGIN_DIR', dirname(__DIR__) . '/');
define('SIGNATUR_VERSION', 'test');

// --- WordPress, as far as the plugin's functions need it ---------------------

function __($s, $d = '') { return $s; }
function esc_html__($s, $d = '') { return esc_html($s); }
function esc_attr__($s, $d = '') { return esc_attr($s); }
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_html($s) { return esc_attr($s); }
function esc_js($s) { return addslashes((string) $s); }
function esc_url($s) { return esc_attr(esc_url_raw($s)); }
// As WordPress's: '' for a link in another protocol (javascript:, data:).
function esc_url_raw($s, $protocols = null)
{
    $s = trim((string) $s);
    $ok = $protocols ?: array('http', 'https', 'mailto', 'tel', 'ftp');

    if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $s, $m) && ! in_array(strtolower($m[1]), $ok, true)) {
        return '';
    }

    return str_replace(array('"', "'", '<', '>', ' '), '', $s);
}
function sanitize_text_field($s) { return trim(preg_replace('/[\r\n\t ]+/', ' ', strip_tags((string) $s))); }
function sanitize_textarea_field($s) { return trim(strip_tags((string) $s)); }
function absint($n) { return abs((int) $n); }
function wp_json_encode($v, $flags = 0) { return json_encode($v, $flags); }
function plugins_url($path, $file = '') { return 'https://example.test/wp-content/plugins/signatur/' . $path; }
function admin_url($path = '') { return 'https://example.test/wp-admin/' . $path; }
function add_query_arg($args, $url) { return $url . (false === strpos($url, '?') ? '?' : '&') . http_build_query($args); }
function wp_create_nonce($a) { return 'nonce'; }
function shortcode_atts($pairs, $atts, $tag = '') { return array_merge($pairs, array_intersect_key((array) $atts, $pairs)); }
function wp_enqueue_style($h) { $GLOBALS['enqueued'][] = $h; }
function wp_enqueue_script($h) { $GLOBALS['enqueued'][] = $h; }
function wp_localize_script($h, $o, $d) {}
function get_option($name, $default = false) { return isset($GLOBALS['options'][$name]) ? $GLOBALS['options'][$name] : $default; }

// Roles: administrator manages the site, the others do not.
class Test_Role
{
    public $caps;
    public function __construct($caps) { $this->caps = $caps; }
    public function has_cap($cap) { return ! empty($this->caps[$cap]); }
}
function wp_roles()
{
    return (object) array('roles' => array(
        'administrator' => array('name' => 'Administrator'),
        'editor'        => array('name' => 'Editor'),
        'author'        => array('name' => 'Author'),
        'subscriber'    => array('name' => 'Subscriber'),
    ));
}
function get_role($role)
{
    $roles = wp_roles()->roles;

    return isset($roles[$role]) ? new Test_Role('administrator' === $role ? array('manage_options' => true) : array()) : null;
}
// The capability check the tests switch: the caps of $GLOBALS['user_caps'].
function current_user_can($cap) { return ! empty($GLOBALS['user_caps'][$cap]); }

// The signatures table: rows in $GLOBALS['rows'] (id => [title, settings]).
class Test_DB
{
    public $prefix = 'wp_';
    public function prepare($sql, ...$args) { return vsprintf(str_replace('%d', '%d', $sql), $args); }
    public function get_row($sql)
    {
        preg_match('/id = (\d+)/', $sql, $m);
        $row = isset($GLOBALS['rows'][(int) $m[1]]) ? $GLOBALS['rows'][(int) $m[1]] : null;

        return $row ? (object) array('title' => $row[0], 'settings' => $row[1]) : null;
    }
    public function get_results($sql)
    {
        $out = array();

        foreach ($GLOBALS['rows'] as $id => $row) {
            $out[] = (object) array('id' => $id, 'title' => $row[0]);
        }

        return $out;
    }
}
$GLOBALS['wpdb']    = new Test_DB();
$GLOBALS['rows']    = array();
$GLOBALS['options'] = array();

require SIGNATUR_PLUGIN_DIR . 'includes/database.php';
require SIGNATUR_PLUGIN_DIR . 'includes/shortcode.php';
require SIGNATUR_PLUGIN_DIR . 'includes/roles.php';
require SIGNATUR_PLUGIN_DIR . 'includes/wpbakery.php';

// --- A tiny runner -----------------------------------------------------------

$GLOBALS['failed'] = 0;
$GLOBALS['passed'] = 0;

function check($ok, $what)
{
    if ($ok) {
        $GLOBALS['passed']++;
    } else {
        $GLOBALS['failed']++;
        echo "FAIL: $what\n";
    }
}

function settings_of($array)
{
    return json_decode(signatur_sanitize_settings(json_encode($array)), true);
}

// --- Versions ----------------------------------------------------------------

$main   = file_get_contents(SIGNATUR_PLUGIN_DIR . 'signatur.php');
$readme = file_get_contents(SIGNATUR_PLUGIN_DIR . 'readme.txt');
preg_match('/^\s*\*\s*Version:\s*(\S+)/m', $main, $header);
preg_match("/define\('SIGNATUR_VERSION',\s*'([^']+)'/", $main, $constant);
preg_match('/^Stable tag:\s*(\S+)/m', $readme, $stable);
check($header && $constant && $stable && $header[1] === $constant[1] && $header[1] === $stable[1], 'Version, SIGNATUR_VERSION and Stable tag match');
check(false !== strpos($readme, '= ' . $header[1] . ' ='), 'readme.txt has a Changelog section for ' . $header[1]);

// --- Settings ----------------------------------------------------------------

$defaults = settings_of(null);
check(300 === $defaults['width'] && array(array(array(1, 1))) === $defaults['rows'], 'broken JSON gives the default settings');

$s = settings_of(array('width' => 50));
check(200 === $s['width'], 'width is at least 200');

$s = settings_of(array(
    'width'   => 400,
    'columns' => array(200, 200),
    'rows'    => array(array(array(1, 1, array(
        'type'  => 'text',
        'text'  => '<b>Andrii</b>',
        'color' => 'red',
        'size'  => 500,
        'link'  => 'javascript:alert(1)',
    )), array(1, 1))),
));
$el = $s['rows'][0][0][2];
check('Andrii' === $el['text'], 'text is stripped of tags');
check('#000000' === $el['color'], 'a colour that is not #rrggbb falls back to the default');
check(72 === $el['size'], 'text size is at most 72');
check('' === $el['link'], 'a javascript: link is dropped');

// A grid that does not add up (3 columns in a 2-column table) falls back to one cell per row.
$s = settings_of(array('columns' => array(100, 100), 'rows' => array(array(array(3, 1)))));
check(array(array(array(2, 1))) === $s['rows'], 'a grid wider than its columns is reset');

// A rowspan reaching past the last row is reset too.
$s = settings_of(array('columns' => array(100), 'rows' => array(array(array(1, 2)))));
check(array(array(array(1, 1))) === $s['rows'], 'a rowspan past the last row is reset');

// The copy button, as WP Banner's Button.
$s = settings_of(array('copy_button' => array('align' => 'middle', 'style' => 'groove', 'shape' => 'round', 'size' => 'huge', 'color' => '#ABCDEF', 'text_color' => 'red')));
$cb = $s['copy_button'];
check('left' === $cb['align'] && 'filled' === $cb['style'] && 'round' === $cb['shape'] && 'default' === $cb['size'], 'copy button choices are checked');
check('#abcdef' === $cb['color'] && '#ffffff' === $cb['text_color'], 'copy button colours are #rrggbb or the default');
check(array('text', 'align', 'style', 'shape', 'size', 'color', 'text_color', 'margin') === array_keys($cb), 'copy button keeps only its own keys');

// Saved before 1.7.0: its colours kept, the nearest Corners and Size.
$old = settings_of(array('copy_button' => array('bg_color' => '#f6f7f7', 'text_color' => '#1d2327', 'border_radius' => 4, 'font_size' => 14, 'margin' => array('top' => 5))));
check('filled' === $old['copy_button']['style'] && '#f6f7f7' === $old['copy_button']['color'] && '#1d2327' === $old['copy_button']['text_color'], 'an old copy button keeps its colours');
check('rounded' === $old['copy_button']['shape'] && 'default' === $old['copy_button']['size'] && 5 === $old['copy_button']['margin']['top'], 'an old copy button gets the nearest Corners and Size, its margin kept');
$old = settings_of(array('copy_button' => array('bg_color' => '#000000', 'border_radius' => 0, 'font_size' => 20)));
check('square' === $old['copy_button']['shape'] && 'extra-large' === $old['copy_button']['size'], 'no radius: Square; a large font: Extra Large');

// Its look: each Background as WP Banner's.
$style = signatur_copy_button_style(array('style' => 'filled', 'shape' => 'rounded', 'size' => 'large', 'color' => '#112233', 'text_color' => '#ffffff'));
check(false !== strpos($style, 'background-color:#112233') && false !== strpos($style, 'border-radius:5px') && false !== strpos($style, 'min-height:48px') && false !== strpos($style, 'font-size:14px'), 'Filled: the colour as background, Rounded, Large');
$style = signatur_copy_button_style(array('style' => 'bordered', 'shape' => 'round', 'color' => '#112233'));
check(false !== strpos($style, 'background-color:transparent') && false !== strpos($style, 'border:2px solid #112233') && false !== strpos($style, 'border-radius:35px'), 'Border: transparent, its line in the colour, Round');
$style = signatur_copy_button_style(array('style' => 'underline', 'shape' => 'round', 'color' => '#112233'));
check(false !== strpos($style, 'border-bottom:3px solid #112233') && false !== strpos($style, 'border-radius:0'), 'Underline: a line under it, no corners');
check(false === strpos(signatur_copy_button_style(array(), false), 'margin:'), 'the editor preview has no margin');

$s = settings_of(array('rows' => array(array(array(1, 1, array('type' => 'social', 'network' => 'myspace', 'link' => '')))), 'columns' => array(300)));
check(! isset($s['rows'][0][0][2]), 'a social element without a link is dropped');

// --- Rendering ---------------------------------------------------------------

$GLOBALS['rows'][7] = array('Test', json_encode(array(
    'width'   => 300,
    'columns' => array(300),
    'rows'    => array(
        array(array(1, 1, array('type' => 'text', 'text' => 'Tom & "Jerry"', 'link' => 'https://example.test/?a=1&b=2'))),
    ),
)));

check('' === signatur_render_shortcode(array('id' => 0)), 'no id: nothing');
check('Signature not found.' === signatur_render_shortcode(array('id' => 99)), 'a missing signature says so');

$GLOBALS['enqueued'] = array();
$html = signatur_render_shortcode(array('id' => 7));
check(false !== strpos($html, 'Tom &amp; &quot;Jerry&quot;'), 'text is escaped in the signature');
check(false !== strpos($html, 'class="signatur-copy-button"'), 'the copy button shows by default');
check(in_array('signatur-frontend', $GLOBALS['enqueued'], true), 'the copy script loads with the button');
check(false === strpos($html, '<script'), 'no script in the signature');

$GLOBALS['enqueued'] = array();
$html = signatur_render_shortcode(array('id' => 7, 'copy' => 'no'));
check(false === strpos($html, 'signatur-copy-button') && ! $GLOBALS['enqueued'], 'copy="no": no button, no script');

// --- Roles -------------------------------------------------------------------

$clean = signatur_sanitize_roles(array(
    'administrator' => array('edit' => 1),
    'editor'        => array('edit' => 1, 'create' => 1, 'delete' => 1, 'wpbakery' => 1, 'unknown' => 1),
    'author'        => array('create' => 1, 'delete' => 1, 'wpbakery' => 1),
    'nobody'        => array('edit' => 1),
    'subscriber'    => 'edit',
));
check(! isset($clean['administrator']), 'administrators are not stored (always all)');
check(array('edit' => true, 'create' => true, 'delete' => true, 'wpbakery' => true) === $clean['editor'], 'an editor keeps the known actions');
check(! isset($clean['author']), 'Add, Delete and WPBakery need Edit signatures');
check(! isset($clean['nobody']) && ! isset($clean['subscriber']), 'unknown roles and broken values are dropped');

$GLOBALS['options'][SIGNATUR_ROLES_OPTION] = array('editor' => array('edit' => 1));
signatur_forget_roles();

$admin = signatur_grant_caps(array('manage_options' => true), array(), array(), (object) array('roles' => array('administrator')));
check(! empty($admin[SIGNATUR_CAP_EDIT]) && ! empty($admin[SIGNATUR_CAP_CREATE]) && ! empty($admin[SIGNATUR_CAP_DELETE]) && ! empty($admin[SIGNATUR_CAP_WPBAKERY]), 'administrators get every capability');

$editor = signatur_grant_caps(array(), array(), array(), (object) array('roles' => array('editor')));
check(! empty($editor[SIGNATUR_CAP_EDIT]) && empty($editor[SIGNATUR_CAP_CREATE]) && empty($editor[SIGNATUR_CAP_DELETE]), 'an editor gets only what is set');

$subscriber = signatur_grant_caps(array('read' => true), array(), array(), (object) array('roles' => array('subscriber')));
check(array('read' => true) === $subscriber, 'a role without rights gets nothing');

// The option saved again: worked out again.
$GLOBALS['options'][SIGNATUR_ROLES_OPTION] = array();
signatur_forget_roles();
$editor = signatur_grant_caps(array(), array(), array(), (object) array('roles' => array('editor')));
check(empty($editor[SIGNATUR_CAP_EDIT]), 'rights are read again once the option changes');

// --- WPBakery element ----------------------------------------------------------

$element = signatur_wpbakery_settings();
$params  = array();

foreach ($element['params'] as $param) {
    $params[$param['param_name']] = $param;
}

check('signatur' === $element['base'], 'the element wraps [signatur]');
check(isset($params['id']['value']['Test (#7)']) && '7' === $params['id']['value']['Test (#7)'], 'the list offers the signatures');
check('yes' === reset($params['copy']['value']), 'the copy button is shown unless chosen otherwise (first value: yes)');

$GLOBALS['user_caps'] = array(SIGNATUR_CAP_WPBAKERY => true);
check(false === strpos(signatur_wpbakery_edit_param(array('param_name' => 'signatur_edit'), ''), 'signatur-vc-edit__add'), 'no Add signature button without Add');
$GLOBALS['user_caps'][SIGNATUR_CAP_CREATE] = true;
check(false !== strpos(signatur_wpbakery_edit_param(array('param_name' => 'signatur_edit'), ''), 'signatur-vc-edit__add'), 'Add signature button with Add');

// --- Translations ------------------------------------------------------------

// Every string the plugin's PHP translates is in each .po, translated, and compiled into its .mo.
$strings = array();

foreach (array_merge(array(SIGNATUR_PLUGIN_DIR . 'signatur.php'), glob(SIGNATUR_PLUGIN_DIR . 'includes/*.php')) as $file) {
    preg_match_all("/(?:__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\(\s*'((?:[^'\\\\]|\\\\.)*)'\s*,\s*'signatur'\s*\)/", file_get_contents($file), $m);

    foreach ($m[1] as $s) {
        $strings[stripcslashes($s)] = true;
    }
}

check(count($strings) > 50, 'translatable strings found (' . count($strings) . ')');

foreach (glob(SIGNATUR_PLUGIN_DIR . 'languages/*.po') as $po) {
    $text = file_get_contents($po);
    $mo   = file_get_contents(substr($po, 0, -3) . '.mo');
    $name = basename($po);

    foreach (array_keys($strings) as $s) {
        $id = 'msgid "' . addcslashes($s, "\"\\") . '"';
        $at = strpos($text, $id . "\nmsgstr \"");
        check(false !== $at && "\"\"\n" !== substr($text, $at + strlen($id) + 8, 3), "$name translates \"$s\"");
        check(false !== strpos($mo, $s . "\0"), "$name.mo has \"$s\"");
    }
}

echo "{$GLOBALS['passed']} passed, {$GLOBALS['failed']} failed\n";
exit($GLOBALS['failed'] ? 1 : 0);
