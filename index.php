<?php

use Ir\Api\Models\ApiHelpers;
use Ir\Api\Models\ApiServerRequest;
use Ir\App\Backend\Controllers\ApiDocsController;
use Ir\App\Core\HTTP\Response\XmlResponse;
use Ir\App\Core\ServiceContainer\Container;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response\JsonResponse;
use Zend\Diactoros\ServerRequestFactory;

// include __DIR__ . '/../maintenance.php';

libxml_disable_entity_loader();
$_SERVER['api'] = 1; //api flag
$_SERVER['requestId'] = bin2hex(openssl_random_pseudo_bytes(16));

require_once __DIR__ . '/../app/config/main_config.php';

$time_start = microtime(true);

_checkProductionAndProtocol();

if (is_dev()) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL ^ E_NOTICE);
}

_defineApiConstants();
_includeFiles();

$uri_string = strtok($_SERVER['REQUEST_URI'], '?');
$uri_path = explode('/', $uri_string);

$request = ServerRequestFactory::fromGlobals();
_runUncommon($uri_string, $request);

_setupServiceGetParams($uri_path);

// note that we should call getServerRequest() after all the request
// adjustments (such as $_GET mangling) are done
$request = ApiServerRequest::getServerRequest();

_throttleGymshark($uri_string, $request);

/**
 * --------------------------------------
 *          Handling api calls
 * --------------------------------------
 */

if (isset($_GET['call'], $_GET['action'])) {
    $call = preg_replace('/[^a-zA-Z0-9]/u', '', (string)$_GET['call']);
    $action = preg_replace('/[^a-zA-Z0-9_]/u', '', (string)$_GET['action']);

    require_once __DIR__ . '/models/api_model.php';

    // Run new version of controllers
    runController($call, $action, $request);
}

unknown_api_call();

/**
 * @param string $controller
 * @param string $action
 * @param ServerRequestInterface $request
 * @return void
 */
function runController($controller, $action, ServerRequestInterface $request)
{
    $controller = camelize($controller) . 'Controller';
    $controllerClass = "Ir\\Api\\Controllers\\${controller}";
    $action = 'action' . camelize($action);

    if (class_exists($controllerClass) && method_exists($controllerClass, $action)) {
        /** @todo Get controller from DI. */
        $controllerInstance = new $controllerClass();
        try {
            if (method_exists($controllerInstance, 'preDispatch')) {
                $response = $controllerInstance->preDispatch($request);
                if ($response instanceof ResponseInterface) {
                    RequestLog::set_response((string)$response->getBody());
                    Container::instance()->getApiResponseEmitter()->emit($response);
                    exit;
                }
            }

            $response = $controllerInstance->$action($request);
            if ($response instanceof ResponseInterface) {
                RequestLog::set_response((string)$response->getBody());
                Container::instance()->getApiResponseEmitter()->emit($response);
                exit;
            }
        } catch (API_Exception $e) {
            $response = generateResponseFromException($e);
            RequestLog::api_error_response((string)$response->getBody());

            $logger = Container::instance()->getLogger()->withName('returns-api');
            $logger->error(
                'API error',
                [
                    'request' => $_REQUEST,
                    'response' => $response->getBody(),
                ]
            );

            Container::instance()->getApiResponseEmitter()->emit($response);
            exit;
        }
    }
}

/**
 * @param API_Exception $e
 * @return ResponseInterface
 */
function generateResponseFromException(API_Exception $e)
{
    $responseData = [
        'error' => [
            'code' => $e->get_code(),
            'message' => $e->get_message(),
        ],
    ];

    $request = ApiServerRequest::getServerRequest();
    if (ApiHelpers::getFormat($request) === 'xml') {
        $xml = arrToXmlWHeader($responseData);
        return new XmlResponse($xml);
    }

    return new JsonResponse($responseData);
}

/**
 * @param string $input
 * @param array|string $separator
 * @param bool $capitalizeFirstCharacter
 * @return string
 */
function camelize($input, $separator = '_', $capitalizeFirstCharacter = true)
{
    $str = str_replace(' ', '', ucwords(str_replace($separator, ' ', $input)));

    if (!$capitalizeFirstCharacter) {
        $str = lcfirst($str);
    }

    return $str;
}

/**
 * Allows only two requests per a time for Gymshark client.
 *
 * @param string $uri_string
 * @return void
 */
function _throttleGymshark($uri_string, ServerRequestInterface $request)
{
    if (
        strtoupper($_REQUEST['login']) === 'GYMSHARK'
        && stripos($uri_string, 'tracking/get_by_date_range') !== false
    ) {
        $res = create_dirs(__DIR__ . "/lockers");
        $file_lock_path = __DIR__ . "/lockers/GYMSHARK.lock";
        $file_lock_path_second = __DIR__ . "/lockers/GYMSHARK_second.lock";
        if (file_locked($file_lock_path) && file_locked($file_lock_path_second)) {
            $response = [
                'error' => [
                    'code' => 'many_requests',
                    'message' => ['Too Many Requests'],
                ],
            ];

            $_SERVER['userdata'] = ['id' => 24];
            header('HTTP/1.1 429 Too Many Requests');
            if (ApiHelpers::getFormat($request) === 'xml') {
                $response = arrToXmlWHeader($response);
            } else {
                $response = json_encode($response);
            }

            RequestLog::api_error_response($response);
            die($response);
        }
    }
}

/**
 * Includes configuration and other legacy part of code.
 *
 * @return void
 */
function _includeFiles()
{
    RequestLog::time_point_start('include_db_con');
    require_once CONFIG_PATH . '/db_con.php';
    RequestLog::time_point_end('include_db_con');
    RequestLog::time_point_start('include_carriers_config');
    require_once CONFIG_PATH . '/carriers_configs.php';
    RequestLog::time_point_end('include_carriers_config');
    RequestLog::time_point_start('include_db');
    require_once CORE_PATH . '/db.php';
    RequestLog::time_point_end('include_db');
    RequestLog::time_point_start('include_countries');
    require_once CONFIG_PATH . '/countries.php';
    RequestLog::time_point_end('include_countries');
    RequestLog::time_point_start('include_country_state');
    require_once CONFIG_PATH . '/country_state.php';
    RequestLog::time_point_end('include_country_state');
    RequestLog::time_point_start('include_helper');
    require_once CORE_PATH . '/helper.php';
    RequestLog::time_point_end('include_helper');
    require_once CORE_PATH . '/cache_file.php';
    RequestLog::time_point_start('include_xml_helper');
    require_once CORE_PATH . '/xml_helper.php';
    RequestLog::time_point_end('include_xml_helper');
    RequestLog::time_point_start('include_ftp_helper');
    require_once CORE_PATH . '/ftp_helper.php';
    RequestLog::time_point_end('include_ftp_helper');
    RequestLog::time_point_start('include_xsd_model');
    require_once API_MODEL_PATH . '/xsd_model.php';
    RequestLog::time_point_end('include_xsd_model');
    RequestLog::time_point_start('include_response_model');
    require_once API_MODEL_PATH . '/response_model.php';
    RequestLog::time_point_end('include_response_model');
    RequestLog::time_point_start('include_api_exception');
    require_once API_PATH . '/api_exception.php';
    RequestLog::time_point_end('include_api_exception');
    RequestLog::time_point_start('include_libmail');
    require_once CORE_PATH . '/libmail.php';
    RequestLog::time_point_end('include_libmail');
    RequestLog::time_point_start('include_files_helper');
    require_once CORE_PATH . '/files_helper.php';
    RequestLog::time_point_end('include_files_helper');
    RequestLog::time_point_start('include_log');
    require_once CORE_PATH . '/log.php';
    RequestLog::time_point_end('include_log');
    RequestLog::time_point_start('include_barcode_helper');
    require_once CORE_PATH . '/barcode_helper.php';
    RequestLog::time_point_end('include_barcode_helper');
    RequestLog::time_point_start('backend_model_includes');
    backend_model_includes();
    RequestLog::time_point_end('backend_model_includes');
}

/** @return void */
function _defineApiConstants()
{
    RequestLog::time_point_start('define_api_constants');
    define('API_PATH', __DIR__);
    define('API_MODEL_PATH', API_PATH . '/models');
    define('API_CONTROLLER_PATH', API_PATH . '/controllers');
    define('API_VIEW_PATH', API_PATH . '/views');
    RequestLog::time_point_end('define_api_constants');
}

/**
 * Check that for production uses HTTPS. If not - connect will be redirected to the HTTPS.
 *
 * @return void
 */
function _checkProductionAndProtocol()
{
    RequestLog::time_point_start('check_production_and_protocol');

    $_SERVER['ACTIVE_PROTOCOL'] = 'http';
    if (is_production()) {
        $_SERVER['ACTIVE_PROTOCOL'] = 'https';
        if (!is_https()) {
            $location = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            header("Location: $location", 1, '301');
            exit;
        }
    }

    RequestLog::time_point_end('check_production_and_protocol');
}

/**
 * Run uncommon API actions like api/console, api/webhooks etc.
 *
 * @param string $uriString
 * @param ServerRequestInterface $request
 * @return void
 */
function _runUncommon($uriString, ServerRequestInterface $request)
{
    if ($uriString === '/api/console') {
        /** @todo replace creation with DI */
        $response = (new ApiDocsController())->actionConsole($request);
        Container::instance()->getApiResponseEmitter()->emit($response);
        die;
    }

    if ($uriString === '/api/webhooks') {
        /** @todo replace creation with DI */
        $response = (new ApiDocsController())->actionWebhooks($request);
        Container::instance()->getApiResponseEmitter()->emit($response);
        die;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        /** @todo replace creation with DI */
        $response = (new ApiDocsController())->actionIndex($request);
        Container::instance()->getApiResponseEmitter()->emit($response);
        die;
    }
}

/**
 * For correct work of the other part of index.php
 * code action & controller params should be presented in the $_GET array.
 *
 * @param string[] $uriPath
 * @return void
 */
function _setupServiceGetParams($uriPath)
{
    if (!isset($_GET['call']) && !isset($_GET['action'])) {
        //remove GET request from $_SERVER['REQUEST_URI'] and save it to $uri_string
        // TODO avoid using GLOBAL variables (it's hard to find it's initialization)
        $_SERVER['uri_path'] = $uriPath; // Saving it global for further usage
        if ($uriPath[0] === '' && $uriPath[1] === 'api' && !empty($uriPath[2]) && !empty($uriPath[3])) {
            $_GET['call'] = $uriPath[2];
            $_GET['action'] = $uriPath[3];

            if (!empty($uriPath[4])) {
                $_GET['format'] = $uriPath[4];
            }
        }
    }
}
