<?
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: "/usr/local/emhttp";

require_once "$docroot/plugins/unraid.patch/include/paths.php";
require_once "$docroot/plugins/dynamix/include/Wrappers.php";
require_once "$docroot/webGui/include/MarkdownExtra.inc.php";

require_once "$docroot/webGui/include/Markdown.php";

$unraidVersion = parse_ini_file($paths['version']);

switch ($_POST['action']) {
  case "accepted": 
    accepted();
    break;
  case "check":
    check();
    break;
  case "install":
    install();
    break;
}

function accepted() {
  touch($paths['accepted']);
  echo "ok";
}

function check() {
  global $paths,$unraidVersion;

  exec("/usr/local/emhttp/plugins/unraid.patch/scripts/patch.php check");
  $installedUpdates = readJsonFile($paths['installedUpdates']);
  $availableUpdates = readJsonFile($paths['flash'].$unraidVersion['version']."/patches.json");

  $updatesAvailable = false;
  foreach ($availableUpdates['patches'] as $update) {
    if ( ! $installedUpdates[basename($update['url'])] ?? false ) {
      $updatesAvailable = true;
      break;
    }
  }
  if ( ! $updatesAvailable ) {
    echo "none";
  } else {
    echo markdown($availableUpdates['changelog']);
  }
}

function install() {
  exec("/usr/local/emhttp/plugins/unraid.patch/scripts/patch.php install");
  echo "installed";
}

function readJsonFile($filename) {
  $json = json_decode(@file_get_contents($filename),true);

  return is_array($json) ? $json : array();
}
?>

