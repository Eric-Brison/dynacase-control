<?php

/**
 * Context Class
 * @author Anakeen
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License
 */

class Context
{

    public $name;
    public $description;
    public $root;
	public $url;
    public $repo;

    private $errorMessage = null;

    public function __construct($name, $desc, $root, $repo, $url)
    {
        $this->name = $name;
        $this->description = $desc;
        $this->root = $root;
		$this->url = $url;
        $this->repo = $repo;
        foreach ($this->repo as $repository)
        {
            $repository->context = $this;
        }
    }
	
	/**
	 * Check if context repositories are valid.
	 * Populate repositories object with appropriate attributes.
	 * @return 
	 */
	public function isValid()
	{
		foreach ($this->repo as $repository)
		{
			$repository->isValid();
			$repository->needAuth();
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

    public function isWritable()
    {
        if (!is_writable($this->root))
        {
            return false;
        }
        return true;
    }

    /**
     * Import archive in Context
     * @return
     * @param object $name
     */
    public function importArchive($archive, $status = '')
    {
        require_once ('class/Class.WIFF.php');
        require_once ('class/Class.Module.php');

        $wiff = WIFF::getInstance();
        if ($wiff === false)
        {
            $this->errorMessage = sprintf("Could not get context.");
            return false;
        }

        $module = new Module($this);

        // Set package file to tmpfile archive
        $module->tmpfile = $archive;
        if ($module->tmpfile === false)
        {
            $this->errorMessage = sprintf("No archive provided.");
            return false;
        }

        // Load module attributes from info.xml
        $moduleXML = $module->loadInfoXml();
        if ($moduleXML === false)
        {
            $this->errorMessage = sprintf("Could not load info xml: '%s'.", $module->errorMessage);
            return false;
        }

        $contextsXML = new DOMDocument();
        $contextsXML->preserveWhiteSpace = false;
        $contextsXML->formatOutput = true;
        $ret = $contextsXML->load($wiff->contexts_filepath);
        if ($ret === false)
        {
            $this->errorMessage = sprintf("Could not load contexts.xml");
            return false;
        }

        $importedXML = $contextsXML->importNode($moduleXML, true); // Import module to contexts xml document
        if ($importedXML === false)
        {
            $this->errorMessage = sprintf("Could not import module node.");
            return false;
        }
        $moduleXML = $importedXML;

        $moduleXML->setAttribute('tmpfile', $archive);
	if( $status == '' ) {
	  $moduleXML->setAttribute('status', 'downloaded');
	} else {
	  $moduleXML->setAttribute('status', $status);
	}

        // Get <modules> node
        $contextsXPath = new DOMXPath($contextsXML);
        $modulesNodeList = $contextsXPath->query("/contexts/context[@name = '".$this->name."']/modules");
        if ($modulesNodeList->length <= 0)
        {
            $this->errorMessage = sprintf("Found no modules node for context '%s'.", $this->name);
            return false;
        }
        $modulesNode = $modulesNodeList->item(0);


        // Look for an existing <module> node
	$query = '';
	if( $status == 'downloaded' ) {
	  $query = sprintf("/contexts/context[@name='%s']/modules/module[@name='%s' and @status='downloaded']", $this->name, $module->name);
	} else if( $status == 'installed' ) {
	  $query = sprintf("/contexts/context[@name='%s']/modules/module[@name='%s' and @status='installed']", $this->name, $module->name);
	} else {
	  $query = sprintf("/contexts/context[@name='%s']/modules/module[@name='%s']", $this->name, $module->name);
	}
	  
        # $existingModuleNodeList = $contextsXPath->query("/contexts/context[@name='".$this->name."']/modules/module[@name='".$module->name."']");
        $existingModuleNodeList = $contextsXPath->query($query);
        if ($existingModuleNodeList->length <= 0)
        {
            // No corresponding module was found, so just append the current module
            # error_log("Creating a new <module> node.");
            $modulesNode->appendChild($moduleXML);
        } else
        {
            // A corresponding module was found, so replace it
            # error_log("Replacing existing <module> node.");
            if ($existingModuleNodeList->length > 1)
            {
                $this->errorMessage = sprintf("Found more than one <module> with name='%s' in '%s'.", $module->name, $wiff->contexts_filepath);
                return false;
            }
            $existingModuleNode = $existingModuleNodeList->item(0);
            $modulesNode->replaceChild($moduleXML, $existingModuleNode);
        }

        $ret = $contextsXML->save($wiff->contexts_filepath);
        if ($ret === false)
        {
            $this->errorMessage = sprintf("Error saving contexts.xml '%s'.", $wiff->contexts_filepath);
            return false;
        }

        return $module->tmpfile;
    }

    /**
     * Activate repository for Context
     * @return boolean success
     * @param string $name repository name
     * @param string $url repository url
     */
    public function activateRepo($name)
    {
        require_once ('class/Class.WIFF.php');
        require_once ('class/Class.Repository.php');

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
        //$contextRepoList = $contextsXPath->query("/contexts/context[@name='".$this->name."']/repositories/access[@name='".$name."']");
        $contextRepoList = $contextsXPath->query("/contexts/context[@name='".$this->name."']/repositories/access[@use='".$name."']");
        if ($contextRepoList->length > 0)
        {
            // If more than zero repository with name
            $this->errorMessage = "Repository already activated.";
            return false;
        }

        // Get repository with this name from WIFF repositories
        $wiffRepoList = $paramsXPath->query("/wiff/repositories/access[@name='".$name."']");
        if ($wiffRepoList->length == 0)
        {
            $this->errorMessage = "No repository with name ".$name.".";
            return false;
        } else if ($wiffRepoList->length > 1)
        {
            $this->errorMessage = "Duplicate repository with same name";
            return false;
        }

        // Add repository to this context
        $node = $contextsXml->createElement('access');
        $repository = $contextList->item(0)->getElementsByTagName('repositories')->item(0)->appendChild($node);

        $repository->setAttribute('use', $name);

        //        $repository = $contextsXml->importNode($wiffRepoList->item(0), true); // Node must be imported from params document.
        //
        //        $repository = $contextList->item(0)->getElementsByTagName('repositories')->item(0)->appendChild($repository);

        $ret = $contextsXml->save($wiff->contexts_filepath);
        if ($ret === false)
        {
            $this->errorMessage = sprintf("Error writing file '%s'.", $wiff->contexts_filepath);
            return false;
        }

        //Update Context object accordingly
        $this->repo[] = new Repository($repository, $this);

        return true;

    }

    /**
     * Deactivate repository for Context
     * @return boolean success
     * @param string $name repository name
     */
    public function deactivateRepo($name)
    {
        require_once ('class/Class.WIFF.php');

        $wiff = WIFF::getInstance();

        $xml = new DOMDocument();
        $xml->load($wiff->contexts_filepath);

        $xpath = new DOMXPath($xml);

        // Check this repository exists
        $contextRepoList = $xpath->query("/contexts/context[@name='".$this->name."']/repositories/access[@name='".$name."']");
        if ($contextRepoList->length == 1)
        {
            $contextRepo = $xpath->query("/contexts/context[@name='".$this->name."']/repositories")->item(0)->removeChild($contextRepoList->item(0));
            $ret = $xml->save($wiff->contexts_filepath);
            if ($ret === false)
            {
                $this->errorMessage = sprintf("Error writing file '%s'.", $wiff->contexts_filepath);
                return false;
            }

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



    public function deactivateAllRepo()
    {

        require_once ('class/Class.WIFF.php');

        $wiff = WIFF::getInstance();

        $xml = new DOMDocument();
        $xml->load($wiff->contexts_filepath);

        $xpath = new DOMXPath($xml);

        $contextRepo = $xpath->query("/contexts/context[@name='".$this->name."']/repositories")->item(0);
		
        while ($contextRepo->childNodes->length)
        {
            $contextRepo->removeChild($contextRepo->firstChild);
        }
		
        $ret = $xml->save($wiff->contexts_filepath);
        if ($ret === false)
        {
            $this->errorMessage = sprintf("Error writing file '%s'.", $wiff->contexts_filepath);
            return false;
        }

        $this->repo = array();

        return true;

}

/**
 * Get Module list
 * @return array of object Module or boolean false
 */
public function getModuleList()
{

    $moduleList = array ();

    $availableModuleList = $this->getAvailableModuleList();
    if ($availableModuleList === false)
    {
        $this->errorMessage = sprintf("Could not get available module list.");
        return false;
    }

    $installedModuleList = $this->getInstalledModuleList();
    if ($installedModuleList === false)
    {
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
    require_once ('class/Class.WIFF.php');
    require_once ('class/Class.Module.php');

    $wiff = WIFF::getInstance();

    $xml = new DOMDocument();
    $xml->load($wiff->contexts_filepath);

    $xpath = new DOMXPath($xml);

    $moduleList = array ();

    $moduleDom = $xpath->query("/contexts/context[@name='".$this->name."']/modules/module");

    foreach ($moduleDom as $module)
    {
        $mod = new Module($this, null, $module, true);
        if ($mod->status == 'installed')
        {
            $moduleList[] = $mod;
        }
    }

    //Process for with available version option
    if ($withAvailableVersion)
    {
        $availableModuleList = $this->getAvailableModuleList();

        foreach ($availableModuleList as $availableKey=>$availableModule)
        {
            foreach ($moduleList as $moduleKey=>$module)
            {
                if ($availableModule->name == $module->name)
                {
                    $module->availableversion = $availableModule->version;
                    $module->availableversionrelease = $availableModule->version.'-'.$availableModule->release;
                    $cmp = $this->cmpModuleByVersionReleaseAsc($module, $availableModule);
                    if ($cmp < 0)
                    {
                        $module->canUpdate = true;
						$module->parseXmlChangelogNode($availableModule->xmlNode);
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
    if ($onlyNotInstalled)
    {
        $installedModuleList = $this->getInstalledModuleList();

        foreach ($installedModuleList as $installedKey=>$installedModule)
        {
            foreach ($moduleList as $moduleKey=>$module)
            {
                if ($installedModule->name == $module->name)
                {
                    unset ($moduleList[$moduleKey]);
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
public function getModule($name, $status = false)
{
    require_once ('class/Class.WIFF.php');
    require_once ('class/Class.Module.php');

    $wiff = WIFF::getInstance();

    $xml = new DOMDocument();
    $xml->load($wiff->contexts_filepath);

    $xpath = new DOMXPath($xml);

    # $moduleDom = $xpath->query("/contexts/context[@name='".$this->name."']/modules/module[@name='".$name."']");

    $query = null;
    if( $status == 'installed' ) {
      $query = sprintf("/contexts/context[@name='%s']/modules/module[@name='%s' and @status='installed']", $this->name, $name);
    } else if( $status == 'downloaded' ) {
      $query = sprintf("/contexts/context[@name='%s']/modules/module[@name='%s' and @status='downloaded']", $this->name, $name);
    } else {
      $query = sprintf("/contexts/context[@name='%s']/modules/module[@name='%s']", $this->name, $name);
    }
    $moduleDom = $xpath->query($query);

    if ($moduleDom->length <= 0)
    {
        $this->errorMessage = sprintf("Could not find a module named '%s' in context '%s'.", $name, $this->name);
        return false;
    }

    return new Module($this, null, $moduleDom->item(0), true);
}

public function getModuleDownloaded($name) {
  return $this->getModule($name, 'downloaded');
}

public function getModuleInstalled($name) {
  return $this->getModule($name, 'installed');
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
            $mod->context = $this;

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
 * @param the module name list
 */
public function getModuleDependencies($namelist, $local = false)
{
    /*
     $modsAvail = $this->getAvailableModuleList();
     if ($modsAvail === false)
     {
     return false;
     }
     */

    $depsList = array ();

    foreach ($namelist as $name)
    {
        if ($local == false)
        {
            $module = $this->getModuleAvail($name);
            if ($module === false)
            {
                $this->errorMessage = sprintf("Module '%s' required by '%s' could not be found in repositories.", $reqModName, $mod->name);
                return false;
            }

            array_push($depsList, $module);
        } else
        {
	  $module = $this->getModuleDownloaded($name);
            if ($module === false)
            {
                $this->errorMessage = sprintf("Local module '%s' not found in contexts.xml.", $name);
                return false;
            }
            array_push($depsList, $module);
        }
    }


    $modMovedBy = array ();

    $i = 0;
    while ($i < count($depsList))
    {
        $mod = $depsList[$i];
        $reqList = $mod->getRequiredModules();

        foreach ($reqList as $req)
        {
            $reqModName = $req['name'];
            $reqModVersion = $req['version'];
            $reqModComp = $req['comp'];

            $reqMod = $this->getModuleAvail($reqModName);
            if ($reqMod === false)
            {
                $this->errorMessage = sprintf("Module '%s' required by '%s' could not be found in repositories.", $reqModName, $mod->name);
                return false;
            }

            switch($reqModComp)
            {
                case '':
                    break;
                case 'ge':
                    if ($this->cmpVersionReleaseAsc($reqMod->version, 0, $reqModVersion, 0) < 0)
                    {
                        $this->errorMessage = sprintf("Module '%s-%s' requires '%s' >= %s, but only '%s-%s' was found on repository.", $mod->name, $mod->version, $reqModName, $reqModVersion, $reqMod->name, $reqMod->version);
                        return false;
                    }
                break;
                default:
                    $this->errorMessage = sprintf("Operator of module comparison '%s' is not yet implemented.", $reqModComp);
                    return false;
            }


            // Check if a version of this module is already installed
	    if( $this->moduleIsInstalled($reqMod) ) {
	      if ($this->moduleIsInstalledAndUpToDateWith($reqMod, $reqModComp, $reqModVersion)) {
                continue ;
	      }
	      $reqMod->needphase = 'upgrade';
	    } else {
	      $reqMod->needphase = 'install';
	    }

            $pos = $this->depsListContains($depsList, $reqMod->name);
            if ($pos < 0)
            {
                // Add the module to the dependencies list
                array_push($depsList, $reqMod);
            }
        }
        $i++;
    }

    function listContains($list, $name)
    {
        foreach ($list as $module)
        {
            if ($module->name == $name)
            {
                return true;
            }
        }
        return false;
    }

    function recursiveOrdering( & $list, & $orderList)
    {
        foreach ($list as $key=>$mod)
        {
            $reqList = $mod->getRequiredModules();

            $pushable = true;

            foreach ($reqList as $req)
            {
                // If ordered list does not contain one dependency and dependency list does contain it, module must not be added to ordered list at that time
                if (!listContains($orderList, $req['name']) && listContains($list, $req['name']))
                {
                    $pushable = false;
                }
            }

            if ($pushable)
            {
                array_push($orderList, $mod);
                unset ($list[$key]);
            }

        }

        if (count($list) != 0)
        {
            recursiveOrdering($list, $orderList);
        }

    }

    $orderList = array ();

    recursiveOrdering($depsList, $orderList);

    return $orderList;
}

/**
 * Check if a Module object with this name already exists a a list of
 * Module objects
 * @return true if the module with the given name is found, false if not found
 * @param array( Module object 1, [...], Module object N )
 */
private function depsListContains( & $depsList, $name)
{
    $i = 0;
    while ($i < count($depsList))
    {
        if ($depsList[$i]->name == $name)
        {
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
private function moveDepToRight( & $depsList, $pos, $pivot)
{
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
private function moduleIsInstalledAndUpToDateWith( & $targetModule, $operator = '', $version = '')
{

    $installedModule = $this->moduleIsInstalled($targetModule);

    if ($installedModule->status != 'installed')
    {
        return false;
    }

    if ($operator != '')
    {
        switch($operator)
        {
            case 'ge':

                $v = $installedModule->version;
                $r = $installedModule->release;

                $cmp = $this->cmpVersionReleaseAsc($v, $r, $version, 0);
                if ($cmp != -1)
                {
                    return true;
                } else
                {
                    return false;
                }
            break;
            case '':

        }
    } else
    {
        return (bool)$installedModule;
    }

    $cmp = $this->cmpModuleByVersionReleaseAsc($installedModule, $targetModule);

    if ($cmp < 0)
    {
        return false;
    }
    return true;
}

public function getParamByName($paramName)
{
    require_once ('class/Class.WIFF.php');

    $wiff = WIFF::getInstance();

    $xml = new DOMDocument();
    $ret = $xml->load($wiff->contexts_filepath);
    if ($ret === false)
    {
        $this->errorMessage = sprintf("Error opening XML file '%s'.", $wiff->contexts_filepath);
        return false;
    }

    $xpath = new DOMXPath($xml);

    $parameterNode = $xpath->query(sprintf("/contexts/context[@name='%s']/parameters-value/param[@name='%s']", $this->name, $paramName))->item(0);
    if ($parameterNode)
    {
        $value = $parameterNode->getAttribute('value');
        $this->errorMessage = '';
        return $value;
    }
    $this->errorMessage = sprintf("Parameter with name '%s' not found in context '%s'.", $paramName, $this->name);
    return '';
}

public function wstop()
{
    $wstop = sprintf("%s/wstop", $this->root);
    # error_log( __CLASS__ ."::". __FUNCTION__ ." ".sprintf("%s", $wstop));
    system(sprintf("%s 1> /dev/null 2>&1", escapeshellarg($wstop), $ret));
    return $ret;
}

public function wstart()
{
    $wstart = sprintf("%s/wstart", $this->root);
    # error_log( __CLASS__ ."::". __FUNCTION__ ." ".sprintf("%s", $wstart));
    system(sprintf("%s 1> /dev/null 2>&1", escapeshellarg($wstart), $ret));
    return $ret;
}

public function uploadModule()
{
    require_once ('lib/Lib.System.php');

    $tmpfile = WiffLibSystem::tempnam(null, 'WIFF_downloadLocalFile');
    if ($tmpfile === false)
    {
        $this->errorMessage = sprintf( __CLASS__ ."::". __FUNCTION__ ." "."Error creating temporary file.");
        return false;
    }

    if (!array_key_exists('module', $_FILES))
    {
        $this->errorMessage = sprintf( __CLASS__ ."::". __FUNCTION__ ." ".sprintf("Missing 'module' in uploaded files."));
        unlink($tmpfile);
        return false;
    }

    $ret = move_uploaded_file($_FILES['module']['tmp_name'], $tmpfile);
    if ($ret === false)
    {
        $this->errorMessage = sprintf( __CLASS__ ."::". __FUNCTION__ ." ".sprintf("Could not move uploaded file to temporary file '%s'.", $tmpfile));
        unlink($tmpfile);
        return false;
    }

    $ret = $this->importArchive($tmpfile);
    if ($ret === false)
    {
        $this->errorMessage = sprintf( __CLASS__ ."::". __FUNCTION__ ." ".sprintf("Failed to import archive: '%s'.", $this->errorMessage));
        return false;
    }

    return $tmpfile;
}

public function getModuleNameFromTmpFile($moduleFilePath)
{
    $wiff = WIFF::getInstance();
    if ($wiff === false)
    {
        $this->errorMessage = sprintf("Could not get context.");
        return false;
    }

    $xml = new DOMDocument();
    $ret = $xml->load($wiff->contexts_filepath);
    if ($ret === false)
    {
        $this->errorMessage = sprintf("Could not load contexts.xml from '%s'", $wiff->contexts_filepath);
        return false;
    }

    $xpath = new DOMXPath($xml);

    $res = $xpath->query(sprintf("/contexts/context[@name='%s']/modules/module[@tmpfile='%s']", $this->name, $moduleFilePath));
    if ($res->length <= 0)
    {
        $this->errorMessage = sprintf("Could not find module with tmpfile '%s'", $moduleFilePath);
        return false;
    }
    if ($res->length > 1)
    {
        $this->errorMessage = sprintf("Found more than one module with tmpfile '%s'", $moduleFilePath);
        return false;
    }

    $module = $res->item(0);

    return $module->getAttribute('name');
}

public function getLocalModuleDependencies($moduleFilePath)
{
    $moduleName = $this->getModuleNameFromTmpFile($moduleFilePath);
    if ($moduleName === false)
    {
        $this->errorMessage = sprintf("Could not get module name from filepath '%s' in contexts.xml: %s", $moduleFilePath, $this->errorMessage);
        return false;
    }

    $module = $this->getModuleDownloaded($moduleName);
    if ($module === false)
    {
        $this->errorMessage = sprintf("Could not get module with name '%s' in contexts.xml: %s", $moduleName, $this->errorMessage);
        return false;
    }

    # error_log(sprintf(">>> moduleName = %s", $moduleName));

    $deps = $this->getModuleDependencies( array ($moduleName), true);

    return $deps;
}

public function loadModuleFromPackage($filename) {
  require_once('class/Class.Module.php');

  $module = new Module($this);
  $module->tmpfile = $filename;

  $xml = $module->loadInfoXml();
  if( $xml === false ) {
    $this->errorMessage = sprintf("Could not load info xml: '%s'.", $module->errorMessage);
    return false;
  }

  return $module;
}

public function removeModule($moduleName, $status = '') {
  require_once('class/Class.WIFF.php');
  require_once('class/Class.Module.php');

  $wiff = WIFF::getInstance();
  if( $wiff === false ) {
    $this->errorMessage = sprintf("Could not get context.");
    return false;
  }

  $xml = new DOMDocument();
  $xml->preserveWhiteSpace = false;
  $xml->formatOutput = true;
  $ret = $xml->load($wiff->contexts_filepath);
  if( $ret === false ) {
    $this->errorMessage = sprintf("Could not load contexts.xml");
    return false;
  }

  $xpath = new DOMXpath($xml);

  $query = null;
  if( $status == 'installed' ) {
    $query = sprintf("/contexts/context[@name='%s']/modules/module[@name='%s' and @status='installed']", $this->name, $moduleName);
  } else if( $status == 'downloaded' ) {
    $query = sprintf("/contexts/context[@name='%s']/modules/module[@name='%s' and @status='downloaded']", $this->name, $moduleName);
  } else {
    $query = sprintf("/contexts/context[@name='%s']/modules/module[@name='%s']", $this->name, $moduleName);
  }
  $moduleDom = $xpath->query($query);

  if( $moduleDom->length <= 0 ) {
    return true;
  }

  for( $i = 0; $i < $moduleDom->length; $i++ ) {
    $module = $moduleDom->item(0);
    $ret = $module->parentNode->removeChild($module);
  }
  
  $ret = $xml->save($wiff->contexts_filepath);
  if( $ret === false ) {
    $this->errorMessage = sprintf("Error saving contexts.xml '%s'.", $wiff->contexts_filepath);
    return false;
  }

  return true;  
}

public function removeModuleInstalled($moduleName) {
  return $this->removeModule($moduleName, 'installed');
}

public function removeModuleDownloaded($moduleName) {
  return $this->removeModule($moduleName, 'downloaded');
}

}

?>
