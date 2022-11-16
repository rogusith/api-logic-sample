<?php

namespace Util\RateLimit;

interface ActionInterface
{
    /**
     * @param string $user
     * @param int $remaining
     * @param int $limit
     * @return void
     */
    public function success($user, $remaining, $limit);

    /**
     * @param string $user
     * @param int $used
     * @param int $limit
     * @return void
     */
    public function fail($user, $used, $limit);
}
