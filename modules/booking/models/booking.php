<?php
/**
 * @filesource modules/booking/models/booking.php
 */

namespace Booking\Booking;

use Booking\Helper\Controller as Helper;
use Kotchasan\Database\Sql;
use Kotchasan\Date;

class Model extends \Kotchasan\Model
{
    /**
     * Get booking form data.
     *
     * @param object $login
     * @param int $id
     *
     * @return object|null
     */
    public static function get($login, int $id = 0, int $prefillRoomId = 0)
    {
        if ($id <= 0) {
            $closedLevel = Helper::getApprovalLevelCount();
            $record = (object) [
                'id' => 0,
                'member_id' => (int) $login->id,
                'member_name' => (string) $login->name,
                'room_id' => $prefillRoomId,
                'room_name' => '',
                'topic' => '',
                'reason' => '',
                'comment' => '',
                'attendees' => 1,
                'begin' => '',
                'end' => '',
                'begin_date' => '',
                'begin_time' => '',
                'end_date' => '',
                'end_time' => '',
                'schedule_type' => Helper::SCHEDULE_DAILY_SLOT,
                'use' => '',
                'accessories' => [],
                'canEdit' => true,
                'approve' => 1,
                'closed' => $closedLevel > 0 ? $closedLevel : 1
            ];

            if ($closedLevel === 0) {
                // Immediate approval
                $record->status = Helper::STATUS_APPROVED;
            } else {
                // Approval level 1
                $record->status = Helper::STATUS_PENDING_REVIEW;
            }

            return $record;
        }
        $record = static::createQuery()
            ->select('R.*', 'U.name member_name', 'Room.name room_name')
            ->from('reservation R')
            ->join('user U', ['U.id', 'R.member_id'], 'LEFT')
            ->join('rooms Room', ['Room.id', 'R.room_id'], 'LEFT')
            ->where([
                ['R.id', $id],
                ['R.member_id', $login->id]
            ])
            ->first();

        if (!$record) {
            return null;
        }

        $meta = self::getMetaValues($id);
        $record->schedule_type = Helper::getScheduleType($record, Helper::SCHEDULE_CONTINUOUS);

        $record->begin_date = !empty($record->begin) ? date('Y-m-d', strtotime((string) $record->begin)) : '';
        $record->end_date = !empty($record->end) ? date('Y-m-d', strtotime((string) $record->end)) : '';
        $record->begin_time = !empty($record->begin) ? date('H:i', strtotime((string) $record->begin)) : '';
        $record->end_time = !empty($record->end) ? date('H:i', strtotime((string) $record->end)) : '';
        $record->use = (string) ($meta['use'] ?? '');
        $record->accessories = self::csvToArray($meta['accessories'] ?? '');
        $record->canEdit = Helper::canEditBooking($record);
        $record->status_text = Helper::getStatusText($record);

        return $record;
    }

    /**
     * Get an owned reservation record.
     *
     * @param int $memberId
     * @param int $id
     *
     * @return object|null
     */
    public static function getRecord(int $memberId, int $id)
    {
        return static::createQuery()
            ->select()
            ->from('reservation')
            ->where([
                ['id', $id],
                ['member_id', $memberId]
            ])
            ->first();
    }

    /**
     * Get any reservation record.
     *
     * @param int $id
     *
     * @return object|null
     */
    public static function getById(int $id)
    {
        return static::createQuery()
            ->select()
            ->from('reservation')
            ->where(['id', $id])
            ->first();
    }

    /**
     * Get a booking row with detail joins used by views and notifications.
     *
     * @param int $id
     *
     * @return object|null
     */
    public static function getDetailRow(int $id)
    {
        if ($id <= 0) {
            return null;
        }

        return static::createQuery()
            ->select(
                'R.id',
                'R.room_id',
                'R.member_id',
                'R.reason',
                'R.topic',
                'R.comment',
                'R.attendees',
                'R.begin',
                'R.end',
                'R.schedule_type',
                'R.status',
                'R.approve',
                'R.closed',
                'R.created_at',
                'U.name member_name',
                'U.username member_email',
                'U.line_uid member_line_uid',
                'U.telegram_id member_telegram_id',
                'Room.name room_name',
                'Room.color room_color',
                'RoomNumber.value room_number',
                'Building.value room_building',
                'Seats.value room_seats',
                'UseMeta.value use_id',
                'Accessories.value accessories'
            )
            ->from('reservation R')
            ->join('rooms Room', ['Room.id', 'R.room_id'], 'LEFT')
            ->join('user U', ['U.id', 'R.member_id'], 'LEFT')
            ->join('rooms_meta RoomNumber', [['RoomNumber.room_id', 'Room.id'], ['RoomNumber.name', 'number']], 'LEFT')
            ->join('rooms_meta Building', [['Building.room_id', 'Room.id'], ['Building.name', 'building']], 'LEFT')
            ->join('rooms_meta Seats', [['Seats.room_id', 'Room.id'], ['Seats.name', 'seats']], 'LEFT')
            ->join('reservation_data UseMeta', [['UseMeta.reservation_id', 'R.id'], ['UseMeta.name', 'use']], 'LEFT')
            ->join('reservation_data Accessories', [['Accessories.reservation_id', 'R.id'], ['Accessories.name', 'accessories']], 'LEFT')
            ->where(['R.id', $id])
            ->first();
    }

    /**
     * Get normalized booking detail data.
     *
     * @param int $id
     *
     * @return array|null
     */
    public static function getDetailData(int $id): ?array
    {
        $row = self::getDetailRow($id);
        if (!$row) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'member_id' => (int) $row->member_id,
            'member_name' => (string) $row->member_name,
            'member_email' => (string) $row->member_email,
            'member_line_uid' => (string) $row->member_line_uid,
            'member_telegram_id' => (string) $row->member_telegram_id,
            'room_id' => (int) $row->room_id,
            'room_name' => (string) $row->room_name,
            'room_number' => (string) ($row->room_number ?? ''),
            'room_building' => (string) ($row->room_building ?? ''),
            'room_seats' => (string) ($row->room_seats ?? ''),
            'reason' => (string) $row->reason,
            'topic' => (string) $row->topic,
            'comment' => (string) $row->comment,
            'attendees' => (int) $row->attendees,
            'begin' => (string) $row->begin,
            'end' => (string) $row->end,
            'schedule_type' => Helper::getScheduleType($row, Helper::SCHEDULE_CONTINUOUS),
            'begin_text' => Date::format($row->begin, 'd M Y H:i'),
            'end_text' => Date::format($row->end, 'd M Y H:i'),
            'reservation_text' => Helper::formatBookingTime((object) [
                'begin' => (string) $row->begin,
                'end' => (string) $row->end,
                'schedule_type' => Helper::getScheduleType($row, Helper::SCHEDULE_CONTINUOUS)
            ]),
            'use_id' => (string) ($row->use_id ?? ''),
            'use_text' => Helper::getUseLabel($row->use_id ?? ''),
            'accessories_text' => Helper::formatAccessoryNames($row->accessories ?? ''),
            'status' => Helper::getStatusValue($row),
            'status_text' => Helper::getStatusText($row),
            'approve' => (int) ($row->approve ?? 1),
            'closed' => (int) ($row->closed ?? 1),
            'created_at' => (string) ($row->created_at ?? ''),
            'room_image_url' => Helper::getRoomFirstImageUrl((int) $row->room_id)
        ];
    }

    /**
     * Check if a user may view a booking detail record.
     *
     * @param object $login
     * @param array|null $detail
     *
     * @return bool
     */
    public static function canView($login, ?array $detail): bool
    {
        if (!$detail) {
            return false;
        }

        $userId = (int) ($login->id ?? 0);
        if ($userId <= 0) {
            return false;
        }

        if (Helper::canApproveRequests($login)) {
            return true;
        }

        return $userId === (int) ($detail['member_id'] ?? 0);
    }

    /**
     * Save reservation.
     *
     * @param int $id
     * @param array $save
     * @param array $meta
     *
     * @return int
     */
    public static function saveReservation(int $id, array $save, array $meta): int
    {
        $db = \Kotchasan\DB::create();
        if ($id > 0) {
            $db->update('reservation', ['id', $id], $save);
        } else {
            $id = (int) $db->insert('reservation', $save);
        }

        self::saveMeta($id, $meta);

        return $id;
    }

    /**
     * Update reservation status and optional meta fields.
     *
     * @param int $id
     * @param int $status
     * @param array $fields
     * @param array $metaUpdates
     *
     * @return void
     */
    public static function updateStatus(int $id, int $status, array $fields = [], array $metaUpdates = []): void
    {
        if ($id <= 0) {
            return;
        }

        $save = array_merge($fields, [
            'status' => Helper::normalizeStatusId($status)
        ]);

        \Kotchasan\DB::create()->update('reservation', ['id', $id], $save);

        if (!empty($metaUpdates)) {
            $meta = self::getMetaValues($id);
            foreach ($metaUpdates as $name => $value) {
                $value = is_array($value) ? implode(',', $value) : trim((string) $value);
                if ($value === '') {
                    unset($meta[$name]);
                } else {
                    $meta[$name] = $value;
                }
            }
            self::saveMeta($id, $meta);
        }
    }

    /**
     * Delete reservations owned by a member.
     *
     * @param int $memberId
     * @param array $ids
     *
     * @return int
     */
    public static function removeOwned(int $memberId, array $ids): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($memberId <= 0 || empty($ids)) {
            return 0;
        }

        $rows = static::createQuery()
            ->select('id', 'status')
            ->from('reservation')
            ->where(['member_id', $memberId])
            ->where(['id', $ids])
            ->fetchAll();

        $allowedIds = [];
        foreach ($rows as $row) {
            if (Helper::canDeleteBookingByRequester($row)) {
                $allowedIds[] = (int) $row->id;
            }
        }

        if (empty($allowedIds)) {
            return 0;
        }

        $db = \Kotchasan\DB::create();
        $db->delete('reservation_data', ['reservation_id', $allowedIds], 0);

        return (int) $db->delete('reservation', [
            ['id', $allowedIds],
            ['member_id', $memberId]
        ], 0);
    }

    /**
     * Save reservation meta.
     *
     * @param int $id
     * @param array $meta
     *
     * @return void
     */
    public static function saveMeta(int $id, array $meta): void
    {
        $db = \Kotchasan\DB::create();
        $db->delete('reservation_data', ['reservation_id', $id], 0);

        foreach ($meta as $name => $value) {
            if (is_array($value)) {
                $value = implode(',', array_values(array_filter(array_map('strval', $value), static function ($item) {
                    return $item !== '';
                })));
            }
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }

            $db->insert('reservation_data', [
                'reservation_id' => $id,
                'name' => $name,
                'value' => $value
            ]);
        }
    }

    /**
     * Get reservation meta as array.
     *
     * @param int $id
     *
     * @return array
     */
    public static function getMetaValues(int $id): array
    {
        $rows = static::createQuery()
            ->select('name', 'value')
            ->from('reservation_data')
            ->where(['reservation_id', $id])
            ->fetchAll();

        $meta = [];
        foreach ($rows as $row) {
            $meta[$row->name] = $row->value;
        }

        return $meta;
    }

    /**
     * Check for datetime overlap on the same room.
     *
     * @param array $save
     *
     * @return bool
     */
    public static function availability($save): bool
    {
        $current = Helper::buildBookingSchedule(
            $save['begin'] ?? null,
            $save['end'] ?? null,
            $save['schedule_type'] ?? null
        );
        if ($current === null) {
            return false;
        }

        $where = [
            ['room_id', $save['room_id']],
            Sql::create('(`status`='.(int) Helper::STATUS_APPROVED.' OR `approve`>1)'),
            ['begin', '<=', $current['end_date'].' 23:59:59'],
            ['end', '>=', $current['begin_date'].' 00:00:00']
        ];
        if ($save['id'] > 0) {
            $where[] = ['id', '!=', $save['id']];
        }
        $rows = static::createQuery()
            ->select('id', 'begin', 'end', 'schedule_type')
            ->from('reservation')
            ->where($where)
            ->fetchAll();

        foreach ($rows as $row) {
            $existing = Helper::buildBookingSchedule(
                (string) $row->begin,
                (string) $row->end,
                Helper::getScheduleType($row, Helper::SCHEDULE_CONTINUOUS)
            );
            if ($existing !== null && Helper::bookingSchedulesOverlap($current, $existing)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Convert CSV values to array of strings.
     *
     * @param string|null $csv
     *
     * @return array
     */
    public static function csvToArray(?string $csv): array
    {
        if ($csv === null || trim($csv) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $csv)), static function ($value) {
            return $value !== '';
        }));
    }
}