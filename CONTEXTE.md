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
