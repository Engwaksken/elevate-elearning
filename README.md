# elevate-elearning

PHP / MySQL eLearning platform for Elevate Her 360: courses, intakes, assignments,
quizzes, chatrooms, payments and certificates.

## Requirements

- PHP 8.1+ with `mysqli`, `mbstring`, `gd`
- MySQL / MariaDB
- Composer (Dompdf is used for certificate PDFs)

## Setup

1. Install PHP dependencies:

   ```bash
   composer install
   ```

2. Configure the database. Copy the example config and fill in real values:

   ```bash
   cp scripts/config.example.php scripts/config.local.php
   ```

   `scripts/config.local.php` is git-ignored. Alternatively set the environment
   variables `EH_DB_HOST`, `EH_DB_USER`, `EH_DB_PASS` and `EH_DB_NAME`.

3. SMTP settings are stored in the database (email settings table), and SSO /
   unified-auth secrets come from environment variables (`EH_SSO_SECRET`, etc.).
   Never commit real secrets.

## Not in this repository

Uploaded and generated content stays on the server only and is git-ignored:
`assignments/`, `certificates/`, `notes/`, `online_class/`, `course_materials/`,
`uploads/` and `images/user_p_pics/`.

## Security note

Asset folders (`css/`, `js/`, `img/`, `images/`, `fonts/`, `font-awesome/`) must
never contain `.php` files. The production server previously held injected PHP
files in those folders. They are excluded from this repository and should be
removed from the server.
