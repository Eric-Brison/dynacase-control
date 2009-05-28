<?php
include_once ('../test.php');

$wiff = WIFF::getInstance();
$contextList = $wiff->getContextList();

if ($contextList === false)
{
    echo "FAILED:\n";
    echo $wiff->errorMessage."\n";
    exit (1);
}

displayContextList($contextList);

exit (0);

?>
