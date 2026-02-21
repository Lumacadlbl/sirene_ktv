<?php

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;

$host = "localhost";
$user = "root";
$pass = "";        //y
$db   = "siren_ktv";


$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}


$capsule = new Capsule;
$capsule->addConnection([
    'driver'    => 'mysql',
    'host'      => $host,
    'database'  => $db,
    'username'  => $user,
    'password'  => $pass,
    'charset'   => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix'    => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();


date_default_timezone_set('Asia/Manila'); 

?>