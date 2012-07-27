<?php


/**
 * StatCollector Class
 * @author Anakeen
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License
 */

class StatCollector  {
	private $dom = null;
	private $stats = null;

	private $wiff = null;
	private $context = null;

	/**
	 * The last error message.
	 * 
	 * Whenever a method returns a boolean false value, you ought to
	 * get the complete error message here.
	 * 
	 * @var string
	 */
	public $last_error = '';

	/**
	 * StatCollector constructor
	 *
	 * @param WIFF $wiff WIFF object (default null)
	 * @param Context $context Context object (default null)
	 *
	 * @return \StatCollector current object ($this)
	 */
	public function __construct(&$wiff = null, &$context = null) {
		$this->wiff = $wiff;
		$this->context = $context;

		return $this;
	}

	/**
	 * Collect statistics/informations on the context and the system
	 *
	 * @return the current object ($this)
	 */
	public function collect() {
		if( $this->wiff === null || $this->context === null ) {
			return false;
		}
		
		$this->dom = new DOMDocument('1.0', 'utf-8');
		$this->dom->preserveWhiteSpace = false;
		$this->dom->formatOutput = true;

		$this->stats = $this->dom->createElement('stats');
		$this->dom->appendChild($this->stats);

		$this->_collect_wiffVersion();
		/* $this->_collect_contextName(); */
		$this->_collect_contextModuleList();
		$this->_collect_contextPHP();
		$this->_collect_contextPostgresql();
		$this->_collect_contextSystem();
		return $this;
	}

	/**
	 * Get the statistics as a XML string serialization.
	 *
	 * @return string containing a XML <stat></stat> document
	 */
	public function getXML() {
		if( $this->dom === null ) {
			$this->last_error = "No stat has been collected yet.";
			return false;
		}
		
		return $this->dom->saveXML();
	}

	/**
	 * Collect wiff statistics (version)
	 *
	 * @return the current object ($this)
	 */
	private function _collect_wiffVersion() {
		$node = $this->dom->createElement('wiff');
		$node->setAttribute('version', $this->wiff->getVersion());

		$this->stats->appendChild($node);

		return $this;
	}

	/**
	 * Collect context statistics (name)
	 *
	 * @return the current object ($this)
	 */
	private function _collect_contextName() {
		$node = $this->dom->createElement('name');
		$text = $this->dom->createTextNode($this->context->name);
		$node->appendChild($text);

		$this->stats->appendChild($node);

		return $this;
	}

	/**
	 * Collect context modules (modules name, version, release, etc.)
	 *
	 * @return the current object ($this)
	 */
	private function _collect_contextModuleList() {
		$modulesNode = $this->dom->createElement('modules');

		$moduleList = $this->context->getInstalledModuleList();
		foreach( $moduleList as &$module ) {
			$node = $this->dom->createElement('module');
			$node->setAttribute('name', $module->name);
			$node->setAttribute('version', $module->version);
			$node->setAttribute('release', $module->release);
			$node->setAttribute('vendor', $module->vendor);
			$node->setAttribute('builder', $module->builder);
			$modulesNode->appendChild($node);
		}
		unset($module);

		$this->stats->appendChild($modulesNode);

		return $this;
	}

	/**
	 * Collect PHP statistics (version)
	 *
	 * @return the current object ($this)
	 */
	private function _collect_contextPHP() {
		$phpNode = $this->dom->createElement('php');
		$phpNode->setAttribute('version', PHP_VERSION);

		$this->stats->appendChild($phpNode);

		return $this;
	}

	/**
	 * Collect Postgresql statistics (server version)
	 *
	 * @return boolean false on error or the current object ($this)
	 */
	private function _collect_contextPostgresql() {
		$pgservice_core = $this->context->getParamByName('core_db');
		if( $pgservice_core == '' ) {
			$this->last_error = sprintf("Undefined or empty core_db parameter in context '%s'.", $this->context->name);
			return false;
		}
		$conn = pg_connect(sprintf('service=%s', $pgservice_core));
		if( $conn === false ) {
			$this->last_error = sprintf("Could not connect to pg service '%s'.", $pgservice_core);
			return false;
		}
		$version = pg_version($conn);
		if( $version === false ) {
			$this->last_error = sprintf("Could not get pg version: %s", pg_last_error($conn));
			return false;
		}

		$pgNode = $this->dom->createElement('postgresql');
		$pgNode->setAttribute('version', $version['server']);

		$this->stats->appendChild($pgNode);

		return $this;
	}

	/**
	 * Collect system statistics (uname, memory, processors, etc.)
	 *
	 * @return the current object ($this)
	 */
	private function _collect_contextSystem() {
		$systemNode = $this->dom->createElement('system');

		$out = array();
		$uname = php_uname();
		$memory = 0;
		if( preg_match('/^Darwin/', $uname) ) {
			exec("sysctl hw.memsize", $out);
			if( preg_match('/^hw.memsize: (?P<size>\d+)$/m', join("\n", $out), $m) ) {
				$memory = $m['size'];
			}
		} else {
			exec("free -b", $out);
			if( preg_match('/^Mem:\s+(?P<size>\d+)/m', join("\n", $out), $m) ) {
				$memory = $m['size'];
			}
		}
		$out = array();
		$processors_count = 0;
		if( preg_match('/^Darwin/', $uname) ) {
			exec("sysctl hw.ncpu", $out);
			if( preg_match('/^hw.ncpu: (?P<count>\d+)$/m', join("\n", $out), $m) ) {
				$processors_count = $m['count'];
			}
		} else {
			exec("grep -c '^processor' /proc/cpuinfo", $out);
			if( preg_match('/^(?P<count>\d+)$/m', join("\n", $out), $m) ) {
				$processors_count = $m['count'];
			}
		}

		$node = $this->dom->createElement('uname');
		$text = $this->dom->createTextNode($uname);
		$node->appendChild($text);
		$systemNode->appendChild($node);

		$node = $this->dom->createElement('memory');
		$text = $this->dom->createTextNode($memory);
		$node->appendChild($text);
		$systemNode->appendChild($node);

		$processorsNode = $this->dom->createElement('processors');
		$processorsNode->setAttribute('count', $processors_count);
		$systemNode->appendChild($processorsNode);

		$out = array();
		$processorInfoList = array();
		if( preg_match('/^Darwin/', $uname) ) {
			for($i = 0; $i < $processors_count; $i++) {
				$processorInfoList[] = 'Unsupported platform';
			}
		} else {
			exec("cat /proc/cpuinfo", $out);
			$out = trim(join("\n", $out));
			$processorInfoList = preg_split('/^$/m', $out);
		}
		foreach( $processorInfoList as $processorInfo ) {
			$node = $this->dom->createElement('processor');
			$text = $this->dom->createTextNode($processorInfo);
			$node->appendChild($text);
			$processorsNode->appendChild($node);
		}

		$this->stats->appendChild($systemNode);

		return $this;
	}

	/**
	 * Get a machine ID (which should be persistent and identify the machine
	 * on which Control is running).
	 * 
	 * @return string|false the ID string or boolean false in case of error
	 */
	public function getMachineId() {
		$uname_s = php_uname("s");
		switch( $uname_s ) {
			case 'Linux':
				return $this->getMachineId_Linux();
				break;
			case 'Darwin' :
				return $this->getMachineId_Darwin();
				break;
		}
		$this->last_error = sprintf("Unknown system '%s'.", $uname_s);
		return false;
	}

	/**
	 * Get a machine ID for Linux systems.
	 * 
	 * @return string|false the ID string or boolean false in case of error
	 */
	public function getMachineId_Linux() {
		$hwaddr = $this->getMachineMacAddr_Linux();
		$cpucount = $this->getMachineCPUCount_Linux();

		if( $hwaddr === false ) {
			/* Use the system node name as a fallback if no ether MAC addr was found.*/
			$hwaddr = php_uname("n");
		}
		if( $hwaddr === false || $cpucount === false ) {
			$this->last_error = sprintf("Could not compute machine ID for Linux host type");
			return false;
		}

		$mid = sprintf("%s,%s", $hwaddr, $cpucount);
		return sha1($mid);
	}
	
	/**
	 * Get a machine ID for Darwin (Mac OS X) systems
	 * 
	 * @return string|false the ID string or boolean false in case of error
	 */
	public function getMachineId_Darwin() {
		$hwaddr = $this->getMachineMacAddr_Darwin();
		$cpucount = $this->getMachineCPUCount_Darwin();

		if( $hwaddr === false || $cpucount === false ) {
			$this->last_error = sprintf("Could not compute machine ID for Darwin host type: %s", $this->last_error);
			return false;
		}
		
		$mid = sprintf("%s,%s", $hwaddr, $cpucount);
		return sha1($mid);
	}

	/**
	 * Get the "main" MAC address of a Linux system.
	 * 
	 * It returns the MAC address of eth0 (if present), or the MAC address of
	 * the first ethernet interface.
	 * 
	 * @return string|false the MAC addr string or boolean false in case of error
	 */
	public function getMachineMacAddr_Linux() {
		$hwaddr = $this->getMachineMacAddr_Linux_iproute2();
		if( $hwaddr === false ) {
			$hwaddr = $this->getMachineMacAddr_Linux_ifconfig();
		}

		if( $hwaddr === false ) {
			return false;
		}

		return strtolower($hwaddr);
	}

	/**
	 * Get the "main" MAC address of a Linux system using the `ip' command
	 * from `iproute2'.
	 * 
	 * @return string|false the MAC addr string or boolean false in case of error
	 */
	public function getMachineMacAddr_Linux_iproute2() {
		include_once('lib/Lib.System.php');
		
		$ip = WiffLibSystem::getCommandPath('ip');
		if( $ip === false ) {
			$ip = '/sbin/ip';
		}

		$hwaddr = false;

		$out = array();
		$ret = 0;
		$locale = getenv("LC_ALL");
		putenv("LC_ALL=C");
		exec(sprintf("%s link show", escapeshellarg($ip)), $out, $ret);
		putenv(sprintf("LC_ALL=%s", $locale));
		if( $ret != 0 ) {
			$this->last_error = sprintf("Error executing '%s link show': %s", $ip, join(' / ', $out));
			return false;
		}

		$ifname = false;
		foreach( $out as $line ) {
			if( preg_match('/^(?P<ifindex>\d+):\s+(?P<ifname>[^\s]+):\s+.*$/', $line, $m) ) {
				$ifname = $m['ifname'];
				continue;
			} elseif( preg_match('|^\s+link/ether\s+(?P<hwaddr>[0-9a-f][0-9a-f]:[0-9a-f][0-9a-f]:[0-9a-f][0-9a-f]:[0-9a-f][0-9a-f]:[0-9a-f][0-9a-f]:[0-9a-f][0-9a-f])\s+.*$|i', $line, $m) ) {
				if( $ifname == 'eth0' ) {
					$hwaddr = $m['hwaddr'];
					break;
				}
				if( $hwaddr === false ) {
					$hwaddr = $m['hwaddr'];
					continue;
				}
			}
		}

		return $hwaddr;
	}

	/**
	 * Get the "main" MAC address of a Linux system using the `ifconfig' command
	 * from `net-tools'.
	 * 
	 * @return string|false the MAC addr string or boolean false in case of error
	 */
	public function getMachineMacAddr_Linux_ifconfig() {
		include_once('lib/Lib.System.php');

		$ifconfig = WiffLibSystem::getCommandPath('ifconfig');
		if( $ifconfig === false ) {
			$ifconfig = '/sbin/ifconfig';
		}

		$hwaddr = false;

		$out = array();
		$ret = 0;
		$locale = getenv("LC_ALL");
		putenv("LC_ALL=C");
		exec(sprintf("%s", escapeshellarg($ifconfig)), $out, $ret);
		putenv(sprintf("LC_ALL=%s", $locale));
		if( $ret != 0 ) {
			$this->last_error = sprintf("Error executing '%s': %s", $ifconfig, join(' / ', $out));
			return false;
		}

		foreach( $out as $line ) {
			if( preg_match('/^(?P<ifname>[^\s]+)\s+Link\s+encap:Ethernet\s+HWaddr\s+(?P<hwaddr>[0-9a-f][0-9a-f]:[0-9a-f][0-9a-f]:[0-9a-f][0-9a-f]:[0-9a-f][0-9a-f]:[0-9a-f][0-9a-f]:[0-9a-f][0-9a-f])$/i', $line, $m) ) {
				if( $m['ifname'] == 'eth0' ) {
					$hwaddr = $m['hwaddr'];
					break;
				}
				if( $hwaddr === false ) {
					$hwaddr = $m['hwaddr'];
				}
			}
		}

		return $hwaddr;
	}

	/**
	 * Get the number of CPUs of a Linux system.
	 * 
	 * @return int|false the number of CPUs or boolean false in case of error
	 */
	public function getMachineCPUCount_Linux() {
		include_once('lib/Lib.System.php');
		
		$grep = WiffLibSystem::getCommandPath('grep');
		if( $grep === false ) {
			$grep = '/bin/grep';
		}
		
		$ret = 0;
		$out = array();
		exec(sprintf("%s -c '^processor' /proc/cpuinfo", escapeshellarg($grep)), $out, $ret);
		if( $ret != 0 ) {
			$this->last_error = sprintf("Error grepping /proc/cpuinfo: %s", join(' / ', $out));
			return false;
		}
		
		$processors_count = false;
		if( preg_match('/^(?P<count>\d+)$/m', join("\n", $out), $m) ) {
			$processors_count = $m['count'];
		}
		
		if( $processors_count === false ) {
			$this->last_error = sprintf("cpuinfo processor count not found.");
			return false;
		}
				
		return $processors_count;
	}
	
	/**
	 * Get the "main" MAC address of a Darwin system.
	 * 
	 * It returns the MAC address of the en0 interface.
	 * 
	 * @return string|false the MAC addr string or boolean false in case of error
	 */
	public function getMachineMacAddr_Darwin() {
		include_once('lib/Lib.System.php');
		
		$netstat = WiffLibSystem::getCommandPath('netstat');
		if( $netstat === false ) {
			$netstat = '/usr/sbin/netstat';
		}
		
		$out = array();
		$ret = 0;
		exec(sprintf("%s -I en0", escapeshellarg($netstat)), $out, $ret);
		if( $ret != 0 ) {
			$this->last_error = sprintf("Could not get en0 MAC addr with netstat '%s'.", $netstat);
			return false;
		}
		
		$hwaddr = false;
		foreach( $out as $line ) {
			if( preg_match('/^en0\s+\d+\s+.*?(?P<hwaddr>[0-9a-fA-F][0-9a-fA-F]:[0-9a-fA-F][0-9a-fA-F]:[0-9a-fA-F][0-9a-fA-F]:[0-9a-fA-F][0-9a-fA-F]:[0-9a-fA-F][0-9a-fA-F]:[0-9a-fA-F][0-9a-fA-F])\s+/', $line, $m) ) {
				$hwaddr = $m['hwaddr'];
				break;
			}
		}
		
		if( $hwaddr === false ) {
			$this->last_error = sprintf("MAC addr not found.");
			return false;
		}
		
		return strtolower($hwaddr);
	}
	
	/**
	 * Get the number of CPUs of a Darwin system.
	 * 
	 * @return int|false the number of CPUs or boolean false in case of error
	 */
	public function getMachineCPUCount_Darwin() {
		include_once('lib/Lib.System.php');
		
		$sysctl = WiffLibSystem::getCommandPath('sysctl');
		if( $sysctl === false ) {
			$sysctl = '/usr/sbin/sysctl';
		}
		
		$ret = 0;
		$out = array();
		exec(sprintf("%s hw.ncpu", escapeshellarg($sysctl)), $out, $ret);
		if( $ret != 0 ) {
			$this->last_error = sprintf("Could not get hw.ncpu with sysctl '%s'.", $sysctl);
			return false;
		}
		$processors_count = false;
		if( preg_match('/^hw.ncpu: (?P<count>\d+)$/m', join("\n", $out), $m) ) {
			$processors_count = $m['count'];
		}
		
		if( $processors_count === false ) {
			$this->last_error = sprintf("hw.ncpu not found.");
			return false;
		}
		
		return $processors_count;
	}
	
}
