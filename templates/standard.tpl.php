<style>
.tr-wrap{font-family:'Segoe UI',system-ui,-apple-system,sans-serif;color:#1f2430;max-width:860px;margin:0 auto}
.tr-title{font-size:1.15rem;font-weight:700;margin:0 0 4px}
.tr-info{font-size:.82rem;color:#697182;margin:0 0 12px;padding:8px 12px;background:#f8fafc;border-radius:6px;border:1px solid #e3e7ee}
.tr-spieltag-nav{display:flex;flex-wrap:wrap;align-items:center;gap:6px;margin:0 0 12px}
.tr-spieltag-nav label{font-size:.8rem;color:#697182}
.tr-spieltag-nav select{font-size:.8rem;padding:3px 8px;border:1px solid #d1d5e0;border-radius:4px;background:#fff;color:#1f2430}
.tr-card{background:#fff;border:1px solid #e3e7ee;border-radius:8px;overflow:hidden;margin:0 0 12px}
.tr-table{width:100%;border-collapse:collapse;font-size:.85rem}
.tr-table th{background:#f4f6f9;color:#697182;font-weight:600;text-align:left;padding:6px 10px;border-bottom:1px solid #e3e7ee;white-space:nowrap}
.tr-table th.col-ergebnis,.tr-table td.tr-ergebnis-cell{text-align:center;white-space:nowrap}
.tr-table td{padding:5px 10px;border-top:1px solid #f0f2f7}
.tr-table tr:hover td{background:#f8fafc}
.tr-table .fav-team{font-weight:700}
.tr-table .col-datum{color:#9098a8;width:1%;white-space:nowrap}
.tr-table .col-heim{text-align:right;white-space:nowrap}
.tr-table .col-gast{text-align:left;white-space:nowrap}

/* Spinner-Input */
.tr-input-wrap{display:inline-flex;align-items:center}
.tr-input{width:2.4em;text-align:center;padding:3px 0;border:1px solid #d1d5e0;border-radius:4px;font-size:.9rem;background:#fff;color:#1f2430;font-family:inherit}
.tr-input:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 2px rgba(37,99,235,.15)}
.tr-input::-webkit-inner-spin-button,.tr-input::-webkit-outer-spin-button{-webkit-appearance:none;margin:0}
.tr-input[type=number]{-moz-appearance:textfield}
.tr-spinner{display:inline-flex;flex-direction:column;margin-left:2px}
.tr-spin{border:1px solid #d1d5e0;background:#f8fafc;color:#697182;font-size:.5rem;padding:0 3px;cursor:pointer;line-height:1.2;user-select:none}
.tr-spin:hover{color:#2563eb;border-color:#2563eb;background:#eef2ff}
.tr-spin:active{background:#dbeafe}
.tr-spin-up{border-radius:3px 3px 0 0}
.tr-spin-down{border-radius:0 0 3px 3px;border-top:none}
.tr-doppelpunkt{margin:0 4px;font-weight:600;color:#697182}

.tr-reset-btn{display:inline-block;padding:6px 16px;border:1px solid #d1d5e0;border-radius:6px;background:#fff;color:#1f2430;font-size:.82rem;cursor:pointer;margin:0 0 12px;transition:border-color .15s,color .15s}
.tr-reset-btn:hover{border-color:#2563eb;color:#2563eb}
.tr-tabelle-section{margin-top:8px}
.tr-tabelle-body{transition:opacity .15s}
.tr-standings{width:100%;border-collapse:collapse;font-size:.85rem}
.tr-standings th{background:#f4f6f9;color:#697182;font-weight:600;text-align:center;padding:5px 8px;border-bottom:1px solid #e3e7ee;white-space:nowrap}
.tr-standings th.st-team{text-align:left}
.tr-standings td{padding:5px 8px;border-top:1px solid #f0f2f7;text-align:center;white-space:nowrap}
.tr-standings td.st-platz{font-weight:600;color:#697182;width:1%}
.tr-standings td.st-trend{width:1%}
.tr-standings td.st-team{text-align:left;white-space:nowrap}
.tr-standings td.st-team img{height:20px;width:auto;vertical-align:middle;margin-right:4px}
.tr-standings tr:hover td{background:#f8fafc}
.tr-standings .fav-team{font-weight:700}
.tr-standings .diff-pos{color:#16a34a}
.tr-standings .diff-neg{color:#dc2626}
.trend-arrow{font-size:.7rem}
.trend-up{color:#16a34a}
.trend-down{color:#dc2626}
.trend-same{color:#9098a8}
.st-straf-hinweis{font-size:.7rem;color:#b45309;margin-left:4px}
.st-footnotes{margin-top:6px;padding:4px 8px}
.st-footnote-item{font-size:.72rem;color:#697182;line-height:1.5;margin:0}
.st-footnote-item a{color:#b45309;text-decoration:none}
.lmo-copyright{font-size:.68rem;color:#9098a8;text-align:right;margin:8px 0 0;padding:0;opacity:.65}
.lmo-copyright a{color:inherit;text-decoration:underline dotted;opacity:.7}
</style>
<div class="tr-wrap">
  <h2 class="tr-title">Tabellenrechner &ndash; {LIGA_NAME}</h2>
  <p class="tr-info">{TR_INFO}</p>
  <div class="tr-spieltag-nav">{SPIELTAG_NAV}</div>

  <div class="tr-card">
  <table class="tr-table">
    <thead>
      <tr>
        <th class="col-datum">Datum</th>
        <th class="col-heim">Heim</th>
        <th class="col-ergebnis">Ergebnis</th>
        <th class="col-gast">Gast</th>
      </tr>
    </thead>
    <tbody>
      <!-- BEGIN PARTIEN -->
      <tr data-pid="{PARTIE_ID}">
        <td class="col-datum">{DATUM}</td>
        <td class="col-heim{HEIM_CLASS}">{HEIM}</td>
        <td class="tr-ergebnis-cell">
          <div class="tr-input-wrap">
            <input type="number" min="0" max="199" class="tr-input tr-h" data-pid="{PARTIE_ID}" data-side="h" data-orig="{H_TORE}" value="{H_TORE}" placeholder="&#8211;">
            <span class="tr-spinner">
              <button type="button" class="tr-spin tr-spin-up" tabindex="-1">&#9650;</button>
              <button type="button" class="tr-spin tr-spin-down" tabindex="-1">&#9660;</button>
            </span>
          </div>
          <span class="tr-doppelpunkt">:</span>
          <div class="tr-input-wrap">
            <input type="number" min="0" max="199" class="tr-input tr-g" data-pid="{PARTIE_ID}" data-side="g" data-orig="{G_TORE}" value="{G_TORE}" placeholder="&#8211;">
            <span class="tr-spinner">
              <button type="button" class="tr-spin tr-spin-up" tabindex="-1">&#9650;</button>
              <button type="button" class="tr-spin tr-spin-down" tabindex="-1">&#9660;</button>
            </span>
          </div>
        </td>
        <td class="col-gast{GAST_CLASS}">{GAST}</td>
      </tr>
      <!-- END PARTIEN -->
    </tbody>
  </table>
  </div>

  <button type="button" class="tr-reset-btn">{RESET_LABEL}</button>

  <div class="tr-tabelle-section">
    <div class="tr-tabelle-body">
      <div class="tr-card">
      <table class="tr-standings">
        <thead>
          <tr>
            <th class="st-platz">Pl.</th>
            <th class="st-trend"></th>
            <th class="st-team">Team</th>
            <th>Sp</th>
            <th>S</th>
            <th>U</th>
            <th>N</th>
            <th>Tore</th>
            <th>Diff</th>
            <th>Pkt</th>
          </tr>
        </thead>
        <tbody>
          <!-- BEGIN INHALT -->
          <tr style="{ROW_STYLE}">
            <td class="st-platz">{PLATZ}</td>
            <td class="st-trend">{TREND}</td>
            <td class="st-team{TEAM_CLASS}">{LOGO}{TEAM}{STRAF_HINWEIS}</td>
            <td>{SP}</td>
            <td>{S}</td>
            <td>{U}</td>
            <td>{N}</td>
            <td>{TORE}</td>
            <td class="{DIFF_CLASS}">{DIFF}</td>
            <td>{PKT}</td>
          </tr>
          <!-- END INHALT -->
        </tbody>
      </table>
      {FUSSNOTEN}
      </div>
    </div>
  </div>
  {COPYRIGHT}
</div>
