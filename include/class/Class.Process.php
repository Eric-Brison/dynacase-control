<?php

/**
 * Process Class
 */
class Process
{
    /**
     * @var string process description xml
     */
    private $xmlStr;
    public $name;
    public $attributes;
    public $label;
    public $help;
    private $type;

    private $phase;

    public function __construct($xmlStr = "", $phase)
    {

        $this->xmlStr = $xmlStr;
        $this->attributes = array ();
        $this->type = "";
        $this->phase = $phase;

        $doc = new DOMDocument();
        $ret = $doc->loadXML($this->xmlStr);
        if ($ret === false)
        {
            return;
        }

        $node = $doc->childNodes->item(0);
        if ($node === null)
        {
            return;
        }

        $this->name = $node->nodeName;
        foreach ($node->attributes as $attr)
        {
            $this->attributes[$attr->name] = $attr->value;
        }
        $this->label = $node->getElementsByTagName('label')->item(0)->nodeValue;
        $this->help = $node->getElementsByTagName('help')->item(0)->nodeValue;

        return;
    }


    /**
     * Execute process
     * Use getErrorMessage() to retrieve error
     * @return boolean success
     */
    public function execute()
    {
        include_once ('lib/Lib.Wcontrol.php');
		$return = wcontrol_eval_process($this);
		if(!$return && !$this->attributes['optional'] == 'yes')
		{
			$this->phase->module->setErrorStatus($this->phase->name);
		}
        return $return ;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getAttribute($attrName)
    {
        if (array_key_exists($attrName, $this->attributes))
        {
            return $this->attributes[$attrName];
        }
        return "";
    }

    /**
     * Get error message
     * @return string error message or boolean false
     */
    public function getErrorMessage()
    {

    }


}

?>
