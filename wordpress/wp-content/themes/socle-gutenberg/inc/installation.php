<?php
/**
 * Mise en place initiale d'un site Gutenberg du socle.
 * - Menus « Menu principal » et « Pied de page » (wp_navigation), reliés aux blocs Navigation de l'en-tête et du pied.
 * - Pied de page avec l'année et le nom du site.
 * - Page d'accueil en blocs natifs (si elle est encore vide).
 * Exécuté à l'activation depuis l'administration, ou par nouveau-projet.sh (outils/apres-theme.php).
 */

defined( 'ABSPATH' ) || exit;

add_action( 'after_switch_theme', function () {
	if ( current_user_can( 'edit_theme_options' ) ) {
		socle_gutenberg_installer();
	}
} );

function socle_gutenberg_installer( $forcer = false ) {
	if ( get_option( 'socle_gutenberg_installe' ) && ! $forcer ) {
		return;
	}
	$page = fn( $slug ) => get_page_by_path( $slug );
	$lien = function ( $titre, $cible ) {
		if ( $cible instanceof WP_Post ) {
			return sprintf( '<!-- wp:navigation-link {"label":"%s","type":"page","id":%d,"url":"%s","kind":"post-type"} /-->', esc_attr( $titre ), $cible->ID, esc_url( get_permalink( $cible ) ) );
		}
		return sprintf( '<!-- wp:navigation-link {"label":"%s","url":"%s","kind":"custom"} /-->', esc_attr( $titre ), esc_attr( $cible ) );
	};

	// 1. Menus (wp_navigation)
	$menus = [
		'Menu principal' => [ [ 'Accueil', $page( 'accueil' ) ], [ 'À propos', $page( 'a-propos' ) ], [ 'La carte', $page( 'carte' ) ], [ 'Contact', $page( 'contact' ) ] ],
		'Pied de page'   => [ [ 'Plan du site', $page( 'plan-du-site' ) ], [ 'Mentions légales', $page( 'mentions-legales' ) ], [ 'Confidentialité', $page( 'politique-de-confidentialite' ) ], [ 'Gestion des cookies', '#gestion-cookies' ] ],
	];
	$refs = [];
	foreach ( $menus as $titre => $liens ) {
		$existant = get_posts( [ 'post_type' => 'wp_navigation', 'title' => $titre, 'numberposts' => 1, 'post_status' => 'publish' ] );
		$refs[ $titre ] = $existant ? $existant[0]->ID : wp_insert_post( [
			'post_type'    => 'wp_navigation',
			'post_status'  => 'publish',
			'post_title'   => $titre,
			'post_content' => implode( "\n", array_map( fn( $l ) => $l[1] ? $lien( $l[0], $l[1] ) : '', $liens ) ),
		] );
	}

	// 2. En-tête et pied de page enregistrés avec la référence de leur menu (modifiables dans l'éditeur de site)
	$dossier = get_stylesheet_directory() . '/parts/';
	$parts   = [
		'header' => [ 'En-tête', str_replace( '<!-- wp:navigation {"overlayMenu":"mobile"', '<!-- wp:navigation {"ref":' . $refs['Menu principal'] . ',"overlayMenu":"mobile"', file_get_contents( $dossier . 'header.html' ) ) ],
		'footer' => [ 'Pied de page', str_replace(
			[ '<!-- wp:navigation {"overlayMenu":"never"', '© Tous droits réservés' ],
			[ '<!-- wp:navigation {"ref":' . $refs['Pied de page'] . ',"overlayMenu":"never"', '© ' . wp_date( 'Y' ) . ' ' . get_bloginfo( 'name' ) ],
			file_get_contents( $dossier . 'footer.html' )
		) ],
	];
	foreach ( $parts as $slug => [ $titre, $contenu ] ) {
		$existant = get_posts( [ 'post_type' => 'wp_template_part', 'name' => $slug, 'numberposts' => 1, 'post_status' => 'publish', 'tax_query' => [ [ 'taxonomy' => 'wp_theme', 'field' => 'name', 'terms' => get_stylesheet() ] ] ] );
		if ( $existant ) {
			continue;
		}
		$id = wp_insert_post( [ 'post_type' => 'wp_template_part', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $titre, 'post_content' => $contenu ] );
		wp_set_object_terms( $id, get_stylesheet(), 'wp_theme' );
		wp_set_object_terms( $id, $slug, 'wp_template_part_area' );
	}

	// 3. Accueil : sections du socle (socle-base) ; sinon un bandeau minimal en blocs natifs
	$accueil = $page( 'accueil' );
	if ( $accueil && function_exists( 'socle_sections_accueil' ) ) {
		socle_sections_accueil( $accueil->ID );
	} elseif ( $accueil && trim( $accueil->post_content ) === '' ) {
		$contact = $page( 'contact' );
		$blocs   = '<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"}}},"layout":{"type":"constrained","contentSize":"1320px"}} -->' . "\n"
			. '<div class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)"><!-- wp:heading {"level":1} -->' . "\n"
			. '<h1 class="wp-block-heading">' . esc_html( get_bloginfo( 'name' ) ) . '</h1>' . "\n<!-- /wp:heading -->\n\n"
			. '<!-- wp:paragraph {"fontSize":"grand"} -->' . "\n" . '<p class="has-grand-font-size">Présentez ici votre activité en une ou deux phrases : ce que vous faites, pour qui, et où.</p>' . "\n<!-- /wp:paragraph -->\n\n"
			. '<!-- wp:buttons -->' . "\n" . '<div class="wp-block-buttons"><!-- wp:button -->' . "\n"
			. '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="' . esc_url( $contact ? get_permalink( $contact ) : '/contact/' ) . '">Nous contacter</a></div>' . "\n"
			. "<!-- /wp:button --></div>\n<!-- /wp:buttons --></div>\n<!-- /wp:group -->";
		wp_update_post( [ 'ID' => $accueil->ID, 'post_content' => $blocs ] );
	}

	update_option( 'socle_gutenberg_installe', time() );
}
