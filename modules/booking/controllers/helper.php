<?php
/**
 * @filesource modules/booking/controllers/helper.php
 */

namespace Booking\Helper;

use Booking\Category\Controller as BookingCategory;
use Gcms\Api as ApiController;
use Kotchasan\Date;
use Kotchasan\Language;

class Controller extends \Gcms\Controller
{
    public const SCHEDULE_CONTINUOUS = 'continuous';
    public const SCHEDULE_DAILY_SLOT = 'daily-slot';

    public const STATUS_PENDING_REVIEW = 0;
    public const STATUS_APPROVED = 1;
    public const STATUS_REJECTED = 2;
    public const STATUS_CANCELLED_BY_REQUESTER = 3;
    public const STATUS_CANCELLED_BY_OFFICER = 4;
    public const STATUS_RETURNED_FOR_EDIT = 5;

    public const APPROVAL_BEFORE_END = 0;
    public const APPROVAL_BEFORE_START = 1;
    public const APPROVAL_ALWAYS = 2;

    public const CANCELLATION_PENDING_ONLY = 0;
    public const CANCELLATION_BEFORE_DATE = 1;
    public const CANCELLATION_BEFORE_END = 2;
    public const CANCELLATION_BEFORE_START = 3;
    public const CANCELLATION_ALWAYS = 4;

    /**
     * Cached room galleries for the current request.
     *
     * @var array
     */
    protected static $roomGalleryCache = [];

    /**
     * Get active approval steps keyed by step number.
     * `approve_level` is the source of truth for how many steps are enabled.
     *
     * @return array<int, array{status:int, department:string}>
     */
    public static function getApprovalSteps(): array
    {
        $levelCount = max(0, (int) (self::$cfg->booking_approve_level ?? 0));
        if ($levelCount === 0) {
            return [];
        }

        $statuses = (array) (self::$cfg->booking_approve_status ?? []);
        $departments = (array) (self::$cfg->booking_approve_department ?? []);
        $steps = [];
        for ($level = 1; $level <= $levelCount; $level++) {
            if (!array_key_exists($level, $statuses)) {
                break;
            }
            $steps[$level] = [
                'status' => (int) $statuses[$level],
                'department' => isset($departments[$level]) ? (string) $departments[$level] : ''
            ];
        }

        return $steps;
    }

    /**
     * Get the number of active approval steps.
     *
     * @return int
     */
    public static function getApprovalLevelCount(): int
    {
        return count(self::getApprovalSteps());
    }

    /**
     * Get configuration for a single approval step.
     *
     * @param int $step
     *
     * @return array{status:int, department:string}|null
     */
    public static function getApprovalStepConfig(int $step): ?array
    {
        $steps = self::getApprovalSteps();

        return $steps[$step] ?? null;
    }

    /**
     * Get the next configured approval step after the current one.
     *
     * @param int $currentStep
     *
     * @return int
     */
    public static function getNextApprovalStep(int $currentStep): int
    {
        $steps = array_keys(self::getApprovalSteps());
        $index = array_search($currentStep, $steps, true);

        if ($index === false || !isset($steps[$index + 1])) {
            return 0;
        }

        return (int) $steps[$index + 1];
    }

    /**
     * Determine the approver level available to this login.
     * -1 means admin approval access, 0 means no approval access.
     *
     * @param object|null $login
     *
     * @return int
     */
    public static function getApproveLevel($login): int
    {
        if (!$login) {
            return 0;
        }
        $steps = self::getApprovalSteps();
        if (empty($steps)) {
            return 0;
        }
        if (ApiController::isAdmin($login)) {
            return -1;
        }

        $loginDepartment = isset($login->metas['department'][0]) ? trim((string) $login->metas['department'][0]) : '';

        foreach ($steps as $level => $step) {
            if ((int) $step['status'] !== (int) $login->status) {
                continue;
            }
            $department = $step['department'];
            if ($department === '' || $department === $loginDepartment) {
                return (int) $level;
            }
        }

        return 0;
    }

    /**
     * Check if user can approve the current step of a request.
     *
     * @param object|null $login
     * @param object|array $request
     *
     * @return bool
     */
    public static function canApproveStep($login, $request): bool
    {
        if (!$login) {
            return false;
        }
        $steps = self::getApprovalSteps();
        if (empty($steps)) {
            return false;
        }

        if (ApiController::isAdmin($login)) {
            return true;
        }

        $approve = is_array($request) ? ($request['approve'] ?? 1) : ($request->approve ?? 1);
        $department = is_array($request) ? ($request['department'] ?? '') : ($request->department ?? '');
        $step = $steps[$approve] ?? null;
        $loginDepartment = isset($login->metas['department'][0]) ? trim((string) $login->metas['department'][0]) : '';
        if ($step !== null && (int) $login->status === (int) $step['status']) {
            if ($step['department'] === '') {
                return (string) $department === $loginDepartment;
            }

            return $step['department'] === $loginDepartment;
        }

        return false;
    }

    /**
     * Permission helper for approval workflow.
     *
     * @param object|null $login
     *
     * @return bool
     */
    public static function canApproveRequests($login): bool
    {
        return self::getApproveLevel($login) !== 0;
    }

    /**
     * Check whether the login should be allowed into approval pages.
     *
     * Approval-area access should follow the configured step rules: the login
     * must match a configured approver status and, when configured, department.
     *
     * @param object|null $login
     *
     * @return bool
     */
    public static function canAccessApprovalArea($login): bool
    {
        if (!$login) {
            return false;
        }

        $steps = self::getApprovalSteps();
        if (empty($steps)) {
            return false;
        }

        if (ApiController::isAdmin($login)) {
            return true;
        }

        return self::canApproveRequests($login);
    }

    /**
     * Get vehicle gallery images.
     *
     * @param int $vehicleId
     *
     * @return array
     */
    public static function getRoomGallery(int $roomId): array
    {
        if ($roomId <= 0) {
            return [];
        }

        if (!array_key_exists($roomId, self::$roomGalleryCache)) {
            $files = \Download\Index\Controller::getAttachments($roomId, 'booking', self::$cfg->img_typies);
            self::$roomGalleryCache[$roomId] = array_values(array_filter($files, static function ($file) {
                return !empty($file['is_image']);
            }));
        }

        return self::$roomGalleryCache[$roomId];
    }

    /**
     * Get the first vehicle image URL.
     *
     * @param int $vehicleId
     *
     * @return string|null
     */
    public static function getRoomFirstImageUrl(int $roomId): ?string
    {
        $gallery = self::getRoomGallery($roomId);

        return $gallery[0]['url'] ?? null;
    }

    /**
     * Check if the vehicle exists.
     *
     * @param int $vehicleId
     *
     * @return bool
     */
    public static function roomExists(int $roomId): bool
    {
        if ($roomId <= 0) {
            return false;
        }

        return \Kotchasan\Model::createQuery()
            ->select('id')
            ->from('rooms')
            ->where(['id', $roomId])
            ->first() !== null;
    }

    /**
     * Vehicle options.
     *
     * @param bool $activeOnly
     * @param int|null $includeId
     *
     * @return array
     */
    public static function getRoomOptions(bool $activeOnly = true, ?int $includeId = null): array
    {
        $query = \Kotchasan\Model::createQuery()
            ->select('R.id', 'R.name', 'R.is_active')
            ->from('rooms R')
            ->orderBy('R.name');

        if ($activeOnly) {
            if ($includeId !== null && $includeId > 0) {
                $query->where([
                    ['R.is_active', 1],
                    ['R.id', $includeId]
                ], 'OR');
            } else {
                $query->where(['R.is_active', 1]);
            }
        }

        $options = [];
        foreach ($query->fetchAll() as $item) {
            $label = (string) $item->name;
            if ((int) $item->is_active !== 1) {
                $label .= ' ({LNG_Inactive})';
            }
            $options[] = [
                'value' => (string) $item->id,
                'text' => $label
            ];
        }

        return $options;
    }

    /**
     * Driver options from members that have can_drive_car permission.
     *
     * @param int|null $includeId
     *
     * @return array
     */
    public static function getUseOptions(): array
    {
        return BookingCategory::init()->toOptions('use');
    }

    /**
     * Driver options for actual assignment (no placeholder choices).
     *
     * @param int|null $includeId
     *
     * @return array
     */
    public static function getAccessoryOptions(): array
    {
        return BookingCategory::init()->toOptions('accessories');
    }

    /**
     * Status options.
     *
     * @return array
     */
    public static function getStatusOptions(): array
    {
        return \Gcms\Controller::arrayToOptions(Language::get('BOOKING_STATUS'));
    }

    /**
     * Return normalized status key.
     *
     * @param object|null $row
     *
     * @return int
     */
    public static function getStatusValue(?object $row): int
    {
        if ($row === null) {
            return self::STATUS_PENDING_REVIEW;
        }

        $status = (int) self::readField($row, 'status');

        return self::normalizeStatusId($status);
    }

    /**
     * Human readable status text.
     *
     * @param object|null $row
     *
     * @return string
     */
    public static function getStatusText(?object $row): string
    {
        return self::getStatusLabel($row->status);
    }

    /**
     * Human readable status label.
     *
     * @param int $status
     *
     * @return string
     */
    public static function getStatusLabel(int $status): string
    {
        return Language::get('BOOKING_STATUS', '-', $status);
    }

    /**
     * Normalize a status value to a supported booking status.
     *
     * @param int $status
     * @param int|null $default
     *
     * @return int
     */
    public static function normalizeStatusId(int $status, ?int $default = null): int
    {
        $booking_status = Language::get('BOOKING_STATUS');
        if (isset($booking_status[$status])) {
            return $status;
        }

        return $default ?? self::STATUS_PENDING_REVIEW;
    }

    /**
     * Configured booking statuses that bookers may permanently delete.
     *
     * @return array
     */
    public static function getBookerDeleteStatuses(): array
    {
        $statuses = self::$cfg->booking_delete ?? [self::STATUS_CANCELLED_BY_REQUESTER];
        if (!is_array($statuses)) {
            $statuses = [$statuses];
        }

        $statuses = array_values(array_unique(array_filter(array_map(static function ($status) {
            return Controller::normalizeStatusId((int) $status, -1);
        }, $statuses), static function ($status) {
            return $status >= 0;
        })));

        return empty($statuses) ? [self::STATUS_CANCELLED_BY_REQUESTER] : $statuses;
    }

    /**
     * Can the requester edit this booking?
     *
     * @param object|null $row
     *
     * @return bool
     */
    public static function canEditBooking(?object $row): bool
    {
        if ($row === null) {
            return false;
        }

        return in_array(self::getStatusValue($row), [self::STATUS_PENDING_REVIEW, self::STATUS_RETURNED_FOR_EDIT], true);
    }

    /**
     * Can staff still process this booking?
     *
     * @param object|null $row
     *
     * @return bool
     */
    public static function canProcessBooking(?object $row): bool
    {
        if ($row === null) {
            return false;
        }

        return self::getStatusValue($row) === self::STATUS_PENDING_REVIEW
        && self::isWithinApprovalWindow($row);
    }

    /**
     * Can the requester cancel this booking?
     *
     * @param object|null $row
     *
     * @return bool
     */
    public static function canCancelBookingByRequester(?object $row): bool
    {
        if ($row === null) {
            return false;
        }

        if (!in_array($row->status, [
            self::STATUS_PENDING_REVIEW,
            self::STATUS_APPROVED,
            self::STATUS_RETURNED_FOR_EDIT
        ], true)) {
            return false;
        }

        switch ((int) (self::$cfg->booking_cancellation ?? self::CANCELLATION_PENDING_ONLY)) {
        case self::CANCELLATION_BEFORE_DATE:
            // ก่อนวันจอง
            return self::isBeforeBoundaryDate($row, 'begin');
        case self::CANCELLATION_BEFORE_END:
            // ก่อนสิ้นสุดเวลาจอง
            return self::isBeforeBoundary($row, 'end');
        case self::CANCELLATION_BEFORE_START:
            // ก่อนถึงเวลาจอง
            return self::isBeforeBoundary($row, 'begin');
        case self::CANCELLATION_ALWAYS:
            // ยกเลิกย้อนหลังได้
            return true;
        }

        // สถานะรอตรวจสอบ หรือ กลับไปแก้ไข
        return in_array($row->status, [self::STATUS_PENDING_REVIEW, self::STATUS_RETURNED_FOR_EDIT], true);
    }

    /**
     * Can the requester delete this booking permanently?
     *
     * @param object|null $row
     *
     * @return bool
     */
    public static function canDeleteBookingByRequester(?object $row): bool
    {
        if ($row === null) {
            return false;
        }

        return in_array($row->status, self::getBookerDeleteStatuses(), true);
    }

    /**
     * Can an officer cancel this booking?
     *
     * @param object|null $row
     *
     * @return bool
     */
    public static function canCancelBookingByOfficer(?object $row): bool
    {
        if ($row === null) {
            return false;
        }

        $status = self::getStatusValue($row);
        if (!in_array($status, [
            self::STATUS_PENDING_REVIEW,
            self::STATUS_APPROVED,
            self::STATUS_RETURNED_FOR_EDIT
        ], true)) {
            return false;
        }

        return self::isWithinApprovalWindow($row);
    }

    /**
     * Check approval/edit timing policy.
     *
     * @param object|null $row
     *
     * @return bool
     */
    public static function isWithinApprovalWindow(?object $row): bool
    {
        if ($row === null) {
            return false;
        }

        switch ((int) (self::$cfg->booking_approving ?? self::APPROVAL_BEFORE_END)) {
        case self::APPROVAL_BEFORE_START:
            return self::isBeforeBoundary($row, 'begin');
        case self::APPROVAL_ALWAYS:
            return true;
        default:
            return self::isBeforeBoundary($row, 'end');
        }
    }

    /**
     * Format accessory CSV into labels.
     *
     * @param string|null $csv
     *
     * @return string
     */
    public static function formatAccessoryNames(?string $csv): string
    {
        if ($csv === null || trim($csv) === '') {
            return '';
        }

        $category = BookingCategory::init();
        $ids = array_values(array_filter(array_map('trim', explode(',', $csv)), static function ($value) {
            return $value !== '';
        }));
        $labels = [];
        foreach ($ids as $id) {
            $label = $category->get('accessories', $id);
            if ($label !== '') {
                $labels[] = $label;
            }
        }

        return implode(', ', $labels);
    }

    /**
     * Use category label.
     *
     * @param string|null $useId
     *
     * @return string
     */
    public static function getUseLabel(?string $useId): string
    {
        if ($useId === null || trim($useId) === '') {
            return '';
        }

        return (string) BookingCategory::init()->get('use', $useId);
    }

    /**
     * Department name helper.
     *
     * @param string|null $departmentId
     *
     * @return string
     */
    public static function getDepartmentName(?string $departmentId): string
    {
        if ($departmentId === null || $departmentId === '') {
            return '';
        }

        return (string) \Gcms\Category::init()->get('department', $departmentId);
    }

    /**
     * Check that the current time is before a booking boundary.
     *
     * @param object|null $row
     * @param string $field
     *
     * @return bool
     */
    public static function isBeforeBoundary(?object $row, string $field): bool
    {
        if ($row === null) {
            return false;
        }

        $value = self::readField($row, $field);
        if (empty($value)) {
            return true;
        }

        $timestamp = strtotime((string) $value);
        if ($timestamp === false) {
            return true;
        }

        return time() < $timestamp;
    }

    /**
     * Check that the current date is before a booking boundary date.
     *
     * @param object|null $row
     * @param string $field
     *
     * @return bool
     */
    public static function isBeforeBoundaryDate(?object $row, string $field): bool
    {
        if ($row === null) {
            return false;
        }

        $value = self::readField($row, $field);
        if (empty($value)) {
            return true;
        }

        $timestamp = strtotime((string) $value);
        if ($timestamp === false) {
            return true;
        }

        return date('Y-m-d') < date('Y-m-d', $timestamp);
    }

    /**
     * Normalize booking schedule type.
     *
     * @param string|null $scheduleType
     * @param string|null $default
     *
     * @return string
     */
    public static function normalizeScheduleType(?string $scheduleType, ?string $default = null): string
    {
        $scheduleType = trim((string) $scheduleType);
        if (in_array($scheduleType, [self::SCHEDULE_CONTINUOUS, self::SCHEDULE_DAILY_SLOT], true)) {
            return $scheduleType;
        }

        return $default ?? self::SCHEDULE_DAILY_SLOT;
    }

    /**
     * Resolve booking schedule type from row-like data.
     *
     * @param object|array|string|null $row
     * @param string|null $default
     *
     * @return string
     */
    public static function getScheduleType($row, ?string $default = null): string
    {
        if (is_array($row)) {
            $scheduleType = $row['schedule_type'] ?? null;
        } elseif (is_object($row)) {
            $scheduleType = $row->schedule_type ?? null;
        } else {
            $scheduleType = $row;
        }

        return self::normalizeScheduleType(
            is_string($scheduleType) ? $scheduleType : null,
            $default ?? self::SCHEDULE_CONTINUOUS
        );
    }

    /**
     * Build normalized booking schedule data from stored begin/end values.
     *
     * @param string|null $begin
     * @param string|null $end
     * @param string|null $scheduleType
     *
     * @return array|null
     */
    public static function buildBookingSchedule(?string $begin, ?string $end, ?string $scheduleType = null): ?array
    {
        if ($begin === null || $end === null || trim($begin) === '' || trim($end) === '') {
            return null;
        }

        $beginTs = strtotime($begin);
        $endTs = strtotime($end);
        if ($beginTs === false || $endTs === false || $endTs <= $beginTs) {
            return null;
        }

        return [
            'schedule_type' => self::normalizeScheduleType($scheduleType, self::SCHEDULE_CONTINUOUS),
            'begin_ts' => $beginTs,
            'end_ts' => $endTs,
            'begin_date' => date('Y-m-d', $beginTs),
            'end_date' => date('Y-m-d', $endTs),
            'begin_time' => date('H:i:s', $beginTs),
            'end_time' => date('H:i:s', $endTs)
        ];
    }

    /**
     * Get the effective time interval for a schedule on a specific date.
     *
     * @param array $schedule
     * @param string $date
     *
     * @return array|null
     */
    public static function getBookingIntervalForDate(array $schedule, string $date): ?array
    {
        if ($date < $schedule['begin_date'] || $date > $schedule['end_date']) {
            return null;
        }

        if ($schedule['schedule_type'] === self::SCHEDULE_DAILY_SLOT) {
            return [
                'start' => self::timeToSeconds($schedule['begin_time']),
                'end' => self::timeToSeconds($schedule['end_time'])
            ];
        }

        if ($schedule['begin_date'] === $schedule['end_date']) {
            return [
                'start' => self::timeToSeconds($schedule['begin_time']),
                'end' => self::timeToSeconds($schedule['end_time'])
            ];
        }

        if ($date === $schedule['begin_date']) {
            return [
                'start' => self::timeToSeconds($schedule['begin_time']),
                'end' => 86400
            ];
        }
        if ($date === $schedule['end_date']) {
            return [
                'start' => 0,
                'end' => self::timeToSeconds($schedule['end_time'])
            ];
        }

        return [
            'start' => 0,
            'end' => 86400
        ];
    }

    /**
     * Determine whether two booking schedules overlap.
     *
     * @param array $left
     * @param array $right
     *
     * @return bool
     */
    public static function bookingSchedulesOverlap(array $left, array $right): bool
    {
        $cursor = strtotime(max($left['begin_date'], $right['begin_date']));
        $last = strtotime(min($left['end_date'], $right['end_date']));
        if ($cursor === false || $last === false || $cursor > $last) {
            return false;
        }
        while ($cursor <= $last) {
            $date = date('Y-m-d', $cursor);
            $leftInterval = self::getBookingIntervalForDate($left, $date);
            $rightInterval = self::getBookingIntervalForDate($right, $date);

            if (
                $leftInterval !== null &&
                $rightInterval !== null &&
                $leftInterval['start'] < $rightInterval['end'] &&
                $leftInterval['end'] > $rightInterval['start']
            ) {
                return true;
            }

            $next = strtotime('+1 day', $cursor);
            if ($next === false) {
                break;
            }
            $cursor = $next;
        }

        return false;
    }

    /**
     * Read object field.
     *
     * @param object|null $row
     * @param string $field
     *
     * @return mixed|null
     */
    protected static function readField(?object $row, string $field)
    {
        if ($row === null) {
            return null;
        }

        return $row->$field ?? null;
    }

    /**
     * คืนค่าเวลาจอง
     *
     * @param object $item
     * @param bool $omitDateOnSameDay
     *
     * @return string
     */
    public static function formatBookingTime(object $item, bool $omitDateOnSameDay = false): string
    {
        $schedule = self::buildBookingSchedule(
            $item->begin ?? null,
            $item->end ?? null,
            self::getScheduleType($item, self::SCHEDULE_CONTINUOUS)
        );
        if ($schedule === null) {
            return '';
        }

        if (
            $schedule['schedule_type'] === self::SCHEDULE_DAILY_SLOT &&
            $schedule['begin_date'] !== $schedule['end_date']
        ) {
            $timeText = Date::format($item->begin, 'H:i').' {LNG_To} '.Date::format($item->end, 'TIME_FORMAT');
            $dateText = Date::format($item->begin, 'd M Y').' {LNG_To} '.Date::format($item->end, 'd M Y');

            return Language::trans('{LNG_Every day} {LNG_Time} '.$timeText.' ('.$dateText.')');
        }

        if ($schedule['begin_date'] === $schedule['end_date']) {
            if ($omitDateOnSameDay) {
                $return = Date::format($item->begin, 'H:i').' {LNG_To} '.Date::format($item->end, 'TIME_FORMAT');
            } else {
                $return = Date::format($item->begin, 'DATE_FORMAT').' {LNG_To} '.Date::format($item->end, 'TIME_FORMAT');
            }
        } else {
            $return = Date::format($item->begin).' {LNG_To} '.Date::format($item->end);
        }

        return Language::trans($return);
    }

    /**
     * Convert HH:mm:ss to seconds from start of day.
     *
     * @param string $value
     *
     * @return int
     */
    protected static function timeToSeconds(string $value): int
    {
        [$hour, $minute, $second] = array_pad(array_map('intval', explode(':', $value)), 3, 0);

        return ($hour * 3600) + ($minute * 60) + $second;
    }
}
