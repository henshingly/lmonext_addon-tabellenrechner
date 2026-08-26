<?php
/**
 * Project: LMOnext
 * Filename: addon/tabellenrechner/lmo-tabellenrechner.php
 * Fileversion: 1.1.0
 *
 * PHP version 8.2
 *
 * @author    Dietmar Kersting <webmaster@liga-manager-online.org>
 * @author    Torsten Hofmann <entwickler@bastel-code.de>
 * @copyright 2026 Dietmar Kersting, Torsten Hofmann
 * @license   GPL-3.0-only
 *
 * ── Einbindung ────────────────────────────────────────────────────────────────
 *
 * Variante 1 (empfohlen) – per include() aus eigenem PHP-Code:
 *
 *   <?php
 *   $tr_liga = 3;       // Liga-ID (Pflicht)
 *   include('/PfadZuLMOnext/addon/tabellenrechner/lmo-tabellenrechner.php');
 *
 * Variante 2 – per direkter URL / IFrame:
 *
 *   <iframe src="https://.../addon/tabellenrechner/lmo-tabellenrechner.php?tr_liga=3"
 *           frameborder="0" width="860" height="700" scrolling="auto"></iframe>
 *
 * Steuerparameter (GET hat immer Vorrang vor vorher gesetzten PHP-Variablen):
 *   tr_liga       Liga-ID (Pflicht)
 *   tr_nr        Spieltag-Nummer (Standard: letzter Spieltag mit Ergebnissen)
 *   tr_template  Template-Name ohne Endung (Standard: "standard"),
 *                Datei muss unter /addon/tabellenrechner/templates/{name}.tpl.php liegen
 *
 * ── Template-Platzhalter ──────────────────────────────────────────────────────
 *
 * Das Template nutzt {PLATZHALTER} im Stil {NAME} (nicht als HTML-Kommentare,
 * um Konflikte mit verschachtelten Kommentaren zu vermeiden).
 * Wiederholungs-Bloecke: <!-- BEGIN PARTIEN --> ... <!-- END PARTIEN -->
 *                        <!-- BEGIN INHALT --> ... <!-- END INHALT -->
 *
 * ── Funktionsweise ────────────────────────────────────────────────────────────
 *
 * Der Tabellenrechner ist ein "Was-waere-wenn"-Werkzeug: Er zeigt den
 * gewaehlten Spieltag mit allen Paarungen und die aktuelle Tabelle darunter.
 * Der Besucher kann Ergebnisse eintragen (ohne Speichern), und die Tabelle
 * wird per AJAX live neu berechnet. Beim Wechsel des Spieltags bleiben
 * bereits eingegebene Ergebnisse erhalten (JS-Cache, kein Page-Reload).
 * Hoch/Runter-Pfeile neben jedem Eingabefeld erlauben das komfortable
 * Inkrementieren per Mausklick.
 */
declare(strict_types=1);

use LMOnext\Liga\LigaService;

// Wird diese Datei über den zentralen Controller addon-run.php aufgerufen
// (der einzige noch erlaubte Aufrufweg, siehe addon/.htaccess) oder per
// include()?
$trIsDirectCall = defined('LMO_ADDON_STANDALONE_CALL');

require_once __DIR__ . '/../../frontend/bootstrap.php';

// Standalone-Addon: eigene Sprachdateien explizit laden.
if (function_exists('addonManager')) {
    \addonManager()->loadLanguages('tabellenrechner');
}

// Dieses Addon ist bewusst zum Einbetten via iframe auf fremden Websites
// gedacht (siehe Docblock oben) - die von frontend/bootstrap.php gesetzten
// Frame-Schutz-Header (X-Frame-Options/CSP frame-ancestors) werden hier
// deshalb wieder entfernt, sonst würde jede Einbettung blockiert.
if (!headers_sent()) {
    header_remove('X-Frame-Options');
    header_remove('Content-Security-Policy');
}

/**
 * Berechnet das URL-Präfix zum Projekt-Root, egal ob diese Datei direkt
 * aufgerufen ODER per include() aus einer beliebig platzierten Wrapper-Datei
 * eingebunden wird (siehe miniProjectRootUrlPrefix() in lmo-minitab.php -
 * gleiche Logik, eigener Funktionsname, damit beide Addons parallel
 * eingebunden werden können, ohne sich gegenseitig zu überschreiben).
 * Ohne dieses Präfix würden Logo-Pfade wie "assets/img/teams/X.png" bei
 * Direktaufruf (Datei liegt unter addon/tabellenrechner/, zwei Ebenen tiefer
 * als der Projekt-Root) vom Browser falsch relativ zu addon/tabellenrechner/
 * aufgelöst.
 */
function trProjectRootUrlPrefix() : string
{
    static $prefix = null;
    if ($prefix !== null) {
        return $prefix;
    }

    if (defined('LMO_ADDON_WEB_BASE')) {
        $prefix = rtrim(dirname(rtrim(LMO_ADDON_WEB_BASE, '/'), 2), '/') . '/';
        return $prefix;
    }

    $projectRootDisk = rtrim(str_replace('\\', '/', dirname(__DIR__, 2)), '/');
    $scriptFilename  = str_replace('\\', '/', (string)($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $scriptName      = (string)($_SERVER['SCRIPT_NAME'] ?? '');

    if ($scriptFilename !== '' && $scriptName !== '' && str_ends_with($scriptFilename, $scriptName)) {
        $documentRootDisk = substr($scriptFilename, 0, -strlen($scriptName));
        $documentRootDisk = rtrim($documentRootDisk, '/');
        if ($documentRootDisk !== '' && str_starts_with($projectRootDisk, $documentRootDisk)) {
            $prefix = substr($projectRootDisk, strlen($documentRootDisk)) . '/';
            return $prefix;
        }
    }

    $isDirectCall = defined('LMO_ADDON_STANDALONE_CALL');
    $prefix = $isDirectCall ? '../../' : '';
    return $prefix;
}

/**
 * Logo-<img> mit korrektem URL-Präfix (siehe trProjectRootUrlPrefix()).
 * Ersetzt LigaService::renderTeamLogoImgWrapped() innerhalb dieses Addons,
 * dessen Pfade sonst nur bei Einbindung über eine Root-Seite stimmen würden.
 */
function trLogoImgWrapped(int $teamId, bool $showLogos) : string
{
    if (!$showLogos || $teamId <= 0) {
        return '';
    }
    $path = findTeamLogoPathFrontend($teamId) ?? 'assets/img/nopic-team.svg';
    $img = '<img src="' . h(trProjectRootUrlPrefix() . $path) . '" alt="" class="team-logo-inline">';
    return '<span class="st-team-logo-wrap">' . $img . '</span>';
}

// ── Parameter auslesen ──────────────────────────────────────────────────────
$ligaId = isset($_GET['tr_liga']) ? (int)$_GET['tr_liga'] : (int)($tr_liga ?? 0);
if ($ligaId <= 0) {
    if ($trIsDirectCall) {
        http_response_code(400);
        echo '<p>Parameter tr_liga (Liga-ID) fehlt.</p>';
    }
    return;
}

$trTemplate = isset($_GET['tr_template']) ? (string)$_GET['tr_template'] : (string)($tr_template ?? 'standard');
$trTemplate = str_replace('..', '', basename($trTemplate)); // Path-Traversal-Schutz

$allSpieltage = LigaService::getAllSpieltage($ligaId);
if (empty($allSpieltage)) {
    if ($trIsDirectCall) {
        echo '<p>Keine Spieltage fuer Liga ' . $ligaId . ' gefunden.</p>';
    }
    return;
}

$maxNr = LigaService::getMaxSpieltagNummer($allSpieltage);
$isKO  = LigaService::getLigaType($ligaId) === 1;

// ── AJAX-Handler ───────────────────────────────────────────────────────────
if (isset($_GET['ajax'])) {
    match ($_GET['ajax']) {
        'recalc'   => handleTabellenrechnerAjax($ligaId, $allSpieltage, $trTemplate),
        'spieltag' => handleSpieltagAjax($ligaId, $allSpieltage, $trTemplate),
        default    => null,
    };
}

// ── Spieltag bestimmen ──────────────────────────────────────────────────────
$trNr = isset($_GET['tr_nr']) ? (int)$_GET['tr_nr'] : 0;
if ($trNr < 1) {
    $spieltag = LigaService::getLatestSpieltagWithResults($allSpieltage);
    $trNr = $spieltag !== null ? (int)$spieltag['nummer'] : 1;
}
if ($trNr > $maxNr) {
    $trNr = $maxNr;
}

// ── Direct-Call: HTML-Geruest ───────────────────────────────────────────────
if ($trIsDirectCall) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>' . "\n" . '<html><head><meta charset="utf-8">'
        . '<title>Tabellenrechner</title>'
        . '<style>html,body{margin:0;padding:8px;background:transparent;}</style>'
        . '</head><body>' . "\n";
}

echo renderTabellenrechnerView($ligaId, $allSpieltage, $trNr, $isKO, $maxNr, $trTemplate);

if ($trIsDirectCall) {
    echo "\n</body></html>";
}

// ════════════════════════════════════════════════════════════════════════════
//  Funktionen
// ════════════════════════════════════════════════════════════════════════════

/**
 * Laedt das Template, extrahiert die Wiederholungs-Bloecke und gibt
 * Skeleton + Row-Templates zurueck.
 */
function trLoadTemplate(string $templateName) : array
{
    $templatePath = __DIR__ . '/templates/' . $templateName . '.tpl.php';
    if (!is_file($templatePath)) {
        $templatePath = __DIR__ . '/templates/standard.tpl.php';
    }
    $src = (string)file_get_contents($templatePath);

    // Partien-Block extrahieren
    $partienTemplate = '';
    if (preg_match('/<!-- BEGIN PARTIEN -->(.*?)<!-- END PARTIEN -->/s', $src, $m)) {
        $partienTemplate = $m[1];
        $src = preg_replace('/<!-- BEGIN PARTIEN -->.*?<!-- END PARTIEN -->/s', '{PARTIEN_ROWS}', $src);
    }

    // Tabellen-Zeilen-Block extrahieren
    $rowTemplate = '';
    if (preg_match('/<!-- BEGIN INHALT -->(.*?)<!-- END INHALT -->/s', $src, $m)) {
        $rowTemplate = $m[1];
        $src = preg_replace('/<!-- BEGIN INHALT -->.*?<!-- END INHALT -->/s', '{STANDINGS_ROWS}', $src);
    }

    return [
        'skeleton'        => $src,
        'partienRowTpl'   => $partienTemplate,
        'standingsRowTpl' => $rowTemplate,
    ];
}

/**
 * Baut den Spieltag-Picker (Select-Dropdown).
 * Kein onchange-location.href mehr — das Switching laeuft per AJAX,
 * damit eingegebene Ergebnisse erhalten bleiben.
 */
function trRenderSpieltagPicker(array $allSpieltage, int $currentNr) : string
{
    if (count($allSpieltage) <= 1) {
        return '';
    }

    $options = '';
    foreach ($allSpieltage as $st) {
        $nr      = (int)$st['nummer'];
        $label   = h(tf('liga_label_pick_matchday') . ' ' . $nr);
        $selAttr = $nr === $currentNr ? ' selected' : '';
        $options .= '<option value="' . $nr . '"' . $selAttr . '>' . $label . '</option>';
    }

    return '<div class="tr-spieltag-nav">'
        . '<label for="tr-spieltag-select">' . h(tf('liga_label_pick_matchday')) . '</label>'
        . '<select id="tr-spieltag-select">' . $options . '</select>'
        . '</div>';
}

/**
 * Rendert die Partien-Zeilen-HTML fuer einen Spieltag.
 * Zentral ausgelagert, damit sowohl der initiale Render als auch der
 * AJAX-Spieltag-Switch dieselbe Logik verwenden.
 */
function trRenderPartienRows(array $partien, ?int $favTeamId, string $partienRowTpl) : string
{
    $partienRowsHtml = '';
    foreach ($partien as $p) {
        $pId   = (int)($p['id'] ?? 0);
        $hId   = (int)($p['heim_id'] ?? 0);
        $gId   = (int)($p['gast_id'] ?? 0);
        $hName = $p['heim_name'] ?? '';
        $gName = $p['gast_name'] ?? '';
        $hTore = $p['h_tore'] ?? null;
        $gTore = $p['g_tore'] ?? null;
        $datum = !empty($p['zeit']) ? date('d.m.Y', (int)strtotime($p['zeit'])) : '';

        $heimClass = ($favTeamId !== null && $hId === $favTeamId) ? ' fav-team' : '';
        $gastClass = ($favTeamId !== null && $gId === $favTeamId) ? ' fav-team' : '';

        $hVal = $hTore !== null ? (string)(int)$hTore : '';
        $gVal = $gTore !== null ? (string)(int)$gTore : '';

        $replacements = [
            '{PARTIE_ID}'  => (string)$pId,
            '{DATUM}'      => h($datum),
            '{HEIM}'       => h($hName),
            '{GAST}'       => h($gName),
            '{HEIM_CLASS}' => $heimClass,
            '{GAST_CLASS}' => $gastClass,
            '{H_TORE}'     => h($hVal),
            '{G_TORE}'     => h($gVal),
        ];
        $partienRowsHtml .= strtr($partienRowTpl, $replacements);
    }
    return $partienRowsHtml;
}

/**
 * Rendert die Tabellenrechner-View.
 */
function renderTabellenrechnerView(int $ligaId, array $allSpieltage, int $trNr, bool $isKO, int $maxNr, string $trTemplate) : string
{
    if ($isKO) {
        return '<p style="font-family:sans-serif;color:#697182;padding:12px">'
            . h(tf('liga_tabellenrechner_no_ko')) . '</p>';
    }

    $opts       = LigaService::getLigaOptions($ligaId);
    $teams      = LigaService::getLigaTeamsList($ligaId);
    $allPartien = LigaService::getAllLigaPartien($allSpieltage);
    $favTeamId  = LigaService::resolveTeamNumberToId($ligaId, (int)($opts['favTeam'] ?? 0));
    $ligaName   = trGetLigaName($ligaId);

    $tpl = trLoadTemplate($trTemplate);

    // ── Spieltag-Picker ────────────────────────────────────────────────────
    $picker = trRenderSpieltagPicker($allSpieltage, $trNr);

    // ── Partien des gewaehlten Spieltags ──────────────────────────────────
    $spieltag = LigaService::getSpieltagByNummer($allSpieltage, $trNr);
    $partien  = $spieltag !== null ? LigaService::getSpieltagPartien((int)$spieltag['id']) : [];
    $partien  = array_values(array_filter($partien, static fn(array $p) => !LigaService::partieIsEmptyPlaceholder($p)));

    $partienRowsHtml = trRenderPartienRows($partien, $favTeamId, $tpl['partienRowTpl']);

    // ── Tabelle (initial) ──────────────────────────────────────────────────
    $tabelleHtml = renderTabellenrechnerTabelle($ligaId, $allPartien, $opts, $teams, $favTeamId, $maxNr, $tpl['standingsRowTpl']);

    // ── JavaScript ────────────────────────────────────────────────────────
    // Selbstreferenzierende AJAX-URL für "Neu berechnen"/Spieltag-Wechsel:
    // über den zentralen Controller addon-run.php (der einzige noch
    // erlaubte Aufrufweg) muss diese URL zwingend die addon=/file=
    // Parameter mitführen, sonst bricht der AJAX-Folgeaufruf mit einem
    // 400-Fehler ab (siehe addon-run.php).
    if (defined('LMO_ADDON_STANDALONE_CALL') && isset($_GET['addon'], $_GET['file'])) {
        $self    = 'addon-run.php?addon=' . rawurlencode((string)$_GET['addon']) . '&file=' . rawurlencode((string)$_GET['file']);
        $selfSep = '&';
    } else {
        $self    = basename($_SERVER['SCRIPT_NAME'] ?? 'lmo-tabellenrechner.php');
        $selfSep = '?';
    }
    $recalcUrl   = $self . $selfSep . 'tr_liga=' . $ligaId . '&ajax=recalc&tr_template=' . urlencode($trTemplate);
    $spieltagUrl = $self . $selfSep . 'tr_liga=' . $ligaId . '&ajax=spieltag&tr_template=' . urlencode($trTemplate);
    $recalcJs   = json_encode($recalcUrl,   JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $spieltagJs = json_encode($spieltagUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $trNrJs     = json_encode($trNr, JSON_UNESCAPED_SLASHES);

    $js = <<<HTML
<script>
(function(){
  "use strict";
  var recalcUrl   = {$recalcJs};
  var spieltagUrl  = {$spieltagJs};
  var currentNr   = {$trNrJs};
  var debounceTimer = null;

  // Cache aller Benutzer-Overrides ueber alle Spieltage hinweg.
  // Schluessel = Partie-ID, Wert = { h: int, g: int }
  var allOverrides = {};

  // ── Eingaben sammeln (nur sichtbare Inputs, aber allOverrides
  //    enthaelt auch Overrides von anderen Spieltagen) ─────────────────────
  function syncVisibleToCache() {
    var inputs = document.querySelectorAll('.tr-input');
    inputs.forEach(function(inp) {
      var pid = inp.getAttribute('data-pid');
      var side = inp.getAttribute('data-side');
      var val = inp.value.trim();
      if (val === '') {
        if (allOverrides[pid]) { delete allOverrides[pid][side]; }
      } else {
        if (!allOverrides[pid]) { allOverrides[pid] = {}; }
        allOverrides[pid][side] = parseInt(val, 10);
      }
      if (allOverrides[pid] && !allOverrides[pid].h && !allOverrides[pid].g) {
        delete allOverrides[pid];
      }
    });
  }

  function recalculate() {
    syncVisibleToCache();
    var body = document.querySelector('.tr-tabelle-body');
    if (!body) { return; }
    body.style.opacity = '0.4';

    fetch(recalcUrl, {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({results: allOverrides})
    })
    .then(function(r) { return r.text(); })
    .then(function(html) {
      body.innerHTML = html;
      body.style.opacity = '1';
    })
    .catch(function() {
      body.style.opacity = '1';
    });
  }

  function scheduleRecalc() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(recalculate, 350);
  }

  // ── Eingabe per Tastatur ───────────────────────────────────────────────
  document.addEventListener('input', function(e) {
    if (!e.target.classList || !e.target.classList.contains('tr-input')) { return; }
    scheduleRecalc();
  });

  // ── Spinner-Pfeile (Hoch/Runter) ───────────────────────────────────────
  document.addEventListener('click', function(e) {
    if (!e.target.classList || !e.target.classList.contains('tr-spin')) { return; }
    var wrap = e.target.closest('.tr-input-wrap');
    if (!wrap) { return; }
    var input = wrap.querySelector('.tr-input');
    if (!input) { return; }
    var val = parseInt(input.value, 10);
    if (isNaN(val)) { val = 0; }
    if (e.target.classList.contains('tr-spin-up')) {
      input.value = Math.min(199, val + 1);
    } else {
      input.value = Math.max(0, val - 1);
    }
    input.dispatchEvent(new Event('input', {bubbles: true}));
  });

  // ── Spieltag-Wechsel per AJAX (Ergebnisse bleiben erhalten) ────────────
  function switchSpieltag(nr) {
    syncVisibleToCache();
    var tbody = document.querySelector('.tr-table tbody');
    if (!tbody) { return; }
    tbody.style.opacity = '0.4';

    fetch(spieltagUrl + '&tr_nr=' + nr)
    .then(function(r) { return r.text(); })
    .then(function(html) {
      tbody.innerHTML = html;
      tbody.style.opacity = '1';
      currentNr = parseInt(nr, 10);
      applyOverrides();
      recalculate();
    })
    .catch(function() {
      tbody.style.opacity = '1';
    });
  }

  // Cached Overrides auf sichtbare Inputs anwenden (nach Spieltag-Wechsel)
  function applyOverrides() {
    Object.keys(allOverrides).forEach(function(pid) {
      var ov = allOverrides[pid];
      var hInput = document.querySelector('.tr-h[data-pid="' + pid + '"]');
      var gInput = document.querySelector('.tr-g[data-pid="' + pid + '"]');
      if (hInput && ov.h !== undefined && ov.h !== null) { hInput.value = ov.h; }
      if (gInput && ov.g !== undefined && ov.g !== null) { gInput.value = ov.g; }
    });
  }

  var select = document.getElementById('tr-spieltag-select');
  if (select) {
    select.addEventListener('change', function() {
      switchSpieltag(this.value);
    });
  }

  // ── Zuruecksetzen ──────────────────────────────────────────────────────
  var resetBtn = document.querySelector('.tr-reset-btn');
  if (resetBtn) {
    resetBtn.addEventListener('click', function() {
      var inputs = document.querySelectorAll('.tr-input');
      inputs.forEach(function(inp) {
        var pid = inp.getAttribute('data-pid');
        var orig = inp.getAttribute('data-orig');
        inp.value = orig !== null ? orig : '';
        // Override fuer diese Partie aus dem Cache loeschen
        if (allOverrides[pid]) {
          delete allOverrides[pid];
        }
      });
      recalculate();
    });
  }
})();
</script>
HTML;

    // ── Skeleton zusammenbauen ────────────────────────────────────────────
    $skeleton = $tpl['skeleton'];
    $skeleton = str_replace('{LIGA_NAME}',      h($ligaName), $skeleton);
    $skeleton = str_replace('{TR_INFO}',         h(tf('liga_tabellenrechner_info')), $skeleton);
    $skeleton = str_replace('{SPIELTAG_NAV}',    $picker, $skeleton);
    $skeleton = str_replace('{PARTIEN_ROWS}',    $partienRowsHtml, $skeleton);
    $skeleton = str_replace('{RESET_LABEL}',     h(tf('liga_tabellenrechner_reset')), $skeleton);
    $skeleton = str_replace('{STANDINGS_ROWS}',  $tabelleHtml, $skeleton);
    $skeleton = str_replace('{FUSSNOTEN}',        '', $skeleton);
    $skeleton = str_replace('{COPYRIGHT}',       LigaService::renderCopyrightNotice('tabellenrechner'), $skeleton);

    return $skeleton . $js;
}

/**
 * Holt den Liga-Namen aus der Datenbank.
 */
function trGetLigaName(int $ligaId) : string
{
    try {
        $db = getDB();
        $stmt = $db->prepare('SELECT name FROM ' . tbl('liga') . ' WHERE id = ?');
        $stmt->execute([$ligaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? (string)$row['name'] : ('Liga ' . $ligaId);
    } catch (\Throwable) {
        return 'Liga ' . $ligaId;
    }
}

/**
 * Berechnet die Tabelle mit optional modifizierten Partien und gibt die
 * Tabellenzeilen-HTML zurueck. Wird fuer den initialen Render und den
 * AJAX-Recall verwendet.
 */
function renderTabellenrechnerTabelle(
    int $ligaId,
    array $allPartien,
    array $opts,
    array $teams,
    ?int $favTeamId,
    int $maxNr,
    string $rowTemplate
) : string {
    $nr = $maxNr;
    $partien = $allPartien;
    if ($maxNr > 0) {
        $partien = array_values(array_filter($partien, static fn(array $p) => (int)($p['_spieltag_nummer'] ?? 0) <= $nr));
    }

    $rows = LigaService::computeStandings($teams, $partien, $opts, $ligaId, 'overall', $nr);
    $totalTeams      = count($rows);
    $showMinuspunkte = ($opts['MinusPoints'] ?? '0') === '1';
    $footnoteNrs     = LigaService::assignStrafFootnotes($rows);
    $showLogos       = ($opts['ShowLogos'] ?? '0') === '1';
    $trendByTeam     = LigaService::computePositionTrend($teams, $partien, $opts, $ligaId, 'overall');

    $rowsHtml = '';
    foreach ($rows as $i => $r) {
        $diff        = $r['tore_h'] - $r['tore_g'];
        $markerColor = LigaService::computeStandingsMarkerColor($i, $totalTeams, $opts);
        $tid         = (int)$r['id'];
        $rowStyle    = $markerColor !== '' ? 'border-left:4px solid ' . h($markerColor) . ';' : '';
        $teamClass   = ($favTeamId !== null && $r['id'] === $favTeamId) ? ' fav-team' : '';

        $trend = $trendByTeam[$tid] ?? ['direction' => 'same', 'delta' => 0];
        $trendHtml = match ($trend['direction']) {
            'up'   => '<span class="trend-arrow trend-up" title="+' . $trend['delta'] . '">&#9650;</span>',
            'down' => '<span class="trend-arrow trend-down" title="' . $trend['delta'] . '">&#9660;</span>',
            default => '<span class="trend-arrow trend-same">&ndash;</span>',
        };

        $pkt = $showMinuspunkte ? ($r['pkt'] . ':' . $r['minuspunkte']) : (string)$r['pkt'];

        $replacements = [
            '{PLATZ}'         => (string)($i + 1),
            '{ROW_STYLE}'     => h($rowStyle),
            '{TEAM_CLASS}'    => $teamClass,
            '{LOGO}'          => trLogoImgWrapped($tid, $showLogos),
            '{TEAM}'          => h($r['name']),
            '{STRAF_HINWEIS}' => LigaService::renderStrafHinweis($r, $footnoteNrs[$tid] ?? 0),
            '{SP}'            => (string)$r['sp'],
            '{S}'             => (string)$r['s'],
            '{U}'             => (string)$r['u'],
            '{N}'             => (string)$r['n'],
            '{TORE}'          => $r['tore_h'] . ':' . $r['tore_g'],
            '{DIFF}'          => ($diff > 0 ? '+' : '') . $diff,
            '{DIFF_CLASS}'    => $diff > 0 ? 'diff-pos' : ($diff < 0 ? 'diff-neg' : ''),
            '{PKT}'           => h($pkt),
            '{TREND}'         => $trendHtml,
        ];
        $rowsHtml .= strtr($rowTemplate, $replacements);
    }

    $footnotes = LigaService::renderStrafFootnotes($rows, $footnoteNrs);

    return $rowsHtml . $footnotes;
}

/**
 * AJAX: Spieltag-Wechsel — liefert nur die Partien-<tr>-Zeilen fuer den
 * angeforderten Spieltag. Die Tabelle wird im Frontend per recalc-AJAX
 * separat aktualisiert.
 */
function handleSpieltagAjax(int $ligaId, array $allSpieltage, string $trTemplate) : void
{
    $trNr = isset($_GET['tr_nr']) ? (int)$_GET['tr_nr'] : 1;
    $spieltag = LigaService::getSpieltagByNummer($allSpieltage, $trNr);
    $partien = $spieltag !== null ? LigaService::getSpieltagPartien((int)$spieltag['id']) : [];
    $partien = array_values(array_filter($partien, static fn(array $p) => !LigaService::partieIsEmptyPlaceholder($p)));

    $opts      = LigaService::getLigaOptions($ligaId);
    $favTeamId = LigaService::resolveTeamNumberToId($ligaId, (int)($opts['favTeam'] ?? 0));

    $tpl = trLoadTemplate($trTemplate);
    echo trRenderPartienRows($partien, $favTeamId, $tpl['partienRowTpl']);
    exit;
}

/**
 * AJAX: Live-Neuberechnung der Tabelle mit allen Overrides aus allen
 * Spieltagen (allOverrides im JS-Cache).
 */
function handleTabellenrechnerAjax(int $ligaId, array $allSpieltage, string $trTemplate) : void
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    $overrides = is_array($data['results'] ?? null) ? $data['results'] : [];

    $opts       = LigaService::getLigaOptions($ligaId);
    $teams      = LigaService::getLigaTeamsList($ligaId);
    $allPartien = LigaService::getAllLigaPartien($allSpieltage);
    $maxNr      = LigaService::getMaxSpieltagNummer($allSpieltage);
    $favTeamId  = LigaService::resolveTeamNumberToId($ligaId, (int)($opts['favTeam'] ?? 0));

    // Overrides auf alle Partien anwenden (Partie-ID ist ueber Spieltage hinweg eindeutig)
    foreach ($allPartien as &$p) {
        $pId = (int)($p['id'] ?? 0);
        if (isset($overrides[$pId])) {
            $ov = $overrides[$pId];
            $hSet = isset($ov['h']) && is_numeric($ov['h']);
            $gSet = isset($ov['g']) && is_numeric($ov['g']);
            if ($hSet) { $p['h_tore'] = (int)$ov['h']; }
            if ($gSet) { $p['g_tore'] = (int)$ov['g']; }
            if ($hSet !== $gSet) {
                $p['h_tore'] = null;
                $p['g_tore'] = null;
            }
        }
    }
    unset($p);

    $tpl = trLoadTemplate($trTemplate);

    $standingsHtml = renderTabellenrechnerTabelle($ligaId, $allPartien, $opts, $teams, $favTeamId, $maxNr, $tpl['standingsRowTpl']);

    echo '<div class="tr-card">'
        . '<table class="tr-standings">'
        . '<thead><tr>'
        . '<th class="st-platz">Pl.</th>'
        . '<th class="st-trend"></th>'
        . '<th class="st-team">Team</th>'
        . '<th>Sp</th><th>S</th><th>U</th><th>N</th>'
        . '<th>Tore</th><th>Diff</th><th>Pkt</th>'
        . '</tr></thead><tbody>'
        . $standingsHtml
        . '</tbody></table>'
        . '</div>';
    exit;
}
