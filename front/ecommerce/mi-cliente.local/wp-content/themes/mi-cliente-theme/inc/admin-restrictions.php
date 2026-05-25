<?php
/**
 * Admin Panel Restrictions for Client Role
 *
 * Removes admin menu items for users with the 'client_role' capability,
 * keeping only: Posts, Pages, Products, Media, and Appearance (Menus & Widgets).
 *
 * @package Mi_Cliente_Theme
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Remove admin menu items for client role users.
 *
 * Restricts the admin sidebar to only the menus the client needs:
 * - Posts (edit.php)
 * - Pages (edit.php?post_type=page)
 * - Products (edit.php?post_type=product) — when WooCommerce is active
 * - Media (upload.php)
 * - Appearance (themes.php) — limited to Menus & Widgets submenus
 *
 * @since 1.0.0
 */
function mi_cliente_theme_restrict_admin_menus() {
    if ( ! current_user_can( 'client_role' ) ) {
        return;
    }

    // Top-level menus to remove for client role.
    $menus_to_remove = array(
        'index.php',                  // Dashboard.
        'separator1',                 // Separator.
        'edit-comments.php',          // Comments.
        'tools.php',                  // Tools.
        'options-general.php',        // Settings.
        'plugins.php',                // Plugins.
        'users.php',                  // Users.
        'separator2',                 // Separator.
    );

    foreach ( $menus_to_remove as $menu ) {
        remove_menu_page( $menu );
    }

    // Remove Appearance sub-menus except Menus and Widgets.
    remove_submenu_page( 'themes.php', 'themes.php' );
    remove_submenu_page( 'themes.php', 'theme-editor.php' );
    remove_submenu_page( 'themes.php', 'customize.php' );
}
add_action( 'admin_menu', 'mi_cliente_theme_restrict_admin_menus', 999 );

/**
 * Redirect client role users away from restricted admin pages.
 *
 * If a client user tries to access a restricted page directly via URL,
 * redirect them to the Posts list.
 *
 * @since 1.0.0
 */
function mi_cliente_theme_restrict_admin_access() {
    if ( ! current_user_can( 'client_role' ) ) {
        return;
    }

    $restricted_pages = array(
        'index.php',
        'edit-comments.php',
        'tools.php',
        'options-general.php',
        'plugins.php',
        'users.php',
        'theme-editor.php',
    );

    global $pagenow;

    if ( in_array( $pagenow, $restricted_pages, true ) ) {
        wp_safe_redirect( admin_url( 'edit.php' ) );
        exit;
    }
}
add_action( 'admin_init', 'mi_cliente_theme_restrict_admin_access' );

/**
 * Remove admin toolbar items for client role.
 *
 * Simplifies the admin bar by removing items that are not relevant
 * to the client's workflow.
 *
 * @since 1.0.0
 *
 * @param WP_Admin_Bar $wp_admin_bar The admin bar instance.
 */
function mi_cliente_theme_restrict_admin_bar( $wp_admin_bar ) {
    if ( ! current_user_can( 'client_role' ) ) {
        return;
    }

    $wp_admin_bar->remove_node( 'updates' );
    $wp_admin_bar->remove_node( 'comments' );
    $wp_admin_bar->remove_node( 'new-user' );
    $wp_admin_bar->remove_node( 'wp-logo' );
}
add_action( 'admin_bar_menu', 'mi_cliente_theme_restrict_admin_bar', 999 );
