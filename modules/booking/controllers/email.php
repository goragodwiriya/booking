<?php
/**
 * @filesource modules/booking/controllers/email.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Booking\Email;

use Booking\Booking\Model as BookingModel;
use Kotchasan\Language;

/**
 * Send email and LINE notifications to relevant parties
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Kotchasan\KBase
{
    /**
     * Send notification from booking reservation number.
     *
     * @param int $id
     *
     * @return string
     */
    public static function sendByBookingId(int $id): string
    {
        $order = BookingModel::getDetailData($id);
        if ($order === null) {
            return 'Saved successfully';
        }

        return self::send($order);
    }

    /**
     * Send email and LINE notifications for the transaction
     *
     * @param array $order
     *
     * @return string
     */
    private static function send($order)
    {
        $lines = [];
        $emails = [];
        $telegrams = [];
        $approvalStep = null;
        $approvalDepartment = null;
        if (!empty(self::$cfg->telegram_chat_id)) {
            $telegrams[self::$cfg->telegram_chat_id] = self::$cfg->telegram_chat_id;
        }
        $name = '';
        $mailto = '';
        $line_uid = '';
        $telegram_id = '';
        if (self::$cfg->demo_mode) {
            // Demo mode, send to the transaction maker and admin only
            $where = [
                ['U.id', [$order['member_id'], 1]]
            ];
        } else {
            // Send to the transaction maker and related staff
            $where = [
                ['U.id', [$order['member_id']]],
                ['U.status', 1]
            ];

            $approvalStep = \Booking\Helper\Controller::getApprovalStepConfig((int) $order['approve']);
            if ((int) $order['status'] === \Booking\Helper\Controller::STATUS_PENDING_REVIEW && $approvalStep !== null) {
                $approvalDepartment = $approvalStep['department'] === '' ? (string) $order['department'] : (string) $approvalStep['department'];
            } else {
                $approvalDepartment = null;
            }
        }

        $query = \Kotchasan\Model::createQuery()
            ->select('U.id', 'U.username', 'U.name', 'U.line_uid', 'U.telegram_id')
            ->from('user U')
            ->join('user_meta M', [['M.member_id', 'U.id'], ['M.name', 'department']], 'LEFT')
            ->where(['U.active', 1])
            ->where($where, 'OR')
            ->groupBy('U.id')
            ->cacheOn();

        if ($approvalDepartment !== null && $approvalStep !== null) {
            $query->whereRaw('(`M`.`value` = :approval_department AND `U`.`status` = :approval_user_status)', 'OR', [
                'approval_department' => $approvalDepartment,
                'approval_user_status' => (int) $approvalStep['status']
            ]);
        }

        foreach ($query->fetchAll() as $item) {
            if ($item->id == $order['member_id']) {
                // Transaction maker
                $name = $item->name;
                $mailto = $item->username;
                $line_uid = $item->line_uid;
                $order['name'] = $item->name;
                $telegram_id = $item->telegram_id;
            } else {
                // Staff
                $emails[] = $item->name.'<'.$item->username.'>';
                if (!empty($item->line_uid)) {
                    $lines[] = $item->line_uid;
                }
                if (!empty($item->telegram_id)) {
                    $telegrams[$item->telegram_id] = $item->telegram_id;
                }
            }
        }
        // Email message
        $msg = Language::trans(\Booking\View\View::render($order, true));
        $ret = [];
        if (!empty(self::$cfg->telegram_bot_token)) {
            // Telegram (Admin)
            $err = \Gcms\Telegram::sendTo($telegrams, $msg);
            if ($err != '') {
                $ret[] = $err;
            }
            // Telegram (User)
            $err = \Gcms\Telegram::sendTo($telegram_id, $msg);
            if ($err != '') {
                $ret[] = $err;
            }
        }
        if (!empty(self::$cfg->line_channel_access_token)) {
            // LINE (Admin)
            $err = \Gcms\Line::sendTo($lines, $msg);
            if ($err != '') {
                $ret[] = $err;
            }
            // LINE (User)
            $err = \Gcms\Line::sendTo($line_uid, $msg);
            if ($err != '') {
                $ret[] = $err;
            }
        }
        if (self::$cfg->noreply_email != '') {
            // email subject
            $subject = '['.self::$cfg->web_title.'] '.Language::get('Book a room').' '.Language::get('BOOKING_STATUS', '', $order['status']);
            // Always send an email to the person making the transaction.
            $err = \Kotchasan\Email::send($name.'<'.$mailto.'>', self::$cfg->noreply_email, $subject, $msg);
            if ($err->error()) {
                // Return error
                $ret[] = strip_tags($err->getErrorMessage());
            }
            foreach ($emails as $item) {
                // Send email
                $err = \Kotchasan\Email::send($item, self::$cfg->noreply_email, $subject, $msg);
                if ($err->error()) {
                    // Return error
                    $ret[] = strip_tags($err->getErrorMessage());
                }
            }
        }
        if (isset($err)) {
            // Email sent successfully or error sending email
            return empty($ret) ? 'Your message was sent successfully' : implode("\n", array_unique($ret));
        } else {
            // No emails need to be sent.
            return 'Saved successfully';
        }
    }
}
