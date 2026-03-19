<?php
/**
 * front/kblist.php — UXKnowDash v1.5.0
 * i18n : chaînes PHP via __(), chaînes JS via window.UXKD_KB_I18N
 */
include('../../../inc/includes.php');
Session::checkRight('plugin_uxknowdash_view', READ);
global $CFG_GLPI;
Html::header(__('UXKnowDash', 'uxknowdash') . ' — ' . __('KB List', 'uxknowdash'), $_SERVER['PHP_SELF'], 'tools', 'PluginUxknowdashMenuKblist');

// Chaînes JS i18n
$i18n = [
    'not_rated'     => __('Not rated', 'uxknowdash'),
    'never'         => __('Never', 'uxknowdash'),
    'loading'       => __('Loading...', 'uxknowdash'),
    'no_result'     => __('No article found.', 'uxknowdash'),
    'network_error' => __('Network error.', 'uxknowdash'),
    'error_prefix'  => __('Error', 'uxknowdash'),
    'articles'      => __('articles', 'uxknowdash'),
    'article'       => __('article', 'uxknowdash'),
];
?>
<div class="container-fluid mt-3">
  <div class="card">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
      <h3 class="card-title mb-0">
        <i class="ti ti-books me-2" style="color:#2E75B6"></i><?= __('KB Article', 'uxknowdash') ?>
      </h3>
      <div class="d-flex align-items-center gap-2">
        <span class="badge bg-blue" id="uxkd-kb-count">…</span>
        <button class="btn btn-sm btn-success" id="uxkd-kb-btn-csv">
          <i class="ti ti-download me-1"></i><?= __('Export CSV', 'uxknowdash') ?>
        </button>
      </div>
    </div>
    <div class="card-body pb-2">
      <div class="d-flex gap-2 mb-3 flex-wrap">
        <input type="text" id="uxkd-kb-search" class="form-control form-control-sm"
               style="max-width:320px" placeholder="<?= __('Search article, author, category...', 'uxknowdash') ?>">
        <button class="btn btn-sm btn-primary" id="uxkd-kb-btn-search">
          <i class="ti ti-search"></i> <?= __('Apply', 'uxknowdash') ?>
        </button>
        <button class="btn btn-sm btn-ghost-secondary" id="uxkd-kb-btn-clear">
          <i class="ti ti-x"></i> <?= __('Reset', 'uxknowdash') ?>
        </button>
      </div>
      <div class="table-responsive">
        <table class="table table-vcenter table-hover card-table" style="font-size:12px">
          <thead>
            <tr>
              <th style="cursor:pointer;white-space:nowrap" data-sort="id">ID <i class="ti ti-selector ms-1 text-muted"></i></th>
              <th style="cursor:pointer" data-sort="name"><?= __('KB Article', 'uxknowdash') ?> <i class="ti ti-selector ms-1 text-muted"></i></th>
              <th style="cursor:pointer;white-space:nowrap" data-sort="creator"><?= __('Creator', 'uxknowdash') ?> <i class="ti ti-selector ms-1 text-muted"></i></th>
              <th style="cursor:pointer;white-space:nowrap" data-sort="kb_category"><?= __('KB Category', 'uxknowdash') ?> <i class="ti ti-selector ms-1 text-muted"></i></th>
              <th style="cursor:pointer;white-space:nowrap" data-sort="itil_categories"><?= __('ITIL Categories', 'uxknowdash') ?> <i class="ti ti-selector ms-1 text-muted"></i></th>
              <th style="cursor:pointer;white-space:nowrap" data-sort="avg_rating"><?= __('Rating', 'uxknowdash') ?> <i class="ti ti-selector ms-1 text-muted"></i></th>
              <th style="cursor:pointer;white-space:nowrap" data-sort="nb_tickets"><?= __('Linked Tickets', 'uxknowdash') ?> <i class="ti ti-selector ms-1 text-muted"></i></th>
              <th style="cursor:pointer;white-space:nowrap" data-sort="nb_assets"><?= __('Linked Assets', 'uxknowdash') ?> <i class="ti ti-selector ms-1 text-muted"></i></th>
              <th style="cursor:pointer;white-space:nowrap" data-sort="date_mod"><?= __('Last revision', 'uxknowdash') ?> <i class="ti ti-selector ms-1 text-muted"></i></th>
              <th style="cursor:pointer;white-space:nowrap" data-sort="view"><?= __('Views', 'uxknowdash') ?> <i class="ti ti-selector ms-1 text-muted"></i></th>
              <th style="cursor:pointer;white-space:nowrap" data-sort="last_view"><?= __('Last Consultation', 'uxknowdash') ?> <i class="ti ti-selector ms-1 text-muted"></i></th>
              <th><?= __('Actions', 'uxknowdash') ?></th>
            </tr>
          </thead>
          <tbody id="uxkd-kb-tbody">
            <tr><td colspan="12" class="text-center py-4">
              <div class="spinner-border spinner-border-sm text-primary"></div> <?= __('Loading...', 'uxknowdash') ?>
            </td></tr>
          </tbody>
        </table>
      </div>
      <nav class="d-flex justify-content-center mt-3" id="uxkd-kb-pagination"></nav>
    </div>
  </div>
</div>

<script>
(function() {
    const rootDoc = <?= json_encode($CFG_GLPI['root_doc']) ?>;
    const ajaxUrl = rootDoc + '/plugins/uxknowdash/ajax/get_kblist.php';
    const I18N    = <?= json_encode($i18n, JSON_UNESCAPED_UNICODE) ?>;
    let sort = 'id', order = 'ASC', search = '', page = 1;
    const perpage = 25;

    function stars(avg, votes) {
        if (votes === 0) return '<span class="text-muted small">' + I18N.not_rated + '</span>';
        let s = '';
        for (let i = 1; i <= 5; i++) s += '<span style="color:' + (i <= Math.round(avg) ? '#F4D03F' : '#ccc') + ';font-size:13px;">★</span>';
        return s + ' <small class="text-muted">(' + parseFloat(avg).toFixed(1) + '/' + votes + 'v)</small>';
    }

    function badge(text, cls) {
        if (!text) return '<span class="text-muted">—</span>';
        return text.split(', ').map(t => '<span class="badge ' + cls + ' me-1" style="font-size:10px">' + t + '</span>').join('');
    }

    function lastViewBadge(lastView, recent) {
        if (!lastView) return '<span class="badge bg-red-lt" style="font-size:10px"><i class="ti ti-eye-off me-1"></i>' + I18N.never + '</span>';
        const cls  = recent ? 'bg-green-lt' : 'bg-orange-lt';
        const icon = recent ? 'ti-circle-check' : 'ti-alert-triangle';
        return '<span class="badge ' + cls + '" style="font-size:10px"><i class="ti ' + icon + ' me-1"></i>' + lastView + '</span>';
    }

    function load() {
        const tbody = document.getElementById('uxkd-kb-tbody');
        tbody.innerHTML = '<tr><td colspan="12" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div> ' + I18N.loading + '</td></tr>';
        fetch(ajaxUrl + '?' + new URLSearchParams({ sort, order, search, page, perpage }), { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                if (data.error) {
                    tbody.innerHTML = '<tr><td colspan="12" class="text-danger text-center py-3">' + I18N.error_prefix + ' : ' + data.error + '</td></tr>';
                    return;
                }
                const label = data.total > 1 ? I18N.articles : I18N.article;
                document.getElementById('uxkd-kb-count').textContent = data.total + ' ' + label;
                if (!data.rows.length) {
                    tbody.innerHTML = '<tr><td colspan="12" class="text-center text-muted py-4">' + I18N.no_result + '</td></tr>';
                    document.getElementById('uxkd-kb-pagination').innerHTML = '';
                    return;
                }
                tbody.innerHTML = data.rows.map(r => `
                <tr>
                  <td class="text-muted">${r.id}</td>
                  <td><a href="${rootDoc}/front/knowbaseitem.form.php?id=${r.id}" target="_blank" class="fw-bold text-decoration-none">${r.name||'—'}</a></td>
                  <td style="white-space:nowrap">${r.creator||'—'}</td>
                  <td>${badge(r.kb_category,'bg-azure-lt')}</td>
                  <td>${badge(r.itil_categories,'bg-purple-lt')}</td>
                  <td style="white-space:nowrap">${stars(r.avg_rating,r.vote_count)}</td>
                  <td class="text-center"><span class="badge bg-blue-lt">${r.nb_tickets}</span></td>
                  <td class="text-center"><span class="badge bg-green-lt">${r.nb_assets}</span></td>
                  <td style="white-space:nowrap" class="text-muted">${r.date_mod||'—'}</td>
                  <td class="text-center"><span class="badge bg-teal-lt">${r.view}</span></td>
                  <td>${lastViewBadge(r.last_view, r.last_view_recent)}</td>
                  <td><a href="${rootDoc}/front/knowbaseitem.form.php?id=${r.id}" class="btn btn-sm btn-ghost-secondary" target="_blank"><i class="ti ti-eye"></i></a></td>
                </tr>`).join('');

                const pages = Math.ceil(data.total / perpage);
                let pag = '<ul class="pagination pagination-sm">';
                for (let p = 1; p <= pages; p++) pag += `<li class="page-item ${p===page?'active':''}"><a class="page-link" href="#" data-page="${p}">${p}</a></li>`;
                pag += '</ul>';
                document.getElementById('uxkd-kb-pagination').innerHTML = pages > 1 ? pag : '';
            })
            .catch(e => {
                tbody.innerHTML = '<tr><td colspan="12" class="text-center text-danger py-4">' + I18N.network_error + '</td></tr>';
                console.error(e);
            });
    }

    document.querySelectorAll('th[data-sort]').forEach(th => {
        th.addEventListener('click', () => { const col = th.dataset.sort; order = (sort===col&&order==='ASC')?'DESC':'ASC'; sort=col; page=1; load(); });
    });
    document.getElementById('uxkd-kb-btn-search').addEventListener('click', () => { search=document.getElementById('uxkd-kb-search').value.trim(); page=1; load(); });
    document.getElementById('uxkd-kb-search').addEventListener('keyup', e => { if(e.key==='Enter') document.getElementById('uxkd-kb-btn-search').click(); });
    document.getElementById('uxkd-kb-btn-clear').addEventListener('click', () => { document.getElementById('uxkd-kb-search').value=''; search=''; page=1; load(); });
    document.getElementById('uxkd-kb-pagination').addEventListener('click', e => { e.preventDefault(); const l=e.target.closest('[data-page]'); if(l){page=parseInt(l.dataset.page);load();} });
    document.getElementById('uxkd-kb-btn-csv').addEventListener('click', () => {
        window.location.href = ajaxUrl + '?' + new URLSearchParams({ sort, order, search, page:1, perpage:99999, export:'csv' });
    });

    load();
})();
</script>

<?php Html::footer(); ?>
