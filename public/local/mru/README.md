# MRU Integration Plugin (local_mru)

Moodle plugin for integrating with the **Mutesa I Royal University (MRU)** core management system.

## Purpose

This plugin bridges the MRU ODEL (Open and Distance E-Learning) Moodle platform with the university's core academic management system. It provides:

- **Student Verification** — Verify student registration status against the core system
- **Marks Synchronisation** — Sync grades from Moodle gradebook to the core academic system
- **Course Mapping** — Map Moodle courses to MRU course codes and programmes
- **User Mapping** — Link Moodle accounts to MRU student/staff numbers
- **Automated Sync** — Scheduled tasks for hands-free data synchronisation
- **Admin Dashboard** — Real-time overview of integration status

## Requirements

- Moodle 5.1+ (2025100600)
- PHP 8.1+
- MySQL/MariaDB
- Access to MRU core system (API or database)
- Theme: `mru_odel` (recommended, designed to work together)

## Installation

1. Copy the `mru` folder to `local/mru/` within your Moodle installation
2. Visit **Site administration > Notifications** to trigger the database install
3. Configure the plugin at **Site administration > Plugins > Local plugins > MRU Integration**

## Configuration

### Core System API

If the MRU core system exposes a REST API:

| Setting | Description |
|---------|-------------|
| API base URL | e.g. `https://core.mru.ac.ug/api/v1` |
| API key | Provided by the core system admin |
| API secret | Provided by the core system admin |
| Request timeout | Default: 30 seconds |

### MRU Database (Direct)

For direct database imports from the `mru_main` database:

| Setting | Description |
|---------|-------------|
| Database host | Default: `localhost` |
| Database port | Default: `3306` |
| Database name | Default: `mru_main` |
| Database user | MySQL username |
| Database password | MySQL password |
| Database socket | Optional, for MAMP/socket connections |

### Synchronisation

| Setting | Description |
|---------|-------------|
| Enable sync | Master toggle for all sync operations |
| Sync frequency | Hourly, Daily, or Weekly |
| Sync students | Import/update student records |
| Sync marks | Sync grades to core system |
| Sync courses | Auto-map courses by idnumber |

### Student Verification

| Setting | Description |
|---------|-------------|
| Verify on login | Re-verify on each login |
| Verify on enrolment | Verify when enrolled in a course |

## Architecture

```
local/mru/
├── classes/
│   ├── api_client.php          # HTTP client for core system REST API
│   ├── db_manager.php          # Direct database connection to mru_main
│   ├── student_manager.php     # Student verification & import
│   ├── marks_manager.php       # Marks/grades synchronisation
│   ├── course_manager.php      # Course mapping & sync
│   ├── sync_manager.php        # Orchestrator for all sync operations
│   ├── external/
│   │   └── api.php             # Moodle web service functions (AJAX)
│   ├── event/
│   │   └── observer.php        # Event handlers (login, enrol, grading)
│   ├── task/
│   │   ├── sync_all.php        # Scheduled full sync task
│   │   └── verify_students.php # Scheduled bulk verification task
│   └── privacy/
│       └── provider.php        # GDPR privacy provider
├── cli/
│   └── sync.php                # CLI sync tool
├── db/
│   ├── access.php              # Capabilities
│   ├── events.php              # Event observer definitions
│   ├── install.xml             # Database schema (4 tables)
│   ├── services.php            # Web service definitions
│   └── tasks.php               # Scheduled task definitions
├── lang/en/
│   └── local_mru.php           # English language strings
├── templates/
│   ├── dashboard.mustache      # Admin dashboard
│   ├── course_sync.mustache    # Course marks sync page
│   ├── status.mustache         # Student status page
│   └── verification_result.mustache  # AJAX verification fragment
├── index.php                   # Admin dashboard page
├── course_sync.php             # Course sync page
├── status.php                  # Student status page
├── lib.php                     # Navigation & callbacks
├── settings.php                # Admin settings
└── version.php                 # Plugin version
```

## Database Tables

| Table | Purpose |
|-------|---------|
| `local_mru_user_map` | Maps Moodle users ↔ MRU student/staff IDs |
| `local_mru_course_map` | Maps Moodle courses ↔ MRU course codes |
| `local_mru_marks_sync` | Tracks individual mark sync records |
| `local_mru_sync_log` | Audit log of all sync operations |

## Web Services

| Function | Description |
|----------|-------------|
| `local_mru_verify_student` | Verify a student (AJAX) |
| `local_mru_sync_course_marks` | Sync marks for a course (AJAX) |
| `local_mru_get_status` | Get integration status (AJAX) |

## CLI Usage

```bash
# Full sync (courses + students + marks)
php local/mru/cli/sync.php --type=all

# Import students only
php local/mru/cli/sync.php --type=students

# Sync marks only
php local/mru/cli/sync.php --type=marks

# Auto-map courses
php local/mru/cli/sync.php --type=courses

# Bulk verify unverified students
php local/mru/cli/sync.php --type=verify
```

## Capabilities

| Capability | Description | Default roles |
|------------|-------------|---------------|
| `local/mru:manage` | Manage integration settings | Manager |
| `local/mru:viewreports` | View sync reports | Manager, Editing teacher |
| `local/mru:syncmarks` | Sync marks for courses | Manager, Editing teacher |
| `local/mru:verifystudents` | Verify student records | Manager, Editing teacher |
| `local/mru:viewownstatus` | View own MRU status | All authenticated |

## Integration with mru_odel Theme

This plugin is designed to work with the `mru_odel` theme:

- Navigation items integrate with Boost-based menus
- Templates use Bootstrap 4 classes matching the theme
- Status badges use the MRU colour scheme
- Dashboard cards complement the theme design

## Troubleshooting

| Issue | Solution |
|-------|----------|
| "API not configured" | Set API base URL, key, and secret in settings |
| "Database connection failed" | Check db_host, db_user, db_pass, db_socket settings |
| Students not syncing | Ensure `sync_students` is enabled and sync is turned on |
| Marks not appearing | Verify course is mapped (check `local_mru_course_map`) |
| Scheduled tasks not running | Check Moodle cron is running; tasks start disabled |

## Licence

This plugin is licensed under the [GNU GPL v3](http://www.gnu.org/copyleft/gpl.html).

Copyright © 2026 Mutesa I Royal University.
