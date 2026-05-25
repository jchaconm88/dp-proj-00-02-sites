<?php
/**
 * Instala traducciones de WooCommerce y crea copias es_PE desde es_ES.
 *
 * Uso: docker exec dp-proj-00-04-wordpress-1 php /path/to/install-wc-translations.php
 */

$zip_path   = '/tmp/woocommerce-es_ES.zip';
$target_dir = '/var/www/html/wp-content/languages/plugins';

if ( ! class_exists( 'ZipArchive' ) ) {
	fwrite( STDERR, "ZipArchive no disponible.\n" );
	exit( 1 );
}

$zip = new ZipArchive();
if ( true !== $zip->open( $zip_path ) ) {
	fwrite( STDERR, "No se pudo abrir: {$zip_path}\n" );
	exit( 1 );
}

$zip->extractTo( $target_dir );
$zip->close();

$es_files = glob( $target_dir . '/woocommerce-es_ES*' );
foreach ( $es_files as $file ) {
	$pe_file = str_replace( 'woocommerce-es_ES', 'woocommerce-es_PE', $file );
	if ( ! file_exists( $pe_file ) ) {
		copy( $file, $pe_file );
	}
}

echo 'Instalado: ' . count( $es_files ) . " archivos es_ES (+ copias es_PE)\n";
echo "Ejecuta build-wc-admin-translations.php para las pantallas React de WooCommerce.\n";
