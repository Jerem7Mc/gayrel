<?php
// Routeur du serveur PHP intégré : fichiers statiques servis tels quels, le reste à WordPress.
$root = __DIR__ . '/wordpress';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = $root . $path;
// Jamais servis : journaux, exports, configuration, fichiers cachés (.git, .env…), XML-RPC, fichiers qui révèlent la version,
// manifestes de dépendances, et aucun PHP exécuté depuis la médiathèque (fichier déposé par une faille d'envoi)
if (preg_match('#(^|/)\.|\.(log|sql|gz|zip|bak|ini|sh|md)$|/wp-config(-sample)?\.php$|/xmlrpc\.php$|^/(readme\.html|license\.txt|licence\.txt)$|/(composer|package)(-lock)?\.(json|lock)$|^/wp-content/uploads/.*\.(php\d?|phtml|phar)$#i', $path)) {
  http_response_code(404); header('Content-Type: text/plain; charset=utf-8'); echo "Introuvable\n"; return true;
}
header_remove('X-Powered-By');
if ($path !== '/' && is_file($file)) {
  if (substr($file, -4) === '.php') { chdir(dirname($file)); require $file; return true; }
  return false;
}
if (is_dir($file) && is_file($file . '/index.php')) { chdir($file); require $file . '/index.php'; return true; }
chdir($root);
require $root . '/index.php';
