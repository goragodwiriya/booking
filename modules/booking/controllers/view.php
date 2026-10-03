<?php
/**
 * @filesource modules/booking/controllers/view.php
 */

namespace Booking\View;

use Booking\Booking\Model as BookingModel;
use Gcms\Api as ApiController;
use Kotchasan\Http\Request;

class Controller extends ApiController
{
    /**
     * Return a modal action with full booking details.
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function index(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'GET');

            // Same rule as the calendar this modal is opened from
            if (empty(self::$cfg->booking_calendar_guest) && !$this->authenticateRequest($request)) {
                return $this->errorResponse('Unauthorized', 401);
            }

            $data = BookingModel::getDetailData($request->get('id')->toInt());
            if ($data === null) {
                return $this->errorResponse('No data available', 404);
            }

            return $this->successResponse([
                'data' => $data,
                'actions' => [
                    View::buildModalAction($data)
                ]
            ], 'Booking details retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }
}
