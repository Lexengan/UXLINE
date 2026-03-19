-- ============================================================
-- UXKnowDash — Schéma complet v1.6.0
-- Installation fraîche : crée toutes les tables et la vue
-- Idempotent : IF NOT EXISTS sur toutes les tables
-- ============================================================

-- ── Table settings ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `glpi_plugin_uxknowdash_settings` (
  `id`            int          NOT NULL AUTO_INCREMENT,
  `entities_id`   int          NOT NULL DEFAULT 0,
  `name`          varchar(255) NOT NULL DEFAULT '',
  `value`         text,
  `date_creation` datetime     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_name_entity` (`name`, `entities_id`)
) ENGINE=InnoDB COMMENT='Paramètres UXKnowDash';

-- Valeurs par défaut
INSERT IGNORE INTO `glpi_plugin_uxknowdash_settings`
  (`entities_id`, `name`, `value`, `date_creation`) VALUES
  (0, 'cron_hour',                   '02:00', NOW()),
  (0, 'enable_kb_prompt_on_resolve', '0',     NOW()),
  (0, 'extra_field',                 '',      NOW());

-- ── Table matrixcache ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `glpi_plugin_uxknowdash_matrixcache` (
  `id`            int          NOT NULL AUTO_INCREMENT,
  `entities_id`   int          NOT NULL DEFAULT 0,
  `cache_key`     varchar(255) NOT NULL DEFAULT '',
  `cache_value`   longtext,
  `date_creation` datetime     DEFAULT NULL,
  `date_mod`      datetime     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_key_entity` (`cache_key`, `entities_id`)
) ENGINE=InnoDB COMMENT='Cache matrice UXKnowDash';

-- ── Table groupcategories ─────────────────────────────────────
-- Correction v1.6.0 : ajout colonnes `entities_id` et `level`
-- manquantes dans le schéma original (causaient Unknown column)
CREATE TABLE IF NOT EXISTS `glpi_plugin_uxknowdash_groupcategories` (
  `id`                int         NOT NULL AUTO_INCREMENT,
  `entities_id`       int         NOT NULL DEFAULT 0 COMMENT 'FK -> glpi_entities.id',
  `groups_id`         int         NOT NULL DEFAULT 0,
  `itilcategories_id` int         NOT NULL DEFAULT 0,
  `level`             varchar(20) NOT NULL DEFAULT 'N1' COMMENT 'Niveau de support : N1, N2, N3, Expert',
  `date_creation`     datetime    DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_group_cat` (`groups_id`, `itilcategories_id`),
  KEY `idx_entity_level` (`entities_id`, `level`)
) ENGINE=InnoDB COMMENT='Association groupes/catégories UXKnowDash';

-- ── Table focusprofiles ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS `glpi_plugin_uxknowdash_focusprofiles` (
  `id`            int      NOT NULL AUTO_INCREMENT,
  `profiles_id`   int      NOT NULL DEFAULT 0,
  `date_creation` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_profile` (`profiles_id`)
) ENGINE=InnoDB COMMENT='Profils ciblés UXKnowDash';

-- ── Table category_targets ────────────────────────────────────
CREATE TABLE IF NOT EXISTS `glpi_plugin_uxknowdash_category_targets` (
  `id`                int   NOT NULL AUTO_INCREMENT,
  `entities_id`       int   NOT NULL DEFAULT 0,
  `itilcategories_id` int   NOT NULL DEFAULT 0,
  `target_fcr`        float NOT NULL DEFAULT 0,
  `target_kb_usage`   float NOT NULL DEFAULT 0,
  `target_kb_quality` float NOT NULL DEFAULT 0,
  `date_creation`     datetime DEFAULT NULL,
  `date_mod`          datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_category` (`itilcategories_id`)
) ENGINE=InnoDB COMMENT='Objectifs par catégorie UXKnowDash';

-- ── Table kbwatch ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `glpi_plugin_uxknowdash_kbwatch` (
  `id`               int      NOT NULL AUTO_INCREMENT,
  `knowbaseitems_id` int      NOT NULL DEFAULT 0,
  `users_id`         int      NOT NULL DEFAULT 0,
  `date_creation`    datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_watch` (`knowbaseitems_id`, `users_id`)
) ENGINE=InnoDB COMMENT='Surveillance articles KB UXKnowDash';

-- ── Table kbratings ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `glpi_plugin_uxknowdash_kbratings` (
  `id`               int     NOT NULL AUTO_INCREMENT,
  `knowbaseitems_id` int     NOT NULL COMMENT 'FK -> glpi_knowbaseitems.id',
  `users_id`         int     NOT NULL COMMENT 'FK -> glpi_users.id',
  `tickets_id`       int     DEFAULT NULL COMMENT 'Ticket à l origine de la notation',
  `rating`           tinyint NOT NULL DEFAULT 0 COMMENT 'Note de 0 à 5',
  `date_creation`    datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_kb` (`knowbaseitems_id`, `users_id`),
  KEY `idx_user` (`users_id`)
) ENGINE=InnoDB COMMENT='Notes utilisateurs sur les articles KB';

-- ── Table kbviews ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `glpi_plugin_uxknowdash_kbviews` (
  `id`               int      NOT NULL AUTO_INCREMENT,
  `knowbaseitems_id` int      NOT NULL COMMENT 'FK -> glpi_knowbaseitems.id',
  `users_id`         int      NOT NULL COMMENT 'FK -> glpi_users.id',
  `date_view`        datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_kb`   (`knowbaseitems_id`),
  KEY `idx_user` (`users_id`),
  KEY `idx_date` (`date_view`)
) ENGINE=InnoDB COMMENT='Consultations articles KB UXKnowDash';

-- ── Vue métriques ─────────────────────────────────────────────
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
  ), 0) AS kb_quality
FROM glpi_tickets t WHERE t.is_deleted = 0;