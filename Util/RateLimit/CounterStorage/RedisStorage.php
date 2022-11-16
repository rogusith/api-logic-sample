<?php

namespace Util\RateLimit\CounterStorage;

use Util\RateLimit\CounterStorageInterface;
use Redis;
use RedisException;

class RedisStorage implements CounterStorageInterface
{
    /** @var Redis */
    private $redis;

    /** @var string */
    private $host;

    /** @var int */
    private $port;

    /**
     * @param string $host
     * @param int $port
     */
    public function __construct($host, $port)
    {
        $this->redis = new Redis();
        $this->host = $host;
        $this->port = $port;
    }

    /**
     * {@inheritDoc}
     */
    public function add($keyName, $initialValue, $ttl)
    {
        /**
         * Without implementation, because this function handled in @see increment method.
         */
    }

    /**
     * {@inheritDoc}
     * @link https://redis.io/commands/INCR#pattern-rate-limiter-2
     */
    public function increment($keyName, $ttl)
    {
        $this->connect();
        $value = (int)$this->redis->incr($keyName);
        if ($value === 1) {
            $this->redis->expire($keyName, $ttl);
        }

        return $value;
    }

    /** @return void */
    private function connect()
    {
        try {
            $this->redis->ping();
        } catch (RedisException $ex) {
            $this->redis->connect($this->host, $this->port);
            $this->redis->ping();
        }
    }
}
