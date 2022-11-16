<?php

namespace Controllers;

use Models\ApiHelpers;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Core\ServiceContainer\Container;
use API_Exception;

class CheckController extends ApiController
{
    /**
     * @return ResponseInterface|null
     */
    public function preDispatch(ServerRequestInterface $request)
    {
        return null;
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws API_Exception
     */
    public function actionAvailable(ServerRequestInterface $request)
    {
        $logger = Container::instance()->getLogger()->withName('returns-api');

        $date = get_db_timestamp();
        if (empty($date)) {
            $logger->error('Check Availability error', [
                'request' => $request
            ]);
            throw new API_Exception('internal', 'Internal server error.');
        }

        return $this->toApiResponse(['success' => ['date' => $date]], ApiHelpers::getFormat($request));
    }
}
