<?php
/**
 * inc/kbrating.class.php — UXKnowDash v1.5.0
 * CSRF : Session::getNewCSRFToken(true) — token standalone indépendant par widget
 * i18n : chaînes UI via __('chaine', 'uxknowdash'), chaînes JS via json_encode
 *
 * RÈGLE CSRF GLPI 11 :
 *   - getNewCSRFToken() sans paramètre retourne $CURRENTCSRFTOKEN (même token pour toute la requête)
 *   - getNewCSRFToken(true) génère un token indépendant ajouté à $_SESSION['glpicsrftokens']
 *   - Utiliser TRUE dès qu'une page effectue plusieurs POST distincts (widget + tracking)
 */
class PluginUxknowdashKBRating extends CommonDBTM {

    public static $rightname = 'plugin_uxknowdash_view';

    public static function saveRating(
        int $knowbaseitems_id,
        int $users_id,
        int $rating,
        ?int $tickets_id = null
    ): bool {
        global $DB;

        $rating = max(0, min(5, $rating));

        $existing = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => 'glpi_plugin_uxknowdash_kbratings',
            'WHERE'  => [
                'knowbaseitems_id' => $knowbaseitems_id,
                'users_id'         => $users_id,
            ],
            'LIMIT'  => 1,
        ]);

        if ($existing->count() > 0) {
            $row    = $existing->current();
            $result = $DB->update(
                'glpi_plugin_uxknowdash_kbratings',
                ['rating' => $rating],
                ['id'     => $row['id']]
            );
        } else {
            $data = [
                'knowbaseitems_id' => $knowbaseitems_id,
                'users_id'         => $users_id,
                'rating'           => $rating,
            ];
            if ($tickets_id !== null) {
                $data['tickets_id'] = $tickets_id;
            }
            $result = $DB->insert('glpi_plugin_uxknowdash_kbratings', $data);
        }

        return (bool) $result;
    }

    public static function getStats(int $knowbaseitems_id): array {
        global $DB;
        $rows = $DB->request([
            'SELECT' => [
                new QueryExpression('AVG(rating) AS ' . $DB->quoteName('avg_rating')),
                new QueryExpression('COUNT(id) AS '   . $DB->quoteName('vote_count')),
            ],
            'FROM'  => 'glpi_plugin_uxknowdash_kbratings',
            'WHERE' => [
                'knowbaseitems_id' => $knowbaseitems_id,
                new QueryExpression('rating > 0'),
            ],
        ]);
        $row = $rows->current();
        return [
            'avg'   => $row ? round((float)$row['avg_rating'], 1) : 0.0,
            'count' => $row ? (int)$row['vote_count'] : 0,
        ];
    }

    public static function getUserRating(int $knowbaseitems_id, int $users_id): int {
        global $DB;
        $rows = $DB->request([
            'SELECT' => ['rating'],
            'FROM'   => 'glpi_plugin_uxknowdash_kbratings',
            'WHERE'  => [
                'knowbaseitems_id' => $knowbaseitems_id,
                'users_id'         => $users_id,
            ],
            'LIMIT'  => 1,
        ]);
        $row = $rows->current();
        return $row ? (int)$row['rating'] : 0;
    }

    public static function renderWidget(int $kbId, string $rootDoc): string {
        $stats     = self::getStats($kbId);
        $myRating  = self::getUserRating($kbId, Session::getLoginUserID());
        $avg       = number_format($stats['avg'], 1);
        $count     = $stats['count'];
        $ajaxBase  = $rootDoc . '/plugins/uxknowdash/ajax';

        // ✅ Token CSRF standalone — getNewCSRFToken(true) obligatoire
        // Si on utilise getNewCSRFToken() sans true, le token est partagé
        // avec csrf_track dans get_kb_widget.php et est consommé en premier
        // par track_kb_view.php → rate_kb.php obtient 403.
        $csrfToken = Session::getNewCSRFToken(true);

        // Chaînes JS i18n
        $i18n = [
            'rate_article'  => __('Rate this article', 'uxknowdash'),
            'saved'         => __('Your rating has been saved.', 'uxknowdash'),
            'save_error'    => __('Error saving rating.', 'uxknowdash'),
            'network_error' => __('Network error.', 'uxknowdash'),
            'votes'         => __('votes', 'uxknowdash'),
            'vote'          => __('vote', 'uxknowdash'),
        ];

        // Votes libellé
        $votesLabel = $count > 1
            ? __('votes', 'uxknowdash')
            : __('vote', 'uxknowdash');

        ob_start(); ?>
<div class="uxknowdash-rating-widget"
     data-kb-id="<?= $kbId ?>"
     data-csrf="<?= htmlspecialchars($csrfToken) ?>"
     style="margin:16px 0; padding:12px 16px;
            border:1px solid #d0dce8; border-radius:6px;
            background:#f7fafc; display:inline-flex; align-items:center; gap:12px;">
  <span style="font-weight:600; color:#2E75B6; font-size:13px;">
    <?= __('Rate this article', 'uxknowdash') ?>
  </span>
  <div class="uxkd-stars" style="display:flex; gap:4px; cursor:pointer;">
    <?php for ($i=1; $i<=5; $i++): ?>
    <span class="uxkd-star" data-value="<?= $i ?>"
          style="font-size:22px; color:<?= $i <= $myRating ? '#F4D03F' : '#ccc' ?>;">★</span>
    <?php endfor; ?>
  </div>
  <span class="uxkd-avg" style="font-size:12px; color:#555;">
    <?= $avg ?>/5 (<?= $count ?> <?= $votesLabel ?>)
  </span>
</div>
<script>
(function() {
  const widget   = document.querySelector('.uxknowdash-rating-widget[data-kb-id="<?= $kbId ?>"]');
  const stars    = widget.querySelectorAll('.uxkd-star');
  const avgEl    = widget.querySelector('.uxkd-avg');
  const ajaxUrl  = <?= json_encode($ajaxBase . '/rate_kb.php') ?>;
  const I18N     = <?= json_encode($i18n, JSON_UNESCAPED_UNICODE) ?>;
  let   myRating = <?= $myRating ?>;

  function refreshStars(val) {
    stars.forEach(s => s.style.color = parseInt(s.dataset.value) <= val ? '#F4D03F' : '#ccc');
  }

  stars.forEach(star => {
    star.addEventListener('mouseenter', () => refreshStars(parseInt(star.dataset.value)));
    star.addEventListener('mouseleave', () => refreshStars(myRating));
    star.addEventListener('click', async () => {
      const rating = parseInt(star.dataset.value);
      const fd = new FormData();
      fd.append('knowbaseitems_id', '<?= $kbId ?>');
      fd.append('rating', rating);
      // ✅ Token CSRF lu depuis data-csrf du widget (token standalone indépendant)
      fd.append('_glpi_csrf_token', widget.dataset.csrf || '');
      try {
        const resp = await fetch(ajaxUrl, { method:'POST', body:fd, credentials:'same-origin' });
        const data = await resp.json();
        if (data.ok) {
          myRating = data.my_rating;
          refreshStars(myRating);
          const vLabel = data.count > 1 ? I18N.votes : I18N.vote;
          avgEl.textContent = data.avg + '/5 (' + data.count + ' ' + vLabel + ')';
        } else {
          console.warn('[UXKnowDash] rate_kb error:', data);
          alert(I18N.save_error + (data.error ? ' : ' + data.error : ''));
        }
      } catch(e) {
        console.warn('[UXKnowDash] fetch error:', e);
        alert(I18N.network_error);
      }
    });
  });
})();
</script>
<?php
        return ob_get_clean();
    }
}
