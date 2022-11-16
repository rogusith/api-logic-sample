<?php

namespace Models;

use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\ServerRequestFactory;

class ApiServerRequest
{
    /**
     * @var null|ServerRequestInterface
     */
    private static $request = null;

    /**
     * Initializes and caches ServerRequestInterface implementation
     *
     * CAUTION: should be called *after* all request adjustments are done
     * @return ServerRequestInterface
     */
    public static function getServerRequest()
    {
        if (self::$request === null) {
            self::$request = ServerRequestFactory::fromGlobals();
        }
        return self::$request;
    }
}
