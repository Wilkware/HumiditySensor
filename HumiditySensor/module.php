<?php

declare(strict_types=1);

/** Generell funktions */
require_once __DIR__ . '/../libs/_traits.php';

/** Namespaced traits */
use Wilkware\HumiditySensor\DebugHelper;
use Wilkware\HumiditySensor\FormHelper;
use Wilkware\HumiditySensor\VariableHelper;
use Wilkware\HumiditySensor\VersionHelper;

/**
 * Class HumiditySensor
 */
class HumiditySensor extends IPSModuleStrict
{
    // -------------------------------------------------------------------------
    // Traits
    // -------------------------------------------------------------------------

    use DebugHelper;
    use FormHelper;
    use VariableHelper;
    use VersionHelper;

    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    /** @var int Min IPS Object ID */
    private const IPS_MIN_ID = 10000;

    /** @var int Status code for missing climate variables */
    private const STATUS_MISSING_VARIABLES = 201;

    /** @var int Status code for implausible humidity values */
    private const STATUS_INVALID_HUMIDITY = 202;

    /** @var list<string> Properties holding the climate variables */
    private const CLIMATE_VARIABLES = [
        'TempOutdoor',
        'HumyOutdoor',
        'TempIndoor',
        'HumyIndoor',
    ];

    /** @var int Update timer value for an event-driven update on changes of the climate variables */
    private const UPDATE_ON_CHANGE = 0;

    /** @var int Delay in seconds after a change to bundle values sent together (e.g. temperature and humidity of one device) */
    private const UPDATE_DEBOUNCE = 2;

    /** @var int Cyclic update in minutes in addition to the event-driven update (season, sensors without changes) */
    private const UPDATE_HEARTBEAT = 60;

    /** @var int Lifetime of the cached history in seconds */
    private const HISTORY_CACHE_TIME = 900;

    /** @var int Season mode: notifications all year round */
    private const SEASON_ALWAYS = 0;

    /** @var int Season mode: heating period determined by the smoothed outdoor temperature */
    private const SEASON_AUTOMATIC = 1;

    /** @var int Season mode: heating period determined by calendar months */
    private const SEASON_CALENDAR = 2;

    /** @var int Hint mode: ventilation dries the room air (climate data only) */
    private const HINT_CLIMATE_DATA = 0;

    /** @var int Hint mode: ventilation required according to the complete ventilation logic (like the notification) */
    private const HINT_VENTILATION_LOGIC = 1;

    /** @var int Clothing mode: fixed clothing insulation */
    private const CLOTHING_FIXED = 0;

    /** @var int Clothing mode: summer/winter clothing switched with the heating period */
    private const CLOTHING_AUTOMATIC = 1;

    /** @var int Thermal state: PMV below the negative limit */
    private const THERMAL_COOL = -1;

    /** @var int Thermal state: PMV within the limits */
    private const THERMAL_COMFORTABLE = 0;

    /** @var int Thermal state: PMV above the positive limit */
    private const THERMAL_WARM = 1;

    /** @var int Time constant for smoothing the outdoor temperature in seconds (48 h, like heating controllers) */
    private const SEASON_SMOOTHING = 172800;

    /** @var float Hysteresis around the heating limit in K (half of the switching difference) */
    private const SEASON_HYSTERESIS = 1.0;

    /**
     * @var array<int,array{0:string,1:string,2:string,3:int,4:int}> Typical room climate (name, temperature, humidity, upper and lower humidity limit)
     * Source: https://www.getair.eu/wissen/optimale-luftfeuchtigkeit-so-schuetzen-sie-gesundheit-und-gebaeude/
     */
    private const ROOM_TYPES = [
        1 => ['Basement', '10 - 15 °C', '50 - 65 %', 65, 50],
        2 => ['Hallway', '15 - 18 °C', '40 - 60 %', 60, 40],
        3 => ['Kitchen', '18 - 20 °C', '50 - 60 %', 60, 50],
        4 => ['Bathroom', '20 - 23 °C', '50 - 70 %', 70, 50],
        5 => ['Children\'s room', '20 - 23 °C', '40 - 60 %', 60, 40],
        6 => ['Living room', '20 - 23 °C', '40 - 60 %', 60, 40],
        7 => ['Study', '20 - 23 °C', '40 - 60 %', 60, 40],
        8 => ['Bedroom', '17 - 20 °C', '40 - 60 %', 60, 40],
    ];

    /** @var list<float> Lower limits of the heat index warning levels 1..4 in °C (caution, extreme caution, danger, extreme danger) */
    private const HEAT_LEVELS = [27.0, 32.0, 41.0, 54.0];

    /**
     * @var list<string> Comfort index table of the ELV comfort display (rows: 5.0 to 35.9 °C in 0.5 K steps,
     * columns: 25 to 75 % relative humidity in 1 % steps; 1 = dry, 2 = comfort dry, 3 = comfort ideal, 4 = comfort humid, 5 = humid)
     */
    private const COMFORT_TABLE = [
        '111111111111111111222222222222222222223333344444555', //  5.0 -  5.4 °C
        '111111111111111111222222222222222222223333344444555', //  5.5 -  5.9 °C
        '111111111111111112222222222222222222233333344444555', //  6.0 -  6.4 °C
        '111111111111111112222222222222222222233333344444555', //  6.5 -  6.9 °C
        '111111111111111122222222222222222222333333344444555', //  7.0 -  7.4 °C
        '111111111111111122222222222222222222333333344444555', //  7.5 -  7.9 °C
        '111111111111111222222222233333333333333333344444555', //  8.0 -  8.4 °C
        '111111111111111222222222233333333333333333344444555', //  8.5 -  8.9 °C
        '111111111111112222222222333333333333333333344444555', //  9.0 -  9.4 °C
        '111111111111112222222222333333333333333333344444555', //  9.5 -  9.9 °C
        '111111111111122222222223333333333333333333344444555', // 10.0 - 10.4 °C
        '111111111111122222222223333333333333333333344444555', // 10.5 - 10.9 °C
        '111111111111222222222233333333333333333333344444555', // 11.0 - 11.4 °C
        '111111111111222222222233333333333333333333344444555', // 11.5 - 11.9 °C
        '111111111112222222222333333333333333333333344444555', // 12.0 - 12.4 °C
        '111111111112222222222333333333333333333333344444555', // 12.5 - 12.9 °C
        '111111111122222222223333333333333333333333344444555', // 13.0 - 13.4 °C
        '111111111122222222223333333333333333333333344444555', // 13.5 - 13.9 °C
        '111111111222222222233333333333333333333333344444555', // 14.0 - 14.4 °C
        '111111111222222222233333333333333333333333344444555', // 14.5 - 14.9 °C
        '111111112222222222333333333333333333333333344444555', // 15.0 - 15.4 °C
        '111111112222222222333333333333333333333333444445555', // 15.5 - 15.9 °C
        '111111112222222223333333333333333333333333444445555', // 16.0 - 16.4 °C
        '111111112222222223333333333333333333333334444455555', // 16.5 - 16.9 °C
        '111111112222222233333333333333333333333334444455555', // 17.0 - 17.4 °C
        '111111112222222233333333333333333334444444444555555', // 17.5 - 17.9 °C
        '111111112222222333333333333333333334444444444555555', // 18.0 - 18.4 °C
        '111111112222222333333333333333333334444444445555555', // 18.5 - 18.9 °C
        '111111112222223333333333333333333344444444445555555', // 19.0 - 19.4 °C
        '111111112222223333333333333333333344444444455555555', // 19.5 - 19.9 °C
        '111111112222233333333333333333333444444444455555555', // 20.0 - 20.4 °C
        '111111112222233333333333333333333444444444555555555', // 20.5 - 20.9 °C
        '111111122222233333333333333333334444444444555555555', // 21.0 - 21.4 °C
        '111111122222233333333333333333334444444445555555555', // 21.5 - 21.9 °C
        '111111222222233333333333333333344444444445555555555', // 22.0 - 22.4 °C
        '111111222222233333333333333333444445555555555555555', // 22.5 - 22.9 °C
        '111112222222233333333333344444444455555555555555555', // 23.0 - 23.4 °C
        '111112222222233333333333344444444455555555555555555', // 23.5 - 23.9 °C
        '111122222222233333333333444444444555555555555555555', // 24.0 - 24.4 °C
        '111122222222233333333333444444444555555555555555555', // 24.5 - 24.9 °C
        '111222222222333333333334444444445555555555555555555', // 25.0 - 25.4 °C
        '111222222222333333333334444444445555555555555555555', // 25.5 - 25.9 °C
        '111222222223333333333344444444455555555555555555555', // 26.0 - 26.4 °C
        '111222222223333333333344444444455555555555555555555', // 26.5 - 26.9 °C
        '111222222233333333333444444444555555555555555555555', // 27.0 - 27.4 °C
        '111222222233333333333444444444555555555555555555555', // 27.5 - 27.9 °C
        '111222222233333333334444444445555555555555555555555', // 28.0 - 28.4 °C
        '111222222333333333334444444445555555555555555555555', // 28.5 - 28.9 °C
        '111222222333333333344444444455555555555555555555555', // 29.0 - 29.4 °C
        '111222222333333333344444444455555555555555555555555', // 29.5 - 29.9 °C
        '111222223333333333444444444555555555555555555555555', // 30.0 - 30.4 °C
        '111222223333333333444444444555555555555555555555555', // 30.5 - 30.9 °C
        '111222223333333334444444445555555555555555555555555', // 31.0 - 31.4 °C
        '111222223333333334444444445555555555555555555555555', // 31.5 - 31.9 °C
        '111222223333333344444444455555555555555555555555555', // 32.0 - 32.4 °C
        '111222223333333344444444455555555555555555555555555', // 32.5 - 32.9 °C
        '111222223333333444444444555555555555555555555555555', // 33.0 - 33.4 °C
        '111222223333333444444444555555555555555555555555555', // 33.5 - 33.9 °C
        '111222223333334444444445555555555555555555555555555', // 34.0 - 34.4 °C
        '111222223333334444444445555555555555555555555555555', // 34.5 - 34.9 °C
        '111222223333344444444455555555555555555555555555555', // 35.0 - 35.4 °C
        '111222223333344444444455555555555555555555555555555', // 35.5 - 35.9 °C
    ];

    /** @var string GUID of the archive control module */
    private const ARCHIVE_MODULE = '{43192F0B-135B-4CE7-A0A7-1475603F3060}';

    /** @var array<string,bool> Tile elements in default order with their default visibility */
    private const TILE_ELEMENTS = [
        'Result'         => false,
        'Outdoor'        => true,
        'ComfortIndex'   => true,
        'ThermalComfort' => true,
        'HeatIndex'      => true,
        'Difference'     => true,
        'DewPoint'       => false,
        'WaterContent'   => false,
        'HeatingPeriod'  => false,
    ];

    /** @var array<string,array{0:string,1:string}> Caption and description of the tile elements in the configuration form */
    private const TILE_CAPTIONS = [
        'Result'         => ['Recommendation text', 'Full recommendation text (variable Result) below the indoor values'],
        'Outdoor'        => ['Outdoor values', 'Outdoor humidity and temperature in the line below the indoor values'],
        'ComfortIndex'   => ['Room climate index', 'Color scale with marker for the room climate (ELV comfort display)'],
        'ThermalComfort' => ['Thermal comfort', 'Color scale with marker for the thermal sensation (PMV) and the dissatisfied (PPD)'],
        'HeatIndex'      => ['Heat index', 'Color scale with marker for the heat index, only shown from the warning level \'Caution\''],
        'Difference'     => ['Difference', 'Difference of the water content indoors to outdoors on the right in the line of the outdoor values'],
        'DewPoint'       => ['Dew point indoor', 'Chip with the dew point of the room air'],
        'WaterContent'   => ['Water content indoor', 'Chip with the water content of the room air'],
        'HeatingPeriod'  => ['Season', 'Chip with the season mode (text) and the current heating period (icon)'],
    ];

    /** @var list<int> Tile scale colors of the room climate index 1..5 (as in the presentation) */
    private const TILE_COLORS_COMFORT = [16711680, 16750848, 65280, 11579392, 255];

    /** @var list<int> Tile scale colors of the thermal sensation from cold to hot (as in the presentation) */
    private const TILE_COLORS_PMV = [128, 255, 32960, 5810438, 16776960, 16744448, 16711680];

    /** @var list<int> Tile scale colors of the heat warning levels 0..4, -1 = neutral (as in the presentation) */
    private const TILE_COLORS_HEAT = [-1, 16776961, 16699191, 16685312, 16646144];

    /** @var list<float> Heat index in °C at the borders of the tile scale segments */
    private const TILE_HEAT_SCALE = [20.0, 27.0, 32.0, 41.0, 54.0, 60.0];

    /** @var int Recommendation: roughly equal humidity indoors and outdoors */
    private const RECOMMENDATION_NO_EFFECT = 0;

    /** @var int Recommendation: ventilation dries the room air */
    private const RECOMMENDATION_VENTILATE = 1;

    /** @var int Recommendation: ventilation dries the room air, but it gets warmer */
    private const RECOMMENDATION_WARMER = 2;

    /** @var int Recommendation: short ventilation possible */
    private const RECOMMENDATION_SHORT = 3;

    /** @var int Recommendation: very short ventilation possible */
    private const RECOMMENDATION_VERY_SHORT = 4;

    /** @var int Recommendation: do not ventilate */
    private const RECOMMENDATION_NONE = 5;

    /** @var array<int,array{0:string,1:int}> Tile badge per recommendation (caption, color) */
    private const RECOMMENDATION_BADGES = [
        self::RECOMMENDATION_NO_EFFECT  => ['No effect', 9342611],
        self::RECOMMENDATION_VENTILATE  => ['Ventilate', 15948332],
        self::RECOMMENDATION_WARMER     => ['Ventilate (warmer)', 15948332],
        self::RECOMMENDATION_SHORT      => ['Ventilate briefly', 15050250],
        self::RECOMMENDATION_VERY_SHORT => ['Ventilate very briefly', 15050250],
        self::RECOMMENDATION_NONE       => ['Don\'t ventilate', 5810438],
    ];

    /** @var float Universal gas constant in J/(kmol*K) */
    private const GAS_CONSTANT = 8314.3;

    /** @var float Molar mass of water vapour in kg/kmol */
    private const MOLAR_MASS_WATER = 18.016;

    /** @var float Saturation vapour pressure at 0 °C in hPa (Magnus formula) */
    private const MAGNUS_BASE = 6.1078;

    /** @var float Water content difference (g/m³) below which indoor and outdoor air count as equal */
    private const EQUAL_WATER_CONTENT = 0.5;

    /** @var float Increase of the relative humidity (%) up to which ventilation makes it only "a little" more humid */
    private const HUMIDITY_INCREASE_LITTLE = 5.0;

    /** @var float Relative humidity (%) after ventilation up to which short ventilation is fine */
    private const HUMIDITY_COMFORT = 55.0;

    /** @var float Relative humidity (%) after ventilation up to which very short ventilation is acceptable */
    private const HUMIDITY_ACCEPTABLE = 65.0;

    /** @var float Share of the indoor air that remains in the room during ventilation */
    private const AIR_EXCHANGE_REMAINING = 0.25;

    // -------------------------------------------------------------------------
    // Presentations
    // -------------------------------------------------------------------------

    /** @var array<string,mixed> Ventilation hint presentation (Value) */
    private const THS_PRESENTATION_HINT = [
        'ICON'         => 'window-frame',
        'OPTIONS'      => '[{"Caption":"Don\'t ventilate!","ColorActive":true,"ColorValue":5810438,"ContentColorActive":false,"ContentColorValue":-1,"IconActive":true,"IconValue":"window-frame","Value":false},{"Caption":"Ventilate!","ColorActive":true,"ColorValue":15948332,"ContentColorActive":false,"ContentColorValue":-1,"IconActive":true,"IconValue":"window-frame-open","Value":true}]',
        'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
    ];

    /** @var array<string,mixed> Ventilation recommendation presentation (Value), same levels as the tile badge */
    private const THS_PRESENTATION_RECOMMENDATION = [
        'ICON'             => 'window-frame',
        'INTERVALS'        => '[{"ColorActive":true,"ColorValue":9342611,"ConstantActive":true,"ConstantValue":"No effect","ContentColorActive":false,"ContentColorValue":-1,"ConversionFactor":1,"DigitsActive":false,"DigitsValue":0,"IconActive":true,"IconValue":"equals","IntervalMaxValue":0,"IntervalMinValue":0,"PrefixActive":false,"PrefixValue":"","SuffixActive":false,"SuffixValue":""},{"ColorActive":true,"ColorValue":15948332,"ConstantActive":true,"ConstantValue":"Ventilate","ContentColorActive":false,"ContentColorValue":-1,"ConversionFactor":1,"DigitsActive":false,"DigitsValue":0,"IconActive":true,"IconValue":"window-frame-open","IntervalMaxValue":1,"IntervalMinValue":1,"PrefixActive":false,"PrefixValue":"","SuffixActive":false,"SuffixValue":""},{"ColorActive":true,"ColorValue":15948332,"ConstantActive":true,"ConstantValue":"Ventilate (warmer)","ContentColorActive":false,"ContentColorValue":-1,"ConversionFactor":1,"DigitsActive":false,"DigitsValue":0,"IconActive":true,"IconValue":"temperature-arrow-up","IntervalMaxValue":2,"IntervalMinValue":2,"PrefixActive":false,"PrefixValue":"","SuffixActive":false,"SuffixValue":""},{"ColorActive":true,"ColorValue":15050250,"ConstantActive":true,"ConstantValue":"Ventilate briefly","ContentColorActive":false,"ContentColorValue":-1,"ConversionFactor":1,"DigitsActive":false,"DigitsValue":0,"IconActive":true,"IconValue":"clock","IntervalMaxValue":3,"IntervalMinValue":3,"PrefixActive":false,"PrefixValue":"","SuffixActive":false,"SuffixValue":""},{"ColorActive":true,"ColorValue":15050250,"ConstantActive":true,"ConstantValue":"Ventilate very briefly","ContentColorActive":false,"ContentColorValue":-1,"ConversionFactor":1,"DigitsActive":false,"DigitsValue":0,"IconActive":true,"IconValue":"stopwatch","IntervalMaxValue":4,"IntervalMinValue":4,"PrefixActive":false,"PrefixValue":"","SuffixActive":false,"SuffixValue":""},{"ColorActive":true,"ColorValue":5810438,"ConstantActive":true,"ConstantValue":"Don\'t ventilate","ContentColorActive":false,"ContentColorValue":-1,"ConversionFactor":1,"DigitsActive":false,"DigitsValue":0,"IconActive":true,"IconValue":"window-frame","IntervalMaxValue":5,"IntervalMinValue":5,"PrefixActive":false,"PrefixValue":"","SuffixActive":false,"SuffixValue":""}]',
        'INTERVALS_ACTIVE' => true,
        'PRESENTATION'     => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
    ];

    /** @var array<string,mixed> Result text presentation (Value) */
    private const THS_PRESENTATION_RESULT = [
        'ICON'         => 'message-dots',
        'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
    ];

    /** @var array<string,mixed> Difference between indoor and outdoor water content presentation (Value) */
    private const THS_PRESENTATION_DIFFERENCE = [
        'DIGITS'           => 2,
        'ICON'             => 'plus-minus',
        'INTERVALS'        => '[{"ColorActive":true,"ColorValue":5810438,"ConstantActive":false,"ConstantValue":"","ContentColorActive":false,"ContentColorValue":-1,"ConversionFactor":1,"DigitsActive":false,"DigitsValue":2,"IconActive":true,"IconValue":"window-frame","IntervalMaxValue":0,"IntervalMinValue":-100,"PrefixActive":false,"PrefixValue":"","SuffixActive":false,"SuffixValue":""},{"ColorActive":true,"ColorValue":15948332,"ConstantActive":false,"ConstantValue":"","ContentColorActive":false,"ContentColorValue":-1,"ConversionFactor":1,"DigitsActive":false,"DigitsValue":2,"IconActive":true,"IconValue":"window-frame-open","IntervalMaxValue":100000,"IntervalMinValue":0,"PrefixActive":true,"PrefixValue":"+","SuffixActive":false,"SuffixValue":""}]',
        'INTERVALS_ACTIVE' => true,
        'PRESENTATION'     => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
        'SUFFIX'           => ' %',
    ];

    /** @var array<string,mixed> Dew point presentation (Value) */
    private const THS_PRESENTATION_DEW_POINT = [
        'DIGITS'       => 1,
        'ICON'         => 'temperature-full',
        'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
        'SUFFIX'       => ' °C',
        'USAGE_TYPE'   => 1,
    ];

    /** @var array<string,mixed> Temperature presentation of the measured values (Value) */
    private const THS_PRESENTATION_TEMPERATURE = [
        'DIGITS'       => 1,
        'ICON'         => 'temperature-half',
        'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
        'SUFFIX'       => ' °C',
        'USAGE_TYPE'   => 1,
    ];

    /** @var array<string,mixed> Relative humidity presentation of the measured values (Value) */
    private const THS_PRESENTATION_HUMIDITY = [
        'DIGITS'       => 0,
        'ICON'         => 'droplet',
        'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
        'SUFFIX'       => ' %',
    ];

    /** @var array<string,mixed> Water content presentation (Value) */
    private const THS_PRESENTATION_WATER_CONTENT = [
        'COLOR'        => 8421504,
        'DIGITS'       => 2,
        'ICON'         => 'raindrops',
        'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
        'SUFFIX'       => ' g/m³',
    ];

    /** @var array<string,mixed> Heat index presentation (Value) */
    private const THS_PRESENTATION_HEAT_INDEX = [
        'DIGITS'           => 1,
        'ICON'             => 'temperature-high',
        'INTERVALS'        => '[{"ColorActive":false,"ColorValue":-1,"ConstantActive":false,"ConstantValue":"","ContentColorActive":false,"ContentColorValue":-1,"ConversionFactor":1,"DigitsActive":false,"DigitsValue":1,"IconActive":true,"IconValue":"temperature-half","IntervalMaxValue":26.9,"IntervalMinValue":-100,"PrefixActive":false,"PrefixValue":"","SuffixActive":false,"SuffixValue":""},{"ColorActive":true,"ColorValue":16776961,"ConstantActive":false,"ConstantValue":"","ContentColorActive":false,"ContentColorValue":-1,"ConversionFactor":1,"DigitsActive":false,"DigitsValue":1,"IconActive":true,"IconValue":"temperature-three-quarters","IntervalMaxValue":31.9,"IntervalMinValue":27,"PrefixActive":false,"PrefixValue":"","SuffixActive":false,"SuffixValue":""},{"ColorActive":true,"ColorValue":16699191,"ConstantActive":false,"ConstantValue":"","ContentColorActive":false,"ContentColorValue":-1,"ConversionFactor":1,"DigitsActive":false,"DigitsValue":1,"IconActive":true,"IconValue":"temperature-full","IntervalMaxValue":40.9,"IntervalMinValue":32,"PrefixActive":false,"PrefixValue":"","SuffixActive":false,"SuffixValue":""},{"ColorActive":true,"ColorValue":16685312,"ConstantActive":false,"ConstantValue":"","ContentColorActive":false,"ContentColorValue":-1,"ConversionFactor":1,"DigitsActive":false,"DigitsValue":1,"IconActive":true,"IconValue":"temperature-high","IntervalMaxValue":53.9,"IntervalMinValue":41,"PrefixActive":false,"PrefixValue":"","SuffixActive":false,"SuffixValue":""},{"ColorActive":true,"ColorValue":16646144,"ConstantActive":false,"ConstantValue":"","ContentColorActive":false,"ContentColorValue":-1,"ConversionFactor":1,"DigitsActive":false,"DigitsValue":1,"IconActive":true,"IconValue":"triangle-exclamation","IntervalMaxValue":200,"IntervalMinValue":54,"PrefixActive":false,"PrefixValue":"","SuffixActive":false,"SuffixValue":""}]',
        'INTERVALS_ACTIVE' => true,
        'PRESENTATION'     => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
        'SUFFIX'           => ' °C',
        'USAGE_TYPE'       => 1,
    ];

    /** @var array<string,mixed> Heat warning level presentation (Value) */
    private const THS_PRESENTATION_HEAT_WARNING = [
        'ICON'             => 'temperature-high',
        'INTERVALS'        => '[{"ColorActive":false,"ColorValue":-1,"ConstantActive":true,"ConstantValue":"Harmless","ContentColorActive":false,"ContentColorValue":-1,"ConversionFactor":1,"DigitsActive":false,"DigitsValue":0,"IconActive":true,"IconValue":"temperature-half","IntervalMaxValue":0,"IntervalMinValue":0,"PrefixActive":false,"PrefixValue":"","SuffixActive":false,"SuffixValue":""},{"ColorActive":true,"ColorValue":16776961,"ConstantActive":true,"ConstantValue":"Caution","ContentColorActive":false,"ContentColorValue":-1,"ConversionFactor":1,"DigitsActive":false,"DigitsValue":0,"IconActive":true,"IconValue":"temperature-three-quarters","IntervalMaxValue":1,"IntervalMinValue":1,"PrefixActive":false,"PrefixValue":"","SuffixActive":false,"SuffixValue":""},{"ColorActive":true,"ColorValue":16699191,"ConstantActive":true,"ConstantValue":"Extreme caution","ContentColorActive":false,"ContentColorValue":-1,"ConversionFactor":1,"DigitsActive":false,"DigitsValue":0,"IconActive":true,"IconValue":"temperature-full","IntervalMaxValue":2,"IntervalMinValue":2,"PrefixActive":false,"PrefixValue":"","SuffixActive":false,"SuffixValue":""},{"ColorActive":true,"ColorValue":16685312,"ConstantActive":true,"ConstantValue":"Danger","ContentColorActive":false,"ContentColorValue":-1,"ConversionFactor":1,"DigitsActive":false,"DigitsValue":0,"IconActive":true,"IconValue":"temperature-high","IntervalMaxValue":3,"IntervalMinValue":3,"PrefixActive":false,"PrefixValue":"","SuffixActive":false,"SuffixValue":""},{"ColorActive":true,"ColorValue":16646144,"ConstantActive":true,"ConstantValue":"Extreme danger","ContentColorActive":false,"ContentColorValue":-1,"ConversionFactor":1,"DigitsActive":false,"DigitsValue":0,"IconActive":true,"IconValue":"triangle-exclamation","IntervalMaxValue":4,"IntervalMinValue":4,"PrefixActive":false,"PrefixValue":"","SuffixActive":false,"SuffixValue":""}]',
        'INTERVALS_ACTIVE' => true,
        'PRESENTATION'     => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
    ];

    /** @var array<string,mixed> Room climate (comfort) index presentation (Value) */
    private const THS_PRESENTATION_COMFORT_INDEX = [
        'ICON'             => 'gauge',
        'INTERVALS'        => '[{"ColorActive":false,"ColorValue":-1,"ConstantActive":true,"ConstantValue":"Out of range","ContentColorActive":false,"ContentColorValue":-1,"ConversionFactor":1,"DigitsActive":false,"DigitsValue":0,"IconActive":true,"IconValue":"circle-question","IntervalMaxValue":0,"IntervalMinValue":0,"PrefixActive":false,"PrefixValue":"","SuffixActive":false,"SuffixValue":""},{"ColorActive":true,"ColorValue":16711680,"ConstantActive":true,"ConstantValue":"Dry","ContentColorActive":false,"ContentColorValue":-1,"ConversionFactor":1,"DigitsActive":false,"DigitsValue":0,"IconActive":true,"IconValue":"droplet-slash","IntervalMaxValue":1,"IntervalMinValue":1,"PrefixActive":false,"PrefixValue":"","SuffixActive":false,"SuffixValue":""},{"ColorActive":true,"ColorValue":16750848,"ConstantActive":true,"ConstantValue":"Comfort dry","ContentColorActive":false,"ContentColorValue":-1,"ConversionFactor":1,"DigitsActive":false,"DigitsValue":0,"IconActive":true,"IconValue":"droplet","IntervalMaxValue":2,"IntervalMinValue":2,"PrefixActive":false,"PrefixValue":"","SuffixActive":false,"SuffixValue":""},{"ColorActive":true,"ColorValue":65280,"ConstantActive":true,"ConstantValue":"Comfort ideal","ContentColorActive":false,"ContentColorValue":-1,"ConversionFactor":1,"DigitsActive":false,"DigitsValue":0,"IconActive":true,"IconValue":"face-smile","IntervalMaxValue":3,"IntervalMinValue":3,"PrefixActive":false,"PrefixValue":"","SuffixActive":false,"SuffixValue":""},{"ColorActive":true,"ColorValue":11579392,"ConstantActive":true,"ConstantValue":"Comfort humid","ContentColorActive":false,"ContentColorValue":-1,"ConversionFactor":1,"DigitsActive":false,"DigitsValue":0,"IconActive":true,"IconValue":"droplet","IntervalMaxValue":4,"IntervalMinValue":4,"PrefixActive":false,"PrefixValue":"","SuffixActive":false,"SuffixValue":""},{"ColorActive":true,"ColorValue":255,"ConstantActive":true,"ConstantValue":"Humid","ContentColorActive":false,"ContentColorValue":-1,"ConversionFactor":1,"DigitsActive":false,"DigitsValue":0,"IconActive":true,"IconValue":"raindrops","IntervalMaxValue":5,"IntervalMinValue":5,"PrefixActive":false,"PrefixValue":"","SuffixActive":false,"SuffixValue":""}]',
        'INTERVALS_ACTIVE' => true,
        'PRESENTATION'     => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
    ];

    /** @var array<string,mixed> Predicted mean vote presentation (Value) */
    private const THS_PRESENTATION_PMV = [
        'ICON'             => 'person',
        'INTERVALS'        => '[{"ColorActive":true,"ColorValue":128,"ConstantActive":false,"ConstantValue":"","ContentColorActive":false,"ContentColorValue":-1,"ConversionFactor":1,"DigitsActive":false,"DigitsValue":2,"IconActive":true,"IconValue":"snowflake","IntervalMaxValue":-2.51,"IntervalMinValue":-10,"PrefixActive":true,"PrefixValue":"Cold (","SuffixActive":true,"SuffixValue":")"},{"ColorActive":true,"ColorValue":255,"ConstantActive":false,"ConstantValue":"","ContentColorActive":false,"ContentColorValue":-1,"ConversionFactor":1,"DigitsActive":false,"DigitsValue":2,"IconActive":true,"IconValue":"temperature-low","IntervalMaxValue":-1.51,"IntervalMinValue":-2.5,"PrefixActive":true,"PrefixValue":"Cool (","SuffixActive":true,"SuffixValue":")"},{"ColorActive":true,"ColorValue":32960,"ConstantActive":false,"ConstantValue":"","ContentColorActive":false,"ContentColorValue":-1,"ConversionFactor":1,"DigitsActive":false,"DigitsValue":2,"IconActive":true,"IconValue":"temperature-quarter","IntervalMaxValue":-0.51,"IntervalMinValue":-1.5,"PrefixActive":true,"PrefixValue":"Slightly cool (","SuffixActive":true,"SuffixValue":")"},{"ColorActive":true,"ColorValue":5810438,"ConstantActive":false,"ConstantValue":"","ContentColorActive":false,"ContentColorValue":-1,"ConversionFactor":1,"DigitsActive":false,"DigitsValue":2,"IconActive":true,"IconValue":"face-smile","IntervalMaxValue":0.5,"IntervalMinValue":-0.5,"PrefixActive":true,"PrefixValue":"Neutral (","SuffixActive":true,"SuffixValue":")"},{"ColorActive":true,"ColorValue":16776960,"ConstantActive":false,"ConstantValue":"","ContentColorActive":false,"ContentColorValue":-1,"ConversionFactor":1,"DigitsActive":false,"DigitsValue":2,"IconActive":true,"IconValue":"temperature-three-quarters","IntervalMaxValue":1.5,"IntervalMinValue":0.51,"PrefixActive":true,"PrefixValue":"Slightly warm (","SuffixActive":true,"SuffixValue":")"},{"ColorActive":true,"ColorValue":16744448,"ConstantActive":false,"ConstantValue":"","ContentColorActive":false,"ContentColorValue":-1,"ConversionFactor":1,"DigitsActive":false,"DigitsValue":2,"IconActive":true,"IconValue":"temperature-high","IntervalMaxValue":2.5,"IntervalMinValue":1.51,"PrefixActive":true,"PrefixValue":"Warm (","SuffixActive":true,"SuffixValue":")"},{"ColorActive":true,"ColorValue":16711680,"ConstantActive":false,"ConstantValue":"","ContentColorActive":false,"ContentColorValue":-1,"ConversionFactor":1,"DigitsActive":false,"DigitsValue":2,"IconActive":true,"IconValue":"fire","IntervalMaxValue":10,"IntervalMinValue":2.51,"PrefixActive":true,"PrefixValue":"Hot (","SuffixActive":true,"SuffixValue":")"}]',
        'INTERVALS_ACTIVE' => true,
        'PRESENTATION'     => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
    ];

    /** @var array<string,mixed> Predicted percentage of dissatisfied presentation (Value) */
    private const THS_PRESENTATION_PPD = [
        'DIGITS'           => 1,
        'ICON'             => 'face-frown',
        'INTERVALS'        => '[{"ColorActive":true,"ColorValue":5810438,"ConstantActive":false,"ConstantValue":"","ContentColorActive":false,"ContentColorValue":-1,"ConversionFactor":1,"DigitsActive":false,"DigitsValue":2,"IconActive":true,"IconValue":"face-smile","IntervalMaxValue":5.9,"IntervalMinValue":0,"PrefixActive":false,"PrefixValue":"","SuffixActive":false,"SuffixValue":""},{"ColorActive":true,"ColorValue":9157443,"ConstantActive":false,"ConstantValue":"","ContentColorActive":false,"ContentColorValue":-1,"ConversionFactor":1,"DigitsActive":false,"DigitsValue":2,"IconActive":true,"IconValue":"face-meh","IntervalMaxValue":9.9,"IntervalMinValue":6,"PrefixActive":false,"PrefixValue":"","SuffixActive":false,"SuffixValue":""},{"ColorActive":true,"ColorValue":16776960,"ConstantActive":false,"ConstantValue":"","ContentColorActive":false,"ContentColorValue":-1,"ConversionFactor":1,"DigitsActive":false,"DigitsValue":2,"IconActive":true,"IconValue":"face-frown","IntervalMaxValue":14.9,"IntervalMinValue":10,"PrefixActive":false,"PrefixValue":"","SuffixActive":false,"SuffixValue":""},{"ColorActive":true,"ColorValue":16744448,"ConstantActive":false,"ConstantValue":"","ContentColorActive":false,"ContentColorValue":-1,"ConversionFactor":1,"DigitsActive":false,"DigitsValue":2,"IconActive":true,"IconValue":"face-frown","IntervalMaxValue":100,"IntervalMinValue":15,"PrefixActive":false,"PrefixValue":"","SuffixActive":false,"SuffixValue":""}]',
        'INTERVALS_ACTIVE' => true,
        'PRESENTATION'     => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
        'SUFFIX'           => ' %',
    ];

    /** @var array<string,mixed> Thermal state presentation (Value) */
    private const THS_PRESENTATION_THERMAL_STATE = [
        'ICON'             => 'person',
        'INTERVALS'        => '[{"ColorActive":true,"ColorValue":255,"ConstantActive":true,"ConstantValue":"Too cool","ContentColorActive":false,"ContentColorValue":-1,"ConversionFactor":1,"DigitsActive":false,"DigitsValue":0,"IconActive":true,"IconValue":"temperature-low","IntervalMaxValue":-1,"IntervalMinValue":-1,"PrefixActive":false,"PrefixValue":"","SuffixActive":false,"SuffixValue":""},{"ColorActive":true,"ColorValue":5810438,"ConstantActive":true,"ConstantValue":"Comfortable","ContentColorActive":false,"ContentColorValue":-1,"ConversionFactor":1,"DigitsActive":false,"DigitsValue":0,"IconActive":true,"IconValue":"face-smile","IntervalMaxValue":0,"IntervalMinValue":0,"PrefixActive":false,"PrefixValue":"","SuffixActive":false,"SuffixValue":""},{"ColorActive":true,"ColorValue":16744448,"ConstantActive":true,"ConstantValue":"Too warm","ContentColorActive":false,"ContentColorValue":-1,"ConversionFactor":1,"DigitsActive":false,"DigitsValue":0,"IconActive":true,"IconValue":"temperature-high","IntervalMaxValue":1,"IntervalMinValue":1,"PrefixActive":false,"PrefixValue":"","SuffixActive":false,"SuffixValue":""}]',
        'INTERVALS_ACTIVE' => true,
        'PRESENTATION'     => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
    ];

    /** @var array<string,mixed> Heating period presentation (Value) */
    private const THS_PRESENTATION_HEATING_PERIOD = [
        'ICON'         => 'fire-flame-curved',
        'OPTIONS'      => '[{"Caption":"Outside heating period","ColorActive":false,"ColorValue":-1,"ContentColorActive":false,"ContentColorValue":-1,"IconActive":true,"IconValue":"sun","Value":false},{"Caption":"Heating period","ColorActive":false,"ColorValue":-1,"ContentColorActive":false,"ContentColorValue":-1,"IconActive":true,"IconValue":"fire-flame-curved","Value":true}]',
        'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
    ];

    // -------------------------------------------------------------------------
    // Methods
    // -------------------------------------------------------------------------

    /**
     * In contrast to Construct, this function is called only once when creating the instance and starting Symcon.
     * Therefore, status variables and module properties which the module requires permanently should be created here.
     *
     * @return void
     */
    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        // Outdoor climate
        $this->RegisterPropertyInteger('TempOutdoor', 0);
        $this->RegisterPropertyInteger('HumyOutdoor', 0);
        // Indoor climate
        $this->RegisterPropertyInteger('TempIndoor', 0);
        $this->RegisterPropertyInteger('HumyIndoor', 0);
        // Thermal comfort
        $this->RegisterPropertyInteger('ClothingMode', self::CLOTHING_FIXED);
        $this->RegisterPropertyFloat('Clothing', 0.7);
        $this->RegisterPropertyFloat('ClothingSummer', 0.5);
        $this->RegisterPropertyFloat('ClothingWinter', 1.0);
        $this->RegisterPropertyFloat('Activity', 1.2);
        $this->RegisterPropertyFloat('AirVelocity', 0.1);
        $this->RegisterPropertyInteger('TempRadiant', 0);
        $this->RegisterPropertyFloat('ThermalLimitUpper', 0.5);
        $this->RegisterPropertyFloat('ThermalLimitLower', -0.5);
        $this->RegisterPropertyFloat('ThermalHysteresis', 0.2);
        // Message management
        $this->RegisterPropertyInteger('DashboardMessage', 0);
        $this->RegisterPropertyInteger('NotificationMessage', 0);
        $this->RegisterPropertyInteger('InstanceWebfront', 0);
        $this->RegisterPropertyInteger('ScriptMessage', 0);
        $this->RegisterPropertyString('RoomName', 'Unknown');
        $this->RegisterPropertyInteger('LifeTime', 0);
        $this->RegisterPropertyInteger('NotificationPause', 30);
        // Ventilation logic
        $this->RegisterPropertyInteger('UpdateTimer', self::UPDATE_ON_CHANGE);
        $this->RegisterPropertyInteger('HintMode', self::HINT_CLIMATE_DATA);
        $this->RegisterPropertyInteger('SeasonMode', self::SEASON_ALWAYS);
        $this->RegisterPropertyFloat('HeatingLimit', 15.0);
        $this->RegisterPropertyInteger('SeasonStart', 10);
        $this->RegisterPropertyInteger('SeasonEnd', 4);
        $this->RegisterPropertyBoolean('CreateHeatingPeriod', false);
        $this->RegisterPropertyInteger('RoomType', 0);
        $this->RegisterPropertyInteger('MinHumidity', 60);
        $this->RegisterPropertyFloat('MinDifference', 1.0);
        // Visualisation
        $this->RegisterPropertyBoolean('ShowRoom', true);
        $this->RegisterPropertyString('TileElements', '[]');
        $this->RegisterPropertyInteger('ChartMode', 1);
        $this->RegisterPropertyInteger('ChartPeriod', 24);
        $this->RegisterPropertyBoolean('ChartBand', true);
        $this->RegisterPropertyInteger('ChartColor', -1);
        $this->RegisterPropertyInteger('ChartColorTemp', -1);
        // Advanced settings
        $this->RegisterPropertyBoolean('CreateDewPoint', true);
        $this->RegisterPropertyBoolean('CreateWaterContent', true);
        $this->RegisterPropertyBoolean('CreateOutdoorValues', true);
        $this->RegisterPropertyBoolean('CreateMeasuredIndoor', false);
        $this->RegisterPropertyBoolean('CreateMeasuredOutdoor', false);
        $this->RegisterPropertyBoolean('CreateHeatIndex', false);
        $this->RegisterPropertyBoolean('CreateComfortIndex', false);
        $this->RegisterPropertyBoolean('CreateThermalComfort', false);
        $this->RegisterPropertyBoolean('CreateThermalState', false);
        // Deprecated: replaced by the ventilation logic, only an existing variable is still evaluated
        $this->RegisterPropertyInteger('MessageThreshold', 100);
        $this->RegisterPropertyBoolean('CreateThreshold', false);

        // Smoothed outdoor temperature and heating period state
        $this->RegisterAttributeString('SeasonState', '{}');
        // Notification already sent for the current ventilation phase (survives restarts)
        $this->RegisterAttributeBoolean('Notified', false);
        // Time of the last push notification (minimum pause between notifications)
        $this->RegisterAttributeInteger('NotificationTime', 0);
        // Dashboard message of the current ventilation phase (number, expiry and text), removed when the phase ends
        $this->RegisterAttributeString('DashboardEntry', '{}');
        // Last determined heating period (tile chip, also without the variable)
        $this->RegisterAttributeBoolean('HeatingPeriod', false);

        // Update timer
        $this->RegisterTimer('UpdateTrigger', 0, "THS_Update(\$_IPS['TARGET']);");

        // Set visualization type to 1, as we want to offer HTML
        $this->SetVisualizationType(1);

        // Wait for the kernel before accessing foreign variables
        $this->RegisterMessage(0, IPS_KERNELSTARTED);
    }

    /**
     * Migrate the persistence of existing instances after a module update.
     * The symmetric thermal limit is split into an upper and a lower limit.
     * Existing instances keep the former defaults of the update timer (15 min) and the notification pause (none).
     *
     * @param string $json Persistence (configuration and attributes) of the instance
     *
     * @return string New persistence, empty if nothing has changed
     */
    public function Migrate(string $json): string
    {
        parent::Migrate($json);
        $data = json_decode($json);
        if (!isset($data->configuration)) {
            return '';
        }
        $changed = false;
        if (isset($data->configuration->ThermalLimit)) {
            $limit = abs((float) $data->configuration->ThermalLimit);
            $data->configuration->ThermalLimitUpper = $limit;
            $data->configuration->ThermalLimitLower = -$limit;
            unset($data->configuration->ThermalLimit);
            $changed = true;
        }
        if (!isset($data->configuration->NotificationPause)) {
            if (!isset($data->configuration->UpdateTimer)) {
                $data->configuration->UpdateTimer = 15;
            }
            $data->configuration->NotificationPause = 0;
            $changed = true;
        }
        return $changed ? json_encode($data) : '';
    }

    /**
     * The content can be overwritten in order to transfer a self-created configuration page.
     * This way, content can be generated dynamically.
     * In this case, the "form.json" on the file system is completely ignored.
     *
     * @return string Content of the configuration page.
     */
    public function GetConfigurationForm(): string
    {
        // Get form
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);

        // Extract version
        $ins = IPS_GetInstance($this->InstanceID);
        $mod = IPS_GetModule($ins['ModuleInfo']['ModuleID']);
        $lib = IPS_GetLibrary($mod['LibraryID']);
        $version = sprintf('v%s.%d', $lib['Version'], $lib['Build']);
        $this->ModifyFormElement($form['actions'], 'Version', function (array &$element) use ($version): void
        {
            $element['caption'] = $version;
        });

        // Show only the settings of the selected season and clothing mode
        $this->UpdateFormVisibility($this->ReadPropertyInteger('SeasonMode'), $this->ReadPropertyInteger('ClothingMode'), $form['elements']);

        // Typical room climate
        $rooms = [];
        foreach (self::ROOM_TYPES as $room) {
            $rooms[] = ['Room' => $this->Translate($room[0]), 'Temperature' => $room[1], 'Humidity' => $room[2]];
        }
        $this->ModifyFormElement($form['elements'], 'RoomTable', function (array &$element) use ($rooms): void
        {
            $element['values'] = $rooms;
        });

        // Tile elements with translated captions in the configured order
        $elements = [];
        foreach ($this->ReadTileElements() as $ident => $show) {
            $elements[] = [
                'Show'        => $show,
                'Name'        => $this->Translate(self::TILE_CAPTIONS[$ident][0]),
                'Description' => $this->Translate(self::TILE_CAPTIONS[$ident][1]),
                'Ident'       => $ident,
            ];
        }
        $this->ModifyFormElement($form['elements'], 'TileElements', function (array &$element) use ($elements): void
        {
            $element['values'] = $elements;
        });

        return json_encode($form);
    }

    /**
     * Is executed when "Apply" is pressed on the configuration page and immediately after the instance has been created.
     *
     * @return void
     */
    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();

        // Result, hint & difference
        $hint = $this->TranslatePresentation(self::THS_PRESENTATION_HINT, 'OPTIONS', 'Caption');
        $recommendation = $this->TranslatePresentation(self::THS_PRESENTATION_RECOMMENDATION, 'INTERVALS', 'ConstantValue');
        $this->MaintainVariable('Recommendation', $this->Translate('Recommendation'), VARIABLETYPE_INTEGER, $recommendation, 3, true);
        $this->MaintainVariable('Hint', $this->Translate('Hint'), VARIABLETYPE_BOOLEAN, $hint, 1, true);
        $this->MaintainVariable('Result', $this->Translate('Result'), VARIABLETYPE_STRING, self::THS_PRESENTATION_RESULT, 2, true);
        $this->MaintainVariable('Difference', $this->Translate('Difference'), VARIABLETYPE_FLOAT, self::THS_PRESENTATION_DIFFERENCE, 3, true);
        $outdoor = $this->ReadPropertyBoolean('CreateOutdoorValues');
        // Dew point
        $create = $this->ReadPropertyBoolean('CreateDewPoint');
        $this->MaintainVariable('DewPointOutdoor', $this->Translate('Dew point outdoor'), VARIABLETYPE_FLOAT, self::THS_PRESENTATION_DEW_POINT, 4, $create && $outdoor);
        $this->MaintainVariable('DewPointIndoor', $this->Translate('Dew point indoor'), VARIABLETYPE_FLOAT, self::THS_PRESENTATION_DEW_POINT, 5, $create);
        // Water content
        $create = $this->ReadPropertyBoolean('CreateWaterContent');
        $this->MaintainVariable('WaterContentOutdoor', $this->Translate('Water content outdoor'), VARIABLETYPE_FLOAT, self::THS_PRESENTATION_WATER_CONTENT, 6, $create && $outdoor);
        $this->MaintainVariable('WaterContentIndoor', $this->Translate('Water content indoor'), VARIABLETYPE_FLOAT, self::THS_PRESENTATION_WATER_CONTENT, 7, $create);
        // Deprecated message threshold, an existing variable is kept (no loss of archive data) but no longer created
        if ($this->HasThresholdVariable()) {
            $this->EnableAction('MessageThreshold');
        }
        // Heating period
        $create = $this->ReadPropertyBoolean('CreateHeatingPeriod') && ($this->ReadPropertyInteger('SeasonMode') !== self::SEASON_ALWAYS);
        $heating = $this->TranslatePresentation(self::THS_PRESENTATION_HEATING_PERIOD, 'OPTIONS', 'Caption');
        $this->MaintainVariable('HeatingPeriod', $this->Translate('Heating period'), VARIABLETYPE_BOOLEAN, $heating, 9, $create);
        // Heat index
        $create = $this->ReadPropertyBoolean('CreateHeatIndex');
        $warning = $this->TranslatePresentation(self::THS_PRESENTATION_HEAT_WARNING, 'INTERVALS', 'ConstantValue');
        $this->MaintainVariable('HeatIndex', $this->Translate('Heat index'), VARIABLETYPE_FLOAT, self::THS_PRESENTATION_HEAT_INDEX, 10, $create);
        $this->MaintainVariable('HeatWarning', $this->Translate('Heat warning'), VARIABLETYPE_INTEGER, $warning, 11, $create);
        // Room climate (comfort) index
        $create = $this->ReadPropertyBoolean('CreateComfortIndex');
        $comfort = $this->TranslatePresentation(self::THS_PRESENTATION_COMFORT_INDEX, 'INTERVALS', 'ConstantValue');
        $this->MaintainVariable('ComfortIndex', $this->Translate('Room climate index'), VARIABLETYPE_INTEGER, $comfort, 12, $create);
        // Thermal comfort (PMV/PPD)
        $create = $this->ReadPropertyBoolean('CreateThermalComfort');
        $pmv = $this->TranslatePresentation(self::THS_PRESENTATION_PMV, 'INTERVALS', 'PrefixValue');
        $this->MaintainVariable('PredictedMeanVote', $this->Translate('Thermal comfort (PMV)'), VARIABLETYPE_FLOAT, $pmv, 13, $create);
        $this->MaintainVariable('PredictedDissatisfied', $this->Translate('Dissatisfied (PPD)'), VARIABLETYPE_FLOAT, self::THS_PRESENTATION_PPD, 14, $create);
        $create = $create && $this->ReadPropertyBoolean('CreateThermalState');
        $state = $this->TranslatePresentation(self::THS_PRESENTATION_THERMAL_STATE, 'INTERVALS', 'ConstantValue');
        $this->MaintainVariable('ThermalState', $this->Translate('Thermal state'), VARIABLETYPE_INTEGER, $state, 15, $create);
        // Copy of the measured values used for the last calculation
        $create = $this->ReadPropertyBoolean('CreateMeasuredIndoor');
        $this->MaintainVariable('TemperatureIndoor', $this->Translate('Indoor temperature'), VARIABLETYPE_FLOAT, self::THS_PRESENTATION_TEMPERATURE, 16, $create);
        $this->MaintainVariable('HumidityIndoor', $this->Translate('Indoor humidity'), VARIABLETYPE_FLOAT, self::THS_PRESENTATION_HUMIDITY, 17, $create);
        $create = $this->ReadPropertyBoolean('CreateMeasuredOutdoor');
        $this->MaintainVariable('TemperatureOutdoor', $this->Translate('Outdoor temperature'), VARIABLETYPE_FLOAT, self::THS_PRESENTATION_TEMPERATURE, 18, $create);
        $this->MaintainVariable('HumidityOutdoor', $this->Translate('Outdoor humidity'), VARIABLETYPE_FLOAT, self::THS_PRESENTATION_HUMIDITY, 19, $create);

        // Settings may have changed the history
        $this->SetBuffer('History', '');

        // Foreign variables may not be available yet, ApplyChanges is called again on IPS_KERNELSTARTED
        if (IPS_GetKernelRunlevel() !== KR_READY) {
            return;
        }

        // Event-driven update: (re)register the changes of the climate variables
        foreach ($this->GetMessageList() as $senderID => $messages) {
            if (in_array(VM_UPDATE, $messages, true)) {
                $this->UnregisterMessage($senderID, VM_UPDATE);
            }
        }
        if ($this->ReadPropertyInteger('UpdateTimer') === self::UPDATE_ON_CHANGE) {
            foreach ([...self::CLIMATE_VARIABLES, 'TempRadiant'] as $property) {
                $variable = $this->ReadPropertyInteger($property);
                if ($this->IsValidVariable($variable)) {
                    $this->RegisterMessage($variable, VM_UPDATE);
                }
            }
        }

        // Delete all references in order to readd them
        foreach ($this->GetReferenceList() as $referenceID) {
            $this->UnregisterReference($referenceID);
        }

        // Register references
        foreach (self::CLIMATE_VARIABLES as $property) {
            $variable = $this->ReadPropertyInteger($property);
            if ($this->IsValidVariable($variable)) {
                $this->RegisterReference($variable);
            }
        }
        $radiant = $this->ReadPropertyInteger('TempRadiant');
        if ($this->IsValidVariable($radiant)) {
            $this->RegisterReference($radiant);
        }
        foreach (['InstanceWebfront', 'ScriptMessage'] as $property) {
            $object = $this->ReadPropertyInteger($property);
            if ($object >= self::IPS_MIN_ID && IPS_ObjectExists($object)) {
                $this->RegisterReference($object);
            }
        }

        // Check configuration
        if (!$this->HasClimateVariables()) {
            $this->SetTimerInterval('UpdateTrigger', 0);
            $this->SetStatus(self::STATUS_MISSING_VARIABLES);
            $this->UpdateVisualizationValue($this->GetFullUpdateMessage());
            return;
        }

        $this->SetStatus(102);

        // Calculate immediately with the new settings
        $this->Update();
    }

    /**
     * The content of the function can be overwritten in order to carry out own reactions to certain messages.
     * The function is only called for registered MessageIDs/SenderIDs combinations.
     *
     * data[0] = new value
     * data[1] = value changed?
     * data[2] = old value
     * data[3] = timestamp.
     *
     * @param int   $timestamp Continuous counter timestamp
     * @param int   $sender    Sender ID
     * @param int   $message   ID of the message
     * @param array{0:mixed,1:bool,2:mixed,3:int} $data Data of the message
     *
     * @return void
     */
    public function MessageSink(int $timestamp, int $sender, int $message, array $data): void
    {
        if ($message === IPS_KERNELSTARTED) {
            $this->ApplyChanges();
        }
        // Values sent together trigger only one calculation, each further change restarts the delay
        if ($message === VM_UPDATE && $data[1]) {
            $this->SetTimerInterval('UpdateTrigger', 1000 * self::UPDATE_DEBOUNCE);
        }
    }

    /**
     * Is called when, for example, a button is clicked in the visualization.
     *
     * @param string $ident Ident of the variable
     * @param mixed $value The value to be set
     *
     * @return void
     */
    public function RequestAction(string $ident, mixed $value): void
    {
        // Debug output
        $this->LogDebug(__FUNCTION__, $ident . ' => ' . $this->Stringify($value));

        switch ($ident) {
            case 'MessageThreshold':
                $this->SetValueInteger($ident, (int) $value);
                break;
            case 'OnFormVisibility':
                // Season and clothing mode as JSON array
                $modes = json_decode((string) $value, true);
                $this->UpdateFormVisibility((int) ($modes[0] ?? 0), (int) ($modes[1] ?? 0));
                break;
            case 'OnRoomType':
                $value = (int) $value;
                if (isset(self::ROOM_TYPES[$value])) {
                    $this->UpdateFormField('MinHumidity', 'value', self::ROOM_TYPES[$value][3]);
                }
                break;
            default:
                $this->LogDebug(__FUNCTION__, 'There was no reaction to the action.');
        }
    }

    /**
     * If the HTML-SDK is to be used, this function must be overwritten in order to return the HTML content.
     *
     * @return string Initial display of a representation via HTML SDK
     */
    public function GetVisualizationTile(): string
    {
        // Add a script to set the values when loading, analogous to changes at runtime
        // Although the return from GetFullUpdateMessage is already JSON-encoded, json_encode is still executed a second time
        // This adds quotation marks to the string and any quotation marks within it are escaped correctly
        $handling = '<script>handleMessage(' . json_encode($this->GetFullUpdateMessage()) . ');</script>';
        // Add static HTML from file
        $module = file_get_contents(__DIR__ . '/module.html');
        // Important: $handling at the end, as the handleMessage function is only defined in the HTML
        return $module . $handling;
    }

    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:.
     *
     * THS_Update($id);
     *
     * @return void
     */
    public function Update(): void
    {
        // Back to the cyclic interval (also after the delay of the event-driven update)
        $minutes = $this->ReadPropertyInteger('UpdateTimer');
        $this->SetTimerInterval('UpdateTrigger', 1000 * 60 * ($minutes === self::UPDATE_ON_CHANGE ? self::UPDATE_HEARTBEAT : $minutes));

        // Read climate data
        $tempOutdoor = $this->ReadClimateValue('TempOutdoor', false);
        $humyOutdoor = $this->ReadClimateValue('HumyOutdoor', true);
        $tempIndoor = $this->ReadClimateValue('TempIndoor', false);
        $humyIndoor = $this->ReadClimateValue('HumyIndoor', true);
        $this->LogDebug(__FUNCTION__, sprintf('Outdoor (t/h): %s / %s', $this->Stringify($tempOutdoor), $this->Stringify($humyOutdoor)));
        $this->LogDebug(__FUNCTION__, sprintf('Indoor (t/h): %s / %s', $this->Stringify($tempIndoor), $this->Stringify($humyIndoor)));

        // All values available?
        if ($tempOutdoor === null || $humyOutdoor === null || $tempIndoor === null || $humyIndoor === null) {
            $this->SetValueString('Result', $this->Translate('Result could not be determined!'));
            $this->SetStatus(self::STATUS_MISSING_VARIABLES);
            $this->UpdateVisualizationValue($this->GetFullUpdateMessage());
            return;
        }

        // A humidity of 0% would lead to an infinite dew point and a division by zero
        if ($humyOutdoor <= 0 || $humyOutdoor > 100 || $humyIndoor <= 0 || $humyIndoor > 100) {
            $this->LogDebug(__FUNCTION__, 'Humidity out of range (0 < h <= 100)!');
            $this->SetValueString('Result', $this->Translate('Result could not be determined!'));
            $this->SetStatus(self::STATUS_INVALID_HUMIDITY);
            $this->UpdateVisualizationValue($this->GetFullUpdateMessage());
            return;
        }
        $this->SetStatus(102);

        // Copy of the measured values
        if ($this->ReadPropertyBoolean('CreateMeasuredIndoor')) {
            $this->SetValueFloat('TemperatureIndoor', $tempIndoor);
            $this->SetValueFloat('HumidityIndoor', $humyIndoor);
        }
        if ($this->ReadPropertyBoolean('CreateMeasuredOutdoor')) {
            $this->SetValueFloat('TemperatureOutdoor', $tempOutdoor);
            $this->SetValueFloat('HumidityOutdoor', $humyOutdoor);
        }

        // Vapour pressure in hPa
        $outdoor = $this->ReadPropertyBoolean('CreateOutdoorValues');
        $saturationOutdoor = $this->SaturationVapourPressure($tempOutdoor);
        $saturationIndoor = $this->SaturationVapourPressure($tempIndoor);
        $vapourOutdoor = $humyOutdoor / 100 * $saturationOutdoor;
        $vapourIndoor = $humyIndoor / 100 * $saturationIndoor;

        // Dew point
        if ($this->ReadPropertyBoolean('CreateDewPoint')) {
            if ($outdoor) {
                $this->SetValueFloat('DewPointOutdoor', $this->DewPoint($tempOutdoor, $vapourOutdoor));
            }
            $this->SetValueFloat('DewPointIndoor', $this->DewPoint($tempIndoor, $vapourIndoor));
        }

        // Water content (absolute humidity)
        $waterOutdoor = $this->WaterContent($tempOutdoor, $vapourOutdoor);
        $waterIndoor = $this->WaterContent($tempIndoor, $vapourIndoor);
        $this->LogDebug(__FUNCTION__, sprintf('Water content (i/o): %.2f / %.2f g/m³', $waterIndoor, $waterOutdoor));
        if ($this->ReadPropertyBoolean('CreateWaterContent')) {
            if ($outdoor) {
                $this->SetValueFloat('WaterContentOutdoor', $waterOutdoor);
            }
            $this->SetValueFloat('WaterContentIndoor', $waterIndoor);
        }

        // Heat index (perceived temperature indoors)
        if ($this->ReadPropertyBoolean('CreateHeatIndex')) {
            $heatIndex = $this->HeatIndex($tempIndoor, $humyIndoor);
            $this->LogDebug(__FUNCTION__, sprintf('Heat index: %.1f °C', $heatIndex));
            $this->SetValueFloat('HeatIndex', $heatIndex);
            $this->SetValueInteger('HeatWarning', $this->HeatWarningLevel($heatIndex));
        }

        // Room climate (comfort) index
        if ($this->ReadPropertyBoolean('CreateComfortIndex')) {
            $comfortIndex = $this->ComfortIndex($tempIndoor, $humyIndoor);
            $this->LogDebug(__FUNCTION__, sprintf('Room climate index: %d', $comfortIndex));
            $this->SetValueInteger('ComfortIndex', $comfortIndex);
        }

        // Heating period (season for notifications and automatic clothing)
        $heating = $this->IsHeatingPeriod($tempOutdoor);

        // Thermal comfort (PMV/PPD)
        if ($this->ReadPropertyBoolean('CreateThermalComfort')) {
            $radiant = $this->ReadClimateValue('TempRadiant', false) ?? $tempIndoor;
            $clothing = $this->ClothingInsulation($heating);
            $this->LogDebug(__FUNCTION__, sprintf('Clothing: %.1f clo', $clothing));
            $comfort = $this->ThermalComfort($tempIndoor, $radiant, $humyIndoor, $clothing);
            if ($comfort === null) {
                $this->LogDebug(__FUNCTION__, 'PMV calculation did not converge!');
            } else {
                $this->LogDebug(__FUNCTION__, sprintf('PMV: %.2f, PPD: %.1f%%', $comfort[0], $comfort[1]));
                $this->SetValueFloat('PredictedMeanVote', $comfort[0]);
                $this->SetValueFloat('PredictedDissatisfied', $comfort[1]);
                if ($this->ReadPropertyBoolean('CreateThermalState')) {
                    $this->SetValueInteger('ThermalState', $this->ThermalState($comfort[0], $this->GetValue('ThermalState')));
                }
            }
        }

        // Difference: how much more water the indoor air contains compared to the outdoor air
        $difference = round(($waterIndoor / $waterOutdoor * 100) - 100, 2);
        $this->SetValueFloat('Difference', $difference);

        // Outdoor air heated to indoor temperature keeps its vapour pressure
        $humyOutdoorHeated = $vapourOutdoor / $saturationIndoor * 100;
        $this->LogDebug(__FUNCTION__, sprintf('Outdoor air at indoor temperature: %.0f%%', $humyOutdoorHeated));
        $humyAfter = round(self::AIR_EXCHANGE_REMAINING * $humyIndoor + (1 - self::AIR_EXCHANGE_REMAINING) * $humyOutdoorHeated, 0);
        $this->LogDebug(__FUNCTION__, sprintf('Indoor humidity after ventilation: %.0f%%', $humyAfter));

        // Recommendation
        $amount = ($humyAfter - $humyIndoor) <= self::HUMIDITY_INCREASE_LITTLE ? $this->Translate('a little') : $this->Translate('much');
        if (abs($waterIndoor - $waterOutdoor) <= self::EQUAL_WATER_CONTENT) {
            $hint = false;
            $recommendation = self::RECOMMENDATION_NO_EFFECT;
            $result = $this->Translate('Roughly equal humidity indoors and outdoors!');
        } elseif ($waterIndoor > $waterOutdoor && $tempIndoor > $tempOutdoor) {
            $hint = true;
            $recommendation = self::RECOMMENDATION_VENTILATE;
            $result = $this->Translate('Ventilate!!');
        } elseif ($waterIndoor > $waterOutdoor) {
            $hint = true;
            $recommendation = self::RECOMMENDATION_WARMER;
            $result = $this->Translate('Ventilation possible, but it will get warmer!!');
        } elseif ($humyAfter <= self::HUMIDITY_COMFORT) {
            // The comfort range of the relative humidity is between 45% and 55%
            $hint = true;
            $recommendation = self::RECOMMENDATION_SHORT;
            $result = sprintf($this->Translate('Short ventilation possible, but it will get %s more humid (up to 50-60%% is OK)'), $amount);
        } elseif ($humyAfter <= self::HUMIDITY_ACCEPTABLE) {
            $hint = true;
            $recommendation = self::RECOMMENDATION_VERY_SHORT;
            $result = sprintf($this->Translate('Very short ventilation possible, but it will get %s more humid (up to 60%% still acceptable)'), $amount);
        } else {
            $hint = false;
            $recommendation = self::RECOMMENDATION_NONE;
            $result = $this->Translate('Do NOT ventilate!');
        }
        $this->SetValueInteger('Recommendation', $recommendation);
        $this->LogDebug(__FUNCTION__, $result);
        $this->SetValueString('Result', $result);

        // Season
        $season = ($this->ReadPropertyInteger('SeasonMode') === self::SEASON_ALWAYS) || $heating;

        // Notify only if ventilation really dries the room air and the room air is too humid
        $notify = $hint && $season && ($waterIndoor > $waterOutdoor);
        $minDifference = $this->ReadPropertyFloat('MinDifference');
        if ($minDifference > 0 && ($waterIndoor - $waterOutdoor) < $minDifference) {
            $notify = false;
        }
        $minHumidity = $this->ReadPropertyInteger('MinHumidity');
        if ($minHumidity > 0 && $humyIndoor < $minHumidity) {
            $notify = false;
        }
        if ($this->HasThresholdVariable() && $difference <= $this->GetValue('MessageThreshold')) {
            $notify = false;
        }
        $this->LogDebug(__FUNCTION__, sprintf('Notify: %s (season: %s)', $this->Stringify($notify), $this->Stringify($season)));
        // The hint follows the climate data or optionally the complete ventilation logic (trigger for automations)
        $this->SetValueBoolean('Hint', $this->ReadPropertyInteger('HintMode') === self::HINT_VENTILATION_LOGIC ? $notify : $hint);

        // Notify only once each time the conditions are met, the dashboard message follows the recommendation
        // and disappears when the conditions no longer apply
        if ($notify && !$this->ReadAttributeBoolean('Notified')) {
            // Conditions flapping around a limit must not push a notification each time
            $pause = $this->ReadPropertyInteger('NotificationPause') * 60;
            $push = (time() - $this->ReadAttributeInteger('NotificationTime')) >= $pause;
            $this->LogDebug(__FUNCTION__, sprintf('Push notification: %s (pause: %d min)', $this->Stringify($push), $pause / 60));
            $this->SendMessages($result, $push);
        } elseif ($notify) {
            $this->ReplaceDashboardMessage($result);
        } else {
            $this->RemoveDashboardMessage();
        }
        $this->WriteAttributeBoolean('Notified', $notify);

        // Update the tile
        $this->UpdateVisualizationValue($this->GetFullUpdateMessage());
    }

    /**
     * Generate a message that updates all elements in the HTML display.
     *
     * @return string JSON encoded message information
     */
    private function GetFullUpdateMessage(): string
    {
        $hint = $this->ObjectID('Hint');
        $ventilate = $hint > 0 && GetValueBoolean($hint);
        $variable = $this->ObjectID('Recommendation');
        $recommendation = $variable > 0 ? GetValueInteger($variable) : -1;
        if (!isset(self::RECOMMENDATION_BADGES[$recommendation])) {
            $recommendation = $ventilate ? self::RECOMMENDATION_VENTILATE : self::RECOMMENDATION_NONE;
        }
        $show = $this->ReadTileElements();

        $result = [];
        $result['room'] = $this->ReadPropertyBoolean('ShowRoom') ? $this->ReadPropertyString('RoomName') : '';
        $result['hintText'] = $this->Translate(self::RECOMMENDATION_BADGES[$recommendation][0]);
        $result['hintColor'] = $this->HexColor(self::RECOMMENDATION_BADGES[$recommendation][1]);
        $result['humidity'] = $this->FormattedClimateValue('HumyIndoor');
        $result['temperature'] = $this->FormattedClimateValue('TempIndoor');
        $result['outdoor'] = $show['Outdoor'] ? $this->FormattedClimateValue('HumyOutdoor') . ' · ' . $this->FormattedClimateValue('TempOutdoor') : '';
        $difference = $this->ObjectID('Difference');
        $result['difference'] = ($show['Difference'] && $difference > 0) ? GetValueFormatted($difference) : '';
        // Notification conditions of the ventilation logic are met
        $alert = $this->ReadAttributeBoolean('Notified');
        $result['alert'] = $alert;
        $resultText = $this->ObjectID('Result');
        $result['titles'] = [
            'hint'        => $resultText > 0 ? GetValueString($resultText) : '',
            'humidity'    => $this->Translate('Indoor humidity'),
            'temperature' => $this->Translate('Indoor temperature'),
            'outdoor'     => $this->Translate('Outdoor humidity and temperature'),
            'difference'  => $this->Translate('Difference of the water content indoors to outdoors') . ($alert ? ' – ' . $this->Translate('ventilation required') : ''),
        ];
        $result['items'] = $this->GetTileItems($show);
        $result['chart'] = $this->GetChartData();

        $this->LogDebug(__FUNCTION__, $result);
        return json_encode($result);
    }

    /**
     * Build the configurable tile elements in the configured order.
     *
     * @param array<string,bool> $show Tile elements and their visibility
     *
     * @return list<array<string,mixed>> Tile elements (text, scale or chip)
     */
    private function GetTileItems(array $show): array
    {
        $items = [];
        foreach ($show as $ident => $visible) {
            if (!$visible) {
                continue;
            }
            switch ($ident) {
                case 'Result':
                    $id = $this->ObjectID('Result');
                    if ($id > 0) {
                        $items[] = ['type' => 'text', 'text' => GetValueString($id)];
                    }
                    break;
                case 'ComfortIndex':
                    $id = $this->ObjectID('ComfortIndex');
                    if ($id > 0) {
                        $index = GetValueInteger($id);
                        $items[] = $this->TileScale('Room climate', GetValueFormatted($id), $index > 0 ? ($index - 0.5) / 5 : null, self::TILE_COLORS_COMFORT, 'gradient');
                    }
                    break;
                case 'ThermalComfort':
                    $pmv = $this->ObjectID('PredictedMeanVote');
                    $ppd = $this->ObjectID('PredictedDissatisfied');
                    if ($pmv > 0 && $ppd > 0) {
                        $items[] = $this->TileScale('Thermal comfort', GetValueFormatted($pmv) . ' · ' . GetValueFormatted($ppd), (GetValueFloat($pmv) + 3) / 6, self::TILE_COLORS_PMV, 'gradient');
                    }
                    break;
                case 'HeatIndex':
                    $heat = $this->ObjectID('HeatIndex');
                    $warning = $this->ObjectID('HeatWarning');
                    // Below the warning level 'caution' the heat index hardly differs from the room temperature
                    if ($heat > 0 && $warning > 0 && GetValueInteger($warning) > 0) {
                        $items[] = $this->TileScale('Heat index', GetValueFormatted($heat) . ' · ' . GetValueFormatted($warning), $this->HeatScalePosition(GetValueFloat($heat)), self::TILE_COLORS_HEAT, 'gradient');
                    }
                    break;
                case 'DewPoint':
                    $id = $this->ObjectID('DewPointIndoor');
                    if ($id > 0) {
                        $items[] = ['type' => 'chip', 'icon' => 'droplet-degree', 'label' => $this->Translate('Dew point indoor'), 'text' => GetValueFormatted($id)];
                    }
                    break;
                case 'WaterContent':
                    $id = $this->ObjectID('WaterContentIndoor');
                    if ($id > 0) {
                        $items[] = ['type' => 'chip', 'icon' => 'raindrops', 'label' => $this->Translate('Water content indoor'), 'text' => GetValueFormatted($id)];
                    }
                    break;
                case 'HeatingPeriod':
                    $items[] = $this->SeasonChip();
                    break;
            }
        }
        return $items;
    }

    /**
     * Build the season chip: the text shows the season mode, the icon the current state.
     *
     * @return array<string,mixed> Tile chip element
     */
    private function SeasonChip(): array
    {
        $mode = $this->ReadPropertyInteger('SeasonMode');
        $modes = [
            self::SEASON_ALWAYS    => 'Always',
            self::SEASON_AUTOMATIC => 'Automatic',
            self::SEASON_CALENDAR  => 'Calendar',
        ];
        $text = $this->Translate($modes[$mode] ?? 'Always');
        if ($mode === self::SEASON_ALWAYS) {
            return ['type' => 'chip', 'icon' => 'calendar', 'label' => $this->Translate('Season') . ': ' . $text, 'text' => $text];
        }
        $heating = $this->ReadAttributeBoolean('HeatingPeriod');
        $state = $this->Translate($heating ? 'Heating period' : 'Outside heating period');
        return ['type' => 'chip', 'icon' => $heating ? 'fire-flame-curved' : 'sun', 'label' => $this->Translate('Season') . ': ' . $text . ' – ' . $state, 'text' => $text];
    }

    /**
     * Build a tile scale element.
     *
     * @param string    $label    Caption (translated here)
     * @param string    $text     Formatted value
     * @param float|null $position Position of the marker from 0 to 1, null = no marker
     * @param list<int> $colors   Colors of the scale, -1 = neutral
     * @param string    $mode     'segments' or 'gradient'
     *
     * @return array<string,mixed> Tile scale element
     */
    private function TileScale(string $label, string $text, ?float $position, array $colors, string $mode): array
    {
        return [
            'type'   => 'scale',
            'label'  => $this->Translate($label),
            'text'   => $text,
            'pos'    => $position === null ? null : round(max(0.0, min(1.0, $position)), 3),
            'colors' => array_map(fn (int $color): string => $this->HexColor($color), $colors),
            'mode'   => $mode,
        ];
    }

    /**
     * Position of a heat index on the tile scale, each warning level gets the same width.
     *
     * @param float $heatIndex Heat index in °C
     *
     * @return float Position from 0 to 1
     */
    private function HeatScalePosition(float $heatIndex): float
    {
        $borders = self::TILE_HEAT_SCALE;
        $segments = count($borders) - 1;
        foreach (array_slice($borders, 0, $segments) as $i => $lower) {
            $upper = $borders[$i + 1];
            if ($heatIndex < $upper || $i === $segments - 1) {
                $width = max(0.1, $upper - $lower);
                $part = max(0.0, min(1.0, ($heatIndex - $lower) / $width));
                return max(0.0, min(1.0, ($i + $part) / $segments));
            }
        }
        return 1.0;
    }

    /**
     * Read the history of the indoor humidity (and temperature) from the archive.
     * Without archive logging of the selected variables no history is shown.
     *
     * @return array<string,mixed>|null Chart data or null if no history is available
     */
    private function GetChartData(): ?array
    {
        $mode = $this->ReadPropertyInteger('ChartMode');
        if ($mode === 0) {
            return null;
        }
        $archive = IPS_GetInstanceListByModuleID(self::ARCHIVE_MODULE)[0] ?? 0;
        if ($archive === 0) {
            return null;
        }
        // Hourly averages hardly change, the event-driven update must not query the archive each time
        $end = time();
        $cache = json_decode($this->GetBuffer('History'), true);
        if (is_array($cache) && ($end - (int) ($cache['time'] ?? 0)) < self::HISTORY_CACHE_TIME) {
            $humidity = $cache['humidity'] ?? null;
            $temperature = $cache['temperature'] ?? null;
        } else {
            $start = $end - $this->ReadPropertyInteger('ChartPeriod') * 3600;
            $humidity = $this->ReadHistory($archive, 'HumyIndoor', $start, $end, true);
            $temperature = ($mode === 2 && $humidity !== null) ? $this->ReadHistory($archive, 'TempIndoor', $start, $end, false) : null;
            $this->SetBuffer('History', json_encode(['time' => $end, 'humidity' => $humidity, 'temperature' => $temperature]));
        }
        if ($humidity === null) {
            return null;
        }

        // Comfort band of the room type, for a custom room up to the minimum room humidity
        $band = null;
        if ($this->ReadPropertyBoolean('ChartBand')) {
            $room = $this->ReadPropertyInteger('RoomType');
            if (isset(self::ROOM_TYPES[$room])) {
                $band = [self::ROOM_TYPES[$room][4], self::ROOM_TYPES[$room][3]];
            } else {
                $limit = $this->ReadPropertyInteger('MinHumidity');
                $band = [40, $limit > 40 ? $limit : 60];
            }
        }

        return [
            'values'    => $humidity['values'],
            'times'     => $humidity['times'],
            'temps'     => $temperature['values'] ?? null,
            'tempTimes' => $temperature['times'] ?? null,
            'band'      => $band,
            'color'     => $this->HexColor($this->ReadPropertyInteger('ChartColor')),
            'tempColor' => $this->HexColor($this->ReadPropertyInteger('ChartColorTemp')),
        ];
    }

    /**
     * Read the hourly averages of a climate variable from the archive (oldest first).
     *
     * @param int    $archive  Archive control instance ID
     * @param string $property Property holding the variable
     * @param int    $start    Start time
     * @param int    $end      End time
     * @param bool   $humidity True if the value is a relative humidity
     *
     * @return array{values: list<float>, times: list<int>}|null Hourly averages with their start times or null if the variable is not logged
     */
    private function ReadHistory(int $archive, string $property, int $start, int $end, bool $humidity): ?array
    {
        $variable = $this->ReadPropertyInteger($property);
        if (!$this->IsValidVariable($variable) || !AC_GetLoggingStatus($archive, $variable)) {
            $this->LogDebug(__FUNCTION__, $property . ' is not logged, no history available.');
            return null;
        }
        $rows = AC_GetAggregatedValues($archive, $variable, 0, $start, $end, 0);
        if (!is_array($rows) || count($rows) === 0) {
            return null;
        }
        $rows = array_reverse($rows);
        $times = array_map(fn (array $row): int => (int) $row['TimeStamp'], $rows);
        $values = array_map(fn (array $row): float => (float) $row['Avg'], $rows);
        // Some sensors deliver the humidity as a fraction (0..1) in a float variable
        if ($humidity && max($values) <= 1 && IPS_GetVariable($variable)['VariableType'] === VARIABLETYPE_FLOAT) {
            $values = array_map(fn (float $value): float => $value * 100, $values);
        }
        return [
            'values' => array_map(fn (float $value): float => round($value, 2), $values),
            'times'  => $times,
        ];
    }

    /**
     * Read the configured tile elements, unknown entries are dropped and new ones appended.
     *
     * @return array<string,bool> Tile elements in the configured order and their visibility
     */
    private function ReadTileElements(): array
    {
        $elements = [];
        $defaults = array_keys(self::TILE_ELEMENTS);
        $list = json_decode($this->ReadPropertyString('TileElements'), true);
        foreach (is_array($list) ? array_values($list) : [] as $index => $row) {
            // Rows without ident (saved before the ident column was stored) keep the default order
            $ident = $row['Ident'] ?? ($defaults[$index] ?? '');
            if (isset(self::TILE_ELEMENTS[$ident])) {
                $elements[$ident] = (bool) ($row['Show'] ?? false);
            }
        }
        foreach (self::TILE_ELEMENTS as $ident => $show) {
            if (!isset($elements[$ident])) {
                $elements[$ident] = $show;
            }
        }
        return $elements;
    }

    /**
     * Formatted value of a configured climate variable.
     *
     * @param string $property Property holding the variable
     *
     * @return string Formatted value or '--'
     */
    private function FormattedClimateValue(string $property): string
    {
        $variable = $this->ReadPropertyInteger($property);
        return $this->IsValidVariable($variable) ? GetValueFormatted($variable) : '--';
    }

    /**
     * ID of an own object by its ident.
     *
     * @param string $ident Ident of the object
     *
     * @return int Object ID or 0 if it does not exist
     */
    private function ObjectID(string $ident): int
    {
        $id = @IPS_GetObjectIDByIdent($ident, $this->InstanceID);
        return $id === false ? 0 : $id;
    }

    /**
     * Color as CSS hex string.
     *
     * @param int $color Color as integer, -1 = no color
     *
     * @return string Color as '#RRGGBB' or '' for no color
     */
    private function HexColor(int $color): string
    {
        return $color < 0 ? '' : sprintf('#%06X', $color);
    }

    /**
     * Send the ventilation recommendation to the dashboard and/or the visualization.
     *
     * @param string $result Recommendation text
     * @param bool   $push   True to send the push notification (outside the minimum pause)
     *
     * @return void
     */
    private function SendMessages(string $result, bool $push): void
    {
        $text = $this->ReadPropertyString('RoomName') . ': ' . $result;

        // Dashboard
        if ($this->ReadPropertyInteger('DashboardMessage')) {
            $lifetime = $this->ReadPropertyInteger('LifeTime') * 60;
            $this->AddDashboardMessage($result, $lifetime > 0 ? time() + $lifetime : 0);
        }

        // Notification
        $visu = $this->ReadPropertyInteger('InstanceWebfront');
        if ($push && $this->ReadPropertyInteger('NotificationMessage') && $visu >= self::IPS_MIN_ID) {
            $this->WriteAttributeInteger('NotificationTime', time());
            if ($this->IsWebFrontVisuInstance($visu)) {
                WFC_PushNotification($visu, $this->Translate('Ventilation note'), $text, 'turn', 0);
            }
            if ($this->IsTileVisuInstance($visu)) {
                VISU_PostNotificationEx($visu, $this->Translate('Ventilation note'), $text, 'window-frame-open', 'turn', $this->InstanceID);
            }
        }
    }

    /**
     * Add the recommendation as message to the dashboard script and remember its number for a later removal.
     *
     * @param string $result Recommendation text
     * @param int $expires Unix timestamp of the automatic removal (0 = no expiry)
     *
     * @return void
     */
    private function AddDashboardMessage(string $result, int $expires): void
    {
        $script = $this->ReadPropertyInteger('ScriptMessage');
        if ($script < self::IPS_MIN_ID || !IPS_ScriptExists($script)) {
            return;
        }
        $params = ['action' => 'add', 'text' => $this->ReadPropertyString('RoomName') . ': ' . $result, 'removable' => true, 'type' => 3, 'image' => 'Ventilation'];
        if ($expires > 0) {
            $params['expires'] = $expires;
        }
        $number = (int) IPS_RunScriptWaitEx($script, $params);
        $this->LogDebug(__FUNCTION__, sprintf('Dashboard message #%d added', $number));
        if ($number > 0) {
            $this->WriteAttributeString('DashboardEntry', json_encode(['number' => $number, 'expires' => $expires, 'result' => $result]));
        }
    }

    /**
     * Replace the dashboard message if the recommendation text changed within the ventilation phase.
     * The original expiry is kept, an expired or already confirmed message is not shown again.
     *
     * @param string $result Recommendation text
     *
     * @return void
     */
    private function ReplaceDashboardMessage(string $result): void
    {
        $entry = json_decode($this->ReadAttributeString('DashboardEntry'), true);
        if (!isset($entry['number']) || ($entry['result'] ?? '') === $result) {
            return;
        }
        if ($this->RemoveDashboardMessage()) {
            $this->AddDashboardMessage($result, (int) ($entry['expires'] ?? 0));
        }
    }

    /**
     * Remove the dashboard message of the ventilation phase (unless it has already expired).
     *
     * @return bool True if the message was still displayed and has been removed
     */
    private function RemoveDashboardMessage(): bool
    {
        $entry = json_decode($this->ReadAttributeString('DashboardEntry'), true);
        $number = (int) ($entry['number'] ?? 0);
        if ($number <= 0) {
            return false;
        }
        $this->WriteAttributeString('DashboardEntry', '{}');
        $expires = (int) ($entry['expires'] ?? 0);
        if ($expires > 0 && $expires <= time()) {
            return false;
        }
        $script = $this->ReadPropertyInteger('ScriptMessage');
        if ($script < self::IPS_MIN_ID || !IPS_ScriptExists($script)) {
            return false;
        }
        // The script returns 1 if the message existed, 0 if it was already confirmed by the user
        $removed = (int) IPS_RunScriptWaitEx($script, ['action' => 'remove', 'number' => $number]) === 1;
        $this->LogDebug(__FUNCTION__, sprintf('Dashboard message #%d removed: %s', $number, $this->Stringify($removed)));
        return $removed;
    }

    /**
     * Determine the heating period according to the selected season mode.
     * The smoothed outdoor temperature is always tracked, so switching the mode takes effect immediately.
     * With the season mode "always" the automatic determination is used (e.g. for the clothing).
     *
     * @param float $tempOutdoor Current outdoor temperature in °C
     *
     * @return bool True if it is the heating period
     */
    private function IsHeatingPeriod(float $tempOutdoor): bool
    {
        $heating = $this->UpdateHeatingPeriod($tempOutdoor);
        $mode = $this->ReadPropertyInteger('SeasonMode');
        if ($mode === self::SEASON_CALENDAR) {
            $month = (int) date('n');
            $start = $this->ReadPropertyInteger('SeasonStart');
            $end = $this->ReadPropertyInteger('SeasonEnd');
            // The heating period usually spans the turn of the year (e.g. October to April)
            $heating = ($start <= $end) ? ($month >= $start && $month <= $end) : ($month >= $start || $month <= $end);
        }
        if ($mode !== self::SEASON_ALWAYS && $this->ReadPropertyBoolean('CreateHeatingPeriod')) {
            $this->SetValueBoolean('HeatingPeriod', $heating);
        }
        $this->WriteAttributeBoolean('HeatingPeriod', $heating);
        return $heating;
    }

    /**
     * Clothing insulation, fixed or switched automatically with the heating period.
     *
     * @param bool $heating True if it is the heating period
     *
     * @return float Clothing insulation in clo
     */
    private function ClothingInsulation(bool $heating): float
    {
        if ($this->ReadPropertyInteger('ClothingMode') === self::CLOTHING_AUTOMATIC) {
            return $this->ReadPropertyFloat($heating ? 'ClothingWinter' : 'ClothingSummer');
        }
        return $this->ReadPropertyFloat('Clothing');
    }

    /**
     * Show only the form fields which are relevant for the selected season and clothing mode.
     *
     * @param int                    $season   Season mode
     * @param int                    $clothing Clothing mode
     * @param array<mixed>|null      $elements Form elements to modify, null to update the open form
     *
     * @return void
     */
    private function UpdateFormVisibility(int $season, int $clothing, ?array &$elements = null): void
    {
        $auto = $clothing === self::CLOTHING_AUTOMATIC;
        $visible = [
            // The heating limit is also needed for the automatic clothing with the season mode "always"
            'HeatingLimit'        => ($season === self::SEASON_AUTOMATIC) || ($season === self::SEASON_ALWAYS && $auto),
            'HeatingLimitHint'    => $season === self::SEASON_ALWAYS && $auto,
            'SeasonStart'         => $season === self::SEASON_CALENDAR,
            'SeasonEnd'           => $season === self::SEASON_CALENDAR,
            'CreateHeatingPeriod' => $season !== self::SEASON_ALWAYS,
            'Clothing'            => !$auto,
            'ClothingSummer'      => $auto,
            'ClothingWinter'      => $auto,
        ];
        foreach ($visible as $name => $value) {
            if ($elements === null) {
                $this->UpdateFormField($name, 'visible', $value);
            } else {
                $this->ModifyFormElement($elements, $name, function (array &$element) use ($value): void
                {
                    $element['visible'] = $value;
                });
            }
        }
    }

    /**
     * Smooth the outdoor temperature exponentially and determine the heating period with hysteresis.
     *
     * @param float $tempOutdoor Current outdoor temperature in °C
     *
     * @return bool True if the smoothed outdoor temperature indicates the heating period
     */
    private function UpdateHeatingPeriod(float $tempOutdoor): bool
    {
        $now = time();
        $limit = $this->ReadPropertyFloat('HeatingLimit');
        $state = json_decode($this->ReadAttributeString('SeasonState'), true);
        if (!isset($state['average'], $state['time'], $state['heating'])) {
            $state = ['average' => $tempOutdoor, 'time' => $now, 'heating' => $tempOutdoor < $limit];
        } else {
            $alpha = 1 - exp(-max(0, $now - $state['time']) / self::SEASON_SMOOTHING);
            $state['average'] += $alpha * ($tempOutdoor - $state['average']);
            $state['time'] = $now;
            if ($state['heating'] && $state['average'] > $limit + self::SEASON_HYSTERESIS) {
                $state['heating'] = false;
            } elseif (!$state['heating'] && $state['average'] < $limit - self::SEASON_HYSTERESIS) {
                $state['heating'] = true;
            }
        }
        $this->WriteAttributeString('SeasonState', json_encode($state));
        $this->LogDebug(__FUNCTION__, sprintf('Smoothed outdoor temperature: %.1f °C, heating period: %s', $state['average'], $this->Stringify($state['heating'])));
        return (bool) $state['heating'];
    }

    /**
     * Check whether the deprecated message threshold variable still exists.
     *
     * @return bool True if the variable exists
     */
    private function HasThresholdVariable(): bool
    {
        return @IPS_GetObjectIDByIdent('MessageThreshold', $this->InstanceID) !== false;
    }

    /**
     * Read the value of a configured climate variable.
     *
     * @param string $property Property name
     * @param bool   $humidity True if the value is a relative humidity
     *
     * @return float|null Value or null if the variable is not available
     */
    private function ReadClimateValue(string $property, bool $humidity): ?float
    {
        $variable = $this->ReadPropertyInteger($property);
        if (!$this->IsValidVariable($variable)) {
            $this->LogDebug(__FUNCTION__, $property . ' not set!');
            return null;
        }
        $value = (float) GetValue($variable);
        // Some sensors deliver the humidity as a fraction (0..1) in a float variable
        if ($humidity && $value > 0 && $value <= 1 && IPS_GetVariable($variable)['VariableType'] === VARIABLETYPE_FLOAT) {
            $value *= 100;
        }
        return $value;
    }

    /**
     * Check whether all climate variables are configured and available.
     *
     * @return bool True if all variables are available
     */
    private function HasClimateVariables(): bool
    {
        foreach (self::CLIMATE_VARIABLES as $property) {
            if (!$this->IsValidVariable($this->ReadPropertyInteger($property))) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check whether a variable ID refers to an existing variable.
     *
     * @param int $variable Variable ID
     *
     * @return bool True if the variable exists
     */
    private function IsValidVariable(int $variable): bool
    {
        return $variable >= self::IPS_MIN_ID && IPS_VariableExists($variable);
    }

    /**
     * Magnus coefficients a and b, over water (supercooled water below 0 °C).
     *
     * @param float $temp Temperature in °C
     *
     * @return array{0:float,1:float} Coefficients a and b
     */
    private function MagnusCoefficients(float $temp): array
    {
        return $temp >= 0 ? [7.5, 237.3] : [7.6, 240.7];
    }

    /**
     * Saturation vapour pressure (Magnus formula).
     *
     * @param float $temp Temperature in °C
     *
     * @return float Saturation vapour pressure in hPa
     */
    private function SaturationVapourPressure(float $temp): float
    {
        [$a, $b] = $this->MagnusCoefficients($temp);
        return self::MAGNUS_BASE * pow(10, ($a * $temp) / ($b + $temp));
    }

    /**
     * Dew point temperature.
     *
     * @param float $temp   Temperature in °C
     * @param float $vapour Vapour pressure in hPa
     *
     * @return float Dew point in °C
     */
    private function DewPoint(float $temp, float $vapour): float
    {
        [$a, $b] = $this->MagnusCoefficients($temp);
        $v = log10($vapour / self::MAGNUS_BASE);
        return $b * $v / ($a - $v);
    }

    /**
     * Heat index according to the algorithm of the US National Weather Service.
     * The Rothfusz regression (Steadman 1979) is only valid from about 27 °C, below a simple approximation is used.
     *
     * @see https://www.wpc.ncep.noaa.gov/html/heatindex_equation.shtml
     *
     * @param float $temp     Temperature in °C
     * @param float $humidity Relative humidity in %
     *
     * @return float Heat index in °C
     */
    private function HeatIndex(float $temp, float $humidity): float
    {
        $t = $temp * 9 / 5 + 32;
        $rh = $humidity;
        $hi = 0.5 * ($t + 61.0 + (($t - 68.0) * 1.2) + ($rh * 0.094));
        if (($hi + $t) / 2 >= 80) {
            $hi = -42.379 + 2.04901523 * $t + 10.14333127 * $rh - 0.22475541 * $t * $rh - 0.00683783 * $t * $t
                - 0.05481717 * $rh * $rh + 0.00122874 * $t * $t * $rh + 0.00085282 * $t * $rh * $rh - 0.00000199 * $t * $t * $rh * $rh;
            if ($rh < 13 && $t >= 80 && $t <= 112) {
                $hi -= ((13 - $rh) / 4) * sqrt((17 - abs($t - 95)) / 17);
            } elseif ($rh > 85 && $t >= 80 && $t <= 87) {
                $hi += (($rh - 85) / 10) * ((87 - $t) / 5);
            }
        }
        return round(($hi - 32) * 5 / 9, 1);
    }

    /**
     * Warning level of a heat index.
     *
     * @param float $heatIndex Heat index in °C
     *
     * @return int 0 = harmless, 1 = caution, 2 = extreme caution, 3 = danger, 4 = extreme danger
     */
    private function HeatWarningLevel(float $heatIndex): int
    {
        $level = 0;
        foreach (self::HEAT_LEVELS as $limit) {
            if ($heatIndex >= $limit) {
                $level++;
            }
        }
        return $level;
    }

    /**
     * Room climate (comfort) index according to the ELV comfort display.
     *
     * @param float $temp     Temperature in °C
     * @param float $humidity Relative humidity in %
     *
     * @return int 0 = out of range, 1 = dry, 2 = comfort dry, 3 = comfort ideal, 4 = comfort humid, 5 = humid
     */
    private function ComfortIndex(float $temp, float $humidity): int
    {
        // The humidity limits apply regardless of the temperature
        if ($humidity < 25) {
            return 1;
        }
        if ($humidity > 75) {
            return 5;
        }
        $row = (int) floor($temp / 0.5) - 10;
        if ($row < 0 || $row >= count(self::COMFORT_TABLE)) {
            return 0;
        }
        $column = (int) floor($humidity - 25);
        return (int) self::COMFORT_TABLE[$row][$column];
    }

    /**
     * Thermal state with hysteresis: too cool, comfortable or too warm.
     * Beyond a limit the state changes immediately, back to comfortable only once the PMV
     * has returned by the hysteresis, in between the previous state is kept.
     *
     * @param float $pmv   Predicted mean vote
     * @param int   $state Previous thermal state
     *
     * @return int New thermal state
     */
    private function ThermalState(float $pmv, int $state): int
    {
        $upper = $this->ReadPropertyFloat('ThermalLimitUpper');
        $lower = $this->ReadPropertyFloat('ThermalLimitLower');
        $hysteresis = $this->ReadPropertyFloat('ThermalHysteresis');
        if ($pmv > $upper) {
            return self::THERMAL_WARM;
        }
        if ($pmv < $lower) {
            return self::THERMAL_COOL;
        }
        if (($state === self::THERMAL_WARM && $pmv < $upper - $hysteresis) || ($state === self::THERMAL_COOL && $pmv > $lower + $hysteresis)) {
            return self::THERMAL_COMFORTABLE;
        }
        return $state;
    }

    /**
     * Predicted mean vote (PMV) and predicted percentage of dissatisfied (PPD) according to ISO 7730 (Annex D).
     *
     * @param float $temp     Air temperature in °C
     * @param float $radiant  Mean radiant temperature in °C
     * @param float $humidity Relative humidity in %
     * @param float $clothing Clothing insulation in clo
     *
     * @return array{0:float,1:float}|null PMV (-3 cold .. +3 hot) and PPD in %, null if the iteration does not converge
     */
    private function ThermalComfort(float $temp, float $radiant, float $humidity, float $clothing): ?array
    {
        // Water vapour partial pressure in Pa
        $pa = $humidity * 10 * exp(16.6536 - 4030.183 / ($temp + 235));
        // Thermal insulation of the clothing in m²K/W
        $icl = 0.155 * $clothing;
        // Metabolic rate in W/m², no external work
        $m = $this->ReadPropertyFloat('Activity') * 58.15;
        $mw = $m;
        // Clothing area factor
        $fcl = ($icl <= 0.078) ? 1 + 1.29 * $icl : 1.05 + 0.645 * $icl;
        // Heat transfer coefficient by forced convection
        $hcf = 12.1 * sqrt($this->ReadPropertyFloat('AirVelocity'));
        $taa = $temp + 273;
        $tra = $radiant + 273;

        // Surface temperature of the clothing by iteration
        $tcla = $taa + (35.5 - $temp) / (3.5 * $icl + 0.1);
        $p1 = $icl * $fcl;
        $p2 = $p1 * 3.96;
        $p3 = $p1 * 100;
        $p4 = $p1 * $taa;
        $p5 = 308.7 - 0.028 * $mw + $p2 * pow($tra / 100, 4);
        $xn = $tcla / 100;
        $xf = $tcla / 50;
        $hc = $hcf;
        $iterations = 0;
        while (abs($xn - $xf) > 0.00015) {
            $xf = ($xf + $xn) / 2;
            $hc = max($hcf, 2.38 * pow(abs(100 * $xf - $taa), 0.25));
            $xn = ($p5 + $p4 * $hc - $p2 * pow($xf, 4)) / (100 + $p3 * $hc);
            if (++$iterations > 150) {
                return null;
            }
        }
        $tcl = 100 * $xn - 273;

        // Heat loss components: skin diffusion, sweating, latent and dry respiration, radiation, convection
        $hl1 = 3.05 * 0.001 * (5733 - 6.99 * $mw - $pa);
        $hl2 = ($mw > 58.15) ? 0.42 * ($mw - 58.15) : 0;
        $hl3 = 1.7 * 0.00001 * $m * (5867 - $pa);
        $hl4 = 0.0014 * $m * (34 - $temp);
        $hl5 = 3.96 * $fcl * (pow($xn, 4) - pow($tra / 100, 4));
        $hl6 = $fcl * $hc * ($tcl - $temp);

        $ts = 0.303 * exp(-0.036 * $m) + 0.028;
        $pmv = $ts * ($mw - $hl1 - $hl2 - $hl3 - $hl4 - $hl5 - $hl6);
        // The seven-point thermal sensation scale is only defined from -3 to +3
        $pmv = max(-3.0, min(3.0, $pmv));
        $ppd = 100 - 95 * exp(-0.03353 * pow($pmv, 4) - 0.2179 * pow($pmv, 2));
        return [round($pmv, 2), round($ppd, 1)];
    }

    /**
     * Water content of the air (absolute humidity).
     *
     * @param float $temp   Temperature in °C
     * @param float $vapour Vapour pressure in hPa
     *
     * @return float Water content in g/m³
     */
    private function WaterContent(float $temp, float $vapour): float
    {
        return pow(10, 5) * self::MOLAR_MASS_WATER / self::GAS_CONSTANT * $vapour / ($temp + 273.15);
    }
}
