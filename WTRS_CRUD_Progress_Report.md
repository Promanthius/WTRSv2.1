# WTRS CRUD Demonstration and System Progress Report

## Part 1: CRUD Operations Demonstration

### Create
- Page: `student/upload.php`
- Operation: Add a new thesis submission record.
- Data involved: thesis title, abstract, selected adviser, uploaded PDF file.
- Expected result: A new `theses` record is created in the database and a corresponding `thesis_versions` entry is saved with the uploaded PDF. The thesis becomes pending review.

> Screenshot guidance: capture the student upload form with the filled title, adviser selection, and selected PDF file before submitting.

### Read
- Pages: `student/index.php` and `public/archive.php`
- Operation: Display existing records.
- Data involved: student submission history or public archived theses.
- Expected result: The system lists records retrieved from the database, showing thesis metadata such as title, status, adviser, and publication details.

> Screenshot guidance: capture either the student dashboard submission list or the public archive search results showing multiple theses.

### Update
- Page: `faculty/review.php`
- Operation: Modify an existing thesis record by changing its review status.
- Data involved: thesis status (`approved`, `revision_requested`, `rejected`), reviewer feedback, and thesis version status.
- Expected result: The thesis record is updated in the `theses` table and the selected `thesis_versions` entry is updated with review feedback and status.

> Screenshot guidance: capture the adviser review interface with a decision selected and feedback entered.

### Delete
- Page: `student/index.php` (student dashboard submission list)
- Operation: Remove an existing thesis submission.
- Data involved: thesis record row in `theses` and associated files in `public/uploads/`.
- Expected result: The selected thesis entry is deleted from the database and the uploaded PDF versions are removed from disk. Locked records (approved or archived) cannot be deleted.

> Screenshot guidance: capture the delete button or confirmation prompt on a deletable submission.

## Part 2: System Progress Update

### System Overview
- Purpose: This is a university thesis repository system for managing student thesis submissions, adviser review workflows, and published thesis archives.
- Target users: students, faculty advisers, and internal administrators/maintainers.

### Completed Features
- User registration and login for students and advisers via `auth/`.
- Student thesis upload with PDF file validation in `student/upload.php`.
- Adviser review workflow and decision processing in `faculty/review.php`.
- Public thesis archive browsing in `public/archive.php`.
- Student dashboard and submission tracker in `student/index.php`.
- Profile update for users in `user/profile.php`.
- Notification support for thesis actions in `user/notifications.php`.
- Delete capability for student submissions that are not yet approved or archived.
- Admin maintenance pages for user activation and system statistics.

### Partially Completed Features
- Hardbound publish workflow: thesis status moves through review and archive states, but full editorial/hardbound tracking may still require final verification.
- Adviser invitation or onboarding flow appears retired/legacy; the system currently supports self-registration with role-specific dashboards.
- Some admin role semantics are legacy labeled as `admin/` but operate as adviser/maintenance pages rather than a separate admin role.

### Pending Features
- Full invitation-only adviser onboarding is not in active use and may still need implementation or cleanup.
- Additional access control polish for admin/adviser role separation.
- Final user-facing documentation export or report generation inside the system.

### Challenges Encountered
- Ensuring uploaded thesis files are stored securely and deleted safely when a submission is removed.
- Preserving status rules so approved or archived theses cannot be removed by students.
- Supporting multiple thesis versions while keeping adviser feedback and review status consistent.
- Maintaining a public archive view that only shows published (`archived`) theses.

### Next Steps
- Capture the required screenshots from the live system using the pages listed above.
- Export this markdown into a PDF or Word document with headings, labels, and image captions.
- Add a brief explanation below each screenshot describing the operation, data, and expected result.
- Optionally, refine the adviser and admin workflows to improve role-based navigation and clarify reviewer state transitions.

## Screenshot Checklist
- [ ] Create screenshot for `student/upload.php`
- [ ] Read screenshot for `student/index.php` or `public/archive.php`
- [ ] Update screenshot for `faculty/review.php`
- [ ] Delete screenshot for `student/index.php`

---

*File created for the activity deliverable.*
