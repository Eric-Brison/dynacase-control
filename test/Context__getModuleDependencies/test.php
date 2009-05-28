<?php
include_once('../test.php');

$wiff = WIFF::getInstance();
$ctx = $wiff->getContext('ctx1');
if( $ctx === false ) {
  echo "ERROR\n";
  echo $wiff->errorMessage."\n";
  exit( 1 );
}

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

exit( 0 );

?>