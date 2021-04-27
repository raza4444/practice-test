<?php

/**
 * Wrapper for InnerTrend's Public API - Compass
 *
 * @package        Atlas v0.1
 * @author        Alex Stoia
 * @link        http://api.innertrends.com/
 */
class CompassApi
{

    /**
     * Api version
     *
     * @var string
     */
    private $version = '1.0';

    private $logSavedData = [];

    /**
     * Communication endpoint url
     * @var string
     */
    private $endpoint = "compass.innertrends.com";

    /**
     * Data collection endpoin
     * @var string
     */
    private $collect_endpoint = "babel.innertrends.com/store";

    /**
     * Stores erros at wrapper level
     *
     * @var array
     */
    private $errors = array();

    /**
     * Output format
     *  * json
     *  * xml
     *
     * @var string
     */
    private $output = 'json';

    /**
     * Communicate through secure protocol, or not
     *
     * @var Boolean
     */
    private static $secure = false;

    /**
     * If this is on the curl request is not executed
     * @var Boolean
     */
    private $dry_run = false;

    /**
     * Your developer private api key
     * !used for read operations
     * @var string
     */
    private $private_key = '';

    /**
     * Your developer public api key
     * !used for write operations
     * @var string
     */
    private $public_key = '21312390129312213';

    /**
     * If this is on, request data payload gets printed
     *
     * @var Boolean
     */
    private $debug = false;

    /**
     * Holds the instantiated self
     */
    private static $instance = null;

    /**
     * Holds log counts in session
     * @var Int
     */
    private $logIndex = 0;

    /**
     * Initiate
     * @param string $pubk
     * @param string $privk
     */
    public function __construct($pubk = "", $privk = "", $version = "")
    {
        /**
         * Check for global configurations
         */
        if (defined("IT_COMPASS_PUBLIC_KEY")) {
            $this->public_key = IT_COMPASS_PUBLIC_KEY;
        }

        if (defined("IT_COMPASS_PRIVATE_KEY")) {
            $this->private_key = IT_COMPASS_PRIVATE_KEY;
        }

        if (defined("IT_COMPASS_VERSION")) {
            $this->version = IT_COMPASS_VERSION;
        }

        /**
         * set/overwrite keys
         */
        if ($pubk != "") {
            $this->public_key = $pubk;
        }

        if ($privk != "") {
            $this->private_key = $privk;
        }

        if ($version != "") {
            $this->version = $version;
        }

    }

    /**
     * Activate debug mode;
     * @param boolean $d
     * @return $this
     */
    public function setDebug($d = true)
    {
        $this->debug = $d;
        return $this;
    }

    /**
     * Setter for the public key
     * @param string $key
     */
    public function setPublicKey($key = "")
    {
        $this->public_key = $key;

        return $this;
    }

    /**
     * Setter for the private key
     * @param string $key
     */
    public function setPrivateKey($key = "")
    {
        $this->private_key = $key;

        return $this;
    }

    /**
     * Feeds the configurations to the instance
     *
     * @param array $confs
     */
    public static function configure($confs = array())
    {

        self::_create();

        if (isset($confs['private_key'])) {
            self::$instance->setPrivateKey($confs['private_key']);
        }

        if (isset($confs['public_key'])) {
            self::$instance->setPublicKey($confs['public_key']);
        }

        if (isset($confs['version'])) {
            self::$instance->setVersion($confs['version']);
        }

        if (isset($confs['debug'])) {
            self::$instance->setDebug($confs['debug']);
        }

        if (isset($confs['dry_run'])) {
            self::$instance->setDryRun($confs['dry_run']);
        }

    }

    /**
     * Setter for the api version
     * @param string $v
     * @return Compass
     */
    public function setVersion($v = "")
    {
        $this->version = $v;
        return $this;
    }

    /**
     * Setter for dry run flag
     * @param string $v
     * @return Compass
     */
    public function setDryRun($v = "")
    {
        $this->dry_run = $v;
        return $this;
    }

    /**
     * Setter for  the output format
     * @param string $format
     */
    public function setOutputFormat($format = "json")
    {
        $this->output = $format;

        return $this;
    }

    /**
     * Formatting the data to be send ready
     *
     * @param array $builder
     * @return array
     */
    private function buildRequest($builder = array())
    {

        if ($builder['__api_op'] == "log") {

            if (!isset($this->public_key) or $this->public_key == "") {
                $this->registerError("a public key must be provided");
            }

            $terminal = (($this->secure) ? 'https' : 'http') . '://' . $this->collect_endpoint;
            $payload = array("event" => "", "labels" => [], "version" => $this->version, "type" => "action", "identity" => "", "context" => new stdClass());
            unset($builder['__api_op']);

            if (!empty($builder)) {
                if (isset($builder['_identity'])) {
                    if (!is_array($builder['_identity'])) {
                        $payload['identity'] = $builder['_identity'];
                    } else {
                        if (!isset($builder['_ids'])) {
                            $builder['_ids'] = [];
                        }

                        $builder['_ids'] = array_merge($builder['_ids'], $builder['_identity']);
                    }
                    unset($builder['_identity']);
                }
                if (isset($builder['_labels'])) {
                    $payload['labels'] = $builder['_labels'];
                    unset($builder['_labels']);
                }
                if (isset($builder['_type'])) {
                    $payload['type'] = in_array($builder['_type'], array("action", "error", "email", "identify", "support", "revenue")) ? $builder['_type'] : "";
                    unset($builder['_type']);
                }
                if (isset($builder['_event'])) {
                    $payload['event'] = $builder['_event'];
                    unset($builder['_event']);
                }
            }
            empty($builder) ? $builder = [] : "";
            $payload['context'] = $builder;

            //to real properties key
            if (isset($payload['context']['_properties'])) {
                $payload['context']['__itl_properties'] = $payload['context']['_properties'];
                unset($payload['context']['_properties']);
            }

            if ($this->debug) {
                $request['view_payload'] = $payload;
            }

            //prepare payload data for http transfer
            $payload = urlencode(json_encode($payload));
            $uniqueIdentifier = $this->logIndex++ . str_replace('.', '', microtime(true));
            $terminal .= "?_itkey=$this->public_key&_itp=$payload&_unq=$uniqueIdentifier";
            $request['url'] = $terminal;
            $request['type'] = "get";
            $request['op'] = "log";
        } else {

            if (!isset($this->public_key) or !isset($this->private_key)) {
                $this->registerError("a private key and a public key must be provided");
            }

            $terminal = (($this->secure) ? 'https' : 'http') . '://' . $this->endpoint . '/atlas/' . $this->version . '';

            if ($builder['__api_op'] == "get" or $builder['__api_op'] == "list") {
                $request['type'] = "get";
            } else {
                $request['type'] = "post";
            }

            $query = $builder;

            unset($query['__api_op'], $query['access']);

            if (isset($query['filters']) and is_array($query['filters'])) {
                $query['filters'] = "filters=" . urlencode(json_encode($query['filters']));
            }

            if (isset($query['citj'])) {
                $query['citj'] = "citj=" . urlencode(trim($query['citj']));
            }

            if ($builder['access'] == "stream" or $builder['access'] == "reports") {
                if (isset($builder['lid']) and $builder['lid'] > 0) {
                    $path = "logbooks/" . $builder['lid'];
                }

                if (isset($builder['rid']) and $builder['rid'] > 0) {
                    $path .= "/reports/" . $builder['rid'];
                }

                if (!isset($builder['lid']) and !isset($builder['rid'])) {
                    $path = "reports/";
                }

            } else {
                $path = $builder['access'];
            }

            unset($query['rid'], $query['lid']);

            $path = '/' . $path . "/";
            $path = str_replace("//", "/", $path);

            $request['url'] = $terminal . $path;

            if ($request['type'] == "get" and !empty($query)) {
                $request['url'] .= '?' . join('&', $query);
            } else {
                $request['fields'] = $query;
            }

        }
        return $request;
    }

    /**
     * 
     * Send the built request to the api via curl
     * @param array $request
     */
    public function send($request = array())
    {

        // print request configuration, before send
        if ($this->debug) {
            echo "<br>The request: " . print_r($request, true);
        }

        $curl_handle = curl_init();
        curl_setopt($curl_handle, CURLOPT_URL, $request['url']);
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl_handle, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl_handle, CURLOPT_SSL_VERIFYHOST, 2);

        if (!isset($request['op'])) {
            curl_setopt($curl_handle, CURLOPT_USERPWD, $this->public_key . ':' . $this->private_key);
        }

        if ($request['type'] == "post") {
            curl_setopt($curl_handle, CURLOPT_POST, count($request['fields']));
            curl_setopt($curl_handle, CURLOPT_POSTFIELDS, $request['op'] === 'logsbatch' ?   $request['fields'] :  http_build_query($request['fields'], '', '&'));
        } else {
            // curl_setopt($curl_handle, CURLOPT_NOBODY, true);
            // curl_setopt($curl_handle, CURLOPT_TIMEOUT_MS, 100);
        }

        if (!$this->dry_run) {
            $response = curl_exec($curl_handle);
        }

        $info = curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);
 //      print_r(['asd'=>$info]);die();

        //a curl server error?
        if ($info != 200 and $response == "") {
            $errcode = curl_errno($curl_handle);
            $errmsg = curl_error($curl_handle);
            $this->registerError("a problem occured while sending the request: $errcode. $errmsg");
        }

        //print request configuration, before send
        if ($this->debug) {
            echo "<br>Header: http->$info";
            echo "<br>Curl version: " . print_r(curl_version(), true);
            echo "<br>The response: " . print_r($response, true);
            echo "<br>Wrapper errors:: " . print_r($this->errors, true);
        }

        //if output is requested as object, convert from default
        if ($this->output == "object") {
            $response = json_decode($response);
        }

        curl_close($curl_handle);

        return  $response !== ""  || $response !== null ? $response : $info;
    }

    /**
     *
     */
    private static function _create()
    {

        if (!isset(self::$instance)) {
            self::$instance = new CompassApi();
        }

    }

    /**
     * Compose and send the request from the called method
     *  * format: getMethod,createMethod,updateMethod -> concrete: getStream(array)
     *
     * @param string $method
     * @param array $args
     */
    public static function __callStatic($method, $args)
    {

        self::_create();

        // fix forwaded format
        if (!empty($args)) {
            $args['fix'] = 1;
        }

        // Forward action to the instance
        return self::$instance->{$method}($args);
    }

    /**
     *  Checkes whether any errors found on the wrapper level
     *
     * @return Boolean
     */
    public function noError()
    {
        return empty($this->errors) ? true : false;
    }

    /**
     *  Pushes the wrapper error array to the stack
     *
     * @param type $err
     * @return \CompassApi
     */
    public function registerError($err = array())
    {
        $this->errors[] = $err;
        return $this;
    }

    /**
     * Return the error stack
     *
     * @return array
     */
    public function getErrors()
    {
        return array("status" => "error", "msg" => $this->errors);
    }

    /**
     *  Catches invalid method calls  of the instantiated object
     *
     * @param type $method
     * @param type $args
     * @return string
     */
    public function __call($method = "", $args = array())
    {

        //Reset previous request
        $this->errors = array();
        $data = array();
        $analyzed = array();
        $builder = array();
        $request = null;

        // If the request comes from static medium, fix format
        if (isset($args[0]['fix'])) {
            unset($args[0]['fix']);
            $args = $args[0];
        }

        //Analyze called method
        if (preg_match("/^(get|create|update|list)(.*)/", $method, $analyzed)) {

            //Create request parameters
            $builder['__api_op'] = $analyzed[1];
            $builder['access'] = strtolower($analyzed[2]);
            $builder = array_merge($builder, $args[0]);

            // Build request
            if ($this->noError()) {
                $request = $this->buildRequest($builder);
            }

        } else {
            // Send an action or error log to the collector endpoint
            if ($method == "log") {

                if (sizeof($args) == 0) {
                    $this->registerError("no arguments provided");
                }

                //polymorph
                if (sizeof($args) == 1) {
                    $tolog = is_array($args[0]) ? $args[0] : array("_event" => $args[0]);
                } else if (sizeof($args) == 2) {
                    $tolog = is_array($args[1]) ? array_merge(array("_event" => $args[0]), $args[1])
                    : array("_event" => $args[1], "_identity" => $args[0]);
                } else {
                    $tolog = array_merge(array("_event" => $args[1], "_identity" => $args[0]), $args[2]);
                }
                $tolog['__api_op'] = $method;

                if ($this->noError()) {
                    $request = $this->buildRequest($tolog);
                }

            } else {
                $this->registerError("invalid method call: $method");
            }

        }

        //Send final composed request -> if no error occured on composition
        if ($this->noError()) {
            $data = $this->send($request);
        }

        // Show errors
        if (!$this->noError() and $this->debug) {
            $data = $this->getErrors();
            print_r($data);
        }

        return $data;
    }

    /**
     * Store Logs Batcch
     *
     * @param array $args
     * @return void
     */
    public static function logBatch($data)
    {
        $self = new self();
        $request =  $self->normalizeData($data);

        //test request Data 
       //print_r($request);

        $data = $self->send($request);

        return json_encode(array(
            "success"=> true,
        ));

    }

    /**
     * Normalize request Data
     *
     * @param array $logsArrayData
     * @return string
     */
    public function normalizeData($logsArrayData)
    {

        $request = [];
        $this->prepareBatchEvents($logsArrayData, 0, 0, []);

        $payload = [];
        $payload['_itkey'] = $this->public_key;
        $payload['_itpbatch'] = $this->getlogData();
         $payload = json_encode($payload);
        
        $request['url'] = (($this->secure) ? 'https' : 'http') . '://' . $this->collect_endpoint;
        $request['type'] = "post";
        $request['op'] = 'logsbatch';
        $request['fields'] = $payload;
        return $request;

    }

    
    public function setlogData($logSavedData)
    {
        $this->logSavedData[] = $logSavedData;

    }


    public function getlogData()
    {
        return $this->logSavedData;
    }


    /**
     * prepare batch event function
     *
     * @param array $logsArray
     * @param int $mainIndex
     * @param int $key
     * @param array $singleLog
     * @return void
     */
    public function prepareBatchEvents($logsArray = [], $mainIndex, $key, $singleLog = [])
    {
        $mainLastIndex = sizeof($logsArray);
        if (count($logsArray) > 0 && ($mainIndex < $mainLastIndex)) {

            $logs = $logsArray[$mainIndex];

            $lastIndex = sizeof($logs);
            $mainLastIndex = sizeof($logsArray);
            if (($key < $lastIndex)) {

                if (filter_var($logs[$key], FILTER_VALIDATE_EMAIL) && !is_array($logs[$key])) {
                    $singleLog['identity'] = $logs[$key];
                    $nextKey = $key + 1;
                    $this->prepareBatchEvents($logsArray, $mainIndex, $nextKey, $singleLog);

                } else if (is_array($logs[$key]) && array_keys($logs[$key]) !== range(0, count($logs[$key]) - 1)) {

                    foreach ($logs[$key] as $keyName => $innerLog) {

                        if ($keyName === 'name' || $keyName === 'company' || $keyName === 'website') {
                            if (isset($singleLog['contaxt']) && is_array($singleLog['contaxt'])) {
                                $singleLog['contaxt'][$keyName] = $innerLog;
                            } else {
                                $singleLog['contaxt'] = array();
                                $singleLog['contaxt'][$keyName] = $innerLog;
                            }

                        } else {
                            $singleLog[$keyName === '_type' ? 'type' : $keyName] = $innerLog;
                        }

                    }

                    $nextKey = $key + 1;
                    $this->prepareBatchEvents($logsArray, $mainIndex, $nextKey, $singleLog);

                } else {
                    $singleLog['event'] = $logs[$key];
                    $nextKey = $key + 1;
                    $this->prepareBatchEvents($logsArray, $mainIndex, $nextKey, $singleLog);
                }

            } else {
                $singleLog = $singleLog;

                $itpbatch = (object) array(
                    "identity" => $singleLog['identity'] ?? "",
                    "event" => $singleLog['event'] ?? "",
                    "timestamp" => gmdate("Y-m-d\TH:i:s\Z"),
                    "type" => $singleLog['type'] ?? "",
                    "context" => (object) $singleLog['contaxt'] ?? []

                );
                $this->setlogData($itpbatch);

                $mainIndex = $mainIndex + 1;
                $this->prepareBatchEvents($logsArray, $mainIndex, 0, []);
            }

        }
    }

}
