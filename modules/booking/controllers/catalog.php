<?php
/**
 * @filesource modules/booking/controllers/catalog.php
 */

namespace Booking\Catalog;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;

class Controller extends ApiController
{
    /**
     * Return active rooms for the member-facing catalog.
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function lists(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'GET');

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->errorResponse('Unauthorized', 401);
            }

            return $this->successResponse([
                'items' => Model::getItems(true)
            ], 'Room catalog retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * Return a single room detail modal payload.
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function detail(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'GET');

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->errorResponse('Unauthorized', 401);
            }

            $data = Model::get($request->get('id')->toInt(), true);
            if ($data === null) {
                return $this->redirectResponse('/404', 'No data available', 404);
            }

            return $this->successResponse([
                'data' => $data,
                'actions' => [
                    [
                        'type' => 'modal',
                        'action' => 'show',
                        'template' => '/booking/catalog-detail.html',
                        'title' => '{LNG_Room} '.$data->name,
                        'titleClass' => 'icon-office'
                    ]
                ]
            ], 'Room details retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }
}