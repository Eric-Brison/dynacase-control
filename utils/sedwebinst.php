<?php
/*
 * @author Anakeen
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU Affero General Public License
*/

if (isset($argv[0]) && $argv[0] != '-') {
    sedwebinst_cli($argv);
}

function sedwebinst($directory, $pattern, $replacement, $opts)
{
    if (is_file($directory)) {
        return sedwebinst_file($directory, $pattern, $replacement, $opts);
    }
    $fd = opendir($directory);
    if ($fd === false) {
        throw new Exception(sprintf("Error: error opening directory '%s'.", $directory));
    }
    while (($file = readdir($fd)) !== false) {
        if ($file == '.' || $file == '..') {
            continue;
        }
        $path = $directory . DIRECTORY_SEPARATOR . $file;
        if ($opts['recursive'] && is_dir($path)) {
            sedwebinst($path, $pattern, $replacement, $opts['recursive'], $opts);
        }
        if (is_file($path) && preg_match('/\.webinst$/', $path)) {
            sedwebinst_file($path, $pattern, $replacement, $opts);
        }
    }
}

function sedwebinst_file($file, $pattern, $replacement, $opts)
{
    if (!is_file($file)) {
        throw new Exception("Error: '%s' is not a valid file.", $file);
    }
    print sprintf("* Processing '%s'...\n", $file);
    $tmpdir = sedwebinst_unpack($file);
    $infoXml = $tmpdir . DIRECTORY_SEPARATOR . 'info.xml';
    if (!is_file($infoXml)) {
        error_log(sprintf("Warning: missing 'info.xml' in unpacked directory from '%s'.", $file));
        sedwebinst_rmdir($tmpdir);
        return;
    }
    $content = file_get_contents($infoXml);
    if ($content === false) {
        throw new Exception(sprintf("Error: error reading content from '%s'.", $infoXml));
    }
    if ($opts['regex']) {
        $content = preg_replace($pattern, $replacement, $content);
        if ($content === null) {
            throw new Exception(sprintf("Error: invalid regex substitution '%s' -> '%s'.", $pattern, $replacement));
        }
    } else {
        $content = str_replace($pattern, $replacement, $content);
    }
    $ret = file_put_contents($infoXml, $content);
    if ($ret === false) {
        throw new Exception(sprintf("Error: error writing content to '%s'.", $infoXml));
    }
    $tmpwebinst = sedwebinst_pack($tmpdir);
    $ret = sedwebinst_rename($tmpwebinst, $file);
    if ($ret === false) {
        throw new Exception(sprintf("Error: error renaming '%s' to '%s'.", $tmpwebinst, $file));
    }
    sedwebinst_rmdir($tmpdir);
}

function sedwebinst_unpack($file)
{
    $tmpdir = tempnam(dirname($file) , 'sedwebinst_unpack');
    if ($tmpdir === false) {
        throw new Exception(sprintf("Error: error creating temporary file in '%s'.", dirname($file)));
    }
    unlink($tmpdir);
    $ret = mkdir($tmpdir, 0700);
    if ($ret === false) {
        throw new Exception(sprintf("Error: error creating temporary directory '%s'.", $tmpdir));
    }
    $cmd = sprintf('tar -C %s -zxf %s', escapeshellarg($tmpdir) , escapeshellarg($file));
    exec($cmd, $out, $ret);
    if ($ret != 0) {
        throw new Exception(sprintf("Error: error unpacking webinst '%s' into '%s': %s", $file, $tmpdir, $out));
    }
    return $tmpdir;
}

function sedwebinst_pack($dir)
{
    $tmpfile = tempnam(dirname($dir) , 'sedwebinst_pack');
    if ($tmpfile === false) {
        throw new Exception(sprintf("Error: error creating temporary file in '%s'.", dirname($dir)));
    }
    $includeList = glob($dir . DIRECTORY_SEPARATOR . '*');
    foreach ($includeList as & $elmt) {
        $elmt = basename($elmt);
    }
    $include = join(' ', $includeList);
    $cmd = sprintf('tar -C %s -zcf %s content.tar.gz %s', $dir, $tmpfile, $include);
    exec($cmd, $out, $ret);
    if ($ret != 0) {
        throw new Exception(sprintf("Error: error packing webinst from '%s'.", $dir));
    }
    return $tmpfile;
}

function sedwebinst_rmdir($dir)
{
    foreach (glob($dir . DIRECTORY_SEPARATOR . '*') as $file) {
        if (is_dir($file)) {
            sedwebinst_rmdir($file);
        } else {
            unlink($file);
        }
    }
    rmdir($dir);
}

function sedwebinst_rename($old, $new) {
	$chmod = (file_exists($new) && ($chmod = stat($new)) !== false) ? $chmod['mode'] : false;
	$ret = rename($old, $new);
	if ($ret === false) {
		return false;
	}
	if ($chmod !== false) {
		$ret = chmod($new, $chmod);
		if ($ret === false) {
			return false;
		}
	}
	return true;
}

function sedwebinst_keybread()
{
    $fh = fopen('php://stdin', 'r');
    if ($fh === false) {
        throw new Exception(sprintf("Error: error opening STDIN."));
    }
    $ans = fgets($fh);
    if ($ans === false) {
        throw new Exception(sprintf("Error: got invalid 'false' response."));
    }
    $ans = trim($ans);
    return $ans;
}

function sedwebinst_usage()
{
    print sprintf(<<<'EOT'
Perform a massive string substitution (or regex substitution) in the
'info.xml' file of webinst files located in a given directory.

Usage: %s [OPTIONS]... <PATTERN> <REPLACEMENT> <DIRECTORY>...

  -r, --recursive process all files recursively in the given directory.
  -c              do not ask confirmation.
  -e,  --regex    treat the pattern and replacement string as a regexe
                  pattern/replacement.


EOT
    , basename(__FILE__));
}

function sedwebinst_cli($argv)
{
    array_shift($argv);
    $confirm = true;
    $opts = array(
        'recursive' => false,
        'regex' => false
    );
    while (count($argv) > 0) {
        $opt = $argv[0];
        switch ($opt) {
            case '-h':
            case '--help':
                sedwebinst_usage();
                exit(0);
            case '-r':
            case '--recursive':
                $opts['recursive'] = true;
                break;

            case '-c':
                $confirm = false;
                break;

            case '-e':
            case '--regex':
                $opts['regex'] = true;
                break;

            case '--':
                break 2;
            default:
                break 2;
        }
        array_shift($argv);
    }
    $pattern = array_shift($argv);
    if ($pattern === null) {
        print sprintf("Missing 'pattern' argument.\n");
        sedwebinst_usage();
        exit(1);
    }
    $replacement = array_shift($argv);
    if ($replacement === null) {
        print sprintf("Missing 'replacement' argument.\n");
        sedwebinst_usage();
        exit(1);
    }
    if (count($argv) <= 0) {
        print sprintf("Missing 'directory' argument.\n");
        sedwebinst_usage();
        exit(1);
    }
    if ($confirm) {
        print "\n";
        if ($opts['regex']) {
            print sprintf("This process will apply regex '%s' -> '%s' on the 'info.xml' file of all webinst files located in '%s'.\n", $pattern, $replacement, join(':', $argv));
        } else {
            print sprintf("This process will replace the string '%s' by '%s' in the 'info.xml' file of all webinst files located in '%s'.\n", $pattern, $replacement, join(':', $argv));
        }
        print sprintf("It's gonna be brutal...\n");
        print "\n";
        print sprintf("Do you really want to proceed? [N/y] ");
        $ans = sedwebinst_keybread();
        if ($ans != 'y' && $ans != 'Y') {
            exit(0);
        }
    }
    try {
        while (count($argv) > 0) {
            sedwebinst(array_shift($argv) , $pattern, $replacement, $opts);
        }
    }
    catch(Exception $e) {
        print sprintf("%s\n", $e->getMessage());
        exit(2);
    }
    exit(0);
}
