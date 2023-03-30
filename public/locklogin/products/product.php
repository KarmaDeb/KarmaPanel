<?php 
require_once '/var/www/panel/work/Configuration.php';

use KarmaDev\Panel\Configuration;
use KarmaDev\Panel\SQL\LockLogin;

$config = new Configuration();

include $config->getWorkingDirectory() . 'vendor/autoload.php';

use KarmaDev\Panel\Utilities as Utils;
use KarmaDev\Panel\SQL\ClientData;

$locklogin = new LockLogin();

if (isset($_GET['download'])) {
    $moduleData = $locklogin->getModule(intval(substr($_GET['download'], 0, 1)), true);
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="'. $moduleData['internal_name'] .'.jar"');

    ClientData::performAction('downloading <a href="'. $config->getHomePath() .'locklogin/products/#'. $moduleData['internal_name'] .'">LockLogin ' . $moduleData['name'] . '</a> version <a href='. $config->getHomePath() .'locklogin/products/product.php/?download='. $_GET['download'] .'">' . $moduleData['version'] . '</a>.');

    echo base64_decode($moduleData['download']);
} else {
    http_response_code(401);
}
