<?php

namespace Util\RateLimit\Action;

use Util\RateLimit\ActionInterface;

final class Sequential implements ActionInterface
{
    /** @var list<ActionInterface> */
    private $actions = [];

    /** @param list<ActionInterface> $actions */
    public function __construct($actions)
    {
        $this->actions = $actions;
    }

    public function success($user, $remaining, $limit)
    {
        foreach ($this->actions as $action) {
            $action->success($user, $remaining, $limit);
        }
    }

    public function fail($user, $used, $limit)
    {
        foreach ($this->actions as $action) {
            $action->fail($user, $used, $limit);
        }
    }
}
