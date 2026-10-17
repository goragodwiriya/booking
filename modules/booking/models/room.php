<?php
/**
 * @filesource modules/booking/models/room.php
 */

namespace Booking\Room;

class Model extends \Kotchasan\Model
{
    /**
     * Get a room for editing.
     *
     * @param int $id
     *
     * @return object|null
     */
    public static function get(int $id)
    {
        $record = (object) [
            'id' => 0,
            'name' => '',
            'color' => '#304FFE',
            'detail' => '',
            'is_active' => 1,
            'building' => '',
            'number' => '',
            'seats' => '',
            'booking' => []
        ];

        if ($id > 0) {
            $room = static::createQuery()
                ->select('id', 'name', 'color', 'detail', 'is_active')
                ->from('rooms')
                ->where(['id', $id])
                ->first();

            if (!$room) {
                return null;
            }

            $record = (object) array_merge((array) $record, (array) $room, self::getMetaValues($id));
            $record->booking = \Download\Index\Controller::getAttachments($id, 'booking', self::$cfg->img_typies);
        }

        return $record;
    }

    /**
     * Save room data.
     *
     * @param int $id
     * @param array $save
     * @param array $meta
     *
     * @return int
     */
    public static function save(int $id, array $save, array $meta): int
    {
        $db = \Kotchasan\DB::create();

        if ($id === 0) {
            $id = (int) $db->insert('rooms', $save);
        } else {
            $db->update('rooms', ['id', $id], $save);
        }

        self::saveMeta($id, $meta);

        return $id;
    }

    /**
     * Delete room records and uploaded images.
     *
     * @param array $ids
     *
     * @return int
     */
    public static function remove(array $ids): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($ids)) {
            return 0;
        }

        $db = \Kotchasan\DB::create();
        $db->delete('rooms_meta', ['room_id', $ids], 0);
        $removed = $db->delete('rooms', ['id', $ids], 0);

        foreach ($ids as $id) {
            \Kotchasan\File::removeDirectory(ROOT_PATH.DATA_FOLDER.'booking/'.$id.'/');
        }

        return (int) $removed;
    }

    /**
     * Toggle active state.
     *
     * @param int $id
     *
     * @return object|null
     */
    public static function toggleActive(int $id)
    {
        $db = \Kotchasan\DB::create();
        $room = $db->first('rooms', ['id', $id]);
        if (!$room) {
            return null;
        }

        $active = (int) $room->is_active === 1 ? 0 : 1;
        $db->update('rooms', ['id', $id], ['is_active' => $active]);
        $room->is_active = $active;

        return $room;
    }

    /**
     * Get raw record by ID.
     *
     * @param int $id
     *
     * @return object|null
     */
    public static function getRecord(int $id)
    {
        return static::createQuery()
            ->select('id', 'name', 'is_active')
            ->from('rooms')
            ->where(['id', $id])
            ->first();
    }

    /**
     * Load room meta into a flat array.
     *
     * @param int $id
     *
     * @return array
     */
    public static function getMetaValues(int $id): array
    {
        $rows = static::createQuery()
            ->select('name', 'value')
            ->from('rooms_meta')
            ->where(['room_id', $id])
            ->fetchAll();

        $meta = [];
        foreach ($rows as $row) {
            $meta[$row->name] = $row->value;
        }

        return $meta;
    }

    /**
     * Save room meta fields.
     *
     * @param int $id
     * @param array $meta
     *
     * @return void
     */
    public static function saveMeta(int $id, array $meta): void
    {
        $db = \Kotchasan\DB::create();
        $db->delete('rooms_meta', ['room_id', $id], 0);

        foreach ($meta as $name => $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }

            $db->insert('rooms_meta', [
                'room_id' => $id,
                'name' => $name,
                'value' => $value
            ]);
        }
    }
}