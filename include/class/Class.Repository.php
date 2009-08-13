<?php

/**
 * Repository Class
 */
class Repository
{
	
	public $name ;
	public $baseurl ;
	public $description ;
	
	public $protocol ;
	
	
	private $login ;
	private $password ;
	
	private $context ;
	
	private $contenturl ;
	
	public $errorMessage = '';

	public function __construct($xml,$context)
	{
		$this->name = $xml->getAttribute('name') ;
		$this->baseurl = $xml->getAttribute('baseurl') ;
		$this->description = $xml->getAttribute('description') ;
		
		if(!$this->baseurl)
		{
			$this->protocol = $xml->getAttribute('protocol');
			$this->host = $xml->getAttribute('host');
			$this->path = $xml->getAttribute('path');
			
			$this->login = $xml->getAttribute('login');
			$this->password = $xml->getAttribute('password');
		}
		
		
		$this->contenturl = $this->getUrl() .'/content.xml' ;
		$this->context = $context ;
	}
	
	public function __set($property,$value)
	{
		$this->$property = $value ;
	}
	
	public function getUrl()
	{
		if($this->baseurl)
		{
			return $this->baseurl ;
		} else {
			return $this->protocol . '://' . $this->login . ':' . $this->password . '@' . $this->host . '/' . $this->path ;
		}
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