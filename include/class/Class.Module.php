<?php

/**
 * Module Class
 */
class Module
{
  	/**
	 * xml attributes
	 */
	public $name;
	public $version;
	public $release;
	public $author;
	public $license;
	public $basecomponent;
	public $src;
	
	public $availableversion;
	
	public $description;

	private $context;
	private $repository;

	public $tmpfile;
	public $tmpdir;

	public $requires;

	public $xmlNode;

	/**
	 * @var boolean true if module is installed, false if module is available
	 */
	private $isInstalled ;
	
	/**
	 * @var boolean true if module is installed and has available update
	 */
	public $canUpdate = false ;
	
	/**
	 * @var string last error message
	 */
	private $errorMessage = '' ;
	
	public function __construct($context, $repository, $xmlNode, $isInstalled=false)
	{
		$this->context = $context;
		$this->repository = $repository;

		$this->parseXmlNode($xmlNode);
		
		$this->isInstalled = $isInstalled ;
	}
	
	public function __set($property,$value)
	{
		$this->$property = $value ;
	}
	
	public function __get($property)
	{
		return $this->$property ;
	}

	public function parseXmlNode($xmlNode) {
		$this->xmlNode = $xmlNode;
		// Load xmlNode attributes="value"
		foreach( array(
				 'name',
				 'version',
				 'release',
				 'author',
				 'license',
				 'basecomponent',
				 'src',
				 'tmpfile',
				 'errorstatus'
				 ) as $attrName ) {
			$this->$attrName = $xmlNode->getAttribute($attrName);
		}
		// Load xmlNode <description> elements
		$descriptionNodeList = $xmlNode->getElementsByTagName('description');
		if ($descriptionNodeList->length > 0) {
			$this->description = $descriptionNodeList->item(0)->nodeValue ;
		}
		
		
		// Load xmlNode <requires> elements
		$this->requires = array();
		$requiresNodeList = $xmlNode->getElementsByTagName('requires');
		if( $requiresNodeList->length > 0 ) {
			$requiresNode = $requiresNodeList->item(0);
			$installerNodeList = $requiresNode->getElementsByTagName('installer');
			if( $installerNodeList->length > 0 ) {
				$installerNode = $installerNodeList->item(0);
				$this->requires['installer'] = array(
					'version' => $installerNode->getAttribute('version'),
					'comp' => $installerNode->getAttribute('comp')
					);
			}
			$moduleNodeList = $requiresNode->getElementsByTagName('module');
			foreach( $moduleNodeList as $moduleNode ) {
				$this->requires['modules'][] = array(
					'name' => $moduleNode->getAttribute('name'),
					'version' => $moduleNode->getAttribute('version'),
					'comp' => $moduleNode->getAttribute('comp')
					);
			}
		}

		return $xmlNode;
	}

	/**
	 * Check dependency with other Modules in repositories
	 * Use getErrorMessage() to retrieve error
	 * @return array of object Module or false if dependency can not be satisfied
	 */
	public function checkDependency(){
		
	}
	
	/**
	 * Set error status
	 * @param string new error status of module
	 * @return boolean method success
	 */
	public function setErrorStatus($newErrorStatus)
	{
		$xml = new DOMDocument();
		$xml->load(WIFF::contexts_filepath);
		$xpath = new DOMXPath($xml);
		
		$modules = $xpath->query("/contexts/context[@name = '".$this->context->name."']/modules/module[@name = '".$this->name."']");
		$modules->item(0)->setAttribute('errorstatus',$newErrorStatus);
		$xml->save(WIFF::contexts_filepath);
		
		return true ;
	}
	
	/**
	 * Download archive in temporary folder
	 * @return temp filename of downloaded file, or false in case of error
	 */
	public function download(){
		$wiff = WIFF::getInstance();

		if( $this->repository === null ) {
		  $this->errorMessage = sprintf("Can't call '%s' method with null '%s'.", __FUNCTION__, 'repository');
		  return false;
		}

		$modUrl = $this->repository->baseurl.'/'.$this->src;
		$this->tmpfile = $wiff->downloadUrl($modUrl);
		if( $this->tmpfile === false ) {
			$this->errorMessage = sprintf("Could not download '%s'.", $modUrl);
			return false;
		}
		
		// Register downloaded module in context xml
		$info = $this->getInfoXml();
		
		$infoXML = new DOMDocument();
		$ret = $infoXML->loadXML($info);
		
		$module = $infoXML->firstChild;
		
		$contextsXML = new DOMDocument();
		$contextsXML->load(WIFF::contexts_filepath);
		$contextsXPath = new DOMXPath($contextsXML);
		
		$modules = $contextsXPath->query("/contexts/context[@name = '".$this->context->name."']/modules");
		
		$module = $contextsXML->importNode($module,true); // Import module to contexts xml document
		$module->setAttribute('status','downloaded');
		$module->setAttribute('tmpfile',$this->tmpfile);
		
		$modules->item(0)->appendChild($module);

		$contextsXML->save(WIFF::contexts_filepath);

		return $this->tmpfile;
	}

	/**
	 * Get manifest from temporary downloaded module archive
	 * @return string of the index of the content of the module content
	 */
	public function getManifest() {
		if( ! is_file($this->tmpfile) ) {
			$this->errorMessage = sprintf("Temporary file of downloaded module does not exists.");
			return false;
		}

		$cmd = 'tar zxOf '.escapeshellarg($this->tmpfile).' content.tar.gz | tar ztvf -';

		$manifest = shell_exec($cmd);

		return $manifest;
	}

	/**
	 * Get the content of the `info.xml' file from temporary downloaded
	 * module archive
	 * @return string of the content of `info.xml'
	 */
	public function getInfoXml() {
		if( ! is_file($this->tmpfile) ) {
			$this->errorMessage = sprintf("Temporary file of downloaded module does not exists.");
			return false;
		}

		$cmd = 'tar zxOf '.escapeshellarg($this->tmpfile).' info.xml';

		$infoxml = shell_exec($cmd);

		return $infoxml;
	}

	public function loadInfoXml() {
		$infoxml = $this->getInfoXml();
		if( $infoxml === false ) {
			return false;
		}
		
		$xml = new DOMDocument();
		$ret = $xml->loadXML($infoxml);
		if( $ret === false ) {
			$this->errorMessage = "Error loading XML string.";
			return false;
		}
		
		$xmlNode = $xml->firstChild;
		if( $xmlNode === null ) {
			$this->errorMessage = "firstChild is null.";
			return false;
		}
		
		return $this->parseXmlNode($xmlNode);
	}

	/**
	 * Remove temp files/dirs used by download/unpack/install process
	 * @return false in case of error
	 */
	public function cleanupDownload() {
		if( is_file($this->tmpfile) ) {
			unlink($this->tmpfile);
		}
		if( is_dir($this->tmpdir) ) {
			unlink($this->tmpdir);
		}
		return true;
	}
	
	/**
	 * Unpack archive in specified destination directory
	 * @param directory path to unpack the archive in (e.g. context root dir)
	 * @return string containing the given destination dir pr false in case of error
	 */
	public function unpack($destDir=''){
		include_once('lib/Lib.System.php');

		if( ! is_file($this->tmpfile) ) {
			$this->errorMessage = sprintf("Temporary file of downloaded module does not exists.");
			return false;
		}

		$cmd = 'tar -zxOf '.escapeshellarg($this->tmpfile).' content.tar.gz | tar '.(($destDir!='')?'-C '.escapeshellarg($destDir):'').' -zxf -';
		
		$ret = null;
		system($cmd, $ret);
		if( $ret != 0 ) {
			$this->errorMessage = sprintf("Error executing command [%s]", $cmd);
			return false;
		}

		return $destDir;
	}
	
	/**
	 * Delete module folder
	 * @return boolean success
	 */
	public function uninstall(){
		$this->errorMessage = sprintf("Method not yet implemented.");
		return false;
	}
	
	/**
	 * Get Module parameter list
	 * @return array of object Parameter or false in case of error
	 */
	public function getParameterList(){
	  $plist = array();

	  if( $this->context->name == null ) {
		  $this->errorMessage = sprintf("Can't call '%s' method with null '%s'.", __FUNCTION__, 'context');
		  return false;
	  }

	  $xml = new DOMDocument();
	  $ret = $xml->load(WIFF::contexts_filepath);
	  if( $ret === false ) {
	    $this->errorMessage = sprintf("Error loading XML file '%s'.", WIFF::contexts_filepath);
	    return false;
	  }

	  $contextsXpath = new DOMXPath($xml);
	  $params = $contextsXpath->query("/contexts/context[@name='".$this->context->name."']/modules/module[@name='".$this->name."']/parameters/param");
	  if( $params->length <= 0 ) {
	    $this->errorMessage = sprintf("Cound not find parameters for module '%s' in context '%s'.", $this->name, $this->context->name);
	    return false;
	  }

	  $pSeen = array();
	  foreach( $params as $param ) {
	    $paramName = $param->getAttribute('name');
	    if( array_key_exists($paramName, $pSeen) ) {
	      continue;
	    }
	    $pSeen[$paramName]++;

	    $p = new Parameter();
	    foreach( array('name', 'label', 'default', 'type', 'needed') as $attr ) {
	      $p->$attr = $param->getAttribute($attr);
	    }

	    $storedParamValue = $contextsXpath->query("/contexts/context[@name='".$this->context->name."']/parameters-value/param[@name='".$p->name."' and @modulename='". $this->name ."']");
	    if( $storedParamValue->length <= 0 ) {
		    $p->value = "";
	    } else {
		    $p->value = $storedParamValue->item(0)->getAttribute('value');
	    }

	    $plist[] = $p;
	  }
	  
	  return $plist;
	}
	
	/**
	 * Get Module parameter by name
	 * @return object Parameter or false if parameter not found
	 * @param string $name
	 */
	public function getParameter($name){
	  $plist = $this->getParameterList();
	  foreach( $plist as $p ) {
	    if( $p->name == $name ) {
	      return $p;
	    }
	  }
	  $this->errorMessage = sprintf("Parameter '%s' not found.", $name);
	  return false;
	}

	/**
	 * Store Module parameter
	 * @return the given object Parameter or false in case of error 
	 * @param object Parameter
	 */
	public function storeParameter($parameter) {
	  if( $this->context->name == null ) {
		  $this->errorMessage = sprintf("Can't call '%s' method with null '%s'.", __FUNCTION__, 'context');
		  return false;
	  }

	  $xml = new DOMDocument();
	  $xml->preserveWhiteSpace = false;
	  $xml->formatOutput = true;
	  $ret = $xml->load(WIFF::contexts_filepath);
	  if( $ret === false ) {
		  $this->errorMessage = sprintf("Error loading XML file '%s'.", WIFF::contexts_filepath);
		  return false;
	  }

	  $contextsXpath = new DOMXPath($xml);
	  $contextNodeList = $contextsXpath->query(sprintf("/contexts/context[@name='%s']", $this->context->name));
	  if( $contextNodeList->length <= 0 ) {
		  $this->errorMessage = sprintf("Could not find the module node.");
		  return false;
	  }
	  $contextNode = $contextNodeList->item(0);

	  $parametersValueList = $contextsXpath->query(sprintf("/contexts/context[@name='%s']/parameters-value", $this->context->name));
	  if( $parametersValueList->length <= 0 ) {
		  $parametersValueNode = $xml->createElement('parameters-value');
		  if( $parametersValueNode === false ) {
			  $this->errorMessage = sprintf("Could not create parameters-value element.");
			  return false;
		  }
		  $contextNode->appendChild($parametersValueNode);
	  } else {
		  $parametersValueNode = $parametersValueList->item(0);
	  }

	  $paramList = $contextsXpath->query(sprintf("/contexts/context[@name='%s']/parameters-value/param[@modulename='%s' and @name='%s']", $this->context->name, $this->name, $parameter->name));
	  if( $paramList->length <= 0 ) {
		  $param = $xml->createElement('param');
		  if( $param === false ) {
			  $this->errorMessage = sprintf("Could not create param element.");
			  return false;
		  }
		  $parametersValueNode->appendChild($param);
	  } else {
		  $param = $paramList->item(0);
	  }

	  $param->setAttribute('name', $parameter->name);
	  $param->setAttribute('modulename', $this->name);
	  $param->setAttribute('value', $parameter->value);

	  $ret = $xml->save(WIFF::contexts_filepath);
	  if( $ret === false ) {
		  $this->errorMessage = sprintf("Error saving XML to '%s'.", WIFF::contexts_filepath);
		  return false;
	  }

	  return $parameter;
	}
	
	/**
	 * Get Phase list
	 * @return array of object Phase
	 * @param string operation string code 'install|upgrade|uninstall|parameter'
	 */
	public function getPhaseList($operation){
	  switch($operation) {
	  case 'install':
	    return array('pre-install', 'unpack', 'post-install');
	    break;
	  case 'upgrade':
	    return array('pre-upgrade', 'unpack', 'post-upgrade');
	    break;
	  case 'uninstall':
	    return array('pre-remove', 'remove', 'post-remove');
	    break;
	  case 'parameter':
	    return array('param', 'post-param');
	  default:
	  }
	  return array();
	}
	
	/**
	 * Get phase by name
	 * @return object Phase
	 * @param string $name Phase name and XML tag
	 */
	public function getPhase($name){
		return new Phase($name, $this->xmlNode, $this);
	}

	/**
	 * Get last error message
	 * @return string error message
	 */
	public function getErrorMessage(){
		return $this->errorMessage;
	}

	/**
	 * Return required installer version for this module
	 * @return array( 'version' => $version, 'comp' => $comp ), or false
	 *         in case of error
	 */
	public function getRequiredInstaller() {
	  if( ! array_key_exists($this->requires, 'installer') ) {
	    return false;
	  }
	  $installer = $this->requires['installer'];
	  return $installer;
	}

	/**
	 * Return required modules name/version/etc. for this module
	 * @return array of array( 'name' => $name, 'version' => $version, [...] )
	 *         or false in case of error
	 */
	public function getRequiredModules() {
	  if( ! array_key_exists('modules', $this->requires) ) {
	    return array();
	  }
	  $modules = $this->requires['modules'];
	  return $modules;
	}

}

?>