<?php
/**
 * Gayrel — construction du site depuis la maquette Figma et les contenus du site actuel (gayrel.fr).
 *
 * Usage : php integration/construire.php [--remplacer]
 *   Importe les médias (maquette/figma, maquette/site-actuel/images), crée les réalisations, les pages, les actualités,
 *   les menus, la fiche Établissement, les réglages du formulaire et les redirections des anciennes adresses.
 *   Sans --remplacer : ne touche pas aux contenus déjà créés (le client a pu les modifier). Avec : les réécrit.
 * Idempotent : les médias sont repérés par leur fichier source (méta _gayrel_source), les contenus par leur adresse.
 */

$racine = dirname( __DIR__ );
$_SERVER['HTTP_HOST'] = 'gayrel.localhost';
$_SERVER['HTTPS']     = 'on';
require $racine . '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
wp_set_current_user( 1 );

$remplacer = in_array( '--remplacer', $argv, true );
$figma     = $racine . '/maquette/figma';
$actuel    = $racine . '/maquette/site-actuel/images';
$theme     = get_stylesheet_directory_uri();

/* ------------------------------------------------------------------ Outils */

function g_media( $fichier, $alt, $titre = '' ) {
	if ( ! is_file( $fichier ) ) {
		fwrite( STDERR, "✗ média introuvable : $fichier\n" );
		return 0;
	}
	$source = basename( dirname( $fichier ) ) . '/' . basename( $fichier );
	$deja   = get_posts( [ 'post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_gayrel_source', 'meta_value' => $source ] );
	if ( $deja ) {
		if ( '' !== $alt ) { // un média réutilisé sans texte (actualité, décor) garde son alternative
			update_post_meta( $deja[0], '_wp_attachment_image_alt', $alt );
		}
		return $deja[0];
	}
	$tmp = wp_tempnam( basename( $fichier ) );
	copy( $fichier, $tmp );
	$nom = $titre ? sanitize_title( $titre ) . '.' . pathinfo( $fichier, PATHINFO_EXTENSION ) : basename( $fichier );
	$id  = media_handle_sideload( [ 'name' => $nom, 'tmp_name' => $tmp ], 0, $titre ?: null );
	if ( is_wp_error( $id ) ) {
		fwrite( STDERR, '✗ ' . $id->get_error_message() . " ($fichier)\n" );
		return 0;
	}
	update_post_meta( $id, '_wp_attachment_image_alt', $alt );
	update_post_meta( $id, '_gayrel_source', $source );
	return $id;
}

function g_image( $id, $classe = '', $taille = 'large', $alt = null ) {
	if ( ! $id ) {
		return '';
	}
	$src  = wp_get_attachment_image_url( $id, $taille );
	$alt  = $alt ?? (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
	$attr = [ 'id' => $id, 'sizeSlug' => $taille, 'linkDestination' => 'none' ];
	if ( $classe ) {
		$attr['className'] = $classe;
	}
	return '<!-- wp:image ' . wp_json_encode( $attr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ' -->' . "\n"
		. '<figure class="wp-block-image size-' . $taille . ( $classe ? ' ' . $classe : '' ) . '"><img src="' . esc_url( $src ) . '" alt="' . esc_attr( $alt ) . '" class="wp-image-' . $id . '"/></figure>' . "\n<!-- /wp:image -->\n";
}

// Image décorative d'un fichier du thème (icônes) : pas dans la médiathèque
function g_icone( $url ) {
	return '<!-- wp:image {"sizeSlug":"full","linkDestination":"none"} -->' . "\n"
		. '<figure class="wp-block-image size-full"><img src="' . esc_url( $url ) . '" alt=""/></figure>' . "\n<!-- /wp:image -->\n";
}

function g_groupe( $classe, $contenu, $balise = '' ) {
	$attr = [ 'className' => $classe, 'layout' => [ 'type' => 'default' ] ];
	$tag  = 'div';
	if ( $balise ) {
		$attr = [ 'tagName' => $balise ] + $attr;
		$tag  = $balise;
	}
	return '<!-- wp:group ' . wp_json_encode( $attr, JSON_UNESCAPED_UNICODE ) . ' -->' . "\n"
		. '<' . $tag . ' class="wp-block-group ' . esc_attr( $classe ) . '">' . $contenu . '</' . $tag . '>' . "\n<!-- /wp:group -->\n";
}

function g_titre( $texte, $niveau = 2, $classe = '' ) {
	$attr = $niveau !== 2 ? [ 'level' => $niveau ] : [];
	if ( $classe ) {
		$attr['className'] = $classe;
	}
	return '<!-- wp:heading' . ( $attr ? ' ' . wp_json_encode( $attr, JSON_UNESCAPED_UNICODE ) : '' ) . ' -->' . "\n"
		. '<h' . $niveau . ' class="wp-block-heading' . ( $classe ? ' ' . $classe : '' ) . '">' . $texte . '</h' . $niveau . '>' . "\n<!-- /wp:heading -->\n";
}

function g_p( $texte, $classe = '' ) {
	return '<!-- wp:paragraph' . ( $classe ? ' {"className":"' . $classe . '"}' : '' ) . ' -->' . "\n"
		. '<p' . ( $classe ? ' class="' . $classe . '"' : '' ) . '>' . $texte . '</p>' . "\n<!-- /wp:paragraph -->\n";
}

function g_liste( array $items, $classe = '' ) {
	$li = implode( '', array_map( fn( $i ) => "<!-- wp:list-item -->\n<li>$i</li>\n<!-- /wp:list-item -->\n", $items ) );
	return '<!-- wp:list' . ( $classe ? ' {"className":"' . $classe . '"}' : '' ) . ' -->' . "\n"
		. '<ul class="wp-block-list' . ( $classe ? ' ' . $classe : '' ) . '">' . $li . '</ul>' . "\n<!-- /wp:list -->\n";
}

function g_boutons( array $boutons ) {
	$b = '';
	foreach ( $boutons as [ $texte, $lien, $classe ] ) {
		$b .= '<!-- wp:button {"className":"' . $classe . '"} -->' . "\n"
			. '<div class="wp-block-button ' . $classe . '"><a class="wp-block-button__link wp-element-button" href="' . esc_attr( $lien ) . '">' . $texte . '</a></div>' . "\n<!-- /wp:button -->\n";
	}
	return "<!-- wp:buttons -->\n<div class=\"wp-block-buttons\">$b</div>\n<!-- /wp:buttons -->\n";
}

// Titre décoratif géant : masqué aux lecteurs d'écran (le vrai titre est ailleurs)
function g_geant( $texte, $classe = '' ) {
	return "<!-- wp:html -->\n" . '<p class="g-geant' . ( $classe ? ' ' . $classe : '' ) . '" aria-hidden="true">' . $texte . '</p>' . "\n<!-- /wp:html -->\n";
}

function g_trait() {
	return "<!-- wp:separator {\"className\":\"g-trait\"} -->\n<hr class=\"wp-block-separator has-alpha-channel-opacity g-trait\"/>\n<!-- /wp:separator -->\n";
}

function g_question( $q, $r ) {
	return '<!-- wp:details -->' . "\n" . '<details class="wp-block-details"><summary>' . $q . '</summary>' . g_p( $r ) . '</details>' . "\n<!-- /wp:details -->\n";
}

function g_page( $slug, $titre, $contenu, $extrait = '', $modele = '', $parent = 0, $ordre = 0, $description = '' ) {
	global $remplacer;
	$page = get_page_by_path( $slug );
	$donnees = [
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_name'    => $slug,
		'post_title'   => $titre,
		'post_content' => $contenu,
		'post_excerpt' => $extrait,
		'post_parent'  => $parent,
		'menu_order'   => $ordre,
	];
	if ( $page && ! $remplacer ) {
		return $page->ID;
	}
	$id = $page ? wp_update_post( [ 'ID' => $page->ID ] + $donnees ) : wp_insert_post( $donnees );
	update_post_meta( $id, '_wp_page_template', $modele ?: 'default' );
	if ( $description ) {
		update_post_meta( $id, '_socle_description', $description );
	}
	return $id;
}

/* ------------------------------------------------------------------ 1. Extension Réalisations du socle */

$ext = 'socle-realisations/socle-realisations.php';
if ( ! is_plugin_active( $ext ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	$r = activate_plugin( $ext );
	echo is_wp_error( $r ) ? '✗ ' . $r->get_error_message() . "\n" : "✓ extension Réalisations activée\n";
	do_action( 'init' );
}
flush_rewrite_rules( false );

/* ------------------------------------------------------------------ 2. Médias */

$m = [
	'ciel'        => g_media( "$figma/accueil/img-3.png", '', 'gayrel-accueil-batiment-b21' ),
	'batiment'    => g_media( "$figma/accueil/img-1.png", '', 'gayrel-accueil-batiment-detoure' ),
	'pro'         => g_media( "$figma/services/img-2.png", 'Bâtiment C80 Airbus à Colomiers, façade aux châssis en bandes filantes', 'solutions-batiment-professionnels' ),
	'particulier' => g_media( "$figma/services/img-6.jpeg", 'Maison contemporaine à bardage sombre et grandes baies vitrées éclairées au crépuscule', 'solutions-habitat-particuliers' ),
	// versions détourées (ciel transparent) des cartes de l'accueil : le grand mot passe derrière le bâtiment
	'pro_detoure' => g_media( "$figma/services/img-3.png", '', 'batiment-c80-detoure' ),
	'part_detoure' => g_media( "$figma/services/img-8.png", '', 'maison-contemporaine-detouree' ),
	'immeuble'    => g_media( "$figma/entreprise/img-2.jpeg", '', 'gayrel-immeuble-facade-aluminium' ),
	'technal'     => g_media( "$figma/entreprise/img-4.png", 'Technal, marque de menuiseries aluminium dont Gayrel est partenaire agréé', 'logo-technal' ),
	'logo'        => g_media( "$figma/entete/img-1.png", 'Gayrel, façade, menuiserie, aluminium', 'logo-gayrel' ),
	'entreprise'  => g_media( "$actuel/entreprise-gayrel.jpg", 'Les locaux de Gayrel dans la zone de Roumagnac à Gaillac', 'entreprise-gayrel-gaillac' ),
	'atelier'     => g_media( "$actuel/atelier-gayrel.jpg", 'L’atelier de fabrication des menuiseries aluminium Gayrel', 'atelier-gayrel' ),
	'etude'       => g_media( "$actuel/bureau-d-etude-gayrel-menuiserie.jpg", 'Étude technique d’une menuiserie aluminium au bureau d’étude', 'bureau-d-etude-gayrel' ),
	'fabrication' => g_media( "$actuel/atelier-de-fabrication-gayrel-2.jpg", 'Fabrication d’un châssis aluminium à l’atelier', 'atelier-de-fabrication-gayrel' ),
	'chantier'    => g_media( "$actuel/chef-de-chantier-gayrel.jpg", 'Chef de chantier Gayrel lors du suivi des travaux', 'suivi-de-chantier-gayrel' ),
	'pose'        => g_media( "$actuel/installation-des-menuiseries-gayrel-gaillac.jpg", 'Pose de menuiseries aluminium sur chantier', 'pose-des-menuiseries-gayrel' ),
	'durable'     => g_media( "$actuel/aluminium-materiaux-durable.jpg", 'Brise-soleil en aluminium sur une façade', 'aluminium-materiau-durable' ),
	'isolation'   => g_media( "$actuel/aluminium-isolation-thermique.jpg", 'Baie coulissante aluminium à rupture de pont thermique', 'aluminium-isolation-thermique' ),
	'recycle'     => g_media( "$actuel/photo-aluminium-recycle.jpg", 'Profilés en aluminium recyclé', 'aluminium-recycle' ),
	'mesure'      => g_media( "$actuel/aluminium-solutions-sur-mesure.jpg", 'Menuiseries aluminium sur mesure', 'menuiserie-aluminium-sur-mesure' ),
	'allforgood'  => g_media( "$actuel/logo-all-for-good.png", 'Label All for Good', 'label-all-for-good' ),
	'qualibat'    => g_media( "$actuel/label-qualibat-en-cours-de-renouvellement.png", 'Label Qualibat', 'label-qualibat' ),
	'agree'       => g_media( "$actuel/fabricant-agree-technal-gayrel.jpg", 'Gayrel, fabricant agréé Technal', 'fabricant-agree-technal' ),
];
echo '✓ médias : ' . count( array_filter( $m ) ) . "\n";

// Logo du site (en-tête) et fiche Établissement
if ( $m['logo'] ) {
	set_theme_mod( 'custom_logo', $m['logo'] );
}

/* ------------------------------------------------------------------ 3. Réalisations (site actuel, fiches complètes) */

$secteur_bat = term_exists( 'Bâtiment', 'secteur' ) ?: wp_insert_term( 'Bâtiment', 'secteur', [ 'slug' => 'batiment', 'description' => 'Bâtiments tertiaires, équipements publics et industriels.' ] );
$secteur_hab = term_exists( 'Habitat', 'secteur' ) ?: wp_insert_term( 'Habitat', 'secteur', [ 'slug' => 'habitat', 'description' => 'Maisons et logements de particuliers.' ] );
$bat         = (int) ( is_array( $secteur_bat ) ? $secteur_bat['term_id'] : $secteur_bat );

// [ titre, slug, lieu, client, architecte, prestations, accroche, texte, [ photos => alt ], en cours ]
$realisations = [
	[ 'Les Halles de la Cartoucherie', 'halles-de-la-cartoucherie', 'Toulouse (31)', 'ROCC Réalisations', 'Compagnie Architecture + Terell', 'Menuiseries extérieures, murs rideaux',
		'Entrée principale et murs rideaux des Halles de la Cartoucherie, nouveau lieu de vie du quartier toulousain.',
		'Reconversion des anciennes halles industrielles de la Cartoucherie en lieu de vie : Gayrel a réalisé les menuiseries extérieures et les murs rideaux, dont l’entrée principale aux profilés aluminium noirs.',
		[ 'les-halles-de-la-cartoucherie.jpg' => 'Entrée principale des Halles de la Cartoucherie à Toulouse, façade et profilés aluminium noirs', 'les-halles-de-la-cartoucherie-zoom-2.jpg' => 'Porte en aluminium et mur rideau de l’entrée des Halles de la Cartoucherie', 'les-halles-de-la-cartoucherie-zoom.jpg' => 'Façade et ouvertures aluminium des Halles de la Cartoucherie' ], false ],
	[ 'Bâtiment C80 Airbus', 'batiment-c80-airbus', 'Colomiers (31)', 'Airbus SAS', 'Séquence Toulouse', 'Menuiseries extérieures : châssis en bandes filantes, châssis fixes',
		'Construction du New Periport d’Airbus : châssis en bandes filantes et châssis fixes.',
		'Construction du New Periport d’Airbus à Colomiers. Gayrel, en sous-traitance de Socotrap, a fabriqué et posé les menuiseries extérieures : châssis en bandes filantes et châssis fixes, sur mesure, avec une attention particulière à l’isolation thermique.',
		[ 'Airbus-New-Periport-blagnac.jpg' => 'Bâtiment C80 New Periport d’Airbus, façade aux menuiseries sur mesure', 'Socotrap-Airbus-New-Periport-zoom-1.jpg' => 'Façade et menuiseries extérieures du New Periport d’Airbus', 'Socotrap-Airbus-New-Periport-7.jpg' => 'Menuiseries extérieures du New Periport vues de l’intérieur', 'Airbus-New-Periport-nuit.jpg' => 'Le New Periport d’Airbus de nuit, intérieur éclairé' ], false ],
	[ 'Bâtiment B21 Airbus', 'batiment-b21-airbus', 'Blagnac (31)', 'Airbus SAS', 'Kardham', 'Menuiseries extérieures, murs rideaux',
		'Customer Care Center d’Airbus à Blagnac : façade aluminium et murs rideaux.',
		'Customer Care Center du site Airbus de Blagnac : menuiseries extérieures et murs rideaux en aluminium sur l’ensemble de la façade.',
		[ 'batiment-b21-blagnac-airbus.jpg' => 'Bâtiment B21 d’Airbus à Blagnac, façade en menuiserie aluminium', 'batiment-b21-blagnac-airbus-nuit.jpg' => 'Le bâtiment B21 de nuit, façade aluminium éclairée', 'batiment-b21-blagnac-airbus-zoom.jpg' => 'Détail d’un mur rideau aluminium du bâtiment B21' ], false ],
	[ 'Blagnac Landing', 'blagnac-landing', 'Blagnac (31)', 'Icade Promotion Tertiaire', 'JF Martinie MR3A, Toulouse', 'Menuiseries extérieures : châssis en bandes filantes, murs rideaux (900 m²)',
		'Immeuble de bureaux : 900 m² de murs rideaux et châssis en bandes filantes.',
		'Construction d’un ensemble de bureaux à Blagnac : châssis en bandes filantes et 900 m² de murs rideaux en aluminium, fabriqués sur mesure.',
		[ 'blagnac-landing-3.jpg' => 'Immeuble de bureaux Blagnac Landing, façade aluminium sur mesure', 'blagnac-landing-5.jpg' => 'Murs rideaux de l’ensemble de bureaux Blagnac Landing', 'blagnac-landing-2.jpg' => 'Mur rideau aluminium des bureaux Blagnac Landing' ], false ],
	[ 'Safran', 'safran-blagnac', 'Blagnac (31)', 'Altarea Cogedim', 'Wilmotte & Associés', 'Menuiseries extérieures, murs rideaux',
		'Siège de Safran à Blagnac : menuiseries extérieures et murs rideaux.',
		'Construction du siège de Safran à Blagnac : menuiseries extérieures et murs rideaux en aluminium.',
		[ 'safran.jpg' => 'Siège de Safran à Blagnac, menuiseries extérieures et murs rideaux' ], false ],
	[ 'Claystone', 'claystone-toulouse', 'Toulouse (31)', 'SNC Altarea Cogedim Régions', 'Betem', 'Menuiseries extérieures, protections solaires, stores intérieurs',
		'Immeuble de bureaux de l’écoquartier Guillaumet : menuiseries, protections solaires et stores.',
		'Immeuble de bureaux dans l’écoquartier Guillaumet, à Toulouse : menuiseries extérieures, protections solaires et stores intérieurs. Chantier en cours de réalisation.',
		[ 'claystone-1.jpg' => 'Façade aluminium de l’immeuble Claystone à Toulouse', 'claystone-2.jpg' => 'Menuiseries extérieures et protections solaires du chantier Claystone', 'claystone-3.jpg' => 'Menuiseries extérieures de l’immeuble Claystone, écoquartier Guillaumet' ], true ],
	[ 'Aérocampus', 'aerocampus-blagnac', 'Blagnac (31)', 'SAS Les Boulots', 'Execo + PPA', 'Menuiseries extérieures, murs rideaux, bardages, portes automatiques, plafonds extérieurs',
		'Immeuble de bureaux en groupement : menuiseries, murs rideaux, bardages et portes automatiques.',
		'Immeuble de bureaux à Blagnac, en groupement avec Sylvea : menuiseries extérieures, murs rideaux, bardages, portes automatiques et plafonds extérieurs. Chantier en cours de réalisation.',
		[ 'aerocampus-blagna.jpg' => 'Finitions des fenêtres aluminium de l’Aérocampus à Blagnac', 'aerocampus-blagna-zoom.jpg' => 'Châssis aluminium posés sur le bâtiment Aérocampus' ], true ],
	[ 'Salle de spectacle de la Cartoucherie', 'salle-de-spectacle-cartoucherie', 'Toulouse (31)', 'ROCC Réalisations', 'Compagnie Architecture + Execo', 'Menuiseries extérieures, murs rideaux',
		'Murs rideaux en arches pour la salle de spectacle de la Cartoucherie.',
		'Salle de spectacle du quartier de la Cartoucherie, à Toulouse : menuiseries extérieures et murs rideaux en aluminium épousant les arches du bâtiment.',
		[ 'salle-des-spectacles-cartoucherie.jpg' => 'Salle de spectacle de la Cartoucherie, murs rideaux aluminium en forme d’arches' ], false ],
	[ 'Pôle sportif Ramierou', 'pole-sportif-ramierou', 'Montauban (82)', 'Ville de Montauban', 'De La Serre (mandataire GBMP / ETC)', 'Menuiseries extérieures',
		'Complexe éducatif et sportif du Ramierou : menuiseries extérieures.',
		'Construction d’un complexe éducatif et sportif dans le quartier du Ramierou, à Montauban : menuiseries extérieures et murs rideaux.',
		[ 'Centre-sportif-Ramierou-3.jpg' => 'Pôle sportif du Ramierou à Montauban', 'Centre-sportif-Ramierou-1.jpg' => 'Extérieur du pôle sportif Ramierou, menuiseries sur mesure', 'Centre-sportif-Ramierou-2.jpg' => 'Murs rideaux du pôle sportif Ramierou' ], false ],
	[ 'Collège Bellevue', 'college-bellevue-albi', 'Albi (81)', 'Département du Tarn', 'Borrel Charon Architectes', 'Menuiseries extérieures : rénovation d’un mur rideau',
		'Rénovation du mur rideau du collège Bellevue d’Albi.',
		'Rénovation d’un mur rideau pour le collège Bellevue d’Albi, pour le compte du Département du Tarn.',
		[ 'bellevue.jpg' => 'Façade rénovée du collège Bellevue à Albi' ], false ],
	[ 'SMAC de Montauban', 'smac-montauban', 'Montauban (82)', 'Ville de Montauban', 'King Kong Architectes + TPFI', 'Menuiseries extérieures',
		'Salle de spectacles et de musiques actuelles : menuiseries extérieures.',
		'Construction de la salle de spectacles et de musiques actuelles (SMAC) de Montauban : menuiseries extérieures, en sous-traitance de Socotrap. Chantier en cours de réalisation.',
		[ 'smac-salle-de-spectacle-et-de-musique-actuelles-Montauban.jpg' => 'Chantier de la SMAC de Montauban, pose des menuiseries et de la façade' ], true ],
	[ 'Piscine du Pays d’Uzès', 'piscine-uzes', 'Uzès (30)', 'Communauté de communes du Pays d’Uzès', 'Éric Lemaire Architecte + Midi Architecture', 'Menuiseries extérieures',
		'Piscine intercommunale du Pays d’Uzès : menuiseries extérieures.',
		'Construction de la piscine intercommunale du Pays d’Uzès : menuiseries extérieures aluminium. Chantier en cours de réalisation.',
		[ 'piscine-intercommunale-uzes.jpg' => 'Chantier de la piscine d’Uzès, menuiseries aluminium en cours d’installation' ], true ],
];
foreach ( $realisations as $ordre => [ $titre, $slug, $lieu, $client, $archi, $presta, $accroche, $texte, $photos, $en_cours ] ) {
	$ids = [];
	foreach ( $photos as $f => $alt ) {
		$ids[] = g_media( "$actuel/$f", $alt, pathinfo( $f, PATHINFO_FILENAME ) );
	}
	$ids     = array_values( array_filter( $ids ) );
	$contenu = g_p( $texte );
	if ( count( $ids ) > 1 ) {
		$imgs = '';
		foreach ( array_slice( $ids, 1 ) as $i ) {
			$imgs .= g_image( $i, '', 'large' );
		}
		$contenu .= '<!-- wp:gallery {"linkTo":"none","columns":2} -->' . "\n" . '<figure class="wp-block-gallery has-nested-images columns-2 is-cropped">' . $imgs . '</figure>' . "\n<!-- /wp:gallery -->\n";
	}
	$existant = get_page_by_path( $slug, OBJECT, 'realisation' );
	if ( $existant && ! $remplacer ) {
		continue;
	}
	$donnees = [ 'post_type' => 'realisation', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $titre, 'post_excerpt' => $accroche, 'post_content' => $contenu, 'menu_order' => $ordre + 1 ];
	$id      = $existant ? wp_update_post( [ 'ID' => $existant->ID ] + $donnees ) : wp_insert_post( $donnees );
	foreach ( [ 'lieu' => $lieu, 'client' => $client, 'architecte' => $archi, 'prestations' => $presta . ( $en_cours ? ' — en cours de réalisation' : '' ) ] as $k => $v ) {
		update_post_meta( $id, $k, $v );
	}
	if ( $ids ) {
		set_post_thumbnail( $id, $ids[0] );
	}
	wp_set_object_terms( $id, [ $bat ], 'secteur' );
}
echo '✓ réalisations : ' . count( $realisations ) . "\n";

/* ------------------------------------------------------------------ 4. Pages */

$cta = fn( $t, $l, $clair = false ) => [ $t, $l, 'g-cta' . ( $clair ? ' g-cta--clair' : '' ) ];

// ---- Accueil (maquette Figma « Home »)
$hero = g_groupe( 'g-hero alignfull',
	g_image( $m['ciel'], 'g-hero__ciel', 'full', '' )
	. g_geant( 'Gayrel', 'g-hero__marque' )
	. g_image( $m['batiment'], 'g-hero__batiment', 'full', '' )
	. g_groupe( 'g-hero__texte',
		g_titre( '<span class="g-ligne"><span>Façade, </span></span><span class="g-ligne"><span>Menuiserie, </span></span><span class="g-ligne"><span>aluminium.</span></span>', 1 )
		. g_p( 'Concepteur, fabricant et installateur de menuiseries et façades en aluminium depuis 1994', 'g-hero__accroche' ) )
	. g_groupe( 'g-hero__acces',
		g_p( '<a href="/habitat/">Solutions pour l’Habitat<br>Particuliers</a>', 'g-acces g-acces--habitat' )
		. g_p( '<a href="/batiment/">Solutions pour le Bâtiment<br>Professionnels</a>', 'g-acces g-acces--batiment' ) )
);
$chiffres = g_groupe( 'g-chiffres',
	g_groupe( 'g-chiffre g-chiffre--experience', g_p( '+30 ans<br>d’expérience' ) )
	. g_groupe( 'g-chiffre g-chiffre--recompenses', g_p( 'Plusieurs<br>récompenses' ) )
	. g_groupe( 'g-chiffre g-chiffre--sur-mesure', g_p( '100 %<br>sur-mesure' ) )
	. g_groupe( 'g-chiffre g-chiffre--garantie', g_p( 'Produits<br>garantis' ) )
);
$services = g_groupe( 'g-services',
	g_geant( 'Nos<br>services', 'g-services__titre' )
	. g_titre( 'Nos services', 2, 'g-lecteur' )
	. g_groupe( 'g-service g-service--pro',
		g_titre( 'Solutions pour le Bâtiment et les Professionnels', 3 )
		. g_p( 'Architectes, maîtres d’œuvre, entreprises générales et collectivités : nous concevons, fabriquons et posons les menuiseries et façades aluminium de vos bâtiments tertiaires, industriels et équipements publics. Murs rideaux, châssis en bandes filantes, portes, brise-soleil, garde-corps : notre équipe maîtrise toute la chaîne, de l’étude technique à la réception des travaux, en neuf comme en rénovation.' )
		. g_boutons( [ $cta( 'Nos solutions pour le Bâtiment', '/batiment/', true ) ] )
		. g_geant( 'Profes-<br>sionnels' )
		. g_image( $m['pro_detoure'], '', 'large', '' ) )
	. g_groupe( 'g-service g-service--part',
		g_titre( 'Nos solutions pour l’Habitat et les Particuliers', 3 )
		. g_p( 'Fenêtres, baies coulissantes, portes d’entrée, volets, garde-corps : nous fabriquons dans notre atelier de Gaillac des menuiseries aluminium sur mesure pour votre maison, avec les gammes Technal. Isolation thermique, sécurité, finitions et couleurs : nous vous conseillons et posons vos menuiseries, en construction comme en rénovation.' )
		. g_boutons( [ $cta( 'Nos solutions pour l’Habitat', '/habitat/', true ) ] )
		. g_geant( 'Parti-<br>culiers' )
		. g_image( $m['part_detoure'], '', 'large', '' ) )
);
$projets = g_groupe( 'g-projets',
	g_geant( 'Découvrir<br>des projets<br>concrets', 'g-projets__geant' )
	. g_groupe( 'g-projets__liste',
		g_titre( 'Nos réalisations' )
		. '<!-- wp:query {"queryId":10,"query":{"perPage":4,"postType":"realisation","order":"asc","orderBy":"menu_order","inherit":false}} -->' . "\n"
		. '<div class="wp-block-query"><!-- wp:post-template -->' . "\n"
		. g_groupe( 'g-carte-projet', '<!-- wp:post-featured-image {"sizeSlug":"large"} /-->' . "\n" . '<!-- wp:post-title {"level":3,"isLink":true} /-->' . "\n" )
		. "<!-- /wp:post-template --></div>\n<!-- /wp:query -->\n"
		. g_boutons( [ $cta( 'Toutes nos réalisations', '/realisations/' ) ] ) )
);
$entreprise = g_groupe( 'g-duo g-duo--entreprise',
	g_groupe( 'g-duo__texte g-cube',
		g_titre( 'Expert en menuiserie et façade aluminium' )
		. g_p( 'Gayrel SAS, située à Gaillac entre Albi et Toulouse, se distingue par son expertise technique en menuiserie aluminium orientée vers la clientèle professionnelle sur les marchés publics.' )
		. g_p( 'Forte d’un savoir-faire reconnu, notre entreprise accompagne les professionnels sur des projets architecturaux complexes, en neuf comme en rénovation.' )
		. g_p( 'Notre équipe maîtrise l’ensemble de la chaîne, de l’étude technique à la mise en œuvre sur site, pour garantir performance, fiabilité et conformité aux exigences du marché.' )
		. g_boutons( [ $cta( 'En savoir plus sur l’entreprise', '/entreprise/' ) ] )
		. g_geant( 'Gayrel' ) )
	. g_groupe( 'g-duo__visuel', g_image( $m['immeuble'], '', 'large', '' ) )
);
$partenaire = g_groupe( 'g-duo g-duo--partenaire',
	g_groupe( 'g-duo__logo', g_image( $m['technal'], '', 'full' ) )
	. g_groupe( 'g-duo__texte',
		g_titre( 'Fiabilité d’une marque reconnue' )
		. g_p( 'Technal® est une marque de référence, reconnue pour la qualité de ses produits et son engagement envers l’innovation et la durabilité. En tant que partenaire agréé, nous bénéficions d’une très grande expérience en termes de conseils, de fabrication et de pose de menuiseries aluminium : portes, fenêtres, murs rideaux, garde-corps… Nous avons accès à une large gamme de solutions sur mesure adaptées à tous types de projets. Cela inclut des produits à la pointe de la technologie en termes d’isolation thermique, de résistance, de sécurité et de conception.' )
		. g_boutons( [ $cta( 'Contacter nos équipes', '/contact/' ) ] ) )
);
$faq_questions = [
	[ 'Travaillez-vous pour les particuliers comme pour les professionnels ?', 'Oui. Notre cœur de métier est le bâtiment tertiaire et les marchés publics, mais nous fabriquons et posons aussi des menuiseries aluminium sur mesure pour les maisons et logements de particuliers : fenêtres, baies coulissantes, portes, volets, garde-corps.' ],
	[ 'Dans quelle zone intervenez-vous ?', 'Nous sommes installés à Gaillac, entre Albi et Toulouse. Nous intervenons principalement dans le Tarn, la Haute-Garonne et le Tarn-et-Garonne, et plus largement en Occitanie selon les projets.' ],
	[ 'Fabriquez-vous vous-mêmes vos menuiseries ?', 'Oui. Nos menuiseries sont fabriquées sur mesure dans notre atelier de Gaillac, avec des profilés Technal, puis posées par nos propres équipes.' ],
	[ 'Répondez-vous aux marchés publics ?', 'Oui. Nous répondons régulièrement aux appels d’offres des collectivités : collèges, équipements sportifs, piscines, salles de spectacle. Notre bureau d’étude prépare les dossiers techniques.' ],
	[ 'Quels produits proposez-vous ?', 'Murs rideaux, fenêtres et portes-fenêtres, baies coulissantes, portes, stores et brise-soleil, volets roulants, garde-corps, produits coupe-feu et produits spécifiques, dans une large gamme de couleurs et de finitions.' ],
	[ 'Les menuiseries aluminium sont-elles bien isolantes ?', 'Oui. Nos menuiseries intègrent une rupture de pont thermique qui interrompt la transmission de chaleur entre l’intérieur et l’extérieur, avec le vitrage adapté à votre projet (isolation thermique et acoustique, sécurité).' ],
	[ 'L’aluminium que vous utilisez est-il recyclé ?', 'Nos châssis sont fabriqués avec un aluminium recyclé à faible empreinte carbone, issu d’une filière française. L’aluminium se recycle ensuite à l’infini sans perdre ses qualités.' ],
	[ 'Comment obtenir un devis ?', 'Décrivez votre projet sur la page Devis ou appelez-nous au <a href="tel:+33563814242">05 63 81 42 42</a>. Joignez si possible vos plans ou le cahier des charges : notre bureau d’étude vous répond rapidement.' ],
];
$faq = g_groupe( 'g-duo g-faq',
	g_groupe( 'g-duo__texte g-cube', g_titre( 'Vous avez une question,<br>vos réponses sont ici !' ) . g_geant( 'FAQ' ) )
	. g_groupe( 'g-faq__liste', implode( '', array_map( fn( $q ) => g_question( ...$q ), $faq_questions ) ) )
);
$accueil = g_page( 'accueil', 'Accueil',
	$hero . $chiffres . g_trait() . $services . g_trait() . $projets . g_trait() . $entreprise . g_trait() . $partenaire . g_trait() . $faq . g_trait(),
	'', '', 0, 0,
	'Gayrel, à Gaillac (Tarn) : conception, fabrication et pose de menuiseries et façades aluminium sur mesure depuis 1994. Partenaire agréé Technal, marchés publics et particuliers.'
);

// ---- L'entreprise (ancienne page « À propos » du socle, adresse /entreprise/ du site actuel)
$apropos = get_page_by_path( 'a-propos' );
if ( $apropos && ! get_page_by_path( 'entreprise' ) ) {
	wp_update_post( [ 'ID' => $apropos->ID, 'post_name' => 'entreprise' ] );
}
$valeurs = [
	[ 'Exigence et qualité', 'Chaque projet est réalisé avec exigence, dans le respect des normes et une qualité irréprochable.' ],
	[ 'Fiabilité', 'Nos partenaires peuvent compter sur nous : nous tenons nos engagements en termes de délais, de coûts et de conformité.' ],
	[ 'Esprit d’équipe', 'En interne comme avec nos clients, la collaboration est au cœur de notre réussite collective, basée sur la confiance et la transparence.' ],
	[ 'Durabilité', 'Nous concevons des menuiseries pensées pour durer, dans le respect des normes et de l’environnement.' ],
];
$savoir_faire = [
	[ 'Bureau d’étude', $m['etude'], 'Études techniques et conception sur mesure', [
		'Notre bureau d’étude étudie chaque projet dans ses moindres détails pour vous proposer les solutions sur mesure les plus adaptées à vos exigences techniques et esthétiques.',
		'Architectes, maîtres d’œuvre ou artisans : nous vous conseillons sur les matériaux, la conception et la performance énergétique, dans le respect du cahier des charges, des normes et des réglementations.',
	], [ 'Étude et conseil technique', 'Technologies BIM et DAO', 'Respect des délais et des budgets' ] ],
	[ 'Atelier de fabrication', $m['fabrication'], 'Fabrication sur mesure dans nos ateliers', [
		'Fenêtres, portes, volets, murs rideaux : chaque produit est fabriqué dans notre atelier de Gaillac par une équipe qualifiée qui maîtrise tous les procédés de fabrication, avec des profilés certifiés Technal.',
		'Notre équipement industriel nous permet de répondre à des productions à grande échelle, avec une exigence constante de qualité et de performance énergétique.',
	], [ 'Production 100 % française', 'Maîtrise des procédés de fabrication', 'Large gamme de produits sur mesure' ] ],
	[ 'Suivi de chantier', $m['chantier'], 'Des menuiseries livrées clés en main', [
		'Un interlocuteur dédié vous accompagne de la conception à la fin du chantier. Nous vous tenons informés de l’avancement et effectuons des contrôles réguliers : bon déroulement des opérations, sécurité du site, respect des normes.',
		'À la fin du chantier, nous effectuons avec vous la réception des travaux, qui valide la conformité des ouvrages et marque le début des garanties légales.',
	], [ 'Suivi de chantier complet', 'Exécution conforme aux normes', 'Réception des travaux' ] ],
	[ 'Pose des menuiseries', $m['pose'], 'Une installation maîtrisée sur site', [
		'Nos équipes de pose, qualifiées et expérimentées, installent vos menuiseries aluminium dans le respect des délais et des règles de sécurité, en neuf comme en rénovation.',
		'Chaque installation est réalisée avec précision pour garantir l’étanchéité et la performance des produits, en coordination avec les autres intervenants du chantier.',
	], [ 'Installation professionnelle', 'Connaissance approfondie des produits', 'Respect des normes et réglementations' ] ],
];
$blocs_sf = '';
foreach ( $savoir_faire as $i => [ $titre, $img, $sous, $paras, $points ] ) {
	$blocs_sf .= g_groupe( 'g-bloc' . ( $i % 2 ? ' g-bloc--inverse' : '' ),
		g_groupe( 'g-bloc__texte', g_titre( $titre, 3 ) . g_p( '<strong>' . $sous . '</strong>' ) . implode( '', array_map( 'g_p', $paras ) ) . g_liste( $points ) )
		. g_image( $img, '', 'large' ) );
}
$labels = g_groupe( 'g-cartes',
	g_groupe( 'g-carte', g_image( $m['allforgood'], '', 'full' ) . g_titre( 'All for Good', 3 ) . g_p( 'Un engagement en faveur de la qualité responsable, de l’éthique et de l’environnement.' ) )
	. g_groupe( 'g-carte', g_image( $m['qualibat'], '', 'full' ) . g_titre( 'Qualibat', 3 ) . g_p( 'La reconnaissance de notre savoir-faire technique et de la conformité aux normes de construction (en cours de renouvellement).' ) )
	. g_groupe( 'g-carte', g_image( $m['agree'], '', 'full' ) . g_titre( 'Agrément Technal', 3 ) . g_p( 'Un label de qualité qui atteste de notre partenariat avec un leader européen de la menuiserie aluminium.' ) )
);
$id_entreprise = g_page( 'entreprise', 'L’entreprise',
	g_groupe( 'g-section',
		g_groupe( 'g-bloc',
			g_groupe( 'g-bloc__texte g-cube',
				g_titre( 'Une expertise technique dédiée aux professionnels du bâtiment' )
				. g_p( 'Gayrel SAS, située à Gaillac entre Albi et Toulouse, se distingue par son expertise technique en menuiserie aluminium orientée vers la clientèle professionnelle sur les marchés publics.' )
				. g_p( 'Notre savoir-faire et notre solide expérience permettent de répondre aux chantiers techniques et architecturaux les plus complexes, en neuf comme en rénovation. Notre équipe pluridisciplinaire gère l’intégralité des projets de menuiseries sur mesure, de la conception à la réception des travaux.' )
				. g_p( 'Notre partenariat avec Technal est un gage de sécurité et de fiabilité : il nous permet de proposer la solution la plus durable, esthétique et performante, adaptée aux contraintes de votre projet. Notre connaissance du bâtiment nous permet d’anticiper et de résoudre rapidement les difficultés techniques du chantier, pour livrer sans retard.' )
				. g_boutons( [ $cta( 'Voir nos réalisations', '/realisations/' ) ] ) )
			. g_image( $m['entreprise'], '', 'large' ) ) )
	. g_groupe( 'g-section',
		g_groupe( 'g-section__tete', g_titre( 'Nos valeurs' ) . g_p( 'Chaque chantier est mené dans le respect de nos principes fondamentaux.' ) )
		. g_groupe( 'g-cartes g-cartes--sombre', implode( '', array_map( fn( $v ) => g_groupe( 'g-carte', g_titre( $v[0], 3 ) . g_p( $v[1] ) ), $valeurs ) ) ) )
	. g_groupe( 'g-section',
		g_groupe( 'g-section__tete', g_titre( 'Notre savoir-faire, de l’étude à la pose' ) . g_p( 'Une seule entreprise pour toute la chaîne : vos menuiseries sont étudiées, fabriquées et posées par nos équipes.' ) )
		. $blocs_sf )
	. g_groupe( 'g-section',
		g_groupe( 'g-section__tete', g_titre( 'La qualité au cœur de notre démarche' ) . g_p( 'Notre démarche qualité garantit une maîtrise technique totale de nos chantiers, dans le respect des normes en matière de résistance, de durée de vie et d’isolation phonique et thermique.' ) )
		. $labels )
	. $partenaire
	. '<!-- wp:pattern {"slug":"socle-gutenberg/appel"} /-->',
	'Menuiserie aluminium depuis 1994 : une équipe qui étudie, fabrique et pose vos menuiseries et façades sur mesure, à Gaillac.',
	'page-visuel', 0, 1,
	'Gayrel, entreprise de menuiserie aluminium à Gaillac depuis 1994 : bureau d’étude, atelier de fabrication, suivi de chantier et pose. Partenaire agréé Technal.'
);
if ( $m['atelier'] ) {
	set_post_thumbnail( $id_entreprise, $m['atelier'] );
}

// ---- Bâtiment (professionnels)
$produits_pro = [ 'Murs rideaux', 'Façades et bardages', 'Châssis en bandes filantes', 'Fenêtres et portes-fenêtres', 'Portes et portes automatiques', 'Stores et brise-soleil', 'Garde-corps', 'Produits coupe-feu', 'Produits spécifiques' ];
$id_batiment = g_page( 'batiment', 'Bâtiment',
	g_groupe( 'g-section',
		g_groupe( 'g-bloc',
			g_groupe( 'g-bloc__texte g-cube',
				g_titre( 'Menuiseries et façades aluminium pour les professionnels' )
				. g_p( 'Bureaux, sièges sociaux, bâtiments industriels, établissements scolaires, équipements sportifs et culturels : nous accompagnons architectes, maîtres d’œuvre, entreprises générales et collectivités sur des projets architecturaux complexes, en neuf comme en rénovation.' )
				. g_p( 'En direct, en sous-traitance ou en groupement, nous répondons aux marchés publics et privés avec un bureau d’étude intégré (BIM, DAO), une fabrication en atelier et nos propres équipes de pose.' )
				. g_liste( $produits_pro, 'g-etiquettes' )
				. g_boutons( [ $cta( 'Consulter notre bureau d’étude', '/devis/' ) ] ) )
			. g_image( $m['pro'], '', 'large' ) ) )
	. g_groupe( 'g-section',
		g_groupe( 'g-section__tete', g_titre( 'Des références sur tout le territoire' ) . g_p( 'Airbus, Safran, Icade, collectivités du Tarn, de Haute-Garonne et du Tarn-et-Garonne : quelques chantiers récents.' ) )
		. '<!-- wp:query {"queryId":11,"query":{"perPage":6,"postType":"realisation","order":"asc","orderBy":"menu_order","inherit":false,"taxQuery":{"secteur":[' . $bat . ']}},"className":"g-grille"} -->' . "\n"
		. '<div class="wp-block-query g-grille"><!-- wp:post-template -->' . "\n"
		. g_groupe( 'g-carte-projet', '<!-- wp:post-featured-image {"sizeSlug":"large","aspectRatio":"4/3"} /-->' . "\n" . '<!-- wp:post-title {"level":3,"isLink":true} /-->' . "\n" )
		. "<!-- /wp:post-template --></div>\n<!-- /wp:query -->\n"
		. g_boutons( [ $cta( 'Toutes nos réalisations', '/realisations/' ) ] ) )
	. $partenaire
	. '<!-- wp:pattern {"slug":"socle-gutenberg/appel"} /-->',
	'Murs rideaux, façades, menuiseries extérieures : nous concevons, fabriquons et posons les ouvrages aluminium des bâtiments tertiaires et des équipements publics.',
	'', 0, 3,
	'Menuiseries et façades aluminium pour les professionnels et les marchés publics : murs rideaux, châssis, brise-soleil, portes. Bureau d’étude, fabrication et pose à Gaillac (Tarn).'
);

// ---- Habitat (particuliers) : page dérivée, contenus produits du site actuel
$aluminium = [
	[ 'L’aluminium, durable et solide', $m['durable'], [ 'Léger et résistant, l’aluminium supporte les intempéries sans se déformer et demande très peu d’entretien. Il permet des profils fins et de grandes surfaces vitrées pour faire entrer la lumière.', 'Recyclable à l’infini sans perdre ses qualités, c’est un matériau particulièrement respectueux de l’environnement.' ], [ 'Légèreté et résistance', 'Durabilité et résistance aux intempéries', 'Esthétique et design' ] ],
	[ 'Isolation thermique et confort', $m['isolation'], [ 'Nos menuiseries aluminium intègrent une rupture de pont thermique, une technologie qui interrompt la transmission de chaleur entre l’intérieur et l’extérieur.', 'Associées au vitrage adapté, elles réduisent vos dépenses d’énergie et les nuisances sonores, été comme hiver.' ], [ 'Économies d’énergie', 'Confort thermique optimisé', 'Réduction des nuisances sonores' ] ],
	[ 'Sur mesure, jusqu’aux finitions', $m['mesure'], [ 'Fenêtres, portes, baies vitrées : chaque menuiserie est fabriquée à vos dimensions dans notre atelier de Gaillac.', 'Choisissez les couleurs, textures et finitions, ainsi que le vitrage : isolation thermique et acoustique, sécurité renforcée ou vitrage autonettoyant.' ], [ 'Dimensions adaptées', 'Design et finitions', 'Types de vitrage', 'Configurations variées' ] ],
	[ 'Un aluminium recyclé, bas carbone', $m['recycle'], [ 'Nos châssis sont fabriqués avec un aluminium recyclé à faible empreinte carbone, issu d’une filière française engagée dans la décarbonation du bâtiment.' ], [] ],
];
$blocs_alu = '';
foreach ( $aluminium as $i => [ $titre, $img, $paras, $points ] ) {
	$blocs_alu .= g_groupe( 'g-bloc' . ( $i % 2 ? ' g-bloc--inverse' : '' ),
		g_groupe( 'g-bloc__texte', g_titre( $titre, 3 ) . implode( '', array_map( 'g_p', $paras ) ) . ( $points ? g_liste( $points ) : '' ) )
		. g_image( $img, '', 'large' ) );
}
$produits_hab = [ 'Fenêtres et portes-fenêtres', 'Baies coulissantes', 'Portes d’entrée', 'Volets roulants', 'Stores et brise-soleil', 'Garde-corps' ];
$id_habitat = g_page( 'habitat', 'Habitat',
	g_groupe( 'g-section',
		g_groupe( 'g-bloc',
			g_groupe( 'g-bloc__texte g-cube',
				g_titre( 'Menuiseries aluminium sur mesure pour votre maison' )
				. g_p( 'Construction ou rénovation : nous fabriquons dans notre atelier de Gaillac des menuiseries aluminium sur mesure, avec les gammes Technal, et nos équipes les posent chez vous.' )
				. g_p( 'Vous bénéficiez de la même exigence que sur nos chantiers professionnels : étude de votre projet, conseils sur les performances et les finitions, pose soignée et étanche.' )
				. g_liste( $produits_hab, 'g-etiquettes' )
				. g_boutons( [ $cta( 'Demander un devis', '/devis/' ) ] ) )
			. g_image( $m['particulier'], '', 'large' ) ) )
	. g_groupe( 'g-section', g_groupe( 'g-section__tete', g_titre( 'Pourquoi choisir l’aluminium ?' ) . g_p( 'Un matériau moderne, apprécié pour ses qualités techniques et esthétiques.' ) ) . $blocs_alu )
	. $partenaire
	. '<!-- wp:pattern {"slug":"socle-gutenberg/appel"} /-->',
	'Fenêtres, baies coulissantes, portes, volets et garde-corps en aluminium, fabriqués sur mesure à Gaillac et posés par nos équipes.',
	'', 0, 2,
	'Menuiseries aluminium sur mesure pour particuliers dans le Tarn : fenêtres, baies coulissantes, portes d’entrée, volets, garde-corps. Fabrication à Gaillac, gammes Technal.'
);

// ---- Showroom : informations à compléter par le client (horaires d'ouverture au public, produits exposés)
$id_showroom = g_page( 'showroom', 'Showroom',
	g_groupe( 'g-section',
		g_groupe( 'g-bloc',
			g_groupe( 'g-bloc__texte g-cube',
				g_titre( 'Voir et toucher nos menuiseries' )
				. g_p( 'Venez découvrir nos menuiseries aluminium dans nos locaux de Gaillac : profilés, finitions, couleurs et vitrages. Nos équipes vous conseillent pour choisir la solution adaptée à votre projet.' )
				. g_p( '<span class="a-completer">[à compléter]</span> produits exposés, jours et horaires d’ouverture du showroom, accueil sur rendez-vous ou non.' )
				. '<!-- wp:shortcode -->' . "\n[socle_coordonnees]\n<!-- /wp:shortcode -->\n"
				. '<!-- wp:shortcode -->' . "\n[socle_horaires]\n<!-- /wp:shortcode -->\n"
				. g_boutons( [ $cta( 'Prendre rendez-vous', '/contact/' ) ] ) )
			. g_image( $m['entreprise'], '', 'large' ) ) )
	. '<!-- wp:pattern {"slug":"socle-gutenberg/appel"} /-->',
	'Profilés, couleurs, finitions et vitrages : découvrez nos menuiseries aluminium à Gaillac.',
	'', 0, 4,
	'Showroom Gayrel à Gaillac (Tarn) : découvrez nos menuiseries aluminium, couleurs, finitions et vitrages, et les conseils de nos équipes.'
);

// ---- Actualités (page des articles : modèle home.html)
$id_actus = g_page( 'actualites', 'Actualités', '', '', '', 0, 5, 'Actualités de Gayrel : chantiers en cours, livraisons et vie de l’entreprise de menuiserie aluminium à Gaillac.' );

// ---- Contact et Devis : formulaire du socle + coordonnées
$coordonnees = g_groupe( 'g-coordonnees',
	g_titre( 'Nos coordonnées' )
	. '<!-- wp:shortcode -->' . "\n[socle_coordonnees]\n<!-- /wp:shortcode -->\n"
	. g_titre( 'Horaires', 3 )
	. '<!-- wp:shortcode -->' . "\n[socle_horaires]\n<!-- /wp:shortcode -->\n"
	. g_p( '<a href="https://fr.linkedin.com/company/gayrel-sas">Gayrel sur LinkedIn</a>' ) );
$id_contact = get_page_by_path( 'contact' )?->ID;
$id_contact = g_page( 'contact', 'Contact',
	g_groupe( 'g-section g-contact-page',
		g_groupe( 'g-contact-formulaire', g_titre( 'Envoyez-nous un message' ) . '<!-- wp:shortcode -->' . "\n[socle_formulaire]\n<!-- /wp:shortcode -->\n" )
		. $coordonnees ),
	'Une question, un projet ? Écrivez-nous ou appelez-nous : notre équipe vous répond dans les plus brefs délais.',
	'', 0, 7,
	'Contacter Gayrel, menuiserie aluminium à Gaillac (Tarn) : 13 avenue de l’Europe, ZAC de Roumagnac. Téléphone 05 63 81 42 42.'
);
$id_devis = g_page( 'devis', 'Demande de devis',
	g_groupe( 'g-section g-contact-page',
		g_groupe( 'g-contact-formulaire',
			g_titre( 'Décrivez votre projet' )
			. g_p( 'Indiquez le type de bâtiment, les ouvrages concernés (fenêtres, murs rideaux, portes…), les quantités ou surfaces et le calendrier. Vous pourrez nous transmettre plans et cahier des charges en réponse à notre premier e-mail.' )
			. '<!-- wp:shortcode -->' . "\n[socle_formulaire]\n<!-- /wp:shortcode -->\n" )
		. $coordonnees ),
	'Professionnels, collectivités ou particuliers : notre bureau d’étude étudie votre projet et vous adresse une proposition détaillée.',
	'', 0, 6,
	'Demande de devis pour vos menuiseries et façades aluminium sur mesure : professionnels, marchés publics et particuliers. Gayrel, Gaillac (Tarn).'
);

// ---- Pages légales (contenu du socle complété avec les mentions du site actuel)
g_page( 'mentions-legales', 'Mentions légales',
	g_groupe( 'g-texte',
		g_titre( 'Éditeur du site' )
		. g_p( 'Gayrel, société par actions simplifiée (SAS)<br>ZAC de Roumagnac, 13 avenue de l’Europe, 81600 Gaillac<br>SIRET : 393 889 530 00027 — RCS : <span class="a-completer">[à compléter]</span> greffe et capital social<br>TVA intracommunautaire : FR52393889530<br>Téléphone : <a href="tel:+33563814242">05 63 81 42 42</a> — E-mail : <a href="mailto:contact@gayrel.fr">contact@gayrel.fr</a>' )
		. g_p( 'Directeur ou directrice de la publication : <span class="a-completer">[à compléter]</span> nom du représentant légal.' )
		. g_titre( 'Hébergement' )
		. g_p( '<span class="a-completer">[à compléter]</span> hébergeur du nouveau site (le site actuel est hébergé par o2switch).' )
		. g_titre( 'Conception et réalisation' )
		. g_p( 'Agoravita — <a href="https://www.agoravita.com/">agoravita.com</a>' )
		. g_titre( 'Propriété intellectuelle' )
		. g_p( 'Les contenus de ce site (textes, photographies, logos) sont protégés. Toute reproduction sans autorisation écrite est interdite. Technal® est une marque déposée de son propriétaire. Crédits photographiques : <span class="a-completer">[à compléter]</span>.' ) ),
	'Éditeur, hébergement et propriété intellectuelle du site gayrel.fr.'
);
g_page( 'politique-de-confidentialite', 'Politique de confidentialité',
	g_groupe( 'g-texte',
		g_titre( 'Responsable du traitement' )
		. g_p( 'Gayrel SAS, ZAC de Roumagnac, 13 avenue de l’Europe, 81600 Gaillac — <a href="mailto:contact@gayrel.fr">contact@gayrel.fr</a>' )
		. g_titre( 'Formulaires de contact et de devis' )
		. g_p( 'Les informations envoyées (nom, e-mail, téléphone, message) servent uniquement à répondre à votre demande. Elles sont conservées 1 an au maximum, puis supprimées automatiquement.' )
		. g_titre( 'Cookies et services tiers' )
		. g_p( 'Aucun service tiers n’est chargé sans votre accord. Vous pouvez modifier vos choix à tout moment : <a href="#gestion-cookies">gestion des cookies</a>.' )
		. g_titre( 'Mesure d’audience' )
		. '<!-- wp:shortcode -->' . "\n[socle_mesure_info]\n<!-- /wp:shortcode -->\n"
		. g_titre( 'Vos droits' )
		. g_p( 'Accès, rectification, effacement, opposition, limitation : écrivez-nous à <a href="mailto:contact@gayrel.fr">contact@gayrel.fr</a>. Réclamation possible auprès de la CNIL (cnil.fr/fr/plaintes).' ) ),
	'Quelles données nous recueillons, pourquoi, combien de temps, et comment exercer vos droits.'
);
foreach ( [ 'plan-du-site' ] as $s ) {
	$p = get_page_by_path( $s );
	if ( $p && ! str_contains( $p->post_content, 'g-texte' ) ) {
		wp_update_post( [ 'ID' => $p->ID, 'post_content' => g_groupe( 'g-texte', $p->post_content ) ] );
	}
	if ( $p && ! has_excerpt( $p ) ) {
		wp_update_post( [ 'ID' => $p->ID, 'post_excerpt' => 'Toutes les pages du site, pour trouver rapidement une information.' ] );
	}
}
echo "✓ pages\n";

/* ------------------------------------------------------------------ 5. Actualités (chantiers en cours du site actuel) */

$actus = [
	[ 'aerocampus-blagnac-chantier-en-cours', 'Aérocampus à Blagnac : un chantier en groupement', 'aerocampus-blagna.jpg',
		'Menuiseries extérieures, murs rideaux, bardages, portes automatiques et plafonds extérieurs : nous réalisons en groupement l’enveloppe d’un nouvel immeuble de bureaux à Blagnac.',
		[ 'Avec Sylvea, nous réalisons en groupement les menuiseries extérieures, les murs rideaux, les bardages, les portes automatiques et les plafonds extérieurs de l’Aérocampus, un immeuble de bureaux à Blagnac (maîtrise d’ouvrage SAS Les Boulots, architectes Execo et PPA).', 'Ce chantier illustre notre capacité à prendre en charge l’ensemble de l’enveloppe d’un bâtiment, de l’étude à la pose.' ], 'aerocampus-blagnac' ],
	[ 'smac-montauban-pose-des-menuiseries', 'SMAC de Montauban : pose des menuiseries extérieures', 'smac-salle-de-spectacle-et-de-musique-actuelles-Montauban.jpg',
		'La future salle de spectacles et de musiques actuelles de Montauban prend forme : nos équipes posent les menuiseries extérieures.',
		[ 'Pour la Ville de Montauban, et en sous-traitance de Socotrap, nous fabriquons et posons les menuiseries extérieures de la future salle de spectacles et de musiques actuelles (architectes King Kong Architectes et TPFI).' ], 'smac-montauban' ],
	[ 'piscine-du-pays-d-uzes', 'Piscine du Pays d’Uzès : les menuiseries en cours d’installation', 'piscine-intercommunale-uzes.jpg',
		'Pour la communauté de communes du Pays d’Uzès, nous installons les menuiseries extérieures de la nouvelle piscine intercommunale.',
		[ 'La piscine intercommunale du Pays d’Uzès (Gard) est en construction : nos menuiseries aluminium, fabriquées dans notre atelier de Gaillac, y sont en cours d’installation (architectes Éric Lemaire et Midi Architecture).' ], 'piscine-uzes' ],
];
foreach ( $actus as $i => [ $slug, $titre, $photo, $extrait, $paras, $lien ] ) {
	$existant = get_page_by_path( $slug, OBJECT, 'post' );
	if ( $existant && ! $remplacer ) {
		continue;
	}
	$contenu = implode( '', array_map( 'g_p', $paras ) ) . g_p( '<a href="/realisations/' . $lien . '/">Voir la fiche de la réalisation</a>' );
	$donnees = [ 'post_type' => 'post', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $titre, 'post_excerpt' => $extrait, 'post_content' => $contenu, 'post_date' => wp_date( 'Y-m-d H:i:s', strtotime( '-' . ( 7 + 21 * $i ) . ' days' ) ) ];
	$id      = $existant ? wp_update_post( [ 'ID' => $existant->ID ] + $donnees ) : wp_insert_post( $donnees );
	$img     = g_media( "$actuel/$photo", '', pathinfo( $photo, PATHINFO_FILENAME ) );
	if ( $img ) {
		set_post_thumbnail( $id, $img );
	}
}
// Article d'exemple de WordPress
$bonjour = get_page_by_path( 'bonjour-tout-le-monde', OBJECT, 'post' );
if ( $bonjour ) {
	wp_delete_post( $bonjour->ID, true );
}
echo "✓ actualités\n";

/* ------------------------------------------------------------------ 6. Lecture, menus, en-tête et pied */

wp_get_theme()->delete_pattern_cache(); // compositions du thème (pied de page, appel) relues

update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $accueil );
update_option( 'page_for_posts', $id_actus );

$lien = fn( $titre, $id ) => sprintf( '<!-- wp:navigation-link {"label":"%s","type":"page","id":%d,"url":"%s","kind":"post-type"} /-->', esc_attr( $titre ), $id, esc_url( get_permalink( $id ) ) );
$libre = fn( $titre, $url ) => sprintf( '<!-- wp:navigation-link {"label":"%s","url":"%s","kind":"custom"} /-->', esc_attr( $titre ), esc_attr( $url ) );
$mobile    = fn( $html ) => str_replace( '"kind":"post-type"}', '"kind":"post-type","className":"g-seulement-mobile"}', $html ); // entrées du seul panneau mobile
$principal = [ $lien( 'L’entreprise', $id_entreprise ), $lien( 'Habitat', $id_habitat ), $lien( 'Bâtiment', $id_batiment ), $lien( 'Showroom', $id_showroom ), $lien( 'Actualités', $id_actus ), $mobile( $lien( 'Devis', $id_devis ) ), $mobile( $lien( 'Nous contacter', $id_contact ) ) ];
$pied      = [ $lien( 'L’entreprise', $id_entreprise ), $lien( 'Habitat', $id_habitat ), $lien( 'Bâtiment', $id_batiment ), $libre( 'Réalisations', '/realisations/' ), $lien( 'Showroom', $id_showroom ), $lien( 'Actualités', $id_actus ), $lien( 'Nous contacter', $id_contact ) ];
$legal     = [ $lien( 'Mentions légales', get_page_by_path( 'mentions-legales' )->ID ), $libre( 'Gestion des cookies', '#gestion-cookies' ), $lien( 'Politique de confidentialité', get_page_by_path( 'politique-de-confidentialite' )->ID ), $lien( 'Plan du site', get_page_by_path( 'plan-du-site' )->ID ) ];
$menus = [ 'Menu principal' => $principal, 'Pied de page' => $pied, 'Informations légales' => $legal ];
$refs  = [];
foreach ( $menus as $titre => $liens ) {
	$existant = get_posts( [ 'post_type' => 'wp_navigation', 'title' => $titre, 'numberposts' => 1, 'post_status' => 'publish' ] );
	$donnees  = [ 'post_type' => 'wp_navigation', 'post_status' => 'publish', 'post_title' => $titre, 'post_content' => implode( "\n", $liens ) ];
	$refs[ $titre ] = $existant ? wp_update_post( [ 'ID' => $existant[0]->ID ] + $donnees ) : wp_insert_post( $donnees );
}
update_option( 'gayrel_menu_pied', $refs['Pied de page'] );
update_option( 'gayrel_menu_legal', $refs['Informations légales'] );

// En-tête et pied enregistrés en base (éditeur de site), à partir des fichiers du thème
$dossier = get_stylesheet_directory() . '/parts/';
$parts   = [
	'header' => [ 'En-tête', str_replace( '<!-- wp:navigation {"overlayMenu":"mobile"', '<!-- wp:navigation {"ref":' . $refs['Menu principal'] . ',"overlayMenu":"mobile"', file_get_contents( $dossier . 'header.html' ) ) ],
	'footer' => [ 'Pied de page', file_get_contents( $dossier . 'footer.html' ) ],
];
foreach ( $parts as $slug => [ $titre, $contenu ] ) {
	$existant = get_posts( [ 'post_type' => 'wp_template_part', 'name' => $slug, 'numberposts' => 1, 'post_status' => 'publish', 'tax_query' => [ [ 'taxonomy' => 'wp_theme', 'field' => 'name', 'terms' => get_stylesheet() ] ] ] );
	if ( $existant ) {
		wp_update_post( [ 'ID' => $existant[0]->ID, 'post_content' => $contenu ] );
		continue;
	}
	$id = wp_insert_post( [ 'post_type' => 'wp_template_part', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $titre, 'post_content' => $contenu ] );
	wp_set_object_terms( $id, get_stylesheet(), 'wp_theme' );
	wp_set_object_terms( $id, $slug, 'wp_template_part_area' );
}
echo "✓ menus, en-tête et pied de page\n";

/* ------------------------------------------------------------------ 7. Établissement, formulaire, redirections */

if ( defined( 'SOCLE_SEO_OPTION' ) ) {
	$etab = (array) get_option( SOCLE_SEO_OPTION, [] );
	update_option( SOCLE_SEO_OPTION, array_merge( $etab, [
		'nom'         => 'Gayrel',
		'nom_court'   => 'Gayrel',
		'type'        => 'HomeAndConstructionBusiness',
		'description' => 'Gayrel conçoit, fabrique et pose des menuiseries et façades aluminium sur mesure depuis 1994, à Gaillac entre Albi et Toulouse. Partenaire agréé Technal, pour les professionnels, les marchés publics et les particuliers.',
		'telephone'   => '+33563814242',
		'email'       => 'contact@gayrel.fr',
		'rue'         => 'ZAC de Roumagnac, 13 avenue de l’Europe',
		'code_postal' => '81600',
		'ville'       => 'Gaillac',
		'pays'        => 'FR',
		'horaires'    => "Mo Tu We Th 07:30-12:00\nMo Tu We Th 13:00-17:30\nFr 07:30-12:00",
		'zone'        => 'Gaillac, Albi, Toulouse, Montauban, Tarn, Haute-Garonne, Tarn-et-Garonne, Occitanie',
		'creation'    => '1994',
		'logo'        => (string) $m['logo'],
		'photos'      => implode( ',', array_filter( [ $m['entreprise'], $m['atelier'], $m['pro'] ] ) ),
		'reseaux'     => 'https://fr.linkedin.com/company/gayrel-sas',
	] ) );
}
if ( defined( 'SOCLE_FORM_OPTION' ) ) {
	$form = (array) get_option( SOCLE_FORM_OPTION, [] );
	update_option( SOCLE_FORM_OPTION, array_merge( $form, [
		'sujets'       => "Demande de devis — Bâtiment / professionnels\nDemande de devis — Habitat / particuliers\nMarché public / appel d’offres\nVisite du showroom\nAutre demande",
		'confirmation' => 'Merci, votre message est bien parti. Notre équipe vous répond dans les plus brefs délais.',
		'delai'        => 'Nous répondons sous 2 jours ouvrés.',
	] ) );
}
// Anciennes adresses du site gayrel.fr (les autres sont identiques : /entreprise/, /realisations/, /contact/, /mentions-legales/)
if ( function_exists( 'socle_redir_regles' ) ) {
	$regles = socle_redir_regles();
	$deja   = array_column( $regles, 'de' );
	foreach ( [ '/services' => '/entreprise/', '/produits' => '/habitat/', '/accueil' => '/' ] as $de => $vers ) {
		if ( ! in_array( $de, $deja, true ) ) {
			$regles[] = [ 'de' => $de, 'vers' => $vers, 'code' => 301, 'visites' => 0, 'derniere' => 0 ];
		}
	}
	socle_redir_enregistrer( $regles );
}
update_option( 'blogdescription', 'Façade, menuiserie, aluminium' );
echo "✓ établissement, formulaire, redirections\n";

if ( function_exists( 'socle_bricks_css' ) ) {
	socle_bricks_css();
}
flush_rewrite_rules( false );
echo "✔ Site Gayrel construit : https://gayrel.localhost\n";
