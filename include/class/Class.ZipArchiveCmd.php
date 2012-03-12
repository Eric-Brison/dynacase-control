<?php

/**
 * ZipArchiveCmd Class
 * @author Anakeen
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License
 *
 * A class to create/extract Zip file using the command line 'zip' and
 * 'unzip' tools.
 * 
 * It's shamelessly inspired by the ZipArchive PHP class, but without
 * its limitation regarding Zip files > 2GB on 32 bits systems.
 * 
 * It's not a complete drop-in replacement for ZipArchive class, with
 * 100% compatible methods, but it implements enough functions to
 * fulfill basic zip creation and extraction.
 */

class ZipArchiveCmd {
	const CREATE = 1;
	const EXTRACT = 2;

	private $last_error = '';

	private $zipcmd = false;
	private $unzipcmd = false;
	private $zipfile = null;
	private $mode = null;

	public $verbose = false;

	public function __construct() {
		return $this;
	}

	/**
	 * Get last error message.
	 *
	 * @return string the last error message
	 */
	public function getStatusString() {
		return $this->last_error;
	}

	/**
	 * Open a zip file for creation or extraction.
	 *
	 * @param string $zipfile path name of the Zip file to open or create
	 * @param int $mode ZipArchiveCmd::CREATE or ZipArchiveCmd::EXTRACT
	 *
	 * @return boolean false on error or $this
	 */
	public function open($zipfile, $mode = self::EXTRACT) {
		include_once('lib/Lib.System.php');

		if( $mode != self::CREATE && $mode != self::EXTRACT ) {
			$this->last_error = sprintf("Wrong mode '%s'.", $mode);
			return false;
		}

		$zipcmd = WiffLibSystem::getCommandPath('zip');
		if( $zipcmd === false ) {
			$this->last_error = sprintf("Could not find 'zip' command in PATH '%s'.", getenv('PATH'));
			return false;
		}

		$unzipcmd = WiffLibSystem::getCommandPath('unzip');
		if( $unzipcmd === false ) {
			$this->last_error = sprintf("Could not find 'unzip' command in PATH '%s'.", getenv('PATH'));
			return false;
		}

		if( $mode == self::EXTRACT ) {
			if( ! file_exists($zipfile) ) {
				$this->last_error = sprintf("Zip file '%s' does not exists.", $zipfile);
				return false;
			}
		}
		
		if( substr($zipfile, 0, 1) != '/' ) {
			$zipfile = sprintf("%s/%s", getcwd(), $zipfile);
		}

		$this->zipcmd = $zipcmd;
		$this->unzipcmd = $unzipcmd;
		$this->zipfile = $zipfile;
		$this->mode = $mode;

		return $this;
	}

	/**
	 * Add a file to the Zip archive and keep its original
	 * path name into the archive.
	 *
	 * @param string $file the file to add
	 *
	 * @return boolean false on error or $this
	 */
	public function addFile($file) {
		return $this->_addFile($file, '');
	}

	/**
	 * Add a file to the Zip archive without keeping its original
	 * path name into the archive:
	 *
	 *    /foo/bar/baz.txt -> baz.txt
	 *
	 * @param string $file the file to add
	 *
	 * @return boolean false on error or $this
	 */
	public function addFileWithoutPath($file) {
		return $this->_addFile($file, '-j');
	}

	/**
	 * Add a file to the Zip archive with specific 'zip' command line flags
	 *
	 * @param string $file the file to add
	 * @param string $flags the 'zip' command line flags to use
	 *
	 * @return boolean false on error or $this
	 */
	private function _addFile($file, $flags) {
		if( $this->mode != self::CREATE ) {
			$this->last_error = sprintf("Zip file '%s' is not opened in CREATE mode.", $this->zipfile);
			return false;
		}

		$cmd = sprintf("%s %s %s %s", escapeshellarg($this->zipcmd), $flags, escapeshellarg($this->zipfile), escapeshellarg($file));

		$out = array();
		$ret = 0;
		if( $this->verbose ) {
			error_log(sprintf("Executing [%s][%s]", getcwd(), $cmd));
		}
		exec($cmd, $out, $ret);
		if( $ret != 0 ) {
			$this->last_error = sprintf("Error adding '%s' to '%s': %s", $file, $this->zipfile, join("\n", $out));
			return false;
		}

		return $this;
	}

	/**
	 * Add a file in the zip archive wiht the supplied file content
	 *
	 * @param string $filename the name of the file in the zip archive
	 * @param string $string the content of the file in the zip archive
	 *
	 * @return boolean false on error or $this
	 */
	public function addFromString($filename, $string) {
		include_once('lib/Lib.System.php');

		/* Refuse filenames which may get out of the base
		 * temporary directory by using the '../' sequence.
		 */
		if( strpos($filename, '../') !== false ) {
			$this->last_error = sprintf("For security reasons, the '../' sequence is not allowed in filename path '%s'.", $filename);
			return false;
		}

		/* Get directory name from filename */
		$f_dirname = dirname($filename);
		if( $f_dirname == '.' ) {
			$f_dirname = '';
		}
		$f_basename = basename($filename);
		if( substr($f_basename, -1, 1) == '/' ) {
			$this->last_error = sprintf("Filename '%s' seems to be a directory path.", $filename);
			return false;
		}

		/* Create the base temporary directory */
		$tmpname = WiffLibSystem::tempnam(null, __CLASS__);
		if( $tmpname === false ) {
			$this->last_error = sprintf("Could not create temporary file.");
			return false;
		}
		unlink($tmpname);
		$tmpdir = sprintf("%s/%s", $tmpname, $f_dirname);
		$ret = mkdir($tmpdir, 0700, true);
		if( $ret === false ) {
			$this->last_error = sprintf("Could not create temporary directory '%s'.", $tmpdir);
			return false;
		}

		/* Write the data to the filename constructed
		 * below the temporary directory.
		 */
		$tmpfile = sprintf("%s/%s", $tmpdir, $f_basename);
		if( file_exists($tmpfile) ) {
			$this->last_error = sprintf("File '%s' already exists in temporary directory '%s'.", $f_basename, $tmpdir);
			return false;
		}
		$ret = $this->_file_put_contents_excl_creat($tmpfile, $string);
		if( $ret === false ) {
			return false;
		}

		/* cd into temporary directory in order to add the file
		 * with its relative base pathname
		 */
		$cwd = getcwd();
		$ret = chdir($tmpname);
		if( $ret === false ) {
			unlink($tmpfile);
			$this->last_error = sprintf("Could not change directory to '%s'.", $tmpname);
			return false;
		}
		$ret = $this->addFile($filename);
		unlink($tmpfile);
		chdir($cwd);
		if( $ret === false ) {
			$this->last_error = sprintf("Could not add file '%s/%s'.", $tmpname, $filename);
			return false;
		}

		return $this;
	}

	/**
	 * Helper method to write content to a file
	 *
	 * @param string $tmpfile the filename to write to
	 * @param string $data the content to write
	 *
	 * @return boolean false on error or $this
	 */
	public function _file_put_contents_excl_creat($tmpfile, $data) {
		$fh = fopen($tmpfile, 'x+');
		if( $fh === false ) {
			$this->last_error = sprintf("Could not create temporary file '%s'.", $tmpfile);
			return false;
		}
		
		$len = strlen($data);
		$pos = 0;
		while( $pos < $len ) {
			$wsize = fwrite($fh, substr($data, $pos));
			if( $wsize === false ) {
				$this->last_error = sprintf("Error writing data to '%s' (written = %s / remain = %s).", $tmpfile, $pos, ($len-$pos));
				return false;
			}
			$pos += $wsize;
		}

		return $this;
	}

	/**
	 * Get the index of the Zip archive in the form of an array-of-array:
	 *   array(
	 *     array(
	 *       'name' => $file1_name,
	 *       'size' => $file1_size_in_bytes,
	 *       'date' => $file1_date,
	 *       'time' => $file1_time
	 *     ),
	 *     [...]
	 *   );
	 *
	 * @return boolean false on error or an array-of-array as decribed above
	 */
	public function getIndex() {
		$out = array();
		$ret = 0;
		$cmd = sprintf("%s -qql %s", escapeshellarg($this->unzipcmd), escapeshellarg($this->zipfile));
		if( $this->verbose ) {
			error_log(sprintf("Executing [%s][%s]", getcwd(), $cmd));
		}
		exec($cmd, $out, $ret);
		if( $ret != 0 ) {
			$this->last_error = sprintf("Error getting content index from Zip file '%s': %s", $this->zipfile, join("\n", $out));
			return false;
		}

		$index = array();
		foreach( $out as $line ) {
			if( preg_match('/^\s*(?P<size>\d+)\s+(?P<date>[0-9-]+)\s+(?P<time>\d\d:\d\d)\s+(?P<name>.*)$/', $line, $m) ) {
				$index[] = array(
					'name' => $m['name'],
					'size' => $m['size'],
					'date' => $m['date'],
					'time' => $m['time']
				);
			}
		}

		return $index;
	}

	/**
	 * Extract the archive into the specified directory
	 *
	 * @param string $exdir the directory to extract to
	 *
	 * @return boolean false on error or $this
	 */
	public function extractTo($exdir) {
		if( ! is_dir($exdir) ) {
			$this->last_error = sprintf("Extraction directory '%s' is not a valid directory.", $exdir);
			return false;
		}

		$out = array();
		$ret = 0;
		$cmd = sprintf("%s -d %s %s", escapeshellarg($this->unzipcmd), escapeshellarg($exdir), escapeshellarg($this->zipfile));
		if( $this->verbose ) {
			error_log(sprintf("%s Executing [%s][%s]", __CLASS__, getcwd(), $cmd));
		}
		exec($cmd, $out, $ret);
		if( $ret != 0 ) {
			$this->last_error = sprintf("Error extracting '%s' into directory '%s': %s", $this->zipfile, $exdir, join("\n", $out));
			return false;
		}

		return $this;
	}

	/**
	 * Get the content of a file from the archive
	 *
	 * @param string $name the filename in the archive
	 *
	 * @return boolean false on error or a string containing the content of the file
	 */
	public function getFileContentFromName($name) {
		$tmpfile = $this->getTmpFileFromName($name);
		if( $tmpfile === false ) {
			return false;
		}

		$data = file_get_contents($tmpfile);
		if( $data === false ) {
			$this->last_error = sprintf("Error reading content from temporary file '%s'.", $tmpfile);
			unlink($tmpfile);
			return false;
		}

		unlink($tmpfile);
		return $data;
	}

	/**
	 * Extract the content of a file into a temporary file
	 *
	 * @param string $name the filename to extract
	 *
	 * @return boolean false on error or a string containing the temporary filename holding the extracted content
	 */
	public function getTmpFileFromName($name) {
		$tmpfile = WiffLibSystem::tempnam(null, __CLASS__);
		if( $tmpfile === false ) {
			$this->last_error = sprintf("Error creating temporary file.");
			return false;
		}

		$out = array();
		$ret = 0;
		$cmd = sprintf("%s -p %s %s > %s", escapeshellarg($this->unzipcmd), escapeshellarg($this->zipfile), escapeshellarg($name), escapeshellarg($tmpfile));
		if( $this->verbose ) {
			error_log(sprintf("%s Executing [%s][%s]", __CLASS__, getcwd(), $cmd));
		}
		exec($cmd, $out, $ret);
		if( $ret != 0 ) {
			$this->last_error = sprintf("Error extracting file '%s' from archive '%s': %s", $name, $this->zipfile, join("\n", $out));
			unlink($tmpfile);
			return false;
		}

		return $tmpfile;
	}

	/**
	 * Close a previously opened archive
	 */
	public function close() {
		$this->zipcmd = false;
		$this->unzipcmd = false;
		$this->zipfile = null;
		$this->mode = 0;
	}
}
