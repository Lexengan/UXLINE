<?php
/**
 * front/index.php — Dashboard UXKnowDash v5.2
 * i18n : toutes les chaînes UI passent par __('chaine', 'uxknowdash')
 * Les chaînes JS sont injectées via $i18n encodé en JSON
 */
include('../../../inc/includes.php');
Session::checkRight('plugin_uxknowdash_view', READ);
Html::header(__('UXKnowDash', 'uxknowdash'), $_SERVER['PHP_SELF'], 'tools', 'PluginUxknowdashMenu');

$entity  = Session::getActiveEntity();
global $CFG_GLPI, $DB;
$rootDoc = $CFG_GLPI['root_doc'];

// Entités accessibles
$entities = [];
foreach ($DB->request(['SELECT' => ['id','completename'], 'FROM' => 'glpi_entities', 'ORDER' => 'completename ASC']) as $e) {
    $entities[] = $e;
}

// Catégories ITIL
$categories = [];
foreach ($DB->request(['SELECT' => ['id','completename'], 'FROM' => 'glpi_itilcategories', 'WHERE' => ['is_helpdeskvisible' => 1], 'ORDER' => 'completename ASC']) as $c) {
    $categories[] = $c;
}

// Groupes
$groups = [];
foreach ($DB->request(['SELECT' => ['id','name'], 'FROM' => 'glpi_groups', 'WHERE' => ['is_assign' => 1], 'ORDER' => 'name ASC']) as $g) {
    $groups[] = $g;
}

// ── Chaînes i18n passées au JS ────────────────────────────────────────────────
// Règle : aucune chaîne traduite ne doit être hardcodée dans le bloc <script>.
// Toutes les chaînes JS sont centralisées ici et injectées via window.UXKD_I18N.
$i18n = [
    'updated_at'          => __('Updated at', 'uxknowdash'),
    'load_error'          => __('Error loading dashboard', 'uxknowdash'),
    'no_data'             => __('No data available.', 'uxknowdash'),
    'no_data_filter'      => __('No data for selected filters.', 'uxknowdash'),
    'no_data_export'      => __('No data to export.', 'uxknowdash'),
    'pdf_error'           => __('PDF error. See F12.', 'uxknowdash'),
    'fcr_yes'             => __('FCR ✓', 'uxknowdash'),
    'fcr_no'              => __('Non FCR', 'uxknowdash'),
    'kb_linked'           => __('KB linked', 'uxknowdash'),
    'kb_none'             => __('No KB', 'uxknowdash'),
    'volume'              => __('Volume', 'uxknowdash'),
    'fcr_pct'             => __('FCR (%)', 'uxknowdash'),
    'kb_usage_pct'        => __('KB Usage (%)', 'uxknowdash'),
    'liaison_pct'         => __('KB Liaison Rate (%)', 'uxknowdash'),
    'good'                => __('✓ Good', 'uxknowdash'),
    'average'             => __('⚠ Average', 'uxknowdash'),
    'low'                 => __('✗ Low', 'uxknowdash'),
    'ok'                  => __('✓ OK', 'uxknowdash'),
    'close'               => __('⚠ Close', 'uxknowdash'),
    'under_target'        => __('✗ Under target', 'uxknowdash'),
    'period'              => __('Period', 'uxknowdash'),
    'export_prefix'       => __('UXKnowDash Export', 'uxknowdash'),
    'col_category'        => __('ITIL Category', 'uxknowdash'),
    'col_volume'          => __('Volume', 'uxknowdash'),
    'col_fcr'             => __('FCR (%)', 'uxknowdash'),
    'col_kb_usage'        => __('KB Usage (%)', 'uxknowdash'),
    'col_kb_quality'      => __('KB Quality (/5)', 'uxknowdash'),
    'col_technician'      => __('Technician', 'uxknowdash'),
    'col_tickets'         => __('Tickets', 'uxknowdash'),
    'col_with_kb'         => __('With KB', 'uxknowdash'),
    'col_rate'            => __('Rate', 'uxknowdash'),
    'col_gauge'           => __('Gauge', 'uxknowdash'),
];
?>
<style>
/* ── base ── */
#uxkd{padding:16px 20px;font-family:inherit;color:#212529}
a.btn{text-decoration:none}

/* ── toolbar ── */
#uxkd-bar{background:#fff;border:1px solid #dee2e6;border-radius:8px;padding:14px 18px;margin-bottom:16px}
.uxkd-row{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end}
.fg{display:flex;flex-direction:column;gap:3px}
.fg label{font-size:11px;font-weight:700;color:#6c757d;text-transform:uppercase;letter-spacing:.4px}
.fg select,.fg input{border:1px solid #ced4da;border-radius:5px;padding:5px 9px;font-size:13px;background:#fff;min-width:150px}
.uxkd-shorts{display:flex;gap:5px;flex-wrap:wrap}
.uxkd-shorts button{font-size:12px;padding:4px 10px;border-radius:4px;border:1px solid #adb5bd;background:#f8f9fa;cursor:pointer;transition:background .15s}
.uxkd-shorts button:hover,.uxkd-shorts button.active{background:#2E75B6;color:#fff;border-color:#2E75B6}
#btn-go{padding:7px 22px;background:#2E75B6;color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:700;cursor:pointer}
#btn-go:hover{background:#1e5a96}

/* ── KPI cards ── */
.kpi-row{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:16px}
.kpi{background:#fff;border-radius:10px;border:1px solid #dee2e6;padding:16px 20px;
     position:relative;overflow:hidden}
.kpi::before{content:'';position:absolute;top:0;left:0;width:4px;height:100%;background:var(--c)}
.kpi-label{font-size:11px;font-weight:700;color:#6c757d;text-transform:uppercase;letter-spacing:.4px;margin-bottom:5px}
.kpi-val{font-size:30px;font-weight:800;color:#212529;line-height:1}
.kpi-sub{font-size:11px;color:#adb5bd;margin-top:4px}

/* ── chart grid ── */
.grid-3{display:grid;grid-template-columns:2fr 1fr 1fr;gap:14px;margin-bottom:14px}
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px}
.grid-1{margin-bottom:14px}
.cbox{background:#fff;border:1px solid #dee2e6;border-radius:10px;padding:16px}
.cbox h3{font-size:12px;font-weight:700;color:#495057;text-transform:uppercase;
          letter-spacing:.4px;margin:0 0 12px;border-bottom:1px solid #f0f0f0;padding-bottom:8px}

/* ── export ── */
.uxkd-export-bar{display:flex;gap:10px;justify-content:flex-end;margin-top:16px;padding-top:12px;border-top:2px solid #e9ecef}
.uxkd-btn-export{border:none;border-radius:6px;padding:8px 18px;font-size:13px;cursor:pointer;display:flex;align-items:center;gap:6px;font-weight:600}
.uxkd-btn-csv{background:#1E8449;color:#fff}.uxkd-btn-csv:hover{background:#155a32}
.uxkd-btn-pdf{background:#C00000;color:#fff}.uxkd-btn-pdf:hover{background:#900000}

/* ── accordéon ── */
.uxkd-accordion-header{display:flex;align-items:center;justify-content:space-between;cursor:pointer;user-select:none;padding-bottom:10px;border-bottom:2px solid #dee2e6;margin-bottom:0}
.uxkd-accordion-header:hover{opacity:.85}
.uxkd-accordion-toggle{font-size:18px;color:#6c757d;transition:transform .25s;display:inline-block;line-height:1}
.uxkd-accordion-toggle.open{transform:rotate(180deg)}
.uxkd-accordion-body{overflow:hidden;transition:max-height .3s ease,opacity .3s ease;max-height:0;opacity:0}
.uxkd-accordion-body.open{max-height:9999px;opacity:1}

/* ── table ── */
#tbl-wrap{background:#fff;border:1px solid #dee2e6;border-radius:10px;padding:16px}
#tbl-wrap h3{font-size:12px;font-weight:700;color:#495057;text-transform:uppercase;
              letter-spacing:.4px;margin:0 0 12px;border-bottom:1px solid #f0f0f0;padding-bottom:8px}
#dtbl{width:100%;border-collapse:collapse;font-size:13px}
#dtbl th{background:#343a40;color:#fff;padding:8px 12px;text-align:left;font-weight:600;font-size:11px;letter-spacing:.3px}
#dtbl td{padding:7px 12px;border-bottom:1px solid #f5f5f5;vertical-align:middle}
#dtbl tr:hover td{background:#f8f9fa}
.ok{background:#d4edda;color:#155724;border-radius:4px;padding:2px 8px;font-size:11px;font-weight:700}
.warn{background:#fff3cd;color:#856404;border-radius:4px;padding:2px 8px;font-size:11px;font-weight:700}
.bad{background:#f8d7da;color:#721c24;border-radius:4px;padding:2px 8px;font-size:11px;font-weight:700}

/* ── loader ── */
#uxkd-loader{display:none;position:fixed;inset:0;background:rgba(255,255,255,.65);
              z-index:9999;align-items:center;justify-content:center}
#uxkd-loader.on{display:flex}
.spin{width:42px;height:42px;border:4px solid #dee2e6;border-top-color:#2E75B6;
      border-radius:50%;animation:spin .7s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}

/* ── misc ── */
#uxkd-ts{font-size:11px;color:#adb5bd}
#uxkd-nodata{display:none;text-align:center;padding:30px;color:#6c757d;font-size:14px}
@media(max-width:1100px){.grid-3,.kpi-row{grid-template-columns:1fr 1fr}}
@media(max-width:700px){.grid-3,.grid-2,.kpi-row{grid-template-columns:1fr}}
</style>

<div id="uxkd-loader"><div class="spin"></div></div>

<div id="uxkd-capture">
<div id="uxkd">

<!-- En-tête -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
  <div>
    <h2 style="margin:0;font-size:20px;font-weight:800">UXKnowDash — <?= __('Dashboard', 'uxknowdash') ?></h2>
    <span id="uxkd-ts"></span>
  </div>
  <?php if (Session::haveRight('config', UPDATE)): ?>
  <a class="btn btn-secondary btn-sm" href="config.php">⚙ <?= __('Settings', 'uxknowdash') ?></a>
  <?php endif; ?>
</div>

<!-- Toolbar filtres -->
<div id="uxkd-bar">
  <div class="uxkd-row">

    <div class="fg">
      <label><?= __('Entity', 'uxknowdash') ?></label>
      <select id="f-entity">
        <?php foreach ($entities as $e): ?>
        <option value="<?= intval($e['id']) ?>" <?= $e['id']==$entity?'selected':'' ?>>
          <?= Html::cleanInputText($e['completename']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="fg">
      <label><?= __('Period', 'uxknowdash') ?></label>
      <div class="uxkd-shorts">
        <button onclick="setPeriod(7,this)">7 j</button>
        <button onclick="setPeriod(30,this)">30 j</button>
        <button onclick="setPeriod(90,this)" class="active">90 j</button>
        <button onclick="setPeriod(180,this)">6 <?= __('months', 'uxknowdash') ?></button>
        <button onclick="setPeriod(365,this)">1 <?= __('year', 'uxknowdash') ?></button>
        <button onclick="setPeriod(0,this)"><?= __('All', 'uxknowdash') ?></button>
      </div>
    </div>

    <div class="fg">
      <label><?= __('From', 'uxknowdash') ?></label>
      <input type="date" id="f-from">
    </div>
    <div class="fg">
      <label><?= __('To', 'uxknowdash') ?></label>
      <input type="date" id="f-to" value="<?= date('Y-m-d') ?>">
    </div>

    <div class="fg">
      <label><?= __('Category', 'uxknowdash') ?></label>
      <select id="f-cat">
        <option value="0">— <?= __('All', 'uxknowdash') ?> —</option>
        <?php foreach ($categories as $c): ?>
        <option value="<?= intval($c['id']) ?>"><?= Html::cleanInputText($c['completename']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="fg">
      <label><?= __('Group', 'uxknowdash') ?></label>
      <select id="f-grp">
        <option value="0">— <?= __('All', 'uxknowdash') ?> —</option>
        <?php foreach ($groups as $g): ?>
        <option value="<?= intval($g['id']) ?>"><?= Html::cleanInputText($g['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="fg" style="justify-content:flex-end">
      <label>&nbsp;</label>
      <button id="btn-go" onclick="load();loadLiaison()">↻ <?= __('Apply', 'uxknowdash') ?></button>
    </div>

  </div>
</div>

<!-- KPI Cards -->
<div class="kpi-row">
  <div class="kpi" style="--c:#2E75B6">
    <div class="kpi-label"><?= __('Tickets resolved', 'uxknowdash') ?></div>
    <div class="kpi-val" id="k-total">—</div>
    <div class="kpi-sub"><?= __('over the period', 'uxknowdash') ?></div>
  </div>
  <div class="kpi" style="--c:#1E8449">
    <div class="kpi-label"><?= __('Average FCR', 'uxknowdash') ?></div>
    <div class="kpi-val" id="k-fcr">—</div>
    <div class="kpi-sub"><?= __('First Contact Resolution', 'uxknowdash') ?></div>
  </div>
  <div class="kpi" style="--c:#D35400">
    <div class="kpi-label"><?= __('KB Usage', 'uxknowdash') ?></div>
    <div class="kpi-val" id="k-kb">—</div>
    <div class="kpi-sub"><?= __('tickets linked to a KB', 'uxknowdash') ?></div>
  </div>
  <div class="kpi" style="--c:#6C3483">
    <div class="kpi-label"><?= __('Avg. KB Quality', 'uxknowdash') ?></div>
    <div class="kpi-val" id="k-qual">—</div>
    <div class="kpi-sub"><?= __('average rating /5', 'uxknowdash') ?></div>
  </div>
</div>

<!-- Accordéon graphiques FCR / KB -->
<div style="background:#fff;border:1px solid #dee2e6;border-radius:10px;padding:16px;margin-bottom:14px">
  <div class="uxkd-accordion-header" onclick="toggleAccordion('acc-charts')">
    <span style="font-size:14px;font-weight:700;color:#495057;text-transform:uppercase;letter-spacing:.4px">📊 <?= __('FCR & KB Metrics', 'uxknowdash') ?></span>
    <span class="uxkd-accordion-toggle open" id="toggle-acc-charts">▼</span>
  </div>
  <div class="uxkd-accordion-body open" id="acc-charts">
    <div style="height:14px"></div>
    <div class="grid-3">
      <div class="cbox"><h3><?= __('FCR Rate by Category', 'uxknowdash') ?> (%)</h3><canvas id="c-fcr-cat"></canvas></div>
      <div class="cbox"><h3><?= __('FCR', 'uxknowdash') ?> — <?= __('yes', 'uxknowdash') ?> / <?= __('no', 'uxknowdash') ?></h3><canvas id="c-pie-fcr"></canvas></div>
      <div class="cbox"><h3><?= __('KB used', 'uxknowdash') ?> — <?= __('yes', 'uxknowdash') ?> / <?= __('no', 'uxknowdash') ?></h3><canvas id="c-pie-kb"></canvas></div>
    </div>
    <div class="grid-3">
      <div class="cbox"><h3><?= __('KB Usage Rate by Category', 'uxknowdash') ?> (%)</h3><canvas id="c-kb-cat"></canvas></div>
      <div class="cbox"><h3><?= __('KB Quality', 'uxknowdash') ?></h3><canvas id="c-qual-dist"></canvas></div>
      <div class="cbox"><h3><?= __('Volume', 'uxknowdash') ?> & <?= __('FCR Rate by Group', 'uxknowdash') ?></h3><canvas id="c-grp"></canvas></div>
    </div>
    <div class="grid-1">
      <div class="cbox"><h3><?= __('Daily trend', 'uxknowdash') ?> — <?= __('volume & FCR', 'uxknowdash') ?></h3><canvas id="c-trend" style="max-height:160px"></canvas></div>
    </div>
  </div>
</div>

<!-- Tableau détaillé -->
<div id="tbl-wrap">
  <div class="uxkd-accordion-header" onclick="toggleAccordion('acc-table')">
    <h3 style="margin:0;border:none;padding:0"><?= __('Detail by category', 'uxknowdash') ?></h3>
    <span class="uxkd-accordion-toggle" id="toggle-acc-table">▼</span>
  </div>
  <div class="uxkd-accordion-body" id="acc-table">
    <div style="height:12px"></div>
    <div id="uxkd-nodata"><?= __('No data for selected filters.', 'uxknowdash') ?></div>
    <div style="overflow-x:auto">
    <table id="dtbl" style="display:none">
      <thead><tr>
        <th><?= __('Category', 'uxknowdash') ?></th>
        <th><?= __('Volume', 'uxknowdash') ?></th>
        <th><?= __('FCR (%)', 'uxknowdash') ?></th>
        <th><?= __('KB Usage (%)', 'uxknowdash') ?></th>
        <th><?= __('KB Quality', 'uxknowdash') ?></th>
        <th><?= __('FCR', 'uxknowdash') ?></th>
        <th><?= __('KB', 'uxknowdash') ?></th>
      </tr></thead>
      <tbody id="dtbody"></tbody>
    </table>
    </div>
  </div>
</div>

<!-- Section Liaison KB -->
<div id="section-liaison" style="margin-top:24px;background:#fff;border:1px solid #dee2e6;border-radius:10px;padding:16px">
  <div class="uxkd-accordion-header" onclick="toggleAccordion('acc-liaison')">
    <div style="display:flex;align-items:center;gap:12px">
      <span style="font-size:16px;font-weight:800;color:#2E75B6">🔗 <?= __('KB Liaison Rate', 'uxknowdash') ?></span>
      <span style="font-size:12px;color:#6c757d"><?= __('Tickets handled (excl. New) linked to at least one KB article', 'uxknowdash') ?></span>
      <span id="liaison-ts" style="font-size:11px;color:#adb5bd"></span>
    </div>
    <span class="uxkd-accordion-toggle open" id="toggle-acc-liaison">▼</span>
  </div>

  <div class="uxkd-accordion-body open" id="acc-liaison">
    <div style="height:14px"></div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:16px">
      <div class="kpi" style="--c:#2E75B6">
        <div class="kpi-label"><?= __('Tickets handled', 'uxknowdash') ?></div>
        <div class="kpi-val" id="kl-total">—</div>
        <div class="kpi-sub"><?= __('excl. New status', 'uxknowdash') ?></div>
      </div>
      <div class="kpi" style="--c:#1E8449">
        <div class="kpi-label"><?= __('Tickets with KB', 'uxknowdash') ?></div>
        <div class="kpi-val" id="kl-with-kb">—</div>
        <div class="kpi-sub"><?= __('at least 1 KB article linked', 'uxknowdash') ?></div>
      </div>
      <div class="kpi" style="--c:#D35400">
        <div class="kpi-label"><?= __('Global KB Liaison Rate', 'uxknowdash') ?></div>
        <div class="kpi-val" id="kl-pct">—</div>
        <div class="kpi-sub"><?= __('% tickets linked to a KB', 'uxknowdash') ?></div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
      <div class="cbox">
        <h3><?= __('KB Liaison Rate by Technician', 'uxknowdash') ?> (%)</h3>
        <div id="liaison-nodata-chart" style="color:#adb5bd;font-size:13px;padding:20px 0"><?= __('No data available.', 'uxknowdash') ?></div>
        <canvas id="c-liaison-tech" style="display:none"></canvas>
      </div>
      <div class="cbox">
        <h3><?= __('Detail by technician', 'uxknowdash') ?></h3>
        <div id="liaison-nodata-tbl" style="color:#adb5bd;font-size:13px;padding:20px 0"><?= __('No data available.', 'uxknowdash') ?></div>
        <div style="overflow-x:auto">
        <table id="liaison-tbl" style="display:none;width:100%;border-collapse:collapse;font-size:12px">
          <thead>
            <tr style="border-bottom:2px solid #dee2e6">
              <th style="text-align:left;padding:6px 8px"><?= __('Technician', 'uxknowdash') ?></th>
              <th style="text-align:center;padding:6px 8px"><?= __('Tickets', 'uxknowdash') ?></th>
              <th style="text-align:center;padding:6px 8px"><?= __('with KB', 'uxknowdash') ?></th>
              <th style="text-align:center;padding:6px 8px"><?= __('Rate', 'uxknowdash') ?></th>
              <th style="text-align:left;padding:6px 8px"><?= __('Gauge', 'uxknowdash') ?></th>
            </tr>
          </thead>
          <tbody id="liaison-tbody"></tbody>
        </table>
        </div>
      </div>
    </div>
  </div>
</div>

</div><!-- /uxkd -->
</div><!-- /uxkd-capture -->

<div class="uxkd-export-bar" id="uxkd-export-bar">
  <button class="uxkd-btn-export uxkd-btn-csv" id="btn-export-csv">⬇ <?= __('Export CSV', 'uxknowdash') ?></button>
  <button class="uxkd-btn-export uxkd-btn-pdf" id="btn-export-pdf">📄 <?= __('Export PDF', 'uxknowdash') ?></button>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
'use strict';
// ── Chaînes i18n injectées par PHP ───────────────────────────
// Toutes les chaînes UI JS proviennent de window.UXKD_I18N
// Elles sont traduites côté PHP via __() puis passées en JSON
window.UXKD_I18N = <?= json_encode($i18n, JSON_UNESCAPED_UNICODE) ?>;
window.UXKD_ROOT = <?= json_encode($rootDoc) ?>;

// ── Palette & utils ──────────────────────────────────────────
const PAL=['#2E75B6','#1E8449','#D35400','#6C3483','#117A65',
           '#C0392B','#1A5276','#7D6608','#4A235A','#0E6655',
           '#6E2F1A','#154360','#4D5656','#145A32','#512E5F'];
const a=(hex,op)=>{const r=parseInt(hex.slice(1,3),16),g=parseInt(hex.slice(3,5),16),b=parseInt(hex.slice(5,7),16);return`rgba(${r},${g},${b},${op})`;};
const esc=s=>String(s??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
const trunc=(s,n)=>s&&s.length>n?s.slice(0,n)+'…':(s||'—');
const fmt=n=>Number(n).toLocaleString('fr-FR');
const t=k=>window.UXKD_I18N[k]||k; // helper traduction

// ── Instances Chart ──────────────────────────────────────────
const CH={};
function mk(id,cfg){if(CH[id])CH[id].destroy();CH[id]=new Chart(document.getElementById(id).getContext('2d'),cfg);}

let _tableData = [];

// ── Période rapide ───────────────────────────────────────────
function setPeriod(days,btn){
  document.querySelectorAll('.uxkd-shorts button').forEach(b=>b.classList.remove('active'));
  if(btn)btn.classList.add('active');
  const to=new Date();
  document.getElementById('f-to').value=to.toISOString().slice(0,10);
  if(!days){document.getElementById('f-from').value='';return;}
  const fr=new Date(to);fr.setDate(fr.getDate()-days);
  document.getElementById('f-from').value=fr.toISOString().slice(0,10);
}

// ── Fetch dashboard ──────────────────────────────────────────
async function load(){
  const ldr=document.getElementById('uxkd-loader');
  ldr.classList.add('on');
  const p=new URLSearchParams({
    entity:   document.getElementById('f-entity').value,
    date_from:document.getElementById('f-from').value,
    date_to:  document.getElementById('f-to').value,
    category: document.getElementById('f-cat').value,
    group:    document.getElementById('f-grp').value,
  });
  try{
    const r=await fetch(window.UXKD_ROOT+'/plugins/uxknowdash/ajax/get_dashboard.php?'+p);
    if(!r.ok)throw new Error('HTTP '+r.status);
    const d=await r.json();
    if(d.error)throw new Error(d.error);
    renderKPI(d.kpis);
    renderFcrCat(d.fcr_by_cat);
    renderPieFCR(d.status_pie);
    renderPieKB(d.status_pie);
    renderKbCat(d.kb_by_cat);
    renderQualDist(d.kb_distrib);
    renderGroup(d.by_group);
    renderTrend(d.volume_by_day);
    renderTable(d.table);
    document.getElementById('uxkd-ts').textContent=t('updated_at')+' : '+new Date().toLocaleTimeString();
  }catch(e){console.error('UXKnowDash:',e);alert(t('load_error')+' : '+e.message);}
  finally{ldr.classList.remove('on');}
}

// ── KPI ──────────────────────────────────────────────────────
function renderKPI(k){
  if(!k)return;
  document.getElementById('k-total').textContent=k.total!=null?fmt(k.total):'—';
  document.getElementById('k-fcr').textContent=k.fcr_pct!=null?k.fcr_pct+' %':'—';
  document.getElementById('k-kb').textContent=k.kb_usage_pct!=null?k.kb_usage_pct+' %':'—';
  document.getElementById('k-qual').textContent=k.kb_quality_avg!=null?k.kb_quality_avg+' /5':'—';
}

function renderFcrCat(rows){
  if(!rows||!rows.length)return;
  const labels=rows.map(r=>trunc(r.cat_name,30));
  const vals=rows.map(r=>parseFloat(r.fcr_pct)||0);
  const colors=vals.map(v=>v>=50?'#1E8449':v>=30?'#D35400':'#C0392B');
  mk('c-fcr-cat',{type:'bar',data:{labels,datasets:[{
    label:t('fcr_pct'),data:vals,backgroundColor:colors.map(c=>a(c,.75)),borderColor:colors,borderWidth:1
  }]},options:{indexAxis:'y',responsive:true,plugins:{legend:{display:false},
    tooltip:{callbacks:{label:c=>c.parsed.x+' %'}}},
    scales:{x:{beginAtZero:true,max:100,ticks:{callback:v=>v+'%'}},
            y:{ticks:{font:{size:11}}}}}});
}

function renderPieFCR(pie){
  if(!pie)return;
  mk('c-pie-fcr',{type:'doughnut',data:{
    labels:[t('fcr_yes'),t('fcr_no')],
    datasets:[{data:[pie.fcr||0,pie.no_fcr||0],
      backgroundColor:[a('#1E8449',.8),a('#C0392B',.7)],borderWidth:2,borderColor:'#fff'}]
  },options:{cutout:'55%',plugins:{legend:{position:'bottom',labels:{font:{size:11},boxWidth:12}},
    tooltip:{callbacks:{label:c=>c.label+': '+fmt(c.parsed)}}}}});
}

function renderPieKB(pie){
  if(!pie)return;
  mk('c-pie-kb',{type:'doughnut',data:{
    labels:[t('kb_linked'),t('kb_none')],
    datasets:[{data:[pie.with_kb||0,pie.without_kb||0],
      backgroundColor:[a('#2E75B6',.8),a('#adb5bd',.6)],borderWidth:2,borderColor:'#fff'}]
  },options:{cutout:'55%',plugins:{legend:{position:'bottom',labels:{font:{size:11},boxWidth:12}},
    tooltip:{callbacks:{label:c=>c.label+': '+fmt(c.parsed)}}}}});
}

function renderKbCat(rows){
  if(!rows||!rows.length)return;
  const labels=rows.map(r=>trunc(r.cat_name,30));
  const vals=rows.map(r=>parseFloat(r.kb_pct)||0);
  mk('c-kb-cat',{type:'bar',data:{labels,datasets:[{
    label:t('kb_usage_pct'),data:vals,
    backgroundColor:vals.map(v=>a(v>=40?'#2E75B6':v>=20?'#D35400':'#adb5bd',.75)),borderWidth:1
  }]},options:{indexAxis:'y',responsive:true,plugins:{legend:{display:false},
    tooltip:{callbacks:{label:c=>c.parsed.x+' %'}}},
    scales:{x:{beginAtZero:true,max:100,ticks:{callback:v=>v+'%'}},
            y:{ticks:{font:{size:11}}}}}});
}

function renderQualDist(rows){
  if(!rows||!rows.length)return;
  mk('c-qual-dist',{type:'doughnut',data:{
    labels:rows.map(r=>r.label),
    datasets:[{data:rows.map(r=>parseInt(r.count)||0),
      backgroundColor:PAL.slice(0,rows.length).map(c=>a(c,.8)),borderWidth:2,borderColor:'#fff'}]
  },options:{cutout:'45%',plugins:{legend:{position:'bottom',labels:{font:{size:10},boxWidth:11}},
    tooltip:{callbacks:{label:c=>c.label+': '+fmt(c.parsed)}}}}});
}

function renderGroup(rows){
  if(!rows||!rows.length)return;
  const labels=rows.map(r=>trunc(r.group_name,22));
  mk('c-grp',{type:'bar',data:{labels,datasets:[
    {label:t('volume'),data:rows.map(r=>parseInt(r.volume)||0),
     backgroundColor:PAL.slice(0,rows.length).map(c=>a(c,.7)),yAxisID:'yv'},
    {label:t('fcr_pct'),data:rows.map(r=>parseFloat(r.fcr_pct)||0),
     type:'line',borderColor:'#1E8449',backgroundColor:'transparent',
     pointRadius:4,tension:.3,yAxisID:'yp'},
  ]},options:{responsive:true,
    plugins:{legend:{position:'top',labels:{font:{size:11},boxWidth:12}}},
    scales:{
      yv:{type:'linear',position:'left',beginAtZero:true},
      yp:{type:'linear',position:'right',beginAtZero:true,max:100,
          ticks:{callback:v=>v+'%'},grid:{drawOnChartArea:false}},
      x:{ticks:{font:{size:10},maxRotation:35}}
    }}});
}

function renderTrend(byDay){
  if(!byDay||!byDay.length)return;
  const labels=byDay.map(r=>r.day);
  const vols=byDay.map(r=>parseInt(r.volume)||0);
  mk('c-trend',{type:'bar',data:{labels,datasets:[
    {label:t('volume'),data:vols,backgroundColor:a('#2E75B6',.45),borderColor:'#2E75B6',borderWidth:1,yAxisID:'yv'},
  ]},options:{responsive:true,maintainAspectRatio:true,
    plugins:{legend:{position:'top',labels:{font:{size:11}}}},
    scales:{
      yv:{type:'linear',position:'left',beginAtZero:true},
      x:{ticks:{font:{size:10},maxTicksLimit:20}}
    }}});
}

function renderTable(matrix){
  _tableData = matrix || [];
  const tbody=document.getElementById('dtbody');
  const tbl=document.getElementById('dtbl');
  const nd=document.getElementById('uxkd-nodata');
  tbody.innerHTML='';
  if(!matrix||!matrix.length){tbl.style.display='none';nd.style.display='block';return;}
  tbl.style.display='table';nd.style.display='none';
  const FCR_T=50,KB_T=30;
  matrix.forEach(r=>{
    const fcr=parseFloat(r.fcr_pct)||0,kb=parseFloat(r.kb_usage_pct)||0;
    const bFCR=fcr>=FCR_T?`<span class="ok">${t('ok')}</span>`:fcr>=FCR_T*.7?`<span class="warn">${t('close')}</span>`:`<span class="bad">${t('under_target')}</span>`;
    const bKB =kb>=KB_T ?`<span class="ok">${t('ok')}</span>`:kb>=KB_T*.7 ?`<span class="warn">${t('close')}</span>`:`<span class="bad">${t('under_target')}</span>`;
    const tr=document.createElement('tr');
    tr.innerHTML=`<td><strong>${esc(r.cat_name)}</strong></td>
      <td>${fmt(r.volume)}</td><td>${fcr} %</td>
      <td>${kb} %</td><td>${parseFloat(r.kb_quality_avg)||0} /5</td>
      <td>${bFCR}</td><td>${bKB}</td>`;
    tbody.appendChild(tr);
  });
}

// ── Liaison KB ───────────────────────────────────────────────
async function loadLiaison() {
  const p = new URLSearchParams({
    entity:    document.getElementById('f-entity').value,
    date_from: document.getElementById('f-from').value,
    date_to:   document.getElementById('f-to').value,
    group:     document.getElementById('f-grp').value,
  });
  try {
    const r = await fetch(window.UXKD_ROOT+'/plugins/uxknowdash/ajax/get_kb_liaison.php?' + p);
    if (!r.ok) throw new Error('HTTP ' + r.status);
    const d = await r.json();
    if (d.error) throw new Error(d.error);
    renderLiaison(d);
    document.getElementById('liaison-ts').textContent = t('updated_at')+' : ' + new Date().toLocaleTimeString();
  } catch(e) { console.error('UXKnowDash liaison:', e); }
}

function renderLiaison(d) {
  document.getElementById('kl-total').textContent   = d.global ? fmt(d.global.total)   : '—';
  document.getElementById('kl-with-kb').textContent = d.global ? fmt(d.global.with_kb) : '—';
  document.getElementById('kl-pct').textContent     = d.global ? d.global.pct + ' %'  : '—';

  const rows = d.byTech || [];
  const noChart = document.getElementById('liaison-nodata-chart');
  const noTbl   = document.getElementById('liaison-nodata-tbl');
  const canvas  = document.getElementById('c-liaison-tech');
  const tbl     = document.getElementById('liaison-tbl');
  const tbody   = document.getElementById('liaison-tbody');

  if (!rows.length) {
    noChart.style.display = 'block'; canvas.style.display = 'none';
    noTbl.style.display   = 'block'; tbl.style.display    = 'none';
    return;
  }

  noChart.style.display = 'none'; canvas.style.display = 'block';
  noTbl.style.display   = 'none'; tbl.style.display    = 'table';

  const labels = rows.map(r => trunc(r.name, 28));
  const vals   = rows.map(r => r.pct);
  const colors = vals.map(v => v >= 50 ? '#1E8449' : v >= 25 ? '#D35400' : '#C0392B');
  mk('c-liaison-tech', {
    type: 'bar',
    data: { labels, datasets: [{
      label: t('liaison_pct'),
      data: vals,
      backgroundColor: colors.map(c => a(c, .75)),
      borderColor: colors, borderWidth: 1,
    }]},
    options: {
      indexAxis: 'y', responsive: true,
      plugins: { legend: { display: false },
        tooltip: { callbacks: { label: c => c.parsed.x + ' % (' + rows[c.dataIndex].with_kb + '/' + rows[c.dataIndex].total + ' tickets)' }}},
      scales: {
        x: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' }},
        y: { ticks: { font: { size: 11 }}}
      }
    }
  });

  tbody.innerHTML = rows.map(r => {
    const pct  = r.pct;
    const col  = pct >= 50 ? '#1E8449' : pct >= 25 ? '#D35400' : '#C0392B';
    const badge = pct >= 50
      ? `<span style="color:#1E8449;font-weight:700">${t('good')}</span>`
      : pct >= 25
        ? `<span style="color:#D35400;font-weight:700">${t('average')}</span>`
        : `<span style="color:#C0392B;font-weight:700">${t('low')}</span>`;
    const bar = `<div style="background:#f0f0f0;border-radius:4px;height:10px;width:100%;min-width:80px">
      <div style="background:${col};height:10px;border-radius:4px;width:${Math.min(pct,100)}%"></div></div>`;
    return `<tr style="border-bottom:1px solid #f0f0f0">
      <td style="padding:5px 8px;font-weight:600">${esc(r.name)}</td>
      <td style="text-align:center;padding:5px 8px">${r.total}</td>
      <td style="text-align:center;padding:5px 8px">${r.with_kb}</td>
      <td style="text-align:center;padding:5px 8px;font-weight:700;color:${col}">${pct} %</td>
      <td style="padding:5px 8px;min-width:100px">${bar}</td>
    </tr>`;
  }).join('');
}

// ── Accordéon ────────────────────────────────────────────────
function toggleAccordion(id){
  const body   = document.getElementById(id);
  const toggle = document.getElementById('toggle-' + id);
  const isOpen = body.classList.contains('open');
  body.classList.toggle('open', !isOpen);
  toggle.classList.toggle('open', !isOpen);
}

// ── Export CSV ───────────────────────────────────────────────
function exportCsv(){
  if(!_tableData.length){alert(t('no_data_export'));return;}
  const sep=';';
  const dateFrom=document.getElementById('f-from').value;
  const dateTo=document.getElementById('f-to').value;
  const meta='"'+t('export_prefix')+'"'+sep+'"'+t('period')+' : '+dateFrom+' → '+dateTo+'"';
  const headers=[t('col_category'),t('col_volume'),t('col_fcr'),t('col_kb_usage'),t('col_kb_quality')];
  const rows=_tableData.map(r=>[
    '"'+(r.cat_name||'').replace(/"/g,'""')+'"',
    r.volume??0, r.fcr_pct??0, r.kb_usage_pct??0, r.kb_quality_avg??0,
  ].join(sep));
  const csv='\uFEFF'+meta+'\n\n'+headers.join(sep)+'\n'+rows.join('\n');
  const blob=new Blob([csv],{type:'text/csv;charset=utf-8;'});
  const url=URL.createObjectURL(blob);
  const a=document.createElement('a');
  a.href=url;a.download='uxknowdash_'+dateFrom+'_'+dateTo+'.csv';
  document.body.appendChild(a);a.click();document.body.removeChild(a);URL.revokeObjectURL(url);
}

// ── Export PDF ───────────────────────────────────────────────
function exportPdf(){
  const captureEl=document.getElementById('uxkd-capture');
  const exportBar=document.getElementById('uxkd-export-bar');
  exportBar.style.display='none';
  html2canvas(captureEl,{scale:1.5,useCORS:true,allowTaint:true,backgroundColor:'#f4f6f8',logging:false}).then(canvas=>{
    const {jsPDF}=window.jspdf;
    const pdf=new jsPDF({orientation:'landscape',unit:'mm',format:'a4'});
    const pageW=pdf.internal.pageSize.getWidth();
    const pageH=pdf.internal.pageSize.getHeight();
    const margin=8,usableW=pageW-margin*2;
    const ratio=canvas.width/usableW;
    const totalImgH=canvas.height/ratio;
    const sliceH=pageH-margin*2-6;
    const date=new Date().toISOString().split('T')[0];
    const dateFrom=document.getElementById('f-from').value;
    const dateTo=document.getElementById('f-to').value;
    let pageNum=0,srcYmm=0;
    while(srcYmm<totalImgH){
      if(pageNum>0)pdf.addPage();pageNum++;
      const drawH=Math.min(sliceH,totalImgH-srcYmm);
      const slice=document.createElement('canvas');
      slice.width=canvas.width;slice.height=Math.ceil(drawH*ratio);
      slice.getContext('2d').drawImage(canvas,0,srcYmm*ratio,canvas.width,drawH*ratio,0,0,canvas.width,drawH*ratio);
      pdf.addImage(slice.toDataURL('image/jpeg',0.92),'JPEG',margin,margin,usableW,drawH);
      pdf.setFontSize(8);pdf.setTextColor(150);
      pdf.text('UXKnowDash — '+date+'   '+t('period')+' : '+dateFrom+' → '+dateTo,margin,pageH-3);
      pdf.text('Page '+pageNum,pageW-margin-12,pageH-3);
      srcYmm+=sliceH;
    }
    pdf.save('uxknowdash_dashboard_'+date+'.pdf');
  }).catch(e=>{console.error(e);alert(t('pdf_error'));})
  .finally(()=>{exportBar.style.display='';});
}

// ── Init ─────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded',()=>{
  const to=new Date(),fr=new Date(to);
  fr.setDate(fr.getDate()-90);
  document.getElementById('f-to').value=to.toISOString().slice(0,10);
  document.getElementById('f-from').value=fr.toISOString().slice(0,10);
  document.getElementById('btn-export-csv').addEventListener('click', exportCsv);
  document.getElementById('btn-export-pdf').addEventListener('click', exportPdf);
  toggleAccordion('acc-table');
  load();
  loadLiaison();
});
</script>

<?php Html::footer(); ?>
