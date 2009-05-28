<?php
include_once('../test.php');

$wiff = new WIFF();
$context = $wiff->createContext("foo", 'ctx-foo', "Contexte de foo");

if( $context === false ) {
  echo "FAILED:\n";
  echo $wiff->errorMessage."\n";
  exit( 1 );
}

echo "OK\n";
exit( 0 );

?>