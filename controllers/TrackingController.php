<?php

namespace Api\Controllers;

use API_Exception;
use Api\Models\ApiHelpers;
use App\Core\HTTP\Response\XmlResponse;
use App\Core\ServiceContainer\Container;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response\JsonResponse;

final class TrackingController extends ApiController
{
    /**
     * @inheritDoc
     * @throws API_Exception
     */
    public function preDispatch(ServerRequestInterface $request)
    {
        $this->checkCredentials($request);
        return null;
    }

    /**
     * @param ServerRequestInterface $request
     * @return XmlResponse|JsonResponse
     * @throws API_Exception
     */
    public function actionGet(ServerRequestInterface $request)
    {
        $requestPayload = $this->extractRequestPayload($request);
        $logger = Container::instance()->getLogger()->withName('returns-api');
        if (empty($requestPayload['tracking'])) {
            $logger->error('Get tracking. Request field is wrong', [
                'request' => $requestPayload,
            ]);
            throw new API_Exception('request_wrong', 'Request field is wrong.');
        }

        if (empty($requestPayload['tracking']['code'])) {
            $logger->error('Get tracking. Missing required field \'code\'', [
                'request' => $requestPayload,
            ]);
            throw new API_Exception('code_empty', "Missing required field 'code'.");
        }

        $result = get_tracking_api($requestPayload['tracking']['code']);
        if (empty($result)) {
            $logger->error('Get tracking. Nothing found', [
                'request' => $requestPayload,
            ]);
            throw new API_Exception('empty', 'Nothing found.');
        }

        return $this->toApiResponse(['success' => $result], ApiHelpers::getFormat($request));
    }

    /**
     * @param ServerRequestInterface $requestObj
     * @return XmlResponse|JsonResponse
     * @throws API_Exception
     */
    public function actionGetByDateRange(ServerRequestInterface $requestObj)
    {
        $logger = Container::instance()->getLogger()->withName('returns-api');
        $request = $this->extractRequestPayload($requestObj);
        mysql_read_from_slave(true);
        if (empty($request['filter'])) {
            $logger->error('Tracking events by date range. Missing required field \'Filter\'', [
                'request' => $request,
            ]);
            throw new API_Exception('request_wrong', "'Filter' field is missing");
        }

        if (!empty($request['filter']['page'])) {
            $request['filter']['page'] = (int)$request['filter']['page'];
            if ($request['filter']['page'] < 1) {
                $logger->error('Tracking events by date range. Invalid \'page\' field', [
                    'request' => $request,
                ]);
                throw new API_Exception('page_wrong', "Page must be positive int.");
            }
        } else {
            $request['filter']['page'] = 1;
        }

        if (empty($request['filter']['count_per_page'])) {
            $request['filter']['count_per_page'] = TRACKING_RANGE_API_LIMIT;
        } else {
            $request['filter']['count_per_page'] = (int)$request['filter']['count_per_page'];
            if ($request['filter']['count_per_page'] < 1) {
                $logger->error('Tracking events by date range. Invalid \'count_per_page\' field', [
                    'request' => $request,
                ]);
                throw new API_Exception('count_per_page_wrong', "Count per page must be positive int.");
            }
            if ($request['filter']['count_per_page'] > TRACKING_RANGE_API_LIMIT) {
                $request['filter']['count_per_page'] = TRACKING_RANGE_API_LIMIT;
            }
        }

        $result = get_tracking_by_date_range($request['filter']);

        if (empty($result['data'])) {
            $logger->error('Tracking events by date range. Nothing found', [
                'request' => $request,
            ]);
            throw new API_Exception('empty', 'Nothing found.');
        }

        if (!empty($result['errors'])) {
            throw new API_Exception('date_filter_is_missing', $result['errors']);
        }

        return $this->toApiResponse(
            [
                'success' => [
                    'tracking_data' => $result['data'],
                    'total_events_count' => (int)$result['total_events_count'],
                    'current_page' => (int)$request['filter']['page'],
                    'total_pages_count' => (int)$result['total_pages_count'],
                    'count_per_page' => (int)$request['filter']['count_per_page'],
                ],
            ],
            ApiHelpers::getFormat($requestObj)
        );
    }

    /**
     * @param ServerRequestInterface $requestObj
     * @return XmlResponse|JsonResponse
     * @throws API_Exception
     */
    public function actionGetByDateAddedRange(ServerRequestInterface $requestObj)
    {
        $logger = Container::instance()->getLogger()->withName('returns-api');
        $request = $this->extractRequestPayload($requestObj);
        if (empty($request['filter'])) {
            $logger->error('Tracking events by date added range. Missing required field \'Filter\'', [
                'request' => $request,
            ]);
            throw new API_Exception('request_wrong', "'Filter' field is missing");
        }

        if (!empty($request['filter']['page'])) {
            $request['filter']['page'] = (int)$request['filter']['page'];
            if ($request['filter']['page'] < 1) {
                $logger->error('Tracking events by date added range. Invalid \'page\' field', [
                    'request' => $request,
                ]);
                throw new API_Exception('page_wrong', "Page must be positive int.");
            }
        } else {
            $request['filter']['page'] = 1;
        }

        if (empty($request['filter']['count_per_page'])) {
            $request['filter']['count_per_page'] = TRACKING_RANGE_API_LIMIT;
        } else {
            $request['filter']['count_per_page'] = (int)$request['filter']['count_per_page'];
            if ($request['filter']['count_per_page'] < 1) {
                $logger->error('Tracking events by date added range. Invalid \'count_per_page\' field', [
                    'request' => $request,
                ]);
                throw new API_Exception('count_per_page_wrong', "Count per page must be positive int.");
            }
            if ($request['filter']['count_per_page'] > TRACKING_RANGE_API_LIMIT) {
                $request['filter']['count_per_page'] = TRACKING_RANGE_API_LIMIT;
            }
        }

        $result = get_tracking_by_date_range($request['filter'], true);

        if (empty($result['data'])) {
            $logger->error('Tracking events by date added range. Nothing found', [
                'request' => $request,
            ]);
            throw new API_Exception('empty', 'Nothing found.');
        }

        if (!empty($result['errors'])) {
            $logger->error('Tracking events by date added range. Error', [
                'request' => $request,
                'errors' => $result['errors'],
            ]);
            throw new API_Exception('date_filter_is_missing', $result['errors']);
        }

        return $this->toApiResponse(
            [
                'success' =>
                    [
                        'tracking_data' => $result['data'],
                        'total_events_count' => (int)$result['total_events_count'],
                        'current_page' => (int)$request['filter']['page'],
                        'total_pages_count' => (int)$result['total_pages_count'],
                        'count_per_page' => (int)$request['filter']['count_per_page'],
                    ],
            ],
            ApiHelpers::getFormat($requestObj)
        );
    }

    /**
     * @param ServerRequestInterface $requestObj
     * @return XmlResponse|JsonResponse
     * @throws API_Exception
     */
    public function actionAddStatus(ServerRequestInterface $requestObj)
    {
        $logger = Container::instance()->getLogger()->withName('returns-api');
        $request = $this->extractRequestPayload($requestObj);
        if (!isset($request["tracking_status"])) {
            $logger->error('Tracking add status. Missing required field \'tracking_status\'', [
                'request' => $request,
            ]);
            throw new API_Exception("request_wrong", "'tracking_status' field is missing");
        }

        $result = api_add_tracking_status($request["tracking_status"]);

        if ($result["code"] !== "success") {
            $logger->error('Tracking add status error', [
                'request' => $request,
                'result' => $result,
            ]);
            throw new API_Exception($result["code"], $result["message"]);
        }

        return $this->toApiResponse(
            [
                'success' => [
                    "code" => $result["code"],
                    "message" => [$result["message"]],
                ],
            ],
            ApiHelpers::getFormat($requestObj)
        );
    }
}
