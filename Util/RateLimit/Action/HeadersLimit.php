<?php

namespace Util\RateLimit\Action;

use Util\RateLimit\ActionInterface;

class HeadersLimit implements ActionInterface
{
    public function success($user, $remaining, $limit)
    {
        header('X-RateLimit-Limit: ' . $limit);
        header('X-RateLimit-Remaining: ' . $remaining);
    }

    /**
     * @param string $user
     * @param int $used
     * @param int $limit
     * @psalm-return never-return
     */
    public function fail($user, $used, $limit)
    {
        header('HTTP/1.1 429 Too Many requests');
        header('X-RateLimit-Limit: ' . $limit);
        header('X-RateLimit-Remaining: 0');
        exit;
    }
}
