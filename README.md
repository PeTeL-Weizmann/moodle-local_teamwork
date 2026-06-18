# moodle-local_teamwork

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](http://www.gnu.org/copyleft/gpl.html)

A Moodle local plugin that lets teachers (and optionally students) organise course participants into named teams directly on the assignment or quiz view page, with drag-and-drop cards, random-team generation, and optional voice-command control.

## Use cases

### Interactive team formation during class
Teachers open the Team Work panel on any assignment or quiz activity and drag students into team cards in real time — no need to leave Moodle or use a spreadsheet. Teams are instantly visible to students.

### Student self-enrolment into teams
With the *Choice by students* feature enabled, teachers can allow students to form their own teams within a configurable time window, with limits on total teams and team size.

### Group-aware quiz and assignment submission
When Team Work is active on an assignment, the submission workflow is extended to show which team a student belongs to. For quizzes it integrates with the quiz summary view so teachers can see team context alongside individual responses.

### Voice-controlled classroom management
A built-in voice interface (configurable per language) lets teachers control Team Work hands-free — create a team, move a student, delete a card, or have the list read aloud — using customisable trigger phrases.

## GDPR

This plugin stores the following personal data:

| Data | Table | Purpose |
|------|-------|---------|
| User ID (team membership) | `local_teamwork_members` | Links enrolled students to named team cards |
| User ID (creator) | `local_teamwork` | Records who created the teamwork session |

No data is shared with external services. Implement `classes/privacy/provider.php` before deploying to a GDPR-regulated environment.

## Branches

| Moodle version | Branch | PHP |
|----------------|--------|-----|
| Moodle 4.4+ (PeTeL m45) | `petel_m45` | 8.3+ |
| Moodle 3.9–4.3 | `MOODLE_39_STABLE` | 7.4+ |
| Moodle 3.3–3.8 | `main` | 7.1+ |

> **Note:** This plugin is maintained as part of the Weizmann Institute PeTeL platform. The canonical branch for active development is `petel_m45`.

## Required plugins

This plugin will not function correctly without the following companion plugins. Both must be installed **before** running the Moodle upgrade.

### `assignsubmission_teamwork` *(mandatory)*

- **Path:** `mod/assign/submission/teamwork/`
- **Role:** Assignment submission sub-plugin that registers the Team Work panel inside the assignment grading workflow. `local_teamwork` checks `assign_plugin_config` for records created by this sub-plugin to decide whether to activate on an assignment page. The two plugins are mutually dependent — `assignsubmission_teamwork/version.php` declares `local_teamwork: ANY_VERSION` as a formal Moodle dependency.
- **Source:** Distributed as part of the PeTeL plugin set. https://github.com/PeTeL-Weizmann/moodle-assignsubmission_teamwork

### `local_quiz_summary_option` *(mandatory for quiz support)*

- **Path:** `local/quiz_summary_option/`
- **Role:** Provides `\local_quiz_summary_option\funcs::get_quiz_config()`, which `local_teamwork` calls directly to check whether Team Work is enabled for a given quiz activity. A missing plugin will cause a **fatal PHP error** on any quiz view page.
- **Source:** Developed by [Catalyst IT](https://www.catalyst-eu.net); customized and available in the PeTeL plugin set. https://github.com/PeTeL-Weizmann/moodle-local_quiz_summary_option

## Installation

1. **Install companion plugins first** — copy or clone `assignsubmission_teamwork` into `mod/assign/submission/teamwork/` and `local_quiz_summary_option` into `local/quiz_summary_option/`.

2. **Clone or copy** this plugin into your Moodle directory:
   ```bash
   git clone https://github.com/PeTeL-Weizmann/moodle-local_teamwork.git local/teamwork
   ```
   or copy the folder to `{moodle_root}/local/teamwork/`.

3. **Apply the core patch** (see [Applying core patches](#applying-core-patches) below).

4. **Run the Moodle upgrade**:
   ```bash
   php admin/cli/upgrade.php
   ```
   or visit **Site administration → Notifications** in a browser.

5. The plugin creates three database tables:
   - `local_teamwork` — one record per activity that has Team Work enabled
   - `local_teamwork_groups` — team cards (named groups inside a teamwork session)
   - `local_teamwork_members` — student→team-card membership

## Team Work configuration

Navigate to **Site administration → Local plugins → Team Work**.

### Voice control

| Setting | Description | Default |
|---------|-------------|---------|
| **Enable voice control** | Master switch to activate the voice interface | Off |
| **"OK" tokens** | Wake-word phrases that activate the voice listener (one per line, per language) | `ok`, `ok petel`, `hello computer`, … |

### Voice command schemes

Each scheme defines the spoken phrase patterns that trigger a team management action. All schemes are configurable per language (English, Hebrew, Hebrew kids, Russian).

| Scheme | Default phrase (English) | Action |
|--------|--------------------------|--------|
| Add a team | `add a team` / `add new team` | Creates a new unnamed team card |
| Create named team | `please create a team {_teamname}` | Creates a card with a specific name |
| Create N teams | `create {_number} teams` | Batch-creates N new cards |
| Move student | `move {userfullname} to {teamname} card` | Drags a student to a target card |
| Delete team | `delete {teamname}` | Removes a team card |
| Read users | `read user lists` | Reads the student list aloud |
| Read teams | `read teams` | Reads existing teams aloud |

## Teacher usage

1. Open any **Assignment** or **Quiz** activity page.
2. The **Team Work** panel is injected automatically when the plugin is active for that activity.
3. Use the toggle to **enable/disable** Team Work for the activity.
4. Manage teams via the panel:
   - **Add card** — creates a new team slot.
   - **Drag and drop** — move student chips between cards.
   - **Random groups** — auto-distribute students evenly.
   - **Student settings** — configure whether students can self-manage teams, maximum number of teams, maximum students per team, and an optional end date.
5. The group selector at the top of the panel filters by Moodle course group.

## Student settings

Accessible to teachers via the *Student Settings* button in the Team Work panel:

| Setting | Description |
|---------|-------------|
| **Choice by students** | Allows students to create or join teams themselves |
| **Numbers of teams** | Maximum number of team cards students may create (0 = unlimited) |
| **Students in each team** | Suggested maximum students per card |
| **Last date of use** | Optional deadline after which student editing is disabled |

## Applying core patches

The rubric PDF export feature requires a one-line addition to Moodle core.

### `mod/assign/amd/src/grading_panel.js` (line ~302)

Add the following inside the grading panel init section:

```javascript
RubricExport.init(userid, function(){
    var url = $(location).attr('href');
    $(location).attr('href', url);
});
```

> Rebuild AMD after applying: `npx grunt amd --component=mod_assign`

## Web services (AJAX API)

All UI interactions use Moodle's AJAX web-service layer. The following functions are registered:

| Function | Description |
|----------|-------------|
| `local_teamwork_render_block_html_page` | Render the Team Work panel for an activity |
| `local_teamwork_render_teamwork_html` | Render the full Team Work popup |
| `local_teamwork_set_teamwork_enable` | Toggle Team Work on/off for an activity |
| `local_teamwork_set_access_to_student` | Save student-editing settings |
| `local_teamwork_add_new_card` | Create a new team card |
| `local_teamwork_delete_card` | Delete a team card |
| `local_teamwork_show_random_popup` | Show the random-groups configuration dialog |
| `local_teamwork_set_random_teams` | Generate random teams |
| `local_teamwork_set_new_team_name` | Rename a team card |
| `local_teamwork_drag_student_card` | Move a student between cards |
| `local_teamwork_render_teams_card` | Re-render team cards |
| `local_teamwork_render_student_list` | Re-render the unassigned student list |
| `local_teamwork_save_rubrics_pdf` | Export rubric as PDF |
| `local_teamwork_delete_user_submit` | Remove a student submission from a card |
| `local_teamwork_check_user_submit` | Check whether a student has a submission |
| `local_teamwork_render_student_settings_popup` | Render the student settings popup |
| `local_teamwork_student_settings_popup_data` | Save student settings |

## Capabilities

| Capability | Default roles | Description |
|------------|---------------|-------------|
| `local/teamwork:manageteams` | teacher, editingteacher, coursecreator, manager | View and manage team assignments for an activity |
| `local/teamwork:submitinteams` | student | Submit work as part of a team (reserved for future use) |

## Integration testing

```bash
# Run PHPUnit tests (from Moodle root)
vendor/bin/phpunit --testsuite local_teamwork_testsuite

# Run code checker
php local/codechecker/cli/checker.php --path=local/teamwork
```

> No automated browser (Behat) tests are currently included.

## Contributing and support

Pull requests are welcome. Please follow the [Moodle coding style](https://moodledev.io/general/development/policies/codingstyle) and include PHPUnit tests for any new functionality.

For commercial support or custom development, contact [Devlion](mailto:info@devlion.co).

## Warm thanks

- **Weizmann Institute of Science PeTeL team** — ongoing maintenance and feature additions
- Weizmann institute was supported by external software development company **[Devlion](https://devlion.co)** for the majority of the development work.

## Maintained by Weizmann Institute — PeTeL

[PeTeL](https://petel.weizmann.ac.il) (Platform for E-Teaching & E-Learning) is the Weizmann Institute of Science's custom Moodle platform, serving thousands of students across Israel with interactive science education.

## License

This software is licensed under the [GNU GPL v3 or later](http://www.gnu.org/copyleft/gpl.html).
