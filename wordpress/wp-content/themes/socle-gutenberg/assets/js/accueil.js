/**
 * Gayrel — accueil.
 * 1. Visuel d'entrée : le mot GAYREL, bien visible au départ, glisse sous le bâtiment au défilement
 *    (variable --g-defile de 0 à 1, utilisée en CSS ; rien si « réduire les animations »).
 * 1 bis. Animation d'entrée : rapports d'agrandissement du cadre (--g-sx, --g-sy).
 * 2. Réalisations empilées (titres et cartes collants en CSS) : rien ne s'efface, tout sort du cadre.
 *    - le grand titre est poussé vers le haut pendant que la dernière carte monte vers sa place ;
 *    - le titre « Nos Réalisations » est poussé par la carte la plus haute quand elle le rejoint
 *      (la dernière, que le bouton pousse par-dessus les autres).
 */
( () => {
	const calme = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	const hero = document.querySelector( '.g-hero' );
	const section = document.querySelector( '.g-projets' );
	const titre = section?.querySelector( 'h2' );
	const geant = section?.querySelector( '.g-projets__geant' );
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
		if ( titre && derniere && 'sticky' === getComputedStyle( titre ).position ) {
			// lectures d'abord, écritures ensuite (pas de recalcul forcé)
			const haut = derniere.getBoundingClientRect().top;
			const place = parseFloat( getComputedStyle( derniere ).top ) || 0;
			const plusHaute = Math.min( ...[ ...cartes ].map( ( c ) => c.getBoundingClientRect().top ) );
			const decalage = parseFloat( titre.style.translate.split( ' ' )[ 1 ] ) || 0;
			const basTitre = titre.getBoundingClientRect().bottom - decalage;
			if ( geant ) {
				const p = Math.min( 1, Math.max( 0, ( innerHeight - haut ) / Math.max( 1, innerHeight - place ) ) );
				const sortie = geant.offsetHeight + ( parseFloat( getComputedStyle( geant ).top ) || 0 );
				geant.style.translate = `0 ${ ( -p * sortie ).toFixed( 1 ) }px`;
			}
			titre.style.translate = `0 ${ Math.min( 0, plusHaute - basTitre ).toFixed( 1 ) }px`;
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
