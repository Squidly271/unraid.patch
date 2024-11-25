#!/usr/bin/php
<?
echo "Installing Patches\n";

passthru("/usr/local/emhttp/plugins/unraid.patch/scripts/patch.php install");
?>