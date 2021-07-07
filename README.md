# Toolmatic Humitidy Sensor (Luftfeuchtigkeitssensor)

[![Version](https://img.shields.io/badge/Symcon-PHP--Modul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Product](https://img.shields.io/badge/Symcon%20Version-5.2-blue.svg)](https://www.symcon.de/produkt/)
[![Version](https://img.shields.io/badge/Modul%20Version-2.0.20210706-orange.svg)](https://github.com/Wilkware/IPSymconHumiditySensor)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Actions](https://github.com/Wilkware/IPSymconHumiditySensor/workflows/Check%20Style/badge.svg)](https://github.com/Wilkware/IPSymconHumiditySensor/actions)

Die *Toolmatic Bibliothek* ist eine kleine Tool-Sammlung im Zusammenhang mit HomeMatic/IP Geräten.  
Hauptsächlich beinhaltet sie kleine Erweiterung zur Automatisierung von Aktoren oder erleichtert das Steuern von Geräten bzw. bietet mehr Komfort bei der Bedienung.  
  
Der *Luftfeuchtigkeitssensor* berechnet anhand der Innen- und Aussentemperatur, sowie der Innen- und Aussenluftfeuchtigkeit den Wassergehalt der Luft, den Taupunkt und ermittelt so ob ein Lüften des Raumes von Vorteil wäre.

## Inhaltverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Installation](#3-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [WebFront](#6-webfront)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)
8. [Versionshistorie](#8-versionshistorie)

### 1. Funktionsumfang

* Berechnung des Wassergehaltes der Luft für Innen und Aussen
* Berechnung des Taupunktes der Luft für Innen und Aussen
* Berechnet Differenz zwischen Innen- und Aussenluftfeutigkeit
* Hinweis ob Lüften des Raumes angebracht wäre
* Liefert textuelle Erklärung für die Entscheidung
* Möglichkeit sich über Meldungsscript oder Push Notification informieren zu lassen

### 2. Voraussetzungen

* IP-Symcon ab Version 5.2

### 3. Installation

* Über den Modul Store das Modul *Toolmatic Humitidy Sensor* installieren.
* Alternativ Über das Modul-Control folgende URL hinzufügen.  
`https://github.com/Wilkware/IPSymconHumitidySensor` oder `git://github.com/Wilkware/IPSymconHumitidySensor.git`

### 4. Einrichten der Instanzen in IP-Symcon

* Unter 'Instanz hinzufügen' ist das *Luftfeuchtigkeitsssensor*-Modul (Alias: *Luftfeuchtigkeitsrechner*) unter dem Hersteller _'(Geräte)'_ aufgeführt.

__Konfigurationsseite__:

Einstellungsbereich:

> Klimadaten  ...

Name                          | Beschreibung
----------------------------- | ---------------------------------
Außentemperatur               | Temperatur (Außenklima)
Luftfeuchtigkeit außen        | Luftfeuchigkeit (Außenklima)
Innentemperatur               | Temperatur (Raumklima)
Luftfeuchtigkeit innen        | Luftfeuchigkeit (Raumklima)

> Meldungsverwaltung ...

Name                           | Beschreibung
------------------------------ | ---------------------------------
Meldung an Anzeige senden      | Auswahl ob Eintrag in die Meldungsverwaltung erfolgen soll oder nicht (Ja/Nein)
Lebensdauer der Nachricht      | Wie lange so die Meldung angezeigt werden?
Nachricht ans Webfront senden  | Auswahl ob Push-Nachricht gesendet werden soll oder nicht (Ja/Nein)
Raumname                       | Text zur eindeutigen Zuordnung des Raums
Differenz(Schwellwert)         | Schwellwert/Offset ab welchen bei Überschreitung bzw. Unterschreitung eine Meldung erfolgen soll; gerade in Wintermonaten wird der Lüftungszustand sehr schnell erreicht
Meldsungsskript                | Skript ID des Meldungsverwaltungsskripts, weiterführende Infos im Forum: [Meldungsanzeige im Webfront](https://community.symcon.de/t/meldungsanzeige-im-webfront/23473)
WebFront Instanz               | ID des Webfronts, an welches die Push-Nachrichten gesendet werden soll

> Erweiterte Einstellungen ...

Name                                             | Beschreibung
------------------------------------------------ | ---------------------------------
Aktualisierungszeit           | Aktualisierungszeitraum in Minuten
Checkbox Taupunkt             | Frage, ob die Variablen für Taupunkte angelegt werden sollen.
Checkbox Wassergehalt         | Frage, ob die Variablen für Wassergehalte angelegt werden sollen.
Checkbox Zustandsvariable     | Frage, ob eine Statusvariable für das Einstellen des Schwellwertes angelegt werden soll.

### 5. Statusvariablen und Profile

Die Statusvariablen/Timer werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

Name                 | Typ       | Beschreibung
-------------------- | --------- | ----------------
Wassergehalt Aussen  | Float     | Wassergehalt der Aussenluft
Wassergehalt Innen   | Float     | Wassergehalt der Innenluft
Taupunkt Aussen      | Float     | Taupunkt der Aussenluft
Taupunkt Innen       | Float     | Taupunkt der Innenluft
Ergebnis             | String    | Zusammenfassung des Berechnungsergebnisses
Hinweis              | Boolean   | Hinweis ob Lüften oder nicht!
Differenz            | Float     | Differenz der Luftfeute zwischen Aussen und Innen  [+]=Innen feuchter, [-]=Aussen feuchter
Schwellwert          | Integer   | Offset der auf den realen Wert addiert wird und somit eine Meldung zum Lüften "verzögert"

Folgende Profile werden angelegt:

Name                 | Typ       | Beschreibung
-------------------- | --------- | ----------------
THS.Differnce        | Float     | Differenz der Luftfeuchte in Prozent (Vorzeichenbehaftet)
THS.WaterContent     | Float     | Wassergehalt der Luft in g/m³
THS.AirOrNot         | Boolaen   | Lüften (true) oder Nicht (false)
THS.Threshold        | Integer   | Prozentualer Offsetwert für Meldungsverzögerung (0 - 250 in 10er Schritten)

### 6. WebFront

Die erzeugten Variablen können direkt ins Webfront verlingt werden.  

### 7. PHP-Befehlsreferenz

```php
void THS_Update(int $InstanzID);
```

Holt entsprechend der Konfiguration die gewählten Daten und berechnet die Werte.  
Die Funktion liefert keinerlei Rückgabewert.

__Beispiel__: `THS_Update(12345);`

```php
void THS_Duration(int $InstanzID, int $Minutes);
```

Setzt die Aktualisierungszeit (Timer) auf die neuen 'x' Minuten.  
Die Funktion liefert keinerlei Rückgabewert.

__Beispiel__: `THS_Duration(12345, 60);`  
Setzt die Wartezeit auf 60 Minuten.

```php
void THS_MessageThreshold(int $InstanzID, int $Threshold);
```

Setzt den Schwellwert ab wann eine aktive Meldung erfolgen soll.  
Die Funktion liefert keinerlei Rückgabewert.

__Beispiel__: `THS_MessageThreshold(12345, 100);`  
Setzt den Schwellwert auf 100.

### 8. Versionshistorie

v2.0.20210607

* _NEU_: Konfigurationsformular komplett überarbeitet und vereinheitlicht
* _NEU_: Meldungsverwaltung überarbeitet, erweitert und vereinheitlicht
* _NEU_: Schwellwert(Offset) kann über Webfront(RequestAction) gesetzt bzw. manipuliert werden
* _NEU_: Funktionen `THS_Threshold` und `THS_Duration` wegen Verwendung von IPS_SetProperty entfernt
* _FIX_: Übersetzungen nachgezogen
* _FIX_: Interne Bibliotheken überarbeitet und vereinheitlicht
* _FIX_: Debug Meldungen überarbeitet
* _FIX_: Dokumentation überarbeitet

v1.1.20190818

* _NEU_: Umstellung für Module Store
* _FIX_: Profile überarbeitet
* _FIX_: Dokumentation überarbeitet

v1.0.20190317

* _NEU_: Initialversion

## Entwickler

Seit nunmehr über 10 Jahren fasziniert mich das Thema Haussteuerung. In den letzten Jahren betätige ich mich auch intensiv in der IP-Symcon Community und steuere dort verschiedenste Skript und Module bei. Ihr findet mich dort unter dem Namen @pitti ;-)

[![GitHub](https://img.shields.io/badge/GitHub-@wilkware-blueviolet.svg?logo=github)](https://wilkware.github.io/)

## Spenden

Die Software ist für die nicht kommzerielle Nutzung kostenlos, über eine Spende bei Gefallen des Moduls würde ich mich freuen.

[![PayPal](https://img.shields.io/badge/PayPal-spenden-blue.svg?logo=paypal)](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=8816166)

## Lizenz

[![Licence](https://licensebuttons.net/i/l/by-nc-sa/transparent/00/00/00/88x31-e.png)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
