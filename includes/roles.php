<?php

/**
 * Signatures → Settings: what each role may do with the signatures, as
 * checkboxes per action (as WP Swiper's roles table). Roles that manage the
 * site (manage_options: Administrators) always may do all of it, and only
 * they open the Settings page. Unset, Signatures is theirs only, as it was
 * before 1.6.0.
 *
 * The rights are the plugin's own capabilities, given at run time
 * (user_has_cap) from the option, never written into the roles: deleting
 * the plugin leaves the roles as they were.
 */

if (! defined('ABSPATH')) {
    exit;
}

define('SIGNATUR_ROLES_OPTION', 'signatur_roles');
define('SIGNATUR_SETTINGS_PAGE', 'signatur-settings');

// Manages the site: all of Signatures, and the Settings page.
define('SIGNATUR_CAP_MANAGE', 'manage_options');

// The plugin's own capabilities.
define('SIGNATUR_CAP_EDIT', 'signatur_edit_signatures');
define('SIGNATUR_CAP_CREATE', 'signatur_create_signatures');
define('SIGNATUR_CAP_DELETE', 'signatur_delete_signatures');
define('SIGNATUR_CAP_WPBAKERY', 'signatur_wpbakery');

/**
 * The actions a role may be given, in the table's order: key =>
 * [capability, name, what it allows]. Add, Delete and the WPBakery element:
 * with Edit signatures only (signatur_sanitize_roles()).
 */
function signatur_role_actions()
{
    return array(
        'edit'     => array(
            SIGNATUR_CAP_EDIT,
            __('Edit signatures', 'signatur'),
            __('Signatures in the menu: open any signature and change it.', 'signatur'),
        ),
        'create'   => array(
            SIGNATUR_CAP_CREATE,
            __('Add', 'signatur'),
            __('New signatures. With Edit signatures only.', 'signatur'),
        ),
        'delete'   => array(
            SIGNATUR_CAP_DELETE,
            __('Delete', 'signatur'),
            __('Delete signatures. With Edit signatures only.', 'signatur'),
        ),
        'wpbakery' => array(
            SIGNATUR_CAP_WPBAKERY,
            __('WPBakery element', 'signatur'),
            __('Put a signature on a page with the WPBakery element. With Edit signatures only. Pages show the signatures to everyone anyway.', 'signatur'),
        ),
    );
}

/**
 * Whether a role manages the site (all of Signatures, always).
 */
function signatur_role_is_full($role)
{
    $object = get_role($role);

    return $object && $object->has_cap(SIGNATUR_CAP_MANAGE);
}

/**
 * What is kept: the site's roles that do not manage it, the known actions,
 * on; Add, Delete and WPBakery element only with Edit signatures.
 *
 * @param mixed $value Posted or saved: role => [action => anything].
 * @return array role => [action => true]
 */
function signatur_sanitize_roles($value)
{
    $clean = array();

    if (! is_array($value)) {
        return $clean;
    }

    $roles = wp_roles()->roles;

    foreach ($value as $role => $actions) {
        $role = (string) $role;

        if (! isset($roles[$role]) || signatur_role_is_full($role) || ! is_array($actions)) {
            continue;
        }

        $on = array();

        foreach (array_keys(signatur_role_actions()) as $action) {
            if (! empty($actions[$action]) && is_scalar($actions[$action])) {
                $on[$action] = true;
            }
        }

        if (empty($on['edit'])) {
            unset($on['create'], $on['delete'], $on['wpbakery']);
        }

        if ($on) {
            $clean[$role] = $on;
        }
    }

    return $clean;
}

/**
 * The rights set, as saved: role => [action => true]. Asked for on every
 * capability check, so worked out once (again when the option changes:
 * signatur_forget_roles()).
 */
function signatur_role_rights($forget = false)
{
    static $rights = null;

    if ($forget) {
        $rights = null;

        return array();
    }

    if (null === $rights) {
        $saved  = get_option(SIGNATUR_ROLES_OPTION, array());
        $rights = is_array($saved) ? signatur_sanitize_roles($saved) : array();
    }

    return $rights;
}

function signatur_forget_roles()
{
    signatur_role_rights(true);
}

/**
 * user_has_cap: the plugin's capabilities, from the user's roles. Those who
 * manage the site have them all.
 */
function signatur_grant_caps($allcaps, $caps, $args, $user)
{
    $actions = signatur_role_actions();

    if (! empty($allcaps[SIGNATUR_CAP_MANAGE])) {
        foreach ($actions as $action) {
            $allcaps[$action[0]] = true;
        }

        return $allcaps;
    }

    $roles = isset($user->roles) ? (array) $user->roles : array();

    if (! $roles) {
        return $allcaps;
    }

    $rights = signatur_role_rights();

    foreach ($roles as $role) {
        if (empty($rights[$role])) {
            continue;
        }

        foreach (array_keys($rights[$role]) as $action) {
            $allcaps[$actions[$action][0]] = true;
        }
    }

    return $allcaps;
}

function signatur_register_roles_setting()
{
    register_setting(
        SIGNATUR_ROLES_OPTION,
        SIGNATUR_ROLES_OPTION,
        array(
            'type'              => 'array',
            'sanitize_callback' => 'signatur_sanitize_roles',
            'default'           => array(),
            'show_in_rest'      => false,
        )
    );
}

/**
 * Signatures → Settings, last in the submenu; site managers only.
 */
function signatur_register_settings_page()
{
    add_submenu_page(
        'signatur-all-signatures',
        __('Signatures settings', 'signatur'),
        __('Settings', 'signatur'),
        SIGNATUR_CAP_MANAGE,
        SIGNATUR_SETTINGS_PAGE,
        'signatur_render_settings_page'
    );
}

/**
 * Its script (Add, Delete and WPBakery element only with Edit) and styles,
 * on it only.
 */
function signatur_enqueue_settings_assets($hook_suffix)
{
    if (false === strpos((string) $hook_suffix, SIGNATUR_SETTINGS_PAGE)) {
        return;
    }

    wp_enqueue_style('signatur-roles', plugins_url('assets/css/roles.css', SIGNATUR_PLUGIN_FILE), array(), SIGNATUR_VERSION);
    wp_enqueue_script('signatur-roles', plugins_url('assets/js/roles.js', SIGNATUR_PLUGIN_FILE), array(), SIGNATUR_VERSION, true);
}

/**
 * The page: a table of the site's roles and the actions, saved by
 * WordPress's options.php (its nonce and capability).
 */
function signatur_render_settings_page()
{
    if (! current_user_can(SIGNATUR_CAP_MANAGE)) {
        return;
    }

    $actions = signatur_role_actions();
    $rights  = signatur_role_rights();
?>
    <div class="wrap signatur-roles">
        <h1><?php esc_html_e('Signatures settings', 'signatur'); ?></h1>
        <?php settings_errors(); ?>
        <form method="post" action="options.php">
            <?php settings_fields(SIGNATUR_ROLES_OPTION); ?>
            <h2><?php esc_html_e('Roles', 'signatur'); ?></h2>
            <p class="description"><?php esc_html_e('What each role may do with the signatures. Roles that manage the site (Administrators) may do all of it, and only they open this page. Visitors see the signatures on the pages whatever is set here.', 'signatur'); ?></p>
            <table class="widefat striped signatur-roles__table">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e('Role', 'signatur'); ?></th>
                        <?php foreach ($actions as $action) : ?>
                            <th scope="col" class="signatur-roles__action" title="<?php echo esc_attr($action[2]); ?>"><?php echo esc_html($action[1]); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (wp_roles()->roles as $role => $data) : ?>
                        <?php $full = signatur_role_is_full($role); ?>
                        <tr data-role="<?php echo esc_attr($role); ?>"<?php echo $full ? ' class="is-full"' : ''; ?>>
                            <th scope="row">
                                <?php echo esc_html(translate_user_role($data['name'])); ?>
                                <?php if ($full) : ?>
                                    <span class="signatur-roles__always"><?php esc_html_e('always all', 'signatur'); ?></span>
                                <?php endif; ?>
                            </th>
                            <?php foreach ($actions as $key => $action) : ?>
                                <td class="signatur-roles__action">
                                    <?php
                                    printf(
                                        '<input type="checkbox" id="%1$s" data-action="%2$s" aria-label="%3$s"%4$s>',
                                        esc_attr('signatur-role-' . $role . '-' . $key),
                                        esc_attr($key),
                                        /* translators: 1: an action (Edit signatures), 2: a role (Editor). */
                                        esc_attr(sprintf(__('%1$s: %2$s', 'signatur'), $action[1], translate_user_role($data['name']))),
                                        // Administrators: shown on, never sent (no name).
                                        $full ? ' checked disabled' : ' name="' . esc_attr(SIGNATUR_ROLES_OPTION . '[' . $role . '][' . $key . ']') . '" value="1"' . (empty($rights[$role][$key]) ? '' : ' checked')
                                    );
                                    ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
<?php
}
