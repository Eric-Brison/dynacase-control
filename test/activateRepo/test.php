<?php
include_once ('../test.php');

$wiff = new WIFF();
$contextList = $wiff->getContextList();

if ($contextList === false)
{
    echo "FAILED:\n";
    echo $wiff->errorMessage."\n";
    exit (1);
}

displayContextList($contextList);

foreach ($contextList as $context)
{
	$context->activateRepo('stable_2.12');
}

displayContextList($contextList);

$moduleList = $context->repo[2]->getModuleList();

echo "List available modules : \n";

foreach ($moduleList as $module)
{
	echo $module->name."\n" ;
	echo $module->isInstalled."\n" ;
}

//foreach ($contextList as $context)
//{
//	$context->deactivateRepo('stable_2.12');
//}
//
//displayContextList($contextList);

exit (0);

?>