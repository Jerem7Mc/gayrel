/**
 * Gayrel — FAQ (blocs Détails natifs) : ouverture et fermeture animées, une seule réponse ouverte à la fois.
 * Sans JavaScript, les blocs Détails restent utilisables (ouverture instantanée).
 */
( () => {
	const calme = window.matchMedia( '(prefers-reduced-motion: reduce)' );
	const listes = document.querySelectorAll( '.g-faq__liste' );

	const animer = ( details, ouvrir ) => {
		const resume = details.querySelector( 'summary' );
		details.classList.toggle( 'g-ferme', ! ouvrir ); // le chevron tourne dès le clic
		if ( calme.matches ) {
			details.open = ouvrir;
			return;
		}
		details._animation?.cancel();
		const depart = details.offsetHeight;
		if ( ouvrir ) {
			details.open = true;
		}
		const arrivee = ouvrir ? details.offsetHeight : resume.offsetHeight + parseFloat( getComputedStyle( details ).paddingBottom );
		details.style.overflow = 'hidden';
		details._animation = details.animate( { height: [ depart + 'px', arrivee + 'px' ] }, { duration: 350, easing: 'cubic-bezier(.2, .7, .2, 1)' } );
		details._animation.onfinish = () => {
			if ( ! ouvrir ) {
				details.open = false;
			}
			details.style.overflow = '';
			details._animation = null;
		};
		details._animation.oncancel = () => {
			details.style.overflow = '';
		};
	};

	listes.forEach( ( liste ) => {
		const tous = [ ...liste.querySelectorAll( 'details' ) ];
		tous.forEach( ( details ) => {
			details._ouverture = details.open; // état visé (l'attribut open ne change qu'en fin d'animation de fermeture)
			details.querySelector( 'summary' ).addEventListener( 'click', ( e ) => {
				e.preventDefault();
				const ouvrir = ! details._ouverture;
				details._ouverture = ouvrir;
				if ( ouvrir ) {
					tous.filter( ( d ) => d !== details && d._ouverture ).forEach( ( d ) => {
						d._ouverture = false;
						animer( d, false );
					} );
				}
				animer( details, ouvrir );
			} );
		} );
	} );
} )();
