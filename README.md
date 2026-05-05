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

## Run Locally (XAMPP)

Follow these steps to set up the system on your local machine:

1.  **Clone/Copy Files**: Place the project folder inside your `C:\xampp\htdocs\` directory.
2.  **Start Services**: Open the XAMPP Control Panel and start **Apache** and **MySQL**.
3.  **Database Setup**:
    - Open [http://localhost/phpmyadmin/](http://localhost/phpmyadmin/) in your browser.
    - Create a new database named `wtrs`.
    - Select the `wtrs` database, go to the **Import** tab, and select the `database.sql` file from the project root.
4.  **Configuration**: 
    - Ensure `includes/config.php` has the correct database credentials (default is `root` with no password).
5.  **Access**: Open [http://localhost/wtrs/](http://localhost/wtrs/) in your browser.

## Sample Credentials

The system comes pre-seeded with 20 sample accounts (10 Student, 10 Faculty) for testing.  
**Note:** All sample accounts use the password: `password123`

### Faculty (Advisers)
| Name | Email | Role | College |
| :--- | :--- | :--- | :--- |
| Juan Dela Cruz | `juan.delacruz@wmsu.edu.ph` | Adviser | Computing Studies |
| Maria Santos | `maria.santos@wmsu.edu.ph` | Adviser | Computing Studies |
| Jose Rizal | `jose.rizal@wmsu.edu.ph` | Adviser | Liberal Arts |
| Corazon Aquino | `corazon.aquino@wmsu.edu.ph` | Adviser | Science & Math |
| Ferdinand Marcos | `ferdinand.marcos@wmsu.edu.ph` | Adviser | Law |
| Andres Bonifacio | `andres.bonifacio@wmsu.edu.ph` | Adviser | Engineering |
| Melchora Aquino | `melchora.aquino@wmsu.edu.ph` | Adviser | Nursing |
| Emilio Aguinaldo | `emilio.aguinaldo@wmsu.edu.ph` | Adviser | Agriculture |
| Apolinario Mabini | `apolinario.mabini@wmsu.edu.ph` | Adviser | Education |
| Gabriela Silang | `gabriela.silang@wmsu.edu.ph` | Adviser | Computing Studies |

### Students
| Name | Email | Role | College |
| :--- | :--- | :--- | :--- |
| Student One | `student1@wmsu.edu.ph` | Student | Computing Studies |
| Rico Blanco | `rico.blanco@wmsu.edu.ph` | Student | Computing Studies |
| Lea Salonga | `lea.salonga@wmsu.edu.ph` | Student | Liberal Arts |
| Manny Pacquiao | `manny.pacquiao@wmsu.edu.ph` | Student | Education |
| Catriona Gray | `catriona.gray@wmsu.edu.ph` | Student | Science & Math |
| Pia Wurtzbach | `pia.wurtzbach@wmsu.edu.ph` | Student | Nursing |
| Bamboo Manalac | `bamboo.manalac@wmsu.edu.ph` | Student | Engineering |
| Sarah Geronimo | `sarah.geronimo@wmsu.edu.ph` | Student | Computing Studies |
| Vic Sotto | `vic.sotto@wmsu.edu.ph` | Student | Computing Studies |
| Jose Manalo | `jose.manalo@wmsu.edu.ph` | Student | Computing Studies |

### Admin
- **Email**: `admin@wmsu.edu.ph` | **Password**: `password123`

---
*WMSU Thesis Repository System (WTRS) - Advanced Agentic Coding v2.2*

## Notes

- `database/wtrs_schema.sql` defines only `student` and `adviser` roles.
- `theses.abstract` is nullable (field removed from required submission flow).
- Invite-only adviser onboarding is retired.

## User Workflows & Tutorials

### 🎓 For Students

#### 1. Registration & Profile
- Register using your `@wmsu.edu.ph` email.
- Complete your academic profile (Bio, Research Interests, Track Record) to help advisers evaluate your requests.

#### 2. Selecting an Adviser
- Go to the **Mentorship Program** page.
- Browse available faculty members in your college.
- Click **Request Advising** to send a formal request. 
- *Note: You can only have one adviser at a time. Once accepted, the option to request others will be locked.*

#### 3. Submitting Research
- Use the **Submit New Thesis** button on your dashboard.
- Upload your initial manuscript (PDF).
- Track the progress in the **Review Lifecycle** timeline on your dashboard.

#### 4. Iteration & Revisions
- If an adviser requests revisions, you will see a "Refinement Required" alert.
- Click **Proceed to Resubmit** to upload the updated version.
- Review your adviser's feedback notes directly on your dashboard.

#### 5. Changing Advisers (Release Process)
- If you need to change advisers, you must first be released.
- Click the **RELEASE** button on your dashboard adviser card.
- Provide a reason for the request and wait for approval.
- Once released, you can apply for a new adviser.

---

### 👨‍🏫 For Faculty (Advisers)

#### 1. Managing Requests
- New student requests appear in your **Advisee Requests** queue.
- Click **View Student Profile** to see their bio, research interests, and previous advisers.
- Accept or Decline based on your current capacity and the student's alignment.

#### 2. Manuscript Review
- View active submissions in your **Manuscript Queue**.
- Use the **Review Workspace** to inspect PDF versions.
- Provide feedback and choose to **Approve** or **Request Revision**.

#### 3. Managing Advisees
- View your active students in the **Research Authors** registry.
- You can "Renounce" or "Remove" a student if the mentorship is complete or no longer viable.
- Respond to **Release Requests** from students in the requests section.

