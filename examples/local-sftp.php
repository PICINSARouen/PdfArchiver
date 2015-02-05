<?php
// Don't forget to require the SFTP adapter: composer require league/flysystem-sftp
// Documentation for this adapter: http://flysystem.thephpleague.com/adapter/sftp/

require 'vendor/autoload.php';

use Antoineaugusti\PdfArchiver\Console\MoverCommand;
use League\Flysystem\Adapter\Local as LocalAdapter;
use League\Flysystem\Sftp\SftpAdapter;
use Symfony\Component\Console\Application;

$local = new LocalAdapter('/tmp/test');
$sftp = new SftpAdapter([
	'host'       => 'example.com',
	'port'       => 22,
	'username'   => 'username',
	'privateKey' => 'path/to/or/contents/of/privatekey',
	'root'       => '/path/to/root',
	'timeout'    => 10,
]);

$moverCommand = new MoverCommand;
$moverCommand->setLocalAdapter($local);
$moverCommand->setRemoteAdapter($sftp);

$application = new Application();
$application->add($moverCommand);
$application->run();