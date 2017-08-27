<?php

 var_dump(__DIR__);
//'../../vendor/autoload.php';

exit;

$config = include  '../../config.php';

$cli = new \HomelyDb\HomelyCli($config);
$cli->setNamespace($config['namespace']);
$cli->setModelDir($config['modelsDir']);

$cli->run();
