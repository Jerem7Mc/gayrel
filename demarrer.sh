#!/bin/zsh
# Démarre ce site local : https://gayrel.localhost  (Ctrl+C arrête le serveur PHP de ce site)
# Utilisable depuis n'importe quel dossier.
cd "$(dirname "$0")" || exit 1

# Serveur HTTPS partagé (Caddy) : démarré une seule fois pour tous les sites du socle, rechargé sinon
if curl -fs http://localhost:2019/config/ >/dev/null 2>&1; then
  caddy reload --config "/Users/jerem/.socle-wp/Caddyfile" --adapter caddyfile --address localhost:2019 >/dev/null 2>&1
elif lsof -nP -iTCP:443 -sTCP:LISTEN >/dev/null 2>&1; then
  echo "Le port 443 est occupé par un autre serveur : arrêtez-le, puis relancez ce script."; exit 1
else
  ( cd "/Users/jerem/.socle-wp" && caddy start --config Caddyfile --adapter caddyfile >/dev/null 2>&1 )
fi

if lsof -nP -iTCP:8082 -sTCP:LISTEN >/dev/null 2>&1; then
  launchctl print "gui/$UID/fr.socle-wp.site.${${:-gayrel.localhost}%.localhost}" >/dev/null 2>&1 \
    && echo "Déjà en ligne (démarrage automatique) : https://gayrel.localhost" || echo "Déjà démarré : https://gayrel.localhost"
  exit 0
fi
echo "Site : https://gayrel.localhost — back-office : https://gayrel.localhost/wp-admin (Ctrl+C pour arrêter)"
# Réglages PHP adaptés à WordPress (photos, imports, builders), comme le démarrage automatique (serveurs.sh)
exec "$(brew --prefix)/bin/php" -d upload_max_filesize=64M -d post_max_size=64M -d memory_limit=512M -d max_execution_time=120 \
  -S 127.0.0.1:8082 -t wordpress router.php
