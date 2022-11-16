<?php

namespace Util\RateLimit;

use DateInterval;
use Exception;

class LimitChecker
{
    /** @var int */
    private $requests;

    /** @var int */
    private $period;

    /** @var CounterStorageInterface */
    private $counterStorage;

    /** @var ActionInterface */
    private $action;

    /**
     * @param int $requests
     * @param int $period in seconds
     */
    public function __construct(
        $requests,
        $period,
        CounterStorageInterface $storage,
        ActionInterface $action
    ) {
        $this->requests = $requests;
        $this->period = $period;
        $this->counterStorage = $storage;
        $this->action = $action;
    }

    /**
     * @param string $user
     * @param string $apiKey
     * @return void
     * @throws CounterStorageException
     */
    public function check($user, $apiKey)
    {
        $keyName = md5($user . ':' . $apiKey);
        try {
            $this->counterStorage->add($keyName, 0, $this->period);
            $used = $this->counterStorage->increment($keyName, $this->period);
        } catch (Exception $ex) {
            throw new CounterStorageException('Counter storage unavailable/misbehaving', 0, $ex);
        }
        if ($used > $this->requests) {
            $this->action->fail($user, $used, $this->requests);
        } else {
            $this->action->success($user, $this->requests - $used, $this->requests);
        }
    }
}
