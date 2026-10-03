<?php
/**
 * @filesource modules/index/controllers/dashboard.php
 *
 * api/index/dashboard — ตัวเลขสรุปของหน้าแรก
 *
 * ตัวหน้าเองไม่รู้จักโมดูลไหนเลย มันรวบรวมของสองชนิดจากทุกโมดูลที่ติดตั้งอยู่
 * ผ่าน hook แบบเดียวกับที่เมนูและสิทธิ์ทำ (ระบบเดิมของ oms ก็ใช้วิธีนี้ผ่าน
 * addCard/addMenu/addBlock) ถอดโมดูลออกแล้วของของโมดูลนั้นหายไปเอง
 * ไม่ต้องแก้หน้าแรก
 *
 *   initDashboard        การ์ดตัวเลข  {title, value, unit, icon, url, hint, class}
 *   initDashboardBlocks  บล็อกกราฟ    {title, type, url, size}
 *
 * `url` ของการ์ดส่งเป็น null ได้ ถ้าตัวเลขนั้นไม่มีหน้าให้เจาะดูต่อ
 * (data-attr ถอดแอตทริบิวต์ href ออกให้เมื่อค่าเป็น null) และ `class`
 * ใส่ positive/negative เพื่อให้บรรทัดล่างของการ์ดเป็นสีเขียว/แดง
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 */

namespace Index\Dashboard;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;
use Kotchasan\Http\Response;

/**
 * API หน้าแรก
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends ApiController
{
    /**
     * GET api/index/dashboard
     *
     * @param Request $request
     *
     * @return Response
     */
    public function index(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'GET');

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->errorResponse('Unauthorized', 401);
            }

            $cards = \Gcms\Controller::initModule([], 'initDashboard', $login);
            $blocks = \Gcms\Controller::initModule([], 'initDashboardBlocks', $login);

            return $this->successResponse([
                'cards' => array_values($cards),
                'has_cards' => !empty($cards),
                'blocks' => array_values($blocks),
                'has_blocks' => !empty($blocks)
            ], 'OK');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }
}
