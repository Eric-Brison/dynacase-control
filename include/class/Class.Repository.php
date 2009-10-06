<?php

/**
 * Repository Class
 */
class Repository
{
    public $use;

    public $name;
    public $baseurl;
    public $description;

    public $protocol;
    public $host;
    public $path;

    private $login;
    private $password;

    private $context;

    private $contenturl;

    public $url;

    public $errorMessage = '';

    public function __construct($xml, $context = null)
    {
        $this->use = $xml->getAttribute('use');

        if ($this->use != '')
        {

            $xml = new DOMDocument();
            $xml->load(WIFF::params_filepath);
            if ($xml === false)
            {
                $this->errorMessage = sprintf("Error loading XML file '%s'.", $this->contexts_filepath);
                return false;
            }

            $xpath = new DOMXPath($xml);

            // Get repository with this name from WIFF repositories
            $wiffRepoList = $xpath->query("/wiff/repositories/access[@name='".$this->use ."']");
            if ($wiffRepoList->length == 0)
            {
                // If there is no repository with such name
                $this->errorMessage = "Repository ".$this->use ." does not exist.";
                return false;
            } else if ($wiffRepoList->length > 1)
            {
                // If there is more than one repository with such name
                $this->errorMessage = "More than one repository with name ".$this->use .".";
                return false;
            }

            $repository = $wiffRepoList->item(0);

            $this->name = $repository->getAttribute('name');

            $this->baseurl = $repository->getAttribute('baseurl');
            $this->description = $repository->getAttribute('description');

            if ($this->baseurl == '')
            {
                $this->protocol = $repository->getAttribute('protocol');
                $this->host = $repository->getAttribute('host');
                $this->path = $repository->getAttribute('path');
                $this->login = $repository->getAttribute('login');
                $this->password = $repository->getAttribute('password');
            }

        } else
        {

            $this->name = $xml->getAttribute('name');

            $this->baseurl = $xml->getAttribute('baseurl');
            $this->description = $xml->getAttribute('description');

            if ($this->baseurl == '')
            {
                $this->protocol = $xml->getAttribute('protocol');
                $this->host = $xml->getAttribute('host');
                $this->path = $xml->getAttribute('path');

                $this->login = $xml->getAttribute('login');
                $this->password = $xml->getAttribute('password');
            }

        }

        $this->contenturl = $this->getUrl().'/content.xml';
        $this->context = $context;

    }

    public function __set($property, $value)
    {
        $this->$property = $value;
    }

    public function getUrl()
    {
        if ($this->baseurl)
        {
            $this->url = $this->baseurl;
        } else
        {
            $this->url = $this->protocol.'://'.$this->login.':'.$this->password.'@'.$this->host.'/'.$this->path;
        }
        return $this->url;
    }

    /**
     * Get Module list (available modules on repository)
     * @return array of object Module
     */
    public function getModuleList()
    {
        require_once ('class/Class.WIFF.php');
        require_once ('class/Class.Module.php');

        $wiff = WIFF::getInstance();
        $tmpfile = $wiff->downloadUrl($this->contenturl);
        if ($tmpfile === false)
        {
            $this->errorMessage = $wiff->errorMessage;
            return false;
        }

        $xml = new DOMDocument();
        $ret = $xml->load($tmpfile);
        if ($ret === false)
        {
            unlink($tmpfile);
            $this->errorMessage = sprintf("Error loading XML file '%s'.", $tmpfile);
            return false;
        }

        $xpath = new DOMXPath($xml);

        $modules = $xpath->query("/repo/modules/module");

        $moduleList = array ();
        foreach ($modules as $module)
        {
            $moduleList[] = new Module($context, $this, $module, false);
        }

        unlink($tmpfile);
        return $moduleList;
    }

}

?>
