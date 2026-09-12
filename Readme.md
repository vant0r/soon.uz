# WEBHUB.UZ — PRODUCTION SPECIFICATION FOR CLAUDE CODE

This is the single source of truth for rebuilding **webhub.uz**, a live IT-services agency website (founder: G'iyosiddin Adhamjonov, Fergana, Uzbekistan). It is written to be executed directly, without requiring clarification. Every requirement below is mandatory unless explicitly marked otherwise. Where a requirement seems to conflict with another, check Section 1 first — the conflict has almost certainly already been identified and resolved there.

---

## 0. MANDATORY WORKFLOW

Execute these six phases **in order**. Do not begin a phase until the prior phase's output exists.

1. **AUDIT** — Read this entire document. Produce a checklist enumerating every individual requirement (every bullet under every "Requirements (MUST)" block, every acceptance-criterion checkbox, every schema field). This checklist is the master task list for the rest of the build and the basis of the Final Audit in Section 16.
2. **PLAN** — Before writing feature code: finalize the file/folder structure, finalize the database schema (Section 12, expandable but never reducible), and confirm the build sequence in Section 5.
3. **IMPLEMENT** — Build strictly in the order defined in Section 5. Do not start a later phase while an earlier phase has open TODOs or failing acceptance criteria.
4. **TEST** — For every checklist item from Phase 1, verify it against its Acceptance Criteria. Record pass/fail per item. This must happen at the end of each phase in Section 5, not only once at the very end (see Section 15).
5. **FIX** — Resolve every failing item before proceeding. Zero known failures may remain when a phase is marked complete.
6. **FINAL AUDIT** — Re-run the entire Phase 1 checklist against the live, running build (Section 16). Confirm nothing was dropped, nothing undocumented was added, and every non-regression rule (Section 3) held throughout.

---

## 1. RESOLVED CONFLICTS AND AMBIGUITIES

An earlier draft of this specification contained the following unresolved conflicts and ambiguities. Each is resolved here explicitly. These resolutions are binding and override any looser phrasing that may still appear informally elsewhere in this document.

1. **Chat threading model.** One part of the earlier draft implied "one thread per user"; another allowed "per-application or general, implementer's choice." **Resolution:** there is exactly **one chat thread per user**, shared across all of that user's applications. No per-application threads. `chat_threads` has no `application_id` column — this is intentional, not an oversight.
2. **JavaScript in the tech stack.** An earlier draft listed the stack without JavaScript, then separately required features (AJAX, live chat, theme switching, animations) that are impossible without it, with a vague "fallback to polling if no JS wanted" escape hatch. **Resolution:** vanilla JavaScript (ES6+, no framework, no external library) is a **mandatory** part of the stack, not optional. There is no no-JS fallback mode. See Section 4.
3. **API content-type vs. file attachments.** The API is specified as JSON-only (`Content-Type: application/json`) for every request/response, but chat messages must support file attachments, which normally require `multipart/form-data`. **Resolution:** `/api/*` endpoints (the APK-facing surface) remain strictly JSON; file attachments sent through `/api/chat/{thread_id}` are base64-encoded inside the JSON body (see Section 10.4). Browser-based uploads from the Admin/User web panels (media library, in-panel chat attachments) are **not** part of the `/api/*` surface and may use standard `multipart/form-data` directly to their own PHP handlers.
4. **Homepage stat counters: admin-editable vs. computed.** **Resolution:** stats are computed live from the database (`COUNT()`) by default. `site_settings` includes optional override keys (`stat_projects_override`, `stat_clients_override`); when an override is non-null, it takes precedence over the computed value. This satisfies both original options without ambiguity.
5. **Installer self-protection: lock file vs. self-delete.** **Resolution:** `install.php` **MUST** write an `install.lock` file after a successful install and refuse to run again whenever that file exists — this is the required mechanism. Self-deleting `install.php` in addition is permitted as extra hardening but is not a substitute for the lock file (file permissions may prevent self-deletion).
6. **Backup export format: "SQL or JSON."** **Resolution:** a full SQL dump is the required, primary backup mechanism. A JSON export of content tables (services, portfolio, blog_posts, site_settings) is an additional, optional convenience export — not a replacement for the SQL dump.
7. **Build-order dependency gap.** The original draft's implementation order built the full Admin Panel (all 11 capabilities) after the User Panel, but several Admin capabilities (content management) produce the data that the Homepage and User Panel need in order to be meaningfully built and tested, while other Admin capabilities (user management, chat, applications, notifications) can only be tested once Homepage/User Panel have generated real users, applications, and messages. **Resolution:** the Admin Panel is split into two build phases — **Admin Content Management** (built before Homepage) and **Admin Operational Tools** (built after User Panel). See Section 5 and the phase tags in Section 7.2.
8. **Missing data model for service customization.** The User Panel requires "customize a selection based on admin-defined options/add-ons," but no schema field existed to store either the admin-defined options or the user's chosen customization. **Resolution:** `services.addons_json` (admin-defined available add-ons) and `applications.customization_json` (the user's actual selection at submission time) are added to the schema in Section 12. This is a data-completeness fix required to satisfy an already-existing requirement, not a new feature.
9. **Service deletion vs. application history integrity.** If a service referenced by past applications is later edited or deleted, application history could break or misrepresent what was actually ordered. **Resolution:** `applications.service_id` is nullable with `ON DELETE SET NULL`, and `applications.service_name_snapshot` stores the service's name at the time of submission so history remains accurate regardless of later catalog changes.
10. **Language scope ("Uzbek only").** This could be misread as applying to code. **Resolution:** the Uzbek-only requirement (Section 6.6) applies strictly to text rendered to end users in the browser — labels, buttons, messages, content, notifications. It does **not** apply to code identifiers, database column/table names, code comments, commit messages, or server logs, which remain in English for maintainability.

---

## 2. PROJECT CONTEXT AND BUSINESS GOALS

### 2.1 What this project is
WebHub.uz is a Fergana-based IT services agency site. Clients browse services (websites, Telegram bots, AI solutions, mobile apps), view a portfolio, and submit orders/applications ("ariza"). The founder communicates with clients directly through the platform.

### 2.2 Business goals that MUST be preserved (non-negotiable)
- G1: Present a services catalog with pricing.
- G2: Capture client orders/applications.
- G3: Showcase completed work (portfolio) to build trust.
- G4: Enable direct one-to-one communication between the admin and each client.

Any implementation choice that weakens G1–G4 is a defect, regardless of visual polish.

### 2.3 Known defects in the current live site — MUST be fixed
Found by auditing the current live version; each is a required bug fix, not optional:
- **D1 — Stat counters show a hardcoded `0`.** Fixed per Resolution 4 in Section 1 (computed by default, optional override).
- **D2 — Portfolio page has zero projects.** The rebuilt Admin Panel must let the admin add portfolio entries (Section 7.2), and the rebuilt Portfolio section (Section 7.1) must display them once added.
- **D3 — Footer contains an empty `tel:` link.** No link anywhere in the final build may render with an empty `href` or empty visible label. Every contact value comes from `site_settings` as the single source of truth (Section 3, Rule 4) and either renders fully populated or does not render at all.

---

## 3. NON-REGRESSION AND SCOPE-DISCIPLINE RULES

Apply for the entire project duration, at every phase:

1. **No feature deletion without replacement.** Every capability described in this document must exist and work in the shipped build. If a requirement seems genuinely impossible given the other constraints, do not silently drop it — implement the closest compliant version and explicitly flag the conflict in your output, in the same style as Section 1.
2. **No silent scope or technology substitutions.** Do not swap in a different technology, library, or approach than Section 4 specifies without explicitly flagging it.
3. **No cross-phase regressions.** If work in a later phase (e.g., Admin Operational Tools) touches shared code from an earlier phase (e.g., the theme system, a shared card component, the chat UI), re-run that earlier phase's acceptance criteria before proceeding.
4. **One source of truth per data type.** Site-wide values (contact info, stats, pricing, SEO fields) are read from the database in exactly one shared place/function and reused everywhere they appear — never hardcoded per-template. This is precisely how defect D3 occurred originally.
5. **Theme and responsiveness are per-component requirements, not a final pass.** Every new page/component must work in both light and dark mode and at all required breakpoints (Section 6.7) before it is considered done.
6. **Uzbek-only user-facing text, no exceptions** (see Section 1, Resolution 10, for exact scope). Any hardcoded English/Russian string visible in the rendered UI is a defect.
7. **No unnecessary features.** Do not add functionality, pages, libraries, or configuration options beyond what this document requires or what Section 1's resolutions explicitly add to satisfy an existing requirement. If you believe something is missing, flag it explicitly rather than silently building it.
8. **No framework introduction, ever**, even a "small" one (no micro-framework, router library, ORM, or templating engine beyond plain PHP). See Section 4.

---

## 4. TECHNOLOGY STACK (resolved — see Section 1, Resolutions 2–3)

**Required:**
- **Backend:** PHP 8+, no framework, procedural or lightweight OOP as needed.
- **Markup/styling:** hand-written HTML5 + CSS3, no CSS framework, no CSS preprocessor requiring a build step.
- **Client-side interactivity:** vanilla JavaScript (ES6+), no framework, no external JS library. Mandatory — not optional, no no-JS fallback mode.
- **Data interchange:** JSON for all AJAX and `/api/*` payloads.
- **Database:** MySQL via PHP PDO with prepared statements exclusively — no raw string-concatenated SQL anywhere, ever.

**Explicitly forbidden:**
- Build tools, bundlers, or transpilers (Webpack, Vite, Babel, Sass compiler as a build step, etc.).
- Any npm/Composer install step required merely to run the site. Any needed static asset (e.g., icon SVGs) is vendored as committed files.
- Reliance on external CDNs for core functionality. The only required external network call is the Google OAuth flow itself; everything else must work fully offline/self-hosted.

**Acceptance Criteria:**
- [ ] No `node_modules`, `vendor` (Composer), `package.json`, or `composer.json` is required to be present for the site to run in production.
- [ ] Disabling internet access except for the OAuth domain still renders every page and every non-Google-auth feature correctly.
- [ ] A repo-wide search finds zero references to a frontend or backend framework.

---

## 5. MASTER IMPLEMENTATION ORDER (dependency-safe)

Build in this exact sequence. Each phase must pass its own acceptance criteria (per the relevant section) before the next phase begins.

| Phase | Scope | Depends on |
|---|---|---|
| 1 | Database schema + `install.php` (Sections 11, 12) | — |
| 2 | Design system foundation: theme variables, glass components, icon set, typography, animation utilities, shared layout shell (Section 6) | Phase 1 |
| 3 | Authentication: Google OAuth (users) + separate Admin login + Login Loading Animation (Sections 8, 9) | Phase 2 |
| 4 | **Admin Panel — Content Management**: Homepage content editor, Media library, Services & pricing, Portfolio management, Blog management, Site settings (Section 7.2, Phase-4 items) | Phase 3 |
| 5 | Public Homepage (Section 7.1), now built and tested against real seeded content from Phase 4 | Phase 4 |
| 6 | User Panel: profile, service browsing/customization/application submission, chat initiation, notifications feed, blog reading (Section 7.3) | Phase 3, Phase 4 |
| 7 | **Admin Panel — Operational Tools**: User management, Chat, Applications management, Notifications sending (Section 7.2, Phase-7 items) | Phase 6 (needs real users/applications/messages to manage) |
| 8 | `/api/` layer, exposing everything built in Phases 4–7 (Section 10) | Phases 4–7 |
| 9 | Security hardening pass across all of the above (Section 13) | Phases 1–8 |
| 10 | Testing & QA (Section 15) | Phase 9 |
| 11 | Final Audit (Section 16) | Phase 10 |

Do not reorder these phases. If a technical reason genuinely requires reordering, state the reason explicitly before doing so.

---

## 6. DESIGN SYSTEM

### 6.1 Visual direction
**Requirements (MUST):**
- Extreme minimalism with a "2100" futuristic feel: generous negative space, oversized confident typography, a restrained color palette, no decorative clutter.
- Tone reference: the visual style of **soon.uz** — large type, sparse layout, quiet motion. This is a tone reference, not a literal design to copy.
- Every visual element serves a functional or hierarchical purpose.

**Acceptance Criteria:**
- [ ] No page has more than one primary accent color active at once outside interactive (hover/focus) states.
- [ ] A documented type scale (e.g., a 1.25–1.5 ratio between heading levels) is defined once and used consistently everywhere; no page invents its own one-off font sizes.

### 6.2 Animation
**Requirements (MUST):**
- Scroll-triggered entrance animations (fade/slide/parallax) on homepage sections.
- Micro-interactions on buttons/cards for hover and touch-active states (subtle transform + shadow change).
- Page/route transitions do not feel like a hard reload, even on classic multi-page PHP navigation (a brief fade or equivalent transition).

**Acceptance Criteria:**
- [ ] All animations respect `prefers-reduced-motion` (reduced/disabled when the OS setting requests it).
- [ ] No animation blocks interaction — a user can click/tap an element before its own entrance animation finishes.

### 6.3 Glassmorphism ("Apple glass")
**Requirements (MUST):**
- Cards, navbar, modals, and chat panels use `backdrop-filter: blur(...)`, a semi-transparent background, and a subtle ~1px semi-transparent border.
- Must render correctly in both light mode (light/white-based glass) and dark mode (dark-based glass).

**Implementation Notes:**
- Implement as one reusable CSS utility class/set of custom properties, not copy-pasted inline styles per component.

**Acceptance Criteria:**
- [ ] Verified visually in both themes, at both mobile and desktop widths, over a busy background (image or gradient) to confirm the blur is actually visible.

### 6.4 Light / dark mode
**Requirements (MUST):**
- Implemented via CSS custom properties, toggled with a `data-theme` attribute (or equivalent) on the root element.
- On first visit, detect `prefers-color-scheme` and apply it as the default.
- The user's explicit toggle choice is persisted in `localStorage` and takes priority over the system setting on return visits.
- A theme toggle control is present in the navigation on every page; switching is instant and smooth, with no flash of the wrong theme on page load.

**Acceptance Criteria:**
- [ ] Reloading the page preserves the user's last chosen theme.
- [ ] A fresh browser profile with the OS set to dark mode loads the site in dark mode by default.
- [ ] Every component built in Section 7 has been visually checked in both themes as part of that component's own "done" definition (Section 3, Rule 5).

### 6.5 Iconography
**Requirements (MUST):**
- One consistent, professional, stroke-based icon set (e.g., Lucide or Phosphor), vendored locally as SVG files — not loaded from a CDN.
- Icon color/weight controlled via CSS, not baked into the SVG files.

**Acceptance Criteria:**
- [ ] No two icons from different icon sets/styles appear anywhere in the shipped UI.
- [ ] Icons render correctly with external network access disabled (aside from the Google OAuth call), confirming they are not CDN-dependent.

### 6.6 Language (see Section 1, Resolution 10, for exact scope)
**Requirements (MUST):**
- 100% of user-facing text — homepage, admin panel, user panel, error messages, validation messages, notifications, empty states, placeholder text — is Uzbek (Latin script) only.
- No language switcher; no other language anywhere in the rendered UI.

### 6.7 Responsiveness
**Requirements (MUST):**
- Mobile-first CSS.
- Admin and User panels fully usable on mobile: collapsible/hamburger navigation, minimum 44px touch targets.
- Chat UI on mobile follows a familiar messaging-app pattern (input pinned to bottom, natural scroll behavior).

**Acceptance Criteria:**
- [ ] Every page/panel tested at minimum: 360px, 768px, 1024px, 1440px, 2560px widths.
- [ ] No horizontal scroll or overlapping elements at any tested width.

---

## 7. SITE ARCHITECTURE

### 7.1 `/` — Public Homepage (Phase 5)
**Requirements (MUST), top to bottom:**
1. Hero: headline, short description, CTA buttons, live stats (Section 1, Resolution 4).
2. Services: cards for Website / Telegram Bot / AI Solution / Mobile App, with admin-editable pricing (Section 7.2).
3. Portfolio preview: latest 4–6 admin-added projects (image, short description, external link).
4. Blog preview: latest published posts.
5. "How we work" — the 4-step process, shown as an animated timeline.
6. Contact/CTA form (name, phone, service type, message) that creates a new row in `applications` (see Section 12) and appears in the Admin Panel's Applications view.
7. Footer with fully populated contact links only, sourced from `site_settings` (Section 1, Resolutions 4 and see D3).

**Acceptance Criteria:**
- [ ] Submitting the contact form creates exactly one new `applications` row, visible in the Admin Panel without requiring a manual cache clear.
- [ ] Stat counters never display a hardcoded `0` (D1 verified fixed).
- [ ] Portfolio preview and Blog preview both render correctly with zero items (graceful empty state) and once items exist (D2 verified fixed).
- [ ] No link renders with an empty `href` or empty label (D3 verified fixed).

### 7.2 `/admin/` — Admin Panel (protected, admin session required)

Split into two build phases per Section 1, Resolution 7 / Section 5. All 11 capabilities are equally mandatory; the phase tag only controls **build order**, not importance.

#### 7.2.A — Content Management capabilities (Phase 4, built before Homepage)
1. **Homepage content** — hero text, stat number overrides (Section 1, Resolution 4), "how we work" step text.
2. **Media library** — upload, replace, delete logo, banner, OG image, and other image/video assets via drag-and-drop; backed by the `media` table (Section 12).
3. **Services & pricing** — create/edit/delete services: title, description, price (integer, UZS, no decimals), "what's included" list, and `addons_json` (Section 1, Resolution 8).
4. **Portfolio management** — add/edit/delete projects: image, title, description, client name, external link, category, sort order.
5. **Blog management** — write/edit posts via a simple WYSIWYG or Markdown editor, with image insertion and draft/published state.
6. **Site settings** — SEO fields (meta description, keywords), contact info (phone, Telegram, Instagram) as the single source of truth for the whole public site, theme accent color, stat overrides. Google OAuth Client ID/Secret are **not** stored here — they live only in `config.php`, generated by the installer (Section 11).

#### 7.2.B — Operational capabilities (Phase 7, built after User Panel)
7. **User management** — list all registered users (name, email, linked Google account, registration date, `status`); open any user's profile to view their full application and message history. Users are never hard-deleted; `users.status` supports `active` / `blocked` / `deleted` as a soft-delete pattern so history is preserved (Section 12).
8. **Chat (Admin ↔ User)** — one thread per user (Section 1, Resolution 1); text messages plus file/document/image attachments with download links; read/unread state; near-real-time updates via AJAX polling every 3–5 seconds.
9. **Applications management** — view all applications from both the Homepage form and the User Panel; change status through the lifecycle `new → in_review → approved → completed → cancelled` (exact enum values, Section 12).
10. **Notifications** — send a targeted notification to one user, or a broadcast to all active users. A broadcast is implemented by inserting one `notifications` row per active user in a single admin action — `notifications.user_id` remains `NOT NULL`; there is no separate broadcast table.
11. **Admin authentication** — separate login/password screen; see Section 8.

**Acceptance Criteria (applies to all 11 capabilities):**
- [ ] Each capability has a corresponding UI screen and a create/edit/delete action (as applicable) that persists to the database and is reflected on the public site or User Panel without manual intervention.
- [ ] Admin Panel is fully operable on a mobile viewport (Section 6.7).
- [ ] Every admin action that changes data is protected by a CSRF token (Section 13).
- [ ] Phase 4 items are fully functional and independently testable before Phase 5 (Homepage) begins, per Section 5.
- [ ] Phase 7 items are fully functional and independently testable using real data generated in Phases 5–6, per Section 5.

### 7.3 `/user/` — User Panel (Phase 6, authenticated clients only)
**Requirements (MUST):**
1. **My Profile** — name, phone, avatar, linked Google account info (read-only), editable settings.
2. **Services** — browse the catalog, select a service, optionally choose from its admin-defined `addons_json` options, and submit as a new application (stored with `customization_json`, Section 1 Resolution 8).
3. **My Applications** — list of all submitted applications with current status and history.
4. **Chat** — the user's single thread (Section 1, Resolution 1) with the admin, supporting file attachments.
5. **Notifications** — feed of admin-sent updates and application status changes, with an unread-count indicator.
6. **News/Blog** — read published blog posts.

**Acceptance Criteria:**
- [ ] A user can go from "browse services" to "submitted application visible in both their own My Applications list and the admin's Applications list" in one uninterrupted flow, with no state lost on any page reload.
- [ ] Unread notification count updates without a full page reload after the admin sends a notification (within the polling interval).
- [ ] Fully usable on mobile (Section 6.7).

---

## 8. AUTHENTICATION

**Requirements (MUST):**
- **Users:** Google OAuth 2.0, Authorization Code flow (not implicit), with the OAuth `state` parameter validated to prevent CSRF on the auth flow itself. No separate email/password registration path for regular users.
  - **"No verification codes" means:** no additional custom OTP/SMS/email confirmation step is layered on top of Google's own sign-in. It does **not** mean skipping OAuth security mechanics (state validation, server-side token exchange are still required).
  - On first sign-in, auto-create the `users` row from the Google profile (name, email). Google does not reliably provide a phone number — prompt for it afterward in Profile (Section 7.3.1), not during sign-in.
- **Admin:** separate login screen, login + password, hashed with `password_hash()` (bcrypt or Argon2id) — intentionally independent of Google, so admin access does not depend on a third-party identity provider being available.
  - Password policy: minimum 10 characters, at least one uppercase letter, one lowercase letter, and one digit.
  - No self-service password reset flow is required. If the admin forgets their password, recovery is a manual server-side action (e.g., a maintenance script that resets the hash directly in the database) — do not build a "forgot password" email flow; it is out of scope (Section 17).
- **Sessions/tokens:**
  - Admin session: native PHP session with `HttpOnly`, `Secure`, `SameSite=Strict` cookie flags; 30-minute idle timeout requiring re-login.
  - API bearer token (`api_tokens` table): random value of at least 64 characters, 30-day expiry; a client obtains a new one by calling `POST /api/auth/google` again (Section 10).

**Acceptance Criteria:**
- [ ] A brand-new Google account completes first-time sign-in with zero manual admin intervention and zero extra verification step.
- [ ] Admin login works fully independently of Google — simulating Google's OAuth endpoint being unreachable does not block admin access.
- [ ] Session cookies inspected in browser dev tools show `HttpOnly`, `Secure`, `SameSite=Strict`.
- [ ] An admin session idle for 30+ minutes requires re-login on the next action.
- [ ] An expired API token is rejected with HTTP 401 (Section 10.5) and the client can obtain a fresh one via `POST /api/auth/google`.

---

## 9. LOGIN LOADING ANIMATION

**Requirements (MUST):**
- After a successful Google sign-in and before the user lands on their dashboard, show a full-screen loading animation lasting **3–5 seconds**.
- Centered content: the WebHub logo/icon performing a subtle animation (rotation, pulse, or SVG stroke draw-on), over a glassmorphism background (Section 6.3), respecting the active theme (Section 6.4).
- This window must be used productively: prefetch the user's profile and applications in the background so the dashboard renders fully populated the instant the animation ends.

**Acceptance Criteria:**
- [ ] Animation duration measured at 3–5 seconds across at least 3 test runs.
- [ ] Dashboard data (profile, applications) is confirmed already fetched (via network tab inspection) before the animation ends — no additional loading spinner appears immediately after it.

---

## 10. `/api/` — EXTERNAL API (FOR THE MOBILE APK)

### 10.1 General contract
**Requirements (MUST):**
- Every `/api/*` request and response uses `Content-Type: application/json` (file attachments handled per Section 1, Resolution 3 / Section 10.4).
- Authenticated endpoints require `Authorization: Bearer <token>`, validated against `api_tokens` (Section 12), including expiry.
- **Ownership check on every authenticated resource access:** the token's `user_id` must match the resource's owning `user_id` (e.g., a user's token can only read/write their own thread, their own applications, their own notifications). Any mismatch returns HTTP 403, never another user's data.
- **Pagination** on every list endpoint: query params `?page=<n>&per_page=<n>`, default `per_page=20`, max `per_page=100`. Response shape: `{ "data": [...], "page": <n>, "per_page": <n>, "total": <n> }`.
- **HTTP status codes**, used consistently:
  - `200` success (GET/PUT), `201` created (POST creating a resource)
  - `400` malformed request body
  - `401` missing/invalid/expired token
  - `403` valid token, insufficient permission (ownership mismatch)
  - `404` resource not found
  - `422` validation failure (with field-level errors)
  - `429` rate limit exceeded
  - `500` unhandled server error (generic message only — never leak stack traces or internal paths)
- **Error shape** (all non-2xx responses): `{ "success": false, "error": "message", "fields": { ... } }` — `fields` present only for `422`.

### 10.2 Endpoints (minimum required set)
```
GET  /api/services              — paginated list of services
GET  /api/portfolio              — paginated list of portfolio projects
GET  /api/blog                   — paginated list of published blog posts
GET  /api/settings               — public site settings (contact info, social links) — never returns OAuth secrets

POST /api/auth/google             — exchange a Google auth code/token for a WebHub API token; creates the user on first use
GET  /api/user/profile            — current user's profile
PUT  /api/user/profile            — update profile (validates phone format, Section 10.3)

GET  /api/user/applications       — paginated list of current user's applications
POST /api/user/applications       — submit a new application (service_id, customization_json, description)

GET  /api/chat/{thread_id}        — paginated messages in the caller's own thread (ownership check applies)
POST /api/chat/{thread_id}        — send a message (text and/or base64 attachment, Section 10.4)

GET  /api/notifications           — paginated list of the caller's notifications
POST /api/notifications/read      — mark one or more notification IDs as read
```

### 10.3 Validation rules
**Requirements (MUST):**
- Phone numbers: Uzbek format `+998XXXXXXXXX` (9 digits after the country code); reject anything else with `422`.
- Required fields per endpoint are enforced server-side regardless of client-side validation (name/phone/message on application submission; message or attachment — at least one — on chat send).

### 10.4 File attachments over the API (Section 1, Resolution 3)
**Requirements (MUST):**
- `POST /api/chat/{thread_id}` accepts an optional `attachment` object: `{ "filename": "...", "mime_type": "...", "data_base64": "..." }`.
- Allowed MIME types and size limits match Section 13.4 (images and documents); the base64-encoded payload is rejected with `422` if the decoded size exceeds the configured limit.
- Decoded files are stored exactly like web-panel uploads (validated, stored outside the web root or in a non-executable directory) and referenced via `chat_messages.file_path`.

### 10.5 Rate limiting
**Requirements (MUST), defaults configurable via `site_settings` without code changes:**
- General public endpoints (`services`, `portfolio`, `blog`, `settings`): 120 requests/minute per IP.
- Authenticated endpoints (`user/*`, `chat/*`, `notifications*`): 60 requests/minute per token.
- `POST /api/auth/google`: 10 requests/minute per IP.
- Exceeding a limit returns `429` with the standard error shape.

**Acceptance Criteria (Section 10 overall):**
- [ ] Every endpoint above returns valid JSON for both success and error cases, with the exact status codes in Section 10.1.
- [ ] An unauthenticated request to any `user/*`, `chat/*`, or `notifications*` endpoint returns `401`, never data.
- [ ] A token belonging to User A requesting User B's thread/application/notification returns `403`.
- [ ] Rate limiting is demonstrably active for each limit class in 10.5 (a scripted burst gets throttled at the stated threshold).
- [ ] A chat message with an oversized base64 attachment is rejected with `422`, not silently truncated or accepted.

---

## 11. `install.php` — INSTALLER

**Requirements (MUST), executed as a step-by-step wizard:**
1. Check server requirements (PHP version, required extensions: `pdo_mysql`, `gd` or `fileinfo`, etc.); halt with a clear Uzbek-language error if unmet.
2. Collect MySQL connection details (host, database name, user, password) and test the connection before proceeding.
3. Auto-create all required tables (`CREATE TABLE IF NOT EXISTS ...`, matching Section 12 exactly, including all foreign keys and enums).
4. Create the first admin account via a form (login + password, validated per Section 8's password policy).
5. Collect Google OAuth Client ID/Secret and the OAuth redirect URI; write them into `config.php` (never into `site_settings` or any DB table — Section 7.2.A item 6).
6. Auto-generate `config.php` from all collected values.
7. Write `install.lock` after success and refuse to execute any installer step if `install.lock` already exists (Section 1, Resolution 5). Self-deleting `install.php` in addition is optional extra hardening.

**Acceptance Criteria:**
- [ ] Running `install.php` against a clean, empty database produces a fully working site with one working admin account and zero manual SQL required.
- [ ] Re-visiting `install.php` after a successful install is blocked (shows a clear message, performs no destructive action).
- [ ] `config.php` is confirmed inaccessible from a direct browser request after install (Section 13.6).

---

## 12. DATABASE SCHEMA

Baseline schema — fields listed here are a floor, not a ceiling. Additional fields/tables discovered as necessary during PLAN are fine to add; removing or renaming any field listed here is not, without flagging the conflict per Section 3, Rule 1.

**Conventions (apply to every table):** primary keys are `BIGINT UNSIGNED AUTO_INCREMENT`; timestamps are `DATETIME`; all foreign keys are enforced at the database level (InnoDB), not only in application code.

```
users            (id, google_id UNIQUE, name, email UNIQUE, phone NULLABLE,
                  avatar NULLABLE, status ENUM('active','blocked','deleted') DEFAULT 'active',
                  created_at)

admins           (id, login UNIQUE, password_hash, name, created_at)
                  -- table supports multiple rows; installer creates exactly one at install time;
                  -- no roles/permissions system is required (Section 3, Rule 7 — do not add one)

services         (id, title, description, price INT, -- UZS, no decimals
                  addons_json NULLABLE,               -- admin-defined optional add-ons, Resolution 8
                  features_json, sort_order, created_at)

portfolio        (id, title, description, image, client_name, link, category,
                  sort_order, created_at)

blog_posts       (id, title, body, image, status ENUM('draft','published') DEFAULT 'draft',
                  created_at)

applications     (id, user_id NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                  service_id NULLABLE REFERENCES services(id) ON DELETE SET NULL,
                  service_name_snapshot,               -- Resolution 9
                  customization_json NULLABLE,          -- user's chosen add-ons, Resolution 8
                  description,
                  status ENUM('new','in_review','approved','completed','cancelled') DEFAULT 'new',
                  created_at)

chat_threads     (id, user_id UNIQUE NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                  created_at)
                  -- UNIQUE on user_id enforces "exactly one thread per user" (Resolution 1)

chat_messages    (id, thread_id NOT NULL REFERENCES chat_threads(id) ON DELETE CASCADE,
                  sender_type ENUM('admin','user') NOT NULL,
                  sender_id NOT NULL,        -- admins.id or users.id depending on sender_type
                  message NULLABLE,
                  file_path NULLABLE,
                  is_read BOOLEAN DEFAULT FALSE,
                  created_at)

notifications    (id, user_id NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                  title, message, is_read BOOLEAN DEFAULT FALSE, created_at)
                  -- broadcasts = one row per active user, inserted in one admin action (Section 7.2.B item 10)

site_settings    (key UNIQUE, value)
                  -- required keys include: contact_phone, contact_telegram, contact_instagram,
                  -- seo_meta_description, seo_keywords, theme_color_primary,
                  -- stat_projects_override NULLABLE, stat_clients_override NULLABLE,
                  -- max_upload_image_mb, max_upload_doc_mb
                  -- OAuth Client ID/Secret are NEVER stored here (they live in config.php only)

api_tokens       (id, user_id NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                  token UNIQUE, created_at, expires_at)

media            (id, filename, path, mime_type, size_bytes,
                  context ENUM('logo','banner','og_image','portfolio','blog','chat_attachment','generic'),
                  uploaded_by REFERENCES admins(id), created_at)

audit_log        (id, admin_id REFERENCES admins(id), action, target_type, target_id,
                  details_json, created_at)
```

**Acceptance Criteria:**
- [ ] Every field listed above exists in the final schema.
- [ ] All foreign keys listed above are enforced at the database level (verified by attempting an insert that violates one and confirming it is rejected).
- [ ] `chat_threads.user_id` is UNIQUE (verified by attempting to create a second thread for the same user and confirming it fails or is deduplicated).
- [ ] Deleting a service that has existing applications does not delete or corrupt those applications (`service_id` becomes `NULL`, `service_name_snapshot` remains intact).

---

## 13. SECURITY REQUIREMENTS

**Requirements (MUST):**
1. All SQL queries use PDO prepared statements — no string-concatenated SQL anywhere.
2. All user-supplied content is escaped with `htmlspecialchars()` (or context-appropriate equivalent) on output.
3. CSRF tokens on every state-changing form and AJAX request (Admin Panel, User Panel, and Homepage contact form alike).
4. File uploads (both web-panel `multipart/form-data` and API base64, Section 10.4):
   - Images: `jpg`, `jpeg`, `png`, `webp`, `gif` — max size per `site_settings.max_upload_image_mb` (default 10 MB).
   - Documents/attachments: `pdf`, `doc`, `docx`, `xls`, `xlsx`, `zip` — max size per `site_settings.max_upload_doc_mb` (default 20 MB).
   - MIME-type is verified server-side (not trusted from the client-supplied extension or header alone).
   - Uploaded files are stored outside the web root, or in a directory configured to never execute scripts.
   - PHP's own `upload_max_filesize`/`post_max_size` must be configured to accommodate the largest configured limit above.
5. Brute-force protection: admin login limited to 5 attempts per 15 minutes per IP, then temporary lockout with backoff.
6. `config.php` (and any credentials file) is inaccessible via direct browser request — enforced via server config (e.g., `.htaccess` deny rule) or by placing it outside the public web root.

**Acceptance Criteria:**
- [ ] A basic SQL injection payload against any form field fails safely (no error leakage, no data exposure).
- [ ] A basic XSS payload submitted through any text field (chat message, application description, blog content) renders as inert text, never executes.
- [ ] Repeated rapid admin login attempts are throttled/blocked per the stated policy.
- [ ] Requesting `config.php` directly via URL returns `403`/`404`, never file contents.
- [ ] Uploading a disallowed file type or an oversized file is rejected with a clear Uzbek-language error, both via the web panels and via `/api/chat` (Section 10.4).

---

## 14. ADDITIONAL REQUIREMENTS

**Requirements (MUST):**
- **SEO preserved and editable:** meta tags, Open Graph tags, `robots.txt`, `sitemap.xml`, all carried over from the current site and editable via `site_settings` (Section 7.2.A item 6).
- **Performance:** lazy-loaded images, WebP conversion where the server supports it, minified CSS/JS (manually or via a simple PHP-based minifier — no external build tool, per Section 4).
- **Custom 404 and error pages**, styled consistently with the rest of the site (Section 6), with useful links back into the site.
- **Privacy Policy and Terms of Use pages** — at minimum a reasonable baseline template, providing minimal legal grounding for Google sign-in.
- **Backup:** an admin-panel action producing a full SQL dump on demand (required), plus an optional JSON export of content tables (Section 1, Resolution 6).
- **Audit log:** every admin create/edit/delete/status-change action is recorded in `audit_log` (Section 12): who, when, what changed.

**Acceptance Criteria:**
- [ ] SEO fields edited in the Admin Panel are reflected in the rendered `<head>` of the relevant public pages.
- [ ] `robots.txt` and `sitemap.xml` are reachable at their standard root-relative URLs.
- [ ] Triggering the backup action produces a downloadable SQL file that restores a working copy of the database when re-imported.
- [ ] Every admin action listed in Section 7.2 produces a corresponding `audit_log` row.

---

## 15. TESTING & QA CHECKLIST

Run at the end of every phase in Section 5 (not only once at the end of the project):

- [ ] All Section 2.3 known-defect fixes (D1, D2, D3) verified individually.
- [ ] All Section 6 design acceptance criteria pass, in both themes, across all required breakpoints.
- [ ] All Section 7.1–7.3 acceptance criteria pass, including the Phase-4/Phase-7 split verification.
- [ ] Section 8 auth flows tested for a brand-new Google account, a returning one, and admin login independent of Google.
- [ ] Section 9 login-animation timing and prefetch behavior verified.
- [ ] Section 10 API tested with valid token, missing token, expired token, and cross-user (ownership-violating) token, for every endpoint.
- [ ] Section 10.5 rate limits verified to actually trigger at their stated thresholds.
- [ ] Section 11 installer tested on a genuinely clean database, confirmed to lock itself afterward.
- [ ] Section 13 security checks (SQLi, XSS, CSRF, brute-force, config exposure, upload validation) each individually attempted and confirmed blocked.
- [ ] Every string visible in the rendered UI reviewed and confirmed Uzbek-language only (Section 6.6).
- [ ] Every conflict resolution in Section 1 spot-checked against the actual build (e.g., confirm there is truly one chat thread per user, confirm stat override precedence works both ways).

---

## 16. FINAL AUDIT

Before declaring the project complete, reconfirm all of the following against the actual running build (not against memory of having built it):

- [ ] Every requirement in Sections 2–14 is implemented and passes its stated Acceptance Criteria.
- [ ] The four business goals in Section 2.2 (G1–G4) all function end-to-end.
- [ ] No requirement from this specification was dropped, weakened, or silently reinterpreted.
- [ ] No functionality outside this specification was added without an explicit flag explaining why it was necessary to satisfy an existing requirement (Section 3, Rule 7).
- [ ] Every item in Section 15 is checked off with zero open failures.
- [ ] Every resolution in Section 1 is verifiably true in the shipped build.

---

## 17. OUT OF SCOPE

Explicitly not required — do not build these, even if it seems like a natural addition:

- Additional languages or a language switcher.
- Any JS/CSS framework, build pipeline, or npm/Composer runtime dependency for the site to function.
- Any change to the four core business goals (Section 2.2) or the overall business model.
- Third-party analytics or advertising scripts.
- A role/permission system for admins (all admin accounts have equal full access, per Section 12).
- A self-service "forgot password" flow for admins (Section 8).
- Per-application chat threads (explicitly resolved against in Section 1, Resolution 1).
