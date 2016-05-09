<?php
namespace Ice\Frame\Runner;
class Service {
    protected $rootPath;

    // input data
    public $serverEnv;
    public $clientEnv;
    public $request;
    public $input;

    // static info
    public $mainAppConf;

    // output data
    public $response;

    public function __construct($rootPath) {
        $this->rootPath = $rootPath;
        $this->mainAppConf = \F_Config::getConfig($this->rootPath . '/conf/app.php');
    }

    public function run() {
        $this->initIce();

        $this->setupEnv();
        $this->setupRequest();
        $this->setupResponse();

        $this->setupIce($this);

        $this->dispatch();
    }

    protected function setupEnv() {
        $this->serverEnv  = new \Ice\Frame\Service\ServerEnv();
        $this->clientEnv  = new \Ice\Frame\Service\ClientEnv();
    }

    protected function setupRequest() {
        $this->input   = file_get_contents('php://input');
        $this->request = \Ice\Frame\Service\ProtocolJsonV1::decodeRequest($this->input);
    }

    protected function setupResponse() {
        $this->response = new \Ice\Frame\Service\Response();
        $this->response->startOb();
        if (is_object($this->request)) {
            $this->response->id = $this->request->id;
        }
    }

    protected function initIce() {
        $this->ice = \F_Ice::init($this, $this->rootPath);
    }

    protected function setupIce() {
        $this->ice->setup();
    }

    protected function dispatch() {
        if (!is_object($this->request)) {
            \F_Ice::$ins->mainApp->logger_common->warn(array(
                'input'  => $this->input,
                'msg'    => 'parse request failed',
            ), $this->request);
            return $this->response->error($this->request);
        }

        try {
            $ucfirstClass  = ucfirst(strtolower($this->request->class));
            $className = "\\{$this->mainAppConf['namespace']}\\Service\\{$ucfirstClass}";

            if (!class_exists($className) || !method_exists($className, $this->request->method)) {
                \F_Ice::$ins->mainApp->logger_common->warn(array(
                    'class' => $this->request->class,
                    'method' => $this->request->method,
                    'msg' => 'dispatch error: no class or method',
                ), \F_ECode::UNKNOWN_URI);
                return $this->response->error(\F_ECode::UNKNOWN_URI, array(
                    'class' => $this->request->class,
                    'method' => $this->request->method,
                    'msg' => 'dispatch error: no class or method',
                ));
            }

            $serviceObj = new $className();

            $result = call_user_func_array(array($serviceObj, $this->request->method), $this->request->params);
            $code = isset($result['code']) ? $result['code'] : \F_ECode::WS_ERROR_RESPONSE;
            $data = isset($result['data']) ? $result['data'] : null;

            $this->response->output($code, $data);
        } catch (\Exception $e) {
            \F_Ice::$ins->mainApp->logger_common->fatal(array(
                'exception' => get_class($e),
                'message'   => $e->getMessage(),
                'code'      => $e->getCode(),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
            ), \F_ECode::PHP_ERROR);
            $this->response->error(\F_ECode::PHP_ERROR);
        }
    }
}