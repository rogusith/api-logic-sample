<?php

use Models\ApiHelpers;
use Models\ApiServerRequest;
use Ir\App\Core\ServiceContainer\Container;

function get_api_request()
{
    injection_detection($_GET, 'api');
    injection_detection($_POST, 'api');
    injection_detection($_COOKIE, 'api');

    $format = ApiHelpers::getFormat(
        ApiServerRequest::getServerRequest()
    );

    if (!isset($_POST['request'])) {
        throw new API_Exception('request_empty', 'Empty request field.');
    }

    $request = $_POST['request'];
    if ($request === '') {
        return array();
    }
    if ($format == 'json') {
        $request = json_decode($request, true);
        if (!$request && !is_array($request)) {
            throw new API_Exception('json_invalid', 'JSON is not valid');
        }
        clean_array_from_tags($request);
    } elseif ($format == 'xml') {
        //XSD validation is temporary off
        if ($_SERVER['userdata']['id'] == ASOS_ID && 0) {
            $path = $_GET['call'] . '_' . $_GET['action'];
            if (file_exists(API_PATH . '/xsd/' . $path . '/request.xsd')) {
                $xml = new DOMDocument();
                $xml->loadXML($request);
                $xsd = file_get_contents(API_PATH . '/xsd/' . $path . '/request.xsd');
                if (!$xml->schemaValidateSource($xsd)) {
                    $errors = xsd_get_error();
                    send_debug_email(
                        'XSD validation failed in request.',
                        'Xml: ' . $request . "\n"
                        . 'Xsd: ' . $xsd . "\n"
                        . 'Errors: ' . $errors
                    );
                    throw new API_Exception('XSD validation failed', $errors);
                }
            }
        }

        $request = xml2arr($request);
        if (!is_array($request)) {
            Container::instance()->getLogger()->error(
                'Malformed XML passed',
                [
                    'parsed_request' => $request,
                    'original_request' => $_POST['request']
                ]
            );

            throw new API_Exception('xml_invalid', 'XML is not valid');
        }
    }

    return $request;
}

function get_api_response($response)
{
    $output_format = ApiHelpers::getFormat(
        ApiServerRequest::getServerRequest()
    );
    if ($output_format == 'xml') {
        header('Content-Type: application/xml');
        $xml_response = arrToXmlWHeader($response, true);
        RequestLog::set_response($xml_response);
        if (isset($response['error'])) {
            return $xml_response;
        }

        return $xml_response;
    }
    header('Content-Type: application/json');
    $response = json_encode($response);
    RequestLog::set_response($response);
    return $response;
}

/**
 * @deprecated Use \Models\ApiHelpers::getFormat()
 */
function get_format()
{
    $format = 'json';
    if (isset($_GET['format'])) {
        if ($_GET['format'] == 'json' || $_GET['format'] == 'xml') {
            $format = $_GET['format'];
        }
    }
    return $format;
}

function unknown_api_call()
{
    $response = array(
        'error' => array(
            'code' => 'unknown_call',
            'message' => 'Unknown api call action'
        )
    );
    $logger = Container::instance()->getLogger()->withName('returns-api');
    $logger->error('Unknown API call', [
        'request_uri' => $_SERVER['REQUEST_URI'],
        'request' => $_REQUEST,
    ]);
    die(get_api_response($response));
}
