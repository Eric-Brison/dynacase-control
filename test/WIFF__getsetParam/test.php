<?php
include_once('../test.php');

$wiff = new WIFF();

echo "getParam\n";
echo "--------\n";
$v = $wiff->getParam('use-proxy');
if( $v === false ) {
  echo "ERROR\n";
  exit( 1 );
}
echo " use-proxy = [$v]\n";
if( $v != 'no' ) {
  echo "ERROR\n";
  exit( 1 );
}
echo "\n";

echo "setParam\n";
echo "--------\n";
$v = $wiff->setParam('proxy-host', 'proxy.example.net');
if( $v === false ) {
  echo "ERROR\n";
  exit( 1 );
}
echo " proxy-host = [$v]\n";
echo "\n";

echo "getParam\n";
echo "--------\n";
$v = $wiff->getParam('proxy-host');
if( $v === false ) {
  echo "ERROR\n";
  exit( 1 );
}
echo " proxy-host = [$v]\n";
if( $v != 'proxy.example.net' ) {
  echo "ERROR\n";
  exit( 1 );
}
echo "\n";

echo "getParamList\n";
echo "------------\n";
$plist = $wiff->getParamList();
if( $plist === false ) {
  echo "ERROR\n";
  exit( 1 );
}
foreach( $plist as $k => $v) {
  echo " $k = [$v]\n";
}
echo "\n";

exit( 0 );

?>