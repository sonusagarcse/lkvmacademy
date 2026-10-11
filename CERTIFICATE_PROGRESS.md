# LKVM LMS - Certificate System Architecture & Progress Snapshot

**Last Updated:** October 10, 2026  
**Environment:** Apache 2.4, PHP 8.2, MySQL on Windows (XAMPP: `c:\xampp\htdocs\lkvm\courses\`)  
**Status:** All core modules implemented, verified, and operational with 0 syntax errors.

---

## 1. System Overview

The LKVM LMS Certificate System is an enterprise-grade automated and manual certification suite. It features:
1. **Automated Issuance upon 100% Course Completion** (tamper-proof unique credential ID generation).
2. **Online Public Verification** with dynamic QR codes (`/certificate-verify?uid=...`).
3. **Visual Canvas Studio & Builder** (drag-and-drop layers, corner-handle resizing, custom background upload with auto aspect-ratio calculation).
4. **Built-in Luxury Templates Gallery** (5 pre-crafted high-DPI CSS/SVG diploma templates + 1 custom uploaded canvas template).
5. **Dynamic Student Data Replacement Tags Engine** (allows adding custom labels and paragraphs with `{student_name}`, `{roll_no}`, `{grade}`, etc.).
6. **Student Dashboard Classroom Hub** (clean tabbed navigation for certificates, PDF notes, orders, and profile).

---

## 2. Database Schema & Tables

### A. `lms_certificates`
| Column | Type | Description |
|---|---|---|
| `id` | `INT(11) AUTO_INCREMENT PRIMARY KEY` | Certificate Record ID |
| `certificate_uid` | `VARCHAR(64) UNIQUE` | Public verification ID (e.g., `LKVM-202610-3QKGA`) |
| `user_id` | `INT(11)` | Student ID in `lms_students` |
| `course_id` | `INT(11)` | Course ID in `lms_courses` |
| `student_name` | `VARCHAR(255)` | Student full name at time of issuance |
| `student_email` | `VARCHAR(255)` | Student email |
| `course_title` | `VARCHAR(255)` | Full course title |
| `instructor_name` | `VARCHAR(255)` | Course instructor / authority |
| `certificate_template` | `VARCHAR(50)` | Template key (`classic_gold`, `modern_blue`, `custom_bg`, etc.) |
| `issue_date` | `DATE` | Issuance date |
| `completion_percent` | `DECIMAL(5,2)` | Completion percentage (100.00) |
| `status` | `ENUM('active','revoked','expired')` | Status (shows red stamp if revoked) |
| `revoked_reason` | `TEXT` | Reason if certificate is revoked |
| `created_at` / `updated_at` | `TIMESTAMP` | Timestamps |

*Unique Key:* `(`user_id`, `course_id`)` ensures one certificate per course per student.

### B. `lms_courses` (Certificate Settings Columns)
- `has_certificate`: `TINYINT(1)` (1 = enabled, 0 = disabled)
- `certificate_title`: `VARCHAR(255)` (Custom title on certificate)
- `certificate_signatory_name`: `VARCHAR(255)` (Signatory name override)
- `certificate_signatory_title`: `VARCHAR(255)` (Designation, e.g. "Director & Head of Academics")
- `certificate_template`: `VARCHAR(50)` (Template style assigned to course)

### C. `lms_settings` Key-Value Store
- `cert_default_template`: System-wide default template key
- `cert_org_title`: Organization name ("LKVM ACADEMY")
- `cert_custom_bg`: Relative path to uploaded certificate border background
- `cert_logo_img`: Relative path to official logo
- `cert_signature_img`: Relative path to signatory signature PNG
- `cert_seal_img`: Relative path to official stamp / seal PNG
- `cert_canvas_config`: JSON string containing complete visual canvas studio configuration (layer coordinates, sizes, fonts, colors, custom dynamic fields).

---

## 3. Core Files & Architecture Map

| File Path | Role & Responsibilities |
|---|---|
| `courses/includes/certificate_helper.php` | Centralized helper functions: table migrations (`lms_ensure_certificates_table`), UID generator (`lms_generate_certificate_uid`), completion checker (`lms_check_course_completion`), template registry (`lms_get_certificate_templates`), auto-issue hook (`lms_auto_issue_certificate`). Idempotent functions wrapped in `if (!function_exists(...))`. |
| `courses/admin/certificate-templates.php` | **Admin Studio & Gallery**: Visual drag-and-drop workspace, corner-handle resizing, element inspector, custom text modal with tag badges, template gallery switcher, reset to default design, two-way sync with hidden form inputs. |
| `courses/certificate-view.php` | **Student Certificate Presentation & Print/Export Engine**: Renders high-DPI responsive certificates (both custom background canvas and 5 luxury CSS templates). Dynamic tag replacements, QR code display, print styling (`@media print`), and PNG/PDF export. Supports URL preview (`?template=...`). |
| `courses/certificate-verify.php` | **Public Credential Verification Portal**: Input LKVM UID or scan QR code to verify validity, student details, course title, issue date, and authenticity status. |
| `courses/admin/certificates.php` | **Admin Certificate Manager**: View all issued certificates, filter by course/student, revoke with reason, restore, re-issue, or download. |
| `courses/admin/courses.php` | Course editor with dedicated **Course Certificate Module** card (toggle certificate, custom signatory, choose template style). |
| `courses/dashboard.php` | Student Hub with sub-tabs for Certificates, PDF Study Material, Orders & Payment History, and Profile. |

---

## 4. The 6 Certificate Template Styles

1. **Classic Gold Luxury (`classic_gold`)**:
   - Royal ivy filigree border, gold crest, vintage serif typography, parchment background tint.
   - Fonts: *Playfair Display* + *Great Vibes* calligraphy.
2. **Modern Royal Blue (`modern_blue`)**:
   - Cobalt & cyan gradient technical border, contemporary geometric layout.
   - Fonts: *Plus Jakarta Sans* + *Playfair Display*.
3. **Emerald Prestige (`emerald_prestige`)**:
   - Executive dark emerald filigree border, platinum/emerald medal crest.
   - Fonts: *Playfair Display* + *Great Vibes*.
4. **Academic Crimson Crest (`academic_crimson`)**:
   - University-style heraldic crimson border, dual gold pinstripes, classic collegiate diploma.
   - Fonts: *Cinzel* Roman + *Playfair Display*.
5. **Minimalist Executive (`minimal_dark`)**:
   - Sharp charcoal dual borders, monochrome luxury layout.
   - Fonts: *Plus Jakarta Sans*.
6. **Custom Uploaded Canvas (`custom_bg`)**:
   - Free-form canvas over uploaded artwork/border. Elements are placed at exact `%` coordinates and auto-scaled.

---

## 5. Visual Canvas Studio Mechanics

### Sizing & Position Persistence
- **Coordinates:** Dragging layers updates `top: X%` and `left: Y%`, which writes to hidden form inputs (`pos_org_top`, `pos_org_left`, etc.).
- **Font Sizes & Scale:** Dragging corner handles, using `+`/`-` stepper buttons, or adjusting inspector slider calls `setLayerFontSize(layerId, size)`:
  - Immediately modifies DOM font-size / photo dimensions.
  - Updates matching hidden inputs (`cfg_org_size`, `cfg_student_size`, `cfg_photo_size`, etc.).
  - Saved to `cert_canvas_config` JSON on submit.
- **Auto Aspect-Ratio:** `getimagesize()` on uploaded background dynamically sets viewport aspect ratio (e.g. `1000 / 707` for standard A4 landscape).
- **Reset to Default:** `resetCanvasToDefaultDesign()` resets all coordinates, sizes, fonts, and colors to clean diploma defaults in 1 click.

---

## 6. Dynamic Student Data Tags Engine

Custom text layers and paragraphs support dynamic placeholders that are replaced during preview and rendering:

| Tag | Replaced With | Example |
|---|---|---|
| `{student_name}` | Student's full name | `SONU SAGAR` |
| `{roll_no}` | Student ID / Roll number | `LKVM-0001` |
| `{course_title}` | Full course title | `Full Stack Web Development` |
| `{grade}` | Performance grade | `A+ (Distinction)` |
| `{issue_date}` | Formatted issuance date | `10 October 2026` |
| `{cert_uid}` | Unique verification ID | `LKVM-202610-3QKGA` |
| `{student_email}` | Registered student email | `sonu@example.com` |
| `{phone}` | Registered mobile number | `+91 98765 43210` |
| `{duration}` | Course duration from course record | `120 Hours` |
| `{completion_percent}` | Completion % | `100%` |
| `{instructor_name}` | Course instructor / Signatory | `Dr. Sonu Sagar` |
| `{father_name}` | Father / Guardian name | `Mr. Guardian` |

### Tag Implementation
- In `certificate-templates.php`: Clickable chip badges in `#modalAddCustomText` and Element Inspector insert tags at cursor position, with live sample preview.
- In `certificate-view.php`: `format_custom_tag($text, $cert, $extra_data)` runs case-insensitive `str_ireplace` on all standard and custom layers.

---

## 7. Verification & URL Testing Reference

- Admin Certificate Studio: `http://localhost/lkvm/courses/admin/certificate-templates?tab=studio`
- Admin Templates Gallery: `http://localhost/lkvm/courses/admin/certificate-templates?tab=gallery`
- Student Certificate View: `http://localhost/lkvm/courses/certificate-view?uid=LKVM-202610-3QKGA`
- Template Preview Overrides: `http://localhost/lkvm/courses/certificate-view?uid=LKVM-202610-3QKGA&template=classic_gold` (also `modern_blue`, `emerald_prestige`, `academic_crimson`, `minimal_dark`, `custom_bg`)
- Public Verification Page: `http://localhost/lkvm/courses/certificate-verify?uid=LKVM-202610-3QKGA`
