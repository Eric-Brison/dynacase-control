<?php
include_once('../test.php');

$wiff = WIFF::getInstance();
$ctx = $wiff->getContext('ctx1');
if( $ctx === false ) {
  echo "ERROR\n";
  echo $wiff->errorMessage."\n";
  exit( 1 );
}

$phase = 'install';

$depsList = $ctx->getModuleDependencies(array('foo'));
if( $depsList === false ) {
  echo "ERROR\n";
  echo $ctx->errorMessage."\n";
  exit( 1 );
}
echo "--- dependencies ---\n";
foreach( $depsList as $dep ) {
  echo sprintf("%s %s-%s for %s\n", $dep->name, $dep->version, $dep->release, ($dep->needphase!='')?$dep->needphase:$phase);
}
echo "--- dependencies ---\n";
echo "OK found ".count($depsList)." dependencies.\n";
echo "\n";

exit( 0 );

?>
