<?php

require __DIR__ . '/../vendor/autoload.php'; # including composer autoload file

# making redis connection
try {
    $redis = new Predis\Client(array(
        'scheme' => 'tcp',
        'host' => 'todo-webcache.gxopva.0001.apse1.cache.amazonaws.com',
//        'host' => '127.0.0.1',
        'port' => 6379
    ));


# working with simple string values
    $date = date('c');
    $key = md5(__FILE__);

    echo $date . "<br/>";
    if (!$redis->exists($key)) {
        $redis->set($key, $date,null,1000);
    }

    echo "<br/>cache<br/>";
    echo $redis->get($key);
} catch (Exception $ex) {
    echo $ex->getMessage();
    echo "<pre>";
    print_r($ex->getTraceAsString());
}