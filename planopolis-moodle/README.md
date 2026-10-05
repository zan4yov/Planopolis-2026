# Planopolis PWK 2026 – Quiz Platform on Moodle

This kit turns a standard Moodle 5.2 site into the quiz platform described in the Planopolis PWK 2026 request. It contains a Moodle plugin (`local_planopolis`), a one-command Docker deployment, and Excel templates for questions and participants.

| Folder / file | What it is |
|---|---|
| `docker-compose.yml`, `.env.example`, `docker/` | Deployment: Moodle 5.2 + PostgreSQL 16 + cron, installs and configures itself on first start |
| `plugin/local_planopolis/` | Source code of the Planopolis plugin |
| `local_planopolis_v1.0.0.zip` | The same plugin, ready for Moodle's *Install plugins* page (for an existing Moodle site) |
| `templates/` | `Template_Soal_Planopolis.xlsx` (questions) and `Template_Peserta_Planopolis.xlsx` (participants) |

## How the request is covered

| Request | Where it is handled |
|---|---|
| **Peserta** logs in with credentials registered by the admin | Self-registration is disabled; admins create accounts in *Planopolis panel → Register participants* |
| **Peserta** takes the quiz | Standard Moodle quiz, 1 attempt, timer, shuffled questions and options |
| **Admin** registers participants | Upload the participant Excel/CSV; passwords are generated when left empty; the username/password list downloads as Excel |
| **Admin** inputs questions via the web | Moodle's question editor (text, images and audio are supported in the editor) |
| **Admin** inputs questions via Excel | *Import questions*: Excel file, or a ZIP with the Excel file plus images/audio; validated preview before anything is saved |
| **Admin** gets participant scores in Excel | *Results*: ranking with score, correct / wrong / blank, duration and "left page" count; *Download Excel* |
| **Super Admin** has all admin features and grants admin access | The Moodle site administrator; *Planopolis panel → Manage admins* gives or removes the **Admin Quiz** role |
| Quiz page cannot be screenshotted / copied | Protection layer on quiz pages (see [Screenshot and copy protection](#screenshot-and-copy-protection)) |
| Multiple choice only; many scoring variations set by admin | Single-answer multiple choice; per-question *Nilai Benar* and *Nilai Salah* (e.g. +4 / −1), different weights per question |
| Question input with text, image and audio | Supported in the Excel import (ZIP) and in the web editor |
| 2024: some questions did not appear | *Quiz check* finds missing questions, questions left in Draft, and random-question pools that are too small; imports always add fixed questions |
| 2024: duplicate answers (A. Ayam, B. Ayam) | Import rejects duplicate options (ignoring capitals, spaces and full stops); *Quiz check* finds them in questions typed in the web editor too |

## 1. Deploy with Docker (recommended)

You need a Linux server with Docker and Docker Compose. As a rough guide, 2 vCPU / 4 GB RAM handles about 150–200 participants taking the quiz at the same moment; use 4 vCPU / 8 GB for 300–500. Run a test with your real number of participants before the event (see [Exam day](#exam-day)).

1. Copy this folder to the server.
2. `cp .env.example .env` and edit `.env`:
   - `MOODLE_URL` – the address participants will open, e.g. `https://quiz.planopolis.id` (no trailing slash).
   - `DB_PASS`, `ADMIN_PASS` – long passwords. `ADMIN_PASS` must contain an upper-case letter, a digit and a symbol. Avoid the `$` character in `.env` values.
   - `QUIZ_TIMELIMIT` – minutes for the first quiz (can be changed later).
3. `docker compose up -d --build`
4. Follow the first start with `docker compose logs -f moodle`. It waits for the database, installs Moodle, and runs the Planopolis setup (roles, course, quiz, security settings). When Apache starts, open `MOODLE_URL` and log in with `ADMIN_USER` / `ADMIN_PASS`. That account is the **Super Admin**.

Restarting or rebuilding is safe: on later starts the container runs Moodle's upgrade (which does nothing when nothing changed), and finishes the Planopolis setup if an earlier first start was interrupted.

**HTTPS.** Put Nginx, Caddy, Cloudflare or your hosting's load balancer in front of port `HTTP_PORT`, and set `MOODLE_SSLPROXY=true` and `MOODLE_REVERSEPROXY=true` in `.env`, then `docker compose up -d` again. Use `https://` in `MOODLE_URL`.

**Backups.** Everything lives in two Docker volumes: the database (`pgdata`) and uploaded files (`moodledata`). Before and after the event:

```bash
docker compose exec db pg_dump -U moodle moodle > planopolis_db_$(date +%F).sql
docker run --rm -v planopolis-moodle_moodledata:/data -v "$PWD":/backup alpine tar czf /backup/moodledata_$(date +%F).tgz -C /data .
```

(The volume name starts with the folder name; check it with `docker volume ls`.)

## 2. Or: install on an existing Moodle 5.0+ site

1. *Site administration → Plugins → Install plugins*, upload `local_planopolis_v1.0.0.zip`, and finish the upgrade.
2. On the server, from the Moodle folder: `php public/local/planopolis/cli/setup.php` (Moodle 5.0 uses `php local/planopolis/cli/setup.php`). Options: `--timelimit=120`, `--lang=id`, `--coursename=...`. The script is safe to run again.
3. Moodle 5.1+ also needs `FallbackResource /r.php` in the web server and `$CFG->routerconfigured = true;` in `config.php` (see Moodle's installation docs).

Note that the setup script changes site-wide settings (no self-registration, forced login, messaging off, WIB time zone). Use a dedicated Moodle site for the competition.

## 3. Roles

| Role | Who | Can do |
|---|---|---|
| **Super Admin** | Moodle site administrator created at install | Everything, including *Manage admins* |
| **Admin Quiz** | Committee members given access by the Super Admin | Register participants, import/edit questions, create quizzes and change quiz settings, run *Quiz check*, view and download results. Cannot give admin access to anyone, cannot log in as a participant, cannot edit the Super Admin account, cannot delete the course |
| **Peserta** | Accounts created by admins, enrolled as *student* | Log in and take the quiz. Cannot register themselves, change their name, see the participant list, or message other participants |

To give someone admin access: *Planopolis panel → Manage admins*, type an existing username, or fill in all fields to create a new admin account.

## 4. Admin guide

Everything is in **Planopolis panel** in the top menu (visible to Super Admin and Admin Quiz only).

### Register participants

1. Download the participant template from the *Register participants* page (or use `templates/Template_Peserta_Planopolis.xlsx`).
2. One row per participant (or per team if a team shares one account). Required: **Username** (lowercase, no spaces, unique) and **Nama**. Optional: Password, Nama Belakang, Email, Instansi, Kota, **Sesi**.
   - Leave **Password** empty to have one generated, e.g. `Rwknn-6798`. Passwords you type yourself must follow the site's password rules (upper case, digit, symbol, at least 8 characters).
   - **Sesi** puts participants in a Moodle group of that name, so you can give each session its own quiz time (see below).
3. Upload, check the preview, click *Register*.
4. Click **Download credentials (Excel)** and hand the usernames and passwords out. Passwords are not stored in readable form, so download the file before leaving the page. To reset one password later, use *Participant list* → the participant → *Edit profile*.

With *Update existing accounts* ticked, a username that already exists gets a new password and updated name; unticked, it is only enrolled.

### Import questions

1. Fill in `Template_Soal_Planopolis.xlsx`, sheet **Soal**. The sheet **Petunjuk** explains every column. Yellow columns are required: **Soal, Opsi A, Opsi B, Kunci**.
2. Options go in order from A (up to E), with no empty option in between. **Kunci** is one letter.
3. Scoring per question:
   - **Nilai Benar** – points for a correct answer (empty = 1).
   - **Nilai Salah** – points for a wrong answer, 0 or negative (empty = 0).
   - Unanswered = 0.
   - Nilai Salah must be a simple fraction of Nilai Benar, as Moodle requires: 4/−1, 3/−1, 2/−1, 5/−1, 4/−2, 5/−2, 1/−1 and similar all work. The import tells you if a combination is not supported.
4. Images and audio:
   - Write the file name in **Gambar Soal** or **Audio Soal** (several files separated by `;`), or put `[img:file.png]` / `[audio:file.mp3]` anywhere inside a question or an option. An option can be just an image, e.g. `[img:peta_a.png]`.
   - Put the Excel file and all media files into **one ZIP** and upload the ZIP. Images: png, jpg, gif, webp, svg. Audio: mp3, wav, ogg, m4a.
   - Do not paste pictures into Excel cells; they are not read.
5. **Acak Opsi** = N keeps the option order for that question (use it for "Semua benar" type options). Everything else is shuffled per participant.
6. *Import questions* → choose the quiz → upload → check the preview. Nothing is saved while there are errors; each error names the Excel row. Click **Import**.
   - *Use total points as the quiz maximum grade* (on by default) makes the final score the raw sum of points, e.g. 40 × 4 = 160. Untick it to scale scores to the quiz maximum grade instead, e.g. 100.
   - Questions cannot be added to a quiz that already has attempts. Use a new quiz, or delete test attempts first (*quiz → Results → select attempts → Delete*).

Imported questions are ordinary Moodle questions: they can be edited later in the quiz's *Questions* page, including their images and audio.

### Quiz settings

The setup script creates the first quiz with competition settings: 1 attempt, 1 question per page with free navigation, shuffled questions and options, and automatic submission when time runs out. Participants see their answers while working and only their mark after the quiz closes; right answers are never shown.

Before the exam, open the quiz *Settings* and set:

- **Open the quiz / Close the quiz** (WIB) and **Time limit**.
- Several sessions: keep one quiz and add *Overrides → Group overrides* per Sesi group with that session's open/close time. Alternatively, create one quiz per round (*Add a quiz / round* on the panel).
- Optional: a quiz password announced in the exam room (*Extra restrictions on attempts*).

### Quiz check

Run **Quiz check** after importing or editing questions and again on exam day. It lists **Problems** (must fix) and **Warnings** (look at them), with an *Edit* link per question:

- Question missing, or left in **Draft** status (it would not be shown). This was one of the 2024 problems.
- Random-question slots whose pool has fewer questions than needed.
- **Duplicate options**, no correct answer, two answers marked correct, empty options. This was the other 2024 problem.
- Same question twice, questions worth 0 points, non-multiple-choice questions.
- Settings that leak answers (right answer shown during or right after the attempt), more than 1 attempt, no closing time.

The quiz table on the panel shows **Ready** when the check finds no problems.

### Exam day

1. Run **Quiz check** and take the quiz once with a test participant account. Then delete that test attempt, so it does not appear in the results.
2. Check the load: have 20–30 committee members start the quiz at the same moment and click through a few pages. If pages are slow, give the server more CPU/RAM before the real event.
3. During the exam, *Results* shows who is *In progress* and who has *Finished*. If a participant's connection drops, they log in again and continue the same attempt; the timer keeps running.

### Results

*Results* ranks participants by score. Equal scores are ordered by the shorter duration. Each row shows correct / wrong / blank counts, start and finish time, and **Left page**: how many times the participant switched away from the quiz window. **Download Excel** gives the same table as an .xlsx file. The quiz's own *Results* report in Moodle has the answer given to every question if you need details.

## Screenshot and copy protection

On quiz pages, participants get:

- no text selection, copying, right-click, dragging or printing;
- a warning on Ctrl+C, PrintScreen and developer-tool shortcuts (the clipboard is cleared after PrintScreen);
- a blurred page with a notice whenever the quiz window loses focus (another tab or app, the Windows snipping tool, a screen recorder window). Each time is logged and counted in *Results*;
- a faint watermark with the participant's name and username across the whole page, so a leaked photo or screenshot shows who took it.

**Be realistic about the limits.** No website can completely block screenshots: a phone camera always works, and a technical user can disable browser scripts. The protection makes copying hard, makes leaks traceable, and shows the committee who kept leaving the page. For stricter control, Moodle's built-in **Safe Exam Browser** support locks the computer into the quiz (*quiz Settings → Safe Exam Browser → Yes – configure manually*). Participants must install Safe Exam Browser first (Windows, macOS, iPad; there is no official Android version).

The protection and watermark can be switched off in *Site administration → Plugins → Local plugins → Planopolis quiz tools*. Admins never see it on their own screens.

## Troubleshooting

| Problem | Fix |
|---|---|
| "Required column(s) not found" | Use the template; do not rename the header row. |
| "File … is not in the ZIP" | The file name in the sheet must match the file in the ZIP exactly (upper/lower case does not matter). Folders inside the ZIP are fine. |
| "Nilai Salah … is not supported" | Choose a penalty that is a simple fraction of Nilai Benar (see Import questions). |
| "This quiz already has attempts" | Delete test attempts or use a new quiz. |
| Site stays in English | The Indonesian language pack could not be downloaded at setup. Install it in *Site administration → Language → Language packs* and set the default language. The Planopolis pages are already translated. |
| Participant forgot password | *Participant list* → participant → *Edit profile* → new password. |

## What was tested

Tested on Moodle 5.2.3+ (PHP 8.3, PostgreSQL 16), including in a headless Chrome browser:

- Setup script on a fresh site, and re-running it.
- Excel and ZIP question import: text, image, audio, image-only options and mixed scoring. Images and audio display for participants; questions and options are shuffled.
- Rejection of duplicate options, empty or invalid keys, gaps between options, unsupported penalties, missing media, and questions already in the quiz.
- Quiz check detecting duplicate options and Draft questions added through the web/database.
- Participant import from Excel and CSV, generated passwords, validation errors, and the credentials Excel download.
- Participant attempts through to submission, and the results ranking and Excel download (numeric cells).
- Super Admin creating an Admin Quiz account, and that account's permission limits.
- Protection layer: watermark, blocked Ctrl+C, blur on leaving the window, and the event log.
- The Docker entrypoint's install, upgrade and cron modes, run directly on the test server.

Not tested here: building the Docker image itself (no Docker available in the test environment) and a real load test. Build the image once and run the exam-day load check before the event.
