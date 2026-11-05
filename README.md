# elevate-elearning

PHP / MySQL eLearning platform for Elevate Her 360: courses, intakes, assignments,
quizzes, chatrooms, payments and certificates.

## Requirements

- PHP 8.1+ with `mysqli`, `mbstring`, `gd`, `dom`, `fileinfo`
- MySQL / MariaDB

PHP libraries (Dompdf for certificate PDFs) are already in `vendor/`, so no
`composer install` is needed on the server.

## Setup

1. Clone or upload the project, **including `vendor/`**.

2. Create the settings file from the template and fill in real values:

   ```bash
   cp .env.example .env
   ```

   - **Recommended:** put `.env` one folder **above** the project (outside the
     public web folder), e.g. `/home/<user>/.env` when the project is
     `/home/<user>/public_html`. The app looks there first, then in the
     project folder.
   - If it must live in the project folder, the root `.htaccess` blocks it
     from being downloaded. Keep that file.
   - `.env` holds the database, global SMTP mail, site URL, SSO and upload
     compression settings. See the comments in `.env.example`.
   - When `EH_MAIL_HOST` is set, every system email uses those SMTP settings;
     when it is empty, the `email_settings` database table is used instead.
   - Older installs may still use `scripts/config.local.php` for the database;
     `.env` values take precedence over it.

3. Never commit `.env` (it is git-ignored).

## Updating libraries

On your own computer (not the server):

```bash
composer update --no-dev --prefer-dist
```

Then commit `vendor/` together with `composer.lock`.

## Maintenance scripts (run from the project folder, via SSH or a cPanel cron job)

```bash
php scripts/cli/compress_existing_uploads.php            # dry run, then add --apply
php scripts/cli/find_orphan_uploads.php                  # report, then add --quarantine
```

## Not in this repository

Uploaded and generated content stays on the server only and is git-ignored:
`assignments/`, `certificates/`, `notes/`, `online_class/`, `course_materials/`,
`uploads/` and `images/user_p_pics/`.

## Security note

Asset folders (`css/`, `js/`, `img/`, `images/`, `fonts/`, `font-awesome/`) must
never contain `.php` files. The production server previously held injected PHP
files in those folders. They are excluded from this repository and should be
removed from the server.
