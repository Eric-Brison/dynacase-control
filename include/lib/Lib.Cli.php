<?php

require_once('class/Class.WIFF.php');

/**
 * wiff help
 */
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

/**
 * wiff list
 */
function wiff_list(&$argv) {
  $op = array_shift($argv);

  switch( $op ) {
  case 'context':
    return wiff_list_context($argv);
    break;
  default:
    error_log(sprintf("Unknown operation '%s'!\n", $op));
  }
  return 0;
}

/**
 * wiff list context
 */
function wiff_list_context(&$argv) {
  $options = parse_argv_options($argv);

  $wiff = WIFF::getInstance();

  $ctxList = $wiff->getContextList();

  if( array_key_exists('pretty', $options) && $options['pretty'] ) {
    echo sprintf("%-16s | %-64s\n", "Name", "Description");
    echo sprintf("%-16s-+-%-64s\n", str_repeat("-", 16), str_repeat("-", 64));
  }
  foreach( $ctxList as $ctx ) {
    if( array_key_exists('pretty', $options) && $options['pretty'] ) {
      echo sprintf("%-16s | %-64s\n", $ctx->name, $ctx->description);
    } else {
      echo sprintf("%s\n", $ctx->name);
    }
  }

  return 0;
}

/**
 * wiff param
 */
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

/**
 * wiff show
 */
function wiff_show(&$argv) {
  echo "'show' not yet implemented.\n";
  return 0;
}

/**
 * wiff context
 */
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
    error_log(sprintf("Error: could not get context '%s': %s\n", $ctx_name, $wiff->errorMessage));
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
  case 'module':
    return wiff_context_module($context, $argv);
    break;
  case 'help':
    return wiff_context_help($context, $argv);
    break;
  default:
    error_log(sprintf("Unknown operation '%s'!\n", $op));
  }

  return 0;
}

/**
 * wiff context help
 */
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

/**
 * wiff context <ctxName> exportenv
 */
function wiff_context_exportenv(&$context, &$argv) {
  echo "export wpub=".$context->root.";\n";
  echo "export pgservice_core=".$context->getParamByName("core_db").";\n";
  echo "export pgservice_freedom=".$context->getParamByName("core_db").";\n";
  echo "export httpuser=".$context->getParamByName("apacheuser").";\n";
  echo "export freedom_context=default\n";
  return 0;
}

/**
 * wiff context <ctxName> shell
 */
function wiff_context_shell(&$context, &$argv) {
  if( ! function_exists("posix_setuid") ) {
    error_log(sprintf("Error: required POSIX PHP functions not available!\n"));
    return 1;
  }
  if( ! function_exists("pcntl_exec") ) {
    error_log(sprintf("Error: required PCNTL PHP functions not available!\n"));
    return 1;
  }

  $uid = posix_getuid();

  $httpuser = $context->getParamByName("apacheuser");
  if( $httpuser === false ) {
    error_log(sprintf("%s\n", $context->errorMessage));
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
    error_log(sprintf("Error: empty core_db parameter!\n"));
    return 1;
  }

  $http_pw = false;
  if( is_numeric($httpuser) ) {
    $http_pw = posix_getpwuid($httpuser);
  } else {
    $http_pw = posix_getpwnam($httpuser);
  }
  if( $http_pw === false ) {
    error_log(sprintf("Error: could not get information for httpuser '%s'\n", $httpuser));
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
    error_log(sprintf("Error: could not chdir to '%s'\n", $context->root));
    return 1;
  }

  if( $uid != $http_uid ) {
    $ret = posix_setgid($http_gid);
    if( $ret === false ) {
      error_log(sprintf("Error: could not setgid to gid '%s'\n", $http_gid));
      return 1;
    }
    $ret = posix_setuid($http_uid);
    if( $ret === false ) {
      error_log(sprintf("Error: could not setuid to uid '%s'\n", $http_uid));
      return 1;
    }
  }

  $ret = pcntl_exec($shell, $argv, $envs);
  if( $ret === false ) {
    error_log(sprintf("Error: exec error for '%s'\n", join(" ", array($shell, join(" ", $argv)))));
    exit( 1 );
  }
}

/**
 * wiff context <ctxName> module
 */
function wiff_context_module(&$context, &$argv) {
  $op = array_shift($argv);

  switch($op) {
  case 'install':
    return wiff_context_module_install($context, $argv);
    break;
  case 'upgrade':
    return wiff_context_module_upgrade($context, $argv);
    break;
  case 'extract':
    return wiff_context_module_extract($context, $argv);
    break;
  case 'list':
    return wiff_context_module_list($context, $argv);
    break;
  default:
    wiff_context_module_help($context, $argv);
    break;
  }
  $wiff = WIFF::getInstance();

  return 0;
}

function wiff_context_module_help(&$context, &$argv) {
  echo "\n";
  echo "Usage\n";
  echo "-----\n";
  echo "\n";
  echo "  wiff context <context-name> module install [install-options] <localModulePkgPath|modName>\n";
  echo "\n";
  echo "  wiff context <context-name> module upgrade [upgrade-options] <localModulePkgPath|modName>\n";
  echo "  wiff context <context-name> module extract [extract-options] <localModulePkgPath|modName>\n";
  echo "  wiff context <context-name> module list <installed|available|updates>\n";
  echo "\n";
  return 0;
}

function wiff_context_module_install(&$context, &$argv) {
  $options = parse_argv_options($argv);

  $modName = array_shift($argv);

  if( is_file($modName) ) {
    return wiff_context_module_install_local($context, $options, $modName, $argv);
  } else {
    return wiff_context_module_install_remote($context, $options, $modName, $argv);
  }

  return 0;
}

function wiff_context_module_install_local(&$context, &$options, &$pkgName, &$argv) {
  require_once('lib/Lib.System.php');

  $tmpfile = WiffLibSystem::tempnam(null, basename($pkgName));
  if( $tmpfile === false ) {
    error_log(sprintf("Error: could not create temp file!\n"));
    return 1;
  }

  $ret = copy($pkgName, $tmpfile);
  if( $ret === false ) {
    error_log(sprintf("Error: could not copy '%s' to '%s'!\n", $pkgName, $tmpfile));
    return 1;
  }

  $tmpMod = $context->importArchive($tmpfile);
  if( $tmpMod === false ) {
    error_log(sprintf("Error: could not import module '%s': %s\n", $tmpfile, $context->errorMessage));
    return 1;
  }

  $depList = $context->getLocalModuleDependencies($tmpfile);
  if( $depList === false ) {
    error_log(sprintf("Error: could not get dependencies for '%s': %s\n", $tmpfile, $context->errorMessage));
    return 1;
  }

  $downloaded = array();
  foreach( $depList as $module ) {
    echo sprintf("Processing required module '%s' (%s-%s).\n", $module->name, $module->version, $module->release);

    if( $module->status == 'downloaded' ) {
      echo sprintf("Module '%s-%s-%s' is already downloaded in '%s'.\n", $module->name, $module->version, $module->release, $module->tmpfile);
    } else {
      echo sprintf("Downloading module '%s-%s-%s'...\n", $module->name, $module->version, $module->release);

      /**
       * download module
       */
      $ret = $module->download();
      if( $ret === false ) {
	error_log(sprintf("Error: could not download module '%s': %s\n", $module->name, $module->errorMessage));
	return 1;
      }

      /**
       * switch to the module object from the context XML database
       */
      $modName = $module->name;
      $module = $context->getModule($modName);
      if( $module === false ) {
	error_log(sprintf("Error: could not get module '%s' from context: %s\n", $modName, $context->errorMessage));
	return 1;
      }

      /**
       * wstop
       */
      $ret = $context->wstop();

      /**
       * ask module parameters
       */
      $paramList = $module->getParameterList();
      if( $paramList !== false && count($paramList) > 0 ) {

	$title = sprintf("Parameters for module '%s'", $module->name);
	if( ! array_key_exists('yes', $options) || ! $options['yes'] ) {
	  echo sprintf("\n%s\n%s\n\n", $title, str_repeat('-', strlen($title)));
	}

	foreach( $paramList as $param ) {
	  $pvalue = $param->value==""?$param->default:$param->default;

	  if( array_key_exists('yes', $options) && $options['yes'] ) {
	    $value = $pvalue;
	  } else {
	    $value = param_ask($param->name, $pvalue);
	  }
	  if( $value === false ) {
	    error_log(sprintf("Error: could not read answer!"));
	    return 1;
	  }
	  $param->value = $value;

	  $ret = $module->storeParameter($param);
	  if( $ret === false ) {
	    error_log(sprintf("Error: could not store parameter '%s'!\n", $param->name));
	    return 1;
	  }

	  if( ! array_key_exists('yes', $options) || ! $options['yes'] ) {
	    echo "\n";
	  }
	}
      }

      /**
       * Execute phase/process list
       */
      $phaseList = $module->getPhaseList('install');
      foreach( $phaseList as $phaseName ) {

	echo sprintf("Processing phase '%s'...\n", $phaseName);
	$phase = $module->getPhase($phaseName);
	$processList = $phase->getProcessList();

	echo sprintf("Processes:\n");

	foreach( $processList as $process ) {
	  echo sprintf("Will run process '%s': %s\n", $process->name, $process->label);
	}

      }

      array_push($downloaded, $module);
    }
  }
  
  return 0;
}

function wiff_context_module_install_remote(&$context, &$options, &$modName, &$argv) {
  return 0;
}

function wiff_context_module_upgrade(&$context, &$argv) {
  return 0;
}

function wiff_context_module_extract(&$context, &$argv) {
  return 0;
}

function wiff_context_module_list(&$context, &$argv) {
  $op = array_shift($argv);

  return 0;
}

/**
 * wiff mkrepoidx <repoPath>
 */
function wiff_mkrepoidx(&$argv) {
  $repoPath = array_shift($argv);
  if( $repoPath === null ) {
    error_log(sprintf("Error: missing repository path!\n"));
    return 1;
  }

  if( !is_dir($repoPath) ) {
    error_log(sprintf("Error: supplied repository path '%s' is not a directory!\n", $repoPath));
    return 1;
  }

  $dir = opendir($repoPath);
  if( $dir === FALSE ) {
    error_log(sprintf("Error: could not open directory '%s'\n", $repoPath));
    return 1;
  }

  $contentxml = new DOMDocument();
  $contentxml->formatOutput = true;

  $repoNode = new DOMElement('repo');
  $contentxml->appendChild($repoNode);

  $modulesNode = new DOMElement('modules');
  $repoNode->appendChild($modulesNode);

  while( ($file = readdir($dir)) !== FALSE ) {
    if( ! is_file($repoPath.'/'.$file) ) {
      continue;
    }
    if( ! preg_match('/\.webinst$/', $file) ) {
      continue;
    }
    $cmd = "tar -zxOf ".escapeshellcmd($repoPath.'/'.$file)." info.xml 2> /dev/null";
    $tar = popen($cmd, "r");
    if( $tar === FALSE ) {
      error_log(sprintf("Error: running '%s'!\n", $cmd));
      return 2;
    }
    $info = '';
    while( ($data = fgets($tar)) !== FALSE ) {
      $info .= $data;
    }
    fclose($tar);
    if( $info == '' ) {
      error_log(sprintf("Warning: empty, or non-existing, 'info.xml' file in package '%s'\n", $file));
      continue;
    }

    $ret = wiff_mkrepoidx_process_info($contentxml, $modulesNode, $info, $file);
    if( $ret === FALSE ) {
      error_log(sprintf("Error: processing file '%s/%s'!\n", $repoPath, $file));
      return 3;
    }
  }

  closedir($dir);

  $temp = tempnam($repoPath, "tmp.content.xml");
  if( $temp === FALSE ) {
    error_log(sprintf("Error: could not create temporary content.xml file!\n"));
    return 4;
  }

  $fh = fopen($temp, "w");
  if( $fh === FALSE ) {
    error_log(sprintf("Error: could not open temp file '%s' for writing!\n", $temp));
    return 4;
  }

  $ret = fwrite($fh, $contentxml->saveXML());
  if( $ret === FALSE ) {
    error_log(sprintf("Error: write to '%s' failed!\n", $temp));
    return 4;
  }

  fclose($fh);

  $ret = chmod($temp, 0644);
  if( $ret === FALSE ) {
    error_log(sprintf("Error: chmod on temp file '%s' failed!\n", $temp));
    return 4;
  }

  $ret = rename($temp, $repoPath.'/content.xml');
  if( $ret === FALSE ) {
    error_log(sprintf("Error: rename of temp file '%s' to '%s/content.xml' failed!\n", $temp, $repoPath));
    return 4;
  }

  return 0;
}

function wiff_mkrepoidx_process_info(&$parentDocument, &$parentNode, $info, $file) {
  $infoxml = new DOMDocument();
  $ret = $infoxml->loadXML($info);
  if( $ret === FALSE ) {
    error_log(sprintf("Error: loading XML content'\n"));
    return FALSE;
  }
  $nodeList = $infoxml->childNodes;
  if( $nodeList->length <= 0 ) {
    error_log(sprintf("Error: XML root node contains no childs!\n"));
    return FALSE;
  }
  if( $nodeList->length > 1 ) {
    error_log(sprintf("Error: more than one child in XML root node!\n"));
    return FALSE;
  }

  $node = $nodeList->item(0);
  $node->setAttribute('src', $file);

  $xpath = new DOMXpath($infoxml);
  $childs = $xpath->query('/module/*');

  $i = 0;
  while( $i < $childs->length ) {
    $child = $childs->item($i);
    if( $child->nodeName != "description" && $child->nodeName != "requires" ) {
      $node->removeChild($child);
    }
    $i++;
  }

  $newNode = $parentDocument->importNode($node, TRUE);
  $parentNode->appendChild($newNode);

  return TRUE;
}

function wiff_whattext(&$argv) {
  $ctx_name = array_shift($argv);

  $wiff = WIFF::getInstance();

  $context = $wiff->getContext($ctx_name);
  if( $context === false ) {
    error_log(sprintf("Error: could not get context '%s'!\n", $ctx_name));
    return 1;
  }
  
  $whattext = sprintf("%s/whattext", $context->root);
  if( ! is_executable($whattext) ) {
    error_log(sprintf("Error: whattext '%s' not found or not executable.\n", $whattext));
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
    error_log(sprintf("Error: could not get context '%s'!\n", $ctx_name));
    return 1;
  }

  $wstart = sprintf("%s/wstop", $context->root);
  if( ! is_executable($wstart) ) {
    error_log(sprintf("Error: wstop '%s' not found or not executable.\n", $wstart));
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
    error_log(sprintf("Error: could not get context '%s'!\n", $ctx_name));
    return 1;
  }

  $wstart = sprintf("%s/wstart", $context->root);
  if( ! is_executable($wstart) ) {
    error_log(sprintf("Error: wstart '%s' not found or not executable.\n", $wstart));
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
    error_log(sprintf("Error: WIFF_CONTEXT_NAME is not defined or empty.\n"));
    return false;
  }

  $wiff = WIFF::getInstance();
  $context = $wiff->getContext($wiffContextName);
  if( $context === false ) {
    error_log(sprintf("Error: could not get context '%s': %s\n", $wiffContextName, $wiff->errorMessage));
    return false;
  }

  return $context->getParamByName($paramName);
}

function parse_argv_options(&$argv) {
  $options = array();
  $m = array();
  while( preg_match('/^--/', $argv[0]) ) {
    if( preg_match('/^--([a-zA-Z0-9_-]+)=(.*)$/', $argv[0], $m) ) {
      $options[$m[1]] = $m[2];
    } elseif( preg_match('/--([a-zA-Z0-9_-]+)$/', $argv[0], $m) ) {
      $options[$m[1]] = true;
    } elseif( preg_match('/^--$/', $argv[0]) ) {
      return $options;
    }
    array_shift($argv);
  }

  return $options;
}

function param_ask($prompt, $default) {
  echo sprintf("%s ? [%s]", $prompt, $default);
  $fh = fopen('php://stdin', 'r');
  if( $fh === false ) {
    return false;
  }
  $ans = fgets($fh);
  if( $ans === false ) {
    return $ans;
  }
  $ans = rtrim($ans);
  if( $ans == "" ) {
    return $default;
  }
  return $ans;
}

?>
