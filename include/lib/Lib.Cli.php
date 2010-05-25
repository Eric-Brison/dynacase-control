<?php

/**
 * CLI Library
 * @author Anakeen
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License
 */

require_once('class/Class.WIFF.php');

global $wiff_lock;

/**
 * wiff help
 */
function wiff_help(&$argv) {
  echo "\n";
  echo "Usage\n";
  echo "-----\n";
  echo "\n";
  echo "  wiff help\n";
  echo "\n";
  echo "  wiff list context\n";
  echo "\n";
  echo "  wiff context <context-name> help\n";
  echo "\n";
  echo "  wiff whattext <context-name>\n";
  echo "  wiff wstop <context-name>\n";
  echo "  wiff wstart <context-name>\n";
  echo "\n";
  return 0;
}

/**
 * wiff (un)lock
 */
function wiff_lock() {
  global $wiff_lock;
  $wiff = WIFF::getInstance();
  $wiff_lock = $wiff->lock();
  if( $wiff_lock === false ) {
    error_log(sprintf("Warning: could not lock wiff!"));
  }
  return $wiff_lock;
}

function wiff_unlock() {
  global $wiff_lock;
  $wiff = WIFF::getInstance();
  $ret = $wiff->unlock($wiff_lock);
  if( $ret === false ) {
    error_log(sprintf("Warning: could not unlock wiff!"));
  }
  return $ret;
}

/**
 * wiff list
 */
function wiff_list(&$argv) {
  $op = array_shift($argv);

  switch( $op ) {
  case 'context':
    wiff_lock();
    $ret = wiff_list_context($argv);
    wiff_unlock();
    return $ret;
    break;
  default:
    error_log(sprintf("Unknown operation '%s'!\n", $op));
    return wiff_list_help($argv);
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
  if( $ctxList === false ) {
    error_log(sprintf("Error: cound not get contexts list: %s\n", $wiff->errorMessage));
    return 1;
  }

  if( boolopt('pretty', $options) ) {
    echo sprintf("%-16s   %-64s\n", "Name", "Description");
    echo sprintf("%-16s---%-64s\n", str_repeat("-", 16), str_repeat("-", 64));
  }
  foreach( $ctxList as $ctx ) {
    if( boolopt('pretty', $options) ) {
      echo sprintf("%-16s   %-64s\n", $ctx->name, $ctx->description);
    } else {
      echo sprintf("%s\n", $ctx->name);
    }
  }

  return 0;
}

function wiff_list_help(&$argv) {
  echo "\n";
  echo "Usage\n";
  echo "-----\n";
  echo "\n";
  echo "  wiff list context\n";
  echo "\n";
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
    $ret = wiff_context_exportenv($context, $argv);
    return $ret;
    break;
  case 'module':
    $ret = wiff_context_module($context, $argv);
    return $ret;
    break;
  case 'param':
    $ret = wiff_context_param($context, $argv);
    return $ret;
    break;
  case 'help':
    return wiff_context_help($context, $argv);
    break;
  default:
    error_log(sprintf("Unknown operation '%s'!\n", $op));
    return wiff_context_help($context, $argv);
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
  echo "  wiff context <context-name> exec /bin/bash --login\n";
  echo "\n";
  echo "  wiff context <context-name> param help\n";
  # echo "  wiff context <context-name> param show [<module-name>]\n";
  # echo "  wiff context <context-name> param get <module-name>:<param-name>\n";
  # echo "  wiff context <context-name> param set <module-name>:<param-name> <param-value>\n";
  echo "\n";
  echo "  wiff context <context-name> module help\n";
  # echo "  wiff context <context-name> module install [--force] [--nopre] [--nopost] [--nothing] <localPkgName|remotePkgName>\n";
  # echo "  wiff context <context-name> module upgrade [--force] [--nopre] [--nopost] [--nothing] <localPkgName|remotePkgName>\n";
  # echo "  wiff context <context-name> module list\n";
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
    wiff_lock();
    $ret = wiff_context_module_install($context, $argv);
    wiff_unlock();
    return $ret;
    break;
  case 'upgrade':
    wiff_lock();
    $ret = wiff_context_module_upgrade($context, $argv);
    wiff_unlock();
    return $ret;
    break;
  case 'extract':
    $ret = wiff_context_module_extract($context, $argv);
    return $ret;
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
  echo "  wiff context <context-name> module upgrade [upgrade-options] <localModulePkgPath|modName>\n";
  echo "  wiff context <context-name> module list installed|available|updates\n";
  echo "\n";
  echo "install-options\n";
  echo "---------------\n";
  echo "\n";
  echo "  --nopre    Do not execute pre-install processes.\n";
  echo "  --nopost   Do not execute post-install processes.\n";
  echo "  --nothing  Do not execute pre-install and post-install processes.\n";
  echo "  --force    Force installation.\n";
  echo "\n";
  echo "upgrade-options\n";
  echo "---------------\n";
  echo "\n";
  echo "  --nopre    Do not execute pre-upgrade processes.\n";
  echo "  --nopost   Do not execute post-upgrade processes.\n";
  echo "  --nothing  Do not execute pre-upgrade and post-upgrade processes.\n";
  echo "  --force    Force upgrade.\n";
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
  require_once('class/Class.Module.php');

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

  $tmpMod = $context->loadModuleFromPackage($tmpfile);
  if( $tmpMod === false ) {
    error_log(sprintf("Error: could not load module '%s': %s\n", $tmpfile, $context->errorMessage));
    return 1;
  }

  $existingModule = $context->getModuleInstalled($tmpMod->name);
  if( $existingModule !== false ) {
    echo sprintf("A module '%s' with version '%s-%s' already exists.\n", $existingModule->name, $existingModule->version, $existingModule->release);
    if( !boolopt('force', $options) ) {
      return 0;
    }
  }
  unset($existingModule);

  $tmpMod = $context->importArchive($tmpfile, 'downloaded');
  if( $tmpMod === false ) {
    error_log(sprintf("Error: could not import module '%s': %s\n", $tmpfile, $context->errorMessage));
    return 1;
  }

  $depList = $context->getLocalModuleDependencies($tmpfile);
  if( $depList === false ) {
    error_log(sprintf("Error: could not get dependencies for '%s': %s\n", $tmpfile, $context->errorMessage));
    return 1;
  }

  if( count($depList) > 1 ) {
    echo sprintf("Will (i)nstall, (u)pgrade or (r)eplace the following packages:\n");
    foreach( $depList as $module ) {
      if( $module->needphase == '' ) {
	$module->needphase = 'install';
      }
      $op = '(i)';
      if( $module->needphase == 'upgrade' ) {
	$op = '(u)';
      } else if( $module->needphase == 'replaced' ) {
	$op = '(r)';
      }
      echo sprintf("- %s-%s-%s %s\n", $module->name, $module->version, $module->release, $op);
    }
    $ret = param_ask("Proceed with installation", "Y/n", "Y");
    if( !preg_match('/^(y|yes|)$/i', $ret) ) {
      return 0;
    }
  }

  return wiff_context_module_install_deplist($context, $options, $argv, $depList);
}

function wiff_context_module_install_remote(&$context, &$options, &$modName, &$argv) {
  require_once('lib/Lib.System.php');

  $existingModule = $context->getModuleInstalled($modName);
  if( $existingModule !== false ) {
    echo sprintf("A module '%s' with version '%s-%s' already exists.\n", $existingModule->name, $existingModule->version, $existingModule->release);
    if( !boolopt('force', $options) ) {
      return 0;
    }
  }
  unset($existingModule);

  $depList = $context->getModuleDependencies(array($modName));
  if( $depList === false ) {
    error_log(sprintf("Error: could not find a module named '%s'!", $modName));
    return 1;
  }

  if( count($depList) > 1 ) {
    echo sprintf("Will (i)nstall, (u)pgrade, or (r)eplace the following packages:\n");
    foreach( $depList as $module ) {
      if( $module->needphase == '' ) {
	$module->needphase = 'install';
      }
      $op = '(i)';
      if( $module->needphase == 'upgrade' ) {
	$op = '(u)';
      } else if( $module->needphase == 'replaced' ) {
	$op = '(r)';
      }
      echo sprintf("- %s-%s-%s %s\n", $module->name, $module->version, $module->release, ($module->needphase=='upgrade'?'(u)':'(i)'));
    }
    $ret = param_ask("Proceed with installation", "Y/n", "Y");
    if( !preg_match('/^(y|yes|)$/i', $ret) ) {
      return 0;
    }
  }

  return wiff_context_module_install_deplist($context, $options, $argv, $depList);
}

function wiff_context_module_install_deplist(&$context, &$options, &$argv, &$depList, $type='install') {
  $downloaded = array();
  foreach( $depList as $module ) {
    if( $module->needphase != '' ) {
      if( $module->needphase == 'replaced' ) {
	$type = 'unregister';
      } else {
	$type = $module->needphase;
      }
    }

    echo sprintf("\nProcessing module '%s' (%s-%s) for %s.\n", $module->name, $module->version, $module->release, $type);

    if( $module->needphase == 'replaced' ) {
      /**
       * Unregister module
       */
      $mod = $context->getModuleInstalled($module->name);
      if( $mod === false ) {
	continue;
      }
      echo sprintf("Unregistering module '%s'.\n", $module->name);
      $ret = $context->removeModule($module->name);
      if( $ret === false ) {
	error_log(sprintf("Error: cound not unregister module '%s' from context: %s\n", $module->name, $context->errorMessage));
	return 1;
      }
      continue;
    }

    if( $module->status == 'downloaded' && is_file($module->tmpfile) ) {
      echo sprintf("Module '%s-%s-%s' is already downloaded in '%s'.\n", $module->name, $module->version, $module->release, $module->tmpfile);
    } else {
      echo sprintf("Downloading module '%s-%s-%s'... ", $module->name, $module->version, $module->release);

      /**
       * download module
       */
      $ret = $module->download('downloaded');
      if( $ret === false ) {
	error_log(sprintf("Error: could not download module '%s': %s\n", $module->name, $module->errorMessage));
	return 1;
      }

      echo sprintf("[%sOK%s]\n", fg_green(), color_reset());
    }

    /**
     * switch to the module object from the context XML database
     */
    $modName = $module->name;
    $module = $context->getModuleDownloaded($modName);
    if( $module === false ) {
      error_log(sprintf("Error: could not get module '%s' from context: %s\n", $modName, $context->errorMessage));
      return 1;
    }
    
    /**
     * ask license
     */
    if( $module->license != '' ) {
      $license = $module->getLicenseText();
      if( $license === false ) {
	error_log(sprintf("Error: could not get license '%s' for module '%s': %s\n", $module->license, $module->name, $module->errorMessage));
	return 1;
      }

      $licenseAgreement = $module->getLicenseAgreement();
      if( $license != '' && $licenseAgreement != 'yes' ) {
	$agree = license_ask($module->name, $module->license, $license);
	if( $agree == 'yes' ) {
	  $ret = $module->storeLicenseAgreement($agree);
	} else {
	  error_log(sprintf("Notice: you did not agreed to '%s' for module '%s'.", $module->license, $module->name));
	  exit( 1 );
	}
      }
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
      if( !boolopt('yes', $options) ) {
	echo sprintf("\n%s\n%s\n\n", $title, str_repeat('-', strlen($title)));
      }
      
      foreach( $paramList as $param ) {
	$pvalue = $param->value==""?$param->default:$param->value;
	
	if( boolopt('yes', $options) ) {
	  $value = $pvalue;
	} else {
	  $value = param_ask($param->name, $pvalue, $pvalue);
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
	
	if( !boolopt('yes', $options) ) {
	  echo "\n";
	}
      }
    }
    
    /**
     * Execute phase/process list
     */
    $phaseList = $module->getPhaseList($type);
    if( boolopt('nothing', $options) ) {
      $phaseList = array_filter($phaseList, create_function('$v', 'return !preg_match("/^(pre|post)-/",$v);'));
    }
    if( boolopt('nopre', $options) ) {
      $phaseList = array_filter($phaseList, create_function('$v', 'return !preg_match("/^pre-/",$v);'));
    }
    if( boolopt('nopost', $options) ) {
      $phaseList = array_filter($phaseList, create_function('$v', 'return !preg_match("/^post-/",$v);'));
    }
    
    foreach( $phaseList as $phaseName ) {
      echo sprintf("Doing '%s' of module '%s'.\n", $phaseName, $module->name);
      
      if( $phaseName == 'unpack' ) {
	echo sprintf("Unpacking module '%s'... ", $module->name);
	$ret = $module->unpack($context->root);
	if( $ret === false ) {
	  error_log(sprintf("Error: could not unpack module '%s' in '%s': %s", $module->name, $context->root, $module->errorMessage));
	  return 1;
	}
	echo sprintf("[%sOK%s]\n", fg_green(), color_reset());
      } else {
	$phase = $module->getPhase($phaseName);
	$processList = $phase->getProcessList();
	
	foreach( $processList as $process ) {

	  while( true ) {
	    echo sprintf("Running '%s'... ", $process->label);
	    echo fg_yellow();
	    $exec = $process->execute();
	    echo color_reset();
	    if( $exec['ret'] === false ) {
	      echo sprintf("\nError: process '%s' returned with error: %s%s%s\n", $process->label, fg_red(), $exec['output'], color_reset());
	      $ret = param_ask("(R)etry, (c)continue or (a)bort", "R/c/a", "R");
	      if( preg_match('/^a.*$/i', $ret) ) {
		echo sprintf("[%sABORTED%s] (%s)\n", fg_red(), color_reset(), $exec['output']);
		return 1;
	      }
	      if( preg_match('/^(c.*)$/i', $ret) ) {
		echo sprintf("[%sSKIPPED%s] (%s)\n", fg_blue(), color_reset(), $exec['output']);
		break;
	      }
	    } else {
	      echo sprintf("[%sOK%s]\n", fg_green(), color_reset());
	      break;
	    }
	  }
	}
      }
    }
    
    /**
     * set status to 'installed'
     */
    if( $type == 'upgrade' ) {
      $ret = $context->removeModuleInstalled($module->name);
      if( $ret === false ) {
	error_log(sprintf("Error: Could not remove old installed module '%s': %s", $module->name, $context->errorMessage));
	return 1;
      }
    }
    $ret = $module->setStatus('installed');
    if( $ret === false ) {
      error_log(sprintf("Error: Could not set installed status on module '%s': %s", $module->name, $module->errorMessage));
      return 1;
    }
    $module->cleanupDownload();

    /**
     * wstart
     */
    $ret = $context->wstart();
    
    array_push($downloaded, $module);
  }
  
  echo sprintf("\nDone.\n\n");

  return 0;
}

/**
 * wiff context <ctxName> module upgrade
 */
function wiff_context_module_upgrade(&$context, &$argv) {
  $options = parse_argv_options($argv);

  $modName = array_shift($argv);

  if( is_file($modName) ) {
    return wiff_context_module_upgrade_local($context, $options, $modName, $argv);
  } else {
    return wiff_context_module_upgrade_remote($context, $options, $modName, $argv);
  }

  return 0;
}

function wiff_context_module_upgrade_local(&$context, &$options, &$pkgName, &$argv) {
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

  $tmpMod = $context->loadModuleFromPackage($tmpfile);
  if( $tmpMod === false ) {
    error_log(sprintf("Error: could not load module '%s': %s\n", $tmpfile, $context->errorMessage));
    return 1;
  }

  $existingModule = $context->getModuleInstalled($tmpMod->name);
  if( $existingModule !== false ) {
    $cmp = $context->cmpVersionReleaseAsc($tmpMod->version, $tmpMod->release, $existingModule->version, $existingModule->release);
    if( $cmp <= 0 ) {
      echo sprintf("A module '%s' with version '%s-%s' already exists.\n", $existingModule->name, $existingModule->version, $existingModule->release);
      if( !boolopt('force', $options) ) {
	return 0;
      }
    }
  }
  unset($existingModule);

  $tmpMod = $context->importArchive($tmpfile, 'downloaded');
  if( $tmpMod === false ) {
    error_log(sprintf("Error: could not import module '%s': %s\n", $tmpfile, $context->errorMessage));
    return 1;
  }

  $depList = $context->getLocalModuleDependencies($tmpfile);
  if( $depList === false ) {
    error_log(sprintf("Error: could not get dependencies for '%s': %s\n", $tmpfile, $context->errorMessage));
    return 1;
  }

  if( count($depList) > 1 ) {
    echo sprintf("Will (u)pgrade, or (i)nstall, the following packages:\n");
    foreach( $depList as $module ) {
      if( $module->needphase == '' ) {
	$module->needphase = 'upgrade';
      }
      $op = '(i)';
      if( $module->needphase == 'upgrade' ) {
	$op = '(u)';
      } else if( $module->needphase == 'replaced' ) {
	$op = '(r)';
      }
      echo sprintf("- %s-%s-%s %s\n", $module->name, $module->version, $module->release, $op);
    }
    $ret = param_ask("Proceed with upgrade", "Y/n", "Y");
    if( !preg_match('/^(y|yes|)$/i', $ret) ) {
      return 0;
    }
  }

  return wiff_context_module_install_deplist($context, $options, $argv, $depList, 'upgrade');
}

function wiff_context_module_upgrade_remote(&$context, &$options, &$modName, &$argv) {
  require_once('lib/Lib.System.php');

  $tmpMod = $context->getModuleAvail($modName);
  if( $tmpMod === false ) {
    error_log(sprintf("Error: could not find a module named '%s'!", $modName));
    return 1;
  }

  $existingModule = $context->getModuleInstalled($modName);
  if( $existingModule !== false ) {
    $cmp = $context->cmpVersionReleaseAsc($tmpMod->version, $tmpMod->release, $existingModule->version, $existingModule->release);
    if( $cmp <= 0 ) {
      echo sprintf("A module '%s' with version '%s-%s' already exists.\n", $existingModule->name, $existingModule->version, $existingModule->release);
      if( !boolopt('force', $options) ) {
	return 0;
      }
    }
  }
  unset($existingModule);
  unset($tmpMod);

  $depList = $context->getModuleDependencies(array($modName));
  if( $depList === false ) {
    error_log(sprintf("Error: could not find a module named '%s'!", $modName));
    return 1;
  }

  if( count($depList) > 1 ) {
    echo sprintf("Will upgrade (or install) the following packages:\n");
    foreach( $depList as $module ) {
      if( $module->needphase == '' ) {
	$module->needphase = 'upgrade';
      }
      echo sprintf("- %s-%s-%s %s\n", $module->name, $module->version, $module->release, ($module->needphase=='upgrade'?'(u)':'(i)'));
    }
    $ret = param_ask("Proceed with upgrade", "Y/n", "Y");
    if( !preg_match('/^(y|yes|)$/i', $ret) ) {
      return 0;
    }
  }

  return wiff_context_module_install_deplist($context, $options, $argv, $depList, 'upgrade');
}

/**
 * wiff context <ctxName> module extract
 */
function wiff_context_module_extract(&$context, &$argv) {
  return 0;
}

function wiff_context_module_list(&$context, &$argv) {
  $op = array_shift($argv);

  switch($op) {
  case 'help':
    return wiff_context_module_list_help($context, $argv);
    break;
  case 'installed':
    return wiff_context_module_list_installed($context, $argv);
    break;
  case 'upgrade':
    return wiff_context_module_list_upgrade($context, $argv);
    break;
  case 'available':
    return wiff_context_module_list_available($context, $argv);
    break;
  default:
    return wiff_context_module_list_help($context, $argv);
  }

  return 0;
}

function wiff_context_module_list_help(&$context, &$argv) {
  echo "\n";
  echo "Usage\n";
  echo "-----\n";
  echo "\n";
  echo "  wiff context <context-name> module list installed|available|upgrade\n";
  echo "\n";
  return 0;
}

function wiff_context_module_list_upgrade(&$context, &$argv) {
  $options = parse_argv_options($argv);

  $installedList = $context->getInstalledModuleList();
  if( $installedList === false ) {
    error_log(sprintf("Error: could not get list of installed modules: %s", $context->errorMessage));
    return 1;
  }

  if( count($installedList) <= 0 ) {
    echo sprintf("Found no modules...\n");
    return 0;
  }

  if( boolopt('pretty', $options) ) {
    echo sprintf("%-32s   %-16s   %-16s\n", "Name", "Current", "Latest");
    echo sprintf("%-32s---%-16s---%-16s\n", str_repeat('-', 32), str_repeat('-', 16), str_repeat('-', 16));
  }
  foreach( $installedList as $module ) {
    $availMod = $context->getModuleAvail($module->name);
    if( $availMod === false ) {
      continue;
    } else {
      $cmp = $context->cmpVersionReleaseAsc($module->version, $module->release, $availMod->version, $availMod->release);
      if( $cmp < 0 ) {
	if( boolopt('pretty', $options) ) {
	  echo sprintf("%-32s   %-16s   %-16s\n", $module->name, sprintf("%s-%s", $module->version, $module->release), sprintf("%s-%s", $availMod->version, $availMod->release));
	} else {
	  echo sprintf("%s (%s-%s)\n", $module->name, $availMod->version, $availMod->release);
	}
      }
    }
  }

  return 0;
}

function wiff_context_module_list_installed(&$context, &$argv) {
  $options = parse_argv_options($argv);

  $installedList = $context->getInstalledModuleList();
  if( $installedList === false ) {
    error_log(sprintf("Error: could not get list of installed modules: %s", $context->errorMessage));
    return 1;
  }

  if( count($installedList) <= 0 ) {
    echo sprintf("Found no modules...\n");
    return 0;
  }

  if( boolopt('pretty', $options) ) {
    echo sprintf("%-32s   %-16s\n", "Name", "Version");
    echo sprintf("%-32s---%-16s\n", str_repeat('-', 32), str_repeat('-', 16));
  }
  foreach( $installedList as $module ) {
    if( boolopt('pretty', $options) ) {
      echo sprintf("%-32s   %-16s\n", $module->name, sprintf("%s-%s", $module->version, $module->release));
    } else {
      echo sprintf("%s (%s-%s)\n", $module->name, $module->version, $module->release);
    }
  }

  return 0;
}

function wiff_context_module_list_available(&$context, &$argv) {
  $options = parse_argv_options($argv);

  $availList = $context->getAvailableModuleList();
  if( $availList === false ) {
    error_log(sprintf("Error: could not get list of available modules: %s", $context->errorMessage));
    return 1;
  }

  if( count($availList) <= 0 ) {
    echo sprintf("Found no modules...\n");
    return 0;
  }

  if( boolopt('pretty', $options) ) {
    echo sprintf("%-32s   %-16s\n", "Name", "Version");
    echo sprintf("%-32s---%-16s\n", str_repeat('-', 32), str_repeat('-', 16));
  }
  foreach( $availList as $module ) {
    if( boolopt('pretty', $options) ) {
      echo sprintf("%-32s   %-16s\n", $module->name, sprintf("%s-%s", $module->version, $module->release));
    } else {
      echo sprintf("%s (%s-%s)\n", $module->name, $module->version, $module->release);
    }
  }

  return 0;
}

/**
 * wiff context <ctxName> param
 */
function wiff_context_param(&$context, &$argv) {
  $op = array_shift($argv);

  switch($op) {
  case 'show':
    return wiff_context_param_show($context, $argv);
    break;
  case 'get':
    return wiff_context_param_get($context, $argv);
    break;
  case 'set':
    return wiff_context_param_set($context, $argv);
    break;
  case 'help':
    return wiff_context_param_help($context, $argv);
    break;
  default:
    return wiff_context_param_help($context, $argv);
    break;
  }

  return 0;
}

function wiff_context_param_help(&$context, &$argv) {
  echo "\n";
  echo "Usage\n";
  echo "-----\n";
  echo "\n";
  echo "  wiff context <context-name> param show [<module-name>]\n";
  echo "  wiff context <context-name> param get <module-name>:<param-name>\n";
  echo "  wiff context <context-name> param set <module-name>:<param-name> <param-value>\n";
  echo "\n";
  return 0;
}

function wiff_context_param_show(&$context, &$argv) {
  $showList = array();

  while( $modName = array_shift($argv) ) {
    $module = $context->getModuleInstalled($modName);
    if( $module === false ) {
      continue;
    }
    array_push($showList, $module);
  }

  if( count($showList) <= 0 ) {
    $showList = $context->getInstalledModuleList();
  }

  foreach( $showList as $module ) {
    $paramList = $module->getParameterList();
    if( $paramList === false ) {
      continue;
    }
    foreach( $paramList as $param ) {
      echo sprintf("%s:%s = %s\n", $module->name, $param->name, $param->value);
    }
  }

  return 0;
}
    
function wiff_context_param_get(&$context, &$argv) {
  $modParam = array_shift($argv);
  if( $modParam === null ) {
    error_log(sprintf("Error: missing module-name:param-name."));
    return 1;
  }

  $m = array();
  if( !preg_match('/^([a-zA-Z0-9_-]+):([a-zA-Z0-9_-]+)$/', $modParam, $m) ) {
    error_log(sprintf("Error: malformed module-name:param-name specifier '%s'.", $modParam));
    return 1;
  }

  $modName = $m[1];
  $paramName = $m[2];

  $module = $context->getModuleInstalled($modName);
  if( $module === false ) {
    error_log(sprintf("Error: could not get module '%s': %s", $modName, $context->errorMessage));
    return 1;
  }

  $parameter = $module->getParameter($paramName);
  if( $parameter === false ) {
    error_log(sprintf("Error: could not get parameter '%s' for module '%s': %s", $paramName, $modName, $module->errorMessage));
    return 1;
  }

  echo sprintf("%s:%s = %s\n", $modName, $paramName, $parameter->value);

  return 0;
}

function wiff_context_param_set(&$context, &$argv) {
  $modParam = array_shift($argv);
  if( $modParam === null ) {
    error_log(sprintf("Error: missing module-name:param-name."));
    return 1;
  }

  $paramValue = array_shift($argv);
  if( $paramValue === null ) {
    error_log(sprintf("Error: missing param-value."));
    return 1;
  }

  $m = array();
  if( !preg_match('/^([a-zA-Z0-9_-]+):([a-zA-Z0-9_-]+)$/', $modParam, $m) ) {
    error_log(sprintf("Error: malformed module-name:param-name specifier '%s'.", $modParam));
    return 1;
  }

  $modName = $m[1];
  $paramName = $m[2];

  $module = $context->getModuleInstalled($modName);
  if( $module === false ) {
    error_log(sprintf("Error: could not get module '%s'.", $modName));
    return 1;
  }

  $parameter = $module->getParameter($paramName);
  if( $parameter === false ) {
    error_log(sprintf("Error: could not get parameter '%s' for module '%s': %s", $paramName, $modName, $module->errorMessage));
    return 1;
  }

  $parameter->value = $paramValue;
  $ret = $module->storeParameter($parameter);
  if( $ret === false ) {
    error_log(sprintf("Error: could not set paremter '%s' for module '%s': %s", $paramName, $modName, $module->errorMessage));
    return 1;
  }

  $parameter = $module->getParameter($paramName);
  if( $parameter === false ) {
    error_log(sprintf("Error: could not get back parameter '%s' for module '%s': %s", $paramName, $modNAme, $module->errorMessage));
    return 1;
  }

  echo sprintf("%s:%s = %s\n", $modName, $paramName, $parameter->value);

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
  while( count($argv) > 0 && preg_match('/^--/', $argv[0]) ) {
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

function boolopt($opt, &$options) {
  if( array_key_exists($opt, $options) && $options[$opt] ) {
    return true;
  }
  return false;
}

function param_ask($prompt, $choice, $default) {
  echo sprintf("%s ? [%s] ", $prompt, $choice);
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

function license_ask($moduleName, $licenseName, $license) {
  $licStart = sprintf("=== License agreement for module '%s' ===\n", $moduleName);
  $licSub   = sprintf("License: %s\n", $licenseName);
  $licSep   = sprintf("%s", str_repeat("-", 72));

  $licenseLines = preg_split("/\r?\n/", $license);

  echo $licStart;
  echo $licSub;
  echo $licSep."\n";

  $max_lines = getenv('LINES');
  if( ! is_numeric($max_lines) || $max_lines <= 6 ) {
    $max_lines = 20;
  }
  $max_lines -= 5;

  $ans = "";
  while( true ) {
    $lines = array_splice($licenseLines, 0, $max_lines);
    echo join("\n", $lines);
    if( count($lines) < $max_lines ) {
      break;
    }
    echo "\n";
    $ans = param_ask("--- View next page ---", "press enter to view next page", "");
    if( preg_match("/^(q|quit|end)$/i", $ans) ) {
      break;
    }
  }
  echo "\n".$licSep."\n";

  while( true ) {
    $ans = param_ask("Do you agree", "y/n", "");
    if( preg_match("/^(y|yes|oui)/i", $ans) ) {
      return 'yes';
    }
    if( preg_match("/^(n|no|non)/i", $ans) ) {
      return 'no';
    }
  }

  return 'no';
}

/**
 * ANSI colors
 */
function fg_black() {
  return chr(0x1b).'[30m';
}

function bg_black() {
  return chr(0x1b).'[40m';
}

function fg_white() {
  return chr(0x1b).'[37m';
}

function bg_white() {
  return chr(0x1b).'[47m';
}

function fg_red() {
  return chr(0x1b).'[31m';
}

function bg_red() {
  return chr(0x1b).'[41m';
}

function fg_green() {
  return chr(0x1b).'[32m';
}

function bg_green() {
  return chr(0x1b).'[42m';
}

function fg_blue() {
  return chr(0x1b).'[34m';
}

function bg_blue() {
  return chr(0x1b).'[44m';
}

function fg_yellow() {
  return chr(0x1b).'[33m';
}

function bg_yellow() {
  return chr(0x1b).'[43m';
}

function color_reset() {
  return chr(0x1b).'[0m';
}

/**
 * change UID to the owner of the wiff script
 */
function setuid_wiff($path) {
  $stat = stat($path);
  if( $stat === false ) {
    error_log(sprintf("Error: could not stat '%s'!", $path));
    return false;
  }

  $uid = posix_getuid();

  $wiff_uid = $stat['uid'];
  $wiff_gid = $stat['gid'];

  if( $uid != $wiff_uid ) {
    $ret = posix_setgid($wiff_gid);
    if( $ret === false ) {
      error_log(sprintf("Error: could not setgid to gid '%s'.\n", $wiff_gid));
      return false;
    }
    $ret = posix_setuid($wiff_uid);
    if( $ret === false ) {
      error_log(sprintf("Error: could not setuid to uid '%s'.\n", $wiff_uid));
      return false;
    }
  }

  return true;
}

?>