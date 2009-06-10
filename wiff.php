<?php
/**
 * Web Installer for Freedom
 * XHR PHP Portal
 * @author Clément Laballe
 *
 * PHP Script called by Web Installer asynchronous requests.
 *
 */
 
header('Content-type: text/html; charset=UTF-8');

set_include_path(get_include_path().PATH_SEPARATOR.getcwd().DIRECTORY_SEPARATOR.'include');

ini_set('error_reporting', 'E_ALL & ~E_NOTICE');
ini_set('display_errors', 'Off');
ini_set('max_execution_time', 3600);

putenv('WIFF_ROOT='.getcwd());

function __autoload($class_name)
{
    require_once 'class/Class.'.$class_name.'.php';
}

/**
 * Format answer for javascript
 * Success attribute is used for recognition by ExtJS
 * @return string formatted
 * @param string $data
 * @param string $error[optional]
 */
function answer($data, $error = null)
{
    if( $data === null )
    {
      // echo "{error:'".addslashes($error)."',data:'',success:'false'}";
      $answer = new JSONAnswer($data, $error, false);
      echo $answer->encode();
    } else
    {
      // echo "{error:'".addslashes($error)."',data:'".addslashes($data)."',success:'true'}";
      $answer = new JSONAnswer($data, $error, true);
      echo $answer->encode();
    }
    exit ();
}


$wiff = WIFF::getInstance();

// Request if installer need update
if ( isset ($_REQUEST['needUpdate']))
{
    $needUpdate = $wiff->needUpdate();
    if (!$wiff->errorMessage)
    {
        answer($needUpdate);
    } else
    {
        answer(null, $wiff->errorMessage);
    }
}

// Request to update installer
if ( isset ($_REQUEST['update']))
{
    $wiff->update();
    if (!$wiff->errorMessage)
    {
        answer(true);
    } else
    {
        answer(null, $wiff->errorMessage);
    }
}

// Request to get global repository list
if ( isset ($_REQUEST['getRepoList']))
{
    $repoList = $wiff->getRepoList();
    if (!$wiff->errorMessage)
    {
        // answer(json_encode($repoList));
        answer($repoList);
    } else
    {
        answer(null, $wiff->errorMessage);
    }
}

// Request to get context list
if ( isset ($_REQUEST['getContextList']))
{
    $contextList = $wiff->getContextList();
	
    if (!$wiff->errorMessage)
    {
        // answer(json_encode($contextList));
        answer($contextList);
    } else
    {
        answer(null, $wiff->errorMessage);
    }
}

// Request to create new context
if ( isset ($_REQUEST['createContext']))
{
    $context = $wiff->createContext($_REQUEST['name'], $_REQUEST['root'], $_REQUEST['desc']);

    if (!$wiff->errorMessage)
    {
		
        $repoList = $wiff->getRepoList();

        foreach ($repoList as $repo)
        {
            $postcode = 'repo-'.$repo->name;
			
			str_replace('.','_',$postcode); // . characters in variables are replace by _ characters during POST requesting

            if ( isset ($_REQUEST[$postcode]))
            {
                $context->activateRepo($repo->name);
                if ($context->errorMessage)
                {
                    answer(null, $context->errorMessage);
                }
            }
        }

        // answer(json_encode($context));
        answer($context);

    } else
    {
        answer(null, $wiff->errorMessage);
    }
}

// Request to get dependency module list for a module
if ( isset ($_REQUEST['context']) && isset ($_REQUEST['module']) && isset ($_REQUEST['getModuleDependencies']))
{
	$context = $wiff->getContext($_REQUEST['context']);
	
	$dependencyList = $context->getModuleDependencies($_REQUEST['module']);
	
	// answer(json_encode($dependencyList));
	answer($dependencyList);
	
}

// Request to download module to temporary dir
if ( isset ($_REQUEST['context']) && isset ($_REQUEST['module']) && isset ($_REQUEST['download']) )
{
	$context = $wiff->getContext($_REQUEST['context']);
	
	$module = $context->getModuleAvail($_REQUEST['module']);
	
	if ($module->download())
	{
		answer(true);
	} else {
		answer(null,$module->errorMessage);
	}
	
}

// Request to unpack module in context
if ( isset ($_REQUEST['context']) && isset ($_REQUEST['module']) && isset ($_REQUEST['unpack']) )
{
	$context = $wiff->getContext($_REQUEST['context']);
	
	$module = $context->getModule($_REQUEST['module']);
	
	if ($module->unpack($context->root))
	{
		answer(true);
	} else {
		answer(null,$module->errorMessage);
	}
	
}


// Request to activate a repo list in context
// TODO Unused
if ( isset ($_REQUEST['context']) && isset ($_REQUEST['activateRepo']) && isset ($_REQUEST['repo']))
{
    $context = $wiff->getContext($_REQUEST['context']);

    if (!$wiff->errorMessage)
    {
        foreach ($_REQUEST['repo'] as $repo)
        {
            $context->activateRepo($repo);
            if (!$context->errorMessage)
            {
                answer(null, $context->errorMessage);
            }
        }

        // answer(json_encode($context));
        answer($context);

    } else
    {
        answer(null, $wiff->errorMessage);
    }

}

// Request to deactivate a repo list in context
// TODO Unused
if ( isset ($_REQUEST['context']) && isset ($_REQUEST['deactivateRepo']) && isset ($_REQUEST['repo']))
{
    $context = $wiff->getContext($_REQUEST['context']);

    if (!$wiff->errorMessage)
    {
        foreach ($_REQUEST['repo'] as $repo)
        {
            $context->deactivateRepo($repo);
            if (!$context->errorMessage)
            {
                answer(null, $context->errorMessage);
            }
        }

        // answer(json_encode($context));
        answer($context);

    } else
    {
        answer(null, $wiff->errorMessage);
    }

}

// Request to get a context installed module list
if ( isset ($_REQUEST['context']) && isset ($_REQUEST['getInstalledModuleList']))
{
    $context = $wiff->getContext($_REQUEST['context']);

    if (!$wiff->errorMessage)
    {

        $moduleList = $context->getInstalledModuleList(true);
        if ($context->errorMessage)
        {
            answer(null, $context->errorMessage);
        }

        // answer(json_encode($moduleList));
        answer($moduleList);

    } else
    {
        answer(null, $wiff->errorMessage);
    }

}

// Request to get a context available module list
if ( isset ($_REQUEST['context']) && isset ($_REQUEST['getAvailableModuleList']))
{
    $context = $wiff->getContext($_REQUEST['context']);

    if (!$wiff->errorMessage)
    {

        $moduleList = $context->getAvailableModuleList(true);
        if ($context->errorMessage)
        {
            answer(null, $context->errorMessage);
        }

        // answer(json_encode($moduleList));
        answer($moduleList);

    } else
    {
        answer(null, $wiff->errorMessage);
    }

}

// Request to get phase list for a given operation
if ( isset ($_REQUEST['context']) && isset ($_REQUEST['module']) && isset ($_REQUEST['getPhaseList']) && isset ($_REQUEST['operation']))
{
    $context = $wiff->getContext($_REQUEST['context']);

    $module = $context->getModule($_REQUEST['module']);
	
	if(!$module) // If no module was found in installed modules by previous method, then try to get module from available modules
	{
		$module = $context->getModuleAvail($_REQUEST['module']);
	}

    $phaseList = $module->getPhaseList($_REQUEST['operation']);

    // answer(json_encode($phaseList));
    answer($phaseList);
}

// Request to get process list for a given phase
if ( isset ($_REQUEST['context']) && isset ($_REQUEST['module']) && isset ($_REQUEST['phase']) && isset ($_REQUEST['getProcessList']))
{
    $context = $wiff->getContext($_REQUEST['context']);
    if( $context === false ) {
      error_log(__FUNCTION__." ".$wiff->errorMessage);
      answer(null, $wiff->errorMessage);
    } 

    $module = $context->getModule($_REQUEST['module']);
    if( $module === false ) {
      error_log(__FUNCTION__." ".$context->errorMessage);
      answer(null, $context->errorMessage);
    }

    $phase = $module->getPhase($_REQUEST['phase']);
    $processList = $phase->getProcessList();

    // answer(json_encode($processList));
    answer($processList);
}

// Request to execute process
if ( isset ($_REQUEST['context']) && isset ($_REQUEST['module']) && isset ($_REQUEST['phase']) && isset ($_REQUEST['process']) && isset ($_REQUEST['execute']))
{
  $context = $wiff->getContext($_REQUEST['context']);
  if( $context === false ) {
    $answer = new JSONAnswer(null, sprintf("Could not get context '%s'.", $_REQUEST['context']), false);
    echo $answer->encode();
    exit( 1 );
  }
  
  $module = $context->getModule($_REQUEST['module']);
  if( $module === false ) {
    $answer = new JSONAnswer(null, sprintf("Could not get module '%s' in context '%s'.", $_REQUEST['module'], $_REQUEST['context']), false);
    echo $answer->encode();
    exit( 1 );
  }
  
  $phase = $module->getPhase($_REQUEST['phase']);
  $process = $phase->getProcess(intval($_REQUEST['process']));
  if( $process === null ) {
    $answer = new JSONAnswer(null, sprintf("Could not get process '%s' for phase '%s' of module '%s' in context '%s'.", $_REQUEST['process'], $_REQUEST['phase'], $_REQUEST['module'], $_REQUEST['context']), false);
    echo $answer->encode();
    exit( 1 );
  }
  
  $result = $process->execute();
  
  if( $result['ret'] === true ) {
    $module->setErrorStatus('');
    $answer = new JSONAnswer(null, $result['output'], true);
    echo $answer->encode();
    exit( 1 );
  }
  
  $module->setErrorStatus($phase->name);
  $answer = new JSONAnswer(null, $result['output'], false);
  echo $answer->encode();
  exit( 1 );
}

// Request to get module parameters
if ( isset ($_REQUEST['context']) && isset ($_REQUEST['module']) && isset ($_REQUEST['getParameterList']))
{
    $context = $wiff->getContext($_REQUEST['context']);

    $module = $context->getModule($_REQUEST['module']);
	if(!$module)
	{
		$module = $context->getModuleAvail($_REQUEST['module']);
	}

    $parameterList = $module->getParameterList();

        // answer(json_encode($parameterList));
	answer($parameterList);

}

// Request to save module parameters
if (isset ($_REQUEST['context']) && isset ($_REQUEST['module']) && isset ($_REQUEST['storeParameter']))
{
	$context = $wiff->getContext($_REQUEST['context']);
	
	$module = $context->getModule($_REQUEST['module']);
	if(!$module)
	{
		$module = $context->getModuleAvail($_REQUEST['module']);
	}
	
	$parameterList = $module->getParameterList();
	
	foreach ($parameterList as $parameter)
	{
		if(isset($_REQUEST[$parameter->name]))
		{
			$parameter->value = $_REQUEST[$parameter->name];
			$module->storeParameter($parameter);
		}
	}
	
	answer(true);
	
}

// Request to run wstop in context
if( isset($_REQUEST['context']) && isset($_REQUEST['wstop']) ) {
  $context = $wiff->getContext($_REQUEST['context']);
  if( $context === false ) {
    $answer = new JSONAnswer(null, sprintf("Error getting context '%s'!", $_REQUEST['context']), true);
    echo $answer->encode();
    exit( 1 );
  }

  $context->wstop();

  answer(true);
}

// Request to run wstart in context
if( isset($_REQUEST['context']) && isset($_REQUEST['wstart']) ) {
  $context = $wiff->getContext($_REQUEST['context']);
  if( $context === false ) {
    $answer = new JSONAnswer(null, sprintf("Error getting context '%s'!", $_REQUEST['context']), true);
    echo $answer->encode();
    exit( 1 );
  }

  $context->wstart();

  answer(true);
}

if (isset ($_REQUEST['context']) && isset ($_REQUEST['module']) && isset ($_REQUEST['storeParameter']))
{
	$context = $wiff->getContext($_REQUEST['context']);
	
	$module = $context->getModule($_REQUEST['module']);
	if(!$module)
	{
		$module = $context->getModuleAvail($_REQUEST['module']);
	}
	
	$parameterList = $module->getParameterList();
	
	foreach ($parameterList as $parameter)
	{
		if(isset($_REQUEST[$parameter->name]))
		{
			$parameter->value = $_REQUEST[$parameter->name];
			$module->storeParameter($parameter);
		}
	}
	
	answer(true);
	
}

// Call to get a param value
if (isset($argv))
{
	
	if(stripos($argv[1],'--getValue=') === 0)
	{
		$paramName =  substr($argv[1], 11);
	}
	
	$xml = new DOMDocument();
	$xml->load(WIFF::contexts_filepath);
	
	$xpath = new DOMXPath($xml);
	
	$parameterNode = $xpath->query(sprintf("/contexts/context[@name='%s']/parameters-value/param[@name='%s']", getenv('WIFF_CONTEXT_NAME'), $paramName))->item(0);
	if($parameterNode)
	{
	$parameterValue = $parameterNode->getAttribute('value');
	return $parameterValue ;
	} else {
		return false ;
	}
	
}

answer(null, "Unrecognized Call");

?>
