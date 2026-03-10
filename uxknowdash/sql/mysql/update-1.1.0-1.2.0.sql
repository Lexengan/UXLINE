-- UXKnowDash — Script de migration v1.0.0 -> v1.1.0
-- Ajout : Module A (Notation KB) — table kbratings + mise à jour vue

-- Ajout de la table de notation KB (Module A)
-- IF NOT EXISTS : sécurise la migration si exécutée deux fois
CREATE TABLE IF NOT EXISTS `glpi_plugin_uxknowdash_kbratings` (
  `id`               int      NOT NULL AUTO_INCREMENT,
  `knowbaseitems_id` int      NOT NULL COMMENT 'FK -> glpi_knowbaseitems.id',
  `users_id`         int      NOT NULL COMMENT 'FK -> glpi_users.id',
  `tickets_id`       int      DEFAULT NULL COMMENT 'Ticket a l\'origine de la notation',
  `rating`           tinyint  NOT NULL DEFAULT 0 COMMENT 'Note de 0 a 5',
  `date_creation`    datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_kb` (`knowbaseitems_id`, `users_id`),
  KEY `idx_user`     (`users_id`)
) ENGINE=InnoDB COMMENT='Notes utilisateurs sur les articles KB';

-- Mise à jour de la vue vw_uxknowdash_metrics
-- DROP obligatoire car CREATE OR REPLACE VIEW ne modifie pas les colonnes existantes
-- si la vue a été créée avec une structure différente en v1.0.0
DROP VIEW IF EXISTS `vw_uxknowdash_metrics`;

CREATE VIEW `vw_uxknowdash_metrics` AS
SELECT
  t.id,
  t.entities_id,
  t.itilcategories_id,
  CASE WHEN t.solvedate IS NOT NULL
       AND NOT EXISTS (
         SELECT 1 FROM glpi_itilsolutions s
         WHERE s.items_id = t.id AND s.itemtype = 'Ticket'
           AND DATE(s.date_creation) > DATE(t.solvedate)
       )
  THEN 1 ELSE 0 END AS is_fcr,
  CASE WHEN EXISTS (
    SELECT 1 FROM glpi_knowbaseitems_items ki
    WHERE ki.items_id = t.id AND ki.itemtype = 'Ticket'
  ) THEN 1 ELSE 0 END AS kb_used,
  COALESCE((
    SELECT AVG(r.rating)
    FROM glpi_plugin_uxknowdash_kbratings r
    JOIN glpi_knowbaseitems_items ki ON ki.knowbaseitems_id = r.knowbaseitems_id
    WHERE ki.items_id = t.id AND ki.itemtype = 'Ticket' AND r.rating > 0
  ), (
    SELECT AVG(k.rating)
    FROM glpi_knowbaseitems k
    JOIN glpi_knowbaseitems_items ki ON ki.knowbaseitems_id = k.id
    WHERE ki.items_id = t.id AND ki.itemtype = 'Ticket' AND k.rating > 0
  ), 0) AS kb_quality
FROM glpi_tickets t WHERE t.is_deleted = 0;