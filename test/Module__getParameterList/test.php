<?php
include_once('../test.php');

$wiff = WIFF::getInstance();
$ctx = $wiff->getContext('ctx1');
if( $ctx === false ) {
  echo "ERROR\n";
  echo $wiff->errorMessage."\n";
  exit( 1 );
}

echo "* getModule('freedom')\n";
$mod = $ctx->getModule("freedom");
if( $mod === false ) {
  echo "ERROR\n";
  echo $ctx->errorMessage."\n";
  exit( 1 );
}
echo "OK found module.";
echo "\n";

echo "* getParameterList()\n";
$paramList = $mod->getParameterList();
if( $paramList === false ) {
  echo "ERROR\n";
  echo $ctx->errorMessage."\n";
  exit( 1 );
}
echo "OK found parameter list:\n";
echo "--- param list ---\n";
foreach( $paramList as $param ) {
  print_r($param);
}
echo "--- param list ---\n";
echo "\n";

echo "* getParameter('bar')\n";
$param = $mod->getParameter('bar');
if( $param === false ) {
  echo "ERROR\n";
  echo $mod->errorMessage."\n";
  exit( 1 );
}
echo "OK got parameter 'bar' with value '".$param->value."'.\n";
echo "\n";

echo "* store <param name='var' value='BAR'>\n";
$param->value = 'BAR';
$ret = $mod->storeParameter($param);
if( $ret === false ) {
  echo "ERROR\n";
  echo $mod->errorMessage."\n";
  exit( 1 );
}
echo "OK\n";
echo "\n";

echo "* getParameter('bar')\n";
$param = $mod->getParameter('bar');
if( $param === false ) {
  echo "ERROR\n";
  echo $mod->errorMessage."\n";
  exit( 1 );
}
echo "OK got parameter 'bar' with value '".$param->value."'.\n";
echo "\n";

echo "* getParameter('foo')\n";
$param = $mod->getParameter('foo');
if( $param === false ) {
  echo "ERROR\n";
  echo $mod->errorMessage."\n";
  exit( 1 );
}
echo "OK got parameter 'foo' with value '".$param->value."'.\n";
echo "\n";

echo "* store <param name='foo' value='FOO'>\n";
$param->value = "FOO";
$ret = $mod->storeParameter($param);
if( $ret === false ) {
  echo "ERROR\n";
  echo $mod->errorMessage."\n";
  exit( 1 );
}
echo "OK\n";
echo "\n";

echo "* getParameter('foo')\n";
$param = $mod->getParameter('foo');
if( $param === false ) {
  echo "ERROR\n";
  echo $mod->errorMessage."\n";
  exit( 1 );
}
echo "Ok got parameter 'foo' with value '".$param->value."'.\n";
echo "\n";

exit( 0 );

?>