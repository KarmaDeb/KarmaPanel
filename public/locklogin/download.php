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
    $updateData = $locklogin->getUpdate($_GET['download']);
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="LockLogin.jar"');

    ClientData::performAction('downloading <a href="'. $config->getHomePath() .'locklogin/download.php/?download='. $_GET['download'] .'">LockLogin ' . $updateData['version'] . '</a>');

    echo base64_decode($updateData['download']);
} else {
    http_response_code(401);
}
