# Luftfeuchtigkeitssensor (Humitidy Sensor)

[![Home](https://img.shields.io/badge/Home-wilkware.de-0b1830.svg?style=flat-square)](https://wilkware.de/module/luftfeuchtigkeitssensor/)
[![Version](https://img.shields.io/badge/Symcon-PHP--Modul-red.svg?style=flat-square)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Product](https://img.shields.io/badge/Symcon%20Version-6.4-blue.svg?style=flat-square)](https://www.symcon.de/produkt/)
[![Version](https://img.shields.io/badge/Modul%20Version-3.0.20221204-orange.svg?style=flat-square)](https://github.com/Wilkware/HumiditySensor)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg?style=flat-square)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Actions](https://img.shields.io/github/actions/workflow/status/wilkware/HumiditySensor/ci.yml?branch=main&label=CI&style=flat-square)](https://github.com/Wilkware/HumiditySensor/actions)

Das Modul Luftfeuchtigkeitssensor (Humitidy Sensor) berechnet anhand der Innen- und Aussentemperatur, sowie der Innen- und Aussenluftfeuchtigkeit den Wassergehalt der Luft, den Taupunkt und ermittelt so ob ein Lüften des Raumes von Vorteil wäre.

## Inhaltverzeichnis

1. [Funktionsumfang](#user-content-1-funktionsumfang)
2. [Voraussetzungen](#user-content-2-voraussetzungen)
3. [Installation](#user-content-3-installation)
4. [Einrichtung](#user-content-4-einrichtung)
5. [Statusvariablen](#user-content-5-statusvariablen)
6. [Darstellungen](#user-content-6-darstellungen)
7. [Visualisierung](#user-content-7-visualisierung)
8. [Befehlsreferenz](#user-content-8-befehlsreferenz)
9. [Versionshistorie](#user-content-9-versionshistorie)

### 1. Funktionsumfang

* Berechnung des Wassergehaltes der Luft für Innen und Aussen
* Berechnung des Taupunktes der Luft für Innen und Aussen
* Berechnet Differenz zwischen Innen- und Aussenluftfeutigkeit
* Hinweis ob Lüften des Raumes angebracht wäre
* Liefert textuelle Erklärung für die Entscheidung
* Möglichkeit sich über Meldungsscript oder Push Notification informieren zu lassen

### 2. Voraussetzungen

* Symcon ab Version 6.4

### 3. Installation

* Über den Modul Store das Modul _Luftfeuchtigkeitssensor_ installieren.
* Alternativ über das Modul Control folgende URL hinzufügen.  
`https://github.com/Wilkware/HumiditySensor` oder `git://github.com/Wilkware/HumiditySensor.git`

### 4. Einrichtung

* Unter 'Instanz hinzufügen' ist das _Luftfeuchtigkeitssensor_-Modul (Alias: _Luftfeuchtigkeitsrechner_) unter dem Hersteller '(Geräte)' aufgeführt.

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

### 5. Statusvariablen

Die Statusvariablen werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

Name                 | Typ     | Beschreibung
-------------------- | ------- | ------------------------------
Wassergehalt Aussen  | Float   | Wassergehalt der Aussenluft
Wassergehalt Innen   | Float   | Wassergehalt der Innenluft
Taupunkt Aussen      | Float   | Taupunkt der Aussenluft
Taupunkt Innen       | Float   | Taupunkt der Innenluft
Ergebnis             | String  | Zusammenfassung des Berechnungsergebnisses
Hinweis              | Boolean | Hinweis ob Lüften oder nicht!
Differenz            | Float   | Differenz der Luftfeuchte zwischen Aussen und Innen  [+]=Innen feuchter, [-]=Aussen feuchter
Schwellwert          | Integer | Offset der auf den realen Wert addiert wird und somit eine Meldung zum Lüften "verzögert"

_Hinweis:_ Die Variablen für Taupunkte, Wassergehalte und den Schwellwert werden nur angelegt, wenn die entsprechende Checkbox unter 'Erweiterte Einstellungen' aktiviert ist.

### 6. Darstellungen

Die Darstellungen werden direkt an den Statusvariablen hinterlegt, es werden keine Profile angelegt.

Variable             | Darstellung   | Werte
-------------------- | ------------- | ------------------------------
Wassergehalt Aussen  | Wertanzeige   | g/m³ (2 Nachkommastellen)
Wassergehalt Innen   | Wertanzeige   | g/m³ (2 Nachkommastellen)
Taupunkt Aussen      | Wertanzeige   | °C (1 Nachkommastelle)
Taupunkt Innen       | Wertanzeige   | °C (1 Nachkommastelle)
Hinweis              | Wertanzeige   | Nicht lüften! (false), Lüften! (true)
Differenz            | Wertanzeige   | % (2 Nachkommastellen, vorzeichenbehaftet)
Schwellwert          | Schieberegler | 0 – 250 % (Schrittweite 10)

### 7. Visualisierung

Die erzeugten Variablen können direkt in die Visualisierung verlingt werden.  

### 8. Befehlsreferenz

```php
void THS_Update(int $InstanzID);
```

Holt entsprechend der Konfiguration die gewählten Daten und berechnet die Werte.  
Die Funktion liefert keinerlei Rückgabewert.

__Beispiel__: `THS_Update(12345);`

### 9. Versionshistorie

v3.0.20221204

* _NEU_: Konfigurationsformular überarbeitet und vereinheitlicht
* _NEU_: Kompatibilität auf 6.0 hoch gesetzt
* _FIX_: Interne Bibliotheken überarbeitet und vereinheitlicht
* _FIX_: Dokumentation überarbeitet

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

Seit nunmehr über 10 Jahren fasziniert mich das Thema Haussteuerung. In den letzten Jahren betätige ich mich auch intensiv in der Symcon Community und steuere dort verschiedenste Skript und Module bei. Ihr findet mich dort unter dem Namen @pitti ;-)

[![GitHub](https://img.shields.io/badge/GitHub-@wilkware-181717.svg?style=for-the-badge&logo=github)](https://wilkware.github.io/)

## Spenden

Die Software ist für die nicht kommerzielle Nutzung kostenlos, über eine Spende bei Gefallen des Moduls würde ich mich freuen.

[![PayPal](https://img.shields.io/badge/PayPal-spenden-00457C.svg?style=for-the-badge&logo=paypal)](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=8816166)

## Lizenz

Namensnennung - Nicht-kommerziell - Weitergabe unter gleichen Bedingungen 4.0 International

[![Licence](https://img.shields.io/badge/License-CC_BY--NC--SA_4.0-EF9421.svg?style=for-the-badge&logo=creativecommons)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
