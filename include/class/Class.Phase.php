<?php


/**
 * Phase Class
 */
class Phase
{
	
	public $xmlNode;
	public $name;

	/**
	 * @param $module object Module
	 * @param $phaseName the name of the phase
	 */
	public function __construct($phaseName, $xmlNode) {
		$this->name = $phaseName;
		$this->xmlNode = $xmlNode;
	}

	/**
	 * Get Process list
	 * @return array of object Process
	 */
	public function getProcessList(){
		$plist = array();

		if( ! in_array($this->name, array(
				       'pre-install', 'pre-upgrade', 'pre-remove',
				       'unpack', 'remove', 'param',
				       'register-xml', 'unregister-xml',
				       'post-install', 'post-upgrade', 'post-remove', 'post-param'
				       )
			    ) ) {
			return $plist;
		}
		
		$phaseNodeList = $this->xmlNode->getElementsByTagName($this->name);
		if( $phaseNodeList->length <= 0 ) {
			return $plist;
		}
		$phaseNode = $phaseNodeList->item(0);

		$processes = $phaseNode->childNodes;
		foreach( $processes as $process ) {
			$xmlStr = $process->ownerDocument->saveXML($process);
			error_log("[[[".$xmlStr."]]]");
			$plist[] = new Process($xmlStr);
		}

		return $plist;
	}
	
	/**
	 * Get Process by rank in list and xml
	 * @return object Process or false in case of error
	 * @param int $rank
	 */
	public function getProcess($rank){
		if( ! in_array($this->name, array(
				       'pre-install', 'pre-upgrade', 'pre-remove',
				       'unpack', 'remove', 'param',
				       'register-xml', 'unregister-xml',
				       'post-install', 'post-upgrade', 'post-remove', 'post-param'
				       )
			    ) ) {
			return false;
		}

		$phaseNodeList = $this->xmlNode->getElementsByTagName($this->name);
		if( $phaseNodeList->length <= 0 ) {
			return $plist;
		}
		$phaseNode = $phaseNodeList->item(0);

		$processe = $phaseNode->childNodes->item($rank);
		if( $process === null ) {
			return false;
		}

		return $process;
	}
	
	
}

?>