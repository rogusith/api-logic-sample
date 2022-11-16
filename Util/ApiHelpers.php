<?php

namespace Util;

use Psr\Http\Message\ServerRequestInterface;

class ApiHelpers
{
    /**
     * @param ServerRequestInterface $request
     *
     * @return string
     */
    public static function getFormat(ServerRequestInterface $request)
    {
        $params = $request->getQueryParams();
        $format = 'json';

        if (isset($params['format']) && in_array($params['format'], ['json', 'xml'])) {
            $format = $params['format'];
        }

        return $format;
    }
}
