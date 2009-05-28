<?php
include_once('../test.php');

$wiff = WIFF::getInstance();
$ctx = $wiff->getContext('ctx1');
if( $ctx === false ) {
  echo "ERROR\n";
  echo $wiff->errorMessage."\n";
  exit( 1 );
}

echo "* Getting dependencies for 'freedom-dav'\n";
$depsList = $ctx->getModuleDependencies('freedom-dav');
if( $depsList === false ) {
  echo "ERROR\n";
  echo $ctx->errorMessage."\n";
  exit( 1 );
}
echo "--- dependencies ---\n";
foreach( $depsList as $dep ) {
  echo sprintf("%s %s-%s\n", $dep->name, $dep->version, $dep->release);
}
echo "--- dependencies ---\n";
echo "OK found ".count($despList)." dependencies.\n";
echo "\n";

echo "* Getting avail module 'freedom-core'\n";
$module = $ctx->getModuleAvail('freedom-core');
if( $module === false ) {
  echo "ERROR\n";
  echo $ctx->errorMessage."\n";
  exit( 1 );
}
echo "OK\n";
echo "\n";

echo "* Downloading module\n";
$tmpfile = $module->download();
if( $tmpfile === false ) {
  echo "ERROR\n";
  echo $module->errorMessage."\n";
  exit( 1 );
}
echo "OK got module in '".$tmpfile."'\n";
echo "\n";

echo "* Loading info.xml\n";
$xmlNode = $module->loadInfoXml();
if( $xmlNode === false ) {
  echo "ERROR\n";
  echo $module->errorMessage."\n";
  exit( 1 );
}
echo "OK loaded info.xml (author=".$module->author.")\n";
echo "\n";

echo "* Getting install phases\n";
$phaseList = $module->getPhaseList('install');
if( $phaseList === false ) {
  echo "ERROR\n";
  exit( 1 );
}
echo "OK got ".count($phaseList)." phases.\n";
echo "author = ".$module->author."\n";
echo "\n";

echo "* Getting processes for pre-install phase\n";
$phase = $module->getPhase('pre-install');
if( $phase === false ) {
  echo "ERROR\n";
  exit( 1 );
}
$processList = $phase->getProcessList();
if( $processList === false ) {
  echo "ERROR\n";
  exit( 1 );
}
echo "OK got ".count($processList)." processes.\n";
echo "\n";

exit( 0 );

?>