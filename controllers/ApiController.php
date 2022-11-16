<?php

namespace Controllers;

use API_Exception;
use Exception;
use Models\ApiHelpers;
use Util\RateLimit\Action\DbLogAction;
use Util\RateLimit\Action\HeadersLimit;
use Util\RateLimit\Action\SelectedUsersOnly;
use Util\RateLimit\Action\Sequential;
use Util\RateLimit\CounterStorage\RedisStorage;
use Util\RateLimit\LimitChecker;
use Core\Dal\LoginAttemptsDal;
use Core\HTTP\Response\XmlResponse;
use Core\ServiceContainer\Container;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response\JsonResponse;

abstract class ApiController
{
    /** @return ResponseInterface|null */
    abstract public function preDispatch(ServerRequestInterface $request);

    /**
     * @param ServerRequestInterface $request
     * @return void
     * @throws API_Exception
     */
    protected function checkCredentials(ServerRequestInterface $request)
    {
        /**
         * $_REQUEST - an associative array that by default contains the contents of $_GET, $_POST and $_COOKIE.
         *
         * @link https://www.php.net/manual/en/reserved.variables.request.php
         */
        $requestData = array_merge(
            $request->getQueryParams(),
            is_array($parsedBody = $request->getParsedBody()) ? $parsedBody : [],
            $request->getCookieParams()
        );

        if (empty($requestData['login'])) {
            throw new API_Exception('login_empty', 'Please specify login.');
        }
        if (empty($requestData['api_key'])) {
            throw new API_Exception('api_key_empty', 'Please specify api_key.');
        }

        $log = Container::instance()->getLogger()->withName('returns-api');

        $loginAttempts = new LoginAttemptsDal();
        if ($loginAttempts->checkFailedLoginAttempts(get_ip(), time())) {
            throw new API_Exception('ip_blocked', 'Too many incorrect log-in attempts.');
        }

        $login = trim($requestData['login']);
        $api_key = trim($requestData['api_key']);

        $sql = "SELECT * FROM `users`"
            . " WHERE login = '" . real_escape_string($login) . "'"
            . " AND api_key = '" . real_escape_string($api_key) . "'"
            . " AND user_type = 2" //main client account
            . " LIMIT 1";
        $userdata = fetch_one_assoc($sql);
        if ($userdata) {
            $limits = json_decode($userdata['api_rate_limits'], true);
            if (!$limits) {
                $log->warning('Missing api rate limits', ['client' => $userdata]);
                $limits = ['limit' => 2, 'period' => 1];
            }

            $checker = new LimitChecker(
                $limits['limit'],
                $limits['period'],
                new RedisStorage(REDIS_HOST, REDIS_PORT),
                new Sequential([
                    // log usage
                    new DbLogAction((int)$userdata['id']),
                    // and throttle just disney, for now
                    new SelectedUsersOnly(
                        ['disney', 'lordflackotestaccount'],
                        new HeadersLimit()
                    )
                ])
            );

            try {
                $checker->check($login, $api_key);
            } catch (Exception $ex) {
                $log->warning('API rate limit checker failed', ['error' => $ex]);
            }

            $loginAttempts->deleteFailedLoginAttempts(get_ip());
            $_SERVER['userdata'] = $userdata;
            $_SERVER['userdata']['frontend_options'] = json_decode($_SERVER['userdata']['frontend_options'], true);
            $_SERVER['userdata']['shop_id'] = $userdata['id']; //для совместимости
            return;
        }
        $loginAttempts->addFailedLoginAttempt(get_ip(), time());
        throw new API_Exception('access_denied', 'Access denied');
    }

    /**
     * @param array $data
     * @param string $format
     * @return XmlResponse|JsonResponse
     */
    protected function toApiResponse(array $data, $format = 'json')
    {
        if ($format === 'xml') {
            $xml = arrToXmlWHeader($data);
            return new XmlResponse($xml);
        }

        return new JsonResponse($data);
    }

    /**
     * @return array
     * @throws API_Exception
     */
    protected function extractRequestPayload(ServerRequestInterface $request)
    {
        $get = $request->getQueryParams();
        $post = $request->getParsedBody();
        injection_detection($get, 'api');
        if (is_array($post)) {
            injection_detection($post, 'api');
        }
        injection_detection($request->getCookieParams(), 'api');

        $format = ApiHelpers::getFormat($request);

        if (!isset($post['request'])) {
            throw new API_Exception('request_empty', 'Empty request field.');
        }

        $requestData = $post['request'];
        if ($requestData === '') {
            return [];
        }

        if ($format === 'xml') {
            try {
                return $this->extractXml($requestData);
            } catch (API_Exception $e) {
                Container::instance()->getLogger()->error(
                    'Malformed XML passed',
                    [
                        'parsed_request' => $requestData,
                        'original_request' => $post['request'],
                    ]
                );

                throw $e;
            }
        }

        // If no "format" provided, consider it's also JSON.
        return $this->extractJson($requestData);
    }

    /**
     * @param string $requestData
     * @return array
     * @throws API_Exception
     */
    private function extractXml($requestData)
    {
        $parsedData = xml2arr($requestData);
        if (!is_array($parsedData)) {
            throw new API_Exception('xml_invalid', 'XML is not valid');
        }

        return $parsedData;
    }

    /**
     * @param string $requestData
     * @return array
     * @throws API_Exception
     */
    private function extractJson($requestData)
    {
        $decodedData = json_decode($requestData, true);
        if (!is_array($decodedData)) {
            throw new API_Exception('json_invalid', 'JSON is not valid');
        }
        clean_array_from_tags($decodedData, ['non_returnable_message']);

        return $decodedData;
    }
}
