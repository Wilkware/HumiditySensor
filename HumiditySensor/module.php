<?php

declare(strict_types=1);

/** Generell funktions  */
require_once __DIR__ . '/../libs/_traits.php';

/** Namespaced traits */
use Wilkware\HumiditySensor\DebugHelper;
use Wilkware\HumiditySensor\EventHelper;
use Wilkware\HumiditySensor\VariableHelper;
use Wilkware\HumiditySensor\VersionHelper;

/**
 *  CLASS HumiditySensor
 */
class HumiditySensor extends IPSModuleStrict
{
    // -------------------------------------------------------------------------
    // Traits
    // -------------------------------------------------------------------------

    use DebugHelper;
    use EventHelper;
    use VariableHelper;
    use VersionHelper;

    // -------------------------------------------------------------------------
    // Presentations
    // -------------------------------------------------------------------------

    /**
     * @var array<string,mixed> AirOrNot Hint Presentation (Value)
     */
    private const THS_PRESENTATION_AIR_OR_NOT = [
        'ICON'         => 'window-frame',
        'OPTIONS'      => '[{"Value":false,"Caption":"Don\'t ventilate!","IconActive":true,"IconValue":"window-frame","ColorActive":true,"ColorValue":5810438,"ContentColorActive":false,"ContentColorValue":-1},{"Value":true,"Caption":"Ventilate!","IconActive":true,"IconValue":"window-frame-open","ColorActive":true,"ColorValue":15948332,"ContentColorActive":false,"ContentColorValue":-1}]',
        'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
    ];

    /**
     * @var array<string,mixed> Water Content Presentation (Value)
     */
    private const THS_PRESENTATION_WATER_CONTENT = [
        'COLOR'        => 8421504,
        'DIGITS'       => 2,
        'ICON'         => 'raindrops',
        'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
        'SUFFIX'       => ' g/m³',
    ];

    /**
     * @var array<string,mixed> Presentation (Value) for the temperature
     */
    private const THS_PRESENTATION_TEMPERATURE = [
        'DIGITS'       => 1,
        'ICON'         => 'temperature-full',
        'PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
        'SUFFIX'       => ' °C',
        'USAGE_TYPE'   => 1,
    ];

    /**
     * @var array<string,mixed> Presentation (Value) for the difference between indoor and outdoor water content
     */
    private const THS_PRESENTATION_DIFFERENCE = [
        'DIGITS'           => 2,
        'ICON'             => 'plus-minus',
        'INTERVALS'        => '[{"IntervalMinValue":-500,"IntervalMaxValue":0.01,"ConstantActive":false,"ConstantValue":"%.2f %%","ConversionFactor":1,"IconActive":true,"IconValue":"window-frame-open","PrefixActive":false,"PrefixValue":"","SuffixActive":false,"SuffixValue":"","DigitsActive":true,"DigitsValue":2,"ColorActive":true,"ColorValue":32768,"ContentColorActive":false,"ContentColorValue":-1},{"IntervalMinValue":0.01,"IntervalMaxValue":500,"ConstantActive":false,"ConstantValue":"+%.2f %%","ConversionFactor":1,"IconActive":true,"IconValue":"window-frame","PrefixActive":false,"PrefixValue":"","SuffixActive":false,"SuffixValue":"","DigitsActive":true,"DigitsValue":2,"ColorActive":true,"ColorValue":8388608,"ContentColorActive":false,"ContentColorValue":-1}]',
        'INTERVALS_ACTIVE' => true,
        'PRESENTATION'     => VARIABLE_PRESENTATION_VALUE_PRESENTATION,
        'SUFFIX'           => ' %',
    ];

    /**
     * @var array<string,mixed> Threshold Presentation (Slider)
     */
    private const THS_PRESENTATION_THRESHOLD = [
        'USAGE_TYPE'          => 5,
        'THOUSANDS_SEPARATOR' => '',
        'DECIMAL_SEPARATOR'   => 'Client',
        'PERCENTAGE'          => false,
        'DIGITS'              => 0,
        'INTERVALS'           => '[]',
        'ICON'                => 'faucet-drip',
        'INTERVALS_ACTIVE'    => false,
        'MAX'                 => 250,
        'GRADIENT_TYPE'       => 0,
        'MIN'                 => 0,
        'CUSTOM_GRADIENT'     => '[]',
        'PREFIX'              => '',
        'PRESENTATION'        => VARIABLE_PRESENTATION_SLIDER,
        'STEP_SIZE'           => 10.0,
        'SUFFIX'              => '+ %',
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

        // Outdoor variables
        $this->RegisterPropertyInteger('TempOutdoor', 0);
        $this->RegisterPropertyInteger('HumyOutdoor', 0);
        // Indoor variables
        $this->RegisterPropertyInteger('TempIndoor', 0);
        $this->RegisterPropertyInteger('HumyIndoor', 0);
        // Dashboard
        $this->RegisterPropertyInteger('DashboardMessage', 0);
        $this->RegisterPropertyInteger('NotificationMessage', 0);
        $this->RegisterPropertyInteger('InstanceWebfront', 0);
        $this->RegisterPropertyInteger('ScriptMessage', 0);
        $this->RegisterPropertyString('RoomName', 'Unknown');
        $this->RegisterPropertyInteger('LifeTime', 0);
        // Settings
        $this->RegisterPropertyInteger('MessageThreshold', 100);
        $this->RegisterPropertyInteger('UpdateTimer', 15);
        $this->RegisterPropertyBoolean('CreateDewPoint', true);
        $this->RegisterPropertyBoolean('CreateWaterContent', true);
        $this->RegisterPropertyBoolean('CreateThreshold', false);
        // Update trigger
        $this->RegisterTimer('UpdateTrigger', 0, "THS_Update(\$_IPS['TARGET']);");
    }

    /**
     * This function is called when deleting the instance during operation and when updating via "Module Control".
     * The function is not called when exiting Symcon.
     *
     * @return void
     */
    public function Destroy(): void
    {
        parent::Destroy();
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

        // Update Trigger Timer
        $this->SetTimerInterval('UpdateTrigger', 1000 * 60 * $this->ReadPropertyInteger('UpdateTimer'));

        $hint = $this->TranslatePresentation(self::THS_PRESENTATION_AIR_OR_NOT, 'OPTIONS', 'Caption');

        // Ergebnis & Hinweis & Differenz
        $this->MaintainVariable('Hint', $this->Translate('Hint'), VARIABLETYPE_BOOLEAN, $hint, 1, true);
        $this->MaintainVariable('Result', $this->Translate('Result'), VARIABLETYPE_STRING, '', 2, true);
        $this->MaintainVariable('Difference', $this->Translate('Difference'), VARIABLETYPE_FLOAT, self::THS_PRESENTATION_DIFFERENCE, 3, true);
        // Taupunkt
        $create = $this->ReadPropertyBoolean('CreateDewPoint');
        $this->MaintainVariable('DewPointOutdoor', $this->Translate('Dew point outdoor'), VARIABLETYPE_FLOAT, self::THS_PRESENTATION_TEMPERATURE, 4, $create);
        $this->MaintainVariable('DewPointIndoor', $this->Translate('Dew point indoor'), VARIABLETYPE_FLOAT, self::THS_PRESENTATION_TEMPERATURE, 5, $create);
        // Wassergehalt (WaterContent)
        $create = $this->ReadPropertyBoolean('CreateWaterContent');
        $this->MaintainVariable('WaterContentOutdoor', $this->Translate('Water content outdoor'), VARIABLETYPE_FLOAT, self::THS_PRESENTATION_WATER_CONTENT, 6, $create);
        $this->MaintainVariable('WaterContentIndoor', $this->Translate('Water content indoor'), VARIABLETYPE_FLOAT, self::THS_PRESENTATION_WATER_CONTENT, 7, $create);
        // Schwellwert (Threshold)
        $create = $this->ReadPropertyBoolean('CreateThreshold');
        $this->MaintainVariable('MessageThreshold', $this->Translate('Message threshold'), VARIABLETYPE_INTEGER, self::THS_PRESENTATION_THRESHOLD, 8, $create);
        if ($create) {
            //$threshold = $this->ReadPropertyInteger('MessageThreshold');
            //$this->SetValueInteger('MessageThreshold', $threshold);
            $this->EnableAction('MessageThreshold');
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
        $this->LogDebug(__FUNCTION__, $ident . ' => ' . $value);
        // Ident == OnXxxxxYyyyy
        switch ($ident) {
            case 'MessageThreshold':
                $this->SetValueInteger($ident, $value);
                break;
        }

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
        $result = 'Ergebnis konnte nicht ermittelt werden!';
        // Daten lesen
        $state = true;

        // Temp Outdoor
        $to = $this->ReadPropertyInteger('TempOutdoor');
        if ($to != 0) {
            $to = GetValue($to);
        } else {
            $this->LogDebug(__FUNCTION__, 'Temperature Outdoor not set!');
            $state = false;
        }

        // Humidity Outdoor
        $ho = $this->ReadPropertyInteger('HumyOutdoor');
        if ($ho != 0) {
            $ho = GetValue($ho);
            // Kann man bestimmt besser lösen
            if ($ho < 1) {
                $ho = $ho * 100.;
            }
        } else {
            $this->LogDebug(__FUNCTION__, 'Humidity Outdoor not set!');
            $state = false;
        }
        $this->LogDebug(__FUNCTION__, 'Outdoor (t/f):' . $to . ' / ' . $ho);

        // Temp Indoor
        $ti = $this->ReadPropertyInteger('TempIndoor');
        if ($ti != 0) {
            $ti = GetValue($ti);
        } else {
            $this->LogDebug(__FUNCTION__, 'Temperature Indoor not set!');
            $state = false;
        }

        // Humidity Indoor
        $hi = $this->ReadPropertyInteger('HumyIndoor');
        if ($hi != 0) {
            $hi = GetValue($hi);
            // Kann man bestimmt besser lösen
            if ($hi < 1) {
                $hi = $hi * 100.;
            }
        } else {
            $this->LogDebug(__FUNCTION__, 'Humidity Indoor not set!');
            $state = false;
        }
        $this->LogDebug(__FUNCTION__, 'Indoor (t/f):' . $ti . ' / ' . $hi);

        // All okay
        if ($state == false) {
            $this->SetValueString('Result', $result);
            return;
        }

        // Minus oder Plus ;-)
        if ($to >= 0) {
            // Plustemperaturen
            $ao = 7.5;
            $bo = 237.3;
        } else {
            // Minustemperaturen
            $ao = 7.6;
            $bo = 240.7;
        }
        if ($ti >= 0) {
            // Plustemperaturen
            $ai = 7.5;
            $bi = 237.3;
        } else {
            // Minustemperaturen
            $ai = 7.6;
            $bi = 240.7;
        }

        // universelle Gaskonstante in J/(kmol*K)
        $rg = 8314.3;

        // Molekulargewicht des Wasserdampfes in kg
        $m = 18.016;

        // Umrechnung in Kelvin
        $ko = $to + 273.15;
        $ki = $ti + 273.15;

        // Berechnung Sättigung Dampfdruck in hPa
        $so = 6.1078 * pow(10, (($ao * $to) / ($bo + $to)));
        $si = 6.1078 * pow(10, (($ai * $ti) / ($bi + $ti)));

        // Dampfdruck in hPa
        $do = ($ho / 100) * $so;
        $di = ($hi / 100) * $si;

        // Berechnung Taupunkt Aussen
        $vo = log10($do / 6.1078);
        $dpo = $bo * $vo / ($ao - $vo);

        // Berechnung Taupunkt Innen
        $vi = log10($di / 6.1078);
        $dpi = $bi * $vi / ($ai - $vi);

        // Speichern Taupunkt?
        $update = $this->ReadPropertyBoolean('CreateDewPoint');
        if ($update == true) {
            $this->SetValue('DewPointOutdoor', $dpo);
            $this->SetValue('DewPointIndoor', $dpi);
        }

        // absolute Feuchtigkeit (Water Content)
        $wco = pow(10, 5) * $m / $rg * $do / $ko;
        $wci = pow(10, 5) * $m / $rg * $di / $ki;
        $this->LogDebug(__FUNCTION__, 'Absolute Feuchtigkeit (i/a):' . $wci . '/' . $wco);

        // Speichern Wassergehalt?
        $update = $this->ReadPropertyBoolean('CreateWaterContent');
        if ($update == true) {
            $this->SetValue('WaterContentOutdoor', $wco);
            $this->SetValue('WaterContentIndoor', $wci);
        }

        // Result (diff out / in)
        $wc = $wco - $wci;
        $wcy = ($wci / $wco) * 100;
        $difference = round(($wcy - 100) * 100) / 100;
        if ($wc >= 0) {
            //$difference = round((100 - $wcy) * 100) / 100; // nur postive Differenzen?
            $result = 'Lüften führt nicht zur Trocknung der Innenraumluft.';
            $hint = false;
        } elseif ($wcy <= 110) {
            $result = 'Zwar ist es innen etwas feuchter, aber es lohnt nicht zu lüften!';
            $hint = false;
        } else {
            $result = 'Lüften führt zur Trocknung der Innenraumluft!';
            $hint = true;
        }
        /*
                $this->SetValue('Result', $result);
                $this->SetValue('Hint', $hint);
         */
        $this->SetValue('Difference', $difference);

        // Umrechnung der Feuchtigkeit Innen
        $iu = round($ho * pow(10, (($ao * $to) / ($bo + $to))) / pow(10, (($ai * $ti) / ($bi + $ti))) * ($ki) / ($ko), 0);
        $this->LogDebug(__FUNCTION__, 'Umrechnung:' . $iu . '%');
        $hin = round(0.25 * $hi + 0.75 * $iu, 0);
        $this->LogDebug(__FUNCTION__, 'Neue Innenfeuchtigkeit:' . $hin . '%');

        if (abs($wci - $wco) <= 0.5) {
            $hint = false;
            $result = 'Etwa gleiche Feuchtigkeiten innen und außen!';
        }
        elseif ($wci > $wco && $ti > $to) {
            $hint = true;
            $result = 'Lüften!!';
        }
        elseif ($wci > $wco && $ti <= $to) {
            $hint = true;
            $result = 'Lüften möglich, aber es wird dadurch wärmer!!';
        }
        elseif ($hin <= 55) {
            // Wichtig ist, dass die relative Luftfeuchtigkeit in einem Bereich von 40 bis maximal 60 % liegen sollte,
            // mit der Tendenz, dass je höher die Temperatur ist, bestimmte Luftfeuchten nicht überschritten werden sollten:
            // Bei 20 °C (80%), 22 °C (70%), 24 °C (62%) und 26 °C (55%), um nur einige Beispiele zu nennen.
            // Behaglichkeitsbereich des Menschen: zwischen 45 % und maximal 55 %.
            $hint = true;
            $delta = $hin - $hi;
            if ($delta <= 5) {
                $viel = 'etwas';
            }
            else {
                $viel = 'viel';
            }
            $result = "Kurzes Lüften möglich, aber es wird dadurch $viel feuchter (ist aber bis 50-60% OK)";
        }
        elseif ($hin <= 65) {
            // Wichtig ist, dass die relative Luftfeuchtigkeit in einem Bereich von 40 bis maximal 60 % liegen sollte,
            // mit der Tendenz, dass je höher die Temperatur ist, bestimmte Luftfeuchten nicht überschritten werden sollten:
            // Bei 20 °C (80%), 22 °C (70%), 24 °C (62%) und 26 °C (55%), um nur einige Beispiele zu nennen.
            $hint = true;
            $delta = $hin - $hi;
            if ($delta <= 5) {
                $viel = 'etwas';
            }
            else {
                $viel = 'viel';
            }
            $result = "Sehr kurzes Lüften möglich, aber es wird dadurch $viel feuchter (ist aber bis 60% noch akzeptabel)";
        }
        else {
            $hint = false;
            $result = 'NICHT lüften!';
        }
        $this->LogDebug(__FUNCTION__, $result);
        $this->SetValue('Result', $result);
        $this->SetValue('Hint', $hint);

        // Threshold
        $threshold = $this->ReadPropertyInteger('MessageThreshold');
        if ($this->ReadPropertyBoolean('CreateThreshold')) {
            $threshold = $this->GetValue('MessageThreshold');
        }

        // Messages
        if ($hint == true && $difference > $threshold) {
            $dbMsg = $this->ReadPropertyInteger('DashboardMessage');
            $wfMsg = $this->ReadPropertyInteger('NotificationMessage');
            // Send
            if ($dbMsg || $wfMsg) {
                $scriptId = $this->ReadPropertyInteger('ScriptMessage');
                $room = $this->ReadPropertyString('RoomName');
                $time = $this->ReadPropertyInteger('LifeTime');
                $time = $time * 60;
                // Dashboard
                if ($dbMsg && $scriptId != 0 && IPS_ScriptExists($scriptId)) {
                    if ($time > 0) {
                        IPS_RunScriptWaitEx(
                            $scriptId,
                            ['action'       => 'add', 'text' => $room . ': ' . $result, 'expires' => time() + $time,
                                'removable' => true, 'type' => 3, 'image' => 'Ventilation', ]
                        );
                    } else {
                        IPS_RunScriptWaitEx(
                            $scriptId,
                            ['action'       => 'add', 'text' => $room . ': ' . $result,
                                'removable' => true, 'type' => 3, 'image' => 'Ventilation', ]
                        );
                    }
                }
                // Notification
                $webfrontId = $this->ReadPropertyInteger('InstanceWebfront');
                if ($wfMsg && $webfrontId != 0) {
                    if ($this->IsWebFrontVisuInstance($webfrontId)) {
                        WFC_PushNotification($webfrontId, $this->Translate('Ventilation note'), $room . ': ' . $result, 'turn', 0);
                    }
                    if ($this->IsTileVisuInstance($webfrontId)) {
                        VISU_PostNotificationEx($webfrontId, $this->Translate('Ventilation note'), $room . ': ' . $result, 'Ventilation', 'turn', 0);
                    }
                }
            }
        }
    }
}
