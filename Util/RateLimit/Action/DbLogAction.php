<?php

namespace Util\RateLimit\Action;

use Util\RateLimit\ActionInterface;

class DbLogAction implements ActionInterface
{
    /** @var int */
    private $userId;

    /** @param int $userId */
    public function __construct($userId)
    {
        $this->userId = $userId;
    }

    /**
     * {@inheritDoc}
     */
    public function success($user, $remaining, $limit)
    {
        $this->logToDb($limit, $remaining, $limit - $remaining);
    }

    /**
     * {@inheritDoc}
     */
    public function fail($user, $used, $limit)
    {
        $this->logToDb($limit, 0, $used);
    }

    /**
     * @param int $limit
     * @param int $remaining
     * @param int $used
     * @return void
     */
    private function logToDb($limit, $remaining, $used)
    {
        run_query("
            insert into api_request_rate_log
            set
                date_added=unix_timestamp(),
                user_id=" . (int) $this->userId . ",
                remaining_requests=" . (int) $remaining . ",
                allowed_requests=" . (int) $limit . ",
                used_requests=" . (int) $used . "
        ");
    }
}
