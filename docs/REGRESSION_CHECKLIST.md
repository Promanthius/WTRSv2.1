# Core Regression Checklist

Use this checklist after schema or workflow changes.

## 1) Register student/adviser

- [ ] Register a student account using valid `@wmsu.edu.ph` email.
- [ ] Register an adviser account using valid `@wmsu.edu.ph` email.
- [ ] Verify non-`@wmsu.edu.ph` email is rejected.
- [ ] Verify both accounts can log in and land on the correct dashboards.

## 2) Student submit flow

- [ ] Student can submit with required fields: title + PDF only.
- [ ] Student must choose assigned professor before upload.
- [ ] Upload creates a thesis request notification for selected adviser.
- [ ] Submission works without entering abstract.
- [ ] Upload rejects non-PDF files and files over max size.
- [ ] New submission appears in student tracker/history.

## 3) Adviser review flow

- [ ] Adviser sees submitted thesis in queue.
- [ ] Adviser can claim unassigned thesis explicitly (if claim flow enabled).
- [ ] Adviser can mark thesis as accepted.
- [ ] Adviser can request revision with feedback.
- [ ] Adviser can reject with feedback.

## 4) Publish after hardbound

- [ ] Accepted thesis does not appear in public archive yet.
- [ ] Adviser marks hardbound received/publish.
- [ ] Thesis status becomes `archived`.
- [ ] Thesis appears in `public/archive.php`.
- [ ] Thesis detail loads in `public/thesis.php` by code.

## 5) Student edit/remove restrictions

- [ ] Student can edit allowed metadata fields only.
- [ ] Student cannot edit/remove thesis after publish (`archived`) if restricted by policy.
- [ ] Remove action deletes thesis versions and files as expected.
- [ ] Unauthorized user cannot edit/remove another student's thesis.
