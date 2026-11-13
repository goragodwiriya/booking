<?php
/**
 * @filesource modules/booking/views/view.php
 *
 * @copyright 2016 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Booking\View;

/**
 * Show document details (modal)
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Kotchasan\KBase
{
    /**
     * Render booking details for modal/email style output.
     * Labels are {LNG_...} inside data-i18n: email passes the result through
     * Language::trans(), the modal sends it as is so the page translates it
     * in its own locale (the server cannot see a language switched in the browser).
     *
     * @param array $index
     * @param bool $email
     *
     * @return string
     */
    public static function render($index, $email = false)
    {
        $content = [];
        $escape = static function ($value) {
            return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        };
        $escapeMultiline = static function ($value) use ($escape) {
            return nl2br($escape($value));
        };

        if ($email) {
            $content[] = '<header>';
            $content[] = '<h4>{LNG_Reservation details} '.$escape($index['room_name']).'</h4>';
            $content[] = '</header>';
        }

        if (!empty($index['room_image_url']) && !$email) {
            $content[] = '<p class="center"><img src="'.$escape($index['room_image_url']).'" alt="'.$escape($index['room_name']).'" style="max-width:100%;max-height:240px"></p>';
        }

        $content[] = '<table class="fullwidth email-table">';
        $content[] = '<tr><td class="item"><span class="icon-user" data-i18n>{LNG_Name}</span></td><td class="item"> : </td><td class="item">'.$escape($index['member_name']).'</td></tr>';
        $roomText = $escape($index['room_name']);
        if (!empty($index['room_number'])) {
            $roomText .= ' ('.$escape($index['room_number']).')';
        }
        $content[] = '<tr><td class="item"><span class="icon-office" data-i18n>{LNG_Room}</span></td><td class="item"> : </td><td class="item">'.$roomText.'</td></tr>';
        if (!empty($index['room_building'])) {
            $content[] = '<tr><td class="item"><span class="icon-location" data-i18n>{LNG_Building}</span></td><td class="item"> : </td><td class="item">'.$escape($index['room_building']).'</td></tr>';
        }
        if (!empty($index['topic'])) {
            $content[] = '<tr><td class="item"><span class="icon-file" data-i18n>{LNG_Topic}</span></td><td class="item"> : </td><td class="item">'.$escapeMultiline($index['topic']).'</td></tr>';
        }
        if (!empty($index['comment'])) {
            $content[] = '<tr><td class="item"><span class="icon-comments" data-i18n>{LNG_Notes}</span></td><td class="item"> : </td><td class="item">'.$escapeMultiline($index['comment']).'</td></tr>';
        }
        $reservationText = !empty($index['reservation_text']) ? $index['reservation_text'] : $index['begin_text'].' - '.$index['end_text'];
        $content[] = '<tr><td class="item"><span class="icon-calendar" data-i18n>{LNG_Reservation period}</span></td><td class="item"> : </td><td class="item">'.$escape($reservationText).'</td></tr>';
        $content[] = '<tr><td class="item"><span class="icon-group" data-i18n>{LNG_Attendees number}</span></td><td class="item"> : </td><td class="item">'.$escape($index['attendees']).'</td></tr>';
        if (!empty($index['use_text'])) {
            $content[] = '<tr><td class="item"><span class="icon-menus" data-i18n>{LNG_Use for}</span></td><td class="item"> : </td><td class="item">'.$escape($index['use_text']).'</td></tr>';
        }
        if (!empty($index['accessories_text'])) {
            $content[] = '<tr><td class="item"><span class="icon-list" data-i18n>{LNG_Accessories}</span></td><td class="item"> : </td><td class="item">'.$escape($index['accessories_text']).'</td></tr>';
        }
        $content[] = '<tr><td class="item"><span class="icon-star0" data-i18n>{LNG_Status}</span></td><td class="item"> : </td><td class="item">'.$escape($index['status_text']).'</td></tr>';
        if (!empty($index['reason'])) {
            $content[] = '<tr><td class="item"><span class="icon-file" data-i18n>{LNG_Reason}</span></td><td class="item"> : </td><td class="item">'.$escapeMultiline($index['reason']).'</td></tr>';
        }
        $content[] = '</table>';
        // Restore HTML
        return implode("\n", $content);
    }

    /**
     * Build the shared modal action payload for booking details.
     *
     * @param array $index
     *
     * @return array
     */
    public static function buildModalAction(array $index): array
    {
        return [
            'type' => 'modal',
            'action' => 'show',
            'template' => 'booking/reservation-view.html',
            'dataKey' => 'data',
            'title' => trim('{LNG_Details of} '.$index['room_name']),
            'titleClass' => 'icon-office'
        ];
    }
}
