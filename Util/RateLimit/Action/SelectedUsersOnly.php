<?php

namespace Util\RateLimit\Action;

use Util\RateLimit\ActionInterface;

final class SelectedUsersOnly implements ActionInterface
{
    /**
     * Logins are keys, values are ignored
     * @var array<lowercase-string, mixed>
     */
    private $users = [];

    /** @var ActionInterface */
    private $action;

    /**
     * @param list<string> $users list of user logins to apply wrapped action to
     * @param ActionInterface $action
     */
    public function __construct($users, $action)
    {
        $this->users = array_fill_keys(array_map('strtolower', $users), true);
        $this->action = $action;
    }

    public function success($user, $remaining, $limit)
    {
        if (array_key_exists(strtolower($user), $this->users)) {
            $this->action->success($user, $remaining, $limit);
        }
    }

    public function fail($user, $used, $limit)
    {
        if (array_key_exists(strtolower($user), $this->users)) {
            $this->action->fail($user, $used, $limit);
        }
    }
}
