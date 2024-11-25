<?
file_put_contents("/tmp/blah",print_r($_POST,true));
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
  touch("/boot/config/plugins/unraid.patch/accepted");
  echo "ok";
}

function check() {
  exec("/usr/local/emhttp/plugins/unraid.patch/scripts/patch.php check");
  echo "blah"; // need to check against what's already installed
}

function install() {
  exec("/usr/local/emhttp/plugins/unraid.patch/scripts/patch.php install");
  echo "installed";
}
?>

