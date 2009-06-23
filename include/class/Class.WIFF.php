<?php

/**
 * Web Installer for Freedom Class
 */
class WIFF
{

    const contexts_filepath = 'conf/contexts.xml';

    const params_filepath = 'conf/params.xml';


    public $contexts_filepath = '';
    public $params_filepath = '';

    public $errorMessage = null;

    private static $instance;

    private function __construct()
    {
      $wiff_root = getenv('WIFF_ROOT');
      if( $wiff_root !== false ) {
	$wiff_root = $wiff_root.DIRECTORY_SEPARATOR;
      }

      $this->contexts_filepath = $wiff_root.WIFF::contexts_filepath;
      $this->params_filepath = $wiff_root.WIFF::params_filepath;
    }

    public static function getInstance()
    {
        if (! isset (self::$instance))
        {
            self::$instance = new WIFF();
        }
        return self::$instance;
    }

    /**
     * Check if WIFF has available update
     * @return boolean
     */
    public function needUpdate()
    {

    }

    /**
     * Update WIFF
     * @return boolean
     */
    public function update()
    {

    }

    /**
     * Get global repository list
     * @return array of object Repository
     */
    public function getRepoList()
    {
      require_once('class/Class.Repository.php');

        $repoList = array ();

        $xml = new DOMDocument();
        $xml->load($this->params_filepath);
        if ($xml === false)
        {
            $this->errorMessage = sprintf("Error loading XML file '%s'.", $this->contexts_filepath);
            return false;
        }

        $repositories = $xml->getElementsByTagName('access');

        if ($repositories->length > 0)
        {

            foreach ($repositories as $repository)
            {
                $repoList[] = new Repository($repository->getAttribute('name'), $repository->getAttribute('baseurl'), $repository->getAttribute('description'));
            }

        }

        return $repoList;

    }

    /**
     * Get Context list
     * @return array of object Context
     */
    public function getContextList()
    {
      require_once('class/Class.Repository.php');
      require_once('class/Class.Context.php');

        $contextList = array ();

        $xml = new DOMDocument();
        $xml->load($this->contexts_filepath);
        if ($xml === false)
        {
            $this->errorMessage = sprintf("Error loading XML file '%s'.", $this->contexts_filepath);
            return false;
        }

        $xpath = new DOMXpath($xml);
        $contexts = $xpath->query("/contexts/context");

        if ($contexts->length > 0)
        {

            foreach ($contexts as $context)
            {

                $repoList = array ();

                $repositories = $context->getElementsByTagName('access');

                foreach ($repositories as $repository)
                {
                    $repoList[] = new Repository($repository->getAttribute('name'), $repository->getAttribute('baseurl'), $repository->getAttribute('description'));
                }

                $contextList[] = new Context($context->getAttribute('name'), $context->getElementsByTagName('description')->item(0)->nodeValue, $context->getAttribute('root'), $repoList);

            }

        }

        return $contextList;

    }

    /**
     * Get Context by name
     * @return object Context or boolean false
     * @param string $name context name
     */
    public function getContext($name)
    {
      require_once('class/Class.Repository.php');
      require_once('class/Class.Context.php');

        $xml = new DOMDocument();
        $xml->load($this->contexts_filepath);
        if ($xml === false)
        {
            $this->errorMessage = sprintf("Error loading XML file '%s'.", $this->contexts_filepath);
            return false;
        }

        $xpath = new DOMXPath($xml);

        $query = "/contexts/context[@name = '".$name."']";
        $context = $xpath->query($query);

        if ($context->length >= 1)
        {

            $repoList = array ();

            $repositories = $context->item(0)->getElementsByTagName('access');

            foreach ($repositories as $repository)
            {
                $repoList[] = new Repository($repository->getAttribute('name'), $repository->getAttribute('baseurl'), $repository->getAttribute('description'), null);
            }

            $this->errorMessage = null;
            return new Context($context->item(0)->getAttribute('name'), $context->item(0)->getElementsByTagName('description')->item(0)->nodeValue, $context->item(0)->getAttribute('root'), $repoList);
        }

        $this->errorMessage = sprintf("Context '%s' not found.", $name);
        return false;

    }

    /**
     * Create Context
     * @return object Context or boolean false
     * @param string $name context name
     * @param string $root context root folder
     * @param string $desc context description
     */
    public function createContext($name, $root, $desc)
    {
        // If Context already exists, method fails.
        if ($this->getContext($name) !== false)
        {
            $this->errorMessage = sprintf("Context '%s' already exists.", $name);
            return false;
        }

        // Create or reuse directory
        if (is_dir($root))
        {
            if (!is_writable($root))
            {
                $this->errorMessage = sprintf("Directory '%s' is not writable.", $root);
                return false;
            }
            $dirListing = @scandir($root);
            if ($dirListing === false)
            {
                $this->errorMessage = sprintf("Error scanning directory '%s'.", $root);
                return false;
            }
            $dirListingCount = count($dirListing);
            if ($dirListingCount > 2)
            {
                $this->errorMessage = sprintf("Directory '%s' is not empty.", $root);
                return false;
            }
        } else
        {
            if (@mkdir($root) === false)
            {
                $this->errorMessage = sprintf("Error creating directory '%s'.", $root);
                return false;
            }
        }

	// Get absolute pathname if directory is not already in absolute form
	if( ! preg_match('|^/|', $root) ) {
	  $abs_root = realpath($root);
	  if( $abs_root === false ) {
	    $this->errorMessage = sprintf("Error getting absolute pathname for '%s'.", $root);
	    return false;
	  }
	  $root = $abs_root;
	}

        // Write contexts XML
        $xml = new DOMDocument();
        $xml->preserveWhiteSpace = false;
        $xml->load($this->contexts_filepath);
        $xml->formatOutput = true;

        $node = $xml->createElement('context');
        $context = $xml->getElementsByTagName('contexts')->item(0)->appendChild($node);

        $context->setAttribute('name', $name);

        $context->setAttribute('root', $root);

        $descriptionNode = $xml->createElement('description', $desc);
        $context->appendChild($descriptionNode);
		
		$moduleNode = $xml->createElement('modules');
		$context->appendChild($moduleNode);

        // Save XML to file
        $ret = $xml->save($this->contexts_filepath);
        if ($ret === false)
        {
            $this->errorMessage = sprintf("Error writing file '%s'.", $this->contexts_filepath);
            return false;
        }

        return $this->getContext($name);
    }

    /**
     * Get parameters list
     * @return array() containing 'key' => 'value' pairs
     */
    public function getParamList()
    {
        $plist = array ();

        $xml = new DOMDocument();
        $ret = $xml->load($this->params_filepath);
        if ($ret === false)
        {
            $this->errorMessage = sprintf("Error loading XML file '%s'.", $this->params_filepath);
            return false;
        }

        $xpath = new DOMXpath($xml);
        $params = $xpath->query("/wiff/parameters/param");
        if ($params === null)
        {
            $this->errorMessage = sprintf("Error executing XPath query '%s' on file '%s'.", "/wiff/parameters/param", $this->params_filepath);
            return false;
        }
        foreach ($params as $param)
        {
            $paramName = $param->getAttribute('name');
            $paramValue = $param->getAttribute('value');
            $plist[$paramName] = $paramValue;
        }

        return $plist;
    }

    /**
     * Get a specific parameter value
     * @return the value of the parameter or false in case of errors
     * @param string $paramName the parameter name
     */
    public function getParam($paramName)
    {
        $plist = $this->getParamList();

        if (array_key_exists($paramName, $plist))
        {
            return $plist[$paramName];
        }

        $this->errorMessage = sprintf("Parameter '%s' not found in contexts parameters.", $paramName);
        return false;
    }

    /**
     * Set a specific parameter value
     * @return return the value or false in case of errors
     * @param string $paramName the name of the parameter to set
     * @param string $paramValue the value of the parameter to set
     */
    public function setParam($paramName, $paramValue)
    {
        $xml = new DOMDocument();
        $ret = $xml->load($this->params_filepath);
        if ($ret === false)
        {
            $this->errorMessage = sprintf("Error loading XML file '%s'.", $this->params_filepath);
            return false;
        }

        $xpath = new DOMXpath($xml);
        $params = $xpath->query("/wiff/parameters/param[@name='$paramName']");
        if ($params === null)
        {
            $this->errorMessage = sprintf("Error executing XPath query '%s' on file '%s'.", "/wiff/parameters/param[@name='$paramName']", $this->params_filepath);
            return false;
        }
        foreach ($params as $param)
        {
            $param->setAttribute('value', $paramValue);
        }

        $ret = $xml->save($this->params_filepath);
        if ($ret === false)
        {
            $this->errorStatus = false;
            $this->errorMessage = sprintf("Error writing file '%s'.", $this->params_filepath);
            return false;
        }

        return $paramValue;
    }

    /**
     * download the file pointed by the URL to a temporary file
     * @ return the name of a temporary file holding the retrieved data
     *   or false in case of error
     * @ params the URL of the file to retrieve
     */
    public function downloadUrl($url)
    {
        if (preg_match('/^https?:/i', $url))
        {
            return $this->downloadHttpUrl($url);
        } else if (preg_match('/^ftp:/i', $url))
        {
            return $this->downloadFtpUrl($url);
        } else
        {
            // treat url as a pathname to a local file
            return $this->downloadLocalFile($url);
        }
        return false;
    }

    public function downloadHttpUrl($url)
    {
        return $this->downloadHttpUrlWget($url);
    }

    public function downloadFtpUrl($url)
    {
        return $this->downloadHttpUrlWget($url);
    }

    public function downloadLocalFile($url)
    {
        $tmpfile = tempnam(null, 'WIFF_downloadLocalFile');
        if ($tmpfile === false)
        {
            $this->errorMessage = sprintf( __CLASS__ ."::". __FUNCTION__ ." "."Error creating temporary file.");
            return false;
        }

        $ret = copy($url, $tmpfile);
        if ($ret === false)
        {
            $this->errorMessage = sprintf( __CLASS__ ."::". __FUNCTION__ ." "."Error copying file '%s' to '%s'.", $url, $tmpfile);
            return false;
        }

        $this->errorMessage = "";
        return $tmpfile;
    }


    public function downloadHttpUrlWget($url)
    {
        include_once ('lib/Lib.System.php');

        $tmpfile = tempnam(null, 'WIFF_downloadHttpUrlWget');
        if ($tmpfile === false)
        {
            $this->errorMessage = sprintf( __CLASS__ ."::". __FUNCTION__ ." "."Error creating temporary file.");
            return false;
        }

        $envs = array ();
        if ($this->getParam('use-proxy') === 'yes')
        {
            $proxy_host = $this->getParam('proxy-host');
            if ($proxy_host !== false && $proxy_host != '')
            {
                $http_proxy = "http://".$proxy_host;
                $proxy_port = $this->getParam('proxy-port');
                if ($proxy_port !== false && $proxy_port != '')
                {
                    $http_proxy .= ":".$proxy_port;
                }
            }
            $envs['http_proxy'] = $http_proxy;
            $envs['https_proxy'] = $http_proxy;
            $envs['ftp_proxy'] = $http_proxy;
        }

        $wget_path = LibSystem::getCommandPath('wget');
        if ($wget_path === false)
        {
            unlink($tmpfile);
            $this->errorMessage = sprintf( __CLASS__ ."::". __FUNCTION__ ." "."Command '%s' not found in PATH.", 'wget');
            return false;
        }

        $wget_opts = array ();
        $wget_opts[] = $wget_path;
        $wget_opts[] = '--no-check-certificate';
        $wget_opts[] = "-q";
        $wget_opts[] = "-O";
        $wget_opts[] = escapeshellarg($tmpfile);
        $proxy_username = $this->getParam('proxy-username');
        if ($proxy_username !== false && $proxy_username != '')
        {
            $wget_opts[] = '--proxy-user='.escapeshellarg($proxy_username);
        }
        $proxy_password = $this->getParam('proxy-password');
        if ($proxy_password !== false && $proxy_password != '')
        {
            $wget_opts[] = '--proxy-password='.escapeshellarg($proxy_password);
        }
        $wget_opts[] = escapeshellarg($url);

        foreach ($envs as $var=>$value)
        {
            putenv(sprintf("%s=%s", $var, $value));
        }

        $cmd = join(' ', $wget_opts);
        $out = system("$cmd > /dev/null", $ret);
        if (($out === false) || ($ret != 0))
        {
            unlink($tmpfile);
            $this->errorMessage = sprintf( __CLASS__ ."::". __FUNCTION__ ." "."Error fetching '%s' with '%s'.", $url, $cmd);
            return false;
        }

        return $tmpfile;
    }

    public function downloadHttpUrlFopen($url)
    {
        $tmpfile = tempnam(null, 'WIFF_downloadHTtpUrlFopen');
        if ($tmpfile === false)
        {
            $this->errorMessage = sprintf( __CLASS__ ."::". __FUNCTION__ ." "."Error creating temporary file.");
            return false;
        }

        $fout = fopen($tmpfile, 'w');
        if ($fout === false)
        {
            unlink($tmpfile);
            $this->errorMessage = sprintf( __CLASS__ ."::". __FUNCTION__ ." "."Error opening output file '%s' for writing.");
            return false;
        }

        $fin = fopen($url, 'r');
        while (!feof($fin))
        {
            $data = fread($fin, 8*1024);
            if ($data === false)
            {
                fclose($fin);
                fclose($fout);
                unlink($tmpfile);
                $this->errorMessage = sprintf( __CLASS__ ."::". __FUNCTION__ ." "."Error reading from input filedescriptor.");
                return false;
            }
            $ret = fwrite($fout, $data);
            if ($ret === false)
            {
                fclose($fin);
                fclose($fout);
                unlink($tmpfile);
                $this->errorMessage = sprintf( __CLASS__ ."::". __FUNCTION__ ." "."Error writing to output filedescriptor.");
                return false;
            }
        }
        fclose($fin);

        return $tmpfile;
    }

    public function DOMDocumentLoadXML($DOMDocument, $xmlFile) {
      $fh = open($xmlFile, "rw");
      if( $fh === false ) {
	$this->errorMessage = sprintf(__CLASS__."::".__FUNCTION__." "."Could not open '%s'.", $xmlFile);
	return false;
      }

      if( flock($fh, LOCK_EX) === false ) {
	$this->errorMessage = sprintf(__CLASS__."::".__FUNCTION__." "."Could not get lock on '%s'.", $xmlFile);
	fclose($fh);
	return false;
      }

      $ret = $DOMDocument->load($xmlFile);
      
      flock($fh, LOCK_UN);
      fclose($fh);
      
      return $ret;
    }

    public function DOMDocumentSaveXML($DOMDocument, $xmlFile) {
      $fh = open($xmlFile, "rw");
      if( $fh === false ) {
	$this->errorMessage = sprintf(__CLASS__."::".__FUNCTION__." "."Could not open '%s'.", $xmlFile);
	return false;
      }

      if( flock($fh, LOCK_EX) === false ) {
	$this->errorMessage = sprintf(__CLASS__."::".__FUNCTION__." "."Could not get lock on '%s'.", $xmlFile);
	fclose($fh);
	return false;
      }

      $ret = $DOMDocument->save($xmlFile);
      
      flock($fh, LOCK_UN);
      fclose($fh);
      
      return $ret;
    }

}

?>
