#!/usr/bin/php
<?
function download_url($url, $path = "", $bg = false, $timeout = 45) {
  $vars = parse_ini_file("/var/local/emhttp/var.ini");
  $keyfile = empty($vars['regFILE']) ? false : @base64_encode(@file_get_contents($vars['regFILE']??""));

  $ch = curl_init();
  curl_setopt($ch,CURLOPT_URL,$url);
  curl_setopt($ch,CURLOPT_FRESH_CONNECT,true);
  curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
  curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,$timeout);
  curl_setopt($ch,CURLOPT_TIMEOUT,$timeout);
  curl_setopt($ch,CURLOPT_ENCODING,"");
  curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
  curl_setopt($ch,CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch,CURLOPT_FAILONERROR,true);
  curl_setopt($ch,CURLOPT_HTTPHEADER,["X-Unraid-Keyfile:$keyfile"]);

  if ( !getenv("http_proxy") && is_file("/boot/config/plugins/community.applications/proxy.cfg") ) {
    $proxyCFG = parse_ini_file("/boot/config/plugins/community.applications/proxy.cfg");
    curl_setopt($ch, CURLOPT_PROXYPORT,intval($proxyCFG['port']));
    curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL,intval($proxyCFG['tunnel']));
    curl_setopt($ch, CURLOPT_PROXY,$proxyCFG['proxy']);
  }
  $out = curl_exec($ch);
  if ( curl_errno($ch) == 23 ) {
    curl_setopt($ch,CURLOPT_ENCODING,null);
    $out = curl_exec($ch);
  }
  curl_close($ch);
  if ( $path )
    file_put_contents($path,$out);

  return $out ?: false;
}
function download_json($url,$path="",$bg=false,$timeout=45) {
  return json_decode(download_url($url,$path,$bg,$timeout),true);
}
function writeJsonFile($filename,$jsonArray) {
  return file_put_contents($filename,json_encode($jsonArray, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}
function readJsonFile($filename) {
  $json = json_decode(@file_get_contents($filename),true);

  return is_array($json) ? $json : array();
}
function getPost($setting,$default="") {
  return isset($_POST[$setting]) ? urldecode(($_POST[$setting])) : $default;
}
function logger($msg) {
  echo $msg;
  exec("logger ".escapeshellarg($msg));
}
###MAIN

$paths['tmp'] = "/tmp/unraid.patch";
$paths['installedUpdates'] = "{$paths['tmp']}/installedUpdates.json";
$paths['flash'] = "/boot/config/plugins/unraid.patch/";
$paths['version'] = "/etc/unraid-version";
$paths['github'] = "https://releases.unraid.net/dl/stable";
$paths['accepted'] = "/boot/config/plugins/unraid.patch/accepted";

@mkdir($paths['tmp']);
$unraidVersion = parse_ini_file($paths['version']);

$action = $argv[1] ?? "";
$option = $argv[2] ?? "";


switch($action) {
  case "install":
    install();
    break;
  case "check":
    check();
    break;
  default:
    break;
}

function install() {
  global $paths, $unraidVersion;

  if ( ! file_exists($paths['accepted'] ) {
    logger("Installation of Unraid patches not accepted.  You must go to Tools - Unraid Patch and accept the disclaimer");
    exit();
  }
  $installedUpdates = readJsonFile($paths['installedUpdates']);

  $installDir = $paths['flash'].$unraidVersion['version]'];
  if ( ! is_dir($installDir) ) {
    logger("Installation directory not found.  Aborting patch installation\n");
    exit(1);
  }

  $updates = readJsonFile("{$paths['flash']}/{$unraidVersion['version']}/patches.json");
  if ( ! is_array($updates['patches']) ) {
    logger("Could not read updates.json.  Aborting");
    exit(1);
  }
  // install each update in order
  foreach($updates['patches'] as $script) {
    $filename = "{$paths['flash']}/{$unraidVersion['version']}/".basename($script['url']);
    if ( $installedUpdates[basename($script['url'])] ?? false ) {
      logger("Skipping $filename... Already Installed\n");
      continue;
    }

    logger("Installing $filename...\n\n");
    if ( md5_file($filename) !== $script['md5'] ) {
      logger("MD5 verification failed.  Aborting\n");
      exit(1);
    }
    passthru("/usr/bin/patch -d /usr/local/ -p1 -i ".escapeshellarg($filename),$exitCode);
    if ( ! $exitCode ) {
      $installedUpdates[basename($script['url'])] = true;
    } else {
      logger("Failed to install update ".basename($script['url'])."   Aborting\n");
      exit(1);
    }

  }
  writeJsonFile($paths['installedUpdates'],$installedUpdates);
}

function check() {
  global $option, $paths, $unraidVersion;

  $option = $option ?: $unraidVersion['version'];
  $patchesAvailable = $paths['github']."/$option/patch/patches.json";
  logger("Checking for updates $patchesAvailable\n");
  $updates = download_json($patchesAvailable);
  if (! $updates || empty($updates) )
    return;

  $downloadFailed = false;
  $updatesAvailable = false;
  $installedUpdates = readJsonFile($paths['installedUpdates']);
  $newPath = "{$paths['flash']}/$option/";
  exec("mkdir -p ".escapeshellarg($newPath));
  foreach ($updates['patches'] as $patches) {
    if ( isset($installedUpdates[basename($patches['url'])]) ) {
      logger("Skipping {$patches['url']} -- Already installed\n");
      continue;
    }
    logger("Downloading {$patches['url']}...");

    download_url($patches['url'],"$newPath/".basename($patches['url']));
    if (md5_file("$newPath/".basename($patches['url'])) !== $patches['md5']) {

      logger("MD5 verification failed!");
      $downloadFailed = true;
      @unlink("$newpath/".basename($patches['file']));
      break;
    }
  }
  if ( $downloadFailed ) {
    logger("\n\Downloads aborted.");
    // only delete files that haven't already been installed
    $alreadyInstalled = glob("{$paths['flash']}/$option/");
    foreach ( $alreadyInstalled as $file) {
      if ( !isset($installedUpdates[basename($file)]) ) {
        @unlink($file);
      }
    }
  } else {
    writeJsonFile("{$paths['flash']}/$option/patches.json",$updates);
  }
}

