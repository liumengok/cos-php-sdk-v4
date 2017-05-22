<?php

namespace qcloudcos;

require_once(__DIR__ . DIRECTORY_SEPARATOR . 'error_code.php');

date_default_timezone_set('PRC');

class CosClient {
    // Time for signature.
    const EXPIRED_SECONDS = 180;

    // Default slice size for multipart uploading.
    const SLICE_SIZE = 1048576;

    // Object whose size equal or large than UPLOAD_OBJECT_THRESHOLD will be multipart uploaded.
    const OBJECT_SIZE_THRESHOLD = 20971520;

    // Max retry times on failure.
    const MAX_RETRY_TIMES = 3;

    private $timeoue;      // integer: default timout in seconds for http each request.
    private $verbose;      // boolean: output verbose log to stderr.
    private $region;       // string: region.
    private $appId;        // string: app id.
    private $secretId;     // string: secret id.
    private $secretKey;    // string: secret key.

    public function __construct($region, $appId, $secretId, $secretKey) {
        $this->timeout   = 60;
        $this->verbose   = false;
        $this->region    = $region;
        $this->appId     = $appId;
        $this->secretId  = $secretId;
        $this->secretKey = $secretKey;
    }

    /**
     * Set timeout in seconds for each http request.
     * @param $timeout timeout in seconds.
     * @return void.
     */
    public function setTimeout($timeout) {
        $this->timeout = $timeout;
    }

    /**
     * Enable debugger will output verbose log to stderr.
     * @return void.
     */
    public function enableDebugger() {
        $this->verbose = true;
    }

    /**
     * Disable debugger.
     * @return void.
     */
    public function disableDebugger() {
        $this->verbose = false;
    }

    /**
     * Upload an object.
     * @param $bucket bucket name.
     * @param $srcFpath source local file path.
     * @param $dstFpath destination file path.
     * @param $bizAttr business attribute.
     * @param $overwrite if the destination location is occupied, overwrite it or not?
     * @return array|mixed.
     */
    public function uploadObject($bucket, $srcFpath, $dstFpath, $bizAttr='', $overwrite=false) {
        if (!file_exists($srcFpath)) {
            return array(
                'code' => COSAPI_PARAMS_ERROR,
                'message' => 'src file ' . $srcFpath .' not exists',
            );
        }

        $dstFpath = $this->normalizerPath($dstFpath, false);

        if (filesize($srcFpath) < self::OBJECT_SIZE_THRESHOLD) {
            return $this->uploadObjectByWhole($bucket, $srcFpath, $dstFpath, $bizAttr, $overwrite);
        } else {
            return $this->uploadBySlicing($bucket, $srcFpath, $dstFpath, $bizAttr, $overwrite);
        }
    }

    /**
     * Stat an object.
     * @param $bucket bucket name.
     * @param $object object name.
     * @return array|mixed.
     */
    public function statObject($bucket, $object) {
        $object = $this->normalizerPath($object);

        return $this->statBase($bucket, $object);
    }

    /**
     * Delete an object.
     * @param $bucket bucket name.
     * @param $object object to delete.
     * @return array|mixed.
     */
    public function deleteObject($bucket, $object) {
        if (empty($bucket) || empty($object)) {
            return array(
                'code' => COSAPI_PARAMS_ERROR,
                'message' => 'object is empty'
            );
        }

        $object = $this->normalizerPath($object);

        return $this->deleteBase($bucket, $object);
    }

    /**
     * Copy an object.
     * @param $bucket bucket name.
     * @param $srcObject source object.
     * @param $dstObject destination object.
     * @param $overwrite if the destination location is occupied, overwrite it or not?
     * @return array|mixed.
     */
    public function copyObject($bucket, $srcObject, $dstObject, $overwrite=false) {
        $srcObject = $this->normalizerPath($srcObject);
        $dstObject = $this->normalizerPath($dstObject);
        $url = $this->generateResUrl($bucket, $srcObject);
        $signature = Auth::createNonreusableSignature(
                $this->appId, $this->secretId, $this->secretKey, $bucket, $srcObject);
        $data = array(
            'op' => 'copy',
            'dest_fileid' => $dstObject,
            'to_over_write' => $overwrite ? 1 : 0,
        );
        $req = array(
            'url' => $url,
            'method' => 'post',
            'timeout' => $this->timeout,
            'data' => $data,
            'header' => array(
                'Authorization: ' . $signature,
            ),
        );

        return $this->sendRequest($req);
    }

    /**
     * Move an object.
     * @param $bucket bucket name.
     * @param $srcObject source object.
     * @param $dstObject destination object.
     * @param $overwrite if the destination location is occupied, overwrite it or not?
     * @return array|mixed.
     */
    public function moveObject($bucket, $srcObject, $dstObject, $overwrite=false) {
        $srcObject = $this->normalizerPath($srcObject);
        $dstObject = $this->normalizerPath($dstObject);
        $url = $this->generateResUrl($bucket, $srcObject);
        var_dump($url);
        $signature = Auth::createNonreusableSignature(
                $this->appId, $this->secretId, $this->secretKey, $bucket, $srcObject);
        $data = array(
            'op' => 'move',
            'dest_fileid' => $dstObject,
            'to_over_write' => $overwrite ? 1 : 0,
        );
        $req = array(
            'url' => $url,
            'method' => 'post',
            'timeout' => $this->timeout,
            'data' => $data,
            'header' => array(
                'Authorization: ' . $signature,
            ),
        );

        return $this->sendRequest($req);
    }

    /**
     * Update an object.
     * @param $bucket bucket name.
     * @param $object object to update.
     * @param $authority:
     *     eInvalid(继承Bucket的读写权限)
     *     eWRPrivate(private read, private write)
     *     eWPrivateRPublic(public read, private write)
	 * @param $arrCustomerHeaders customer headers:
     *     'Cache-Control' => '*'
     *     'Content-Type' => '*'
     *     'Content-Disposition' => '*'
     *     'Content-Language' => '*'
     *     'x-cos-meta-自定义内容' => '*'
     */
    public function updateObject($bucket, $object, $bizAttr=null, $authority=null, $arrCustomerHeaders=array()) {
        $object = $this->normalizerPath($object);
        return $this->updateObject($bucket, $object, $bizAttr, $authority, $arrCustomerHeaders);
    }

    /**
     * Create directory.
     */
    public function createDirectory($bucket, $directory, $bizAttr='') {
        if (!$this->isValidPath($directory)) {
            return array(
                'code' => COSAPI_PARAMS_ERROR,
                'message' => 'directory ' . $directory . ' is not a valid directory name',
            );
        }

        $directory = $this->normalizerPath($directory, True);
        $directory = $this->cosUrlEncode($directory);
        $expired = time() + self::EXPIRED_SECONDS;
        $url = $this->generateResUrl($bucket, $directory);
        $signature = Auth::createReusableSignature(
                $this->appId, $this->secretId, $this->secretKey, $expired, $bucket);

        $data = array(
            'op' => 'create',
            'biz_attr' => $bizAttr,
        );

        $data = json_encode($data);

        $req = array(
            'url' => $url,
            'method' => 'post',
            'timeout' => $this->timeout,
            'data' => $data,
            'header' => array(
                'Authorization: ' . $signature,
                'Content-Type: application/json',
            ),
        );

        return $this->sendRequest($req);
    }

    public function statDirectory($bucket, $directory) {
        $directory = $this->normalizerPath($directory, true);

        return $this->statBase($bucket, $directory);
    }

    public function removeDirectory($bucket, $directory) {
        if (empty($bucket) || empty($directory)) {
            return array(
                'code' => COSAPI_PARAMS_ERROR,
                'message' => 'bucket or path is empty'
            );
        }

        $directory = $this->normalizerPath($directory, true);
        return $this->deleteBase($bucket, $directory);
    }

    /*
     * Update directory.
     */
    public function updateDirectory($bucket, $directory, $bizAttr='') {
        $directory = $this->normalizerPath($directory, true);

        return $this->updateBase($bucket, $directory, $bizAttr);
    }

    /**
     * List directory.
     */
    public function listDirectory(
                    $bucket, $directory, $num = 20,
                    $pattern = 'eListBoth', $order = 0,
                    $context = null) {
        $directory = $this->normalizerPath($directory, True);

        return $this->listBase($bucket, $directory, $num, $pattern, $order, $context);
    }

    /**
     * 目录列表(前缀搜索)
     * @param  string  $bucket bucket名称
     * @param  string  $prefix   列出含此前缀的所有文件
     * @param  int     $num      拉取的总数
     * @param  string  $pattern  eListBoth(默认),ListDirOnly,eListFileOnly
     * @param  int     $order    默认正序(=0), 填1为反序,
     * @param  string  $offset   透传字段,用于翻页,前端不需理解,需要往前/往后翻页则透传回来
     */
    public function prefixSearch(
                    $bucket, $prefix, $num=20,
                    $pattern='eListBoth', $order=0, $context=null) {
        $prefix = $this->normalizerPath($prefix);
        return $this->listBase($bucket, $prefix, $num, $pattern, $order, $context);
    }

    /******************************/
    /* Internal private functions */
    /******************************/
    private function uploadObjectByWhole($bucket, $srcFpath, $dstFpath, $bizAttr = '', $overwrite = false) {
	    $dstFpath = $this->cosUrlEncode($dstFpath);

        $expired = time() + self::EXPIRED_SECONDS;
        $url = $this->generateResUrl($bucket, $dstFpath);
        $signature = Auth::createReusableSignature(
                $this->appId, $this->secretId, $this->secretKey, $expired, $bucket);
        $fileSha = hash_file('sha1', $srcFpath);

        $data = array(
            'op' => 'upload',
            'sha' => $fileSha,
            'biz_attr' => $bizAttr,
        );

        $data['filecontent'] = file_get_contents($srcFpath);
        $data['insertOnly'] = $overwrite ? 0 : 1;

        $req = array(
            'url' => $url,
            'method' => 'post',
            'timeout' => $this->timeout,
            'data' => $data,
            'header' => array(
                'Authorization: ' . $signature,
            ),
        );

        return $this->sendRequest($req);
    }

    private function uploadBySlicing(
            $bucket, $srcFpath,  $dstFpath, $bizAttr='', $overwrite=false) {
        $srcFpath = realpath($srcFpath);
        $fileSize = filesize($srcFpath);
        $dstFpath = $this->cosUrlEncode($dstFpath);
        $url = $this->generateResUrl($bucket, $dstFpath);
        $sliceCount = ceil($fileSize / self::SLICE_SIZE);
        // expiration seconds for one slice mutiply by slice count
        // will be the expired seconds for whole file
        $expiration = time() + (self::EXPIRED_SECONDS * $sliceCount);
        if ($expiration >= (time() + 10 * 24 * 60 * 60)) {
            $expiration = time() + 10 * 24 * 60 * 60;
        }
        $signature = Auth::createReusableSignature(
                $this->appId, $this->secretId, $this->secretKey, $expiration, $bucket);

        $sliceUploading = new SliceUploading($this->timeout * 1000, self::MAX_RETRY_TIMES, $this->verbose);
        for ($tryCount = 0; $tryCount < self::MAX_RETRY_TIMES; ++$tryCount) {
            if ($sliceUploading->initUploading(
                        $signature,
                        $srcFpath,
                        $url,
                        $fileSize, self::SLICE_SIZE, $bizAttr, $overwrite)) {
                break;
            }

            $errorCode = $sliceUploading->getLastErrorCode();
            if ($errorCode === -4019) {
                // Delete broken file and retry again on _ERROR_FILE_NOT_FINISH_UPLOAD error.
                $this->deleteObject($bucket, $dstFpath);
                continue;
            }

            if ($tryCount === self::MAX_RETRY_TIMES - 1) {
                return array(
                            'code' => $sliceUploading->getLastErrorCode(),
                            'message' => $sliceUploading->getLastErrorMessage(),
                            'request_id' => $sliceUploading->getRequestId(),
                        );
            }
        }

        if (!$sliceUploading->performUploading()) {
            return array(
                        'code' => $sliceUploading->getLastErrorCode(),
                        'message' => $sliceUploading->getLastErrorMessage(),
                        'request_id' => $sliceUploading->getRequestId(),
                    );
        }

        if (!$sliceUploading->finishUploading()) {
            return array(
                        'code' => $sliceUploading->getLastErrorCode(),
                        'message' => $sliceUploading->getLastErrorMessage(),
                        'request_id' => $sliceUploading->getRequestId(),
                    );
        }

        return array(
                    'code' => 0,
                    'message' => 'success',
                    'request_id' => $sliceUploading->getRequestId(),
                    'data' => array(
                        'access_url' => $sliceUploading->getAccessUrl(),
                        'resource_path' => $sliceUploading->getResourcePath(),
                        'source_url' => $sliceUploading->getSourceUrl(),
                    ),
                );
    }

    private function listBase(
            $bucket, $path, $num = 20, $pattern = 'eListBoth', $order = 0, $context = null) {
        $path = $this->cosUrlEncode($path);
        $expired = time() + self::EXPIRED_SECONDS;
        $url = $this->generateResUrl($bucket, $path);
        $signature = Auth::createReusableSignature(
                $this->appId, $this->secretId, $this->secretKey, $expired, $bucket);

        $data = array(
            'op' => 'list',
        );

        if ($this->isPatternValid($pattern) == false) {
            return array(
                'code' => COSAPI_PARAMS_ERROR,
                'message' => 'parameter pattern invalid',
            );
        }
        $data['pattern'] = $pattern;

        if ($order != 0 && $order != 1) {
            return array(
                'code' => COSAPI_PARAMS_ERROR,
                'message' => 'parameter order invalid',
            );
        }
		$data['order'] = $order;

		if ($num < 0 || $num > 199) {
            return array(
                'code' => COSAPI_PARAMS_ERROR,
                'message' => 'parameter num invalid, num need less then 200',
            );
		}
        $data['num'] = $num;

        if (isset($context)) {
            $data['context'] = $context;
        }

        $url = $url . '?' . http_build_query($data);

        $req = array(
                    'url' => $url,
                    'method' => 'get',
                    'timeout' => $this->timeout,
                    'header' => array(
                        'Authorization: ' . $signature,
                    ),
                );

        return $this->sendRequest($req);
    }

    /*
     * $authority:  eInvalid/eWRPrivate(私有)/eWPrivateRPublic(公有读写)
	 * $arrCustomerHeaders customer headers
     *     'Cache-Control' => '*'
     *     'Content-Type' => '*'
     *     'Content-Disposition' => '*'
     *     'Content-Language' => '*'
     *     'x-cos-meta-自定义内容' => '*'
     */
    private function updateBase(
            $bucket, $path, $bizAttr=null, $authority=null, $arrCustomerHeaders=array()) {
        $path = $this->cosUrlEncode($path);
        $expired = time() + self::EXPIRED_SECONDS;
        $url = $this->generateResUrl($bucket, $path);
        $signature = Auth::createNonreusableSignature(
                $this->appId, $this->secretId, $this->secretKey, $bucket, $path);

        $data = array('op' => 'update');

	    if (isset($bizAttr)) {
	        $data['biz_attr'] = $bizAttr;
	    }

	    if (isset($authority) && strlen($authority) > 0) {
			if($this->isAuthorityValid($authority) == false) {
                return array(
                        'code' => COSAPI_PARAMS_ERROR,
                        'message' => 'parameter authority invalid');
			}

	        $data['authority'] = $authority;
	    }

	    if (isset($arrCustomerHeaders)) {
	        $data['custom_headers'] = array();
	        $this->addCustomerHeader($data['custom_headers'], $arrCustomerHeaders);
	    }

        $data = json_encode($data);

        $req = array(
            'url' => $url,
            'method' => 'post',
            'timeout' => $this->timeout,
            'data' => $data,
            'header' => array(
                'Authorization: ' . $signature,
                'Content-Type: application/json',
            ),
        );

		return $this->sendRequest($req);
    }

    private function statBase($bucket, $path) {
        $path = $this->cosUrlEncode($path);
        $expired = time() + self::EXPIRED_SECONDS;
        $url = $this->generateResUrl($bucket, $path);
        $signature = Auth::createReusableSignature(
                $this->appId, $this->secretId, $this->secretKey, $expired, $bucket);

        $data = array('op' => 'stat');

        $url = $url . '?' . http_build_query($data);

        $req = array(
            'url' => $url,
            'method' => 'get',
            'timeout' => $this->timeout,
            'header' => array(
                'Authorization: ' . $signature,
            ),
        );

        return $this->sendRequest($req);
    }

    private function deleteBase($bucket, $path) {
        if ($path == "/") {
            return array(
                    'code' => COSAPI_PARAMS_ERROR,
                    'message' => 'can not delete bucket using api! go to ' .
                                 'http://console.qcloud.com/cos to operate bucket');
        }

        $path = $this->cosUrlEncode($path);
        $expired = time() + self::EXPIRED_SECONDS;
        $url = $this->generateResUrl($bucket, $path);
        $signature = Auth::createNonreusableSignature(
                $this->appId, $this->secretId, $this->secretKey, $bucket, $path);

        $data = array('op' => 'delete');

        $data = json_encode($data);

        $req = array(
            'url' => $url,
            'method' => 'post',
            'timeout' => $this->timeout,
            'data' => $data,
            'header' => array(
                'Authorization: ' . $signature,
                'Content-Type: application/json',
            ),
        );

        return $this->sendRequest($req);
    }

	private function cosUrlEncode($path) {
        return str_replace('%2F', '/',  rawurlencode($path));
    }

    private function generateResUrl($bucket, $dstPath) {
        $endPoint = Conf::API_COSAPI_END_POINT;
        $endPoint = str_replace('region', $this->region, $endPoint);

        return $endPoint . $this->appId . '/' . $bucket . $dstPath;
    }

    private function sendRequest($req) {
        $rsp = HttpClient::sendRequest($req, $this->verbose);
        if ($rsp === false) {
            return array(
                'code' => COSAPI_NETWORK_ERROR,
                'message' => 'network error',
            );
        }

        $info = HttpClient::info();
        $ret = json_decode($rsp, true);

        if ($ret === NULL) {
            return array(
                'code' => COSAPI_NETWORK_ERROR,
                'message' => $rsp,
                'data' => array()
            );
        }

        return $ret;
    }

	private function getSliceSize($sliceSize) {
        // Fix slice size to 1MB.
        return self::SLICE_SIZE_1M;
	}

	private function normalizerPath($path, $isDirectory=false) {
		if (preg_match('/^\//', $path) == 0) {
            $path = '/' . $path;
        }

        if ($isDirectory) {
            if (preg_match('/\/$/', $path) == 0) {
                $path = $path . '/';
            }
        }

        // Remove unnecessary slashes.
        $path = preg_replace('#/+#', '/', $path);

		return $path;
	}

    private function isAuthorityValid($authority) {
        if ($authority == 'eInvalid' || $authority == 'eWRPrivate' || $authority == 'eWPrivateRPublic') {
            return true;
	    }
	    return false;
    }

    private function isPatternValid($pattern) {
        if ($pattern == 'eListBoth' || $pattern == 'eListDirOnly' || $pattern == 'eListFileOnly') {
            return true;
	    }
	    return false;
    }

    private function isCustomerHeader($key) {
        if ($key == 'Cache-Control' || $key == 'Content-Type' ||
                $key == 'Content-Disposition' || $key == 'Content-Language' ||
                $key == 'Content-Encoding' ||
                substr($key,0,strlen('x-cos-meta-')) == 'x-cos-meta-') {
            return true;
	    }
	    return false;
    }

    private function addCustomerHeader(&$data, $arrCustomerHeaders) {
        foreach($arrCustomerHeaders as $key => $value) {
            if($this->isCustomerHeader($key)) {
                $data[$key] = $value;
            }
        }
    }

    private function isValidPath($path) {
        if (strpos($path, '?') !== false) {
            return false;
        }
        if (strpos($path, '*') !== false) {
            return false;
        }
        if (strpos($path, ':') !== false) {
            return false;
        }
        if (strpos($path, '|') !== false) {
            return false;
        }
        if (strpos($path, '\\') !== false) {
            return false;
        }
        if (strpos($path, '<') !== false) {
            return false;
        }
        if (strpos($path, '>') !== false) {
            return false;
        }
        if (strpos($path, '"') !== false) {
            return false;
        }

        return true;
    }
}
