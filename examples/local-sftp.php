<?php

// Don't forget to require the SFTP adapter: composer require league/flysystem-sftp
// Documentation for this adapter: http://flysystem.thephpleague.com/adapter/sftp/

require 'vendor/autoload.php';

use Antoineaugusti\PdfArchiver\Mover;
use League\Flysystem\Adapter\Local as LocalAdapter;
use League\Flysystem\Sftp\SftpAdapter;

$local = new LocalAdapter('/tmp/test');
$sftp = new SftpAdapter([
	'host'       => 'example.com',
	'port'       => 22,
	'username'   => 'username',
	'privateKey' => 'path/to/or/contents/of/privatekey',
	'root'       => '/path/to/root',
	'timeout'    => 10,
]);

// The path to start from
$path = ($argc == 2) ? $argv[1] : '.';

// Run the script
$mover = new Mover($local, $sftp);
$mover->run($path);