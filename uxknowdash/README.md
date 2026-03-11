# UXKnowDash

> Plugin GLPI 11 — Tableau de bord FCR, utilisation et qualité de la base de connaissances

[![GLPI](https://img.shields.io/badge/GLPI-11.0%2B-blue)](https://glpi-project.org)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-green)](LICENSE)
[![Version](https://img.shields.io/badge/Version-1.5.0-orange)](https://github.com/VOTRE_ORG/uxknowdash/releases)

---

## Fonctionnalités

- **Taux FCR (First Contact Resolution)** — par catégorie et groupe technicien
- **Taux d'utilisation KB** — pourcentage de tickets résolus liés à un article KB
- **Notation qualité KB** — évaluation par étoiles des articles par les techniciens
- **Suivi des consultations KB** — date de dernière consultation avec badge de fraîcheur (vert / orange / rouge)
- **Taux de liaison KB** — statistiques globales et par technicien
- **Export CSV et PDF** — pour chaque section du tableau de bord
- **Sections réductibles** — interface accordéon
- **Graphiques interactifs** — barres, courbes, camembert via Chart.js

---

## Prérequis

| Composant | Version minimale |
|-----------|-----------------|
| GLPI      | 11.0.0          |
| PHP       | 8.1             |
| MariaDB   | 10.6            |

---

## Installation

### Méthode 1 — Marketplace GLPI (recommandée)

1. Dans GLPI : **Configuration → Plugins → Découvrir**
2. Rechercher **UXKnowDash**
3. Cliquer **Installer** puis **Activer**

### Méthode 2 — Installation manuelle

1. Télécharger la [dernière release](https://github.com/Lexengan/UXLINE/releases/)
2. Extraire dans le dossier `plugins/` de GLPI :
   ```
   glpi/plugins/uxknowdash/
   ```
3. Dans GLPI : **Configuration → Plugins → UXKnowDash → Installer** puis **Activer**

> **Important** : le nom du dossier doit être exactement `uxknowdash` (minuscules, sans tirets).

---

## Structure des fichiers

```
uxknowdash/
├── setup.php                        # Déclaration, hooks, install/uninstall
├── hook.php                         # Callbacks hooks GLPI
├── manifest.php                     # Constantes du plugin
├── uxknowdash.png                   # Logo 40x40px (catalogue plugins)
├── README.md
├── LICENSE
├── locales/
│   ├── fr_FR.po / fr_FR.mo          # Traduction française
│   └── en_GB.po / en_GB.mo          # Traduction anglaise
├── inc/
│   ├── menu.class.php               # Entrée menu Tableau de bord
│   ├── menukblist.class.php         # Entrée menu Liste KB
│   ├── matrix.class.php
│   ├── kbrating.class.php           # Widget notation KB
│   └── [autres classes]
├── front/
│   ├── index.php                    # Tableau de bord principal
│   ├── kblist.php                   # Liste des articles KB
│   └── config.php                   # Page de configuration
├── ajax/
│   ├── get_dashboard.php
│   ├── get_kb_widget.php            # Widget notation + tokens CSRF
│   ├── get_kblist.php               # Liste KB paginée
│   ├── get_kb_liaison.php           # Taux de liaison KB
│   ├── rate_kb.php                  # Enregistrement notation
│   └── track_kb_view.php            # Traçage consultations
├── js/
│   └── uxknowdash.js                # JS principal (widget, tracking, exports)
└── sql/mysql/
    ├── plugin_uxknowdash-1.0.0.sql  # Schéma complet v1.5.0 (idempotent)
    ├── update-1.0.0-1.1.0.sql       # Migration → v1.1.0
    ├── update-1.1.0-1.2.0.sql       # Migration → v1.2.0
    └── update-1.2.0-1.3.0.sql       # Migration → v1.3.0
```

---

## Tables créées

| Table | Description |
|-------|-------------|
| `glpi_plugin_uxknowdash_settings` | Paramètres du plugin |
| `glpi_plugin_uxknowdash_kbratings` | Notations KB par utilisateur |
| `glpi_plugin_uxknowdash_kbviews` | Consultations KB (dédupliquées 1h/user) |
| `glpi_plugin_uxknowdash_kbwatch` | Surveillance articles KB |
| `glpi_plugin_uxknowdash_matrixcache` | Cache matrice métriques |
| `glpi_plugin_uxknowdash_groupcategories` | Association groupes/catégories |
| `glpi_plugin_uxknowdash_focusprofiles` | Profils ciblés |
| `glpi_plugin_uxknowdash_category_targets` | Objectifs FCR/KB par catégorie |

Vue SQL : `vw_uxknowdash_metrics`

---

## Règles techniques GLPI 11 respectées

- Aucune requête SQL brute — utilisation exclusive de `$DB->request()`, `$DB->insert()`, `$DB->update()`
- Tokens CSRF individuels par POST via `Session::getNewCSRFToken(true)`
- Assets JS servis depuis `public/plugins/uxknowdash/js/` (copiés à l'installation)
- Droits insérés dans `glpi_profilerights` à l'installation pour tous les profils
- Compatible `DBmysqlIterator` — pas de `$DB->query()`

---

## Droits assignés à l'installation

| Profil | Droits |
|--------|--------|
| Super-Admin (id=4) | READ + UPDATE |
| Tous les autres profils | READ |

---

## Traductions disponibles

| Langue | Fichier |
|--------|---------|
| Français | `locales/fr_FR.po` |
| Anglais | `locales/en_GB.po` |

Pour compiler les fichiers `.mo` (nécessaire pour la production) :

```bash
msgfmt locales/fr_FR.po -o locales/fr_FR.mo
msgfmt locales/en_GB.po -o locales/en_GB.mo
```

---

## Changelog

### v1.5.0 (2026-03-10)
- Installation fraîche entièrement fonctionnelle : schéma SQL complet, copie JS public/, droits automatiques
- Correction CSRF : `getNewCSRFToken(true)` pour notation et tracking indépendants
- Schéma SQL idempotent (`IF NOT EXISTS` sur toutes les tables)
- Sections réductibles dans le tableau de bord

### v1.4.0
- Liste KB avec suivi des consultations et badge de fraîcheur
- Export CSV liste KB
- Taux de liaison KB global et par technicien

### v1.3.0
- Table `kbviews` — traçage des consultations KB (dédupliqué 1h/user)
- Widget notation KB avec token CSRF standalone

### v1.2.0
- Widget notation KB (étoiles) sur les pages articles
- Table `kbratings`

### v1.1.0
- Tableau de bord FCR et KB avec graphiques Chart.js
- Export CSV et PDF

### v1.0.0
- Première version — vue métriques SQL

---

## Licence

GPL v2+ — voir [LICENSE](LICENSE)

## Auteur

Alexandre THEBAUD
