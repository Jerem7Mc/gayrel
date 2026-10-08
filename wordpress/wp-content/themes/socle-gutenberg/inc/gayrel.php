<?php
/**
 * Gayrel — compléments du thème propres au projet (le reste vit dans theme.json, les modèles et projet.css).
 */

defined( 'ABSPATH' ) || exit;

// Pages : l'extrait sert d'introduction sous le titre (en-tête de page)
add_action( 'init', fn() => add_post_type_support( 'page', 'excerpt' ) );

// Pas de lien « Lire la suite » dans les extraits (la carte entière est cliquable)
add_filter( 'excerpt_more', fn() => '…' );

// Réalisations : filtres par secteur au-dessus de la liste (lien courant marqué aria-current)
add_shortcode( 'gayrel_filtres_secteurs', function () {
	$secteurs = get_terms( [ 'taxonomy' => 'secteur', 'hide_empty' => true ] );
	if ( is_wp_error( $secteurs ) || count( $secteurs ) < 2 ) {
		return '';
	}
	$courant = is_tax( 'secteur' ) ? get_queried_object_id() : 0;
	$liens   = sprintf( '<a href="%s"%s>Toutes</a>', esc_url( get_post_type_archive_link( 'realisation' ) ), $courant ? '' : ' aria-current="page"' );
	foreach ( $secteurs as $t ) {
		$liens .= sprintf( '<a href="%s"%s>%s</a>', esc_url( get_term_link( $t ) ), $courant === $t->term_id ? ' aria-current="page"' : '', esc_html( $t->name ) );
	}
	return '<nav class="g-filtres" aria-label="Filtrer les réalisations par secteur">' . $liens . '</nav>';
} );

// Archive des réalisations et secteurs : titre lisible (« Réalisations », « Bâtiment »)
add_filter( 'get_the_archive_title', function ( $titre ) {
	if ( is_post_type_archive( 'realisation' ) ) {
		return 'Réalisations';
	}
	if ( is_tax( 'secteur' ) ) {
		return 'Réalisations — ' . single_term_title( '', false );
	}
	return $titre;
} );


// FAQ : animation et réponse unique ouverte (blocs Détails des pages)
add_action( 'wp_enqueue_scripts', function () {
	if ( is_singular() && has_block( 'core/details', get_queried_object_id() ) ) {
		$f = get_stylesheet_directory() . '/assets/js/faq.js';
		wp_enqueue_script( 'gayrel-faq', get_stylesheet_directory_uri() . '/assets/js/faq.js', [], filemtime( $f ), [ 'strategy' => 'defer', 'in_footer' => true ] );
	}
} );

// Éditeur : les pages s'ouvrent avec leur modèle (en-tête, pied, mise en page réelle), comme sur le site
add_action( 'init', fn() => add_post_type_support( 'page', 'editor', [ 'default-mode' => 'template-locked' ] ), 20 );

// Accueil : titre des réalisations empilées (assets/js/realisations.js)
add_action( 'wp_enqueue_scripts', function () {
	if ( is_front_page() ) {
		$f = get_stylesheet_directory() . '/assets/js/realisations.js';
		wp_enqueue_script( 'gayrel-realisations', get_stylesheet_directory_uri() . '/assets/js/realisations.js', [], filemtime( $f ), [ 'strategy' => 'defer', 'in_footer' => true ] );
	}
} );

// En-tête des pages intérieures : grand mot décoratif de la maquette (méta _gayrel_mot de la page), masqué aux
// lecteurs d'écran ; la page des articles utilise celui de la page « Actualités »
add_filter( 'render_block_core/group', function ( $html, $bloc ) {
	if ( ! str_contains( $bloc['attrs']['className'] ?? '', 'g-page-entete' ) || str_contains( $html, 'g-geant' ) ) {
		return $html;
	}
	$id  = is_home() ? (int) get_option( 'page_for_posts' ) : ( is_page() ? get_queried_object_id() : 0 );
	$mot = $id ? trim( (string) get_post_meta( $id, '_gayrel_mot', true ) ) : '';
	if ( '' === $mot ) {
		return $html;
	}
	$pos = strrpos( $html, '</div>' );
	return substr( $html, 0, $pos ) . '<p class="g-geant" aria-hidden="true">' . esc_html( $mot ) . '</p>' . substr( $html, $pos );
}, 10, 2 );
