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

set_include_path(get_include_path().PATH_SEPARATOR.getcwd().'/include');
putenv('WIFF_ROOT = '.getcwd());

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
    if (!$data)
    {
        echo "{error:'".$error."',data:'',success:'false'}";
    } else
    {
        echo "{error:'".$error."',data:".$data.",success:'true'}";
    }
    exit ();
}


$wiff = WIFF::getInstance();

// Request if installer need update
if ( isset ($_POST['needUpdate']))
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
if ( isset ($_POST['update']))
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
if ( isset ($_POST['getRepoList']))
{
    $repoList = $wiff->getRepoList();
    if (!$wiff->errorMessage)
    {
        answer(json_encode($repoList));
    } else
    {
        answer(null, $wiff->errorMessage);
    }
}

// Request to get context list
if ( isset ($_POST['getContextList']))
{
    $contextList = $wiff->getContextList();
	
    if (!$wiff->errorMessage)
    {
        answer(json_encode($contextList));
    } else
    {
        answer(null, $wiff->errorMessage);
    }
}

// Request to create new context
if ( isset ($_POST['createContext']))
{
    $context = $wiff->createContext($_POST['name'], $_POST['root'], $_POST['desc']);

    if (!$wiff->errorMessage)
    {
		
        $repoList = $wiff->getRepoList();

        foreach ($repoList as $repo)
        {
            $postcode = 'repo-'.$repo->name;
			
			str_replace('.','_',$postcode); // . characters in variables are replace by _ characters during POST requesting

            if ( isset ($_POST[$postcode]))
            {
                $context->activateRepo($repo->name);
                if ($context->errorMessage)
                {
                    answer(null, $context->errorMessage);
                }
            }
        }

        answer(json_encode($context));

    } else
    {
        answer(null, $wiff->errorMessage);
    }
}

// Request to get dependency module list for a module
if ( isset ($_POST['context']) && isset ($_POST['module']) && isset ($_POST['getModuleDependencies']))
{
	$context = $wiff->getContext($_POST['context']);
	
	$dependencyList = $context->getModuleDependencies($_POST['module']);
	
	answer(json_encode($dependencyList));
	
}

// Request to download module to temporary dir
if ( isset ($_POST['context']) && isset ($_POST['module']) && isset ($_POST['download']) )
{
	$context = $wiff->getContext($_POST['context']);
	
	$module = $context->getModuleAvail($_POST['module']);
	
	if ($module->download())
	{
		answer(true);
	} else {
		answer(null,$module->errorMessage);
	}
	
}

// Request to unpack module in context
if ( isset ($_POST['context']) && isset ($_POST['module']) && isset ($_POST['unpack']) )
{
	$context = $wiff->getContext($_POST['context']);
	
	$module = $context->getModule($_POST['module']);
	
	if ($module->unpack($context->root))
	{
		answer(true);
	} else {
		answer(null,$module->errorMessage);
	}
	
}


// Request to activate a repo list in context
// TODO Unused
if ( isset ($_POST['context']) && isset ($_POST['activateRepo']) && isset ($_POST['repo']))
{
    $context = $wiff->getContext($_POST['context']);

    if (!$wiff->errorMessage)
    {
        foreach ($_POST['repo'] as $repo)
        {
            $context->activateRepo($repo);
            if (!$context->errorMessage)
            {
                answer(null, $context->errorMessage);
            }
        }

        answer(json_encode($context));

    } else
    {
        answer(null, $wiff->errorMessage);
    }

}

// Request to deactivate a repo list in context
// TODO Unused
if ( isset ($_POST['context']) && isset ($_POST['deactivateRepo']) && isset ($_POST['repo']))
{
    $context = $wiff->getContext($_POST['context']);

    if (!$wiff->errorMessage)
    {
        foreach ($_POST['repo'] as $repo)
        {
            $context->deactivateRepo($repo);
            if (!$context->errorMessage)
            {
                answer(null, $context->errorMessage);
            }
        }

        answer(json_encode($context));

    } else
    {
        answer(null, $wiff->errorMessage);
    }

}

// Request to get a context installed module list
if ( isset ($_POST['context']) && isset ($_POST['getInstalledModuleList']))
{
    $context = $wiff->getContext($_POST['context']);

    if (!$wiff->errorMessage)
    {

        $moduleList = $context->getInstalledModuleList(true);
        if ($context->errorMessage)
        {
            answer(null, $context->errorMessage);
        }

        answer(json_encode($moduleList));

    } else
    {
        answer(null, $wiff->errorMessage);
    }

}

// Request to get a context available module list
if ( isset ($_POST['context']) && isset ($_POST['getAvailableModuleList']))
{
    $context = $wiff->getContext($_POST['context']);

    if (!$wiff->errorMessage)
    {

        $moduleList = $context->getAvailableModuleList(true);
        if ($context->errorMessage)
        {
            answer(null, $context->errorMessage);
        }

        answer(json_encode($moduleList));

    } else
    {
        answer(null, $wiff->errorMessage);
    }

}

// Request to get phase list for a given operation
if ( isset ($_POST['context']) && isset ($_POST['module']) && isset ($_POST['getPhaseList']) && isset ($_POST['operation']))
{
    $context = $wiff->getContext($_POST['context']);

    $module = $context->getModule($_POST['module']);
	
	if(!$module) // If no module was found in installed modules by previous method, then try to get module from available modules
	{
		$module = $context->getModuleAvail($_POST['module']);
	}

    $phaseList = $module->getPhaseList($_POST['operation']);

    answer(json_encode($phaseList));
}

// Request to get process list for a given phase
if ( isset ($_POST['context']) && isset ($_POST['module']) && isset ($_POST['phase']) && isset ($_POST['getProcessList']))
{
    $context = $wiff->getContext($_POST['context']);

    $module = $context->getModule($_POST['module']);

    $phase = $module->getPhase($_POST['phase']);

    $processList = $phase->getProcessList();

    answer(json_encode($processList));
}

// Request to execute process
if ( isset ($_POST['context']) && isset ($_POST['module']) && isset ($_POST['phase']) && isset ($_POST['process']) && isset ($_POST['execute']))
{
    $context = $wiff->getContext($_POST['context']);

    $module = $context->getModule($_POST['module']);

    $phase = $module->getPhase($_POST['phase']);

    $process = $phase->getProcess(intval($_POST['process']));

    $result = $process->execute();
    if ($result)
    {
        answer($result);
    } else
    {
        answer(null, $process->help);
    }

}

// Request to get module parameters
if ( isset ($_POST['context']) && isset ($_POST['module']) && isset ($_POST['getParameterList']))
{
    $context = $wiff->getContext($_POST['context']);

    $module = $context->getModule($_POST['module']);
	if(!$module)
	{
		$module = $context->getModuleAvail($_POST['module']);
	}

    $parameterList = $module->getParameterList();

	answer(json_encode($parameterList));

}

// Request to save module parameters
if (isset ($_POST['context']) && isset ($_POST['module']) && isset ($_POST['storeParameter']))
{
	$context = $wiff->getContext($_POST['context']);
	
	$module = $context->getModule($_POST['module']);
	if(!$module)
	{
		$module = $context->getModuleAvail($_POST['module']);
	}
	
	$parameterList = $module->getParameterList();
	
	foreach ($parameterList as $parameter)
	{
		if(isset($_POST[$parameter->name]))
		{
			$parameter->value = $_POST[$parameter->name];
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