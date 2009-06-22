<?php

/**
 * Repository Class
 */
class Repository
{
	
	public $name ;
	public $baseurl ;
	public $description ;
	
	private $context ;
	
	private $contenturl ;
	
	public $errorMessage = '';

	public function __construct($name,$url,$description,$context = null)
	{
		$this->name = $name ;
		$this->baseurl = $url ;
		$this->description = $description ;
		$this->contenturl = $this->baseurl.'/content.xml' ;
		$this->context = context ;
	}
	
	public function __set($property,$value)
	{
		$this->$property = $value ;
	}
	
	/**
	 * Get Module list (available modules on repository)
	 * @return array of object Module
	 */
	public function getModuleList()
	{
	  require_once('class/Class.WIFF.php');
	  require_once('class/Class.Module.php');

		$wiff = WIFF::getInstance();
		$tmpfile = $wiff->downloadUrl($this->contenturl);
		if( $tmpfile === false ) {
			$this->errorMessage = $wiff->errorMessage;
			return false;
		}

		$xml = new DOMDocument();
		$ret = $xml->load($tmpfile);
		if( $ret === false ) {
			unlink($tmpfile);
			$this->errorMessage = sprintf("Error loading XML file '%s'.", $tmpfile);
			return false;
		}
		
		$xpath = new DOMXPath($xml);
		
		$modules = $xpath->query("/repo/modules/module");
		
		$moduleList = array();		
		foreach($modules as $module)
		{
		  $moduleList[] = new Module($context, $this, $module, false);
		}

		unlink($tmpfile);
		return $moduleList;
	}

}

?>