/**
 * Gayrel — accueil, réalisations empilées : le titre « Nos réalisations » reste collé pendant l'empilement
 * et s'efface à l'apparition de la dernière réalisation (quand elle atteint les deux tiers de l'écran).
 */
( () => {
	const section = document.querySelector( '.g-projets' );
	const titre = section?.querySelector( 'h2' );
	const cartes = section?.querySelectorAll( '.wp-block-post' );
	if ( ! titre || ! cartes?.length ) {
		return;
	}
	const derniere = cartes[ cartes.length - 1 ];
	let attente = false;
	const maj = () => {
		attente = false;
		section.classList.toggle( 'g-projets--fin', derniere.getBoundingClientRect().top < innerHeight * 0.66 );
	};
	addEventListener( 'scroll', () => {
		if ( ! attente ) {
			attente = true;
			requestAnimationFrame( maj );
		}
	}, { passive: true } );
	maj();
} )();
