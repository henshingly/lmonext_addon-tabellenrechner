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

## Version 1.1.3 (Dokumentations-Bugfix)

- Bugfix (siehe ewige-tabelle CHANGELOG.md 1.3.2 fuer den vollstaendigen
  Hintergrund zur systematischen Pruefung): iframe-Hinweis im Datei-Kopf
  zeigte einen direkten, durch addon/.htaccess gesperrten Pfad
  (addon/tabellenrechner/lmo-tabellenrechner.php?...). Korrigiert auf
  addon-run.php?addon=tabellenrechner&file=lmo-tabellenrechner.php&... -
  die Standalone-Erkennung selbst war bereits korrekt.

## Version 1.1.2 (Bugfix)

- Bugfix "Addon nicht gefunden:" bei jeder Ergebnisänderung/jedem Spieltag-Wechsel, wenn das Addon über eine Demo-/Wrapper-Seite (z.B. website/demo.php) per include() eingebunden wurde: die selbstreferenzierende AJAX-URL kannte bisher nur den Aufruf über addon-run.php (addon=/file=) sowie einen angenommenen Direktaufruf der Addon-Datei selbst. Bei Einbindung über eine ?name=...-basierte Wrapper-Seite zeigt SCRIPT_NAME jedoch auf die Wrapper-Datei, wodurch die gebaute AJAX-URL das für den Wrapper nötige name=-Präfix verlor und der Folgeaufruf ins Leere lief. Fix: zusätzlicher Erkennungszweig für isset($_GET['name']), der die AJAX-URL mit name=-Parameter gegen die Wrapper-Datei selbst aufbaut.

## Version 1.1.1 (KRITISCHER Bugfix)

- KRITISCHER Bugfix (gemeldet: "403 Forbidden: Ungültiges oder fehlendes CSRF-Token" bei jeder Ergebnisänderung im Was-wäre-wenn-Rechner): die Neuberechnungs-Anfrage wurde bisher mit Content-Type "application/json" gesendet (roher JSON-Body, ohne jedes CSRF-Token). PHP füllt $_POST aber NUR bei "application/x-www-form-urlencoded" oder "multipart/form-data" automatisch - requireCsrf() (zentral in frontend/bootstrap.php für JEDEN POST-Request geprüft, noch bevor der Addon-Code selbst läuft) verlangt das Token aber ausschließlich in $_POST['csrf_token']. Ein im JSON-Body mitgesendetes Token wäre also so oder so nie gesehen worden. Fix: lmo-tabellenrechner.php 1.1.1 - der Client sendet jetzt "application/x-www-form-urlencoded" mit zwei Feldern (csrf_token, results als JSON-String) statt eines rohen JSON-Bodys; das Token wird beim Seitenaufbau serverseitig als JS-Variable eingebettet (analog zu csrfField() bei normalen POST-Formularen). handleTabellenrechnerAjax() liest die Overrides jetzt aus $_POST['results'] statt aus dem rohen Request-Body.
- Hinweis: dieser Fix behebt das eindeutig identifizierte Problem (fehlendes Token). Sollte dieses Addon als iframe auf einer FREMDEN Domain eingebettet werden, könnte je nach Session-Cookie-Konfiguration des Servers (SameSite-Richtlinie) zusätzlich ein separates Cookie-Problem auftreten, das eine serverseitige Konfigurationsanpassung bräuchte - das ist unabhängig von diesem Fix.

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
