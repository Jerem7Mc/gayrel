# Gayrel — instructions projet

Refonte de gayrel.fr (menuiserie et façades aluminium, Gaillac) en WordPress + **Gutenberg**, construite sur le
**socle WordPress** (`~/Documents/Projects/socle-wp`). Vrai client. Maquette : Figma « Gayrel » (page Home seulement,
1920 px) — https://www.figma.com/design/93P32qqGyYXcjjiOaD84nH/Gayrel. Historique et décisions : **CONTEXTE.md** ;
backlog : **A-FAIRE.md**.

## Règles permanentes

- Tout en **français** (contenus, back-office, code, commits).
- **Texte ≥ 16 px**, **zones de clic ≥ 32 × 32 px**, contrastes RGAA calculés, un seul H1, `alt` pertinents.
- Les grands titres décoratifs de la maquette (« NOS SERVICES », « FAQ », « GAYREL »…) sont **aria-hidden** et doublés
  d'un vrai titre : leur faible contraste est voulu et sans incidence RGAA.
- RGPD : polices auto-hébergées (Manrope), aucun service tiers sans consentement.
- SEO / GEO / performance au cœur ; référentiel : `socle-wp/docs/checklist-bonnes-pratiques.md`.
- Pas de polices « IA » ; toujours les dernières versions stables.

## Lancer et reconstruire

- Site : https://gayrel.localhost (démarrage automatique du socle) — accès : `ACCES-LOCAL.txt`.
- `php integration/construire.php [--remplacer]` : médias, 12 réalisations, pages, actualités, menus, fiche
  Établissement, formulaire, redirections. Sans `--remplacer`, ne réécrit pas ce que le client a pu modifier.
- Sauvegarde avant tout script risqué : `~/Documents/Projects/socle-wp/sauvegarder.sh gayrel`.
- Vérification : `~/Documents/Projects/socle-wp/verifier.sh --site gayrel`.

## Arborescence

| Chemin | Rôle |
|---|---|
| `maquette/figma/` | Images et SVG exportés de Figma (par section) |
| `maquette/site-actuel/images/` | Photos du site actuel gayrel.fr (réalisations, atelier, labels) |
| `integration/construire.php` | Construction complète des contenus |
| `wordpress/wp-content/themes/socle-gutenberg/` | Thème du projet : `theme.json` (palette Gayrel, Manrope), `assets/css/projet.css` (toutes les sections), `parts/`, `templates/`, `patterns/` (pied de page, appel), `inc/gayrel.php` |

Extensions du socle (source dans `socle-wp/extensions`, déployées par `rsync -a`) : base, seo, rgpd, formulaire,
redirections, mesure, **realisations** (portfolio, créée pour ce projet et versée au socle).

## Composants CSS (préfixe `g-`)

`g-hero` (visuel d'accueil 3 calques), `g-chiffres`, `g-services`/`g-service--pro|--part`, `g-projets` (cartes
empilées), `g-duo` (2 colonnes séparées par des traits : entreprise, partenaire, FAQ), `g-cta` (bouton contour + flèche,
`--clair` sur fond sombre), `g-contact`, `g-page-entete` (`--image`), `g-section`, `g-bloc` (texte + photo,
`--inverse`), `g-cartes` (`--sombre`, `--bleu`), `g-grille` (archives), `g-fiche`, `g-appel`, `g-pied`, `g-geant`
(titre décoratif), `g-lecteur` (texte réservé aux lecteurs d'écran).
