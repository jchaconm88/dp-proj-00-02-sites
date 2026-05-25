<?php
/**
 * Genera woocommerce-{locale}-wc-admin-app.json combinando los JSON de chunks.
 */

define( 'WP_USE_THEMES', false );
require '/var/www/html/wp-load.php';

$locale   = determine_locale();
$lang_dir = WP_LANG_DIR . '/plugins/';
$domain   = 'woocommerce';
$pattern  = $lang_dir . $domain . '-' . $locale . '-*.json';

if ( 'en_US' === $locale ) {
	echo "Locale en_US: no se requiere.\n";
	exit( 0 );
}

$json_files = glob( $pattern );
if ( empty( $json_files ) ) {
	fwrite( STDERR, "No hay archivos JSON para {$locale} en {$lang_dir}\n" );
	exit( 1 );
}

// Excluir el archivo combinado si ya existe.
$combined_name = $domain . '-' . $locale . '-wc-admin-app.json';
$json_files    = array_values(
	array_filter(
		$json_files,
		static function ( $file ) use ( $combined_name ) {
			return basename( $file ) !== $combined_name;
		}
	)
);

$combined = array();

foreach ( $json_files as $json_filename ) {
	$chunk_data = json_decode( file_get_contents( $json_filename ), true );
	if ( empty( $chunk_data ) || ! isset( $chunk_data['comment']['reference'] ) ) {
		continue;
	}

	$reference = $chunk_data['comment']['reference'];
	if (
		false === strpos( $reference, 'assets/client/admin/chunks/' ) &&
		false === strpos( $reference, 'assets/client/admin/app/index.js' )
	) {
		continue;
	}

	if ( empty( $combined ) ) {
		$combined = $chunk_data;
	} else {
		$combined['locale_data']['messages'] = array_merge(
			$combined['locale_data']['messages'],
			$chunk_data['locale_data']['messages']
		);
	}
}

if ( empty( $combined ) ) {
	fwrite( STDERR, "No se encontraron chunks de wc-admin para combinar.\n" );
	exit( 1 );
}

unset( $combined['comment'] );

$out = $lang_dir . $combined_name;
file_put_contents( $out, wp_json_encode( $combined ) );
chown( $out, 'www-data' );
chgrp( $out, 'www-data' );

echo 'OK: ' . $out . ' (' . filesize( $out ) . " bytes, " . count( $json_files ) . " chunks)\n";
