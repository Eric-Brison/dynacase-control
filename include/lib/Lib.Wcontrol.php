<?php

require_once ('lib/Lib.System.php');

/**
 * evaluate a Process object
 */
function wcontrol_eval_process($process)
{
    if ($process->getName() == "check")
    {
        if (function_exists("wcontrol_check_".$process->getAttribute('type')))
        {
            error_log(sprintf("%s Running wcontrol_check_%s()", __FUNCTION__ , $process->getAttribute('type')));
            eval ("\$ret = wcontrol_check_".$process->getAttribute('type')."(\$process);");

            if (function_exists("wcontrol_msg_".$process->getAttribute('type')))
            {
                eval ("\$msg = wcontrol_msg_".$process->getAttribute('type')."(\$process);");
            } else
            {
                $msg = generic_message($process);
            }

            return array (
            'ret'=>$ret,
            'output'=>$msg
            );
        }
    } else if ($process->getName() == "process")
    {
        return wcontrol_process($process);
    }

    return array (
    'ret'=>false,
    'output'=>sprintf("Unknown process with name '%s'", $process->getName())
    );
}

/**
 *
 * @return
 * @param object $process
 */
function wcontrol_process($process)
{
    require_once ('lib/Lib.System.php');

    $cmd = $process->getAttribute('command');

    if (!preg_match('|^\s*/|', $cmd))
    {
        $ctx_root = getenv('WIFF_CONTEXT_ROOT');
        if ($ctx_root === false)
        {
            return array (
            'ret'=>false,
            'output'=>'WIFF_CONTEXT_ROOT env variable is not defined.'
            );
        }
        $cmd = sprintf("%s/%s", $ctx_root, $cmd);
    }

    /*
     $cmd = sprintf("( %s ) 2>&1 3>/dev/null; echo $? >&3", $cmd);
     $proc = proc_open($cmd,
     array(
     0 => array('pipe', 'r'),
     1 => array('pipe', 'w'),
     2 => array('pipe', 'w'),
     3 => array('pipe', 'w')
     ),
     $pipes,
     null,
     null
     );
     if( $proc === false ) {
     $ret = proc_close($proc);
     */

    $tmpfile = LibSystem::tempnam(null, 'wcontrol_process');
    if ($tmpfile === false)
    {
        return array (
        'ret'=>false,
        'output'=>'Error creating temporary file.'
        );
    }

    $cmd = sprintf('( %s ) 1> "%s" 2>&1', $cmd, escapeshellcmd($tmpfile));
    error_log(sprintf("%s %s", __FUNCTION__ , $cmd));

    /*
     $curdir = getcwd();
     if( $curdir === false ) {
     return array(
     'ret' => false,
     'output' => sprintf("Could not get current working directory.")
     );
     }
     $ctx_root = getenv('WIFF_CONTEXT_ROOT');
     if( chdir($ctx_root) === false )  {
     return array(
     'ret' => false,
     'output' => sprintf("Could not change directory to '%s'.", $ctx_root)
     );
     }
     */

    system($cmd, $ret);

    /*
     if( chdir($curdir) === false ) {
     return array(
     'ret' => false,
     'output' => sprintf("Could not change directory back to '%s'.", $curdir)
     );
     }
     */

    $output = file_get_contents($tmpfile);
    unlink($tmpfile);

    return array (
    'ret'=>($ret === 0)?true:false,
    'output'=>$output
    );
}

/**
 * generic message
 */
function generic_msg($process)
{
    return sprintf("Checking process with type '%s'", $process->getAttribute('type'));
}

/**
 * phpfunction check
 */

function wcontrol_check_phpfunction($process)
{
    return function_exists($process->getAttribute('function'));
}

function wcontrol_msg_phpfunction($process)
{
	//return "TEST " . function_exists('ncurses_init') . " END TEST.";
    return sprintf("Checking if the PHP function '%s' exists", $process->getAttribute('function'));
}

/**
 * exec check
 */

function wcontrol_check_exec($process)
{
    $out = system($process->getAttribute('cmd'), $ret);
    return ($ret === 0)?true:false;
}

function wcontrol_msg_check_exec($process)
{
    return sprintf("Checking if the command '%s' returns a success exit code", $process->getAttribute('cmd'));
}

/**
 * file check
 */

function wcontrol_check_file($process)
{
    switch($process->getAttribute('predicate'))
    {
        case 'file_exists':
        case 'e':
        case '-e':
        case 'a':
        case '-a':
            return file_exists($process->getAttribute('file'));
            break;
        case 'is_dir':
        case 'd':
        case '-d':
            return is_dir($process->getAttribute('file'));
            break;
        case 'is_file':
        case 'f':
        case '-f':
            return is_file($process->getAttribute('file'));
            break;
        case 'is_link':
        case 'L':
        case '-L':
            return is_link($process->getAttribute('file'));
            break;
        case 'is_readable':
        case 'r':
        case '-r':
            return is_readable($process->getAttribute('file'));
            break;
        case 'is_writable':
        case 'w':
        case '-w':
            return is_writable($process->getAttribute('file'));
            break;
        case 'is_executable':
        case 'x':
        case '-x':
            return is_executable($process->getAttribute('file'));
            break;
        default:
            return false;
    }
}

function wcontrol_msg_check_file($process)
{
    return sprintf("Checking if the file '%s' validate the predicate '%s'", $process->getAttribute('file'), $process->getAttribute('predicate'));
}

/**
 * syscommand check
 */

function wcontrol_check_syscommand($process)
{
    $ret = LibSystem::getCommandPath($process->getAttribute('command'));
    if ($ret === false)
    {
        return false;
    }
    return true;
}

function wcontrol_msg_syscommand($process)
{
    return sprintf("Checking if the command '%s' is in the PATH", $process->getAttribute('command'));
}

/**
 * pearmodule check
 */

function wcontrol_check_pearmodule($process)
{
    return wcontrol_check_phpclass($process);
}

function wcontrol_check_phpclass($process)
{
    $include = $process->getAttribute('include');
    if ($include != "")
    {
        $ret = @ include ($include);
        if ($ret == false)
        {
            return false;
        }
    }
    if (!class_exists($process->getAttribute('class')))
    {
        return false;
    }
    return true;
}

function wcontrol_msg_pearmodule($process)
{
    return wcontrol_msg_phpclass($process);
}

function wcontrol_msg_phpclass($process)
{
    return sprintf("Checking if the class '%s' is available in include file '%s'", $process->getAttribute('class'), $process->getAttribute('include'));
}

/**
 * apachemodule check
 */

function wcontrol_check_apachemodule($process)
{
    if (!function_exists('apache_get_modules'))
    {
        return true;
    }
    $mods = apache_get_modules();
    if (in_array($process->getAttribute('module'), $mods))
    {
        return true;
    }
    return false;
}

function wcontrol_msg_apachemodule($process)
{
    return sprintf("Checking if the Apache module '%s' is loaded", $process->getAttribute('module'));
}

/**
 * pgversion check
 */

function wcontrol_check_pgversion( & $process)
{
	
	if(!function_exists('pg_connect'))
	{
		$process->errorMessage = 'PHP function pg_connect() not available. You might need to install a php-pg package from your distribution in order to have Postgresql support in PHP.</help>';
		return false;
	}
	
    require_once ('class/Class.WIFF.php');

    $service = $process->getAttribute('service');
    $predicate = $process->getAttribute('predicate');
    $version = $process->getAttribute('version');

    $wiff = WIFF::getInstance();
    $service = $wiff->expandParamValue($service);

    if ($service == "")
    {
        return false;
    }
    if ($version == "")
    {
        return false;
    }

    $conn = pg_connect("service='$service'");
    if ($conn === false)
    {
        $process->errorMessage = "Connection failed. Maybe postgresql server is not started or database '".$service."' does not exist. ";
        return false;
    }

    $res = pg_query($conn, "SHOW SERVER_VERSION");
    if ($res === false)
    {
        pg_close($conn);
        return false;
    }
    $row = pg_fetch_row($res);
    if ($row === false)
    {
        pg_close($conn);
        return false;
    }

    $verstr_server = join("", array_map(create_function('$v', 'return sprintf("%03d", $v);'), preg_split("/\./", $row[0])));
    $verstr_target = join("", array_map(create_function('$v', 'return sprintf("%03d", $v);'), preg_split("/\./", $version)));

    switch($predicate)
    {
        case 'eq':
            $op = "equal to";
            $predic = ($verstr_server = $verstr_target)?true:false;
        break;
        case 'ne':
            $op = "not equal to";
            $return = ($verstr_server != $verstr_target)?true:false;
        break;
        case 'lt':
            $op = "less than";
            $return = ($verstr_server < $verstr_target)?true:false;
        break;
        case 'le':
            $op = "less than or equal to";
            $return = ($verstr_server <= $verstr_target)?true:false;
        break;
        case 'gt':
            $op = "greater than";
            $return = ($verstr_server > $verstr_target)?true:false;
        break;
        case 'ge':
            $op = "greater or equal to";
            $return = ($verstr_server >= $verstr_target)?true:false;
        break;
    }

    if (!$return)
    {
        $process->errorMessage = "Server version (currently ".$row[0].") must be ".$op." ".$version.".";
        return false;
    }

    $encoding = pg_client_encoding($conn);

    if (!in_array(strtolower($encoding), array ('unicode', 'utf8')))
    {
    	$process->errorMessage = "Database encoding : ".$encoding.". UTF8 required. ";
    	return false;
    }

    return true;
}

function wcontrol_msg_pgversion($process)
{
    if ($process->errorMessage)
    {
        return $process->errorMessage;
    }
    else
    {
        return "";
    }
}

/**
 * pgempty check
 */

function wcontrol_check_pgempty( & $process)
{
	
	if(!function_exists('pg_connect'))
	{
		$process->errorMessage = 'PHP function pg_connect() not available. You might need to install a php-pg package from your distribution in order to have Postgresql support in PHP.</help>';
		return false;
	}
	
    require_once ('class/Class.WIFF.php');

    $service = $process->getAttribute('service');

    $wiff = WIFF::getInstance();
    $service = $wiff->expandParamValue($service);

    if ($service == "")
    {
        return false;
    }

    $conn = pg_connect("service='$service'");
    if ($conn === false)
    {
        $process->errorMessage = "Connection failed. Maybe postgresql server is not started or database '".$service."' does not exist. ";
        return false;
    }

	// Test if database is empty
    $res = pg_query($conn, "SELECT COUNT(*) FROM information_schema.tables WHERE table_type = 'BASE TABLE' AND table_schema NOT IN ('information_schema','pg_catalog');");
    if ($res === false)
    {
        pg_close($conn);
        return false;
    }
	$row = pg_fetch_row($res);
    if ($row === false)
    {
        pg_close($conn);
        return false;
    }
	
	$table_number = $row[0];
	if ($table_number != 0)
	{
		$process->errorMessage = "Database for service " . $service . " is not empty of user defined tables (". $table_number ." found)".";
		return false;
	}
	

    return true;
}

function wcontrol_msg_pgempty($process)
{
    if ($process->errorMessage)
    {
        return $process->errorMessage;
    }
    else
    {
        return "";
    }
}

function wcontrol_check_ncurses( & $process)
{
	
	ob_start();

	system('php -r "print function_exists("ncurses_init");"');
	
	$result=ob_get_contents();
	
  	ob_end_clean();
	
	if($result != 1)
	{
		return false ;
	}
	
	return true ;
	
}

function wcontrol_msg_ncurses($process)
{
	return "";
}

?>
