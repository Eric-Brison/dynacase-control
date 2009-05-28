<?php
include_once('../test.php');

$wiff = WIFF::getInstance();
$ctx = $wiff->getContext('ctx1');
if( $ctx === false ) {
  echo "ERROR\n";
  echo $wiff->errorMessage."\n";
  exit( 1 );
}

$modAvail = $ctx->getAvailableModuleList();
if( $modAvail === false ) {
  echo "ERROR\n";
  echo $ctx->errorMessage."\n";
  exit( 1 );
}

if( count($modAvail) < 0 ) {
  echo "ERROR\n";
  exit( 1 );
}

echo "OK found ".count($modAvail)." modules.\n";
echo "\n";

$mod = $ctx->getModuleAvail("freedom");
if( $mod === false ) {
  echo "ERROR\n";
  echo $ctx->errorMessage."\n";
  exit( 1 );
}
echo "OK got module '".$mod->name."' in repository '".$mod->repository->name."'.\n";
echo "\n";

$tmpfile = $mod->download();
if( $tmpfile === false ) {
  echo "ERROR\n";
  exit( 1 );
}
echo "OK downloaded module '".$mod->name."' to '".$tmpfile."'.\n";
echo "\n";

$infoxml = $mod->getInfoXml();
if( $infoxml === false ) {
  echo "ERROR\n";
  exit( 1 );
}
echo "OK got info.xml:\n";
echo "--- info.xml ---\n";
echo $infoxml."\n";
echo "--- info.xml ---\n";
echo "\n";

$ret = $mod->loadInfoXml();
if( $ret === false ) {
  echo "ERROR\n";
  echo $mod->errorMessage."\n";
  exit( 1 );
}
print_r($mod);
echo "OK\n";
echo "\n";

$manifest = $mod->getManifest();
if( $manifest === false ) {
  echo "ERROR\n";
  exit( 1 );
}
echo "OK got manifest:\n";
echo "--- manifest ---\n";
echo substr($manifest, 0, 256)."[...]\n";
echo "--- manifest ---\n";
echo "\n";

$ret = $mod->unpack();
if( $ret === false ) {
  echo "ERROR\n";
  exit( 1 );
}
echo "OK unpacked module in '".$ret."'.";
echo "\n";

$mod->cleanupDownload();
exit( 0 );

?>