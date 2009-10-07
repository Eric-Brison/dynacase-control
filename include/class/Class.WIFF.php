<?php

/**
 * Web Installer for Freedom Class
 */
class WIFF
{

    const contexts_filepath = 'conf/contexts.xml';
    const params_filepath = 'conf/params.xml';

    const available_host = "ftp://ftp.freedom-ecm.org/";
    const available_url = "2.14/tarball/";

    public $contexts_filepath = '';
    public $params_filepath = '';

    public $errorMessage = null;

    public $archiveFile;

    private static $instance;

    private function __construct()
    {
        $wiff_root = getenv('WIFF_ROOT');
        if ($wiff_root !== false)
        {
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
     * Get WIFF version
     * @return string
     */
    public function getVersion()
    {
        $wiff_root = getenv('WIFF_ROOT');
        if ($wiff_root !== false)
        {
            $wiff_root = $wiff_root.DIRECTORY_SEPARATOR;
        }

        if (!$fversion = fopen($wiff_root.'VERSION', 'r'))
        {
            $this->errorMessage = sprintf("Error when opening VERSION file.");
            return false;
        }

        if (!$frelease = fopen($wiff_root.'RELEASE', 'r'))
        {
            $this->errorMessage = sprintf("Error when opening RELEASE file.");
            return false;
        }

        $version = trim(fgets($fversion));
        $release = trim(fgets($frelease));

        fclose($fversion);
        fclose($frelease);

        return $version.'-'.$release;
    }

    /** 
     * Get current available WIFF version
     * @return string
     */
    public function getAvailVersion()
    {

        $tmpfile = $this->downloadUrl(self::available_host.self::available_url.'content.xml');
        if ($tmpfile === false)
        {
            $this->errorMessage = 'Error when retrieving repository for wiff update.';
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

        $return = false;

        foreach ($modules as $module)
        {
            $name = $module->getAttribute('name');
            if ($name == 'freedom-wiff')
            {
                $version = $module->getAttribute('version');
                $release = $module->getAttribute('release');
                $return = $version.'-'.$release;
            }

        }

        unlink($tmpfile);

        return $return;

    }

    public function hasPasswordFile()
    {

        @$accessFile = fopen('.htaccess', 'r');
        @$passwordFile = fopen('.htpasswd', 'r');

        if (!$accessFile || !$passwordFile)
        {
            return false;
        } else
        {
            return true;
        }

    }

    public function createPasswordFile($login, $password)
    {

        @$accessFile = fopen('.htaccess', 'w');
        @$passwordFile = fopen('.htpasswd', 'w');

        fwrite($accessFile,
        "AuthUserFile ".getenv('WIFF_ROOT')."/.htpasswd
AuthGroupFile /dev/null
AuthName 'Veuillez vous identifier'
AuthType Basic

<Limit GET POST>
require valid-user
</Limit>"
        );

        fwrite($passwordFile,
        $login.':'.crypt($password)
        );

        fclose($accessFile);
        fclose($passwordFile);

        return true;

    }

    /**
     * Compare WIFF versions
     * @return
     * @param string $v1
     * @param string $r1
     * @param string $v2
     * @param string $r2
     */
    private function compareVersion($v1, $r1, $v2, $r2)
    {
        $ver1 = preg_split('/\./', $v1, 3);
        $rel1 = $r1;
        $ver2 = preg_split('/\./', $v2, 3);
        $rel2 = $r2;

        $str1 = sprintf("%03d%03d%03d%03d", $ver1[0], $ver1[1], $ver1[2], $rel1);
        $str2 = sprintf("%03d%03d%03d%03d", $ver2[0], $ver2[1], $ver2[2], $rel2);

        return strcmp($str1, $str2);
    }

    /**
     * Check if WIFF has available update
     * @return boolean
     */
    public function needUpdate()
    {
        $vr = $this->getVersion();
        $svr = preg_split('/\-/', $vr, 2);
        $v1 = $svr[0];
        $r1 = $svr[1];

        $avr = $this->getAvailVersion();
        $savr = preg_split('/\-/', $avr, 2);
        $v2 = $savr[0];
        $r2 = $savr[1];

        return $this->compareVersion($v2, $r2, $v1, $r1) == 1?true:false;

    }

    /**
     * Download latest WIFF file archive
     * @return
     */
    private function download()
    {
        $this->archiveFile = $this->downloadUrl(self::available_host.self::available_url.'freedom-wiff-current.tar.gz');
        return $this->archiveFile;
    }

    /**
     * Unpack archive in specified destination directory
     * @param directory path to unpack the archive in (e.g. context root dir)
     * @return string containing the given destination dir pr false in case of error
     */
    private function unpack()
    {
        include_once ('lib/Lib.System.php');

        if (!is_file($this->archiveFile))
        {
            $this->errorMessage = sprintf("Archive file has not been downloaded.");
            return false;
        }

        $cmd = 'tar xf '.escapeshellarg($this->archiveFile).' --strip-components 1';

        $ret = null;
        system($cmd, $ret);
        if ($ret != 0)
        {
            $this->errorMessage = sprintf("Error executing command [%s]", $cmd);
            return false;
        }

        return true;
    }

    /**
     * Update WIFF
     * @return boolean
     */
    public function update()
    {
        $this->download();
        $this->unpack();
    }

    /**
     * Get global repository list
     * @return array of object Repository
     */
    public function getRepoList()
    {
        require_once ('class/Class.Repository.php');

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
                $repoList[] = new Repository($repository);
            }

        }

        return $repoList;

    }

    /**
     * Add repository to global repo list
     * @return boolean
     */
    public function createRepo($name, $description, $baseurl, $protocol, $host, $path, $login, $password)
    {
        require_once ('class/Class.Repository.php');

        $xml = new DOMDocument();
        $xml->load($this->params_filepath);
        if ($xml === false)
        {
            $this->errorMessage = sprintf("Error loading XML file '%s'.", $this->params_filepath);
            return false;
        }

        $xPath = new DOMXPath($xml);

        // Get repository with this name from WIFF repositories
        $wiffRepoList = $xPath->query("/wiff/repositories/access[@name='".$name."']");
        if ($wiffRepoList->length != 0)
        {
            // If there is already a repository with same name
            $this->errorMessage = "Repository with same name already exists.";
            return false;
        }

        // Add repository to this context
        $node = $xml->createElement('access');
        $repository = $xml->getElementsByTagName('repositories')->item(0)->appendChild($node);

        $repository->setAttribute('name', $name);
        $repository->setAttribute('description', $description);
        $repository->setAttribute('baseurl', $baseurl);
		$repository->setAttribute('protocol', $protocol);
        $repository->setAttribute('host', $host);
        $repository->setAttribute('path', $path);
        $repository->setAttribute('login', $login);
        $repository->setAttribute('password', $password);

        $ret = $xml->save($this->params_filepath);
        if ($ret === false)
        {
            $this->errorMessage = sprintf("Error writing file '%s'.", $this->params_filepath);
            return false;
        }

        return true;

    }

    /**
     * Delete repository from global repo list
     * @return boolean
     */
    public function deleteRepo($name)
    {
        require_once ('class/Class.Repository.php');

        $xml = new DOMDocument();
        $xml->load($this->params_filepath);
        if ($xml === false)
        {
            $this->errorMessage = sprintf("Error loading XML file '%s'.", $this->params_filepath);
            return false;
        }

        $xPath = new DOMXPath($xml);

        // Get repository with this name from WIFF repositories
        $wiffRepoList = $xPath->query("/wiff/repositories/access[@name='".$name."']");
        if ($wiffRepoList->length == 0)
        {
            // If there is not at least one repository with such name enlisted
            $this->errorMessage = "Repository not found.";
            return false;
        }

        // Delete repository from this context
        $repository = $xml->getElementsByTagName('repositories')->item(0)->removeChild($wiffRepoList->item(0));

        $ret = $xml->save($this->params_filepath);
        if ($ret === false)
        {
            $this->errorMessage = sprintf("Error writing file '%s'.", $this->params_filepath);
            return false;
        }

        return true;

    }

    /**
     * Get Context list
     * @return array of object Context
     */
    public function getContextList()
    {
        require_once ('class/Class.Repository.php');
        require_once ('class/Class.Context.php');

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
                    $repoList[] = new Repository($repository);
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
        require_once ('class/Class.Repository.php');
        require_once ('class/Class.Context.php');

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
                $repoList[] = new Repository($repository);
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
        } else
        {
            $this->errorMessage = null;
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
        if (!preg_match('|^/|', $root))
        {
            $abs_root = realpath($root);
            if ($abs_root === false)
            {
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
    public function setParam($paramName, $paramValue, $create = true)
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
        if ($params === null && !$create)
        {
            $this->errorMessage = sprintf("Error executing XPath query '%s' on file '%s'.", "/wiff/parameters/param[@name='$paramName']", $this->params_filepath);
            return false;
        } else {
        	$param = $xml->createElement('param');
        	$param = $xml->getElementsByTagName('parameters')->item(0)->appendChild($param);
        	$param->setAttribute('name', $paramName);
        	$param->setAttribute('value', $paramValue);

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
        require_once ('lib/Lib.System.php');

        $tmpfile = LibSystem::tempnam(null, 'WIFF_downloadLocalFile');
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

        $tmpfile = LibSystem::tempnam(null, 'WIFF_downloadHttpUrlWget');
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
        require_once ('lib/Lib.System.php');

        $tmpfile = LibSystem::tempnam(null, 'WIFF_downloadHttpUrlFopen');
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

    public function expandParamValue($paramName)
    {
        $paramName = preg_replace('/@(\w+?)/', '\1', $paramName);

        $contextName = getenv("WIFF_CONTEXT_NAME");
        if ($contextName === false)
        {
            $this->errorMessage = sprintf( __CLASS__ ."::". __FUNCTION__ ." "."WIFF_CONTEXT_NAME env var not defined!");
            return false;
        }
        $context = $this->getContext($contextName);
        if ($context === false)
        {
            $this->errorMessage = sprintf( __CLASS__ ."::". __FUNCTION__ ." "."Could not get context with name '%s'.", $contextName);
            return false;
        }
        $paramValue = $context->getParamByName($paramName);
        if ($paramValue === false)
        {
            $this->errorMessage = sprintf( __CLASS__ ."::". __FUNCTION__ ." "."Could not get value for param with name '%s'.", $paramName);
            return false;
        }

        return $paramValue;
    }

    public function DOMDocumentLoadXML($DOMDocument, $xmlFile)
    {
        $fh = open($xmlFile, "rw");
        if ($fh === false)
        {
            $this->errorMessage = sprintf( __CLASS__ ."::". __FUNCTION__ ." "."Could not open '%s'.", $xmlFile);
            return false;
        }

        if (flock($fh, LOCK_EX) === false)
        {
            $this->errorMessage = sprintf( __CLASS__ ."::". __FUNCTION__ ." "."Could not get lock on '%s'.", $xmlFile);
            fclose($fh);
            return false;
        }

        $ret = $DOMDocument->load($xmlFile);

        flock($fh, LOCK_UN);
        fclose($fh);

        return $ret;
    }

    public function DOMDocumentSaveXML($DOMDocument, $xmlFile)
    {
        $fh = open($xmlFile, "rw");
        if ($fh === false)
        {
            $this->errorMessage = sprintf( __CLASS__ ."::". __FUNCTION__ ." "."Could not open '%s'.", $xmlFile);
            return false;
        }

        if (flock($fh, LOCK_EX) === false)
        {
            $this->errorMessage = sprintf( __CLASS__ ."::". __FUNCTION__ ." "."Could not get lock on '%s'.", $xmlFile);
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
