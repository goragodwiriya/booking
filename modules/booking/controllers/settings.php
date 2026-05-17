<?php
/**
 * @filesource modules/booking/controllers/settings.php
 */

namespace Booking\Settings;

use Booking\Helper\Controller as Helper;
use Gcms\Api as ApiController;
use Gcms\Config;
use Kotchasan\Http\Request;
use Kotchasan\Language;

class Controller extends ApiController
{
    /**
     * Get module settings.
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function get(Request $request)
    {
        try {
            // Validate request method (GET request doesn't need CSRF token)
            ApiController::validateMethod($request, 'GET');

            // Read user from token (Bearer /X-Access-Token param)
            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->errorResponse('Unauthorized', 401);
            }

            // Permission check
            if (!ApiController::hasPermission($login, ['can_manage_booking', 'can_config'])) {
                return $this->errorResponse('Forbidden', 403);
            }

            return $this->successResponse([
                'data' => (object) [
                    'booking_approving' => self::$cfg->booking_approving,
                    'booking_cancellation' => self::$cfg->booking_cancellation,
                    'booking_delete' => self::$cfg->booking_delete,
                    'booking_approve_level' => Helper::getApprovalLevelCount(),
                    'booking_approve_status' => self::$cfg->booking_approve_status,
                    'booking_approve_department' => self::$cfg->booking_approve_department
                ],
                'options' => (object) [
                    'booking_statuses' => Helper::getStatusOptions(),
                    'status' => \Gcms\Controller::getUserStatusOptions(),
                    'department' => \Gcms\Category::init()->toOptions('department')
                ]
            ], 'Booking settings loaded');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * Save module settings.
     *
     * @param Request $request
     *
     * @return mixed
     */
    public function save(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'POST');
            ApiController::validateCsrfToken($request);

            // Authentication check (required)
            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->redirectResponse('/login', 'Unauthorized', 401);
            }

            // Permission check
            if (!ApiController::canModify($login, ['can_manage_booking', 'can_config'])) {
                return $this->errorResponse('Permission required', 403);
            }

            $bookingApproving = $request->post('booking_approving')->toInt();
            if (!in_array($bookingApproving, [
                Helper::APPROVAL_BEFORE_END,
                Helper::APPROVAL_BEFORE_START,
                Helper::APPROVAL_ALWAYS
            ], true)) {
                $bookingApproving = Helper::APPROVAL_BEFORE_START;
            }

            $bookingCancellation = $request->post('booking_cancellation')->toInt();
            if (!in_array($bookingCancellation, [
                Helper::CANCELLATION_PENDING_ONLY,
                Helper::CANCELLATION_BEFORE_DATE,
                Helper::CANCELLATION_BEFORE_START,
                Helper::CANCELLATION_BEFORE_END,
                Helper::CANCELLATION_ALWAYS
            ], true)) {
                $bookingCancellation = Helper::CANCELLATION_PENDING_ONLY;
            }

            $bookingStatuses = Language::get('BOOKING_STATUS');
            $bookingDelete = [];
            foreach ($request->post('booking_delete')->toArray() as $status) {
                if (isset($bookingStatuses[$status])) {
                    $bookingDelete[] = (int) $status;
                }
            }

            $bookingApproveLevel = max(0, $request->post('booking_approve_level')->toInt());
            $bookingApproveStatus = $request->post('booking_approve_status', [])->toInt();
            $bookingApproveDepartment = $request->post('booking_approve_department', [])->topic();

            $config = Config::load(ROOT_PATH.'settings/config.php');
            $config->booking_approving = $bookingApproving;
            $config->booking_cancellation = $bookingCancellation;
            $config->booking_delete = $bookingDelete;
            $config->booking_approve_level = $bookingApproveLevel;
            $config->booking_approve_status = [];
            $config->booking_approve_department = [];
            for ($level = 1; $level <= $bookingApproveLevel; $level++) {
                $config->booking_approve_status[$level] = isset($bookingApproveStatus[$level]) ? (int) $bookingApproveStatus[$level] : 0;
                $config->booking_approve_department[$level] = isset($bookingApproveDepartment[$level]) ? (string) $bookingApproveDepartment[$level] : '';
            }

            if (Config::save($config, ROOT_PATH.'settings/config.php')) {
                \Index\Log\Model::add(0, 'booking', 'Save', 'Save Booking Settings', $login->id);

                // Reload page
                return $this->redirectResponse('reload', 'Saved successfully', 200, 1000);
            }
        } catch (\Kotchasan\ApiException $e) {
            // Keep original HTTP code (e.g. 403 CSRF, 405 method)
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 400);
        }
        // Error save settings
        return $this->errorResponse('Failed to save settings', 500);
    }
}
