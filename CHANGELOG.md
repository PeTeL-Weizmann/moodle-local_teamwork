# Changelog — local_teamwork

All notable changes to this project are documented in this file.  
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [2022080814] — 2022-08-08

### Changed
- Maintenance release; version bump after internal QA pass.

---

## [2022080811] — 2022-08-08

### Added
- New `counter` field on `local_teamwork` table to track sequential team card numbering per activity, ensuring default names like `Default 01`, `Default 02` are always unique and in order.

---

## [2022080808] — 2022-08-08

### Added
- Voice control feature: optional speech-recognition interface for hands-free team management.
- Admin settings page with per-language configurable wake-word tokens and eight voice-command schemes (add team, add named team, create N teams, move student, delete team, read users, read teams, sing a song).
- Supported languages: English (`en`), Hebrew (`he`), Hebrew kids (`he_kids`), Russian (`ru`).
- Upgrade step seeds default voice tokens and scheme phrases for all four languages.

---

## [2020060303] — 2020-06-03

### Fixed
- Upgrade step ensures `assign_plugin_config` records are correctly created or updated for all already-active Team Work assignments, preventing a mismatch between the teamwork active flag and the assignment submission plugin registration.

---

## [2019070815] — 2019-07-08

### Changed
- Database tables renamed from `teamwork`, `teamwork_groups`, `teamwork_members` to `local_teamwork`, `local_teamwork_groups`, `local_teamwork_members` to comply with Moodle Frankenstyle naming conventions.
- Install script handles legacy table names to support migration from the pre-Frankenstyle version.

---

## [2019000000] — 2019 (initial release)

### Added
- Initial release.
- Team card panel injected into `mod-assign-view` and `mod-quiz-view` page types.
- Drag-and-drop student assignment to named team cards.
- Random team generation with configurable students-per-team.
- Student self-enrolment mode with optional team-count limit and time-window deadline.
- Per-activity enable/disable toggle.
- Course-group filtering in the team panel.
- Web services AJAX API for all panel interactions.
- Capabilities: `local/teamwork:manageteams`, `local/teamwork:submitinteams`.
- Rubric PDF export button on assignment grading panel (requires core patch).
- Multilingual support: English, Hebrew, Hebrew kids, Russian.
