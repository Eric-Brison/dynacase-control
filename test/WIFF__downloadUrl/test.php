<?php
include_once('../test.php');

$url = "http://ftp.freedom-ecm.org/frdom/webinst/2_12/content.xml";

$wiff = WIFF::getInstance();

echo "* Downloading '$url' without proxy:\n";
$tempf = $wiff->downloadUrl($url);
if( $tempf === false ) {
  echo "ERROR\n";
  exit( 1 );
}

echo "OK ($tempf)\n";
echo "\n";

echo "* Downloading '$url' with proxy:\n";
$wiff->setParam('use-proxy', 'yes');
$wiff->setParam('proxy-host', '127.0.0.1');
$wiff->setParam('proxy-port', '8080');
$wiff->setParam('proxy-username', 'foo');
$wiff->setParam('proxy-password', 'bar');

$tempf = $wiff->downloadUrl($url);
if( $tempf === false ) {
  echo "ERROR\n";
  exit( 1 );
}

echo "OK ($tempf)\n";
echo "\n";

exit( 0 );

?>