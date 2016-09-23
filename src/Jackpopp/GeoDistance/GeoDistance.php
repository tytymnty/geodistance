<?php 
/**
 * # Geo distance calculate
 * # Example:
 * $geoDistance = new GeoDistance('lat', 'lng');
 * $result = $geoDistance->scopeWithin(50, 'km', 39.9086920000, 116.397477000);
 * print_r($result);
 */
namespace Jackpopp\GeoDistance;

use Jackpopp\GeoDistance\InvalidMeasurementException;

class GeoDistance {

    protected $latColumn = 'lat';

    protected $lngColumn = 'lng';

    protected $distance = 10;

    private static $MEASUREMENTS = [
        'miles'          => 3959,      // 英里
        'm'              => 3959,      // 英里
        'kilometers'     => 6371,      // 千米
        'km'             => 6371,      // 千米
        'meters'         => 6371000,   // 米
        'feet'           => 20902231,  // 英寸
        'nautical_miles' => 3440.06479 // 海里
    ];

    public function __construct($latColumn = 'lat', $lngColumn = 'lng')
    {
        $this->latColumn = $latColumn;
        $this->lngColumn = $lngColumn;
    }

    public function getLatColumn()
    {
        return $this->latColumn;
    }

    public function getLngColumn()
    {
        return $this->lngColumn;
    }

    public function lat($lat = null)
    {
        if ($lat)
        {
            $this->lat = $lat;
            return $this;
        }

        return $this->lat;
    }

    public function lng($lng = null)
    {
        if ($lng)
        {
            $this->lng = $lng;
            return $this;
        }

        return $this->lng;
    }

    /**
    * @param string
    *
    * Grabs the earths mean radius in a specific measurment based on the key provided, throws an exception
    * if no mean readius measurement is found
    * 
    * @throws InvalidMeasurementException
    * @return float
    **/

    public function resolveEarthMeanRadius($measurement = null)
    {
        $measurement = ($measurement === null) ? key(static::$MEASUREMENTS) : strtolower($measurement);

        if (array_key_exists($measurement, static::$MEASUREMENTS))
            return static::$MEASUREMENTS[$measurement];

        throw new InvalidMeasurementException('Invalid measurement');
    }

    /**
    * @param integer
    * @param mixed
    * @param mixed
    *
    * @todo Use pdo paramater bindings, instead of direct variables in query
    * @return Query
    *
    * Implements a distance radius search using Haversine formula.
    * Returns a query scope.
    * credit - https://developers.google.com/maps/articles/phpsqlsearch_v3
    **/

    public function scopeWithin($distance, $measurement = null, $lat = null, $lng = null)
    {

        $latColumn = $this->getLatColumn();
        $lngColumn = $this->getLngColumn();

        $lat = ($lat === null) ? $this->lat() : $lat;
        $lng = ($lng === null) ? $this->lng() : $lng;

        $meanRadius = $this->resolveEarthMeanRadius($measurement);
        $distance = floatval($distance);

        // first-cut bounding box (in degrees)
        $maxLat = floatval($lat) + rad2deg($distance/$meanRadius);
        $minLat = floatval($lat) - rad2deg($distance/$meanRadius);
        // compensate for degrees longitude getting smaller with increasing latitude
        $maxLng = floatval($lng) + rad2deg($distance/$meanRadius/cos(deg2rad(floatval($lat))));
        $minLng = floatval($lng) - rad2deg($distance/$meanRadius/cos(deg2rad(floatval($lat))));

        $column = "( $meanRadius * acos( cos( radians($lat) ) * cos( radians( $latColumn ) ) * cos( radians( $lngColumn ) - radians($lng) ) + sin( radians($lat) ) * sin( radians( $latColumn ) ) ) ) AS geo_distance";

        $latBetween = [$minLat, $maxLat];
        $lngBetween = [$minLng, $maxLng];

        return [
            'column'      => $column,
            'latBetween'  => $latBetween,
            'lngBetween'  => $lngBetween,
            'distance'    => $distance,
            'measurement' => $measurement
        ];

        // return $q->select(DB::raw("*, ( $meanRadius * acos( cos( radians($lat) ) * cos( radians( $latColumn ) ) * cos( radians( $lngColumn ) - radians($lng) ) + sin( radians($lat) ) * sin( radians( $latColumn ) ) ) ) AS distance"))
        //     ->from(DB::raw(
        //         "(
        //             Select *
        //             From {$this->getTable()}
        //             Where $latColumn Between $minLat And $maxLat
        //             And $lngColumn Between $minLng And $maxLng
        //         ) As {$this->getTable()}"
        //     ))
        //     ->having('distance', '<=', $distance)
        //     ->orderby('distance', 'ASC');
    }

    public function scopeOutside($distance, $measurement = null, $lat = null, $lng = null)
    {
        $latColumn = $this->getLatColumn();
        $lngColumn = $this->getLngColumn();

        $lat = ($lat === null) ? $this->lat() : $lat;
        $lng = ($lng === null) ? $this->lng() : $lng;

        $meanRadius = $this->resolveEarthMeanRadius($measurement);
        $distance = floatval($distance);

        $column = "*, ( $meanRadius * acos( cos( radians($lat) ) * cos( radians( $latColumn ) ) * cos( radians( $lngColumn ) - radians($lng) ) + sin( radians($lat) ) * sin( radians( $latColumn ) ) ) ) AS distance";

        return [
            'column'      => $column,
            'distance'    => $distance,
            'measurement' => $measurement
        ];
    }

}
