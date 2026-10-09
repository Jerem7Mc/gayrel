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

// Menu mobile : fermeture animée (assets/js/menu.js)
add_action( 'wp_enqueue_scripts', function () {
	$f = get_stylesheet_directory() . '/assets/js/menu.js';
	wp_enqueue_script( 'gayrel-menu', get_stylesheet_directory_uri() . '/assets/js/menu.js', [], filemtime( $f ), [ 'strategy' => 'defer', 'in_footer' => true ] );
} );

// Éditeur : les pages s'ouvrent avec leur modèle (en-tête, pied, mise en page réelle), comme sur le site
add_action( 'init', fn() => add_post_type_support( 'page', 'editor', [ 'default-mode' => 'template-locked' ] ), 20 );

// Accueil : mot GAYREL au défilement, titre des réalisations empilées (assets/js/accueil.js)
add_action( 'wp_enqueue_scripts', function () {
	if ( is_front_page() ) {
		$f = get_stylesheet_directory() . '/assets/js/accueil.js';
		wp_enqueue_script( 'gayrel-accueil', get_stylesheet_directory_uri() . '/assets/js/accueil.js', [], filemtime( $f ), [ 'strategy' => 'defer', 'in_footer' => true ] );
	}
} );

// Grand mot décoratif des pages : modifiable dans l'éditeur (panneau « En-tête de la page », assets/js/editeur.js)
add_action( 'init', function () {
	register_post_meta( 'page', '_gayrel_mot', [
		'type'              => 'string',
		'single'            => true,
		'show_in_rest'      => true,
		'sanitize_callback' => fn( $v ) => mb_substr( sanitize_text_field( $v ), 0, 14 ),
		'auth_callback'     => fn( $autorise, $cle, $id ) => current_user_can( 'edit_post', $id ),
	] );
} );
add_action( 'enqueue_block_editor_assets', function () {
	$f = get_stylesheet_directory() . '/assets/js/editeur.js';
	wp_enqueue_script( 'gayrel-editeur', get_stylesheet_directory_uri() . '/assets/js/editeur.js', [ 'wp-plugins', 'wp-editor', 'wp-components', 'wp-data', 'wp-element' ], filemtime( $f ), true );
} );

// En-tête des pages intérieures (bandeau photo de hauteur fixe) : photo de fond quand le modèle n'en a pas (page des
// articles, archive des réalisations, article) et grand mot décoratif masqué aux lecteurs d'écran (méta _gayrel_mot)
add_filter( 'render_block_core/group', function ( $html, $bloc ) {
	if ( ! str_contains( $bloc['attrs']['className'] ?? '', 'g-page-entete' ) ) {
		return $html;
	}
	$page = is_home() ? (int) get_option( 'page_for_posts' ) : ( is_singular() ? get_queried_object_id() : 0 );
	// Photo
	if ( ! str_contains( $html, 'wp-block-post-featured-image' ) && ! str_contains( $html, 'g-page-entete__photo' ) ) {
		$img = $page ? get_post_thumbnail_id( $page ) : 0;
		if ( ! $img && ( is_post_type_archive( 'realisation' ) || is_tax( 'secteur' ) ) ) {
			$img = (int) get_option( 'gayrel_image_realisations' );
		}
		if ( ! $img && is_404() ) {
			$img = (int) get_option( 'gayrel_image_404' );
		}
		if ( $img ) {
			$photo = '<figure class="wp-block-post-featured-image g-page-entete__photo">' . wp_get_attachment_image( $img, 'full', false, [ 'alt' => '', 'loading' => 'eager', 'fetchpriority' => 'high', 'sizes' => '100vw' ] ) . '</figure>';
			$html  = preg_replace( '/(<div\b[^>]*\bg-page-entete\b[^>]*>)/', '$1' . $photo, $html, 1 );
		}
	}
	if ( str_contains( $html, 'wp-block-post-featured-image' ) && ! str_contains( $html, 'g-page-entete--image' ) ) {
		$html = preg_replace( '/\bg-page-entete\b/', 'g-page-entete g-page-entete--image', $html, 1 );
	}
	// Grand mot décoratif (même couleur que le fond de la page)
	if ( ! str_contains( $html, 'g-geant' ) ) {
		$mot = $page ? trim( (string) get_post_meta( $page, '_gayrel_mot', true ) ) : '';
		if ( '' === $mot ) {
			$mot = is_404() ? '404' : ( is_singular( 'realisation' ) ? 'Projet' : ( is_singular( 'post' ) ? 'Actus' : '' ) );
		}
		if ( '' !== $mot ) {
			$pos  = strrpos( $html, '</div>' );
			$html = substr( $html, 0, $pos ) . '<p class="g-geant" aria-hidden="true" style="--g-lettres:' . max( 1, mb_strlen( $mot ) ) . '">' . esc_html( $mot ) . '</p>' . substr( $html, $pos );
		}
	}
	return $html;
}, 10, 2 );

// Guide du site (socle-base) : consignes propres à Gayrel
add_filter( 'socle_guide_sections', function ( $sections ) {
	$projet = [
		'Les pages et leur en-tête' => '<ul><li>Chaque page s\'ouvre avec son modèle complet (en-tête, pied de page) : seul le contenu central se modifie ; l\'en-tête et le pied de page sont gérés par l\'agence.</li><li>Bandeau photo en haut de page : la photo est l\'<strong>image mise en avant</strong>, le texte sous le titre est l\'<strong>extrait</strong> (panneau de droite, onglet Page). Le panneau « En-tête de la page » règle le <strong>grand mot décoratif</strong> (HABITAT, SHOWROOM…), à laisser vide si besoin.</li><li>Photos de bandeau : paysage, 2 000 px de large, sujet au centre (le haut et le bas sont recadrés).</li></ul>',
		'Page d\'accueil' => '<ul><li>Les quatre dernières <strong>réalisations</strong> s\'affichent automatiquement dans « Découvrir des projets concrets » : publier une réalisation suffit.</li><li><strong>Questions fréquentes</strong> : chaque question est un bloc « Détails » (titre = question, contenu = réponse). Ajouter ou modifier ici met aussi à jour les informations lues par Google.</li><li>Les chiffres clés, les textes et les boutons se modifient en cliquant dessus ; garder des textes courts pour ne pas déséquilibrer la mise en page.</li></ul>',
		'Plan d\'accès' => '<ul><li>La carte (pages Contact et Showroom) reprend l\'adresse de la fiche établissement (à signaler à l\'agence si elle change) ; la carte Google ne se charge qu\'au clic du visiteur (protection des données).</li></ul>',
	] ;
	// après « Modifier une page » et « Images » (consignes générales d'abord)
	$debut = array_slice( $sections, 0, 2, true );
	return $debut + $projet + array_slice( $sections, 2, null, true );
} );
