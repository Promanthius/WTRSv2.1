# WMSU Thesis Repository System (WTRS)

## Current flow (source of truth)

- Public landing page is `public/archive.php` (published theses only).
- Users self-register as `student` or `adviser` using `@wmsu.edu.ph` email.
- Students submit thesis title + PDF.
- Advisers review submissions: accept / revise / reject.
- Thesis becomes publicly visible only after hardbound publish (`status = 'archived'`).

## Project organization

- `index.php` - root entry (redirects to public archive)
- `public/` - archive and thesis public pages
- `auth/` - login/register/logout
- `student/` - student dashboard, upload, tracker
- `faculty/` - adviser dashboard and review queue
- `admin/` - retained adviser-only maintenance endpoints (legacy folder name, no admin role)
- `includes/` - shared backend utilities
- `assets/` - local CSS, JS, images, icons, fonts
- `database/wtrs_schema.sql` - canonical schema for fresh setup

## Run locally (XAMPP)

1. Start Apache and MySQL in XAMPP.
2. Import schema: `mysql -u root < database/wtrs_schema.sql`.
3. Open `http://localhost/wtrs/`.
4. Register a new student and adviser account for testing.

## Notes

- `database/wtrs_schema.sql` defines only `student` and `adviser` roles.
- `theses.abstract` is nullable (field removed from required submission flow).
- Invite-only adviser onboarding is retired.
