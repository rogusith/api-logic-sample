<?php

use Util\RateLimit\Action\DbLogAction;
use Util\RateLimit\CounterStorage\RedisStorage;
use Util\RateLimit\LimitChecker;
use Ir\App\Core\Dal\LoginAttemptsDal;
use Ir\App\Core\ServiceContainer\Container;

/**
 * @param array $params
 * @return void
 * @throws API_Exception
 */
function check_api_credentials($params)
{
    if (empty($params['login'])) {
        throw new API_Exception('login_empty', 'Please specify login.');
    }
    if (empty($params['api_key'])) {
        throw new API_Exception('api_key_empty', 'Please specify api_key.');
    }

    $log = Container::instance()->getLogger()->withName('returns-api');

    $loginAttempts = new LoginAttemptsDal();
    if ($loginAttempts->checkFailedLoginAttempts(get_ip(), time())) {
        throw new API_Exception('ip_blocked', 'Too many incorrect log-in attempts.');
    }

    $login = trim($params['login']);
    $api_key = trim($params['api_key']);

    $sql = "SELECT * FROM " . USERS
        . " WHERE login = '" . addslashes($login) . "'"
        . " AND api_key = '" . addslashes($api_key) . "'"
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
            // to be replaced later with actual limiting action
            // just log usage for now
            new DbLogAction((int) $userdata['id'])
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
