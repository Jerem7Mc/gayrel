/**
 * Gayrel — fermeture animée du menu mobile.
 * Le bloc Navigation ferme le panneau d'un coup (classe is-menu-open retirée) : on intercepte la fermeture
 * (bouton, touche Échap), on joue l'animation de sortie (classe g-menu-sortie, voir projet.css), puis on laisse
 * le bloc fermer réellement. Rien si « réduire les animations ».
 */
( () => {
	if ( window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
		return;
	}
	const DUREE = 380;
	let enCours = false;
	const fermer = ( panneau, action ) => {
		enCours = true;
		panneau.classList.add( 'g-menu-sortie' );
		setTimeout( () => {
			action();
			panneau.classList.remove( 'g-menu-sortie' );
			enCours = false;
		}, DUREE );
	};
	document.addEventListener( 'click', ( e ) => {
		const bouton = e.target.closest( '.g-entete .wp-block-navigation__responsive-container-close' );
		const panneau = bouton?.closest( '.wp-block-navigation__responsive-container.is-menu-open' );
		if ( ! panneau || enCours ) {
			return;
		}
		e.preventDefault();
		e.stopPropagation();
		fermer( panneau, () => {
			enCours = true; // laisse passer ce clic-là jusqu'au bloc
			bouton.click();
		} );
	}, true );
	document.addEventListener( 'keydown', ( e ) => {
		const panneau = document.querySelector( '.g-entete .wp-block-navigation__responsive-container.is-menu-open' );
		if ( 'Escape' !== e.key || ! panneau || enCours ) {
			return;
		}
		e.preventDefault();
		e.stopPropagation();
		fermer( panneau, () => panneau.querySelector( '.wp-block-navigation__responsive-container-close' )?.click() );
	}, true );
} )();
