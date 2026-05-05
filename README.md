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

## Sample Credentials (Filipino Personas)

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
| Bamboo Mañalac | `bamboo.manalac@wmsu.edu.ph` | Student | Engineering |
| Sarah Geronimo | `sarah.geronimo@wmsu.edu.ph` | Student | Computing Studies |
| Vic Sotto | `vic.sotto@wmsu.edu.ph` | Student | Business Admin |
| Jose Manalo | `jose.manalo@wmsu.edu.ph` | Student | Computing Studies |

### Admin
- **Email**: `admin@wmsu.edu.ph` | **Password**: `password123`

---
*WMSU Thesis Repository System (WTRS) - Advanced Agentic Coding v2.2*

## Notes

- `database/wtrs_schema.sql` defines only `student` and `adviser` roles.
- `theses.abstract` is nullable (field removed from required submission flow).
- Invite-only adviser onboarding is retired.
