<?php
/**
 * front/itiltargets.form.php — UXKnowDash v2.0
 * Améliorations :
 *   P1 — Objectifs globaux par défaut (appliqués en masse)
 *   P2 — Tableau inline éditable avec filtre + "non configurées seulement"
 *   P3 — Export CSV + Import CSV
 */
include('../../../inc/includes.php');
Session::checkRight('config', UPDATE);

$entity = Session::getActiveEntity();

// ── P3 : Import CSV ───────────────────────────────────────────────────────────
if (isset($_POST['import_csv']) && !empty($_FILES['csv_file']['tmp_name'])) {
    Session::checkSessionToken($_POST['_glpi_csrf_token'] ?? '');
    $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
    fgetcsv($handle, 0, ';'); // skip header
    $count = 0;
    while (($row = fgetcsv($handle, 0, ';')) !== false) {
        if (count($row) < 4) continue;
        // col: itilcategories_id ; fcr% ; kb_usage% ; kb_quality
        $catId   = intval($row[0]);
        $fcr     = floatval(str_replace(',', '.', $row[1])) / 100;
        $kb      = floatval(str_replace(',', '.', $row[2])) / 100;
        $quality = floatval(str_replace(',', '.', $row[3]));
        if ($catId > 0) {
            PluginUxknowdashCategoryTarget::saveTarget($entity, $catId, $fcr, $kb, $quality);
            $count++;
        }
    }
    fclose($handle);
    Session::addMessageAfterRedirect("$count objectif(s) importé(s) depuis le CSV.", true, INFO);
    Html::redirect($_SERVER['PHP_SELF']);
}

// ── P3 : Export CSV ───────────────────────────────────────────────────────────
if (isset($_GET['export_csv'])) {
    global $DB;
    $categories = iterator_to_array($DB->request([
        'SELECT'  => ['id', 'completename'],
        'FROM'    => 'glpi_itilcategories',
        'WHERE'   => ['entities_id' => $entity, 'is_helpdeskvisible' => 1],
        'ORDERBY' => ['completename ASC'],
    ]), false);
    $targets = [];
    foreach (PluginUxknowdashCategoryTarget::getTargets($entity) as $t) {
        $targets[$t['itilcategories_id']] = $t;
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="uxknowdash_objectifs_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8
    fputcsv($out, ['itilcategories_id', 'FCR (%)', 'KB Usage (%)', 'Qualite KB (/5)', 'Nom categorie'], ';');
    foreach ($categories as $cat) {
        $t = $targets[$cat['id']] ?? [];
        fputcsv($out, [
            $cat['id'],
            round(($t['target_fcr']        ?? 0.5) * 100),
            round(($t['target_kb_usage']   ?? 0.3) * 100),
            $t['target_kb_quality'] ?? 3.5,
            $cat['completename'],
        ], ';');
    }
    fclose($out);
    exit;
}

// ── Enregistrement du formulaire ──────────────────────────────────────────────
if (isset($_POST['save'])) {
    Session::checkSessionToken($_POST['_glpi_csrf_token'] ?? '');

    // P1 : Appliquer les objectifs globaux à toutes les catégories non configurées
    if (!empty($_POST['apply_global'])) {
        global $DB;
        $allCats = iterator_to_array($DB->request([
            'SELECT' => ['id'],
            'FROM'   => 'glpi_itilcategories',
            'WHERE'  => ['entities_id' => $entity, 'is_helpdeskvisible' => 1],
        ]), false);
        $existingTargets = [];
        foreach (PluginUxknowdashCategoryTarget::getTargets($entity) as $t) {
            $existingTargets[$t['itilcategories_id']] = true;
        }
        $gFcr     = floatval($_POST['global_fcr']     ?? 50) / 100;
        $gKb      = floatval($_POST['global_kb']      ?? 30) / 100;
        $gQuality = floatval($_POST['global_quality'] ?? 3.5);
        foreach ($allCats as $cat) {
            if (!isset($existingTargets[$cat['id']])) {
                PluginUxknowdashCategoryTarget::saveTarget($entity, $cat['id'], $gFcr, $gKb, $gQuality);
            }
        }
        Session::addMessageAfterRedirect('Objectifs globaux appliqués aux catégories non configurées.', true, INFO);
    }

    // P2 : Enregistrement ligne par ligne
    $cats = $_POST['cats'] ?? [];
    foreach ($cats as $catId => $vals) {
        PluginUxknowdashCategoryTarget::saveTarget(
            $entity,
            intval($catId),
            floatval($vals['fcr']     ?? 50) / 100,
            floatval($vals['kb']      ?? 30) / 100,
            floatval($vals['quality'] ?? 3.5)
        );
    }
    Session::addMessageAfterRedirect('Objectifs enregistrés.', true, INFO);
    Html::redirect($_SERVER['PHP_SELF']);
}

// ── Chargement des données ────────────────────────────────────────────────────
global $DB;
$categories = iterator_to_array($DB->request([
    'SELECT'  => ['id', 'completename'],
    'FROM'    => 'glpi_itilcategories',
    'WHERE'   => ['entities_id' => $entity, 'is_helpdeskvisible' => 1],
    'ORDERBY' => ['completename ASC'],
]), false);
$targets = [];
foreach (PluginUxknowdashCategoryTarget::getTargets($entity) as $t) {
    $targets[$t['itilcategories_id']] = $t;
}
$totalCats      = count($categories);
$configuredCats = count($targets);

Html::header('UXKnowDash – Objectifs ITIL', $_SERVER['PHP_SELF'], 'tools', 'PluginUxknowdashMenu');
?>
<style>
.uxkd-targets { padding: 16px 20px; }
.uxkd-card    { background:#fff; border:1px solid #dee2e6; border-radius:10px; padding:20px; margin-bottom:16px; }
.uxkd-card h3 { font-size:14px; font-weight:700; color:#495057; text-transform:uppercase; letter-spacing:.4px; margin:0 0 14px; padding-bottom:10px; border-bottom:1px solid #f0f0f0; }

/* ── P1 Objectifs globaux ── */
.global-row   { display:flex; flex-wrap:wrap; gap:14px; align-items:flex-end; }
.global-field { display:flex; flex-direction:column; gap:4px; }
.global-field label { font-size:11px; font-weight:700; color:#6c757d; text-transform:uppercase; }
.global-field input { border:1px solid #ced4da; border-radius:5px; padding:5px 9px; font-size:13px; width:90px; }
.btn-global { padding:7px 18px; background:#6C3483; color:#fff; border:none; border-radius:6px; font-size:13px; font-weight:700; cursor:pointer; }
.btn-global:hover { background:#4A235A; }

/* ── P2 Toolbar filtre ── */
.uxkd-toolbar { display:flex; flex-wrap:wrap; gap:10px; align-items:center; margin-bottom:12px; }
.uxkd-toolbar input[type=text] { border:1px solid #ced4da; border-radius:5px; padding:5px 10px; font-size:13px; width:260px; }
.uxkd-toolbar label { font-size:13px; color:#495057; display:flex; align-items:center; gap:6px; cursor:pointer; }
.badge-config { font-size:11px; padding:3px 8px; border-radius:12px; background:#d4edda; color:#155724; font-weight:700; margin-left:auto; }

/* ── Tableau ── */
#tbl-targets { width:100%; border-collapse:collapse; font-size:13px; }
#tbl-targets th { background:#343a40; color:#fff; padding:9px 12px; text-align:left; font-size:11px; font-weight:600; letter-spacing:.3px; }
#tbl-targets td { padding:8px 12px; border-bottom:1px solid #f5f5f5; vertical-align:middle; }
#tbl-targets tr:hover td { background:#f8f9fa; }
#tbl-targets tr.configured td:first-child::after { content:'✓'; color:#1E8449; font-size:11px; margin-left:6px; }
#tbl-targets tr.hidden-row { display:none; }
#tbl-targets input[type=number] { border:1px solid #ced4da; border-radius:4px; padding:4px 7px; font-size:13px; width:75px; text-align:center; }
#tbl-targets input[type=number]:focus { border-color:#2E75B6; outline:none; box-shadow:0 0 0 2px rgba(46,117,182,.15); }

/* ── P3 Import/Export ── */
.uxkd-io-row { display:flex; gap:12px; flex-wrap:wrap; align-items:center; }
.btn-export { padding:7px 16px; background:#1E8449; color:#fff; border:none; border-radius:6px; font-size:13px; font-weight:600; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:5px; }
.btn-export:hover { background:#155a32; color:#fff; }
.btn-import { padding:7px 16px; background:#2E75B6; color:#fff; border:none; border-radius:6px; font-size:13px; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:5px; }
.btn-import:hover { background:#1e5a96; }
.csv-hint { font-size:11px; color:#6c757d; }

/* ── Footer boutons ── */
.uxkd-footer { display:flex; gap:10px; justify-content:flex-end; padding-top:14px; border-top:2px solid #e9ecef; margin-top:4px; }
.btn-save { padding:8px 24px; background:#2E75B6; color:#fff; border:none; border-radius:6px; font-size:14px; font-weight:700; cursor:pointer; }
.btn-save:hover { background:#1e5a96; }
.btn-back { padding:8px 18px; background:#f8f9fa; color:#495057; border:1px solid #dee2e6; border-radius:6px; font-size:13px; text-decoration:none; }
</style>

<div class="uxkd-targets">

  <!-- En-tête -->
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <div>
      <h2 style="margin:0;font-size:20px;font-weight:800">Objectifs par catégorie ITIL</h2>
      <p style="margin:4px 0 0;color:#6c757d;font-size:13px">
        Définissez les seuils FCR, utilisation KB et qualité KB par catégorie.
      </p>
    </div>
    <span class="badge-config"><?= $configuredCats ?> / <?= $totalCats ?> catégories configurées</span>
  </div>

  <?php echo Html::scriptBlock(''); // flush messages GLPI ?>

  <!-- ═══════════════════════════════════════════
       P1 — Objectifs globaux par défaut
       ═══════════════════════════════════════════ -->
  <div class="uxkd-card">
    <h3>① Objectifs globaux par défaut</h3>
    <p style="font-size:13px;color:#6c757d;margin:0 0 14px">
      Définissez des valeurs par défaut et appliquez-les en un clic à toutes les catégories <strong>non encore configurées</strong>.
    </p>
    <form method="POST" action="">
      <?= Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken(true)]) ?>
      <?= Html::hidden('save', ['value' => '1']) ?>
      <?= Html::hidden('apply_global', ['value' => '1']) ?>
      <div class="global-row">
        <div class="global-field">
          <label>FCR cible (%)</label>
          <input type="number" name="global_fcr" value="50" min="0" max="100">
        </div>
        <div class="global-field">
          <label>KB Usage cible (%)</label>
          <input type="number" name="global_kb" value="30" min="0" max="100">
        </div>
        <div class="global-field">
          <label>Qualité KB (/5)</label>
          <input type="number" name="global_quality" value="3.5" min="0" max="5" step="0.1">
        </div>
        <div class="global-field" style="justify-content:flex-end">
          <label>&nbsp;</label>
          <button type="submit" class="btn-global">⚡ Appliquer aux non configurées</button>
        </div>
      </div>
    </form>
  </div>

  <!-- ═══════════════════════════════════════════
       P3 — Import / Export CSV
       ═══════════════════════════════════════════ -->
  <div class="uxkd-card">
    <h3>② Import / Export CSV</h3>
    <div class="uxkd-io-row">

      <!-- Export -->
      <a href="?export_csv=1" class="btn-export">⬇ Exporter CSV</a>

      <!-- Import -->
      <form method="POST" action="" enctype="multipart/form-data" style="display:flex;align-items:center;gap:8px">
        <?= Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken(true)]) ?>
        <?= Html::hidden('import_csv', ['value' => '1']) ?>
        <input type="file" name="csv_file" accept=".csv"
               style="border:1px solid #ced4da;border-radius:5px;padding:4px 8px;font-size:13px">
        <button type="submit" class="btn-import">⬆ Importer CSV</button>
      </form>

      <span class="csv-hint">
        Format attendu : <code>itilcategories_id ; FCR% ; KB% ; Qualite</code><br>
        Astuce : exporter d'abord, modifier dans Excel, puis réimporter.
      </span>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════
       P2 — Tableau inline éditable avec filtres
       ═══════════════════════════════════════════ -->
  <div class="uxkd-card">
    <h3>③ Objectifs par catégorie</h3>

    <!-- Toolbar filtre -->
    <div class="uxkd-toolbar">
      <input type="text" id="filter-search" placeholder="🔍 Filtrer par nom de catégorie…" oninput="filterTable()">
      <label>
        <input type="checkbox" id="filter-unconfigured" onchange="filterTable()">
        Afficher seulement les non configurées
      </label>
      <button type="button" onclick="resetAll()"
              style="padding:5px 12px;background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;border-radius:5px;font-size:12px;cursor:pointer">
        ✕ Réinitialiser tout
      </button>
    </div>

    <form method="POST" action="" id="form-targets">
      <?= Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken(true)]) ?>
      <?= Html::hidden('save', ['value' => '1']) ?>

      <div style="overflow-x:auto">
        <table id="tbl-targets">
          <thead>
            <tr>
              <th style="width:40%">Catégorie ITIL</th>
              <th>FCR cible (%)</th>
              <th>KB Usage cible (%)</th>
              <th>Qualité KB (/5)</th>
              <th style="width:80px">Statut</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($categories as $cat):
            $t        = $targets[$cat['id']] ?? [];
            $isConfig = !empty($t);
            $fcr      = round(($t['target_fcr']        ?? 0.5) * 100);
            $kb       = round(($t['target_kb_usage']   ?? 0.3) * 100);
            $q        = $t['target_kb_quality'] ?? 3.5;
            $id       = intval($cat['id']);
            $name     = Html::cleanInputText($cat['completename']);
            $rowClass = $isConfig ? 'configured' : 'unconfigured';
          ?>
            <tr class="<?= $rowClass ?>" data-name="<?= strtolower($name) ?>" data-configured="<?= $isConfig ? '1' : '0' ?>">
              <td><?= $name ?></td>
              <td>
                <input type="number" name="cats[<?= $id ?>][fcr]"
                       value="<?= $fcr ?>" min="0" max="100">
                <span style="color:#6c757d;font-size:12px"> %</span>
              </td>
              <td>
                <input type="number" name="cats[<?= $id ?>][kb]"
                       value="<?= $kb ?>" min="0" max="100">
                <span style="color:#6c757d;font-size:12px"> %</span>
              </td>
              <td>
                <input type="number" name="cats[<?= $id ?>][quality]"
                       value="<?= $q ?>" min="0" max="5" step="0.1">
                <span style="color:#6c757d;font-size:12px"> /5</span>
              </td>
              <td>
                <?php if ($isConfig): ?>
                  <span style="color:#1E8449;font-size:12px;font-weight:700">✓ Configuré</span>
                <?php else: ?>
                  <span style="color:#adb5bd;font-size:12px">— Défaut</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div id="no-results" style="display:none;text-align:center;padding:20px;color:#6c757d;font-size:13px">
        Aucune catégorie ne correspond au filtre.
      </div>

      <div class="uxkd-footer">
        <a class="btn-back" href="config.php">← Retour</a>
        <button type="submit" class="btn-save">💾 Tout enregistrer</button>
      </div>
    </form>
  </div>

</div><!-- /uxkd-targets -->

<script>
function filterTable() {
    const search       = document.getElementById('filter-search').value.toLowerCase().trim();
    const unconfigOnly = document.getElementById('filter-unconfigured').checked;
    const rows         = document.querySelectorAll('#tbl-targets tbody tr');
    let visible = 0;

    rows.forEach(row => {
        const name       = row.dataset.name || '';
        const configured = row.dataset.configured === '1';
        const matchSearch = !search || name.includes(search);
        const matchFilter = !unconfigOnly || !configured;

        if (matchSearch && matchFilter) {
            row.classList.remove('hidden-row');
            visible++;
        } else {
            row.classList.add('hidden-row');
        }
    });

    document.getElementById('no-results').style.display = visible === 0 ? 'block' : 'none';
}

function resetAll() {
    if (!confirm('Réinitialiser tous les objectifs aux valeurs par défaut ?')) return;
    document.querySelectorAll('#tbl-targets tbody tr').forEach(row => {
        const inputs = row.querySelectorAll('input[type=number]');
        if (inputs[0]) inputs[0].value = 50;   // FCR
        if (inputs[1]) inputs[1].value = 30;   // KB
        if (inputs[2]) inputs[2].value = 3.5;  // Qualité
    });
}
</script>

<?php Html::footer(); ?>
