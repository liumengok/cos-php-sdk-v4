cos-php-sdk：php sdk for [腾讯云对象存储服务](https://www.qcloud.com/product/cos.html)
===================================================================================================

### 安装（直接下载源码集成）
直接从[github](https://github.com/tencentyun/cos-php-sdk-v4)下载源码，然后在您的程序中加载cos-php-sdk-v4/autoload.php就可以了。

### 示例程序
请参考sample.php

```php
// 包含cos-php-sdk-v4/autoload.php文件
require('cos-php-sdk-v4/autoload.php');
use qcloudcos\CosClient;

// 创建CosClient对象：
//     华南  -> gz
//     华中  -> sh
//     华北  -> tj
$cosClient = new CosClient('sh', $appId, $secretId, $secretKey);

// 创建文件夹
$ret = $cosClient->createDirectory($bucket, $folder);
var_dump($ret);

// 上传文件
$ret = $cosClient->uploadObject($bucket, $src, $dst);
var_dump($ret);

// 目录列表
$ret = $cosClient->listDirectory($bucket, $directory);
var_dump($ret);

// 更新目录信息
$bizAttr = "";
$ret = $cosClient->updateDirectory($bucket, $directory, $bizAttr);
var_dump($ret);

// 更新文件信息
$bizAttr = '';
$authority = 'eWPrivateRPublic';
$customerHeaders = array(
    'Cache-Control' => 'no',
    'Content-Type' => 'application/pdf',
    'Content-Language' => 'ch',
);
$ret = $cosClient->updateObject($bucket, $dst, $bizAttr, $authority, $customerHeaders);
var_dump($ret);

// 查询目录信息
$ret = $cosClient->statDirectory($bucket, $folder);
var_dump($ret);

// 查询文件信息
$ret = $cosClient->statObject($bucket, $object);
var_dump($ret);

// 删除文件
$ret = $cosClient->deleteObject($bucket, $object);
var_dump($ret);

// 删除目录
$ret = $cosClient->removeDirectory($bucket, $directory);
var_dump($ret);

// 复制文件
$ret = $cosClient->copyObject($bucket, $srcObject, $dstObject);
var_dump($ret);

// 移动文件
$ret = $cosClient->moveObject($bucket, $srcObject, $dstObject);
var_dump($ret);
```
