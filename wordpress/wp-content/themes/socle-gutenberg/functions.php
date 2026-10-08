<?php
/**
 * Socle Gutenberg — thème bloc.
 *
 * La mise en page vit dans les modèles, les compositions et les pages (éditeur de site). Ce fichier n'ajoute que
 * les styles de base accessibles (assets/css/base.css, aussi chargés dans l'éditeur), le préchargement des polices
 * et la mise en place initiale (inc/installation.php).
 */

defined( 'ABSPATH' ) || exit;

require __DIR__ . '/inc/installation.php';
require __DIR__ . '/inc/gayrel.php';

add_action( 'after_setup_theme', function () {
	add_theme_support( 'editor-styles' );
	add_editor_style( 'assets/css/base.css' );
	if ( file_exists( get_stylesheet_directory() . '/assets/css/ambiance.css' ) ) {
		add_editor_style( 'assets/css/ambiance.css' ); // style de départ visible dans l'éditeur
	}
} );

add_action( 'wp_enqueue_scripts', function () {
	$f = get_stylesheet_directory() . '/assets/css/base.css';
	wp_enqueue_style( 'socle-base', get_stylesheet_directory_uri() . '/assets/css/base.css', [], filemtime( $f ) );
	// Style de départ (outils/style.php) : après la base, avant la feuille d'une maquette
	$a = get_stylesheet_directory() . '/assets/css/ambiance.css';
	if ( file_exists( $a ) ) {
		wp_enqueue_style( 'socle-ambiance', get_stylesheet_directory_uri() . '/assets/css/ambiance.css', [ 'socle-base' ], filemtime( $a ) );
	}
	$p = get_stylesheet_directory() . '/assets/css/projet.css';
	if ( file_exists( $p ) ) {
		wp_enqueue_style( 'socle-projet', get_stylesheet_directory_uri() . '/assets/css/projet.css', [ file_exists( $a ) ? 'socle-ambiance' : 'socle-base' ], filemtime( $p ) );
	}
} );

// Préchargement des polices principales (déclarées dans theme.json par outils/polices.py)
add_action( 'wp_head', function () {
	foreach ( array_slice( glob( get_stylesheet_directory() . '/assets/fonts/*-latin.woff2' ) ?: [], 0, 2 ) as $f ) {
		printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( get_stylesheet_directory_uri() . '/assets/fonts/' . basename( $f ) ) );
	}
}, 2 );
