<?php
/**
 * @filesource modules/booking/controllers/room.php
 */

namespace Booking\Room;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;

class Controller extends ApiController
{
    /**
     * Get room details.
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
            if (!ApiController::hasPermission($login, ['can_manage_booking', 'can_config'])) {
                return $this->errorResponse('Permission required', 403);
            }

            $data = Model::get($request->get('id', 0)->toInt());
            if ($data === null) {
                return $this->redirectResponse('/404', 'No data available', 404);
            }

            return $this->successResponse([
                'data' => $data,
                'actions' => [
                    [
                        'type' => 'modal',
                        'action' => 'show',
                        'template' => '/booking/room.html',
                        'title' => ($data->id > 0 ? '{LNG_Edit} {LNG_Room}' : '{LNG_Add} {LNG_Room}'),
                        'titleClass' => 'icon-office'
                    ]
                ]
            ], 'Room details retrieved');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * Save room details.
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
            if (!ApiController::canModify($login, ['can_manage_booking', 'can_config'])) {
                return $this->errorResponse('Permission required', 403);
            }

            $id = $request->post('id', 0)->toInt();
            if ($id > 0 && Model::getRecord($id) === null) {
                return $this->errorResponse('No data available', 404);
            }

            $save = [
                'name' => $request->post('name')->topic(),
                'color' => $request->post('color')->toString(),
                'detail' => $request->post('detail')->textarea(),
                'is_active' => $request->post('is_active')->toBoolean() ? 1 : 0
            ];
            $meta = [
                'building' => $request->post('building')->topic(),
                'number' => $request->post('number')->topic(),
                'seats' => $request->post('seats')->topic()
            ];

            $errors = [];
            if ($save['name'] === '') {
                $errors['name'] = 'Please fill in';
            }

            if (!empty($errors)) {
                return $this->formErrorResponse($errors, 400);
            }

            $id = Model::save($id, $save, $meta);

            $ret = [];
            \Download\Upload\Model::execute($ret, $request, $id, 'booking', self::$cfg->img_typies, 0, self::$cfg->stored_img_size);

            \Index\Log\Model::add($id, 'booking', 'Save', 'Saved room: '.$save['name'], $login->id);

            return $this->successResponse([
                'actions' => [
                    [
                        'type' => 'notification',
                        'level' => empty($ret) ? 'success' : 'error',
                        'message' => empty($ret) ? 'Saved successfully' : ($ret['booking'] ?? 'Saved successfully')
                    ],
                    [
                        'type' => 'redirect',
                        'url' => 'reload',
                        'target' => 'table',
                        'delay' => 3000
                    ],
                    [
                        'type' => 'modal',
                        'action' => 'close'
                    ]
                ]
            ], 'Saved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * Remove an uploaded room image.
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function removeImage(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'POST');
            $this->validateCsrfToken($request);

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->redirectResponse('/login', 'Unauthorized', 401);
            }
            if (!ApiController::canModify($login, ['can_manage_booking', 'can_config'])) {
                return $this->errorResponse('Permission required', 403);
            }

            $json = json_decode($request->post('id')->toString());
            if (!$json || !isset($json->id, $json->file)) {
                return $this->errorResponse('No data available', 404);
            }
            if (Model::getRecord((int) $json->id) === null) {
                return $this->errorResponse('No data available', 404);
            }

            $file = ROOT_PATH.DATA_FOLDER.'booking/'.$json->id.'/'.$json->file;
            if (!file_exists($file)) {
                return $this->errorResponse('No data available', 404);
            }

            @unlink($file);

            \Index\Log\Model::add((int) $json->id, 'booking', 'Delete', 'Removed room image: '.$json->file, $login->id);

            return $this->successResponse([], 'Image removed successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }
}