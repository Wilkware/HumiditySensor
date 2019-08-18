[![Version](https://img.shields.io/badge/Symcon-PHP--Modul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Product](https://img.shields.io/badge/Symcon%20Version-5.0%20%3E-blue.svg)](https://www.symcon.de/produkt/)
[![Version](https://img.shields.io/badge/Modul%20Version-1.1.20190818-orange.svg)](https://github.com/Wilkware/IPSymconToolmatic)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![StyleCI](https://github.styleci.io/repos/203009208/shield?style=flat)](https://github.styleci.io/repos/76893952)

# Luftfeuchtigkeitssensor

Brechnet anhand der Innen- und Aussentemperatur, sowie der Innen- und Aussenluftfeuchtigkeit den  
Wassergehalt der Luft, den Taupunkt und ermittelt so ob ein Lüften des Raumes von Vorteil wäre.  
Wer die Meldungsverwaltung (Thema: "Meldungsanzeige im Webfront" <https://www.symcon.de/forum/threads/12115-Meldungsanzeige-im-WebFront?highlight=Meldungsverwaltung>)
kann sich über den aktuellen Stand seiner Räume darüber informieren lassen.

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
* Hinweis ob Lüften des Raumes angebracht wäre

### 2. Voraussetzungen

* IP-Symcon ab Version 5.0

### 3. Installation

* Über den Modul Store das Modul 'Humitidy Sensor' installieren.
* Alternativ Über das Modul-Control folgende URL hinzufügen.  
`https://github.com/Wilkware/IPSymconHumitidySensor` oder `git://github.com/Wilkware/IPSymconHumitidySensor.git`

### 4. Einrichten der Instanzen in IP-Symcon

* Unter "Instanz hinzufügen" ist das 'Luftfeuchtigkeitsssensor'-Modul (Alias: Luftfeuchtigkeitsrechner) unter dem Hersteller '(Sonstige)' aufgeführt.

__Konfigurationsseite__:

Name                          | Beschreibung
------------------            | ---------------------------------
Temperatur (Außenklima)       | Außentemperatur
Luftfeuchigkeit (Außenklima)  | Außenluftfeuchte
Temperatur (Raumklima)        | Innen(Raum)temperatur
Luftfeuchigkeit (Raumklima)   | Innen(Raum)luftfeuchte
Meldungsscript                | Skript ID des Meldungsverwaltungsscripts
Raumname                      | Text zur eindeutigen Zuordnung des Raums
Lebensdauer der Nachricht     | Wie lange so die Info angezeigt werden?
Aktualisierungszeit           | Aktualisierungszeitraum in Minuten
Checkbox Taupunkt             | Frage, ob die Variablen für Taupunkte angelegt werden sollen.
Checkbox Wassergehalt         | Frage, ob die Variablen für Taupunkte angelegt werden sollen.

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

Folgende Profile werden angelegt:

Name                 | Typ       | Beschreibung
-------------------- | --------- | ----------------
THS.Differnce        | Float     | Differenz der Luftfeuchte in Prozent (Vorzeichenbehaftet)
THS.WaterContent     | Float     | Wassergehalt der Luft in g/m³
THS.AirOrNot         | Boolaen   | Lüften (true) oder Nicht (false)

### 6. WebFront

Die erzeugten Variablen können direkt ins Webfront verlingt werden.  

_Hinweis:_ Das Script 'Meldungsanzeige im Webfront' (Meldungsverwaltung) wird unterstützt.

### 7. PHP-Befehlsreferenz

`void THS_Update(int $InstanzID);`
Holt entsprechend der Konfiguration die gewählten Daten und berechnet die Werte.
Die Funktion liefert keinerlei Rückgabewert.

Beispiel:
`THS_Update(12345);`

`void THS_Duration(int $InstanzID, int $Minutes);`  
Setzt die Aktualisierungszeit (Timer) auf die neuen 'x' Minuten.  
Die Funktion liefert keinerlei Rückgabewert.

Beispiel:  
`THS_Duration(12345, 60);`  
Setzt die Wartezeit auf 60 Minuten.

`void THS_MessageThreshold(int $InstanzID, int $Threshold);`  
Setzt den Schwellwert ab wann eine aktive Meldung erfolgen soll.  
Die Funktion liefert keinerlei Rückgabewert.

Beispiel:  
`THS_MessageThreshold(12345, 100);`  
Setzt den Schwellwert auf 100.

### 8. Versionshistorie

v1.1.20190818

* _NEU_: Umstellung für Module Store
* _FIX_: Dokumentation überarbeitet

v1.0.20190317

* _NEU_: Initialversion

## Entwickler

* Heiko Wilknitz ([@wilkware](https://github.com/wilkware))

## Spenden

Die Software ist für die nicht kommzerielle Nutzung kostenlos, Schenkungen als Unterstützung für den Entwickler bitte hier:  
<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=8816166" target="_blank"><img src="https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_LG.gif" border="0" /></a>

## Lizenz

[![Licence](https://licensebuttons.net/i/l/by-nc-sa/transparent/00/00/00/88x31-e.png)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
