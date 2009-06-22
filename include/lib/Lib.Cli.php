<?php

require_once('class/Class.WIFF.php');

function wiff_help(&$argv) {
  echo "\n";
  echo "Usage\n";
  echo "-----\n";
  echo "\n";
  echo "  wiff help [get|set|param|context]\n";
  echo "\n";
  echo "  wiff param show\n";
  echo "  wiff param get <param_name>\n";
  echo "  wiff param set <param_name> <param_value>\n";
  echo "\n";
  echo "  wiff whattext <context_name>\n";
  echo "  wiff wstop <context_name>\n";
  echo "  wiff wstart <context_name>\n";
  echo "\n";
  return 0;
}

function wiff_param(&$argv) {
  $op = array_shift($argv);
  switch( $op ) {
  case 'show':
    echo "'param show' not yet implemented.\n";
    break;
  case 'get':
    echo "'param get' not yet implemented.\n";
    break;
  case 'set':
    echo "'param set' not yet implemented.\n";
    break;
  }
  return 0;
}

function wiff_show(&$argv) {
  echo "'show' not yet implemented.\n";
  return 0;
}

function wiff_context(&$argv) {
  echo "'context' not yet implemented.\n";
  return 0;
}

function wiff_whattext(&$argv) {
  $ctx_name = array_shift($argv);

  $wiff = WIFF::getInstance();

  $context = $wiff->getContext($ctx_name);
  if( $context === false ) {
    echo sprintf("Error getting context '%s'!", $ctx_name);
    return -1;
  }
  
  $whattext = sprintf("%s/whattext", $context->root);
  if( ! is_executable($whattext) ) {
    echo sprintf("whattext '%s' not found or not executable.", $whattext);
    return -1;
  }

  $cmd = sprintf("%s", escapeshellarg($whattext));
  $out = system($cmd, $ret);

  return $ret;
}

function wiff_wstop(&$argv) {
  $ctx_name = array_shift($argv);

  $wiff = WIFF::getInstance();

  $context = $wiff->getContext($ctx_name);
  if( $context === false ) {
    echo sprintf("Error getting context '%s'!", $ctx_name);
    return -1;
  }

  $wstart = sprintf("%s/wstop", $context->root);
  if( ! is_executable($wstart) ) {
    echo sprintf("wstop '%s' not found or not executable.", $wstart);
    return -1;
  }

  $cmd = sprintf("%s", escapeshellarg($wstart));
  $out = system($cmd, $ret);

  return $ret;
}

function wiff_wstart(&$argv) {
  $ctx_name = array_shift($argv);

  $wiff = WIFF::getInstance();

  $context = $wiff->getContext($ctx_name);
  if( $context === false ) {
    echo sprintf("Error getting context '%s'!", $ctx_name);
    return -1;
  }

  $wstart = sprintf("%s/wstart", $context->root);
  if( ! is_executable($wstart) ) {
    echo sprintf("wstart '%s' not found or not executable.", $wstart);
    return -1;
  }

  $cmd = sprintf("%s", escapeshellarg($wstart));
  $out = system($cmd, $ret);

  return $ret;
}

function wiff_default(&$argv) {
  if( stripos($argv[0], '--getValue=') !== false ) {
    return wiff_default_getValue($argv);
  }
  return wiff_help($argv);
}

function wiff_default_getValue(&$argv) {
  global $wiff_root;

  $paramName =  substr($argv[0], 11);

  $wiffContextName = getenv('WIFF_CONTEXT_NAME');
  if( $wiffContextName === false || preg_match('/^\s*$/', $wiffContextName) ) {
    error_log(sprintf("WIFF_CONTEXT_NAME is not defined or empty."));
    exit( 1 );
  }

  $wiff = WIFF::getInstance();

  $context = $wiff->getContext($wiffContextName);
  if( $context === false ) {
    echo sprintf("Error getting context '%s'!", $wiffContextName);
    echo "";
    return -1;
  }

  echo $context->getParamByName($paramName)."\n";
  return 0;
}

?>