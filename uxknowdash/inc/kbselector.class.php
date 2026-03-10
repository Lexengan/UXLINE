<?php
/**
 * inc/kbselector.class.php
 * Module B — Sélecteur KB dans le ticket + autosaisie du lien KB natif.
 *
 * Ce module NE crée PAS de table supplémentaire : il écrit dans la table GLPI
 * native glpi_knowbaseitems_items via la classe KnowbaseItem_Item.
 */
class PluginUxknowdashKBSelector extends CommonGLPI {
 
    public static $rightname = 'plugin_uxknowdash_view';
 
    /**
     * Génère le widget HTML + JS du sélecteur KB.
     * Affiché dans l'onglet Solution/Admin du ticket via le hook post_show_tab.
     *
     * @param int    $ticketId  ID du ticket
     * @param string $rootDoc   CFG_GLPI[root_doc]
     * @return string HTML
     */
    public static function renderWidget(int $ticketId, string $rootDoc): string {
        $ajaxBase = $rootDoc . '/plugins/uxknowdash/ajax';
 
        // Récupérer les KB déjà liées à ce ticket
        $linked = self::getLinkedKBs($ticketId);
 
        ob_start(); ?>
<div class="uxknowdash-kb-selector" id="uxkd-kbsel-<?= $ticketId ?>" style="
     margin:16px 0; padding:16px; border:1px solid #2E75B6;
     border-radius:6px; background:#f0f6fc;">
 
  <div style="font-weight:600; color:#2E75B6; margin-bottom:10px; font-size:13px;">
    Lier une Base de Connaissance (UXKnowDash)
  </div>
 
  <!-- Recherche KB -->
 
  <div style="display:flex; gap:8px; margin-bottom:10px;">
    <input type="text" id="uxkd-kb-search-<?= $ticketId ?>"
           placeholder="Rechercher un article KB par mot-clé..."
           style="flex:1; padding:6px 10px; border:1px solid #ccc; border-radius:4px; font-size:13px;">
    <button type="button" onclick="uxkdSearchKB(<?= $ticketId ?>)"
            style="padding:6px 14px; background:#2E75B6; color:#fff;
                   border:none; border-radius:4px; cursor:pointer; font-size:13px;">
      Rechercher
    </button>
  </div>
 
  <!-- Résultats de recherche -->
 
  <div id="uxkd-kb-results-<?= $ticketId ?>" style="
       max-height:200px; overflow-y:auto; border:1px solid #ddd;
       border-radius:4px; display:none; background:#fff; margin-bottom:10px;">
  </div>
 
  <!-- KB déjà liées -->
 
  <div style="font-size:12px; color:#555; margin-bottom:6px; font-weight:600;">
    KB liées à ce ticket :
  </div>
  <div id="uxkd-kb-linked-<?= $ticketId ?>">
    <?php if (empty($linked)): ?>
      <span style="color:#999; font-size:12px; font-style:italic;">Aucune KB liée pour l'instant.</span>
    <?php else: ?>
      <?php foreach ($linked as $kb): ?>
      <div class="uxkd-linked-item" data-kb-id="<?= $kb['id'] ?>" style="
           display:flex; justify-content:space-between; align-items:center;
           padding:4px 8px; margin-bottom:4px; background:#e8f0fa;
           border-radius:4px; font-size:12px;">
        <span><?= htmlspecialchars($kb['name']) ?></span>
        <button type="button" onclick="uxkdUnlinkKB(<?= $ticketId ?>, <?= $kb['id'] ?>)"
                style="background:none; border:none; color:#C00000;
                       cursor:pointer; font-size:14px; padding:0 4px;">✕</button>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
 
<script>
const _uxkdAjaxBase = '<?= $ajaxBase ?>';
 
async function uxkdSearchKB(ticketId) {
  const q = document.getElementById('uxkd-kb-search-' + ticketId).value.trim();
  if (!q) return;
  const resp = await fetch(_uxkdAjaxBase + '/search_kb.php?q=' + encodeURIComponent(q));
  const data = await resp.json();
  const div  = document.getElementById('uxkd-kb-results-' + ticketId);
  div.style.display = 'block';
  div.innerHTML = '';
  if (!data.length) {
    div.innerHTML = '<div style="padding:8px;color:#999;font-size:12px;">Aucun résultat.</div>';
    return;
  }
  data.forEach(kb => {
    const row = document.createElement('div');
    row.style.cssText = 'padding:8px 12px;cursor:pointer;border-bottom:1px solid #f0f0f0;font-size:13px;';
    row.innerHTML = '<strong>' + kb.name + '</strong> <span style="color:#999;font-size:11px;">#' + kb.id + '</span>';
    row.addEventListener('mouseenter', () => row.style.background = '#e8f0fa');
    row.addEventListener('mouseleave', () => row.style.background = '');
    row.addEventListener('click', () => uxkdLinkKB(ticketId, kb.id, kb.name));
    div.appendChild(row);
  });
}
 
async function uxkdLinkKB(ticketId, kbId, kbName) {
  const fd = new FormData();
  fd.append('tickets_id', ticketId);
  fd.append('knowbaseitems_id', kbId);
  const resp = await fetch(_uxkdAjaxBase + '/link_kb.php', { method: 'POST', body: fd });
  const data = await resp.json();
  if (data.ok) {
    document.getElementById('uxkd-kb-results-' + ticketId).style.display = 'none';
    document.getElementById('uxkd-kb-search-' + ticketId).value = '';
    const linked = document.getElementById('uxkd-kb-linked-' + ticketId);
    // Supprimer le message "Aucune KB liée"
    linked.querySelectorAll('span[style*="italic"]').forEach(e => e.remove());
    const item = document.createElement('div');
    item.className = 'uxkd-linked-item';
    item.dataset.kbId = kbId;
    item.style.cssText = 'display:flex;justify-content:space-between;align-items:center;padding:4px 8px;margin-bottom:4px;background:#e8f0fa;border-radius:4px;font-size:12px;';
    item.innerHTML = '<span>' + kbName + '</span> <button type="button" onclick="uxkdUnlinkKB(' + ticketId + ',' + kbId + ')" style="background:none;border:none;color:#C00000;cursor:pointer;font-size:14px;padding:0 4px;">✕</button>';
    linked.appendChild(item);
  }
}
 
async function uxkdUnlinkKB(ticketId, kbId) {
  const fd = new FormData();
  fd.append('tickets_id', ticketId);
  fd.append('knowbaseitems_id', kbId);
  const resp = await fetch(_uxkdAjaxBase + '/unlink_kb.php', { method: 'POST', body: fd });
  const data = await resp.json();
  if (data.ok) {
    const el = document.querySelector('.uxkd-linked-item[data-kb-id="' + kbId + '"]');
    if (el) el.remove();
  }
}
</script>
    <?php
    return ob_get_clean();
    }
 
    /** Retourne les KB actuellement liées à un ticket (via glpi_knowbaseitems_items natif). */
    public static function getLinkedKBs(int $ticketId): array {
        global $DB;
        $rows = $DB->request([
            'SELECT' => ['k.id', 'k.name'],
            'FROM'   => 'glpi_knowbaseitems AS k',
            'INNER JOIN' => ['glpi_knowbaseitems_items AS ki' => ['ON' => ['ki' => 'knowbaseitems_id', 'k' => 'id']]],
            'WHERE'  => ['ki.itemtype' => 'Ticket', 'ki.items_id' => $ticketId, 'k.is_deleted' => 0],
        ]);
        return iterator_to_array($rows, false);
    }
}