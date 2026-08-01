<?php
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\DrupalKernel;

$autoloader = require dirname(getcwd()) . "/vendor/autoload.php";
$request = Request::createFromGlobals();
$kernel = DrupalKernel::createFromRequest($request, $autoloader, "prod");
$kernel->boot();

require_once getcwd() . "/modules/custom/sir/sir.install";
if (!function_exists("sir_update_8003")) { throw new RuntimeException("sir_update_8003 not found"); }
sir_update_8003();
print "sir_update_8003 executed\\n";
