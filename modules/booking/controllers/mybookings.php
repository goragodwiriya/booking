<?php
/**
 * @filesource modules/booking/controllers/mybookings.php
 */

namespace Booking\Mybookings;

use Booking\Booking\Model as BookingModel;
use Booking\Helper\Controller as Helper;
use Booking\View\View as BookingView;
use Gcms\Api as ApiController;
use Kotchasan\Http\Request;

class Controller extends \Gcms\Table
{
    /**
     * Allowed sort columns.
     *
     * @var array
     */
    protected $allowedSortColumns = [
        'room_name',
        'reason',
        'begin',
        'end',
        'created_at',
        'status'
    ];

    /**
     * Custom query params.
     *
     * @param Request $request
     * @param object $login
     *
     * @return array
     */
    protected function getCustomParams(Request $request, $login): array
    {
        return [
            'status' => $request->get('status')->filter('0-9')
        ];
    }

    /**
     * Build query.
     *
     * @param array $params
     * @param object|null $login
     *
     * @return \Kotchasan\QueryBuilder\QueryBuilderInterface
     */
    protected function toDataTable($params, $login = null)
    {
        return Model::toDataTable($params, $login);
    }

    /**
     * Filter options.
     *
     * @param array $params
     * @param object $login
     *
     * @return array
     */
    protected function getFilters(array $params, $login)
    {
        return [
            'status' => Helper::getStatusOptions()
        ];
    }

    /**
     * Format row data.
     *
     * @param array $datas
     * @param object|null $login
     *
     * @return array
     */
    protected function formatDatas(array $datas, $login = null): array
    {
        foreach ($datas as $item) {
            $item->can_edit = Helper::canEditBooking($item);
            $item->can_cancel = Helper::canCancelBookingByRequester($item);
            $item->first_image_url = Helper::getRoomFirstImageUrl((int) ($item->room_id ?? 0));
            $item->reservation_text = Helper::formatBookingTime($item);
        }

        return $datas;
    }

    /**
     * Row edit action.
     *
     * @param Request $request
     * @param object $login
     *
     * @return \Kotchasan\Http\Response
     */
    protected function handleEditAction(Request $request, $login)
    {
        $id = $request->post('id')->toInt();
        $row = BookingModel::getRecord((int) $login->id, $id);
        if ($row === null) {
            return $this->errorResponse('No data available', 404);
        }
        if (!Helper::canEditBooking($row)) {
            return $this->errorResponse('This booking can no longer be edited', 403);
        }

        return $this->redirectResponse('/booking?id='.$id, 'Opening booking');
    }

    /**
     * Row view action.
     *
     * @param Request $request
     * @param object $login
     *
     * @return \Kotchasan\Http\Response
     */
    protected function handleViewAction(Request $request, $login)
    {
        $id = $request->post('id')->toInt();
        $row = BookingModel::getRecord((int) $login->id, $id);
        if ($row === null) {
            return $this->errorResponse('No data available', 404);
        }

        $data = BookingModel::getDetailData($id);
        if ($data === null) {
            return $this->errorResponse('No data available', 404);
        }
        if (!BookingModel::canView($login, $data)) {
            return $this->errorResponse('Permission required', 403);
        }

        return $this->successResponse([
            'data' => $data,
            'actions' => [
                BookingView::buildModalAction($data)
            ]
        ], 'Booking details retrieved');
    }

    /**
     * Row cancel action.
     *
     * @param Request $request
     * @param object $login
     *
     * @return \Kotchasan\Http\Response
     */
    protected function handleCancelAction(Request $request, $login)
    {
        ApiController::validateMethod($request, 'POST');

        $id = $request->post('id')->toInt();
        $row = BookingModel::getRecord((int) $login->id, $id);
        if ($row === null) {
            return $this->errorResponse('No data available', 404);
        }
        if (!Helper::canCancelBookingByRequester($row)) {
            return $this->errorResponse('This booking can no longer be cancelled', 403);
        }

        BookingModel::updateStatus($id, Helper::STATUS_CANCELLED_BY_REQUESTER);
        \Index\Log\Model::add($id, 'booking', 'Cancel', 'Cancelled room booking: '.$id, $login->id);

        $message = \Booking\Email\Controller::sendByBookingId($id);

        return $this->redirectResponse('reload', $message, 200, 0, 'table');
    }

    /**
     * Bulk delete action for the requester.
     *
     * @param Request $request
     * @param object $login
     *
     * @return \Kotchasan\Http\Response
     */
    protected function handleDeleteAction(Request $request, $login)
    {
        ApiController::validateMethod($request, 'POST');

        $ids = $request->request('ids', [])->toInt();
        $removed = BookingModel::removeOwned((int) $login->id, $ids);
        if ($removed === 0) {
            return $this->errorResponse('No data to delete', 400);
        }

        \Index\Log\Model::add(0, 'booking', 'Delete', 'Deleted room booking ID(s): '.implode(', ', $ids), $login->id);

        return $this->redirectResponse('reload', 'Deleted booking(s) successfully', 200, 0, 'table');
    }
}