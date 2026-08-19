<?php
/**
 * @filesource modules/booking/models/catalog.php
 */

namespace Booking\Catalog;

use Booking\Helper\Controller as Helper;

class Model extends \Kotchasan\Model
{
    /**
     * Return a single room for the member-facing catalog.
     *
     * @param int $id
     * @param bool $activeOnly
     *
     * @return object|null
     */
    public static function get(int $id, bool $activeOnly = true)
    {
        if ($id <= 0) {
            return null;
        }

        $query = self::createCatalogQuery($activeOnly)
            ->where(['R.id', $id]);

        $row = $query->first();

        return $row ? self::hydrateItem($row) : null;
    }

    /**
     * Return rooms for the member-facing catalog.
     *
     * @param bool $activeOnly
     *
     * @return array
     */
    public static function getItems(bool $activeOnly = true): array
    {
        $query = self::createCatalogQuery($activeOnly);

        $items = [];
        foreach ($query->fetchAll() as $row) {
            $items[] = self::hydrateItem($row);
        }

        return $items;
    }

    /**
     * Base query for catalog rows.
     *
     * @param bool $activeOnly
     *
     * @return \Kotchasan\QueryBuilder\QueryBuilderInterface
     */
    protected static function createCatalogQuery(bool $activeOnly = true)
    {
        $query = static::createQuery()
            ->select(
                'R.id',
                'R.name',
                'R.color',
                'R.detail',
                'R.is_active',
                'Building.value building',
                'RoomNumber.value room_number',
                'Seats.value seats'
            )
            ->from('rooms R')
            ->join('rooms_meta Building', [['Building.room_id', 'R.id'], ['Building.name', 'building']], 'LEFT')
            ->join('rooms_meta RoomNumber', [['RoomNumber.room_id', 'R.id'], ['RoomNumber.name', 'number']], 'LEFT')
            ->join('rooms_meta Seats', [['Seats.room_id', 'R.id'], ['Seats.name', 'seats']], 'LEFT')
            ->orderBy('R.name');

        if ($activeOnly) {
            $query->where(['R.is_active', 1]);
        }

        return $query;
    }

    /**
     * Add gallery and derived fields to a catalog item.
     *
     * @param object $row
     *
     * @return object
     */
    protected static function hydrateItem($row)
    {
        $gallery = Helper::getRoomGallery((int) $row->id);
        $row->name = (string) ($row->name ?? '');
        $row->detail = (string) ($row->detail ?? '');
        $row->building = (string) ($row->building ?? '');
        $row->room_number = (string) ($row->room_number ?? '');
        $row->seats = (string) ($row->seats ?? '');
        $row->gallery = $gallery;
        $row->gallery_count = count($gallery);
        $row->first_image_url = $gallery[0]['url'] ?? null;
        $row->booking_url = '/booking?room_id='.(int) $row->id;

        return $row;
    }
}