<?php
/**
 * Remplace l'adresse d'un site partout où WordPress et les builders la stockent
 * (options, contenus, métadonnées sérialisées) sans casser la sérialisation.
 *
 * Usage : php migrer-url.php <dossier wordpress> <ancienne URL> <nouvelle URL>
 * Ex.   : php migrer-url.php ./wordpress https://monsite.localhost https://www.monsite.fr
 */
if ( PHP_SAPI !== 'cli' || $argc < 4 ) {
	fwrite( STDERR, "Usage : php migrer-url.php <dossier wordpress> <ancienne URL> <nouvelle URL>\n" );
	exit( 1 );
}
[ , $dossier, $ancienne, $nouvelle ] = $argv;
$ancienne = rtrim( $ancienne, '/' );
$nouvelle = rtrim( $nouvelle, '/' );

$_SERVER['HTTP_HOST'] = parse_url( $ancienne, PHP_URL_HOST );
$_SERVER['HTTPS']     = str_starts_with( $ancienne, 'https' ) ? 'on' : '';
define( 'WP_HOME', $nouvelle );
define( 'WP_SITEURL', $nouvelle );
require rtrim( $dossier, '/' ) . '/wp-load.php';

function socle_remplacer( $v, $a, $n ) {
	if ( is_string( $v ) ) return str_replace( $a, $n, $v );
	if ( is_array( $v ) ) return array_map( fn( $x ) => socle_remplacer( $x, $a, $n ), $v );
	return $v;
}

global $wpdb;
$like = '%' . $wpdb->esc_like( $ancienne ) . '%';
$n    = 0;
foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT meta_id, meta_value FROM $wpdb->postmeta WHERE meta_value LIKE %s", $like ) ) as $m ) {
	$v = maybe_unserialize( $m->meta_value );
	$wpdb->update( $wpdb->postmeta, [ 'meta_value' => maybe_serialize( socle_remplacer( $v, $ancienne, $nouvelle ) ) ], [ 'meta_id' => $m->meta_id ] );
	++$n;
}
foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT option_id, option_value FROM $wpdb->options WHERE option_value LIKE %s", $like ) ) as $o ) {
	$v = maybe_unserialize( $o->option_value );
	$wpdb->update( $wpdb->options, [ 'option_value' => maybe_serialize( socle_remplacer( $v, $ancienne, $nouvelle ) ) ], [ 'option_id' => $o->option_id ] );
	++$n;
}
$n += (int) $wpdb->query( $wpdb->prepare( "UPDATE $wpdb->posts SET post_content = REPLACE(post_content, %s, %s), guid = REPLACE(guid, %s, %s)", $ancienne, $nouvelle, $ancienne, $nouvelle ) );
wp_cache_flush();
echo "$n enregistrements mis à jour ($ancienne → $nouvelle)\n";
echo "Pensez à mettre à jour WP_HOME / WP_SITEURL dans wp-config.php.\n";
