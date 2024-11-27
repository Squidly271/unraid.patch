<?
/* Copyright 2024, Lime Technology
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 */

$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: "/usr/local/emhttp";

require_once "$docroot/plugins/unraid.patch/include/paths.php";
require_once "$docroot/plugins/dynamix/include/Wrappers.php";
if (is_file("$docroot/webGui/include/MarkdownExtra.inc.php") ) {
  require_once "$docroot/webGui/include/MarkdownExtra.inc.php";
}
require_once "$docroot/webGui/include/Markdown.php";

$unraidVersion = parse_ini_file($paths['version']);

$_POST['action'] = $_POST['action'] ?? $argv[1];

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
  case "currentchangelog":
    currentchangelog();
    break;
}

function accepted() {
  global $paths;

  touch($paths['accepted']);
  echo "ok";
}

function check() {
  global $paths,$unraidVersion;

  exec("/usr/local/emhttp/plugins/unraid.patch/scripts/patch.php check");
  $installedUpdates = readJsonFile($paths['installedUpdates']);
  $availableUpdates = readJsonFile($paths['flash'].$unraidVersion['version']."/patches.json");

  $updatesAvailable = false;
  foreach ($availableUpdates['patches'] ?? [] as $update) {
    if ( ! ($installedUpdates[basename($update['url'])] ?? false) ) {
      $updatesAvailable = true;
      break;
    }
  }
  if ( ! $updatesAvailable ) {
    foreach ($availableUpdates['scripts'] ?? [] as $update) {
      if ( ! ($installedUpdates[basename($update['url'])] ?? false) ) {
        $updatesAvailable = true;
        break;
      }
    }
  }
  if ( ! $updatesAvailable ) {
    foreach ($availableUpdates['prescripts'] ?? [] as $update) {
      if ( ! ($installedUpdates[basename($update['url'])] ?? false) ) {
        $updatesAvailable = true;
        break;
      }
    }
  }
  if ( ! $updatesAvailable ) {
    echo "none";
  } else {
    $msg = version_compare($unraidVersion['version'],$availableUpdates['unraidVersion'],"!=") ? "  * MISMATCH" : "";
    echo markdown("#Unraid Version: {$availableUpdates['unraidVersion']}$msg\n\n{$availableUpdates['changelog']}");
  }
}

function install() {
  exec("/usr/local/emhttp/plugins/unraid.patch/scripts/patch.php install");
  echo "installed";
}

function currentchangelog() {
  global $paths, $unraidVersion;

  $current = readJsonFile($paths['flash'].$unraidVersion['version']."/patches.json");
  if ( ! ($current['unraidVersion'] ?? false) ) {
    echo "none";
    return;
  }
  $msg = version_compare($unraidVersion['version'],$current['unraidVersion'],"!=") ? "  * MISMATCH" : "";
  echo markdown("#Unraid Version: {$current['unraidVersion']}$msg\n\n{$current['changelog']}");
}

function readJsonFile($filename) {
  $json = json_decode(@file_get_contents($filename),true);

  return is_array($json) ? $json : array();
}
?>

