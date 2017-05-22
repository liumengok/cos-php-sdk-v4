<?php

// >= php 5.3.0
spl_autoload_register(function($class){
    $class = strtolower(preg_replace(
            ['/([A-Z]+)/', '/_([A-Z]+)([A-Z][a-z])/'], ['_$1', '_$1_$2'], ($class)));
    $class = str_replace('\\_', '\\', $class);

    $dir = dirname(__FILE__);
    $class = str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';
    include($dir . DIRECTORY_SEPARATOR . $class);
});
