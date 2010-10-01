<?php

/**
 * Web Installer for Freedom Class
 * @author Anakeen
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License
 */

function curPageURL()
{
	$pageURL = 'http';
	if ($_SERVER["HTTPS"] == "on") {$pageURL .= "s";}
	$pageURL .= "://";
	if ($_SERVER["SERVER_PORT"] != "80") {
		$pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
	} else {
		$pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
	}
	return $pageURL;
}

class WIFF
{

	const contexts_filepath = 'conf/contexts.xml';
	const params_filepath = 'conf/params.xml';
	const archive_filepath = 'archived-contexts/';

	public $available_host;
	public $available_url;
	public $available_file;

	public $contexts_filepath = '';
	public $params_filepath = '';
	public $archive_filepath = '';

	public $errorMessage = null;

	public $archiveFile;

	public $authInfo = array();

	private static $instance;

	public $lock = null;
	public $lock_level = 0;

	private function __construct()
	{
		$wiff_root = getenv('WIFF_ROOT');
		if ($wiff_root !== false)
		{
			$wiff_root = $wiff_root.DIRECTORY_SEPARATOR;
		}

		$this->contexts_filepath = $wiff_root.WIFF::contexts_filepath;
		$this->params_filepath = $wiff_root.WIFF::params_filepath;
		$this->archive_filepath = $wiff_root.WIFF::archive_filepath;

		$this->updateParam();

		$this->available_host = $this->getParam('wiff-update-host');
		$this->available_url = $this->getParam('wiff-update-path');
		$this->available_file = $this->getParam('wiff-update-file');

	}

	public static function getInstance()
	{
		if (! isset (self::$instance))
		{
			self::$instance = new WIFF();
		}
		if (!self::$instance->isWritable())
		{
			self::$instance->errorMessage = 'Cannot write configuration files';
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
		$tmpfile = $this->downloadUrl($this->available_host.$this->available_url.'content.xml');

		if ($tmpfile === false)
		{
			$this->errorMessage = $this->errorMessage ? ('Error when retrieving repository for wiff update :: '.$this->errorMessage) : 'Error when retrieving repository for wiff update.';
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

	public function getLogin()
	{
		if (!$this->hasPasswordFile())
		{
			return false;
		} else
		{
			@$passwordFile = fopen('.htpasswd', 'r');
			$explode = explode(':', fgets($passwordFile, 100));
			$login = $explode[0];
			return $login;
		}
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
sprintf("%s:{SHA}%s", $login, base64_encode(sha1($password, true)))
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

		$str1 = sprintf("%03d%03d%03d", $ver1[0], $ver1[1], $ver1[2]);
		$str2 = sprintf("%03d%03d%03d", $ver2[0], $ver2[1], $ver2[2]);

		$cmp_ver = strcmp($str1, $str2);

		/* Version is different, so we do not
		 * need to test the release
		 */
		if( $cmp_ver != 0 ) {
			return $cmp_ver;
		}

		/* Version is equal, so we need to
		 * test the release:
		 *   num vs. num => numeric comparison
		 *   str vs. str => string comparison
		 *   num vs. str => string is < to num
		 */
		$cmp_rel = 0;
		if( is_numeric($rel1) && is_numeric($rel2) ) {
			/* standard numeric comparison */
			$cmp_rel = $rel1-$rel2;
		} else if( is_numeric($rel1) && is_string($rel2) ) {
			/* number is > to string */
			$cmp_rel = 1;
		} else if( is_string($rel1) && is_numeric($rel2) ) {
			/* string is < to number */
			$cmp_rel = -1;
		} else if( is_string($rel1) && is_string($rel2) ) {
			/* standard string comparison */
			$cmp_rel = strcmp($rel1, $rel2);
		} else {
			$cmp_rel = 0;
		}

		return $cmp_rel;
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

		return $this->compareVersion($v2, $r2, $v1, $r1) > 0 ? true : false;

	}

	/**
	 * Download latest WIFF file archive
	 * @return
	 */
	private function download()
	{
		$this->archiveFile = $this->downloadUrl($this->available_host.$this->available_url.$this->available_file);
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
		$v1 = $this->getVersion();

		$ret = $this->download();
		if( $ret === false ) {
			return $ret;
		}

		$ret = $this->unpack();
		if( $ret === false ) {
			return $ret;
		}

		$v2 = $this->getVersion();

		$ret = $this->postUpgrade($v1, $v2);
		if( $ret === false ) {
			return $ret;
		}

		return true;
	}

	public function updateParam()
	{
		$available_host = $this->getParam('wiff-update-host');
		if (!$available_host || $available_host === 'ftp://ftp.freedom-ecm.org/')
		{
			$this->setParam('wiff-update-host', 'ftp://ftp.dynacase.org/');
		}
		$available_url = $this->getParam('wiff-update-path');
		if (!$available_url)
		{
			$this->setParam('wiff-update-path', '2.14/tarball/');
		}
		$available_file = $this->getParam('wiff-update-file');
		if (!$available_file || $available_file === 'freedom-wiff-current.tar.gz')
		{
			$this->setParam('wiff-update-file', 'dynacase-control-current.tar.gz');
		}
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
			$this->errorMessage = sprintf("Error loading XML file '%s'.", $this->params_filepath);
			return false;
		}

		$repositories = $xml->getElementsByTagName('access');

		if ($repositories->length > 0)
		{

			foreach ($repositories as $repository)
			{
				$repoList[] = new Repository($repository, null, array('checkValidity' => true));
			}

		}

		return $repoList;

	}

	/**
	 * Get repository from global repo list
	 */
	public function getRepo($name)
	{
		require_once ('class/Class.Repository.php');

		if ($name == '')
		{
			$this->errorMessage = "A name must be provided.";
			return false;
		}

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
			// If there is already a repository with same name
			$this->errorMessage = "Repository does not exist.";
			return false;
		}

		$repository = $wiffRepoList->item(0);

		$repositoryObject = new Repository($repository);

		return $repositoryObject;

	}

	/**
	 * Add repository to global repo list
	 * @return boolean
	 */
	public function createRepo($name, $description, $protocol, $host, $path, $default, $authentified, $login, $password)
	{
		require_once ('class/Class.Repository.php');

		if ($name == '')
		{
			$this->errorMessage = "A name must be provided.";
			return false;
		}

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
		$repository->setAttribute('protocol', $protocol);
		$repository->setAttribute('host', $host);
		$repository->setAttribute('path', $path);
		$repository->setAttribute('default', $default);
		$repository->setAttribute('authentified', $authentified);
		$repository->setAttribute('login', $login);
		$repository->setAttribute('password', $password);

		$repositoryObject = new Repository($repository);

		$isValid = $repositoryObject->isValid();

		$repository->setAttribute('label', $repositoryObject->label);

		$ret = $xml->save($this->params_filepath);
		if ($ret === false)
		{
			$this->errorMessage = sprintf("Error writing file '%s'.", $this->params_filepath);
			return false;
		}
		return $isValid;

	}

	/**
	 * Change all parameters in one go
	 * @param array $request
	 * @return boolean
	 */
	public function changeAllParams($request)
	{
		if (count($request) <= 1) {
			$this->errorMessage = "No params to change";
			return false;
		}
		$paramList = $this->getParamList();
		if ($paramList === false) {
			return false;
		}
		foreach ($paramList as $name => $value) {
			$i = 0;
			foreach ($request as $r_name => $r_value) {
				if ($r_name !== 'changeAllParams') {
					if ($r_name == $name) {
						$err = $this->changeParams($r_name, $r_value);
						if ($err === false) {
							return false;
						}
						$i++;
						break;
					}
				}
			}
			if ($i === 0) {
				$err = $this->changeParams($name, false);
				if ($err === false) {
					return false;
				}
			}
		}
		return $paramList;
	}

	/**
	 * Change Dynacase-control parameters
	 * @param string $name : Name of the parameters to change
	 * @param string $value : New value one want to set to the parameter
	 * @return boolean
	 */
	public function changeParams($name, $value)
	{
		if ($name == '') {
			$this->errorMessage = "A name must be provided";
			return false;
		}

		$xml = new DOMDocument();
		$xml->load($this->params_filepath);
		if ($xml === false) {
			$this->errorMessage = sprintf("Error loading XML file '%s'.", $this->params_filepath);
			return false;
		}
		$paramList = $xml->getElementsByTagName('param');
		if ($paramList->length > 0) {
			foreach ($paramList as $param) {
				if ($param->getAttribute('name') === $name) {
					$valueTest = $param->getAttribute('value');
					$param->removeAttribute('value');
					if ($valueTest == 'yes' || $valueTest == 'no') {
						if ($value === true || $value === 'on' || $value === 'true') {
							$param->setAttribute('value', 'yes');
						}
						else {
							$param->setAttribute('value', 'no');
						}
					}
					else {
						$param->setAttribute('value', $value);
					}
					break;
				}
			}
		}
		$ret = $xml->save($this->params_filepath);
		if ($ret === false)
		{
			$this->errorMessage = sprintf("Error writing file '%s'.", $this->params_filepath);
			return false;
		}
		return true;
	}

	/**
	 * Add repository to global repo list
	 * @return boolean
	 */
	public function modifyRepo($name, $description, $protocol, $host, $path, $default, $authentified, $login, $password)
	{
		require_once ('class/Class.Repository.php');

		if ($name == '')
		{
			$this->errorMessage = "A name must be provided.";
			return false;
		}

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
			// If there is already a repository with same name
			$this->errorMessage = "Repository does not exist.";
			return false;
		}

		// Add repository to this context
		//        $node = $xml->createElement('access');
		//        $repository = $xml->getElementsByTagName('repositories')->item(0)->appendChild($node);

		$repository = $wiffRepoList->item(0);

		$repository->setAttribute('name', $name);
		$repository->setAttribute('description', $description);
		$repository->setAttribute('protocol', $protocol);
		$repository->setAttribute('host', $host);
		$repository->setAttribute('path', $path);
		$repository->setAttribute('default', $default);
		$repository->setAttribute('authentified', $authentified);
		$repository->setAttribute('login', $login);
		$repository->setAttribute('password', $password);

		$repositoryObject = new Repository($repository);
		$ret = $xml->save($this->params_filepath);
		if ($ret === false)
		{
			$this->errorMessage = sprintf("Error writing file '%s'.", $this->params_filepath);
			return false;
		}
		return $repositoryObject->isValid();

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

	public function setAuthInfo($request)
	{
		//echo 'REQUEST'.print_r($request[0]->name,true);
		//echo 'SET AuthInfo Size'.count($request);
		$this->authInfo = $request;
	}

	public function getAuthInfo($repoName)
	{
		//echo ('GET AuthInfo'.$repoName.count($this->authInfo));
		for ($i = 0 ; $i < count($this->authInfo) ; $i++)
		{
			//echo ('Looking through authinfo');
			if ($this->authInfo[$i]->name == $repoName)
			{
				return $this->authInfo[$i];
			}
		}
		return false;
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

				$context = new Context($context->getAttribute('name'), $context->getElementsByTagName('description')->item(0)->nodeValue, $context->getAttribute('root'), $repoList, $context->getAttribute('url'));
				$context->isValid();

				if (!$context->isWritable())
				{
					$this->errorMessage = sprintf("Apache user does not have write rights for context '%s'.", $context->name);
					return false;
				}

				$contextList[] = $context;

			}

		}


		$archived_root = $this->archive_filepath;

		if (is_dir($archived_root))
		{
			if (!is_writable($archived_root))
			{
				$this->errorMessage = sprintf("Directory '%s' is not writable.", $archived_root);
				return false;
			}
		} else
		{
			if (@mkdir($archived_root) === false)
			{
				$this->errorMessage = sprintf("Error creating directory '%s'.", $archived_root);
				return false;
			}
		}

		if ($handle = opendir($archived_root)) {

			while (false !== ($file = readdir($handle))){

				if(preg_match('/^.+\.ctx$/',$file)){

					$status_handle = fopen($archived_root.DIRECTORY_SEPARATOR.$file,'r');
					$context = array();
					$context['name'] = fread($status_handle,filesize($archived_root.DIRECTORY_SEPARATOR.$file));
					$context['inProgress'] = true ;
					$contextList[] = $context ;

				}

			}

		}

		return $contextList;

	}

	public function getArchivedContextList()
	{

		$archivedContextList = array();

		$wiff_root = getenv('WIFF_ROOT');
		if ($wiff_root !== false)
		{
			$wiff_root = $wiff_root.DIRECTORY_SEPARATOR;
		}

		$archived_root = $wiff_root.WIFF::archive_filepath;

		if (is_dir($archived_root))
		{
			if (!is_writable($archived_root))
			{
				$this->errorMessage = sprintf("Directory '%s' is not writable.", $archived_root);
				return false;
			}
		} else
		{
			if (@mkdir($archived_root) === false)
			{
				$this->errorMessage = sprintf("Error creating directory '%s'.", $archived_root);
				return false;
			}
		}

		if ($handle = opendir($archived_root)) {

			while (false !== ($file = readdir($handle))){

				if(preg_match('/^(?P<basename>.+)\.fcz$/',$file,$fmatch)){

					$zip = zip_open($archived_root.DIRECTORY_SEPARATOR.$file);

					if(is_int($zip)){
						$this->errorMessage = "Error when opening archive.";
						return false;
					}

					do {
						$info = zip_read($zip);
					} while ($info && zip_entry_name($info) != "info.xml");

					if(zip_entry_name($info) == "info.xml"){

						zip_entry_open($zip, $info, "r");

						$info_content = zip_entry_read($info, zip_entry_filesize($info));

						$xml = new DOMDocument();
						$xml->loadXML($info_content);
						if ($xml === false)
						{
							$this->errorMessage = sprintf("Error loading XML file '%s'.", $info);
							return false;
						}

						$xpath = new DOMXpath($xml);

						$contexts = $xpath->query("/info/context");

						if ($contexts->length > 0)
						{
							foreach ($contexts as $context){ // Should be only one context
								$contextName = $context->getAttribute('name');
							}
						}

						$archived_contexts = $xpath->query("/info/archive");

						if ($archived_contexts->length > 0)
						{
							foreach ($archived_contexts as $context){ // Should be only one context
								$archiveContext = array();
								$archiveContext['name'] = $context->getAttribute('name');
								$archiveContext['description'] = $context->getAttribute('description');
								$archiveContext['id'] = $fmatch['basename'];
								$archiveContext['datetime'] = $context->getAttribute('datetime');
								$archiveContext['vault'] = $context->getAttribute('vault');

								$moduleList = array();

								$moduleDom = $xpath->query("/info/context[@name='".$contextName."']/modules/module");

								foreach ($moduleDom as $module)
								{
									$mod = new Module($this, null, $module, true);
									if ($mod->status == 'installed')
									{
										$moduleList[] = $mod;
									}
								}

								$archiveContext['moduleList'] = $moduleList;

								$archivedContextList[] = $archiveContext ;
							}
						}


					} else {
						$this->errorMessage = "info.xml not found in archive";
					}

				}

				if(preg_match('/^.+\.sts$/',$file)){

					error_log('STATUS FILE --- '.$file);

					$status_handle = fopen($archived_root.DIRECTORY_SEPARATOR.$file,'r');
					$archiveContext = array();
					$archiveContext['name'] = fread($status_handle,filesize($archived_root.DIRECTORY_SEPARATOR.$file));
					$archiveContext['inProgress'] = true ;
					$archivedContextList[] = $archiveContext ;

				}
			}

		}

		return $archivedContextList;

	}

	public function createContextFromArchive($archiveId, $name, $root, $desc, $url, $vault_root, $pgservice, $remove_profiles, $user_login, $user_password, $clean_tmp_directory = false)
	{

		$wiff_root = getenv('WIFF_ROOT');
		if ($wiff_root !== false)
		{
			$wiff_root = $wiff_root.DIRECTORY_SEPARATOR;
		}

		$archived_root = $wiff_root.WIFF::archive_filepath;

		// --- Create status file for context --- //
		$status_file = $archived_root.DIRECTORY_SEPARATOR.$archiveId.'.ctx';
		$status_handle = fopen($status_file, "w");
		fwrite($status_handle,$name);

		// --- Create or reuse directory --- //
		if (is_dir($root))
		{
			if (!is_writable($root))
			{
				$this->errorMessage = sprintf("Directory '%s' is not writable.", $root);
				// --- Delete status file --- //
				unlink($status_file);
				return false;
			}
			$dirListing = @scandir($root);
			if ($dirListing === false)
			{
				$this->errorMessage = sprintf("Error scanning directory '%s'.", $root);
				// --- Delete status file --- //
				unlink($status_file);
				return false;
			}
			$dirListingCount = count($dirListing);
			if ($dirListingCount > 2)
			{
				$this->errorMessage = sprintf("Directory '%s' is not empty.", $root);
				// --- Delete status file --- //
				unlink($status_file);
				return false;
			}
		} else
		{
			if (@mkdir($root) === false)
			{
				$this->errorMessage = sprintf("Error creating directory '%s'.", $root);
				// --- Delete status file --- //
				unlink($status_file);
				return false;
			}
		}

		if (is_dir($vault_root))
		{
			if (!is_writable($vault_root))
			{
				$this->errorMessage = sprintf("Directory '%s' is not writable.", $vault_root);
				// --- Delete status file --- //
				unlink($status_file);
				return false;
			}
			$dirListing = @scandir($vault_root);
			if ($dirListing === false)
			{
				$this->errorMessage = sprintf("Error scanning directory '%s'.", $vault_root);
				// --- Delete status file --- //
				unlink($status_file);
				return false;
			}
			$dirListingCount = count($dirListing);
			if ($dirListingCount > 2)
			{
				$this->errorMessage = sprintf("Directory '%s' is not empty.", $vault_root);
				// --- Delete status file --- //
				unlink($status_file);
				return false;
			}
		} else
		{
			if (@mkdir($vault_root, 0777, true) === false)
			{
				$this->errorMessage = sprintf("Error creating directory '%s'.", $vault_root);
				// --- Delete status file --- //
				unlink($status_file);
				return false;
			}
		}

		// If Context already exists, method fails.
		if ($this->getContext($name) !== false)
		{
			$this->errorMessage = sprintf("Context '%s' already exists.", $name);
			// --- Delete status file --- //
			unlink($status_file);
			return false;
		}

		// Get absolute pathname if directory is not already in absolute form
		if (!preg_match('|^/|', $root))
		{
			$abs_root = realpath($root);
			if ($abs_root === false)
			{
				$this->errorMessage = sprintf("Error getting absolute pathname for '%s'.", $root);
				// --- Delete status file --- //
				unlink($status_file);
				return false;
			}
			$root = $abs_root;
		}

		if (!preg_match('|^/|', $vault_root))
		{
			$abs_vault_root = realpath($vault_root);
			if ($abs_vault_root === false)
			{
				$this->errorMessage = sprintf("Error getting absolute pathname for '%s'.", $vault_root);
				// --- Delete status file --- //
				unlink($status_file);
				return false;
			}
			$vault_root = $abs_vault_root;
		}


		$wiff_root = getenv('WIFF_ROOT');
		if ($wiff_root !== false)
		{
			$wiff_root = $wiff_root.DIRECTORY_SEPARATOR;
		}

		$archived_root = $wiff_root.WIFF::archive_filepath;

		if ($handle = opendir($archived_root))
		{

			while (false !== ($file = readdir($handle)))
			{

				if($file == $archiveId.'.fcz')
				{

					$temporary_extract_root = $archived_root.'archived-tmp';

					$zip = new ZipArchive();
					$res = $zip->open($archived_root.DIRECTORY_SEPARATOR.$file);
					if ($res === TRUE) {
						$zip->extractTo($temporary_extract_root);
						$zip->close();
					} else {
						$this->errorMessage = "Error when opening archive.";
						// --- Delete status file --- //
						unlink($status_file);
						return false;
					}

					// --- Extract context tar gz --- //

					$context_tar = $temporary_extract_root.DIRECTORY_SEPARATOR."context.tar.gz";

					$script = sprintf("tar -zxf %s -C %s", escapeshellarg($context_tar), escapeshellarg($root));

					$result = exec($script,$output,$retval);

					if($retval != 0){
						$this->errorMessage = "Error when extracting context.tar.gz to $root";
						// --- Delete status file --- //
						unlink($status_file);
						return false;
					}

					error_log('Context tar gz extracted');

					// --- Restore database --- //

					// Setting datestyle
					$dbconnect = pg_connect("service=$pgservice");
					if ($dbconnect === false) {
						$this->errorMessage = "Error when trying to connect to database $pgservice";
						error_log("Error trying to connect to database $pgservice");
						unlink($status_file);
					}
					$result = pg_query($dbconnect,"alter database $pgservice set datestyle = 'SQL, DMY';");
					if ($result === false) {
						$this->errorMessage = "Error when trying to get databse info :: ".pg_last_erro();
						error_log("Error when trying to get databse info :: ".pg_last_erro());
						unlink($status_file);
					}

					$dump = $temporary_extract_root.DIRECTORY_SEPARATOR."core_db.pg_dump.gz";

					$script = sprintf("gzip -dc %s | PGSERVICE=%s psql", $dump, $pgservice);
					$result = exec($script,$output,$retval);

					if($retval != 0){
						$this->errorMessage = "Error when restoring core_db.pg_dump.gz";
						// --- Delete status file --- //
						unlink($status_file);
						return false;
					}

					error_log('Database restored');

					// --- Extract vault tar gz --- //
					$vaultfound = false;
					if ($handle = opendir($temporary_extract_root))
					{

						while (false !== ($file = readdir($handle)))
						{

							if(substr($file, 0, 5) == 'vault')
							{
								$id_fs = substr($file,6,-7);
								$vaultfound = true;
								$vault_tar = $temporary_extract_root.DIRECTORY_SEPARATOR.$file ;
								$vault_subdir = $vault_root.DIRECTORY_SEPARATOR.$id_fs.DIRECTORY_SEPARATOR ;

								if (@mkdir($vault_subdir,0777, true) === false)
								{
									$this->errorMessage = sprintf("Error creating directory '%s'.", $vault_subdir);
									error_log(sprintf("Error creating directory '%s'.", $vault_subdir));
									// --- Delete status file --- //
									unlink($status_file);
									return false;
								}

								$script = sprintf("tar -zxf %s -C %s", escapeshellarg($vault_tar), escapeshellarg($vault_subdir));

								$result = exec($script,$output,$retval);

								if($retval != 0){
									$this->errorMessage = "Error when extracting vault to $vault_root";
									// --- Delete status file --- //
									unlink($status_file);
									return false;
								}

								if ($clean_tmp_directory === 'on') {
									// --- Delete tmp tar file --- //
									unlink($vault_tar);
								}
							}

						}

					}

					error_log('Vault tar gz extracted');

				}
			}

		}

		// Write contexts XML
		$xml = new DOMDocument();
		$xml->preserveWhiteSpace = false;
		$xml->load($this->contexts_filepath);
		$xml->formatOutput = true;

		$infoFile = $temporary_extract_root.DIRECTORY_SEPARATOR."info.xml";

		$archiveXml = new DOMDocument();
		$archiveXml->load($infoFile);


		$xmlXPath = new DOMXPath($xml);
		$contextList = $xmlXPath->query("/contexts/context[@name='".$name."']");
		if ($contextList->length != 0)
		{
			// If more than one context with name
			$this->errorMessage = "Context with same name already exists.";
			// --- Delete status file --- //
			unlink($status_file);
			return false;
		}

		$contextList = $xmlXPath->query("/contexts");

		$archiveXPath = new DOMXPath($archiveXml);

		// Get this context
		$archiveList = $archiveXPath->query("/info/context");
		if ($archiveList->length != 1)
		{
			// If more than one context found
			$this->errorMessage = "More than one context in archive";
			// --- Delete status file --- //
			unlink($status_file);
			return false;
		}

		$context = $xml->importNode($archiveList->item(0), true); // Node must be imported from archive document.
		$context->setAttribute('name',$name);
		$context->setAttribute('root',$root);
		$context->setAttribute('url', $url);
		$context = $contextList->item(0)->appendChild($context);

		// Modify core_db in xml
		$paramList = $xmlXPath->query("/contexts/context[@name='".$name."']/parameters-value/param[@name='core_db']");
		if ($paramList->length != 1)
		{
			$this->errorMessage = "Parameter core_db does not exist.";
			// --- Delete status file --- //
			unlink($status_file);
			return false;
		}

		$paramList->item(0)->setAttribute('value',$pgservice);

		// Modify client_name in xml by context name
		$paramList = $xmlXPath->query("/contexts/context[@name='".$name."']/parameters-value/param[@name='client_name']");
		if ($paramList->length != 1)
		{
			$this->errorMessage = "Parameter client_name does not exist.";
			// --- Delete status file --- //
			unlink($status_file);
			return false;
		}

		$paramList->item(0)->setAttribute('value',$name);

		// Modify or add vault_root in xml
		$paramList = $xmlXPath->query("/contexts/context[@name='".$name."']/parameters-value/param[@name='vault_root']");
		$paramValueList = $xmlXPath->query("/contexts/context[@name='".$name."']/parameters-value");
		$paramVaultRoot = $xml->createElement('param');
		$paramVaultRoot->setAttribute('name','vault_root');
		$paramVaultRoot->setAttribute('value',$vault_root);
		if ($vaultfound === false) {
			$vault_save_value = 'no';
		}
		else {
			$vault_save_value = 'yes';
		}
		if ($paramList->length != 1){
			$paramVaultRoot = $paramValueList->item(0)->appendChild($paramVaultRoot);
		}  else {
			$paramVaultRoot = $paramValueList->item(0)->replaceChild($paramVaultRoot, $paramList->item(0));
		}

		$vault_save = $xml->createElement('param');
		$vault_save->setAttribute('name','vault_save');
		$vault_save->setAttribute('value',$vault_save_value);
		$paramValueList->item(0)->appendChild($vault_save);

		if(isset($remove_profiles) && $remove_profiles == true){
			// Modify or add remove_profiles in xml
			$paramList = $xmlXPath->query("/contexts/context[@name='".$name."']/parameters-value/param[@name='remove_profiles']");
			if ($paramList->length != 1)
			{

				$paramValueList = $xmlXPath->query("/contexts/context[@name='".$name."']/parameters-value");

				$paramRemoveProfiles = $xml->createElement('param');
				$paramRemoveProfiles->setAttribute('name','remove_profiles');
				$paramRemoveProfiles->setAttribute('value',true);
				$paramRemoveProfiles = $paramValueList->item(0)->appendChild($paramVaultRoot);

				$paramUserLogin = $xml->createElement('param');
				$paramUserLogin->setAttribute('name','user_login');
				$paramUserLogin->setAttribute('value',$user_login);
				$paramUserLogin = $paramValueList->item(0)->appendChild($paramUserLogin);

				$paramUserPassword = $xml->createElement('param');
				$paramUserPassword->setAttribute('name','user_password');
				$paramUserPassword->setAttribute('value',$user_password);
				$paramUserPassword = $paramValueList->item(0)->appendChild($paramUserPassword);

			}
		}

		// Save XML to file
		$ret = $xml->save($this->contexts_filepath);
		if ($ret === false)
		{
			$this->errorMessage = sprintf("Error writing file '%s'.", $this->contexts_filepath);
			// --- Delete status file --- //
			unlink($status_file);
			return false;
		}


		// --- checking if reconfigure script exists --- //
		$context = $this->getContext($name);
		if (!file_exists($context->root.'/programs/toolbox_reconfigure')) {
			$this->errorMessage = sprintf("Reconfigure script doesn't exists");
			error_log('reconfigure script not found :: '.$context->root.'/programs/toolbox_reconfigure');
			$result = true;
			// --- Delete status file --- //
			unlink($status_file);
			$context->delete($result);
			return false;
		}

		$this->reconfigure($name);

		if ($clean_tmp_directory === 'on') {
			// --- Delete Tmp tar file --- //
			unlink($context_tar);
			unlink($dump);
			unlink($infoFile);
		}
		// --- Delete status file --- //
		unlink($status_file);

		return true ;

		//return $this->getContext($name);

	}


	public function reconfigure($name)
	{

		error_log('Call to reconfigure');

		$context = $this->getContext($name);

		$installedModuleList = $context->getInstalledModuleList();
		foreach($installedModuleList as $module){
			$phase = $module->getPhase('reconfigure');
			$processList = $phase->getProcessList();
			foreach($processList as $process){
				$process->execute();
			}
		}
		$htaccess = $context->root.'/.htaccess';
		if (file_exists($htaccess)) {
			$searchLine = '';
			$fileLines = file($htaccess);
			foreach ($fileLines as $line) {
				$searchCount = substr_count($line, 'php_value session.save_path');
				if($searchCount > 0) {
					$searchLine = $line;
					break;
				}
			}
			if ($searchLine != '') {
				$resLine = 'php_value session.save_path '."\"$context->root"."/session\"\n";
				$str=implode('',file($htaccess));
				$fp=fopen($htaccess,'w');
				$str=str_replace($searchLine,$resLine,$str);
				fwrite($fp,$str,strlen($str));
			}
		}
	}

	/**
	 * Delete an archived context.
	 * @return boolean method success
	 * @param integer $archiveId
	 */
	public function deleteArchive($archiveId)
	{

		$wiff_root = getenv('WIFF_ROOT');
		if ($wiff_root !== false)
		{
			$wiff_root = $wiff_root.DIRECTORY_SEPARATOR;
		}

		$archived_root = $wiff_root.WIFF::archive_filepath;

		if(unlink($archived_root.$archiveId.'.fcz'))
		{
			return true ;
		}

		return false ;

	}

	/**
	 * Get an url to download an archived context.
	 * @return string Archive url
	 * @param integer $archivedId
	 */
	public function downloadArchive($archiveId){

		$archived_url = curPageURL().wiff::archive_filepath ;

		return $archived_url.DIRECTORY_SEPARATOR.$archiveId.'fcz';

	}

	/**
	 * Get Context by name
	 * @return object Context or boolean false
	 * @param string $name context name
	 */
	public function getContext($name, $opt = false)
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
			$context = new Context($context->item(0)->getAttribute('name'), $context->item(0)->getElementsByTagName('description')->item(0)->nodeValue, $context->item(0)->getAttribute('root'), $repoList, $context->item(0)->getAttribute('url'));

			if (!$context->isWritable() && $opt == false)
			{
				$this->errorMessage = sprintf("Context '%s' configuration is not writable.", $context->name);
				return false;
			}

			return $context;

		}

		$this->errorMessage = sprintf("Context '%s' not found.", $name);
		return false;

	}

	public function isWritable()
	{
		if (!is_writable($this->contexts_filepath) || !is_writable($this->params_filepath))
		{
			return false;
		}
		return true;
	}

	/**
	 * Create Context
	 * @return object Context or boolean false
	 * @param string $name context name
	 * @param string $root context root folder
	 * @param string $desc context description
	 */
	public function createContext($name, $root, $desc, $url)
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

		$context->setAttribute('url', $url);

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
	 * Save Context
	 * @return object Context or boolean false
	 */
	public function saveContext($name, $root, $desc, $url)
	{

		// Write contexts XML
		$xml = new DOMDocument();
		$xml->preserveWhiteSpace = false;
		$xml->load($this->contexts_filepath);
		$xml->formatOutput = true;

		$xpath = new DOMXPath($xml);

		$query = "/contexts/context[@root = '".$root."']";
		$context = $xpath->query($query)->item(0);

		$context->setAttribute('name', $name);
		$context->setAttribute('url',$url);

		$query = "/contexts/context[@root = '".$root."']/description";
		$description = $xpath->query($query)->item(0);

		$description->nodeValue = $desc ;

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
	 * @param boolean $strict if not found, should method report an error
	 */
	public function getParam($paramName, $strict = false)
	{
		$plist = $this->getParamList();

		if (array_key_exists($paramName, $plist))
		{
			return $plist[$paramName];
		}

		if ($strict)
		{
			$this->errorMessage = sprintf("Parameter '%s' not found in contexts parameters.", $paramName);
		}
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
		if ($params === null)
		{
			$this->errorMessage = sprintf("Error executing XPath query '%s' on file '%s'.", "/wiff/parameters/param[@name='$paramName']", $this->params_filepath);
			return false;
		}

		$found = false;

		foreach ($params as $param)
		{
			$found = true;
			$param->setAttribute('value', $paramValue);
		}

		if (!$found && $create)
		{
			$param = $xml->createElement('param');
			$param = $xml->getElementsByTagName('parameters')->item(0)->appendChild($param);
			$param->setAttribute('name', $paramName);
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

		$tmpfile = WiffLibSystem::tempnam(null, 'WIFF_downloadLocalFile');
		if ($tmpfile === false)
		{
			$this->errorMessage = sprintf("Error creating temporary file.");
			error_log(sprintf( __CLASS__ ."::". __FUNCTION__ ." "."Error creating temporary file."));
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

		$tmpfile = WiffLibSystem::tempnam(null, 'WIFF_downloadHttpUrlWget');
		if ($tmpfile === false)
		{
			$this->errorMessage = sprintf("Error creating temporary file.");
			error_log(sprintf( __CLASS__ ."::". __FUNCTION__ ." "."Error creating temporary file."));
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

		$wget_path = WiffLibSystem::getCommandPath('wget');
		if ($wget_path === false)
		{
			unlink($tmpfile);
			error_log(sprintf( __CLASS__ ."::". __FUNCTION__ ." "."Command '%s' not found in PATH.", 'wget'));
			$this->errorMessage = sprintf("Command '%s' not found in PATH.", 'wget');
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
			error_log(sprintf( __CLASS__ ."::". __FUNCTION__ ." "."Error fetching '%s' with '%s'.", $url, $cmd));
			$this->errorMessage = sprintf("Error fetching '%s' with '%s'.", $url, $cmd);
			return false;
		}

		return $tmpfile;
	}

	public function downloadHttpUrlFopen($url)
	{
		require_once ('lib/Lib.System.php');

		$tmpfile = WiffLibSystem::tempnam(null, 'WIFF_downloadHttpUrlFopen');
		if ($tmpfile === false)
		{
			$this->errorMessage = sprintf("Error creating temporary file.");
			error_log(sprintf( __CLASS__ ."::". __FUNCTION__ ." "."Error creating temporary file."));
			return false;
		}

		$fout = fopen($tmpfile, 'w');
		if ($fout === false)
		{
			unlink($tmpfile);
			$this->errorMessage = sprintf( __CLASS__ ."::". __FUNCTION__ ." "."Error opening output file '%s' for writing.", $tmpfile);
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

	public function lock() {
		if( $this->lock != null ) {
			$this->lock_level++;
			return $this->lock;
		}

		$fh = fopen(sprintf("%s.lock", $this->contexts_filepath), "a");
		if( $fh === false ) {
			$this->errorMessage = sprintf("Could not open '%s' for lock.", sprintf("%s.lock", $this->contexts_filepath));
			return false;
		}

		$ret = flock($fh, LOCK_EX);
		if( $ret === false ) {
			$this->errorMessage = sprintf("Could not get lock on '%s'.", sprintf("%s.lock", $this->contexts_filepath));
			return false;
		}

		$this->lock = $fh;
		$this->lock_level++;

		return $fh;
	}

	public function unlock($fh) {
		if( $this->lock != null ) {
			$this->lock_level--;
			if( $this->lock_level > 0 ) {
				return $this->lock;
			}
		}

		$ret = flock($fh, LOCK_UN);
		if( $ret == false ) {
			$this->errorMessage = sprintf("Could not release lock on '%s'.", sprintf("%s.lock", $this->contexts_filepath));
			return false;
		}

		$this->lock = null;
		$this->lock_level = 0;
		fclose($fh);

		return true;
	}

	public function postUpgrade($fromVersion, $toVersion) {
		include_once('lib/Lib.System.php');

		$v = preg_split('/-/', $fromVersion, 2);
		$fromVer = $v[0];
		$fromRel = $v[1];

		$v = preg_split('/-/', $toVersion, 2);
		$toVer = $v[0];
		$toRel = $v[1];

		$wiff_root = getenv('WIFF_ROOT');
		if( $wiff_root !== false ) {
			$wiff_root = $wiff_root.DIRECTORY_SEPARATOR;
		}

		$dir = @opendir(sprintf('%s/%s', $wiff_root, 'migr'));
		if( $dir === false ) {
			$this->errorMessage = sprintf("Failed to open 'migr' directory.");
			return false;
		}

		$migrList = array();
		while( $migr = readdir($dir) ) {
			if( ! preg_match('/^\d+\.\d+\.\d+-\d+$/', $migr) ) {
				continue;
			}
			$v = preg_split('/-/', $migr, 2);
			$migrVer = $v[0];
			$migrRel = $v[1];
			array_push($migrList,
			array(
			 'migr' => $migr,
			 'ver' => $migrVer,
			 'rel' => $migrRel
			)
			);
		}

		usort($migrList, array($this, 'postUpgradeCompareVersion'));

		foreach( $migrList as $migr ) {
			if( $this->compareVersion($migr['ver'], $migr['rel'], $fromVer, $fromRel) <= 0 ) {
				continue;
			}

			error_log(__CLASS__."::".__FUNCTION__." ".sprintf("Executing migr script '%s'.", $migr['migr']));
			$temp = tempnam(null, sprintf("wiff_migr_%s", $migr['migr']));
			if( $temp === false ) {
				$this->errorMessage = "Could not create temp file.";
				return false;
			}

			$cmd = sprintf("%s/%s/%s > %s 2>&1", $wiff_root, 'migr', $migr['migr'], $temp);
			system($cmd, $ret);
			$output = file_get_contents($temp);
			if( $ret !== 0 ) {
				$err = sprintf("Migr script '%s' returned with error status %s (output=[[[%s]]])", $migr['migr'], $ret, $output);
				error_log(__CLASS__."::".__FUNCTION__." ".sprintf("%s", $err));
				$this->errorMessage = $err;
				return false;
			}
			error_log(__CLASS__."::".__FUNCTION__." ".sprintf("Migr script '%s': Ok.", $migr['migr']));
			@unlink($temp);
		}

		$this->errorMessage = '';
		return true;
	}

	function postUpgradeCompareVersion($a, $b) {
		return $this->compareVersion($a["ver"], $a["rel"], $b["ver"], $b["rel"]);
	}

	function getLicenseAgreement($ctxName, $moduleName, $licenseName) {
		$lock = $this->lock();
		if( $lock === false ) {
			$err = sprintf(__CLASS__."::".__FUNCTION__." "."Could not get lock on context XML file.");
			error_log($err);
			$this->errorMessage($err);
			return false;
		}

		$xml = new DOMDocument();
		$xml->preserveWhiteSpace = false;
		$xml->formatOutput = true;
		$ret = $xml->load($this->contexts_filepath);
		if( $ret === false ) {
			$err = sprintf(__CLASS__."::".__FUNCTION__." "."Could not load XML file '%s'.", $this->contexts_filepath);
			error_log($err);
			$this->errorMessage = $err;
			$this->unlock($lock);
			return false;
		}

		$xpath = new DOMXpath($xml);
		$query = sprintf("/contexts/context[@name='%s']/licenses/license[@module='%s' and @license='%s']", $ctxName, $moduleName, $licenseName);
		$licensesList = $xpath->query($query);

		if( $licensesList->length <= 0 ) {
			$err = sprintf(__CLASS__."::".__FUNCTION__." "."Could not find a license for module '%s' in context '%s'.", $moduleName, $ctxName);
			$this->errorMessage = $err;
			$this->unlock($lock);
			return 'no';
		}

		if( $licensesList->length > 1 ) {
			$warn = sprintf(__CLASS__."::".__FUNCTION__." "."Warning: found more than one license for module '%s' in context '%s'", $moduleName, $ctxName);
			error_log($warn);
		}

		$licenseNode = $licensesList->item(0);

		$agree = ($licenseNode->getAttribute('agree') != 'yes')?'no':'yes';

		$this->unlock($lock);
		return $agree;
	}

	function storeLicenseAgreement($ctxName, $moduleName, $licenseName, $agree) {
		$lock = $this->lock();
		if( $lock === false ) {
			$err = sprintf(__CLASS__."::".__FUNCTION__." "."Could not get lock on context XML file.");
			error_log($err);
			$this->errorMessage($err);
			return false;
		}

		$xml = new DOMDocument();
		$xml->preserveWhiteSpace = false;
		$xml->formatOutput = true;
		$ret = $xml->load($this->contexts_filepath);
		if( $ret === false ) {
			$err = sprintf(__CLASS__."::".__FUNCTION__." "."Could not load XML file '%s'.", $this->contexts_filepath);
			error_log($err);
			$this->errorMessage = $err;
			$this->unlock($lock);
			return false;
		}

		$xpath = new DOMXpath($xml);

		$query = sprintf("/contexts/context[@name='%s']", $ctxName);
		$contextNodeList = $xpath->query($query);
		if( $contextNodeList->length <= 0 ) {
			$err = sprintf(__CLASS__."::".__FUNCTION__." "."Could not find context '%s' in '%s'.", $ctxName, $this->xcontexts_filepath);
			$this->errorMessage = $err;
			$this->unlock($lock);
			return false;
		}
		$contextNode = $contextNodeList->item(0);

		$licensesNode = null;
		$query = sprintf("/contexts/context[@name='%s']/licenses", $ctxName);
		$licensesNodeList = $xpath->query($query);
		if( $licensesNodeList->length <= 0 ) {
			// Create licenses node
			$licensesNode = $xml->createElement('licenses');
			$contextNodeList->item(0)->appendChild($licensesNode);
		} else {
			$licensesNode = $licensesNodeList->item(0);
		}

		$query = sprintf("/contexts/context[@name='%s']/licenses/license[@module='%s' and @license='%s']", $ctxName, $moduleName, $licenseName);
		$licenseNodeList = $xpath->query($query);

		if( $licenseNodeList->length > 1 ) {
			// That should not happen...
			// Cannot store/update license if multiple licenses exists.
			$err = sprintf(__CLASS__."::".__FUNCTION__." "."Warning: found more than one license for module '%s' in context '%s'", $moduleName, $ctxName);
			error_log($err);
			$this->errorMessage = $err;
			$this->unlock($lock);
			return false;
		}

		if( $licenseNodeList->length <= 0 ) {
			// Add a new license node.
			$licenseNode = $xml->createElement('license');
			$licenseNode->setAttribute('module', $moduleName);
			$licenseNode->setAttribute('license', $licenseName);
			$licenseNode->setAttribute('agree', $agree);

			$ret = $licensesNode->appendChild($licenseNode);
			if( ! is_object($ret) ) {
				$err = sprintf(__CLASS__."::".__FUNCTION__." "."Could not append license '%s' for module '%s' in context '%s'.", $moduleName, $licenseName, $ctxName);
				error_log($err);
				$this->errorMessage = $err;
				$this->unlock($lock);
				return false;
			}
		} else {
			// Update the existing license.
			$licenseNode = $licenseNodeList->item(0);
			$licenseNode->setAttribute('agree', $agree);
		}

		$ret = $xml->save($this->contexts_filepath);
		if( $ret === false ) {
			$err = sprintf(__CLASS__."::".__FUNCTION__." "."Error writing file '%s'.", $this->contexts_filepath);
			error_log($err);
			$this->errorMessage = $err;
			$this->unlock($lock);
			return false;
		}

		return $agree;
	}

	/**
	 * Check repo validity
	 */
	public function checkRepoValidity($name) {
		$repo = $this->getRepo($name);
		if( $repo === false ) {
			return false;
		}

		if( $repo->isValid() === false ) {
			return false;
		}

		return array('valid' => true, 'label' => $repo->label);
	}

	/**
	 * Get WIFF root path
	 */
	public function getWiffRoot() {
		$wiff_root = getenv('WIFF_ROOT');
		if( $wiff_root !== false ) {
			$wiff_root = $wiff_root.DIRECTORY_SEPARATOR;
		}
		return $wiff_root;
	}

	/**
	 * Delete a context
	 */
	public function deleteContext($contextName, &$result, $opt = false) {
		$result = true;
		if ($opt === 'unregister') {
			$context = $this->getContext($contextName, true);
		}
		else {
			$context = $this->getContext($contextName);

		}
		if( $context === false ) {
			$result = false;
			error_log("ContextName == $contextName ::: opt === $opt ::: error === $this->errorMessage");
			$this->errorMessage = sprintf("Error: could not get context '%s'.", $contextName);
			return $this->errorMessage;
		}

		$err = $context->delete($res, $opt);
		if( $res === false ) {
			$result = false;
			error_log("ContextName == $contextName ::: opt === $opt ::: error === $this->errorMessage");
			$this->errorMessage = sprintf("Error: could not delete context '%s': %s", $contextName, implode("\n", $err));
			return $this->errorMessage;
		}
		if (!empty($err)) {
			error_log(__CLASS__."::".__FUNCTION__." ".sprintf("The following errors occured : '%s'",$context->errorMessage));
			$this->errorMessage = sprintf("The following errors occured : '%s'",$context->errorMessage);
			return $err;
		}
		return ;
	}

}

?>
