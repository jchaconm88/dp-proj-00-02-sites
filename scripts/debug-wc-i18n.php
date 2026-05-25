<?php
/**
 * Debug WooCommerce admin script translations.
 */

$_SERVER['PHP_SELF'] = '/wp-admin/admin.php';
$_GET['page']       = 'wc-admin';

define( 'WP_USE_THEMES', false );
require '/var/www/html/wp-load.php';

set_current_screen( 'woocommerce_page_wc-admin' );
do_action( 'admin_enqueue_scripts', 'woocommerce_page_wc-admin' );

$scripts = wp_scripts();
$found   = 0;

foreach ( $scripts->registered as $handle => $script ) {
	$src = $script->src ?? '';
	if ( false !== strpos( $src, '7311' ) || false !== strpos( $handle, '7311' ) ) {
		echo "HANDLE: {$handle}\n";
		echo "SRC: {$src}\n";
		echo 'translations: ' . wp_json_encode( $script->translations ) . "\n\n";
		++$found;
	}
}

// Check i18n path WordPress would use for a known chunk.
$path = 'assets/client/admin/chunks/7311.js';
$hash = md5( $path . 'woocommerce' );
$file = WP_LANG_DIR . '/plugins/woocommerce-es_PE-' . $hash . '.json';
echo "Expected JSON: {$file}\n";
echo 'Exists: ' . ( file_exists( $file ) ? 'yes' : 'no' ) . "\n";

// List wc-admin script handles with translation count.
echo "\n--- wc-admin handles with translations ---\n";
foreach ( $scripts->registered as $handle => $script ) {
	if ( false === strpos( $handle, 'wc-' ) && false === strpos( $script->src ?? '', 'woocommerce' ) ) {
		continue;
	}
	$trans_count = is_array( $script->translations ) ? count( $script->translations ) : 0;
	if ( $trans_count > 0 || false !== strpos( $script->src ?? '', 'chunks' ) ) {
		echo "{$handle}: translations={$trans_count} src=" . basename( $script->src ?? '' ) . "\n";
	}
}

echo "\nLocale: " . determine_locale() . "\n";
