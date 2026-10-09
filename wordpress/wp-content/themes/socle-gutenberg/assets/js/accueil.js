/**
 * Gayrel — accueil.
 * 1. Visuel d'entrée : le mot GAYREL, bien visible au départ, glisse sous le bâtiment au défilement
 *    (variable --g-defile de 0 à 1, utilisée en CSS ; rien si « réduire les animations »).
 * 1 bis. Animation d'entrée : rapports d'agrandissement du cadre (--g-sx, --g-sy).
 * 2. Réalisations empilées : le titre « Nos réalisations » reste collé pendant l'empilement et s'efface
 *    à l'apparition de la dernière réalisation (quand elle atteint les deux tiers de l'écran).
 */
( () => {
	const calme = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	const hero = document.querySelector( '.g-hero' );
	const section = document.querySelector( '.g-projets' );
	const titre = section?.querySelector( 'h2' );
	const cartes = section?.querySelectorAll( '.wp-block-post' );
	const derniere = cartes?.length ? cartes[ cartes.length - 1 ] : null;
	let attente = false;

	// Entrée : la photo part du plein écran (rapport vertical exact, voir @keyframes g-cadre)
	if ( hero ) {
		const marge = parseFloat( getComputedStyle( hero ).marginTop ) || 0;
		hero.style.setProperty( '--g-sx', ( ( hero.offsetWidth + 2 * marge ) / ( hero.offsetWidth || 1 ) ).toFixed( 4 ) );
		hero.style.setProperty( '--g-sy', ( ( hero.offsetHeight + 2 * marge ) / ( hero.offsetHeight || 1 ) ).toFixed( 4 ) );
	}

	const maj = () => {
		attente = false;
		if ( hero && ! calme ) {
			const h = hero.offsetHeight || 1;
			hero.style.setProperty( '--g-defile', Math.min( 1, Math.max( 0, scrollY / ( h * 0.6 ) ) ).toFixed( 3 ) );
		}
		if ( titre && derniere ) {
			section.classList.toggle( 'g-projets--fin', derniere.getBoundingClientRect().top < innerHeight * 0.66 );
		}
	};
	addEventListener( 'scroll', () => {
		if ( ! attente ) {
			attente = true;
			requestAnimationFrame( maj );
		}
	}, { passive: true } );
	maj();
} )();
