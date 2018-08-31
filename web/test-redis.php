<?php

require __DIR__ . '/../vendor/autoload.php'; # including composer autoload file
try {
    $redis = new Predis\Client(array(
        'scheme' => 'tcp',
        'host' => '{endpoint}',
//        'host' => '127.0.0.1',
        'port' => 6379
    ));

    echo "Server is running: ".$redis->ping();
    echo "<br>";

# working with simple string values
    $date = "Test - ".date('c');
    $key = md5(__FILE__);

    echo $date . "<br/>";
    if (!$redis->exists($key)) {
        $redis->set($key, $date);
    }

    echo "<br/>cache<br/>";
    echo $redis->get($key);
} catch (Exception $ex) {
    echo $ex->getMessage();
    echo "<pre>";
    print_r($ex->getTraceAsString());
}
