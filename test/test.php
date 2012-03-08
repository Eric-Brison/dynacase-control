<?php

set_include_path(getcwd().'/../'.PATH_SEPARATOR.getcwd().'/../../include'.PATH_SEPARATOR.get_include_path());

function __autoload($class_name) {
    require_once 'class/Class.' . $class_name . '.php';
}

// Quick display functions
function displayContextList($contextList)
{

    echo "<pre>";

    foreach ($contextList as $context)
    {
        echo "Name : $context->name \n";
        echo "Root : $context->root \n";
        echo "Description : $context->description \n";
        echo "Repositories : \n";

        foreach ($context->repo as $repository)
        {
            echo "--- Name : $repository->name \n";
            echo "--- Base Url : $repository->baseurl \n";
            echo "--- Description : $repository->description \n";
        }
        echo "--------------- \n";
    }

    echo "</pre>";

}

function setupWorkEnv() {
	system("umask 0002 && rm -Rf work/ && cp -R env/ work/");
}

setupWorkEnv();

error_log(sprintf("Changing directory to work dir."));
if( chdir("work") === false ) {
  error_log(sprintf("Erro changing directory to work dir."));
  exit( 1 );
}

error_log(sprintf("Executing test..."));
include_once("test.php");

?>
