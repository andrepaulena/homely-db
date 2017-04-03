<?php

require '../../vendor/autoload.php';

$config = [
    'dbname' => 'basicSite',
    'user' => 'root',
    'password' => 'neon',
    'host' => 'localhost',
    'driver' => 'pdo_mysql'
];

\HomelyDb\HomelyDb::init($config);

$db = \HomelyDb\HomelyDb::getConnection();

$sm = $db->getSchemaManager();

$tables = $sm->listTables();

foreach ($tables as $table) {
    var_dump($table);
}

var_dump($tables);
exit;
