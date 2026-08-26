# Changelog: tabellenrechner-Addon (LMOnext)

Dieses Addon war bis LMOnext 1.9.0-beta Teil des Core-Pakets. Mit der
Einführung des Addon-Manager-Frameworks (Beitrag Torsten Hofmann) wurde es
als eigenständiges, self-contained Paket extrahiert.

Die vollständige Entwicklungshistorie bis zur Extraktion steht im
CHANGELOG.md des LMOnext-Kernprojekts unter den Abschnitten
`addon/tabellenrechner/*`.

## Aktuelle Version: 1.0.7

- Als eigenständiges addon.json-Paket verpackt (Templates/Sprachdateien
  jetzt lokal im Addon statt zentral im Core), installierbar über
  Administrator → Addons.

## min_core_version-Korrektur

- min_core_version von "1.4.0" auf "1.9.0" korrigiert (Copy-Paste-Rest aus
  einer älteren internen Versionierung, faktisch wirkungslos, da niedriger
  als jede real existierende LMOnext-Version - AddonManager konnte damit nie
  eine zu alte Core-Version blockieren). "1.9.0" ist die tatsächliche
  LMOnext-Core-Version, ab der der Addon-Manager überhaupt existiert.

## Version 1.1.0 (Sicherheitsüberarbeitung)

- lmo-tabellenrechner.php 1.1.0: Aufruf-Erkennung auf die neue Konstante
  LMO_ADDON_STANDALONE_CALL umgestellt (gesetzt vom neuen zentralen
  Controller /addon-run.php). Der direkte URL-Aufruf ist per
  addon/.htaccess jetzt komplett gesperrt - Einbettungen müssen ab sofort
  über /addon-run.php?addon=tabellenrechner&file=lmo-tabellenrechner.php&...
  laufen, NICHT mehr über /addon/tabellenrechner/lmo-tabellenrechner.php.
- Neues Manifest-Feld "standalone_entrypoints": ["lmo-tabellenrechner.php"].
- Asset-Pfadauflösung (trProjectRootUrlPrefix()) nutzt jetzt bevorzugt
  die vom Controller gelieferte LMO_ADDON_WEB_BASE.
- ZUSÄTZLICHER Bugfix (beim Umbau entdeckt): die AJAX-Selbstreferenz-URL
  für "Neu berechnen"/Spieltag-Wechsel wurde bisher aus dem reinen
  Dateinamen gebaut (basename($_SERVER['SCRIPT_NAME'])) - über den neuen
  Controller aufgerufen wäre das auf "addon-run.php" ohne die nötigen
  addon=/file=-Parameter gezeigt und mit einem 400-Fehler fehlgeschlagen.
  Baut die URL jetzt korrekt inkl. dieser Parameter.

**WICHTIG für bestehende Einbettungen:** URL wie oben anpassen, falls
bereits per iframe/URL extern eingebunden.
