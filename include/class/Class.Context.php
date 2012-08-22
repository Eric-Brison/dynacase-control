<?php

/*
 * Context Class
 * @author Anakeen
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License
 */

// Util functions using php DOMDocument
function deleteNode($node) {
	deleteChildren($node);
	$parent = $node->parentNode;
	$oldnode = $parent->removeChild($node);
}

function deleteChildren($node) {
	while (isset($node->firstChild)) {
		deleteChildren($node->firstChild);
		$node->removeChild($node->firstChild);
	}
}


class Context
{

	public $name;
	public $description;
	public $root;
	public $url;
	public $repo;
	public $register;

	public $errorMessage = null;

	public function __construct($name, $desc, $root, $repo, $url, $register)
	{
		$this->name = $name;
		$this->description = $desc;
		$this->root = $root;
		$this->url = $url;
		$this->repo = $repo;
		$this->register = $register;
		foreach ($this->repo as $repository)
		{
			$repository->context = $this;
		}
	}

    /**
     * Check if context repositories are valid.
     * Populate repositories object with appropriate attributes.
     * @return void
     */
	public function isValid()
	{
		foreach ($this->repo as $repository)
		{
			// $repository->isValid();
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
     * @return bool|string boolean false on error or the archive pathname
     * @param string $archive the archive pathname
     * @param string $status the status to which the imported archive will be set to (default = 'downloaded')
     * @internal param object $name
     */
	public function importArchive($archive, $status = 'downloaded')
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
		if($status == 'downloaded') {
			$query = sprintf("/contexts/context[@name='%s']/modules/module[@name='%s' and @status='downloaded']", $this->name, $module->name);
		} else if($status == 'installed') {
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
     * @internal param string $url repository url
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
			$xpath->query("/contexts/context[@name='".$this->name."']/repositories")->item(0)->removeChild($contextRepoList->item(0));
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


    /**
     * Deactivate all repositories
     * @return bool success
     */
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

			foreach ($availableModuleList as $availableModule)
			{
				foreach ($moduleList as $module)
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

			foreach ($installedModuleList as $installedModule)
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
	 * @return array containing unique module Objects
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
			$seen[$module->name] = isset($seen[$module->name]) ? $seen[$module->name] + 1 : 1;
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

		$str1 = sprintf("%03d%03d%03d", $ver1[0], $ver1[1], $ver1[2]);
		$str2 = sprintf("%03d%03d%03d", $ver2[0], $ver2[1], $ver2[2]);

		$cmp_ver = strcmp($str1, $str2);

		/* Version is different, so we do not
		 * need to test the release
		 */
		if( $cmp_ver != 0 ) {
			return $cmp_ver;
		}

		/* Version is equal, so we need to
		 * test the release:
		 *   num vs. num => numeric comparison
		 *   str vs. str => string comparison
		 *   num vs. str => string is < to num
		 */
		if( is_numeric($rel1) && is_numeric($rel2) ) {
			/* standard numeric comparison */
			$cmp_rel = $rel1-$rel2;
		} else if( is_numeric($rel1) && is_string($rel2) ) {
			/* number is > to string */
			$cmp_rel = 1;
		} else if( is_string($rel1) && is_numeric($rel2) ) {
			/* string is < to number */
			$cmp_rel = -1;
		} else if( is_string($rel1) && is_string($rel2) ) {
			/* standard string comparison */
			$cmp_rel = strcmp($rel1, $rel2);
		} else {
			$cmp_rel = 0;
		}

		return $cmp_rel;
	}

	/**
	 * Compare two module Objects by ascending version-release
	 * @return int < 0 if mod1 is less than mod2, > 0 if mod1 is greater than mod2,
	 * @param Module $module1
	 * @param Module $module2
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
	 * @return int > 0 if mod1 is less than mod2, < 0 if mod1 is greater than mod2,
	 * @param Module $module1
	 * @param Module $module2
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
	 * @param Module $name Module name
	 * @param bool $status
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

	public function getModuleReplaced($name, $status = false) {
		require_once ('class/Class.WIFF.php');
		require_once ('class/Class.Module.php');

		$wiff = WIFF::getInstance();

		$xml = new DOMDocument();
		$xml->load($wiff->contexts_filepath);

		$xpath = new DOMXPath($xml);

		$query = null;
		if( $status == 'installed' ) {
			$query = sprintf("/contexts/context[@name='%s']/modules/module[@status='installed']/replaces/module[@name='%s']/../..", $this->name, $name);
		} else if( $status == 'downloaded' ) {
			$query = sprintf("/contexts/context[@name='%s']/modules/module[@status='downloaded']/replaces/module[@name='%s']/../..", $this->name, $name);
		} else {
			$query = sprintf("/contexts/context[@name='%s']/modules/module/replaces/module[@name='%s']/../..", $this->name, $name);
		}
		$moduleDom = $xpath->query($query);

		if ($moduleDom->length <= 0)
		{
			$this->errorMessage = sprintf("Could not find a module providing '%s' in context '%s'.", $name, $this->name);
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

	public function getModuleInstalledReplaced($name) {
		return $this->getModuleReplaced($name, 'installed');
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

    public function compSymbol($comp) {
        $symbol = array(
            'gt' => '>',
            'ge' => '>=',
            'lt' => '<',
            'le' => '<=',
            'eq' => '==',
            'ne' => '!='
        );
        return (isset($symbol[$comp]))?$symbol[$comp]:"??";
    }

    public function getModuleAvailSatisfying($name, $comp, $version) {
        $moduleList = array();
        foreach ($this->repo as $repository) {
            $repoModuleList = $repository->getModuleList();
            if ($repoModuleList === false) {
                $this->errorMessage = sprintf("Error fetching index for repository '%s'.", $repository->name);
                continue;
            }
            foreach ($repoModuleList as $module) {
                if ($module->name == $name && $this->moduleMeetsRequiredVersion($module, $comp, $version)) {
                    array_push($moduleList, $module);
                }
            }
        }
        usort($moduleList, array($this, "cmpModuleByVersionReleaseDesc"));
        if (isset($moduleList[0])) {
            $mod = $moduleList[0];
            $mod->context = $this;
            return $mod;
        }
        return false;
    }

	public function getModuleAvailReplaced($name) {
		$modAvails = $this->getAvailableModuleList();
		if( $modAvails === false ) {
			return false;
		}
		foreach( $modAvails as $mod ) {
			foreach( $mod->replaces as $replace ) {
				if( $replace['name'] == $name ) {
					return $mod;
				}
			}
		}

		$this->errorMessage = sprintf("Could not find a module providing '%s' in context '%s'.", $name, $this->name);
		return false;
	}

	/**
	 * Get module dependencies from repositories indexes
	 * @return array containing a list of Module objects ordered by their
	 *         install order, or false in case of error
	 * @param the module name list
	 * @param bool $local
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
					$this->errorMessage = sprintf("Module '%s' could not be found in repositories.", $name);
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

		$i = 0;
		while ($i < count($depsList))
		{
			$mod = $depsList[$i];

			if( ! $this->installerMeetsModuleRequiredVersion($mod) ) {
				$this->errorMessage = sprintf("Module '%s' (%s-%s) requires installer %s", $mod->name, $mod->version, $mod->release, $this->errorMessage);
				return false;
			}

			$reqList = $mod->getRequiredModules();

			foreach ($reqList as $req)
			{
				$reqModName = $req['name'];
				$reqModVersion = $req['version'];
				$reqModComp = $req['comp'];

				$reqMod = $this->getModuleInstalled($reqModName);
				if( $reqMod !== false ) {
					// Found an installed module
					if( $this->moduleMeetsRequiredVersion($reqMod, $reqModComp, $reqModVersion) ) {
						// The installed module satisfy the required version
						// Keep it
						continue;
					} else {
						// Installed module does not satisfy required version
						// so try looking for a matching module in repositories
						$currentInstalledMod = $reqMod;
						$satisfyingMod = $this->getModuleAvailSatisfying($reqModName, $reqModComp, $reqModVersion);
						if( $satisfyingMod !== false ) {
							if ($this->cmpModuleByVersionReleaseAsc($satisfyingMod, $currentInstalledMod) > 0) {
								$satisfyingMod->needphase = 'upgrade';
							} else {
								$this->errorMessage = sprintf("Module %s (%s %s) required by %s is not compatible with current set of installed and available modules.", $reqModName, $this->compSymbol($reqModComp), $reqModVersion, $mod->name);
								return false;
							}
							// Keep the satisfying module as the required module for install/upgrade
							$reqMod = $satisfyingMod;
						} else {
							// No satisfying module has been found
							$reqMod = false;
						}
					}
				} else {
					// Module is not already installed
					// so lookup in repositories for a matching module
					$reqMod = $this->getModuleAvailSatisfying($reqModName, $reqModComp, $reqModVersion);
					if( $reqMod !== false ) {
						$reqMod->needphase = 'install';
					}
				}

				if( $reqMod === false ) {
					// Search the required module in replaced modules
					$reqMod = $this->getModuleInstalledReplaced($reqModName);
					if( $reqMod !== false ) {
						continue;
					} else {
						// Look for an available module online that replaces the required module
						$reqMod = $this->getModuleAvailReplaced($reqModName);
						if( $reqMod !== false ) {
							$reqMod->needphase = 'install';
						}
					}
				}

				if( $reqMod === false ) {
					$this->errorMessage = sprintf("Module '%s' (%s %s) required by '%s' could not be found in repositories.", $reqModName, $this->compSymbol($reqModComp), $reqModVersion, $mod->name);
					return false;
				}

				$pos = $this->depsListContains($depsList, $reqMod->name);
				if( $pos < 0 ) {
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

		// Put toolbox always at the beginning of the list
		foreach($orderList as $key=>$value){
			if(($value->name == 'dynacase-platform')  || ($value->name == 'freedom-toolbox')){

				unset($orderList[$key]);
				array_unshift($orderList,$value);

			}
		}

		// Check for and add replaced modules to the dep list
		// and mark them with needPhase='replaced'

		$removeList = array();
		foreach( $orderList as &$mod ) {
			foreach( $mod->replaces as $replace ) {
				$replacedModule = $this->getModuleInstalled($replace['name']);
				if( $replacedModule !== false ) {
					// This module is installed, so mark it for removal
					array_push($removeList, $replacedModule);
					// and mark the main module for 'upgrade'
					$mod->needphase = 'upgrade';
				}
			}
		}

		unset($mod);

		foreach( $removeList as $mod ) {
			if( ! listContains($orderList, $mod->name) ) {
				$mod->needphase = 'replaced';
				array_unshift($orderList, $mod);
			}
		}

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
	 * @return void (nothing)
	 * @param array $depsList array of Modules
	 * @param int $pos actual module to move
	 * @param int $pivot position which the module should be moved to
	 */
	private function moveDepToRight( & $depsList, $pos, $pivot)
	{
		$extractedModule = array_splice($depsList, $pos, 1);
		array_splice($depsList, $pivot, 0, $extractedModule);
	}

	/**
	 * Check if a module is installed
	 * @param Module $module the Module object
	 * @return bool|\Module|object
	 */
	private function moduleIsInstalled( & $module)
	{
		$installedModule = $this->getModuleInstalled($module->name);
		if ($installedModule === false)
		{
			return false;
		}
		return $installedModule;
	}

	/**
	 * Check if the given module Object is already installed and up-to-date
	 * @param Module $targetModule the Module object
	 * @param string $operator comparison operator (e.g. 'gt', 'le', etc.)
	 * @param string $version comparison version (e.g. '1.2.3', '3.6.9', etc.)
	 * @return bool
	 */
	private function moduleIsInstalledAndUpToDateWith( & $targetModule, $operator = '', $version = '')
	{

		$installedModule = $this->moduleIsInstalled($targetModule);

		if ($installedModule === false || $installedModule->status != 'installed')
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

	public function installerMeetsModuleRequiredVersion(&$module) {
		if( ! isset($module->requires['installer'] ) ) {
			return true;
		}

		$wiff = WIFF::getInstance();
		$wiffVersion = $wiff->getVersion();
		if( $wiffVersion === false ) {
			$this->errorMessage = $wiff->errorMessage;
			return false;
		}
		$wiffVersion = preg_split('/\-/', $wiffVersion, 2);

		switch( $module->requires['installer']['comp'] ) {
			case 'ge':
				$cmp = $this->cmpVersionReleaseAsc($module->requires['installer']['version'], 0, $wiffVersion[0], 0);
				if( $cmp > 0 ) {
					$this->errorMessage = sprintf(">= %s", $module->requires['installer']['version']);
					return false;
				} else {
					return true;
				}
			default:
				error_log(__CLASS__."::".__FUNCTION_." ".sprintf("Comparison operator '%s' not yet supported.", $module->requires['installer']['comp']));
		}

		return true;
	}

	public function moduleMeetsRequiredVersion(&$module, $operator = '', $version = '') {
		$v = $module->version;
		$r = $module->release;

		switch( $operator ) {
			case 'ge':
				$cmp = $this->cmpVersionReleaseAsc($v, $r, $version, 0);
				if( $cmp >= 0 ) {
					return true;
				} else {
					return false;
				}
				break;
            case 'gt':
                $cmp = $this->cmpVersionReleaseAsc($v, $r, $version, 0);
                if( $cmp > 0 ) {
                    return true;
                } else {
                    return false;
                }
                break;
            case 'le':
                $cmp = $this->cmpVersionReleaseAsc($v, $r, $version, 0);
                if( $cmp <= 0 ) {
                    return true;
                } else {
                    return false;
                }
                break;
            case 'lt':
                $cmp = $this->cmpVersionReleaseAsc($v, $r, $version, 0);
                if( $cmp < 0 ) {
                    return true;
                } else {
                    return false;
                }
                break;
            case 'eq':
                $cmp = $this->cmpVersionReleaseAsc($v, $r, $version, 0);
                if( $cmp == 0 ) {
                    return true;
                } else {
                    return false;
                }
                break;
            case 'ne':
                $cmp = $this->cmpVersionReleaseAsc($v, $r, $version, 0);
                if( $cmp != 0 ) {
                    return true;
                } else {
                    return false;
                }
                break;
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
		$ret = 0;
		system(sprintf("%s 1> /dev/null 2>&1", escapeshellarg($wstop), $ret));
		return $ret;
	}

	public function wstart()
	{
		$wstart = sprintf("%s/wstart", $this->root);
		# error_log( __CLASS__ ."::". __FUNCTION__ ." ".sprintf("%s", $wstart));
		$ret = 0;
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
			$module = $moduleDom->item($i);
			$module->parentNode->removeChild($module);
		}

		$ret = $xml->save($wiff->contexts_filepath);
		if( $ret === false ) {
			$this->errorMessage = sprintf("Error saving contexts.xml '%s'.", $wiff->contexts_filepath);
			return false;
		}

		return true;
	}

	public function removeModuleInstalled($moduleName) {
		$ret = $this->removeModule($moduleName, 'installed');
		if( $ret === false ) {
			return false;
		}

		return true;
	}

	public function removeModuleDownloaded($moduleName) {
		return $this->removeModule($moduleName, 'downloaded');
	}

	public function archiveContext($archiveName,$archiveDesc = '', $vaultExclude = false) {
		$tmp = 'archived-tmp';

		// --- Create or reuse directory --- //
		if (is_dir($tmp))
		{
			if (!is_writable($tmp))
			{
				$this->errorMessage = sprintf("Directory '%s' is not writable.", $tmp);
				return false;
			}
		} else
		{
			if (@mkdir($tmp) === false)
			{
				$this->errorMessage = sprintf("Error creating directory '%s'.", $tmp);
				return false;
			}
		}

		$zip = new ZipArchiveCmd();

		$wiff_root = getenv('WIFF_ROOT');
		if ($wiff_root !== false)
		{
			$wiff_root = $wiff_root.DIRECTORY_SEPARATOR;
		}
		$archived_root = $wiff_root.WIFF::archive_filepath;

		// --- Generate archive id --- //
		$datetime = new DateTime();
		$archiveId = sprintf("%s-%s", preg_replace('/\//', '_', $archiveName), sha1($this->name.$datetime->format('Y-m-d H:i:s')));

		// --- Create status file for archive --- //
		$status_file = $archived_root.DIRECTORY_SEPARATOR.$archiveId.'.sts';
		$status_handle = fopen($status_file, "w");
		fwrite($status_handle,$archiveName);

		$zipfile = $archived_root."/$archiveId.fcz";
		if( $zip->open($zipfile, ZipArchiveCmd::CREATE) !== false ) {

			// --- Generate info.xml --- //
			$doc = new DOMDocument();
			$doc->formatOutput = true;

			$root = $doc->createElement('info');
			$root = $doc->appendChild($root);



			// --- Copy context information --- //
			$wiff_root = getenv('WIFF_ROOT');
			if ($wiff_root !== false)
			{
				$wiff_root = $wiff_root.DIRECTORY_SEPARATOR;
			}

			$contexts_filepath = $wiff_root.WIFF::contexts_filepath;

			$contextsXml = new DOMDocument();
			$contextsXml->load($contexts_filepath);

			$contextsXPath = new DOMXPath($contextsXml);

			// Get this context
			$contextList = $contextsXPath->query("/contexts/context[@name='".$this->name."']");
			if ($contextList->length != 1)
			{
				// If more than one context with name
				$this->errorMessage = "Duplicate contexts with same name";
				$zip->close();
				if (file_exists($archived_root."/$archiveId.fcz")) {
					unlink($archived_root."/$archiveId.fcz");
				}
				unlink($status_file);
				return false;
			}

			$context = $doc->importNode($contextList->item(0), true); // Node must be imported from contexts document.
			if( $context->hasAttribute('register') ) {
				// Remove register status on archived contexts
				$context->removeAttribute('register');
			}
			$context = $root->appendChild($context);

			$repositories = $context->getElementsByTagName('repositories')->item(0);
			if ($repositories) deleteNode($repositories);

			// Identify and exclude vaults located below the context directory
			$vaultList = $this->getVaultList();
			if( $vaultList === false ) {
				$zip->close();
				if (file_exists($archived_root."/$archiveId.fcz")) {
					unlink($archived_root."/$archiveId.fcz");
				}
				unlink($status_file);
				return false;
			}
			$realContextRootPath = realpath($this->root);
			if( $realContextRootPath === false ) {
				$this->errorMessage = sprintf("Error getting real path for '%s'", $this->root);
				$zip->close();
				if (file_exists($archived_root."/$archiveId.fcz")) {
					unlink($archived_root."/$archiveId.fcz");
				}
				unlink($status_file);
				return false;
			}
			$tarExcludeOpts = '';
			$tarExcludeList = array(
				sprintf("--exclude %s", escapeshellarg('.'.DIRECTORY_SEPARATOR.'var'))
			);
			foreach( $vaultList as $vault ) {
				$r_path = $vault['r_path'];
				if( $r_path[0] != '/' ) {
					$r_path = $this->root.DIRECTORY_SEPARATOR.$r_path;
				}
				$real_r_path = realpath($r_path);
				if( $real_r_path === false ) {
					continue;
				}
				if( strpos($real_r_path, $realContextRootPath) === 0 ) {
					$relative_r_path = "." . substr($real_r_path, strlen($realContextRootPath));
					$tarExcludeList []= sprintf("--exclude %s", escapeshellarg($relative_r_path));
				}
			}
			if( count($tarExcludeList) > 0 ) {
				$tarExcludeOpts = join(' ', $tarExcludeList);
			}
			error_log(__METHOD__." ".sprintf("tarExcludeOpts = [%s]", $tarExcludeOpts));

			// --- Generate context tar.gz --- //
			$script = sprintf("tar -C %s -czf %s/context.tar.gz %s .", escapeshellarg($this->root), escapeshellarg($tmp), $tarExcludeOpts);
			$result = system($script, $retval);
			if($retval != 0){
				$this->errorMessage = "Error when making context tar :: ".$result;
				if (file_exists("$tmp/context.tar.gz")) {
					unlink("$tmp/context.tar.gz");
				}
				$zip->close();
				if (file_exists($archived_root."/$archiveId.fcz")) {
					unlink($archived_root."/$archiveId.fcz");
				}
				unlink($status_file);
				return false;
			}
			$err = $zip->addFileWithoutPath("$tmp/context.tar.gz");
			if ($err === false) {
				$this->errorMessage = sprintf("Could not add 'context.tar.gz' to archive: %s", $zip->getStatusString());
				if (file_exists("$tmp/context.tar.gz")) {
					unlink("$tmp/context.tar.gz");
				}
				$zip->close();
				if (file_exists($archived_root."/$archiveId.fcz")) {
					unlink($archived_root."/$archiveId.fcz");
				}
				unlink($status_file);
				return false;
			}
			error_log('Generated context.tar.gz');

			// --- Generate database dump --- //
			$pgservice_core = $this->getParamByName('core_db');

			$dump = $tmp.DIRECTORY_SEPARATOR.'core_db.pg_dump.gz';

			$script = sprintf("PGSERVICE=%s pg_dump > %s --compress=9 --no-owner", escapeshellarg($pgservice_core), escapeshellarg($dump));
			$result = system($script, $retval);

			if( $retval != 0 ){
				$this->errorMessage = "Error when making database dump :: ".$result;
				if (file_exists("$tmp/context.tar.gz")) {
					unlink("$tmp/context.tar.gz");
				}
				if (file_exists("$dump")) {
					unlink("$dump");
				}
				$zip->close();
				if (file_exists($archived_root."/$archiveId.fcz")) {
					unlink($archived_root."/$archiveId.fcz");
				}
				unlink($status_file);
				return false;
			}

			$err = $zip->addFileWithoutPath($dump);
			if ($err === false) {
				$this->errorMessage = sprintf("Could not add 'core_db.pg_dump.gz' to archive: %s", $zip->getStatusString());
				if (file_exists("$tmp/context.tar.gz")) {
					unlink("$tmp/context.tar.gz");
				}
				if (file_exists("$dump")) {
					unlink("$dump");
				}
				$zip->close();
				if (file_exists($archived_root."/$archiveId.fcz")) {
					unlink($archived_root."/$archiveId.fcz");
				}
				unlink($status_file);
				return false;
			}
			error_log('Generated core_db.pg_dump.gz');

			if ($vaultExclude != 'on') {
				// --- Generate vaults tar.gz files --- //
				$vaultList = $this->getVaultList();
				if( $vaultList === false ) {
					if (file_exists("$tmp/context.tar.gz")) {
						unlink("$tmp/context.tar.gz");
					}
					if (file_exists("$dump")) {
						unlink("$dump");
					}
					$zip->close();
					if (file_exists($archived_root."/$archiveId.fcz")) {
						unlink($archived_root."/$archiveId.fcz");
					}
					unlink($status_file);
				}

				$vaultDirList = array();
				foreach( $vaultList as $vault ) {
					$id_fs = $vault['id_fs'];
					$r_path = $vault['r_path'];
					if (is_dir($r_path)) {
						$vaultDirList[] = array("id_fs" => $id_fs, "r_path" => $r_path);
						$vaultExclude = 'Vaultexists';
						$script = sprintf("tar -C %s -czf  %s/vault_$id_fs.tar.gz .", escapeshellarg($r_path), escapeshellarg($tmp));
						$res = system($script, $retval);
						if($retval != 0){
							$this->errorMessage = "Error when making vault tar :: ".$res;
							if (file_exists("$tmp/context.tar.gz")) {
								unlink("$tmp/context.tar.gz");
							}
							if (file_exists("$dump")) {
								unlink("$dump");
							}
							/*--- Delete vault list --- */
							$i = 0;
							while ($vaultDirList[$i]) {
								if (file_exists($tmp."/vault_".$vaultDirList[$i]["id_fs"].".tar.gz")) {
									unlink($tmp."/vault_".$vaultDirList[$i]["id_fs"].".tar.gz");
								}
								$i++;
							}
							$zip->close();
							if (file_exists($archived_root."/$archiveId.fcz")) {
								unlink($archived_root."/$archiveId.fcz");
							}
							unlink($status_file);
							return false;
						}
						$err = $zip->addFileWithoutPath("$tmp/vault_${id_fs}.tar.gz");
						if ($err === false) {
							$this->errorMessage = sprintf("Could not add 'vault_%s.tar.gz' to archive: %s", $id_fs, $zip->getStatusString());
							if (file_exists("$tmp/context.tar.gz")) {
								unlink("$tmp/context.tar.gz");
							}
							if (file_exists("$dump")) {
								unlink("$dump");
							}
							/*--- Delete vault list --- */
							$i = 0;
							while ($vaultDirList[$i]) {
								if (file_exists($tmp."/vault_".$vaultDirList[$i]["id_fs"].".tar.gz")) {
									unlink($tmp."/vault_".$vaultDirList[$i]["id_fs"].".tar.gz");
								}
								$i++;
							}
							$zip->close();
							if (file_exists($archived_root."/$archiveId.fcz")) {
								unlink($archived_root."/$archiveId.fcz");
							}
							unlink($status_file);
							return false;
						}
					}
					elseif ($vaultExclude != 'Vaultexists') {
						$vaultExclude = 'on';
						error_log("No vault directory found");
					}
				}
				if ($vaultExclude != 'on') {
					error_log('Generated vault tar gz');
				}
			}

			// --- Write archive information --- //
			$archive = $doc->createElement('archive');
			$archive->setAttribute('id',$archiveId);
			$archive->setAttribute('name',$archiveName);
			$archive->setAttribute('datetime',$datetime->format('Y-m-d H:i:s'));
			$archive->setAttribute('description',$archiveDesc);

			if ($vaultExclude == 'on') {
				$archive->setAttribute('vault', 'No');
			}
			else {
				$archive->setAttribute('vault', 'Yes');
			}
			$root->appendChild($archive);

			$xml = $doc->saveXML();

			$err = $zip->addFromString('info.xml',$xml);
			if ($err === false) {
				$zip->close();
				if (file_exists($archived_root."/$archiveId.fcz")) {
					unlink($archived_root."/$archiveId.fcz");
				}
				unlink($status_file);
				if (file_exists("$tmp/context.tar.gz")) {
					unlink("$tmp/context.tar.gz");
				}
				if (file_exists("$dump")) {
					unlink("$dump");
				}
				if (empty($vaultDirList) === false) {
					/*--- Delete vault list --- */
					$i = 0;
					while ($vaultDirList[$i]) {
						if (file_exists($tmp."/vault_".$vaultDirList[$i]["id_fs"].".tar.gz")) {
							unlink($tmp."/vault_".$vaultDirList[$i]["id_fs"].".tar.gz");
						}
						$i++;
					}
				}
				if (file_exists($tmp."/vault_$id_fs.tar.gz")) {
					unlink($tmp."/vault_$id_fs.tar.gz");
				}
				$this->errorMessage = sprintf("Could not add 'info.xml' to archive: %s", $zip->getStatusString());
				return false;
			}

			// --- Save zip --- //
			$zip->close();



			// --- Delete status file --- //
			unlink($status_file);

			// --- Clean tmp directory --- //
			if (file_exists("$tmp/context.tar.gz")) {
				unlink("$tmp/context.tar.gz");
			}
			if (file_exists("$dump")) {
				unlink("$dump");
			}
			if (empty($vaultDirList) === false) {
				/*--- Delete vault list --- */
				$i = 0;
				while ($vaultDirList[$i]) {
					if (file_exists($tmp."/vault_".$vaultDirList[$i]["id_fs"].".tar.gz")) {
						unlink($tmp."/vault_".$vaultDirList[$i]["id_fs"].".tar.gz");
					}
					$i++;
				}
			}

			return $archiveId ;

		} else {
			$this->errorMessage = sprintf("Cannot create Zip archive '%s': %s", $zipfile, $zip->getStatusString());
			// --- Delete status file --- //
			unlink($status_file);
		}

		return false ;

	}

	private function getVaultList() {
		$pgservice_core = $this->getParamByName('core_db');
		$dbconnect = pg_connect("service=$pgservice_core");
		if ($dbconnect === false) {
			$this->errorMessage = "Error when trying to connect to database";
			return false;
		}
		$result = pg_query("SELECT id_fs, r_path FROM vaultdiskfsstorage ;");
		if ($result === false) {
			$this->errorMessage = "Error when trying to get databse info :: ".pg_last_error();
			return false;
		}
		$vaultList = pg_fetch_all($result);
		pg_close($dbconnect);
		return $vaultList;
	}

	/**
	 * Store the manifest of a downloaded module
	 * @param Module $module a Module object
	 * @return bool
	 */
	public function storeManifestForModule($module) {
		if( ! is_object($module) ) {
			$err = sprintf(__CLASS__."::".__FUNCTION__." "."not an object");
			$this->errorMessage = $err;
			return false;
		}

		$manifest = $module->getManifest();
		if( $manifest == '' ) {
			$err = sprintf(__CLASS__."::".__FUNCTION__." "."empty manifest for '%s'", $module->name);
			$this->errorMessage = $err;
			return $manifest;
		}

		$manifestDir = sprintf("%s/", $this->root);
		$manifestFile = sprintf("%s.manifest", $module->name);

		$tmpfile = tempnam($manifestDir, $manifestFile);
		if( $tmpfile === false ) {
			$err = sprintf(__CLASS__."::".__FUNCTION__." "."Error creating temp file in '%s'", $manifestDir);
			$this->errorMessage = $err;
			return false;
		}

		$fout = fopen($tmpfile, 'w');
		if( $fout === false ) {
			$err = sprintf(__CLASS__."::".__FUNCTION__." "."Error opening output file '%s' for writing.", $tmpfile);
			$this->errorMessage = $err;
			unlink($tmpfile);
			return false;
		}

		$ret = fwrite($fout, $manifest);
		if( $ret === false ) {
			$err = sprintf(__CLASS__."::".__FUNCTION__." "."Error writing manifest to '%s'.", $tmpfile);
			$this->errorMessage = $err;
			unlink($tmpfile);
			return false;
		}

		fclose($fout);

		$ret = rename($tmpfile, sprintf("%s/%s", $manifestDir, $manifestFile));
		if( $ret === false ) {
			$err = sprintf(__CLASS__."::".__FUNCTION__." "."Error moving '%s' to '%s'", $tmpfile, sprintf("%s/%s", $manifestDir, $manifestFile));
			$this->errorMessage = $err;
			unlink($tmpfile);
			return false;
		}

		return $manifest;
	}

	/**
	 * get the manifest of a module name
	 * @param Module $moduleName a Module object
	 * @return bool|string boolean false on error or the manifests content
	 */
	public function getManifestForModule($moduleName) {
		if( is_object($moduleName) ) {
			$moduleName = $moduleName->name;
		}

		$manifestFile = sprintf("%s/%s.manifest", $this->root, $moduleName);
		if( ! is_file($manifestFile) ) {
			$err = sprintf(__CLASS__."::".__FUNCTION__." "."Manifest file '%s' does not exists.", $manifestFile);
			$this->errorMessage = $err;
			return false;
		}

		$manifest = file_get_contents($manifestFile);
		if( $manifest === false ) {
			$err = sprintf(__CLASS__."::".__FUNCTION__." "."Error getting content from manifest file '%s'.", $manifestFile);
			$this->errorMessage = $err;
			return false;
		}

		return $manifest;
	}

	/**
	 * Delete the manifest file of a module name
	 * @param Module $moduleName a Module object
	 * @return bool|string boolean false on error or the manifests content
	 */
	public function deleteManifestForModule($moduleName) {
		if( is_object($moduleName) ) {
			$moduleName = $moduleName->name;
		}

		$manifestFile = sprintf("%s/%s.manifest", $this->root, $moduleName);
		if( ! file_exists($manifestFile) ) {
			return $manifestFile;
		}
		if( ! is_file($manifestFile) ) {
			$err = sprintf(__CLASS__."::".__FUNCTION__." "."'%s' is not a file.", $manifestFile);
			$this->errorMessage = $err;
			return false;
		}

		$ret = unlink($manifestFile);
		if( $ret === false ) {
			$err = sprintf(__CLASS__."::".__FUNCTION__." "."Error unlinking manifest file '%s'.", $manifestFile);
			$this->errorMessage = $err;
			return false;
		}

		return $manifestFile;
	}

	/**
	 * Delete files from the given module name
	 * @param Module $moduleName a Module object
	 * @return bool success
	 */
	public function deleteFilesFromModule($moduleName) {
		if( is_object($moduleName) ) {
			$moduleName = $moduleName->name;
		}

		$manifestEntries = $this->getManifestEntriesForModule($moduleName);
		if( $manifestEntries === false ) {
			$err = sprintf(__CLASS__."::".__FUNCTION__." "."Error getting manifest entries for module '%s': %s", $moduleName, $this->errorMessage);
			$this->errorMessage = $err;
			return false;
		}

		// Sort files in reverse order in order to be able to processs
		// removal of directories after their contained files
		usort($manifestEntries, array($this, "sortManifestEntriesByNameReverse"));

		foreach( $manifestEntries as $mentry ) {
			$fpath = sprintf("%s/%s", $this->root, $mentry['name']);

			if( ! file_exists($fpath) ) {
				continue;
			}

			$stat = lstat($fpath);
			if( $stat === false ) {
				error_log(__CLASS__."::".__FUNCTION__." ".sprintf("stat('%s') from module '%s' returned with error.", $fpath, $moduleName));
				continue;
			}

			if( ! is_link($fpath) && is_dir($fpath) ) {
				if( $mentry['type'] != 'd' ) {
					error_log(__CLASS__."::".__FUNCTION__." ".sprintf("Type mismatch for file '%s' from module '%s': type is 'd' while manifest says '%s'.", $fpath, $moduleName, $mentry['type']));
					continue;
				}
				if( $stat['nlink'] > 2 ) {
					continue;
				}
				$ret = @rmdir($fpath);
			} else {
				$ret = @unlink($fpath);
			}

			if( $ret === false ) {
				error_log(__CLASS__."::".__FUNCTION__." ".sprintf("Error removing '%s' (%s) from module '%s'.", $fpath, $mentry['type'], $moduleName));
			}
		}

		return true;
	}

    /**
     * Sort helper function for manifest entries
     * @param array $a a manifest entry array structure
     * @param array $b a manifest entry array structure
     * @return int
     */
	private function sortManifestEntriesByNameReverse($a, $b) {
		return strcmp($b['name'], $a['name']);
	}

	public function getManifestEntriesForModule($moduleName) {
		$manifest = $this->getManifestForModule($moduleName);
		$manifestLines = preg_split("/\n/", $manifest);
		$manifestEntries = array();

		foreach( $manifestLines as $line ) {
			$minfo = array();
			if( ! preg_match("|^(?P<type>.)(?P<mode>.........)\s+(?P<uid>.*?)/(?P<gid>.*?)\s+(?P<size>\d+)\s+(?P<date>\d\d\d\d-\d\d-\d\d\s+\d\d:\d\d(?::\d\d)?)\s+(?P<name>.*?)(?P<link>\s+->\s+.*?)?$|", $line, $minfo) ) {
				continue;
			}
			array_push($manifestEntries, $minfo);
		}

		return $manifestEntries;
	}

	/**
	 * Purge/remove parameters value that are associated
	 * with a module that is no more present in the context.
	 * @return bool success
	 */
	public function purgeUnreferencedParametersValue() {
		require_once ('class/Class.WIFF.php');

		$wiff = WIFF::getInstance();

		$xml = new DOMDocument();
		$xml->preserveWhiteSpace = false;
		$xml->formatOutput = true;
		$ret = $xml->load($wiff->contexts_filepath);
		if( $ret === false ) {
			$this->errorMessage = sprintf("Error opening XML file '%s'.", $wiff->contexts_filepath);
			return false;
		}

		$xpath = new DOMXPath($xml);

		$parametersValueNodeList = $xpath->query(sprintf("/contexts/context[@name='%s']/parameters-value/param", $this->name));
		if( $parametersValueNodeList->length <= 0 ) {
			return true;
		}

		$purgeNodeList = array();
		for( $i = 0; $i < $parametersValueNodeList->length; $i++ ) {
			$pv = $parametersValueNodeList->item($i);
			$moduleName = $pv->getAttribute('modulename');
			$module = $this->getModule($moduleName);
			if( $module === false || $pv->getAttribute('volatile') == 'yes' ) {
				array_push($purgeNodeList, $pv);
			}
		}

		foreach( $purgeNodeList as $node ) {
			$node->parentNode->removeChild($node);
		}

		$ret = $xml->save($wiff->contexts_filepath);
		if( $ret === false ) {
			$this->errorMessage = sprintf("Error saving contexts.xml '%s'.", $wiff->contexts_filepath);
			return false;
		}

		return true;

	}


	public function wsh($api_name, $args) {
		$cmd = sprintf('%s/wsh.php --api=%s', escapeshellarg($this->root), escapeshellarg($api_name));
		foreach ($args as $name => $value) {
			$cmd .= sprintf(' --%s=%s', $name, escapeshellarg($value));
		}

		system(sprintf("%s", $cmd), $ret);
		if ($ret != 0) {
			return 'Error Trying to delete crontab';
		}
		return;
	}

	/**
	 * Delete context
	 * @param boolean $res the result of the operation: boolean false|true
	 * @param boolean $opt
	 * @return string the error message
	 */
	public function delete(&$res, $opt = false) {
		$err_msg = '';
		$res = true;
		if ($opt === 'crontab' || $opt === false) {
			$args = array("cmd" => "unregister", 'file' => 'FREEDOM/freedom.cron');
			$ret = $this->wsh("crontab", $args);
			if( $ret ) {
				$err_msg .= $ret;
				error_log(__CLASS__."::".__FUNCTION__." ".sprintf("deleteContextCrontab returned with error: %s", $this->errorMessage));
			}
			error_log("crontab deleted");
		}
		if ($opt === 'vault' || $opt === false) {
			$ret = $this->deleteContextVault();
			if( $ret ) {
				$err_msg .= $ret;
				error_log(__CLASS__."::".__FUNCTION__." ".sprintf("deleteContextVault returned with error: %s", $this->errorMessage));
			}
			error_log("vault deleted");
		}
		if ($opt === 'database' || $opt === false) {
			$err = '';
			$ret = $this->deleteContextDatabaseContent($err);
			if( $ret === false ) {
				$err_msg .= $this->errorMessage;
				error_log(__CLASS__."::".__FUNCTION__." ".sprintf("deleteContextDatabaseContent returned with error: %s", $this->errorMessage));
			} elseif( $err != '' ) {
				$err_msg .= $err;
				error_log(__CLASS__."::".__FUNCTION__." ".sprintf("deleteContextDatabaseContent returned with warning: %s", $err));
			}
			/*
			if( $ret ) {
				$err_msg .= $ret;
				error_log(__CLASS__."::".__FUNCTION__." ".sprintf("deleteContextDatabaseContent returned with error: %s", $this->errorMessage));
			}
			if ($err != '') {
				$err_msg .= $$err;
			}
			*/
			error_log("database deleted");
		}
		if ($opt === 'root' || $opt === false) {
			$ret = $this->deleteContextRoot();
			if( $ret ) {
				$err_msg .= $ret;
				error_log(__CLASS__."::".__FUNCTION__." ".sprintf("deleteContextRoot returned with error: %s", $this->errorMessage));
			}
			error_log("root deleted");
		}
		if ($opt === 'unregister' || $opt === false) {
			if( $this->register == 'registered' ) {
				$ret = $this->deleteRegistrationConfiguration();
				if( $ret === false ) {
					$err_msg .= $this->errorMessage;
					error_log(__CLASS__."::".__FUNCTION__." ".sprintf("deleteRegistrationConfiguration returned with error: %s", $this->errrorMessage));
				}
			}
			$ret = $this->unregisterContextFromConfig();
			if( $ret ) {
				$res = false;
				$err_msg .= $ret;
				error_log(__CLASS__."::".__FUNCTION__." ".sprintf("unregisterContextFromConfig returned with error: %s", $this->errorMessage));
			}
			error_log("context unregister");
		}
		return $err_msg;
	}

	public function unregisterContextFromConfig() {
		$wiff = WIFF::getInstance();
		if( $wiff === false ) {
			$this->errorMessage .= sprintf("Could not get wiff instance.");
			return  sprintf("Could not get wiff instance.");;
		}

		$xml = new DOMDocument();
		$xml->preserveWhiteSpace = false;
		$xml->formatOutput = true;

		$ret = $xml->load($wiff->contexts_filepath);
		if( $ret === false ) {
			$this->errorMessage .= sprintf("Could not load contexts.xml");
			return sprintf("Could not load contexts.xml");
		}

		$xpath = new DOMXpath($xml);

		$contextNodeList = $xpath->query(sprintf("/contexts/context[@name='%s']", $this->name));
		if( $contextNodeList->length <= 0 ) {
			$this->errorMessage .= sprintf("Could not find a context with name '%s'!", $this->name);
			return sprintf("Could not find a context with name '%s'!", $this->name);
		}
		if( $contextNodeList->length > 1 ) {
			$this->errorMessage .= sprintf("There is more than one context with name '%s'!", $this->name);
			return sprintf("There is more than one context with name '%s'!", $this->name);
		}
		$contextNode = $contextNodeList->item(0);

		$xml->documentElement->removeChild($contextNode);

		$ret = $xml->save($wiff->contexts_filepath);
		if( $ret === false ) {
			$this->errorMessage .= sprintf("Error saving contexts.xml '%s'.", $wiff->contexts_filepath);
			return sprintf("Error saving contexts.xml '%s'.", $wiff->contexts_filepath);
		}

		return;
	}

	public function getContextVaultPathList(&$err) {
		$pgservice_core = $this->getParamByName('core_db');
		if( $pgservice_core == "" ) {
			$err = sprintf("Parameter 'core_db' not found or empty in context '%s'.\n", $this->name);
			$this->errorMessage .= $err;
			return false;
		}

		$conn = pg_connect(sprintf("service=%s", $pgservice_core));
		if( $conn === false ) {
			$err = sprintf("Error connecting to 'service=%s'.\n", $pgservice_core);
			$this->errorMessage .= $err;
			return false;
		}

		$res = pg_query($conn, "SELECT r_path FROM vaultdiskfsstorage");
		if( $res === false ) {
			$err = sprintf("Error fetching vaultdiskfsstorage.r_path from 'service=%s'.\n", $pgservice_core);
			$this->errorMessage .= $err;
			pg_close($conn);
			return false;
		}

		$pathList = array();
		while( $el = pg_fetch_assoc($res) ) {
			array_push($pathList, $el['r_path']);
		}

		pg_close($conn);
		return $pathList;
	}

	private function rm_Rf($path, &$err_list) {
		if( ! is_array($err_list) ) {
			$err = sprintf(__CLASS__."::".__FUNCTION__." "."err_list is not an array.");
			$this->errorMessage .= $err;
			error_log($err);
			return false;
		}

		$filetype = filetype($path);
		if( $filetype === false ) {
			$this->errorMessage .= sprintf(__CLASS__."::".__FUNCTION__." "."Could not get type for file '%s'.\n", $path);
			$err = sprintf("Could not get type for file '%s'.", $path);
			array_push($err_list, $err);
			error_log($this->errorMessage);
			return false;
		}

		if( $filetype == 'dir' ) {
			$recursive_ret = true;
			foreach( scandir($path) as $file ) {
				if( $file == "." || $file == ".." ) {
					continue;
				};
				$recursive_ret = ( $recursive_ret && $this->rm_Rf(sprintf("%s%s%s", $path, DIRECTORY_SEPARATOR, $file),$err_list));
			}

			$s = stat($path);
			if( $s === false ) {
				$this->errorMessage .= sprintf(__CLASS__."::".__FUNCTION__." "."Could not stat dir '%s'.\n", $path);
				$err = sprintf("Could not stat dir '%s'.", $path);
				array_push($err_list, $err);
				error_log($this->errorMessage);
				return false;
			}

			if( $s['nlink'] > 2 ) {
				$this->errorMessage = sprintf(__CLASS__."::".__FUNCTION__." "."Won't remove dir '%s' as it contains %s files.\n", $path, $s['nlink']-2);
				$err = sprintf("Won't remove dir '%s' as it contains %s files.", $path, $s['nlink']-2);
				array_push($err_list, $err);
				error_log($this->errorMessage);
				return false;
			}

			$ret = @rmdir($path);
			if( $ret === false ) {
				$this->errorMessage = sprintf(__CLASS__."::".__FUNCTION__." "."Error removing dir '%s'.\n", $path);
				$err = sprintf("Error removing dir '%s'.", $path);
				array_push($err_list, $err);
				error_log($this->errorMessage);
				return false;
			}

			return ($ret && $recursive_ret);
		}

		$ret = unlink($path);
		if( $ret === false ) {
			$this->errorMessage = sprintf(__CLASS__."::".__FUNCTION__." "."Error removing file '%s' (filetype=%s).\n", $path, $filetype);
			$err = sprintf("Error removing file '%s' (filetype=%s).", $path, $filetype);
			array_push($err_list, $err);
			error_log($this->errorMessage);
			return false;
		}

		return $ret;
	}

	public function deleteContextVault() {
		$vaultList = $this->getContextVaultPathList($err);
		if( $vaultList === false ) {
			return $err;
		}

		if( count($vaultList) <= 0 ) {
			return;
		}

		$ret = true;
		$err_list = array();
		foreach( $vaultList as $vault ) {
			$ret = ($ret && $this->rm_Rf($vault, $err_list) );
		}

		if( $ret === false ) {
			$this->errorMessage .= sprintf("Some errors occured while removing files from vaults:\n");
			$this->errorMessage .= join("\n", $err_list);
			$err = sprintf("Some errors occured while removing files from vaults:\n");
			$err .= join("\n", $err_list);
			return $err;
		}
		else {
			return;
		}
	}

	public function deleteContextRoot() {
		$err_list = array();
		$ret = $this->rm_Rf($this->root, $err_list);

		if( $ret === false ) {
			$this->errorMessage .= sprintf("Some errors occured while removing files from context root.\n");
			$this->errorMessage .= join("\n", $err_list);
			$err = sprintf("Some errors occured while removing files from context root.\n");
			$err .= join("\n", $err_list);
			return $err;
		}
		else {
			return ;
		}
	}

	public function deleteContextDatabaseContent(&$err) {
		$pgservice_core = $this->getParamByName('core_db');

		if( $pgservice_core == "" ) {
			$this->errorMessage .= sprintf("Parameter 'core_db' not found or empty in context '%s'.\n", $this->name);
			return false;
			// return sprintf("Empty pgservice_core after include of '%s'.\n", $dbaccess);
		}

		$conn = pg_connect(sprintf("service=%s", $pgservice_core));
		if( $conn === false ) {
			$this->errorMessage .= sprintf("Error connecting to 'service=%s'.\n", $pgservice_core);
			return false;
			// return sprintf("Error connecting to 'service=%s'.\n", $pgservice_core);
		}

		$res = pg_query($conn, sprintf("DROP SCHEMA public CASCADE"));
		if( $res === false ) {
			$this->errorMessage .= sprintf("Error dropping schema public.\n");
			$err .= sprintf("Error dropping schema public.\n");
		}
		$res = pg_query($conn, sprintf("CREATE SCHEMA public"));
		if( $res === false ) {
			$this->errorMessage .= sprintf("Error re-creating schema public.\n");
			$err .= sprintf("Error dropping schema public.\n");
		}

		foreach( array("family", "dav") as $schema ) {
			$res = pg_query($conn, sprintf("DROP SCHEMA %s CASCADE", pg_escape_string($schema)));
			if( $res === false ) {
				$this->errorMessage .= sprintf("Error dropping schema %s.", $schema);
				$err .= sprintf("Error dropping schema %s.", $schema);
			}
		}

		return true;
	}

	public function setRegister($register) {
		require_once ('class/Class.WIFF.php');
		require_once ('class/Class.Repository.php');

		if( ! is_bool($register) ) {
			$this->errorMessage = sprintf("Argument of %s::%s should be boolean (%s given).", __CLASS__, __FUNCTION__, gettype($register));
			return false;
		}

		$wiff = WIFF::getInstance();

		$contextsXml = new DOMDocument();
		$contextsXml->load($wiff->contexts_filepath);

		$contextsXPath = new DOMXPath($contextsXml);

		// Get this context
		$contextList = $contextsXPath->query("/contexts/context[@name='".$this->name."']");
		if( $contextList->length <= 0) {
			$this->errorMessage = sprintf("Could not get context with name '%s'.", $this->name);
			return false;
		}
		if( $contextList->length > 1 ) {
			$this->errorMessage = sprintf("Found more than 1 context with name '%s'.", $this->name);
			return false;
		}

		$contextNode = $contextList->item(0);
		$contextNode->setAttribute('register', ($register === true)?'registered':'unregistered');

		$ret = $contextsXml->save($wiff->contexts_filepath);
		if( $ret === false ) {
			$this->errorMessage = sprintf("Error writing file '%s'.", $wiff->contexts_filepath);
			return false;
		}

		return true;
	}

	public function sendConfiguration() {
		include_once('class/Class.StatCollector.php');

		if( $this->register != 'registered' ) {
			$this->errorMessage = sprintf("Context '%s' is not registered.", $this->name);
			error_log(__CLASS__."::".__FUNCTION__." ".$this->errorMessage);
			return true;
		}

		$wiff = WIFF::getInstance();
		$info = $wiff->getRegistrationInfo();
		if( $info === false ) {
			$this->errorMessage = sprintf("Could not get WIFF registration info.");
			return false;
		}

		$sc = new StatCollector($wiff, $this);
		$sc->collect();
		$stats = $sc->getXML();

		$rc = $wiff->getRegistrationClient();

		$res = $rc->add_context($info['mid'], $info['ctrlid'], $this->name, $stats);
		if( $res === false ) {
			$this->errorMessage = sprintf("Error add_context request: %s", $rc->last_error);
			return false;
		}

		if( $res['code'] >= 200 && $res['code'] < 300 ) {
			return true;
		}

		$this->errorMessage = sprintf("Unknwon response with code '%s': %s", $res['code'], $res['response']);
		return false;
	}

	public function deleteRegistrationConfiguration() {
		$wiff = WIFF::getInstance();

		$info = $wiff->getRegistrationInfo();
		if( $info === false ) {
			$this->errorMessage = sprintf("Error getting registration info: %s", $wiff->errorMessage);
			return false;
		}

		$rc = $wiff->getRegistrationClient();

		$res = $rc->delete_context($info['mid'], $info['ctrlid'], $this->name);
		if( $res === false ) {
			$this->errorMessage = sprintf("Error delete_context request: %s", $rc->last_error);
			return false;
		}

		if( $res['code'] >= 200 && $res['code'] < 300 ) {
			return true;
		}

		$this->errorMessage = sprintf("Unknown response with code '%s': %s", $res['code'], $res['response']);
		return false;
	}

	/**
	 * Expand "@PARAM_NAME" variables in a string.
	 *
	 * Supported notations:
	 * - "@PARAM_NAME" -> value of PARAM_NAME
	 * - "@{PARAM_NAME}" -> value of PARAM_NAME
	 * - "@@" -> literal "@"
	 *
	 * @param $str
	 * @return string
	 */
	public function expandParamsValues($str) {
		return self::_expandParamsValues($str, array(
			'escape' => '@',
			'begin' => '{',
			'allow_shorthand' => true,
			'vars' => array($this, '_expandParamsValuesHandler')
		));
	}

	/**
	 * Get the value of the given parameters name
	 *
	 * @param string $varName parameters name to expand
	 * @return string the value of the parameter
	 * @throws WIFFException
	 */
	private function _expandParamsValuesHandler($varName) {
		$staticVars = array(
			'CONTEXT_NAME' => $this->name,
		);
		if (isset($staticVars[$varName])) {
			return $staticVars[$varName];
		}
		$value = $this->getParamByName($varName);
		if ($value === false) {
			return '';
		}
		return $value;
	}

	/**
	 * Generic and configurable method to expand variables in a string.
	 *
	 * Behaviour is configured though the $conf hash argument:
	 * - 'escape' => the character that trigger variable expansion (default "@")
	 * - 'begin' => the variable beginning delimiter character (default "{")
	 * - 'end' => the variable ending delimiter character (default is the corresponding closing brace/paren/braquet of the 'begin' char)
	 * - 'allow_shorthand' => allow var expansion without begin/end delimiters (default "false")
	 * - 'vars' => an array containing ("VAR_name" => "value") associations, or a callback function
	 *             that will perform the expansion
	 *
	 * @param string $str the string to expand
	 * @param array $conf the config
	 * @return string the resulting string with expanded values
	 */
	private function _expandParamsValues($str, $conf = array()) {
		/* Config check */
		if (!isset($conf['escape'])) {
			$conf['escape'] = '@';
		}
		if (!isset($conf['begin'])) {
			$conf['begin'] = '{';
		}
		if (!isset($conf['end'])) {
			$conf['end'] = $conf['begin'];
			foreach (array('{}', '()', '[]', '<>') as $t) {
				if ($conf['begin'] == $t[0]) {
					$conf['end'] = $t[1];
					break;
				}
			}
		}
		if (!isset($conf['allow_shorthand']) || !is_bool($conf['allow_shorthand'])) {
			$conf['allow_shorthand'] = false;
		}
		if (!isset($conf['vars']) || (!is_array($conf['vars']) && !is_callable($conf['vars']))) {
			$conf['vars'] = array();
		}
		/* Parse the string */
		$tokens = preg_split('/([' . preg_quote($conf['escape'] . $conf['begin'] . $conf['end'], '/') . '])/', $str, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
		$stack = array();
		$var = null;
		$len = count($tokens);
		for ($i = 0; $i < $len; $i++) {
			if ($var === null) {
				if ($tokens[$i] == $conf['escape'] && $i < ($len - 1)) {
					if ($tokens[$i+1] == $conf['escape']) {
						$stack [] = $conf['escape'];
						$i++;
					} else if ($tokens[$i+1] == $conf['begin']) {
						$var = '';
						$i++;
					} else if ($conf['allow_shorthand']) {
						if (preg_match('/^(?<var>[a-zA-Z_][a-zA-Z0-9_]*)(?<remaining>.*)$/', $tokens[$i+1], $m)) {
							$stack [] = is_callable($conf['vars']) ? call_user_func_array($conf['vars'], array($m['var'])) : ((isset($conf['vars'][$m['var']])) ? $conf['vars'][$m['var']] : '');
							$tokens[$i+1] = $m['remaining'];
						}
					} else {
						$stack [] = $tokens[$i];
					}
				} else {
					$stack [] = $tokens[$i];
				}
			} else {
				if ($tokens[$i] == $conf['end']) {
					$stack [] = is_callable($conf['vars']) ? call_user_func_array($conf['vars'], array($var)) : ((isset($conf['vars'][$var])) ? $conf['vars'][$var] : '');
					$var = null;
				} else {
					$var .= $tokens[$i];
				}
			}
		}

		return join('', $stack);
	}
}
