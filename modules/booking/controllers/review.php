<?php
/**
 * @filesource modules/booking/controllers/review.php
 */

namespace Booking\Review;

use Booking\Booking\Model as BookingModel;
use Booking\Helper\Controller as Helper;
use Gcms\Api as ApiController;
use Kotchasan\Date;
use Kotchasan\Http\Request;

class Controller extends ApiController
{
    /**
     * Get review details.
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function get(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'GET');

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->errorResponse('Unauthorized', 401);
            }
            if (!Helper::canApproveRequests($login)) {
                return $this->errorResponse('Forbidden', 403);
            }

            $row = Model::get($request->get('id')->toInt());
            if ($row === null) {
                return $this->redirectResponse('/404', 'No data available', 404);
            }

            $canProcess = Model::canProcess($row);
            $canApproveAction = $canProcess && Helper::canApproveStep($login, $row);

            return $this->successResponse([
                'id' => (int) $row->id,
                'member_name' => (string) $row->member_name,
                'room_name' => (string) $row->room_name,
                'room_number' => (string) ($row->room_number ?? ''),
                'reason' => (string) $row->reason,
                'topic' => (string) $row->topic,
                'comment' => (string) $row->comment,
                'attendees' => (int) $row->attendees,
                'reservation_text' => Helper::formatBookingTime($row),
                'begin_text' => Date::format($row->begin, 'd M Y H:i'),
                'end_text' => Date::format($row->end, 'd M Y H:i'),
                'status_text' => Helper::getStatusText($row),
                'use_text' => (string) $row->use_text,
                'accessories_text' => (string) $row->accessories_text,
                'approval_reason' => (string) $row->reason,
                'canProcess' => $canProcess,
                'canApproveAction' => $canApproveAction,
                'canCancelAction' => Helper::canCancelBookingByOfficer($row)
            ], 'Review data retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * Approve reservation.
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function approve(Request $request)
    {
        return $this->processDecision($request, Helper::STATUS_APPROVED, 'Approved room booking', false);
    }

    /**
     * Reject reservation.
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function reject(Request $request)
    {
        return $this->processDecision($request, Helper::STATUS_REJECTED, 'Rejected room booking', true);
    }

    /**
     * Return reservation for correction.
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function returnedit(Request $request)
    {
        return $this->processDecision($request, Helper::STATUS_RETURNED_FOR_EDIT, 'Returned room booking for correction', true);
    }

    /**
     * Cancel reservation by officer.
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function cancelofficer(Request $request)
    {
        return $this->processDecision($request, Helper::STATUS_CANCELLED_BY_OFFICER, 'Cancelled room booking by officer', true, true);
    }

    /**
     * Shared decision handler.
     *
     * @param Request $request
     * @param int $status
     * @param string $logTopic
     * @param bool $requireReason
     * @param bool $allowOfficerCancel
     *
     * @return \Kotchasan\Http\Response
     */
    protected function processDecision(Request $request, int $status, string $logTopic, bool $requireReason, bool $allowOfficerCancel = false)
    {
        try {
            ApiController::validateMethod($request, 'POST');
            $this->validateCsrfToken($request);

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->errorResponse('Unauthorized', 401);
            }
            if (!Helper::canApproveRequests($login)) {
                return $this->errorResponse('Permission required', 403);
            }

            $id = $request->post('id')->toInt();
            $approvalReason = trim($request->post('approval_reason')->textarea());

            $row = Model::get($id);
            if ($row === null) {
                return $this->errorResponse('No data available', 404);
            }

            if ($allowOfficerCancel) {
                if (!Helper::canCancelBookingByOfficer($row)) {
                    return $this->errorResponse('This booking can no longer be cancelled', 400);
                }
            }
            if (!Model::canProcess($row)) {
                return $this->errorResponse('This booking has already been processed', 400);
            }
            if (!Helper::canApproveStep($login, $row)) {
                return $this->errorResponse('You are not allowed to approve this step', 403);
            }

            if ($requireReason && $approvalReason === '') {
                return $this->errorResponse('Decision note is required', 400);
            }

            $currentApprove = (int) ($row->approve ?? 1);
            $closedLevel = (int) ($row->closed ?? 1);

            $save = [];
            $finalStatus = $status;
            if ($status === Helper::STATUS_APPROVED) {
                if (!BookingModel::availability([
                    'id' => (int) $row->id,
                    'room_id' => (int) $row->room_id,
                    'begin' => (string) $row->begin,
                    'end' => (string) $row->end
                ])) {
                    return $this->errorResponse('Booking are not available at select time', 400);
                }

                if ($currentApprove >= $closedLevel || \Gcms\Api::isAdmin($login)) {
                    $save['approve'] = $closedLevel;
                } else {
                    $finalStatus = Helper::STATUS_PENDING_REVIEW;
                    $nextApprove = Helper::getNextApprovalStep($currentApprove);
                    $save['approve'] = $nextApprove > 0 ? $nextApprove : $closedLevel;
                }
                $save['reason'] = '';
            } else {
                $save['approve'] = $currentApprove;
                $save['reason'] = $approvalReason;
            }

            BookingModel::updateStatus($id, $finalStatus, $save);
            \Index\Log\Model::add($id, 'booking', 'Status', $logTopic.': '.$id, $login->id, $approvalReason, [
                'status' => $finalStatus
            ]);

            $message = \Booking\Email\Controller::sendByBookingId($id);

            return $this->redirectResponse('/approvals', $message);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }
}
