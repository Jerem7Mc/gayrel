# Gayrel — contexte et décisions

## 8 octobre 2026 — démarrage

- Client réel : GAYREL SAS, ZAC de Roumagnac, 13 avenue de l'Europe, 81600 Gaillac — 05 63 81 42 42 —
  contact@gayrel.fr — SIRET 393 889 530 00027 — TVA FR52393889530. Menuiserie et façades aluminium depuis 1994,
  partenaire agréé Technal, surtout B2B / marchés publics. LinkedIn : fr.linkedin.com/company/gayrel-sas.
- Maquette Figma fournie : **seule la page d'accueil** (1920 px). Décision de l'utilisateur : **dériver les pages
  manquantes** (entreprise, habitat, bâtiment, showroom, actualités, réalisations, contact, devis, mobile).
- Builder : **Gutenberg** (thème bloc du socle personnalisé dans le projet, pas de thème enfant : le thème du socle
  charge ses CSS par `get_stylesheet_directory`).
- Contenus repris du site actuel gayrel.fr (textes entreprise, services, produits, 12 réalisations avec fiches et
  photos, mentions légales). Le site actuel bloque les robots (o2switch « badbots ») : textes lus avec le navigateur
  intégré, photos téléchargées avec un agent de navigateur, avec l'accord de l'utilisateur.
- Nouvelle navigation (maquette) : L'entreprise, Habitat, Bâtiment, Showroom, Actualités + Devis, téléphone,
  LinkedIn, Nous contacter. Anciennes adresses : /entreprise/, /realisations/, /contact/, /mentions-legales/
  conservées ; /services/ → /entreprise/, /produits/ → /habitat/ (redirections 301).
- Extension **socle-realisations** créée et versée au socle (option `nouveau-projet.sh --realisations`).
- FAQ en blocs Détails natifs : socle-seo produit désormais le JSON-LD FAQPage à partir de ces blocs.
- Agence : Agoravita (signature « by agoravita » du pied de page, maquette).

## 8-9 octobre 2026 — recettes successives (fidélité à la maquette)

Retours de l'utilisateur traités, et reportés dans la méthode du socle (`socle-wp/docs/maquette-figma.md`,
« Points de contrôle ») :
- En-tête : pastilles à la hauteur du logo (62 px), états Hover/Pressed des composants, zone de droite complète.
- Boutons : variantes de fond, flèche poussée par une flèche identique (0,9 s), couleurs de flèche lues dans Figma.
- Accueil : animation d'entrée en trois temps (bâtiment seul, GAYREL qui monte de derrière, puis en-tête et bas
  ensemble) ; cartes d'accès au fond rayé exact (vecteurs Figma) ; GAYREL qui glisse sous le bâtiment au défilement.
- Réalisations (accueil) : titres collés puis **poussés hors du cadre** (rien ne s'efface), pause de la dernière carte,
  le bouton la pousse, marge droite de la maquette.
- Pages intérieures : en-tête photo de hauteur fixe avec grand mot « découpé » dans la couleur du fond, 404 dans la DA,
  plan du site ordonné.
- Accessibilité (versée au socle) : repères `role`, liens d'évitement, bandeau cookies premier au clavier et rouvrable,
  focus visibles, fil d'Ariane sans lien sur la page courante, liens de texte animés, liens externes en nouvel onglet.
- Mobile : logo à gauche, pictos téléphone / contact (enveloppe) / menu regroupés à droite, menu animé à l'ouverture
  et à la fermeture (`assets/js/menu.js`) avec Devis, téléphone et LinkedIn, aucune animation au toucher, pastilles
  « Voir le projet » / « Lire l'article » dans le coin des photos.
- Plan d'accès (Contact, Showroom) : carte Google Maps conservée (au clic), façade sur image OpenStreetMap locale
  teintée (`maquette/carte/plan-gayrel.jpg`, outil `socle-wp/outils/carte-statique.php`).
- Arbitrage : adresse et téléphone retirés du pied de page à la demande de l'utilisateur → Opquast 105 signalé par la
  vérification, accepté.
- Cookies et confidentialité (9 octobre, depuis le socle) : panneau « Gestion des cookies » qui présente les cookies
  nécessaires puis Google Maps (soumis à accord) ; politique de confidentialité générée par `[socle_donnees]`
  (formulaire de contact et de demande de devis, cookies, services) et droits détaillés (portabilité, retrait du
  consentement, réponse sous un mois). Débordement des grands mots à 320 px corrigé ; contraste du survol du menu
  vérifié (5,14:1 au repos, 11,81:1 au survol). Vérification : tout conforme sauf Opquast 105 (arbitrage ci-dessus).
