<?php

/**
 * Process Class
 * @author Anakeen
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License
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
    public $type;

    public $phase;

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

	if( $this->label == "" ) {
	  $this->label = $this->computeLabel();
	}

        return;

    }

    private function computeLabel() {
      $label = "";
      if( $this->name == 'check' ) {
	if( $this->attributes['type'] == 'syscommand' ) {
	  $label = sprintf('Check system command %s', $this->attributes['command']);
	} elseif( $this->attributes['type'] == 'phpfunction' ) {
	  $label = sprintf('Check php function %s', $this->attributes['function']);
	} elseif( $this->attributes['type'] == 'phpclass' ) {
	  $label = sprintf('Check php class %s', $this->attributes['class']);
	} elseif( $this->attributes['type'] == 'pearmodule' ) {
	  $label = sprintf('Check pear module %s', $this->attributes['module']);
	} elseif( $this->attributes['type'] == 'apachemodule' ) {
	  $label = sprintf('Check apache module %s', $this->atributes['module']);
	} else {
	  $label = sprintf("Check %s", $this->attributes['type']);
	}
      } elseif( $this->name == 'process' ) {
	$label = sprintf('Process %s', $this->attributes['command']);
      } elseif( $this->name == 'download' ) {
	$label = sprintf('Download %s', $this->attributes['href']);
      } else {
	$label = sprintf("<unknwon>");
      }

      return $label;
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
	require_once('class/Class.Debug.php');

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
