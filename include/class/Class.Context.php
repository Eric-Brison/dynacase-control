<?php

/**
 * Context Class
 */
class Context
{

    public $name;
    public $description;
    public $root;
    public $repo;

    private $errorMessage = null;

    public function __construct($name, $desc, $root, $repo)
    {
        $this->name = $name;
        $this->description = $desc;
        $this->root = $root;
        $this->repo = $repo;
        foreach ($this->repo as $repository)
        {
            $repository->context = $this;
        }
    }

    public function __set($property, $value)
    {
        $this->$property = $value;
    }

    public function __get($property)
    {
        return $this->$property;
    }

    /**
     * Activate repository for Context
     * @return boolean success
     * @param string $name repository name
     * @param string $url repository url
     */
    public function activateRepo($name)
    {
      $wiff = WIFF::getInstance();

        $paramsXml = new DOMDocument();
        $paramsXml->load($wiff->params_filepath);

        $paramsXPath = new DOMXPath($paramsXml);

        $contextsXml = new DOMDocument();
        $contextsXml->load($wiff->contexts_filepath);

        $contextsXPath = new DOMXPath($contextsXml);

        // Get this context
        $contextList = $contextsXPath->query("/contexts/context[@name='".$this->name."']");
        if ($contextList->length != 1)
        {
            // If more than one context with name
            $this->errorMessage = "Duplicate contexts with same name";
            return false;
        }

        // Add a repositories list if context does not have one
        $contextRepo = $contextsXPath->query("/contexts/context[@name='".$this->name."']/repositories");
        if ($contextRepo->length != 1)
        {
            // if repositories node does not exist, create one
            $contextList->item(0)->appendChild($contextsXml->createElement('repositories'));

        }

        // Check this repository is not already in context
        $contextRepoList = $contextsXPath->query("/contexts/context[@name='".$this->name."']/repositories/access[@name='".$name."']");
        if ($contextRepoList->length > 0)
        {
            // If more than zero repository with name
            $this->errorMessage = "Repository already activated";
            return false;
        }

        // Get repository with this name from WIFF repositories
        $wiffRepoList = $paramsXPath->query("/wiff/repositories/access[@name='".$name."']");
        if ($wiffRepoList->length != 1)
        {
            // If different than one repository with name
            $this->errorMessage = "Duplicate repository with same name";
            return false;
        }

        // Add repository to this context

        $repository = $contextsXml->importNode($wiffRepoList->item(0), true); // Node must be imported from params document.

        $repository = $contextList->item(0)->getElementsByTagName('repositories')->item(0)->appendChild($repository);

        $ret = $contextsXml->save($wiff->contexts_filepath);
        if ($ret === false)
        {
            $this->errorMessage = sprintf("Error writing file '%s'.", $wiff->contexts_filepath);
            return false;
        }

        //Update Context object accordingly
        $this->repo[] = new Repository($repository->getAttribute('name'), $repository->getAttribute('baseurl'), $repository->getAttribute('description'), $this);

        return true;

    }

    /**
     * Deactivate repository for Context
     * @return boolean success
     * @param string $name repository name
     */
    public function deactivateRepo($name)
    {
      $wiff = WIFF::getInstance();

        $xml = new DOMDocument();
        $xml->load($wiff->contexts_filepath);

        $xpath = new DOMXPath($xml);

        // Check this repository exists
        $contextRepoList = $xpath->query("/contexts/context[@name='".$this->name."']/repositories/access[@name='".$name."']");
        if ($contextRepoList->length == 1)
        {
            $contextRepo = $xpath->query("/contexts/context[@name='".$this->name."']/repositories")->item(0)->removeChild($contextRepoList->item(0));
            $xml->save($wiff->contexts_filepath);

            foreach ($this->repo as $repo)
            {
                if ($repo->name == $name)
                {
                    unset ($this->repo[array_search($repo, $this->repo)]);
                }
            }

            return true;
        } else
        {
            $this->errorMessage = sprintf("Could not find active repository '%s' in context '%s'.", $name, $this->name);
            return false;
        }

    }

    /**
     * Get Module list
     * @return array of object Module or boolean false
     */
    public function getModuleList()
    {

        $moduleList = array ();

        $availableModuleList = $this->getAvailableModuleList();
	if( $availableModuleList === false ) {
	  $this->errorMessage = sprintf("Could not get available module list.");
	  return false;
	}

        $installedModuleList = $this->getInstalledModuleList();
	if( $installedModuleList === false ) {
	  $this->errorMessage = sprintf("Could not get installed module list.");
	  return false;
	}

        $moduleList = array_merge($availableModuleList, $installedModuleList); // TODO appropriate merge

        return $moduleList;
    }

    /**
     * Get installed Module list
     * @param boolean withAvailableVersion returned objects will have last available version from Repository attribute populated
     * @return array of object Module
     */
    public function getInstalledModuleList($withAvailableVersion = false)
    {
      $wiff = WIFF::getInstance();

        $xml = new DOMDocument();
        $xml->load($wiff->contexts_filepath);

        $xpath = new DOMXPath($xml);

        $moduleList = array ();

        $moduleDom = $xpath->query("/contexts/context[@name='".$this->name."']/modules/module");

        foreach ($moduleDom as $module)
	  {
	    $mod = new Module($this, null, $module, true);
	    if( $mod->status == 'installed' ) {
	      $moduleList[] = $mod;
	    }
        }
		
		//Process for with available version option
		if($withAvailableVersion)
		{
			$availableModuleList = $this->getAvailableModuleList();
			
			foreach ($availableModuleList as $availableKey => $availableModule)
			{
				foreach ($moduleList as $moduleKey => $module)
				{
					if($availableModule->name == $module->name)
					{
						$module->availableversion = $availableModule->version ;
						$cmp = $this->cmpModuleByVersionReleaseAsc($module, $availableModule);
						if($cmp < 0)
						{
							$module->canUpdate = true ;
						}
					}
				}
			}
			
		}
		
		
		
        return $moduleList;

    }

    /**
     * Get the list of available module Objects in the repositories of the context
     * @param boolean onlyNotInstalled only return available and not installed modules
     * @return array of module Objects
     */
    public function getAvailableModuleList($onlyNotInstalled = false)
    {
        $moduleList = array ();
        foreach ($this->repo as $repository)
        {
            $repoModuleList = $repository->getModuleList();
            if ($repoModuleList === false)
            {
                $this->errorMessage = sprintf("Error fetching index for repository '%s'.", $repository->name);
                continue ;
            }
            $moduleList = $this->mergeModuleList($moduleList, $repoModuleList);
            if ($moduleList === false)
            {
                $this->errorMessage = sprintf("Error merging module list.");
                return false;
            }
        }
		
		// Process for only not installed option
		if($onlyNotInstalled)
		{
			$installedModuleList = $this->getInstalledModuleList();
			
			foreach ($installedModuleList as $installedKey => $installedModule)
			{
				foreach ($moduleList as $moduleKey => $module)
				{
					if($installedModule->name == $module->name)
					{
						unset($moduleList[$moduleKey]);
						$moduleList = array_values($moduleList);
					}
				}
			}
			
		}

        return $moduleList;
    }

    /**
     * Merge two module lists, sort and keep modules with highest version-release
     *   (kinda sort|uniq).
     * @return an array containing unique module Objects
     * @param first array of module Objects
     * @param second array of module Objects
     */
    public function mergeModuleList( & $list1, & $list2)
    {
        $tmp = array_merge($list1, $list2);
        $ret = usort($tmp, array ($this, 'cmpModuleByVersionReleaseDesc'));
        if ($ret === false)
        {
            $this->errorMessage = sprintf("Error sorting module list.");
            return false;
        }

        $seen = array ();
        $list = array ();
        foreach ($tmp as $module)
        {
            if (array_key_exists($module->name, $seen))
            {
                continue ;
            }
            array_push($list, $module);
            $seen[$module->name]++;
        }

        return $list;
    }

    /**
     * Compare (str_v1, str_r1, str_v2, str_r2) versions/releases
     * @return < 0 if v1-r1 is less than v2-r2, > 0 if v1-r1 is greater than v2-r2
     *         and 0 if they are equal
     * @param string version #1
     * @param string release #1
     * @param string version #2
     * @param string release #2
     */
    public function cmpVersionReleaseAsc($v1, $r1, $v2, $r2)
    {
        $ver1 = preg_split('/\./', $v1, 3);
        $rel1 = $r1;
        $ver2 = preg_split('/\./', $v2, 3);
        $rel2 = $r2;

        $str1 = sprintf("%03d%03d%03d%03d", $ver1[0], $ver1[1], $ver1[2], $rel1);
        $str2 = sprintf("%03d%03d%03d%03d", $ver2[0], $ver2[1], $ver2[2], $rel2);

        return strcmp($str1, $str2);
    }

    /**
     * Compare two module Objects by ascending version-release
     * @return < 0 if mod1 is less than mod2, > 0 if mod1 is greater than mod2,
     *         and 0 if they are equal
     * @param module Object 1
     * @param module Object 2
     */
    public function cmpModuleByVersionReleaseAsc( & $module1, & $module2)
    {
        return $this->cmpVersionReleaseAsc($module1->version,
        $module1->release,
        $module2->version,
        $module2->release);
    }

    /**
     * Compare two module Objects by descending version-release
     * @return > 0 if mod1 is less than mod2, < 0 if mod1 is greater than mod2,
     *         and 0 if they are equal
     * @param module Object 1
     * @param module Object 2
     */
    public function cmpModuleByVersionReleaseDesc( & $module1, & $module2)
    {
        $ret = $this->cmpModuleByVersionReleaseAsc($module1, $module2);
        if ($ret > 0)
        {
            return -1;
        } else if ($ret < 0)
        {
            return 1;
        }
        return 0;
    }

    /**
     * Get Module by name
     * @return object Module or boolean false
     * @param object $name Module name
     */
    public function getModule($name)
    {
      $wiff = WIFF::getInstance();

        $xml = new DOMDocument();
        $xml->load($wiff->contexts_filepath);

        $xpath = new DOMXPath($xml);

        $moduleDom = $xpath->query("/contexts/context[@name='".$this->name."']/modules/module[@name='".$name."']");

        if ($moduleDom->length <= 0)
        {
            $this->errorMessage = sprintf("Could not find a module named '%s' in context '%s'.", $name, $this->name);
            return false;
        }

        return new Module($this, null, $moduleDom->item(0), true);
    }

    public function getModuleAvail($name)
    {
        $modAvails = $this->getAvailableModuleList();
        if ($modAvails === false)
        {
            return false;
        }

        foreach ($modAvails as $mod)
        {
            if ($mod->name == "$name")
            {
            	$mod->context = $this ;
				
                return $mod;
            }
        }

        $this->errorMessage = sprintf("Could not find module '%s' in context '%s'.", $name, $this->name);
        return false;
    }

    /**
     * Get module dependencies from repositories indexes
     * @return array containing a list of Module objects ordered by their
     *         install order, or false in case of error
     * @param the module name
     */
    public function getModuleDependencies($name) {
      $modsAvail = $this->getAvailableModuleList();
      if ($modsAvail === false) {
	return false;
      }
      
      $module = $this->getModuleAvail($name);
      if ($module === false) {
	return false;
      }
      
      $depsList = array ();
      array_push($depsList, $module);
      
      $modMovedBy = array();
      
      $i = 0;
      while ($i < count($depsList)) {
	$mod = $depsList[$i];
	$reqList = $mod->getRequiredModules();
	
	foreach ($reqList as $req) {
	  $reqModName = $req['name'];
	  $reqModVersion = $req['version'];
	  $reqModComp = $req['comp'];
	  
	  $reqMod = $this->getModuleAvail($reqModName);
	  if( $reqMod === false ) {
	    $this->errorMessage = sprintf("Module '%s' required by '%s' could not be found in repositories.", $reqModName, $mod->name);
	    return false;
	  }
	  
	  switch($reqModComp) {
	  case '':
	    break;
	  case 'ge':
	    if ($this->cmpVersionRelease($reqModVersion, 0, $reqMod->version, 0) < 0) {
	      $this->errorMessage = sprintf("Module '%s-%s' requires '%s' >= %s, but only '%s-%s' was found on repository.");
	      return false;
	    }
	    break;
	  default:
	    $this->errorMessage = sprintf("Operator of module comparison '%s' is not yet implemented.", $reqModComp);
	    return false;
	  }

	  // Check if a version of this module is already installed
	  if ($this->moduleIsInstalledAndUpToDateWith($reqMod)) {
	    continue ;
	  }
	 
	  $pos = $this->depsListContains($depsList, $reqMod->name);
	  if( $pos < 0 ) {
	    // Add the module to the dependencies list
	    array_push($depsList, $reqMod);
	  } else if( $pos >= 0 && $pos < $i ) {
	    if( $modMovedBy[$reqMod->name][$mod->name] <= 1 ) {
	      // Move the module to the right
	      $this->moveDepToRight($depsList, $pos, $i);
	      $modMovedBy[$reqMod->name][$mod->name]++;
	      $i--;
	    }
	  }
	}
	$i++;
      }
      return $depsList;
    }
    
    /**
     * Check if a Module object with this name already exists a a list of
     * Module objects
     * @return true if the module with the given name is found, false if not found
     * @param array( Module object 1, [...], Module object N )
     */
    private function depsListContains(&$depsList, $name)
    {
      $i = 0;
      while( $i < count($depsList) ) {
	if ($depsList[$i]->name == $name) {
	  return $i;
	}
	$i++;
      }
      return -1;
    }

    /**
     * Move a module at position $pos after position $pivot
     * @return (nothing)
     * @param array of Module
     * @param position of actual module to move
     * @param position after which the module should be moved
     */
    private function moveDepToRight(&$depsList, $pos, $pivot) {
      $extractedModule = array_splice($depsList, $pos, 1);
      array_splice($depsList, $pivot, 0, $extractedModule);
    }

    /**
     * Check if a module is installed
     */
    private function moduleIsInstalled( & $module)
    {
        $installedModule = $this->getModule($module->name);
        if ($installedModule === false)
        {
            return false;
        }
        return $installedModule;
    }

    /**
     * Check if the given module Object is already installed and up-to-date
     */
    private function moduleIsInstalledAndUpToDateWith( & $targetModule)
    {
        $installedModule = $this->moduleIsInstalled($targetModule);
        if ($installedModule === false)
        {
            return false;
        }

	if( $installedModule->status != 'installed' ) {
	  return false;
	}

        $cmp = $this->cmpModuleByVersionReleaseAsc($installedModule, $targetModule);

        if ($cmp < 0)
        {
            return false;
        }
        return true;
    }

    public function getParamByName($paramName) {
      $wiff = WIFF::getInstance();

      $xml = new DOMDocument();
      $ret = $xml->load($wiff->contexts_filepath);
      if( $ret === false ) {
	$this->errorMessage = sprintf("Error opening XML file '%s'.", $wiff->contexts_filepath);
	return false;
      }

      $xpath = new DOMXPath($xml);

      $parameterNode = $xpath->query(sprintf("/contexts/context[@name='%s']/parameters-value/param[@name='%s']", $this->name, $paramName))->item(0);
      if( $parameterNode ) {
	$value = $parameterNode->getAttribute('value');
	$this->errorMessage = '';
	return $value;
      }
      $this->errorMessage = sprintf("Parameter with name '%s' not found in context '%s'.", $paramName, $this->name);
      return '';
    }

    public function wstop() {
      $wstop = sprintf("%s/wstop", $this->root);
      error_log(__CLASS__."::".__FUNCTION__." ".sprintf("%s", $wstop));
      system(sprintf("%s 1> /dev/null 2>&1", escapeshellarg($wstop), $ret));
      return $ret;
    }

    public function wstart() {
      $wstart = sprintf("%s/wstart", $this->root);
      error_log(__CLASS__."::".__FUNCTION__." ".sprintf("%s", $wstart));
      system(sprintf("%s 1> /dev/null 2>&1", escapeshellarg($wstart), $ret));
      return $ret;
    }

}

?>