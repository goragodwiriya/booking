<?php
/**
 * @filesource modules/booking/controllers/booking.php
 */

namespace Booking\Booking;

use Booking\Helper\Controller as Helper;
use Gcms\Api as ApiController;
use Kotchasan\Http\Request;

class Controller extends ApiController
{
    /**
     * Get booking details.
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

            $data = Model::get(
                $login,
                $request->get('id')->toInt(),
                $request->get('room_id')->toInt()
            );
            if ($data === null) {
                return $this->redirectResponse('/404', 'No data available', 404);
            }

            $includeRoomId = !empty($data->room_id) ? (int) $data->room_id : null;

            $data->options = [
                'room_id' => Helper::getRoomOptions(true, $includeRoomId),
                'use' => Helper::getUseOptions(),
                'accessories' => Helper::getAccessoryOptions()
            ];

            return $this->successResponse($data, 'Booking details retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

/**
 * Save booking.
 *
 * @param Request $request
 *
 * @return \Kotchasan\Http\Response
 */
    public function save(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'POST');
            $this->validateCsrfToken($request);

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->redirectResponse('/login', 'Unauthorized', 401);
            }

            // Parse input data
            $save = $this->parseInput($request);

            // Check if booking exists and user has permission to edit
            $booking = Model::get($login, $request->post('id')->toInt());
            if (!$booking) {
                return $this->errorResponse('No data available', 404);
            }

            // If booking exists, check if it can be edited
            if (!$booking->canEdit) {
                return $this->errorResponse('This booking can no longer be edited', 403);
            }
            // Validate input data and prepare save data
            $errors = $this->validateAndPrepareSaveData($save, $booking);
            if (!empty($errors)) {
                // Error response
                return $this->formErrorResponse($errors, 400);
            }

            $meta = [
                'use' => $save['use'],
                'accessories' => array_values(array_filter(array_map('intval', $save['accessories'])))
            ];
            unset($save['use'], $save['accessories']);

            $id = Model::saveReservation($booking->id, $save, $meta);
            \Index\Log\Model::add($id, 'booking', 'Save', 'Saved room booking: '.$id, $login->id);

            if ($booking->id === 0 || $booking->status !== $save['status']) {
                $message = \Booking\Email\Controller::sendByBookingId($id);
            } else {
                $message = 'Saved successfully';
            }

            return $this->redirectResponse('/my-bookings', $message);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * Parse user input from request
     *
     * @param Request $request
     *
     * @return array
     */
    protected function parseInput(Request $request): array
    {
        return [
            'begin_date' => $request->post('begin_date')->date(),
            'begin_time' => $request->post('begin_time')->time(),
            'end_date' => $request->post('end_date')->date(),
            'end_time' => $request->post('end_time')->time(),
            'accessories' => $request->post('accessories', [])->toInt(),
            'comment' => $request->post('comment')->textarea(),
            'topic' => $request->post('topic')->topic(),
            'attendees' => $request->post('attendees')->toInt(),
            'room_id' => $request->post('room_id')->toInt(),
            'use' => $request->post('use')->topic()
        ];
    }

    /**
     * Validate booking input and prepare the save payload.
     *
     * @param array &$save Save data (modified by reference)
     * @param object $booking Existing booking
     *
     * @return array Validation errors, empty if valid
     */
    protected function validateAndPrepareSaveData(&$save, $booking)
    {
        $errors = [];
        if ($save['room_id'] <= 0) {
            $errors['room_id'] = 'Please select';
        }
        if ($save['attendees'] <= 0) {
            $errors['attendees'] = 'Please fill in';
        }
        if ($save['topic'] === '') {
            $errors['topic'] = 'Please fill in';
        }
        if ($save['begin_date'] === '') {
            $errors['begin_date'] = 'Please fill in';
        }
        if ($save['begin_time'] === '') {
            $errors['begin_time'] = 'Please fill in';
        }
        if ($save['end_date'] === '') {
            $errors['end_date'] = 'Please fill in';
        }
        if ($save['end_time'] === '') {
            $errors['end_time'] = 'Please fill in';
        }

        if (empty($errors)) {
            if ($save['end_date'] >= $save['begin_date']) {
                if ($save['end_time'] <= $save['begin_time']) {
                    $errors['end_time'] = 'End time must be greater than begin time';
                }

                if (!empty($errors)) {
                    return $errors;
                }

                $save['begin'] = $save['begin_date'].' '.$save['begin_time'].':01';
                $save['end'] = $save['end_date'].' '.$save['end_time'].':00';
                $save['schedule_type'] = Helper::SCHEDULE_DAILY_SLOT;
                $save['id'] = $booking->id;
                $save['member_id'] = $booking->member_id;

                if (!Model::availability($save)) {
                    $errors['begin_date'] = 'Booking are not available at select time';
                }

                unset($save['begin_date']);
                unset($save['begin_time']);
                unset($save['end_date']);
                unset($save['end_time']);

                if ($booking->id === 0) {
                    $save['created_at'] = date('Y-m-d H:i:s');
                }
            } else {
                $errors['end_date'] = 'End date must be greater than or equal to begin date';
            }

            if ($booking->id > 0 && $booking->status === Helper::STATUS_RETURNED_FOR_EDIT) {
                // comes from editing return to pending approval status again.
                $save['status'] = Helper::STATUS_PENDING_REVIEW;
            } else {
                $save['status'] = $booking->status;
                $save['closed'] = $booking->closed;
                $save['approve'] = $booking->approve;
            }
        }

        return $errors;
    }

    /**
     * Cancel booking.
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function cancel(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'POST');
            $this->validateCsrfToken($request);

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->redirectResponse('/login', 'Unauthorized', 401);
            }

            $id = $request->post('id')->toInt();
            $row = Model::getRecord((int) $login->id, $id);
            if ($row === null) {
                return $this->errorResponse('No data available', 404);
            }
            if (!Helper::canCancelBookingByRequester($row)) {
                return $this->errorResponse('This booking can no longer be cancelled', 403);
            }

            Model::updateStatus($id, Helper::STATUS_CANCELLED_BY_REQUESTER);

            // Log cancellation
            \Index\Log\Model::add($id, 'booking', 'Cancel', 'Cancelled room booking: '.$id, $login->id);

            // Send notification
            $message = \Booking\Email\Controller::sendByBookingId($id);

            return $this->redirectResponse('/my-bookings', $message);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }
}
