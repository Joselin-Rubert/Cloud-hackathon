# StudentFlow — Smart Student Life Assistant

A complete PHP 8 + MySQL web application for students: tasks, focus timer, study planner, expenses, habits, goals, calendar, analytics and a **"What should I do now?"** recommendation engine.

**Stack:** PHP 8 · MySQL (PDO prepared statements) · PHP sessions · Tailwind CSS (CDN) · Chart.js (CDN) · Lucide icons (CDN). No build step, no Node/React required.

---

## Requirements

- [XAMPP](https://www.apachefriends.org/) (Apache + MySQL + PHP 8+)
- Or any PHP 8 + MySQL environment

## Setup (XAMPP)

1. **Copy the project** into `C:\xampp\htdocs\studentflow`
2. **Start services** — open the XAMPP Control Panel and start **Apache** and **MySQL**
3. **Import the database** — open <http://localhost/phpmyadmin>, create a new database named `studentflow`, select it, then **Import** → choose `database/studentflow.sql` → Go
   - (Optional) Import `database/demo_data.sql` for sample data
   - Then import `database/github_migration.sql` to add the GitHub Explorer tables
   - Then import `database/features_migration.sql` to add the learning-resource cache table (required for the Learning Resource Finder)
4. **Database credentials** — defaults are `root` / empty password / `127.0.0.1`, matching a stock XAMPP. To change them, set environment variables (no code edits needed):
   - `SF_DB_HOST`, `SF_DB_NAME`, `SF_DB_USER`, `SF_DB_PASS`
5. **Open the app** — <http://localhost/studentflow/>

## Cloud deploy (one click)

The repo ships with a `Dockerfile` (Apache + PHP + MariaDB auto-bootstrapped in a
single container) and a Render Blueprint:

1. Push this repo to GitHub (done).
2. Create a free account at <https://render.com> (sign in with GitHub).
3. **New + → Blueprint → Cloud-hackathon → Apply**. The app deploys with its
   database included; no external MySQL needed.
4. Open the generated URL and log in with the demo account below.

## Demo account

Import `database/demo_data.sql`, then log in with:

| Email | Password |
|---|---|
| `demo@studentflow.app` | `demo1234` |

Or register a fresh account at `/register.php`.

## Project structure

```
studentflow/
├── config/          # database, auth/sessions/CSRF, shared domain logic
├── includes/        # head, sidebar, navbar, header, footer, auth guard
├── actions/         # POST endpoints (tasks, focus, expenses, habits, goals, ...)
├── api/             # JSON endpoints (dashboard, notifications, chart data)
├── assets/
│   ├── css/style.css
│   └── js/          # main.js (SF core) + one script per page
├── database/        # studentflow.sql (schema), demo_data.sql (sample data), *_migration.sql add-ons
├── *.php            # pages: login, register, dashboard, tasks, focus, ...
```

## Features

- **What should I do now?** — rule-based recommendation scoring due date, priority, effort and status; Focus Mode starts a session against the recommended task in one click
- **Deadline Risk Indicator** — every task is graded `SAFE / APPROACHING / CRITICAL` from its due date vs. estimated effort (overdue and high-priority tasks escalate to critical); shown as colored chips in **Tasks**, colored events + a legend in **Calendar**, and a **Deadline Risk** doughnut + metric card in **Analytics**, with a summary on the dashboard
- **Student Life Score** — a 0–100 lifestyle score on the dashboard built from task completion, study time, goals, habits, deadlines and budget (each with an earned/wins breakdown), a 30-day change indicator and an "How is the score calculated?" modal
- **Learning Resource Finder** — search books via the **Google Books API** with an automatic **Open Library** fallback when the keyless endpoint is rate-limited; results are cached per-user (24h) for instant repeat searches, with a per-topic shortcut chips
- **Tasks** — sections (today / upcoming / overdue / completed / trash), search, filter, sort, scoring
- **Focus Mode** — Pomodoro timer with breaks, saved focus sessions, auto-start via `?auto=1`
- **Study planner** — generates a phased study plan from subjects and exam dates
- **Analytics** — productivity score, task/study/expense/habit charts (Chart.js)
- **Expenses** — budget tracking, category and weekly/monthly charts, insights
- **Habits & Goals** — streaks, 30-day calendar, progress rings
- **Calendar** — events with categories
- **GitHub Explorer** — search any public GitHub username (official REST API via PHP cURL, no token/OAuth): profile card, public repositories with search/filter/sort, total stars/forks, programming-language doughnut chart, recent public activity and side-by-side profile comparison; optional sync + a Developer Snapshot card on the dashboard, plus an optional GitHub repository link per goal
- **Notifications** — generated from real deadlines, exams, habits and budgets
- **Dark mode**, profile with avatar upload, settings, secure auth (`password_hash`, CSRF, prepared statements)

## Security

- PDO prepared statements everywhere
- `password_hash()` / `password_verify()`
- CSRF token on every POST (meta + hidden input + header check)
- Session-based auth with remember-me token
- All output escaped with `e()` (htmlspecialchars)
