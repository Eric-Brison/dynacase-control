#!/usr/bin/env php
<?php

function usage() {
  echo "Usage:\n";
  echo "\n";
  echo "  context_migr -s|--service <pg_service_name> -c|--context <context_root>\n";
  echo "    [-x|--xml </path/to/wiff/cont/contexts.xml>]\n";
  echo "    [-n|--name <context_name>]\n";
  echo "    [-d|--description <context_description>]\n";
  echo "    [-f|--force]\n";
  echo "\n";
  echo "If no --xml is specified, default output is to stdout.\n";
  echo "\n";
}

$cmd = array_shift($argv);
$PGSERVICE = '';
$CONTEXT_ROOT = '';
$CONTEXTS_XML = '';
$CONTEXT_NAME = '';
$CONTEXT_DESCRIPTION = '';
$FORCE = false;
while( $opt = array_shift($argv) ) {
  switch( $opt ) {
  case '-s':
  case '--service':
    $PGSERVICE = array_shift($argv);
    if( $PGSERVICE == '' ) {
      error_log("Empty --service\n");
      usage(); exit( 1 );
    }
    break;
  case '-c':
  case '--context':
    $CONTEXT_ROOT = array_shift($argv);
    if( $CONTEXT_ROOT == '' ) {
      error_log("Empty --context\n");
      usage(); exit( 1 );
    }
    break;
  case '-x':
  case '--xml':
    $CONTEXTS_XML = array_shift($argv);
    if( $CONTEXTS_XML == '' ) {
      error_log("Empty --xml\n");
      usage(); exit( 1 );
    }
    break;
  case '-n':
  case '--name':
    $CONTEXT_NAME = array_shift($argv);
    if( $CONTEXT_NAME == '' ) {
      error_log("Empty --name\n");
      usage(); exit( 1 );
    }
    break;
  case '-d':
  case '--description':
    $CONTEXT_DESCRIPTION = array_shift($argv);
    if( $CONTEXT_DESCRIPTION == '' ) {
      error_log("Empty --description\n");
      usage(); exit( 1 );
    }
    break;
  case '-f':
  case '--force':
    $FORCE = true;
    break;
  default:
    error_log(sprintf("Unknown option '%s'.", $opt));
    usage(); exit( 1 );
  }
}

if( $PGSERVICE == '' ) {
  usage(); exit( 1 );
}
if( $CONTEXT_ROOT == '' ) {
  usage(); exit( 1 );
}

$app_map = array(
		'CORE' => 'freedom-core',
		'FDC' => 'freedom-common',
		'FREEDOM' => 'freedom',
		'FREEEVENT' => 'freedom-freeevent',
		'NU' => 'freedom-networkuser',
		'THESAURUS' => 'freedom-thesaurus',
		'VAULT' => 'freedom-vault',
		'WEBDESK' => 'freedom-webdesk',
		'WGCAL' => 'freedom-wgcal',
		'DAV' => 'freedom-dav',
		'FILECONNECTOR' => 'freedom-fileconnector',
		'MAILCONNECTOR' => 'freedom-mailconnector',
		);

$fam_map = array(
		'SEARCHSHEET' => 'freedom-searchsheet',
		);

$dbh = pg_connect(sprintf('service=%s', $PGSERVICE));
if( $dbh === false ) {
  error_log(sprintf("Error connecting to database with service '%s'.", $PGSERVICE));
  exit( 1 );
}

# -- Extract applications

$res = pg_query($dbh, "SELECT app.name, p.val FROM application AS app, paramv AS p WHERE app.id = p.appid AND p.name = 'VERSION'");
if( $res === false ) {
  error_log(sprintf("Error querying application table."));
  exit( 1 );
}

$app = array();
foreach( pg_fetch_all($res) as $k => $v ) {
  $app[$v['name']] = $v['val'];
}

# -- Extract families

$res = pg_query($dbh, "SELECT name FROM docfam;");
if( $res === false ) {
  error_log(sprintf("Error querying docfam table."));
  exit( 1 );
}

$fam = array();
foreach( pg_fetch_all($res) as $k => $v ) {
  $name = rtrim($v['name'], "\n");
  $fam[$name]++;
}

# -- Guess modules form applications and families

$modules = array();

foreach( $app as $name => $version ) {
  if( array_key_exists($name, $app_map) ) {
    $module_name = $app_map[$name];
    $module_version = $app[$name];
    error_log(sprintf("Found application '%s' with version '%s'.", $module_name, $module_version));
    $modules[$module_name] = $module_version;
  }
}

foreach( $fam as $name => $v ) {
  if( array_key_exists($name, $fam_map) ) {
    $module_name = $fam_map[$name];
    error_log(sprintf("Found family '%s' from package '%s'.", $name, $module_name));
    $modules[$module_name] = '0.0.0-0';
  }
}

# -- Extracts params

$CONTEXT_NAME = '';
$CONTEXT_DESCRIPTION = '';
$CORE_CLIENT = '';
$WEBDAV_DB = '';

$pathi = pathinfo($CONTEXT_ROOT);
$CONTEXT_NAME = $pathi['filename'];
$CONTEXT_DESCRIPTION = sprintf("Imported context: $CONTEXT_NAME");

$MODULES_LIST = '';
foreach( $modules as $name => $version ) {
  $version = preg_split('/-/', $version, 2);
  $MODULES_LIST .= sprintf('<module name="%s" version="%s" release="%s" status="installed" errorstatus="" />'."\n", $name, $version[0], $version[1]);
}
  
$CORE_CLIENT = '';
$res = pg_query($dbh, "SELECT val FROM paramv WHERE name = 'CORE_CLIENT'");
if( $res === false ) {
  error_log(sprintf("Error extracting CORE_CLIENT."));
  exit( 1 );
}
$v = pg_fetch_all($res);
if( count($v) <= 0 ) {
  error_log(sprintf("CORE_CLIENT not found"));
} else {
  $CORE_CLIENT = $v[0]['val'];
}
$CORE_CLIENT = preg_replace('/&[^;]*;/', '', $CORE_CLIENT);

# -- Generate context

$xml_str = <<<EOD
  <context name="$CONTEXT_NAME" root="$CONTEXT_ROOT">
    <description>$CONTEXT_DESCRIPTION</description>
    <modules>
$MODULES_LIST
    </modules>
    <repositories>
      <access name="freedom-test" description="Freedom Test (2.13)" baseurl="http://ftp.freedom-ecm.org/frdom/webinst/test" /> 
    </repositories>
    <parameters-value>
      <param name="client_name" modulename="freedom-core" value="$CORE_CLIENT" />
      <param name="core_db" modulename="freedom-core" value="$CORE_DB" />
      <param name="authtype" modulename="freedom-core" value="html" />
      <param name="apacheuser" modulename="freedom-core" value="www-data" />
      <param name="webdav_db" modulename="freedom-dav" value="$WEBDAV_DB" />
    </parameters-value>
  </context>

EOD;

if( $CONTEXTS_XML != '' ) {
  if( ! is_file($CONTEXTS_XML) ) {
    error_log(sprintf("'%s' is not a file!", $CONTEXTS_XML));
    exit( 1 );
  }
  $temp = tempnam(dirname($CONTEXTS_XML), "context_migr.xml");
  if( $temp === false ) {
    error_log("Error creating temporary xml file!");
    exit( 1 );
  }

  $stat = stat($CONTEXTS_XML);
  if( $stat === false ) {
    error_log(sprintf("Error stat on '%s'.", $CONTEXTS_XML));
    exit( 1 );
  }

  if( getmyuid() === 0 ) {
    chown($temp, $stat['uid']);
    chgrp($temp, $stat['gid']);
  }

  $xml = new DOMDocument();
  $xml->preserveWhiteSpace = false;
  $xml->formatOutput = true;
  $ret = $xml->load($CONTEXTS_XML);
  if( $ret === false ) {
    error_log(sprintf("Error opening contexts XML file from '%s'.", $CONTEXTS_XML));
    exit( 1 );
  }

  $my_xml = new DOMDocument();
  $my_xml->preserveWhiteSpace = false;
  $my_xml->formatOutput = true;
  $my_xml->createEntityReference('nbsp');
  $ret = $my_xml->loadXML($xml_str);
  if( $ret === false ) {
    error_log(sprintf("Error loading generated XML!"));
    exit( 1 );
  }

  $my_xpath = new DOMXpath($my_xml);
  $myContextNodeList = $my_xpath->query(sprintf("/context"));
  $myContextNode = $myContextNodeList->item(0);

  $xpath = new DOMXpath($xml);
  $contextsNodeList = $xpath->query(sprintf("/contexts"));
  $contextsNode = $contextsNodeList->item(0);

  $nodeList = $xpath->query(sprintf("/contexts/context[@name='%s']", $CONTEXT_NAME));
  if( $nodeList->length <= 0 ) {
    // No existing context with this name
    $newNode = $xml->importNode($myContextNode, true);
    $contextsNode->appendChild($newNode);
  } else {
    if( $FORCE ) {
      $newNode = $xml->importNode($myContextNode, true);
      $node = $nodeList->item(0);
      $contextsNode->replaceChild($newNode, $node);
    } else {
      error_log(sprintf("Context with name '%s' already exists!", $CONTEXT_NAME));
      exit( 1 );
    }
  }

  $ret = $xml->save($temp);
  if( $ret === false ) {
    error_log(sprintf("Error saving to temporary xml file '%s'!", $temp));
    exit( 1 );
  }

  $ret = rename($temp, $CONTEXTS_XML);
  if( $ret === false ) {
    error_log(sprintf("Error renaming temporary xml file '%s' to '%s'!", $temp, $CONTEXTS_XML));
    exit( 1 );
  }
} else {
  echo $xml_str;
}

exit( 0 );

?>