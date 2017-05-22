<?php

require('./autoload.php');

use qcloudcos\CosClient;

$appId = '';
$secretId = '';
$secretKey = '';

// 设置COS所在的区域，对应关系如下：
//     华南  -> gz
//     华中  -> sh
//     华北  -> tj
$cosClient = new CosClient('sh', $appId, $secretId, $secretKey);

// Debugger can be enabled for debugging information.
//$cosClient->enableDebugger();

// Upload local file ./hello.txt to object hello.txt.
$ret = $cosClient->uploadObject('testbucket', './hello.txt', 'hello.txt');
var_dump($ret);

// Create directory.
$ret = $cosClient->createDirectory('testbucket', 'testdir');
var_dump($ret);

$ret = $cosClient->uploadObject('testbucket', './hello.txt', 'testdir/hello.txt');
var_dump($ret);

$ret = $cosClient->listDirectory('testbucket', 'testdir');
var_dump($ret);

$ret = $cosClient->deleteObject('testbucket', 'testdir/hello.txt');
var_dump($ret);

$ret = $cosClient->removeDirectory('testbucket', 'testdir');
var_dump($ret);
