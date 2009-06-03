<?php


/**
 * Phase Class
 */
class Phase
{

    public $xmlNode;
    public $name;

	public $module;

    /**
     * @param $module object Module
     * @param $phaseName the name of the phase
     */
    public function __construct($phaseName, $xmlNode, $module)
    {
        $this->name = $phaseName;
        $this->xmlNode = $xmlNode;
		$this->module = $module;
    }

    /**
     * Get Process list
     * @return array of object Process
     */
    public function getProcessList()
    {
        $plist = array ();

        if (!in_array($this->name, array (
        'pre-install', 'pre-upgrade', 'pre-remove',
        'unpack', 'remove', 'param',
        'post-install', 'post-upgrade', 'post-remove', 'post-param'
        )
        ))
        {
            return $plist;
        }

        $phaseNodeList = $this->xmlNode->getElementsByTagName($this->name);
        if ($phaseNodeList->length <= 0)
        {
            return $plist;
        }
        $phaseNode = $phaseNodeList->item(0);

        $processes = $phaseNode->childNodes;
        foreach ($processes as $process)
        {
            if (!($process instanceof DomComment))
            {
                $xmlStr = $process->ownerDocument->saveXML($process);
                error_log("[[[".$xmlStr."]]]");

                $xmlStr = ltrim($xmlStr); // @TODO While making this loop, there are occurencies of $xmlStr composed of spaces only. Check why. The ltrim correct this but should not by required.
                if ($xmlStr != '')
                {
                    $plist[] = new Process($xmlStr,$this);
                }
            }

        }

        return $plist;
    }

    /**
     * Get Process by rank in list and xml
     * @return object Process or false in case of error
     * @param int $rank
     */
    public function getProcess($rank)
    {
//        if (!in_array($this->name, array (
//        'pre-install', 'pre-upgrade', 'pre-remove',
//        'unpack', 'remove', 'param',
//        'register-xml', 'unregister-xml',
//        'post-install', 'post-upgrade', 'post-remove', 'post-param'
//        )
//        ))
//        {
//            return false;
//        }
//
//        $phaseNodeList = $this->xmlNode->getElementsByTagName($this->name);
//        if ($phaseNodeList->length <= 0)
//        {
//            return $plist;
//        }
//        $phaseNode = $phaseNodeList->item(0);
//
//        $process = $phaseNode->childNodes->item($rank);
//        if ($process === null)
//        {
//            return false;
//        }

		$processList = $this->getProcessList();
		

        return $processList[$rank];
    }


}

?>
