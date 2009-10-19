<?php

require_once('class/Class.WIFF.php');

function wiff_help(&$argv) {
  echo "\n";
  echo "Usage\n";
  echo "-----\n";
  echo "\n";
  echo "  wiff help [get|set|param|context]\n";
  echo "\n";
  echo "  wiff context <context-name>\n";
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
  if (!is_array($argv)) return 0;
  $ctx_name = array_shift($argv);
  if ($ctx_name=="") {
    wiff_help($argv);
    return 0;
  }
  $wiff = WIFF::getInstance();
  $context = $wiff->getContext($ctx_name);
  if( $context === false ) {
    error_log(sprintf("Error: could not get context '%s': %s", $ctx_name, $wiff->errorMessage));
    return 1;
  }


  if( count($argv) <= 0 ) {
    return wiff_context_exportenv($context, $argv);
  }

  $op = array_shift($argv);
  switch( $op ) {
  case 'exec':
  case 'shell':
    return wiff_context_shell($context, $argv);
    break;
  case 'exportenv':
    return wiff_context_exportenv($context, $argv);
    break;
  case 'help':
    return wiff_context_help($context, $argv);
    break;
  default:
    error_log(sprintf("Unknown operation '%s'!", $op));
  }

  return 0;
}

function wiff_context_help(&$context, &$argv) {
  echo "\n";
  echo "Usage\n";
  echo "-----\n";
  echo "\n";
  echo "  wiff context <context-name>\n";
  echo "\n";
  echo "  wiff context <context-name> exportenv\n";
  echo "  wiff context <context-name> shell\n";
  echo "  wiff context <context-name> exec /bin/bash\n";
  echo "\n";
  return 0;
}

function wiff_context_exportenv(&$context, &$argv) {
  echo "export wpub=".$context->root.";\n";
  echo "export pgservice_core=".$context->getParamByName("core_db").";\n";
  echo "export pgservice_freedom=".$context->getParamByName("core_db").";\n";
  echo "export httpuser=".$context->getParamByName("apacheuser").";\n";
  echo "export freedom_context=default\n";
  return 0;
}

function wiff_context_shell(&$context, &$argv) {
  if( ! function_exists("posix_setuid") ) {
    error_log(sprintf("Error: required POSIX PHP functions not available!"));
    return 1;
  }
  if( ! function_exists("pcntl_exec") ) {
    error_log(sprintf("Error: required PCNTL PHP functions not available!"));
    return 1;
  }

  $uid = posix_getuid();

  $httpuser = $context->getParamByName("apacheuser");
  if( $httpuser === false ) {
    error_log(sprintf("%s", $context->errorMessage));
    return 1;
  }
  if( $httpuser == '' ) {
    $httpuser = $uid;
  }

  $envs = array();
  $envs['wpub'] = $context->root;
  $envs['pgservice_core'] = $context->getParamByName("core_db");
  $envs['pgservice_freedom'] = $envs['pgservice_core'];
  $envs['freedom_context'] = "default";
  $envs['PS1'] = sprintf("wiff(%s)\\w\\$ ", $context->name);

  if( $envs['pgservice_core'] === false || $envs['pgservice_core'] == '' ) {
    error_log(sprintf("Error: empty core_db parameter!"));
    return 1;
  }

  $http_pw = false;
  if( is_numeric($httpuser) ) {
    $http_pw = posix_getpwuid($httpuser);
  } else {
    $http_pw = posix_getpwnam($httpuser);
  }
  if( $http_pw === false ) {
    error_log(sprintf("Error: could not get information for httpuser '%s'", $httpuser));
    return 1;
  }

  $http_uid = $http_pw['uid'];
  $http_gid = $http_pw['gid'];

  $shell = array_shift($argv);
  if( $shell === null ) {
    $shell = $http_pw['shell'];
  }

  $envs['HOME'] = $context->root;

  $ret = chdir($context->root);
  if( $ret === false ) {
    error_log(sprintf("Error: could not chdir to '%s'", $context->root));
    return 1;
  }

  if( $uid != $http_uid ) {
    $ret = posix_setgid($http_gid);
    if( $ret === false ) {
      error_log(sprintf("Error: could not setgid to gid '%s'", $http_gid));
      return 1;
    }
    $ret = posix_setuid($http_uid);
    if( $ret === false ) {
      error_log(sprintf("Error: could not setuid to uid '%s'", $http_uid));
      return 1;
    }
  }

  $ret = pcntl_exec($shell, $argv, $envs);
  if( $ret === false ) {
    error_log(sprintf("Error: exec error for '%s'", join(" ", array($shell, join(" ", $argv)))));
    exit( 1 );
  }
}

function wiff_whattext(&$argv) {
  $ctx_name = array_shift($argv);

  $wiff = WIFF::getInstance();

  $context = $wiff->getContext($ctx_name);
  if( $context === false ) {
    error_log(sprintf("Error: could not get context '%s'!", $ctx_name));
    return 1;
  }
  
  $whattext = sprintf("%s/whattext", $context->root);
  if( ! is_executable($whattext) ) {
    error_log(sprintf("Error: whattext '%s' not found or not executable.", $whattext));
    return 1;
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
    error_log(sprintf("Error: could not get context '%s'!", $ctx_name));
    return 1;
  }

  $wstart = sprintf("%s/wstop", $context->root);
  if( ! is_executable($wstart) ) {
    error_log(sprintf("Error: wstop '%s' not found or not executable.", $wstart));
    return 1;
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
    error_log(sprintf("Error: could not get context '%s'!", $ctx_name));
    return 1;
  }

  $wstart = sprintf("%s/wstart", $context->root);
  if( ! is_executable($wstart) ) {
    error_log(sprintf("Error: wstart '%s' not found or not executable.", $wstart));
    return 1;
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

  $value = wiff_getParamValue($paramName);
  if( $value === false ) {
    return 1;
  }
  echo $value."\n";
  return 0;
}

function wiff_getParamValue($paramName) {
  $wiffContextName = getenv('WIFF_CONTEXT_NAME');
  if( $wiffContextName === false || preg_match('/^\s*$/', $wiffContextName) ) {
    error_log(sprintf("Error: WIFF_CONTEXT_NAME is not defined or empty."));
    return false;
  }

  $wiff = WIFF::getInstance();
  $context = $wiff->getContext($wiffContextName);
  if( $context === false ) {
    error_log(sprintf("Error: could not get context '%s': %s", $wiffContextName, $wiff->errorMessage));
    return false;
  }

  return $context->getParamByName($paramName);
}

?>
