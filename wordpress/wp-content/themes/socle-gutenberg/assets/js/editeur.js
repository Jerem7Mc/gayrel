/**
 * Gayrel — éditeur : panneau « En-tête de la page » (barre latérale, onglet Page).
 * Rappelle d'où viennent la photo et l'introduction du bandeau, et règle le grand mot décoratif (méta _gayrel_mot).
 * Sans compilation : wp.element.createElement.
 */
( ( wp ) => {
	const { registerPlugin } = wp.plugins;
	const { PluginDocumentSettingPanel } = wp.editor || wp.editPost;
	const { TextControl } = wp.components;
	const { useSelect, useDispatch } = wp.data;
	const el = wp.element.createElement;

	const Panneau = () => {
		const type = useSelect( ( s ) => s( 'core/editor' ).getCurrentPostType(), [] );
		const mot = useSelect( ( s ) => ( s( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {} )._gayrel_mot || '', [] );
		const { editPost } = useDispatch( 'core/editor' );
		if ( 'page' !== type ) {
			return null;
		}
		return el( PluginDocumentSettingPanel, { name: 'gayrel-entete', title: 'En-tête de la page' },
			el( 'p', null, 'Photo du bandeau : « Image mise en avant ». Texte sous le titre : « Extrait ».' ),
			el( TextControl, {
				label: 'Grand mot décoratif',
				help: 'Mot géant en bas du bandeau (ex. HABITAT). Laisser vide pour aucun. Masqué aux lecteurs d’écran.',
				value: mot,
				maxLength: 14,
				onChange: ( v ) => editPost( { meta: { _gayrel_mot: v } } ),
				__nextHasNoMarginBottom: true,
			} )
		);
	};
	registerPlugin( 'gayrel-entete', { render: Panneau } );

	// Aperçu fidèle : le grand mot (ajouté au rendu du site par inc/gayrel.php) apparaît aussi dans l'éditeur
	const afficherMot = () => {
		const ed = wp.data.select( 'core/editor' );
		if ( ! ed || 'page' !== ed.getCurrentPostType() ) {
			return;
		}
		const cadre = document.querySelector( 'iframe[name="editor-canvas"]' );
		const doc = cadre ? cadre.contentDocument : document;
		const entete = doc && doc.querySelector( '.g-page-entete' );
		if ( ! entete ) {
			return;
		}
		const mot = ( ( ed.getEditedPostAttribute( 'meta' ) || {} )._gayrel_mot || '' ).trim();
		let p = entete.querySelector( ':scope > .g-geant' );
		if ( ! mot ) {
			p?.remove();
			return;
		}
		if ( ! p ) {
			p = doc.createElement( 'p' );
			p.className = 'g-geant';
			p.setAttribute( 'aria-hidden', 'true' );
			p.contentEditable = 'false';
			entete.appendChild( p );
		}
		if ( p.textContent !== mot ) {
			p.textContent = mot;
			p.style.setProperty( '--g-lettres', mot.length );
		}
	};
	wp.data.subscribe( afficherMot );
	setInterval( afficherMot, 1500 ); // le cadre de l'éditeur peut être recréé (changement d'appareil d'aperçu)
} )( window.wp );
