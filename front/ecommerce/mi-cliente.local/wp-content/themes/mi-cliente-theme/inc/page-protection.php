<?php
/**
 * Page Protection Dialog
 *
 * Adds JavaScript confirmation dialogs when client role users attempt
 * critical page operations (delete main pages, modify templates, change
 * homepage settings).
 *
 * @package Mi_Cliente_Theme
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Enqueue page protection script for client role users.
 *
 * Only loads the script in the admin area for users with the 'client_role'
 * capability, on relevant pages (pages list, page editor, reading settings).
 *
 * @since 1.0.0
 *
 * @param string $hook_suffix The current admin page hook suffix.
 */
function mi_cliente_theme_enqueue_page_protection( $hook_suffix ) {
    if ( ! current_user_can( 'client_role' ) ) {
        return;
    }

    // Only load on pages where protection is needed.
    $relevant_pages = array(
        'edit.php',
        'post.php',
        'post-new.php',
        'options-reading.php',
    );

    if ( ! in_array( $hook_suffix, $relevant_pages, true ) ) {
        return;
    }

    wp_enqueue_script(
        'mi-cliente-page-protection',
        get_template_directory_uri() . '/assets/js/page-protection.js',
        array(),
        '1.0.0',
        true
    );

    // Pass protected page slugs and labels to JavaScript.
    wp_localize_script(
        'mi-cliente-page-protection',
        'miClientePageProtection',
        array(
            'protectedPages' => array( 'inicio', 'tienda', 'contacto' ),
            'messages'       => array(
                'deleteWarning'   => __( '⚠️ ATENCIÓN: Estás a punto de eliminar una página principal del sitio. Esta acción puede afectar gravemente el funcionamiento de la tienda. ¿Estás seguro de que deseas continuar?', 'mi-cliente-theme' ),
                'templateWarning' => __( '⚠️ ATENCIÓN: Estás modificando la plantilla de una página principal. Esto puede alterar el diseño del sitio. ¿Deseas continuar?', 'mi-cliente-theme' ),
                'homepageWarning' => __( '⚠️ ATENCIÓN: Estás cambiando la configuración de la página de inicio. Esto afectará qué contenido ven los visitantes al entrar al sitio. ¿Deseas continuar?', 'mi-cliente-theme' ),
            ),
        )
    );
}
add_action( 'admin_enqueue_scripts', 'mi_cliente_theme_enqueue_page_protection' );
