<?php

namespace Util\RateLimit;

use Exception;

interface CounterStorageInterface
{
    /**
     * @param string $keyName
     * @param int $initialValue
     * @param int $ttl
     * @return void
     * @throws Exception
     */
    public function add($keyName, $initialValue, $ttl);

    /**
     * @param string $keyName
     * @param int $ttl
     * @return int incremented value
     */
    public function increment($keyName, $ttl);
}
