<?php
/**
 * Title: Pied de page Gayrel
 * Slug: socle-gutenberg/pied-gayrel
 * Categories: footer
 * Block Types: core/template-part/footer
 * Inserter: no
 *
 * Bandeau bleu de la maquette : logo blanc, menu du pied, liens légaux, coordonnées, signature de l'agence.
 * Les références des menus sont posées à l'installation (integration/construire.php).
 */
$menu_pied   = (int) get_option( 'gayrel_menu_pied' );
$menu_legal  = (int) get_option( 'gayrel_menu_legal' );
$theme       = get_stylesheet_directory_uri();
?>
<!-- wp:group {"className":"g-pied","layout":{"type":"default"}} -->
<div class="wp-block-group g-pied"><!-- wp:group {"className":"g-pied__marque","layout":{"type":"default"}} -->
<div class="wp-block-group g-pied__marque"><!-- wp:html -->
<a class="g-pied__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>"><img src="<?php echo esc_url( $theme . '/assets/images/logo-gayrel-blanc-760.png' ); ?>" srcset="<?php echo esc_url( $theme . '/assets/images/logo-gayrel-blanc-760.png' ); ?> 760w, <?php echo esc_url( $theme . '/assets/images/logo-gayrel-blanc.png' ); ?> 1333w" sizes="(min-width: 1900px) 729px, 38vw" width="760" height="207" loading="lazy" decoding="async" alt="Gayrel, façade, menuiserie, aluminium — retour à l’accueil"></a>
<!-- /wp:html --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"g-pied__nav","layout":{"type":"default"}} -->
<div class="wp-block-group g-pied__nav"><!-- wp:navigation {"ref":<?php echo $menu_pied; ?>,"overlayMenu":"never","layout":{"type":"flex","orientation":"vertical"},"ariaLabel":"Pied de page"} /--></div>
<!-- /wp:group -->

<!-- wp:group {"className":"g-pied__bas","layout":{"type":"default"}} -->
<div class="wp-block-group g-pied__bas"><!-- wp:navigation {"ref":<?php echo $menu_legal; ?>,"overlayMenu":"never","layout":{"type":"flex","flexWrap":"wrap"},"ariaLabel":"Informations légales"} /-->

<!-- wp:html -->
<?php echo do_shortcode( '[socle_coordonnees]' ); // dans une composition, le bloc Code court n'est pas interprété ?>
<!-- /wp:html -->

<!-- wp:paragraph {"className":"g-pied__signature"} -->
<p class="g-pied__signature"><a href="https://www.agoravita.com/" rel="noopener">by <img src="<?php echo esc_url( $theme . '/assets/icones/agoravita.svg' ); ?>" alt="Agoravita" width="84" height="16"></a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
