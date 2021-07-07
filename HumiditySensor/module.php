<?php

declare(strict_types=1);

// Allgemeine Funktionen
require_once __DIR__ . '/../libs/_traits.php';

// CLASS HumitidySensor
class HumitidySensor extends IPSModule
{
    use ProfileHelper;
    use DebugHelper;

    public function Create()
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

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        // Update Trigger Timer
        $this->SetTimerInterval('UpdateTrigger', 1000 * 60 * $this->ReadPropertyInteger('UpdateTimer'));

        // Profile "THS.AirOrNot"
        $association = [
            [0, 'Nicht Lüften!', 'Window-100', 0x00FF00],
            [1, 'Lüften!', 'Window-0', 0xFF0000],
        ];
        $this->RegisterProfile(vtBoolean, 'THS.AirOrNot', 'Window', '', '', 0, 0, 0, 0, $association);

        // Profile "THS.WaterContent"
        $association = [
            [0, '%0.2f', '', 0x808080],
        ];
        $this->RegisterProfile(vtFloat, 'THS.WaterContent', 'Drops', '', ' g/m³', 0, 0, 0, 0, $association);

        // Profile "THS.Difference"
        $association = [
            [-500, '%0.2f %%', 'Window-0', 16711680],
            [0, '%0.2f %%', 'Window-0', 16711680],
            [0.01, '+%0.2f %%', 'Window-100', 16744448],
            [10, '+%0.2f %%', 'Window-100', 32768],
        ];
        $this->RegisterProfile(vtFloat, 'THS.Difference', 'Window', '', '', 0, 0, 0, 2, $association);
        // Profil "THS.Threshold"
        $this->RegisterProfile(vtInteger, 'THS.Threshold', 'Tap', '', '+ %', 0, 250, 10, 0);

        // Ergebnis & Hinweis & Differenz
        $this->MaintainVariable('Hint', $this->Translate('Hint'), vtBoolean, 'THS.AirOrNot', 1, true);
        $this->MaintainVariable('Result', $this->Translate('Result'), vtString, '', 2, true);
        $this->MaintainVariable('Difference', $this->Translate('Difference'), vtFloat, 'THS.Difference', 3, true);
        // Taupunkt
        $create = $this->ReadPropertyBoolean('CreateDewPoint');
        $this->MaintainVariable('DewPointOutdoor', $this->Translate('Dew point outdoor'), vtFloat, '~Temperature', 4, $create);
        $this->MaintainVariable('DewPointIndoor', $this->Translate('Dew point indoor'), vtFloat, '~Temperature', 5, $create);
        // Wassergehalt (WaterContent)
        $create = $this->ReadPropertyBoolean('CreateWaterContent');
        $this->MaintainVariable('WaterContentOutdoor', $this->Translate('Water content outdoor'), vtFloat, 'THS.WaterContent', 6, $create);
        $this->MaintainVariable('WaterContentIndoor', $this->Translate('Water content indoor'), vtFloat, 'THS.WaterContent', 7, $create);
        // Schwellwert (Threshold)
        $create = $this->ReadPropertyBoolean('CreateThreshold');
        $this->MaintainVariable('MessageThreshold', $this->Translate('Message threshold'), vtInteger, 'THS.Threshold', 8, $create);
        if ($create) {
            //$threshold = $this->ReadPropertyInteger('MessageThreshold');
            //$this->SetValueInteger('MessageThreshold', $threshold);
            $this->EnableAction('MessageThreshold');
        }
    }

    /**
     * This function will be available automatically after the module is imported with the module control.
     * Using the custom prefix this function will be callable from PHP and JSON-RPC through:.
     *
     * THS_Update($id);
     */
    public function Update()
    {
        $result = 'Ergebnis konnte nicht ermittelt werden!';
        // Daten lesen
        $state = true;
        // Temp Outdoor
        $to = $this->ReadPropertyInteger('TempOutdoor');
        if ($to != 0) {
            $to = GetValue($to);
        } else {
            $this->SendDebug(__FUNCTION__, 'Temperature Outdoor not set!');
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
            $this->SendDebug(__FUNCTION__, 'Humidity Outdoor not set!');
            $state = false;
        }
        // Temp Indoor
        $ti = $this->ReadPropertyInteger('TempIndoor');
        if ($ti != 0) {
            $ti = GetValue($ti);
        } else {
            $this->SendDebug(__FUNCTION__, 'Temperature Indoor not set!');
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
            $this->SendDebug(__FUNCTION__, 'Humidity Indoor not set!');
            $state = false;
        }
        // All okay
        if ($state == false) {
            $this->SetValueString('Result', $result);
            return;
        }

        // Minus oder Plus ;-)
        if ($ti >= 0) {
            // Plustemperaturen
            $ao = 7.5;
            $bo = 237.7;
            $ai = $ao;
            $bi = $bo;
        } else {
            // Minustemperaturen
            $ao = 7.6;
            $bo = 240.7;
            $ai = $ao;
            $bi = $bo;
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
        // WaterContent
        $wco = pow(10, 5) * $m / $rg * $do / $ko;
        $wci = pow(10, 5) * $m / $rg * $di / $ki;
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
            $difference = round((100 - $wcy) * 100) / 100;
            $result = 'Lüften führt nicht zur Trocknung der Innenraumluft.';
            $hint = false;
        } elseif ($wcy <= 110) {
            $result = 'Zwar ist es innen etwas feuchter, aber es lohnt nicht zu lüften!';
            $hint = false;
        } else {
            $result = 'Lüften führt zur Trocknung der Innenraumluft!';
            $hint = true;
        }
        $this->SetValue('Result', $result);
        $this->SetValue('Hint', $hint);
        $this->SetValue('Difference', $difference);

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
                    WFC_PushNotification($webfrontId, $this->Translate('Ventilation note'), $room . ': ' . $result, 'Ventilation', 0);
                }
            }
        }
    }

    /**
     * RequestAction.
     *
     *  @param string $ident Ident.
     *  @param string $value Value.
     */
    public function RequestAction($ident, $value)
    {
        // Debug output
        $this->SendDebug(__FUNCTION__, $ident . ' => ' . $value);
        // Ident == OnXxxxxYyyyy
        switch ($ident) {
            case 'MessageThreshold':
                $this->SetValueInteger($ident, $value);
            break;
        }
        //return true;
    }

    /**
     * Update a boolean value.
     *
     * @param string $ident Ident of the boolean variable
     * @param bool   $value Value of the boolean variable
     */
    private function SetValueBoolean(string $ident, bool $value)
    {
        $id = $this->GetIDForIdent($ident);
        SetValueBoolean($id, $value);
    }

    /**
     * Update a string value.
     *
     * @param string $ident Ident of the string variable
     * @param string $value Value of the string variable
     */
    private function SetValueString(string $ident, string $value)
    {
        $id = $this->GetIDForIdent($ident);
        SetValueString($id, $value);
    }

    /**
     * Update a integer value.
     *
     * @param string $ident Ident of the integer variable
     * @param int    $value Value of the integer variable
     */
    private function SetValueInteger(string $ident, int $value)
    {
        $id = $this->GetIDForIdent($ident);
        SetValueInteger($id, $value);
    }
}
