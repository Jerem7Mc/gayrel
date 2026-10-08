<?php
/**
 * Title: Pied de page Gayrel
 * Slug: socle-gutenberg/pied-gayrel
 * Categories: footer
 * Block Types: core/template-part/footer
 * Inserter: no
 *
 * Bandeau bleu de la maquette : logo blanc, menu du pied, liens légaux à gauche, signature de l'agence à droite.
 * Les références des menus sont posées à l'installation (integration/construire.php).
 */
$menu_pied   = (int) get_option( 'gayrel_menu_pied' );
$menu_legal  = (int) get_option( 'gayrel_menu_legal' );
$theme       = get_stylesheet_directory_uri();
?>
<!-- wp:group {"className":"g-pied","layout":{"type":"default"}} -->
<div class="wp-block-group g-pied"><!-- wp:group {"className":"g-pied__marque","layout":{"type":"default"}} -->
<div class="wp-block-group g-pied__marque"><!-- wp:html -->
<?php
// Logo animé (« Animation footer » de la maquette) : les sept facettes du cube s'assemblent à l'arrivée du pied de page.
// Coordonnées Figma (groupe de 729 × 248,74 px) converties en pourcentages ; le logotype est le logo blanc recadré.
$l = 729; $h = 248.74;
$faces = [ [ 114.67, 0, 112.948, 178.475 ], [ 0, 0, 114.672, 178.475 ], [ 0, 108.64, 227.62, 140.107 ], [ 53.46, 104.33, 61.216, 75.873 ], [ 114.67, 78.03, 104.757, 161.662 ], [ 8.62, 76.74, 106.05, 162.955 ], [ 8.62, 12.07, 199.168, 80.345 ] ];
$pc = fn( $v, $t ) => round( $v / $t * 100, 3 ) . '%';
?>
<a class="g-pied__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="Gayrel, façade, menuiserie, aluminium — retour à l’accueil"><span class="g-pied__cube" aria-hidden="true"><?php foreach ( $faces as $i => [ $x, $y, $w, $hh ] ) : ?><img class="g-face g-face--<?php echo $i + 1; ?>" src="<?php echo esc_url( $theme . '/assets/icones/cube/face-' . ( $i + 1 ) . '.svg' ); ?>" alt="" width="<?php echo (int) round( $w ); ?>" height="<?php echo (int) round( $hh ); ?>" style="left:<?php echo $pc( $x, $l ); ?>;top:<?php echo $pc( $y, $h ); ?>;width:<?php echo $pc( $w, $l ); ?>;height:<?php echo $pc( $hh, $h ); ?>"><?php endforeach; ?></span><span class="g-pied__mot" aria-hidden="true" style="left:<?php echo $pc( 255.76, $l ); ?>;top:<?php echo $pc( 33.12, $h ); ?>;width:<?php echo $pc( 473.24, $l ); ?>;height:<?php echo $pc( 182.29, $h ); ?>"><img src="<?php echo esc_url( $theme . '/assets/images/logo-gayrel-blanc-760.png' ); ?>" srcset="<?php echo esc_url( $theme . '/assets/images/logo-gayrel-blanc-400.png' ); ?> 400w, <?php echo esc_url( $theme . '/assets/images/logo-gayrel-blanc-760.png' ); ?> 760w" sizes="(min-width: 1920px) 667px, max(220px, 35vw)" alt="" width="760" height="207" loading="lazy" decoding="async"></span></a>
<!-- /wp:html --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"g-pied__nav","layout":{"type":"default"}} -->
<div class="wp-block-group g-pied__nav"><!-- wp:navigation {"ref":<?php echo $menu_pied; ?>,"overlayMenu":"never","layout":{"type":"flex","orientation":"vertical"},"ariaLabel":"Pied de page"} /--></div>
<!-- /wp:group -->

<!-- wp:group {"className":"g-pied__bas","layout":{"type":"default"}} -->
<div class="wp-block-group g-pied__bas"><!-- wp:navigation {"ref":<?php echo $menu_legal; ?>,"overlayMenu":"never","layout":{"type":"flex","flexWrap":"wrap"},"ariaLabel":"Informations légales"} /-->

<!-- wp:paragraph {"className":"g-pied__signature"} -->
<p class="g-pied__signature"><a href="https://www.agoravita.com/" rel="noopener">by <img src="<?php echo esc_url( $theme . '/assets/icones/agoravita.svg' ); ?>" alt="Agoravita" width="84" height="16"></a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
