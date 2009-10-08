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

    public $errorMessage;

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
        require_once ('class/Class.WIFF.php');
        require_once ('lib/Lib.Wcontrol.php');

        $wiff = WIFF::getInstance();

        putenv("WIFF_CONTEXT_NAME=".$this->phase->module->context->name);
        putenv("WIFF_CONTEXT_ROOT=".$this->phase->module->context->root);

        $cwd = getcwd();

        $ret = chdir($this->phase->module->context->root);
        if ($ret === false)
        {
            return array (
            'ret'=>false,
            'output'=>sprintf("Could not chdir to %s.", $this->phase->module->context->root)
            );
        }

        $result = wcontrol_eval_process($this);

        chdir($cwd);
		
		if(!$return){
			Debug::log($this->errorMessage);
		}

        return $result;
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

}
?>
