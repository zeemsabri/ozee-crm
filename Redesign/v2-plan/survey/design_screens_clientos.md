# OZee CRM — Client OS screen notes (§5.x companion to `design_guide.md`)

Source: `/home/claude/design/CRM Restructure/` (Claude Design export, 2 Sep 2026). Companion to `/home/claude/survey/design_guide.md` §0–§4. Section numbers continue the guide's §5 plan. Every value is transcribed from the mock HTML/fixture unless marked *inferred*. Fixture people/firms are placeholder personas ("Harper & Vale" law firm / a Thornlie dental practice) — they are demo data, not real records, and are quoted only where the copy pattern matters.

Contents (in file order): 5.7 Login · 5.8 Home · 5.9 Announcement · 5.10 Product Plan · 5.11 SEO Reports · 5.12 Signature feature overview (data model, brand/rendering rules) · 5.6 SignatureBlock fragment · 5.13 Signature Builder · 5.14 Signature Studio · 5.15 Signature Suite · 5.16 Inbox (offline) verdict · Consolidated navigation · Cross-cutting issues.

Conventions used below:
- **Shell** = 56px white header + 64px icon rail + scrolling `<main>` (guide §1.12 "App shell").
- **Client OS rail (canonical)** — the set used by Login/Home/Announcement: `Home` "Today" · `Inbox` "Enquiries" · `Calendar` "Calendar" · `Email` "Signatures" · `Globe` "Domain" · `Doc` "OZee". Active item = `bg #eceffa`, `fg var(--ozee-blue)`; resting `fg #676879`. Items 48×48 radius 4, 20px Icon. Note this differs from the internal-CRM rail and from the SEO Reports / Product Plan mocks (both call out below) — see the consolidated navigation note at the end.
- Client OS uses the **OZee identity blue** `var(--ozee-blue)` (#0e2ba4) / `--ozee-blue-deep` hover for primary buttons and active nav (not the working blue #1a73e8 used in the internal CRM). Product Plan and SEO Reports rails are the exception (they use #1a73e8/#d3e4fd). The wash for identity blue is `#eceffa`; amber wash `#fdf3e0`; green wash `var(--ozee-green-wash)`.
- Client OS body type is 16/22–16/24 Figtree (larger than the internal 14/20), h1 Poppins 600 32/40 −0.5px.
- All Client OS buttons are hand-built `<button>`s in the mocks (44px or 36px tall, radius 4, identity blue) rather than the DS `Button` — the DS `Button` is 40/32px working-blue. **Decision for the port:** add a `kind="brand"` / Client OS variant to the Button component (44px, `--ozee-blue`) rather than hand-rolling.

---

## 5.7 `Client OS Login.dc.html` — client sign-in

**Purpose / audience.** Client-portal sign-in for a client's staff (owner, office manager, fee earner). Passwordless-first: email → 6-digit code → optional password set. Social sign-in (Google / Microsoft / LinkedIn). First-time users are walked through setting a password.

**Tweaks props** (`data-props`): `showSocial` (bool, default true, section "Sign-in options"); `firstTimeUser` (bool, default true — when false the code step goes straight to "done" and skips the password step). Preview 1440×900.

### Layout (no app shell — standalone split page)

- Root: `height:100vh; display:flex; font-family:Figtree; color:#323338; background:#fff`.
- **Brand panel (`<aside>`)**: `width:44%; min-width:380px; flex:none; background:var(--ozee-blue); color:#fff; padding:40px; display:flex; flex-direction:column; border-inline-end:6px solid var(--ozee-amber)` (the 6px amber edge is the brand accent strip).
  - Logo: `assets/ozee-logo-full.png` at `width:220px`, `align-self:flex-start`, with `filter:brightness(0) invert(1)` → renders the lockup **solid white** (this is how the mock gets a white-on-blue logo without a white logo file; alt text "OZee Web & Digital Services — your one stop shop").
  - Middle block (`margin:auto 0; padding:32px 0; max-width:460px; gap:20px`):
    - h1 Poppins 600 32/40 −0.5px, white: **"Everything your business runs on, in one login."**
    - p 16/24 Figtree `rgba(255,255,255,0.78)`: **"Leads, bookings, your email signature and the work OZee is doing for you — all in the same place. No password to remember on your first visit."**
    - Three check marks (`marks[]`), each: 20px green disc (`var(--ozee-green)`) with `Icon Check size 12` + 16/22 text `rgba(255,255,255,0.9)`:
      1. "Every enquiry from your website, in one list"
      2. "Bookings straight onto your real calendar"
      3. "Approve our work without a single email"
  - Footer line 14/20 `rgba(255,255,255,0.6)`: **"Trouble signing in? Call OZee on +61 456 639 389."** (business contact number from the brand config; keep it a config value.)
- **Form panel (`<main>`)**: `flex:1; display:flex; align-items:center; justify-content:center; padding:40px; background:#f6f7fb; overflow:auto`. Inner column `width:100%; max-width:420px; gap:20px`.
  - **Step indicator** (3 segments, `gap:8px`): each = 3px bar (radius 2) + label 600 12/16. Bar tone: completed `var(--ozee-green)`, current `var(--ozee-blue)`, upcoming `#e6e9ef`; label colour `#323338` for current/done, `#676879` upcoming. Labels: **"Your email" · "Verify" · "Password"**. Transition `background 150ms`.
  - **Card**: white, 1px `#d0d4e4`, radius 8, `padding:28px`, `gap:20px`. Holds one of four step bodies.
  - **Footer line** (12/16 grey, centred): `Icon Security 12` + "Your workspace is hosted in Australia. **Privacy** · **Terms**" (links; anchor colour `var(--ozee-blue)`).

### Steps (state machine `step: email | code | password | done`, `via: code | Google | Microsoft | LinkedIn | saved | skipped`)

**1. `email`**
- h2 Poppins 600 24/30 "Sign in"; sub 14/20 grey "Enter your work email and we'll send you a six-digit code."
- Label 600 14/20 "Work email"; `<input type=email autocomplete=email placeholder="you@yourfirm.com.au">` height 40, padding 0 12, border 1px `#c3c6d4` (error → `#d83a52`), radius 4, 16/22 text, `transition:border-color 100ms`. Enter key submits.
- Validation error (12/16 `#d83a52`): **"Enter a valid email address, like you@yourfirm.com.au"** (regex `^[^\s@]+@[^\s@]+\.[^\s@]+$`).
- Primary button 44px full-width, `var(--ozee-blue)` → hover `--ozee-blue-deep`, press scale .95, 600 16/22 white: **"Email me a code"**.
- If `showSocial`: divider row (1px `#e6e9ef` lines + 12/16 grey **"or use an account you already have"**) then three 44px outlined buttons (1px `#c3c6d4`, white, hover `#f6f7fb`, 600 14/20) with 18px mark + label: **"Continue with Google"** (inline Google "G" SVG, 4-colour), **"Continue with Microsoft"** (4-square SVG #F25022/#7FBA00/#00A4EF/#FFB900), **"Continue with LinkedIn"** (`_ds/.../assets/social-linkedin.svg`). Social click → `done` with provider-specific copy.

**2. `code`**
- Back link (no border, 600 14/20 grey, hover dark): `Icon NavigationChevronLeft 14` + **"Use a different email"**.
- h2 "Check your email"; sub: "We sent a six-digit code to **{email}**. It expires in 10 minutes."
- Six inputs (`inputmode=numeric maxlength=1`, `aria-label="Digit n"`) `flex:1; height:56px; text-align:center; font 600 24/30`, border `#c3c6d4` → filled `var(--ozee-blue)` → error `#d83a52`. Behaviour: typing advances focus; paste of a string fills all cells from cell 0 (`onPaste` preventDefault, strips non-digits); Backspace on an empty cell moves focus back; Enter verifies. First cell auto-focused 60ms after step entry.
- Error 12/16 red: **"Enter all six digits."**
- Primary 44px **"Sign in"**.
- Resend row 14/20 grey centred: "Didn't get it?" + either link-button **"Send it again"** (600, `--ozee-blue`) or countdown text **"Send it again in {n}s"** (30-second timer started on send and on resend).
- Verify → `password` step if `firstTimeUser` else `done` (via `code`).

**3. `password`** (first-time users only)
- h2 "Set a password"; sub "You're in. Pick a password so next time you can skip the code — or skip this and keep using codes."
- Label "New password"; composite field: bordered wrapper (1px `#c3c6d4`, radius 4) containing borderless input `height:38px placeholder="At least 8 characters"` + 38×38 icon button `aria-label="Show or hide password"` toggling `Icon Show` / `Icon Hide` (16px) and `type=password|text`.
- Rule list (14/20; met → `var(--ozee-green)` + `Icon Check 14`; unmet → grey + 12px hollow circle border `#c3c6d4`): **"At least 8 characters"**, **"Contains a number"**.
- Buttons stacked (gap 8): primary 44px **"Save password"** (only proceeds when both rules met); secondary 40px outlined **"Skip — email me a code each time"**.

**4. `done`**
- 48px circle `var(--ozee-green-wash)`/`var(--ozee-green)` with `Icon Completed 24`, then h2 + grey body from `doneCopy[via]`:
  - `code`: "You're signed in" / "Taking you to your workspace — leads, calendar and the work OZee is doing for you."
  - `Google`: "Signed in with Google" / "We matched your Google account to your workspace. No password needed."
  - `Microsoft`: "Signed in with Microsoft" / "We matched your Microsoft 365 account to your workspace. No password needed."
  - `LinkedIn`: "Signed in with LinkedIn" / "We matched your LinkedIn account to your workspace. No password needed."
  - `saved`: "Password saved" / "Next time you can sign in with your email and password, or ask for a code."
  - `skipped`: "You're signed in" / "No password set — we'll email you a code each time you sign in."
- Outlined 40px button **"Run the flow again"** (mock-only reset; in product this step redirects to Home).

### DS components used
`Icon` (Check, NavigationChevronLeft, Show, Hide, Completed, Security). Nothing else from the DS — inputs and buttons are hand-built. *Port note:* use `TextField` for email/password (40px) and a new `CodeInput` component for the six cells; the step indicator is a candidate for `MultiStepIndicator` (guide §1.10 lists it for "Onboarding, signature builder") but the mock's 3px-bar style is simpler — keep the mock style as a small `StepBars` component reused by Signature Builder.

### States
- Error: email invalid; code incomplete (design doesn't show "wrong code"/"expired" — *inferred*: reuse the same red 12/16 line: "That code isn't right — check the email or send a new one." / "That code has expired — send a new one.").
- Disabled/loading: not designed (*inferred*: primary button shows `Loader` while sending; keep label).
- Resend cooldown (30s).
- Dark mode: not designed; the brand panel is fixed navy regardless.
- No "existing user with password" path is drawn — the flow always starts with email→code. *Open question:* where does a returning user type their password? (Decision 1 in the Product Plan also questions whether clients get their own login at all.)

### Responsive (*inferred* from geometry)
Brand panel `min-width:380px` means below ~800px the layout should stack: brand panel becomes a top band (logo + h1 only) and the form column takes full width; the checkmark list can drop. Form column already fluid (`max-width:420px`).

### Backend implied
- `POST /client/auth/code` (email) → sends OTP (6 digits, 10-minute expiry, resend throttle 30s).
- `POST /client/auth/verify` (email, code) → session; flag `first_login` drives password step.
- `POST /client/auth/password` (password ≥8 chars incl. a digit) / skip.
- OAuth: Google, Microsoft 365, LinkedIn — account matched to a client workspace by email domain/invite (*inferred*).
- Client user entity: `email`, `name`, `password?` (nullable), `oauth_provider?`, `client_id`, `role` (owner / office manager / fee earner — see Home).
- Data residency copy "hosted in Australia" → deploy region commitment.

---

## 5.8 `Client OS Home.dc.html` — client home ("Today")

**Purpose / audience.** Landing screen after login. Answers "what needs me today?" per role. Three role variants are switchable via a dashed "Preview as" bar (design-only device; in product the role comes from the signed-in user). Roles: **Owner**, **Office manager**, **Fee earner**.

### Shell
- Header 56px: `ozee-logo-sm.png` h26 · 1×22 divider `#e6e9ef` · client name 600 14/20 ("Harper & Vale" — the **client's** firm name, not OZee) · right: `IconButton Search small` · `IconButton Notifications small` · `Avatar text={userName} size=medium`.
- Rail 64px: canonical Client OS rail (Home "Today" active `#eceffa`/`--ozee-blue`, Inbox "Enquiries", Calendar "Calendar", Email "Signatures", Globe "Domain", Doc "OZee"). `title` attr carries the label (no visible text).
- Main: `overflow:auto; display:flex; justify-content:center; align-items:flex-start`; column `width:100%; max-width:780px; padding:36px 24px 64px; gap:24px`.

### Regions (top → bottom)
1. **"Preview as" bar** (mock-only): white, `border:1px dashed #c3c6d4`, radius 4, padding 8 12; eyebrow "PREVIEW AS" + 28px pill buttons per role (selected: border/text `--ozee-blue`, bg `#eceffa`). Do not ship.
2. **Greeting**: h1 Poppins 600 32/40 `{greeting}` ("Good morning, {first name}"); sub 16/22 grey `{summaryLine}` = `"{n} things need you — Tuesday 26 August."` / `"1 thing needs you — …"` / `"Nothing needs you — Tuesday 26 August."` (AU date, day-month, no ordinal).
3. **Hero stat card** (accent strip pattern, guide §1.12): white card radius 8, `display:flex; overflow:hidden`; 4px `var(--ozee-amber)` strip; content `padding:20px 24px; gap:24px; flex-wrap`. Left: eyebrow `{hero.label}` · value Poppins 700 32/40 `{hero.value}` · note 14/20 grey `{hero.note}`. Right: two mini stats (`{n}` Poppins 600 18/24 + 12/16 grey label).
4. **"Needs you today" / "Yours today"** list (`itemsHeading` eyebrow) — one **action card** per item: white card radius 8, 4px left strip in `it.tone`, body `padding:18px 20px; gap:14px`. Row: 36px circle disc (`it.wash` bg, `it.tone` fg, `Icon {it.icon} 18`) + title Poppins 600 18/24 + `{when}` 12/16 grey (same baseline row, wraps) + body 16/24 grey. Actions row indented `padding-inline-start:48px`: primary 36px identity-blue button `{it.primary}` + secondary 36px outlined `{it.secondary}`. Either action removes the card (mock: adds to `done[]`).
5. **Empty state** (when no items): white card padding 32 centred; 56px green disc + `Icon Completed 24`; title Poppins 600 18/24 **"You're all clear"**; body 16/24 grey max 400 **"Nothing needs you right now. New enquiries appear here the second they come in — you don't have to go looking."**; outlined 36px button "Bring today's items back" (mock-only reset).
6. **"Ticking along without you"** (eyebrow) — 3-col grid `gap:12px` of quiet stat cards: white card padding 16; number Poppins 700 24/30 + label 14/20 grey.
7. **Agency card**: white card `padding:16px 18px; gap:14px; flex-wrap`; `ozee-logo-sm.png` h22 · title 600 14/20 `{agency.title}` + body 14/20 grey `{agency.body}` · link-text 600 14/20 `--ozee-blue` **"See our work"**.
8. **Jump chips** (4): 40px white chips, 1px `#d0d4e4`, radius 4, `padding:0 14px`, 600 14/20, blue 16px icon: `Inbox` **"All enquiries"**, `Calendar` **"Your calendar"**, `Email` **"Email signatures"**, `Doc` **"Work OZee is doing"**. Hover `#f6f7fb`.

### Fixture data by role (pattern, not to be shipped verbatim)

| Role | Hero | Items (icon/tone · title · primary / secondary) | Quiet stats | Agency card |
|---|---|---|---|---|
| **Owner** | "New work won this quarter" · **$48,200** · "From 23 website enquiries. Up $11,400 on last quarter." · side: 23 enquiries / 1m 20s average reply | Inbox/blue · "New enquiry — {name}" ("9 minutes ago"; body mentions matter, est. value, and that the auto-text went out "nine seconds after submitting the form") · **Call {name}** / **Assign to {staff}**; Doc/green · "OZee needs your approval" ("Waiting 2 days"; "The new services page layout is ready for a look before we build it.") · **Review and approve** / **Ask a question** | 7 "enquiries this week, all answered under 2 minutes"; 4 "consultations booked for next week"; 12 "staff signatures live and correct" | "OZee is working on 3 things for you" / "Services page redesign, August SEO retainer, family law ads." |
| **Office manager** | same hero label/value · note "Nothing has gone unanswered. Two enquiries still need assigning." · side: 2 unassigned / 3 to confirm | Inbox/amber · "2 enquiries have nobody on them" ("Oldest is 40 minutes") · **Assign both** / **Open enquiries**; Calendar/blue · "{name}'s consultation isn't confirmed" ("Tomorrow, 10:00 am"; "She answered all three intake questions when she booked. Room and fee earner still blank.") · **Confirm booking** / **View intake notes**; Email/green · "New paralegal has no email signature" ("Started Monday"; "…emails are going out unbranded. It takes about a minute to fix.") · **Create her signature** / **Later**; Globe/amber · "{domain} is due for renewal" ("Renews in 24 days"; "OZee handles this, but you can renew early so nothing lapses.") · **Renew now** / **Leave it to OZee** | 7 enquiries…; 0 "missed calls without a follow-up text"; 12 signatures live | title same / "Ellen has one approval waiting on the services page." |
| **Fee earner** | "Your next appointment" · **11:00 am** · "Discovery call with {name} — intake notes are already attached." · side: 3 appointments / 2 enquiries yours | Calendar/blue · "Discovery call — {name}" ("In 2 hours") · **Open intake notes** / **Reschedule**; Inbox/amber · "{name} is waiting on your call" ("Assigned 20 minutes ago") · **Call {name}** / **Send booking link** | 3 "appointments in your calendar today"; 2 "enquiries assigned to you this week"; "Thu" "your next free consultation slot" | "OZee is working on 3 things for the firm" / "Nothing needs you — Ellen approves this work." |

Tone/wash mapping: blue `var(--ozee-blue)`/`#eceffa` (enquiry, appointment); amber `var(--ozee-amber)`/`#fdf3e0` (unassigned, waiting, renewal); green `var(--ozee-green)`/`var(--ozee-green-wash)` (approval, signature).

### Item types implied (feed model)
`new_enquiry`, `approval_request` (agency hub), `unassigned_enquiries` (aggregate), `unconfirmed_booking`, `missing_signature` (staff member), `domain_renewal`, `upcoming_appointment`, `assigned_enquiry`. Each has: `icon`, `tone`, `when` (relative or absolute), `title`, `body`, `primary`/`secondary` action (deep link or mutation), `role_visibility`.

### DS components used
`IconButton` (Search, Notifications), `Avatar`, `Icon` (Home, Inbox, Calendar, Email, Globe, Doc, Completed). Everything else hand-built.

### States
Populated / empty ("You're all clear"). No loading/error designed (*inferred*: skeleton the hero + 2 cards). No dark mode.

### Responsive
Column is fluid to 780; quiet-stats grid is fixed `repeat(3,1fr)` (*inferred*: 1 col under ~520px); hero uses `flex-wrap` so side stats drop below; action buttons wrap.

### Backend implied
- `GET /client/home` → `{ user{name, role}, greeting, date, hero{label,value,note,side[]}, items[], quiet[], agency{title, body, link}, jumps[] }` computed per role.
- Aggregates: quarter revenue won (from enquiry→job values), enquiries count, average first-reply time, bookings next week, signatures live count, missed calls without SMS follow-up, appointments today, next free slot.
- Actions: assign enquiry, confirm booking, create signature (deep link to Signature Builder), renew domain, open intake notes, reschedule, send booking link, review/approve (agency hub), ask a question.
- Time-of-day greeting; AU locale dates.

---

## 5.9 `Client OS Announcement.dc.html` — notice popup (feature / promotion / approval)

**Purpose.** One modal "popup" anatomy with three jobs: **feature announcement**, **promotion/offer**, **something waiting on the client** (approval). Shown over Home. Page copy states the rule: *"One popup, three jobs — a feature announcement, a promotion, or something of ours waiting on the client. Same anatomy every time, so it never feels like a different app interrupting them."*

### Shell
Same Client OS shell as Home (header: logo, divider, "Harper & Vale", `IconButton Notifications`, `Avatar`; canonical rail with Home active). Main column 780px, `padding:36px 24px 64px; gap:20px`. Page body behind the modal is a placeholder: dashed "SHOW" switcher bar (`kindTabs`: "New feature" / "Promotion" / "Needs approval" — mock-only) + placeholder h1 in `#c3c6d4` "Home sits behind here" + the explanatory sentence above. Root has `position:relative` so the overlay is absolute within it.

### Modal anatomy
- Backdrop: `position:absolute; inset:0; background:rgba(41,47,76,0.7); display:flex; align-items:flex-start; justify-content:center; padding:32px; overflow:auto`.
- Dialog (`role=dialog aria-modal=true`): `margin:auto 0; width:100%; max-width:{card.width}` (560px feature/promo, 520px approval); white, radius **16**, `box-shadow:var(--box-shadow-large)`, `overflow:hidden`, entrance `omPop 150ms cubic-bezier(0,0,.35,1)` (`from{opacity:0;transform:translateY(8px) scale(.98)}`).
- Close button: absolute top-right 12px, 32×32 radius 4, `Icon Close 16`; bg/fg per kind (`rgba(255,255,255,0.9)`/`#323338` over media; `transparent`/`#676879` for the band-only kind); hover `rgba(103,104,121,0.1)`; `aria-label="Close"`.
- Optional **media** (feature, promo): 200px tall block in `card.tone` containing `<image-slot id="announce-media" shape="rect" placeholder="Drop the feature screenshot or promo image">` → in product a real `image_url` (screenshot/promo image, `object-fit:cover`).
- Optional **band** (approval): 4px strip in `card.tone` at top instead of media.
- Body `padding:28px; gap:16px`:
  1. Eyebrow row: filled pill (`card.tone` bg, white 600 12/16, `padding:3px 10px`, radius 4) with 12px `Icon {card.icon}` + `{eyebrow}`; then `{meta}` 12/16 grey.
  2. h2 Poppins 600 24/30 `{title}`; p 16/24 grey `{body}`.
  3. Optional **points** list: 18px circle (`card.wash` bg, `card.tone` fg) with `Icon Check 11` + 16/22 text.
  4. Optional **detail** table (approval): bordered radius-8 box; rows `padding:10px 14px; border-bottom:1px solid #e6e9ef`; key 120px 14/20 grey + value 600 14/20.
  5. Optional **offer** block (promo): `card.wash` bg radius 8 padding 16; `{offerValue}` Poppins 700 32/40 in `card.tone` + `{offerLabel}` 12/16 grey + `{offerNote}` 14/20.
  6. Actions: primary 44px identity-blue `{primary}` + secondary 44px outlined `{secondary}` (`padding:0 20px`, 600 16/22). Both close the modal in the mock.
  7. Footnote: hairline `#e6e9ef` top, 12/16 grey `{footnote}`.
- When closed the mock shows a floating "Show the popup again" button bottom-right (mock-only; `box-shadow:var(--box-shadow-medium)`).

### The three kinds (fixture — copy patterns to keep)

| Kind | tone / wash / icon | width | blocks | eyebrow · meta | title | body | list / extras | primary / secondary | footnote |
|---|---|---|---|---|---|---|---|---|---|
| `feature` "New feature" | `--ozee-blue` / `#eceffa` / `Bolt` | 560 | media, points | "New feature" · "Live now for {client}" | "Your booking link now collects intake notes" | "When someone books a consultation they answer up to three questions first, so you walk in already knowing the matter." | "Set your questions once, per appointment type" · "Answers land on the booking and in the contact's history" · "Nothing to install — it's already on your website form" | **Set up my questions** / **Not right now** | "You'll only see this once. Everything new also lives under What's new." |
| `promo` "Promotion" | `--ozee-amber` / `#fdf3e0` / `Announcement` | 560 | media, points, offer | "Offer" · "Ends Friday 5 September" | "Two months of Google Ads management, half price" | "August is quiet for family law enquiries and cheap to bid on. We'll run and manage a campaign for the next two months at half our usual fee." | points: "We write the ads, you approve them once" · "Cost per lead reported on your home screen" · "Cancel after the two months with no exit fee"; offer: **50% off** / "ad management, 2 months" / "$480 instead of $960. Ad spend is separate and stays yours to set." | **I'm interested** / **No thanks** | "Clicking through starts a conversation — nothing is charged until you approve a quote." |
| `approval` "Needs approval" | `--ozee-green` / `--ozee-green-wash` / `Doc` | 520 | band, detail | "Waiting on you" · "Sent 2 days ago" | "Your new services page is ready for a look" | "Have a read before we build it. If something's off, tell us here rather than by email — it keeps the whole thread in one place." | detail rows: What = "Services page layout, round 2"; From = "{designer}, OZee design"; Waiting since = "Sunday 24 August"; Holds up = "Build starts once approved" | **Review and approve** / **Ask a question** | "Approving doesn't lock anything in — you get one more look before it goes live." |

### DS components used
`IconButton`, `Avatar`, `Icon` (Close, Check, Bolt, Announcement, Doc + rail icons). `image-slot` (design scaffold). *Port:* use the DS `Modal` (16px radius, large shadow, navy backdrop — matches) with a custom body.

### Interactions / states
Open on Home load when an unseen announcement exists; close via X / either button; "seen once" persistence per user (footnote copy). Approval kind should deep-link to the agency-hub approval. Promo primary "starts a conversation" (creates an enquiry/thread to OZee). A "What's new" page is referenced but not designed.

### Backend implied
`announcements` entity: `kind (feature|promo|approval)`, `title`, `body`, `eyebrow`, `meta` (or `ends_at`/`sent_at`), `image_url?`, `points[]?`, `detail[]?` (key/value), `offer{value,label,note}?`, `primary_label`, `primary_action`, `secondary_label`, `footnote`, `audience` (client / segment / all), `show_once`, plus per-user `announcement_views`. Approval kind is generated from an `approval_request` rather than authored.

---

## 5.10 `Client OS Product Plan.dc.html` — the Client OS product-plan document

**Purpose / audience.** Internal (OZee) planning document rendered as a page: defines what "Client OS" is, why, the delivery roadmap, the module catalogue, target verticals and open decisions. **This is the product definition** — everything in §5.7–5.16 is a screen of it. Transcribed in full below.

### Shell (this page uses the *internal* chrome, not the client rail)
- Header 56px `gap:12px`: `ozee-logo-sm.png` h26 · "Client OS" 600 14/20 grey · pill tag "Product plan" (12/16 grey, 1px `#d0d4e4`, radius 4, `padding:1px 6px`) · right: `IconButton Notifications small` + `Avatar text="Zeeshan Sabri" size=medium` (OZee staff).
- Rail 64px, items **48×44** (note: 44 not 48 tall here), working-blue active (`#d3e4fd`/`#1a73e8`): `Home` "My work", `Board` "Plan" (**active**), `Inbox` "Inbox", `Calendar` "Schedule", `CheckList` "Tasks", `Chart` "Reports", `Settings` "Admin".
- Main `overflow:auto`. **Sticky-style page header band**: white, `padding:20px 32px 0; border-bottom:1px solid #d0d4e4`: h1 Poppins 600 32/40 **"Client OS — product plan"**; sub 16/22 grey max 820: *"A simple workspace we give every client after their website goes live. They run their day in it; we run their marketing from it. One-off builds become monthly retainers."*; right: `Button kind=secondary size=small` **"Export plan"** + `Button size=small` **"Design the first screen"**. Underline tabs (`padding:0 14px 10px; border-bottom:2px`): **Plan** (active `#1a73e8`) · Screens · Data model · Pricing (the other three tabs are not designed).
- Content `padding:24px 32px 64px; gap:32px; max-width:1440px` (full-width internal layout, not the 780 column).
- Tweaks prop: `showDecisions` (bool, default true, section "Content").

### Section A — "The bet" + objectives (grid `1.55fr 1fr`)
Card "THE BET" (eyebrow): lead Poppins 600 24/32 — **"If the client's leads, calendar, domain and email signature all live with us, the retainer stops being a line item they can cut."** Body 16/24 grey — **"It ships as its own product — separate login, its own name — but it looks and behaves like the OZee CRM our team already uses, so there is one design language and one set of parts to build with."** Bottom stat row (Poppins 700 24/30 + 14/20 grey): **5** "things in the first release" · **3** "months to that release" · **7** "modules over the full plan" · **1** "login for the client". (Note: the catalogue below actually lists **nine** modules and its own caption says "Nine modules" — the "7" stat is stale; flag.)

Objective cards (3, stacked, 32px icon disc `#f0f6ff`/`#1a73e8`):
1. `Chart` **Lift lifetime value** — "Project fees become monthly retainers — SEO, social, ads and support."
2. `Globe` **Hold the sticky assets** — "Domains, DNS, hosted signature images and lead history all sit with us."
3. `Board` **One place to work** — "Their business and our agency work share a single login and one screen."

### Section B — "A day in it — Harper & Vale, a three-partner law firm"
Sub: "No training, no manual. This is the whole product in four moments." 4-col card grid (time eyebrow · what 600 16/22 · detail 14/20 grey · module link 600 12/16 blue with 14px icon):
1. **8:40 am** — "A signature that is always right" — "New paralegal starts. The office manager adds her once — correct branding, headshot and booking link, on every email she sends." → `Email` **Signature builder**
2. **10:15 am** — "An enquiry answers itself" — "Someone fills in the contact form. They get a text back in nine seconds with a link to book. The partner sees it in the inbox." → `Inbox` **Leads & inbox**
3. **1:30 pm** — "No back-and-forth booking" — "The prospect picks a Thursday slot straight off the partner's real calendar and answers three intake questions while booking." → `Calendar` **Scheduling**
4. **4:00 pm** — "Our work, visible" — "The new site's wireframe is waiting for approval in the same window. Two clicks, approved, back to billable work." → `Doc` **Agency hub**

### Section C — Delivery roadmap
Header: h2 "Delivery roadmap" + caption `filterCaption` ("Four phases, roughly a year end to end." / "Showing {phase name} only.") + filter pills right-aligned: **All phases · Phase 1 · Phase 2 · Phase 3 · Phase 4** (selected `#d3e4fd`/`#1a73e8` border `#1a73e8`). Grid = `repeat(n,1fr)` or `minmax(0,520px)` when a single phase is selected. Phase card: header row (3×26 tone bar, tag eyebrow in tone, name 600 16/22, window 12/16 grey right), focus line 14/20 grey, then item rows (min-height 40, 8px tone square, label 14/20, optional amber `Bolt` 14 "Hard to walk away from" lock icon, optional blue "Start here" pill). Legend below: amber Bolt + *"Cancelling breaks something the client uses every day."*

Phase tones: P1 `#1a73e8`, P2 `#037f4c`, P3 `#784bd1`, P4 `#fdab3d`.

| Phase | Name | Window | Focus | Items (⚡ = lock-in) |
|---|---|---|---|---|
| **Phase 1** | Foundation & lock-in | Months 1–3 | "Useful on day one, and quietly hard to leave by day thirty." | ⚡ **Dynamic email signature builder, assets hosted by us** — tagged **"Start here"**; Website lead capture with instant SMS reply; Two-way Google and Outlook calendar sync; ⚡ Domain and DNS management via registrar API; ⚡ Agency hub — wireframe approvals, assets, tickets |
| **Phase 2** | Operations & ROI | Months 4–6 | "Deepen daily reliance, and put a number on the marketing retainer." | One-click lead to job, assigned to a staff member; ⚡ Two-way Xero invoice sync; Plain-English SEO and ads results dashboard; Automatic Google review requests after a job |
| **Phase 3** | Social & verticals | Months 7–9 | "Coordinate the full agency service, then chase niche markets." | Social calendar with client approval step; ⚡ Their @company.com inbox, inside the platform; QR table ordering for hospitality clients |
| **Phase 4** | Intelligence & scale | Months 10+ | "Automate what the agency currently does by hand." | AI qualifies web-form and message leads; Drag-and-drop follow-up automation builder; Alerts us when a client's lead volume drops |

### Section D — Module catalog
h2 "Module catalog" + caption ("Nine modules, ranked by how hard they are to walk away from." / "Modules landing in {phase}."). Table card, grid `2.1fr 2.7fr 0.8fr 1fr 1.3fr`, header row `#f6f7fb` uppercase eyebrow: **Module · What it does · Phase · Lock-in · Depends on**. Module cell = 28px icon disc `#f0f6ff`/`#1a73e8` + name 600 14/20; phase = filled pill in phase tone ("P1"…); lock-in text 600 14/20 (`#00854d` when High, grey otherwise).

| # | Module (icon) | What it does | Phase | Lock-in | Depends on |
|---|---|---|---|---|---|
| 1 | **Email signature builder** (`Email`) | "Drag-and-drop signatures with merge tags for name, title, phone and booking link. Images and buttons stay on our servers." | P1 | High | Our asset CDN |
| 2 | **Leads & inbox** (`Inbox`) | "Web forms, Google Business, WhatsApp and SMS in one stream, with auto-replies and a full history per contact." | P1 | High | Website, SMS provider |
| 3 | **Scheduling** (`Calendar`) | "Two-way calendar sync, booking links and embedded widgets, staff allocation and automatic reminders." | P1 | Medium | Google / Microsoft APIs |
| 4 | **Domain & DNS** (`Globe`) | "Register domains and edit safe DNS records. We keep primary administrative ownership." | P1 | High | Cloudflare / Namecheap |
| 5 | **Agency hub** (`Doc`) | "Where the client approves wireframes, uploads assets, raises tickets and watches our work progress." | P1 | Medium | Existing CRM data |
| 6 | **Jobs & invoicing** (`CheckList`) | "Turn a lead into a job, assign it to staff, and push the finished job to Xero as a draft invoice." | P2 | High | Leads, Xero |
| 7 | **Results dashboard** (`Chart`) | "Three numbers, in English: visitors, page-one keywords, cost per lead. Plus review requests after every job." | P2 | Low | GA4, Search Console, Ads |
| 8 | **Social planner** (`Announcement`) | "We draft, they approve in one click, it publishes to Facebook, Instagram, LinkedIn and Google Business." | P3 | Medium | Meta / LinkedIn APIs |
| 9 | **Automation & AI** (`Robot`) | "A bot qualifies inbound leads, drips follow-ups, and warns us when a client's numbers slide." | P4 | Medium | Everything above |

Mapping to the Client OS rail: Today (home feed) · Enquiries = Leads & inbox · Calendar = Scheduling · Signatures = Email signature builder · Domain = Domain & DNS · OZee = Agency hub. SEO Reports = Results dashboard (P2) — already designed (§5.11).

### Section E — "Who we sell it to"
Sub: "Same core product, one wedge feature each. Professional services first." 3 cards, 4px tone band top, name Poppins 600 18/24 (+ blue "First" pill on the first), who 14/20 grey, pitch 16/24, then "WE REPORT ON" list with 6px tone dots.

| Vertical | Who | Pitch | We report on |
|---|---|---|---|
| **Professional services** (First; tone `#1a73e8`, card border blue) | Lawyers, accountants, consultants, financial advisers | "Let prospects book discovery calls straight onto your calendar, collect intake notes upfront, and stop chasing emails to schedule." | Consultation booking rate · Days to onboard a client · Retainer renewal rate |
| **Local home services** (`#037f4c`) | Plumbers, electricians, HVAC, cleaners | "Never lose an emergency job to a competitor. Capture the lead, text back instantly, dispatch a tech, invoice from Xero." | Lead response under 2 minutes · Missed-call conversion · Reviews generated |
| **Restaurants & hospitality** (`#784bd1`) | Cafés, restaurants, small venues | "Cut third-party commission with direct QR ordering, and turn one-time diners into regulars with SMS loyalty campaigns." | Commission-free order volume · Table turn rate · Customer database growth |

### Section F — "Open calls before we draw screens" (`showDecisions`)
Sub: "Each one changes what Phase 1 actually contains." 2-col cards with numbered 24px circle:
1. **"Do clients get their own login, or a guest seat on our CRM?"** — "Separate product means a second auth system and a second permission model to maintain."
2. **"Which registrar do we standardise on?"** — "Cloudflare and Namecheap have very different APIs. Picking one shapes the DNS screen."
3. **"Is the signature builder sold alone, or only inside a retainer?"** — "It is the cheapest thing to build and the stickiest thing we own. It could be the wedge."
4. **"Who pays for SMS?"** — "Instant text replies are the headline feature and a real per-message cost. It needs a line in pricing."

### DS components used
`Button` (secondary small, primary small), `IconButton`, `Avatar`, `Icon` (Chart, Globe, Board, Email, Inbox, Calendar, Doc, CheckList, Announcement, Robot, Bolt + rail).

### Implementation note
Whether this page ships at all is a decision: it is a planning artefact. If kept, it is an internal-only static/CMS page under the internal rail ("Plan"). The **content** above is what matters — it is the scope statement for Client OS. Inconsistencies to resolve: "7 modules" vs nine listed; "5 things in first release" = the five P1 items; roadmap says P1 includes Domain/DNS and Agency hub, but the SEO Reports (P2 dashboard) screen is already designed while the P1 Enquiries, Calendar, Domain and Agency-hub screens are not.

---

## 5.11 `Client OS SEO Reports.dc.html` — SEO & organic growth (overview + monthly report)

**Purpose / audience.** Client-facing results dashboard for the SEO retainer (Product Plan module "Results dashboard"), plus a readable monthly write-up per month with a question/comment loop back to the OZee account person. Plain-English explanations under every metric are part of the design (copy rule: the app explains the number).

**Tweaks prop:** `clientName` (text, default "Harper & Vale"). Fixture content is for a dental practice (keywords, GBP) — i.e. the persona is inconsistent with the header; treat as generic.

### Shell
- Header: logo · divider · `{clientName}` 600 14/20 · right `IconButton Search`, `IconButton Notifications`, `Avatar`.
- Rail (**differs from canonical**; working-blue active `#d3e4fd`/`#1a73e8`): `Home` "My work", `Board` "Projects", `CheckList` "Tasks", `Chart` "SEO reports" (**active**), `Email` "Messages", `Settings` "Settings". *Decision:* re-map to the canonical Client OS rail; SEO reports would sit under "OZee" (agency hub) or gain its own `Chart` "Reports" item.
- Main `overflow:auto`, two views switched by `view: overview | report`.

### View 1 — Overview (`max-width:1120px; margin:0 auto; padding:32px 24px 72px; gap:20px`) — wider than 780 because of the 5-up KPI grid.

**1. Page header** (flex-end, wrap): eyebrow **"SEO & organic growth"**; h1 **"How your search presence is tracking"**; sub 16/22 grey `{headline}` — pattern: *"August 2026 · your sixth month with us. Organic visits are up 18% on July, enquiries hit a new high of 38, and AI assistants now name you in 23 of the 40 questions we track."* Right: **baseline segmented control** (white, 1px `#c3c6d4`, radius 4; 36px segments 600 13/18; selected `#d3e4fd`/`#1a73e8`): **"vs July"** (`mom`) · **"vs Aug 2025"** (`yoy`); then primary 36px identity-blue button with `Icon Doc 16`: **"Download PDF"** (toast "Report PDF is downloading").

**2. KPI tiles** — grid `repeat(5, minmax(0,1fr)) gap 12`. Tile: white card padding 16 gap 10; header row = eyebrow label + 24px "Ask about this" icon button (`Icon Email 14`, hover wash) that opens the question modal for that metric; value Poppins 700 28/34 + delta pill (600 12/16, `padding 2px 6px`, wash `#e6f4ec` text `#00854d`); inline SVG sparkline `viewBox 0 0 200 44` (area fill at 0.1 alpha + 2px line in `k.tone`, `vector-effect:non-scaling-stroke`); explain 12/16 grey; baseline note 12/16 grey ("compared with July 2026" / "compared with August 2025").

| Label | Value | MoM | YoY | tone | Explain |
|---|---|---|---|---|---|
| Organic visits | 4,820 | +18% | +143% | `#1a73e8` | "People who found you through an unpaid Google result." |
| Clicks from search | 2,140 | +11% | +161% | `#00854d` | "Times someone chose your listing out of the results page." |
| Enquiries | 38 | +31% | +322% | `#f8a100` | "Form fills and calls that started with an organic visit." |
| Terms on page one | 27 | +6 | +21 | `#579bfc` | "Search terms where you rank in the top 10 results." |
| AI answer share | 58% | +13pts | +51pts | `#a25ddc` | "Of 40 questions we track, the share where an AI assistant names you." |

Each carries a 12-month series (Sep→Aug) for the sparkline. Delta pill is always green in the fixture; *inferred* negative → `#fdeaed`/`#d83a52`.

**3. "Answer engines & AI search" card** (AEO/GEO) — white card with **3px top border `#a25ddc`** (purple = explanation); title Poppins 600 24/30 + purple pill **"AEO / GEO"** (11/16 uppercase, `#f3ebff`/`#a25ddc`); sub: *"More people now ask an assistant instead of scrolling results. AEO is being the answer; GEO is being the source it cites. We track 40 real questions your customers ask and check who gets named."*; outlined 32px **"Ask about this"** button (target "AI search visibility").
- 4 stat boxes (1px `#e6e9ef` radius 4 padding 12 14; eyebrow · Poppins 700 24/30 value + 600 12/16 delta in tone · 12/16 explain): **AI Overview appearances** 19 (+7) "Google searches where your site was used in the AI answer box." · **Assistant citations** 46 (+18) "Times an AI assistant named or linked you when answering." · **Prompt coverage** 58% (+13pts) "23 of the 40 tracked questions mention you somewhere." · **Visits from AI tools** 164 (+61%) "Sessions referred by ChatGPT, Perplexity, Gemini and friends."
- Two columns (`1fr 1fr gap 24`):
  - **"Where you get cited"**: per engine a row (name 600 14/20 · cited 700 14/20 · "of 40 prompts" 12/16 grey · delta 600 12/16 right 36px) + 8px pill track `#f0f1f5` with purple `#a25ddc` fill `width = cited/40`, `transition:width 400ms cubic-bezier(0,0,.2,1)`. Engines: Google AI Overviews 19 (+7) · ChatGPT Search 11 (+5) · Perplexity 8 (+3) · Gemini 5 (+2) · Copilot 3 (0 → grey delta).
  - **"Prompts we track"**: rows (`padding 9px 0`, hairline `#f0f1f5`) = quoted prompt 14/20 + status pill 600 12/16: **Cited** (`#e6f4ec`/`#00854d`), **Competitor** (`#fdeaed`/`#d83a52`), **Not mentioned** (`#f0f1f5`/`#676879`). Then purple insight wash (`#f3ebff`, `Bolt 16` in `#a25ddc`, 13/18): *"Assistants quote pages that answer a question plainly in the first paragraph. That's why we're rewriting your service pages question-first — it lifts both AI citations and normal rankings."*

**4. "Visibility over 12 months" card** — title Poppins 600 18/24; sub *"Impressions are how often you appeared in Google. Clicks are how often someone chose you. Both rising together means you're showing up more and being picked more."*; legend right (10px squares): **Clicks** `#1a73e8`, **Impressions (thousands)** `#d3e4fd`. Chart: hand-drawn combo — 46px y-axis label column (5 gridline labels, max 2,400 clicks) + SVG `viewBox 0 0 960 232` (`preserveAspectRatio:none`): gridlines `#e6e9ef`; impression bars `#d3e4fd` rx 2 (width 56% of slot); clicks area fill `#1a73e8` α .08 + 2.5px line + 3.5px white points w/ blue stroke; month labels row below (Sep…Aug). **Port:** replace with `<OzeeChart type="bar"+line>` — the wrapper has no combo type, so either extend it (bar + line dual axis) or draw two stacked charts; keep `big:true`. Note dual-axis (impressions in thousands vs clicks) contradicts guide "one series → accent"; the design intent is bars = context, line = the metric.

**5. Two-column block (`1.35fr 1fr`)**
- **"Keyword rankings"** card: sub *"Position 1–3 is the top of page one, where most clicks happen. Anything past 20 is page three or worse."*; "Ask about this" (target "keyword rankings"). **Rank-band bar**: 36px tall segmented bar, widths = count/60: Position 1–3 = 11 (`#00854d`, white text) · Position 4–10 = 16 (`#579bfc`) · Position 11–20 = 14 (`#ffcb00`, dark text) · Past 20 = 19 (`#f0f1f5`, grey text); legend below with 8px squares. **Keyword table** grid `1fr 60px 64px 76px`: header **Search term · Now · Change · Searches/mo**; rows 14/20, position 600, change 600 in green (`#00854d`) or red when it starts with "−" (`#d83a52`), volume grey. 7 fixture terms (e.g. "emergency dentist perth" 3 / +4 / 2,400; "dental implants thornlie" 1 / +2 / 590; "wisdom teeth removal cost" 12 / −1 / 1,300 …). Total tracked terms = 60.
- **"Google Business Profile"** card: sub *"The map listing people see when they search nearby. Calls and direction taps here are as good as an enquiry form."*; 2×2 stat boxes (value Poppins 700 24/30 · label 12/16 · delta 600 12/16 tone): Profile views 3,210 "+22% on July" · Calls from Maps 88 "+14 calls" · Direction taps 142 "+9%" · New reviews 24 "4.8 average" (grey). Blue insight wash (`#f0f6ff`, `Globe 16`): *"Average Maps Pack position **2.4** — you appear in the three-result map block for 14 of 20 tracked local searches."*
- **"Site health"** card: sub *"Core Web Vitals are Google's speed and stability checks. Passing all three protects your rankings."*; per vital: name 600 14/20 · value 700 14/20 in tone · verdict pill (`#e6f4ec`/`#00854d` "Good") · 6px pill track with tone fill (`pct`) · explain 12/16. **Largest Contentful Paint** 1.9s Good 78% "How fast the main content appears. Under 2.5s passes." · **Interaction to Next Paint** 140ms Good 86% "How quickly the page responds to a tap. Under 200ms passes." · **Cumulative Layout Shift** 0.04 Good 92% "How much the page jumps while loading. Under 0.1 passes." (*inferred* other verdicts: "Needs work" amber `#fff8e6`/`#8a5b00`, "Poor" red.)

**6. "What a lead costs you" card** — sub *"Cost per lead is spend divided by enquiries. SEO costs the same each month while the leads keep growing, so the line falls. Ads stop the day you stop paying, so theirs stays flat."*; "Ask about this" (target "cost per lead"). Grid `1fr 320px`: grouped bar chart (SVG `640×188`, 6 months Mar–Aug, two bars per month: SEO `var(--ozee-blue)` and Google Ads `#c3c6d4`, y-axis $0–$160 in 4 gridlines; legend below) + 3 stat boxes: **Cost per lead — SEO** $47 (green) "$1,800 retainer ÷ 38 enquiries" · **Cost per lead — Ads** $138 (dark) "$2,480 ad spend ÷ 18 enquiries" · **Saved this month** $3,458 (identity blue) "What those 38 leads would have cost at the Ads rate". Port → `OzeeChart type="bar"` with two series, unit "$".

**7. "Where these numbers come from" card** — sub *"Every figure on this page is pulled straight from the platform that owns it — nothing is typed in by hand. {syncNote}"* where `syncNote` = "Last full refresh 1 Sep 2026, 6:04 am AWST." / "Refreshing now…"; outlined 32px button `Icon Duplicate 14` **"Refresh data"** (sets `syncing` 1.4s then toast **"Data refreshed from all connected platforms"**). 3-col source tiles (28px icon disc in wash/tone; name 600 14/20 + status pill 11/16 uppercase; feeds 12/16; synced 12/16):

| Source | Icon | Status | Feeds | Synced |
|---|---|---|---|---|
| Google Search Console | Search | Connected (green) | Clicks, impressions, average position, indexing | Synced 2 hours ago |
| Google Analytics 4 | Chart | Connected | Organic sessions, enquiries, AI-referral traffic | Synced 2 hours ago |
| Google Business Profile | Location | Connected | Profile views, calls, direction taps, reviews | Synced 6 hours ago |
| Bing Webmaster Tools | Search | Connected | Bing clicks and impressions, Copilot citations | Synced yesterday |
| Google Ads | Bolt | Connected | Ad spend and conversions for the cost-per-lead split | Synced 2 hours ago |
| AI visibility tracker | Robot | **Beta** (purple `#f3ebff`/`#a25ddc`) | 40 tracked prompts across five assistants | Checked weekly, last Monday |

(Icon `Location` must exist in `assets/icons` — verify; guide warns unknown names render a grey square.)

**8. Two-column block (`1fr 1fr`)**
- **"Work in flight — August"** card: sub "Every hour of your retainer, accounted for." Three groups with 8px tone square + eyebrow + count, items indented with 2px `#f0f1f5` left rule: **Done** (`#00854d`, text grey, 5 items with dates like "4 Aug") · **In progress** (`#f8a100`, text dark, 2 items "due 3 Sep") · **Next up** (`#579bfc`, grey, 3 items "Sep"/"late Sep"). Item examples: "Rewrote the implants service page around "dental implants thornlie"", "Fixed 22 broken internal links found in the crawl", "Added FAQ schema to five service pages", "Building the Canning Vale location page", "Quarterly competitor gap analysis". → these are retainer **tasks** with status/date, shown to the client.
- **"Monthly reports"** card: sub "Open any month to read the full write-up." List rows (button, `padding 12px 8px`, hairline, hover `#f6f7fb`): 32px blue disc `Doc 16` · month 600 14/20 + one-line ellipsised summary 12/16 · tag pill (**New** `#e6f4ec`/`#00854d`, **Read** `#f0f1f5`/`#676879`) · chevron (`NavigationChevronLeft` rotated 180°). Six months Aug→Mar 2026 with summaries like "Best month yet — 38 enquiries, cost per lead down to $47", "Technical clean-up: 118 crawl errors cleared". Click → report view for that month.

### View 2 — Monthly report (`max-width:900px; padding:24px 24px 72px; gap:16px`)
- Toolbar: outlined 32px back button `NavigationChevronLeft 14` **"All reports"**; right primary 32px **"Download PDF"** (`Doc 14`).
- **Report card** (white, radius 8, `padding:32px 40px 36px; gap:28px`) — designed to print/PDF:
  1. Masthead (hairline below): eyebrow **"Monthly SEO report"**, h1 `{month}` ("August 2026"), sub 14/20 grey "Prepared for {clientName} by OZee Web & Digital · {prepared}" ("1 September 2026"); `ozee-logo.png` h40 right.
  2. **"The short version"** — one paragraph 16/26 (`report.summary`): narrative of the month (strongest month, visits >4,800, 38 enquiries up from 29, what drove it, SEO CPL $47 vs Ads $138 "so organic is doing roughly three times the work per dollar").
  3. **"Month on month"** table (grid `1.4fr 1fr 1fr 1fr`; header **Metric · This month · Last month · Change**; metric cell = name 600 + explain 12/16 grey; change 600 in tone). 10 rows: Organic visits (4,820 / 4,090 / +18%) · Clicks from search (2,140 / 1,930 / +11%) · Impressions (96,400 / 88,200 / +9%) · Enquiries (38 / 29 / +31%) · Terms on page one (27 / 21 / +6) · AI Overview appearances (19 / 12 / +7) · Assistant citations (46 / 28 / +18) · Visits from AI tools (164 / 102 / +61%) · Cost per lead ($47 / $62 / −24%, green because lower is better) · Average position (11.4 / 13.1 / +1.7). Explain strings: "Unpaid visits from search engines", "People who chose your listing", "Times you appeared in results", "Forms and calls from organic visits", "Rankings inside the top 10", "Google AI answers that used your site", "ChatGPT, Perplexity, Gemini and Copilot mentions", "Sessions referred by an assistant", "Retainer divided by enquiries", "Where you sit across tracked terms".
  4. **"AI search & answer engines"** + AEO/GEO pill — paragraph (`report.aeo`).
  5. **"What we did"** — check list (green `Check 16`, 16/24), 6 items.
  6. **"Next month"** — list with blue `Calendar 16`, 3 items.
  7. **Contact wash** (`#f0f6ff`, radius 8, padding 16 18): `Avatar text="{account manager}" medium` + "Questions on any of this? Leave a comment and {first name} will answer inside two business days." + primary 32px **"Ask a question"** (target "the {month} report").

### Question modal (shared by every "Ask about this")
`position:fixed; inset:0; rgba(41,47,76,0.7); z-index:40`; card `max-width:460px; radius 16; box-shadow:0 20px 40px rgba(41,47,76,0.25); padding:24px; gap:14px`. Title Poppins 600 18/24 **"Ask about {target}"** (targets: metric label lower-cased, "AI search visibility", "keyword rankings", "cost per lead", "the August 2026 report"); sub *"Your question goes straight to the person doing the work — no ticket, no queue."*; 28px close; `textarea` min-height 110, placeholder **"e.g. Why did impressions jump but clicks stay flat?"**; footer **Cancel** (outlined 36) + **Send question** (primary 36). Send → toast **"Question sent to {name} — you'll hear back within two business days"**. Click on backdrop closes.

### Toast
`position:fixed; bottom:24px; left:50%`, `#323338` bg, white 600 14/20, `Check 16`, radius 4, shadow `0 8px 24px rgba(41,47,76,0.28)`, auto-dismiss 2.6s. (DS `Toast` exists — use it.)

### DS components used
`IconButton`, `Avatar`, `Icon` (Doc, Email, Bolt, Globe, Duplicate, Search, Chart, Location, Robot, Check, Calendar, Close, NavigationChevronLeft + rail). Everything else hand-built; charts are inline SVG (→ `OzeeChart`).

### States
Overview / report; baseline MoM / YoY; syncing (`syncNote`, source "Refreshing…"); toast; modal. Not designed: no data yet (first month), disconnected source (*inferred*: status pill "Disconnected" red + "Reconnect" action), loading, dark.

### Responsive (*inferred*)
KPI grid 5→3→2 cols; two-column blocks stack under ~900px; charts are `preserveAspectRatio:none` SVGs so they squash — OzeeChart handles width. Report card padding reduces to 24.

### Backend implied
- Entities: `seo_reports` (client_id, month, prepared_at, summary, aeo_paragraph, did[], next[], status new/read per user, pdf_url); `seo_metrics_monthly` (client_id, month, organic_visits, clicks, impressions, enquiries, page_one_terms, ai_overview_appearances, assistant_citations, ai_visits, cost_per_lead, avg_position — with prior-month and prior-year for deltas); `tracked_keywords` (term, position, change, volume); `tracked_prompts` (text, status cited/competitor/not_mentioned, per engine); `engine_citations` (engine, cited_count, delta); `gbp_stats`; `core_web_vitals`; `cpl_series` (seo vs ads per month, retainer amount, ad spend); `retainer_tasks` (title, status done/in_progress/next, date); `data_sources` (name, type, status connected/beta/disconnected, feeds, last_synced_at); `report_questions` (client_user, target, text → routed to account manager).
- Endpoints: `GET /client/seo`, `GET /client/seo/reports/{month}`, `GET …/pdf`, `POST /client/seo/refresh`, `POST /client/seo/questions`.
- Integrations: Google Search Console, GA4, Google Business Profile, Bing Webmaster Tools, Google Ads, an "AI visibility tracker" (weekly prompt checks across Google AI Overviews, ChatGPT Search, Perplexity, Gemini, Copilot). PDF rendering of the report card.

---

## 5.12 The email-signature feature — end-to-end (read this before 5.13–5.16)

Product Plan §5.10 makes the **Dynamic email signature builder** the "Start here" module (P1, lock-in High, depends on "Our asset CDN"; open call #3 asks whether it is sold alone as the wedge). Four mocks cover it at three levels of ambition, all tagged **Exploration** in the guide's inventory (i.e. not final):

| Mock | Ambition | Audience | What it adds |
|---|---|---|---|
| `SignatureBlock.dc.html` | Component | Fragment | The rendered signature itself — 4 layouts, props = the data model |
| `Client OS Signature Builder.dc.html` | **Simplest** ("stepped form") | Office manager | Gallery of 4 layouts → one builder page: fixed firm fields, merge tags, accent, 3 toggles, live email preview, "Apply to" staff list |
| `Client OS Signature Suite.dc.html` | **Full product** | OZee staff / office manager / fee earner | A whole "Signatures" app with its own secondary sidebar: Setup wizard, All signatures, Templates, Studio (drag-and-drop canvas), Rules, Disclaimers, Deployment, plus People (Employees) and Workspace (Company details, Brand kit, Media library, My profile) and a New-signature modal |
| `Client OS Signature Studio.dc.html` | **Most advanced editor** | Admin / fee earner | Block palette + layers + inspector (Content / Style / Advanced), animated GIF logo/banner, computed fields, conditions engine, compatibility linting, per-mail-client preview, AI "ask for a change" |

### What a signature is (data model synthesised from all four)
- **Signature template** (`signatures`): `id, client_id, name` ("Partner brand", "Fee earner", "Spring campaign", "Court-safe compact"), `layout` (`classic | photo | stacked | compact`), `status` (`Draft | Live | Waiting to send`), `scope/assignment` (everyone / group / person; "Partners (2 people)"), `accent` colour + `wash`, `font` (Figtree | Arial | Georgia | Verdana — with web-safe fallback), `blocks[]` (ordered, per column, on/off), `show_booking, show_social, show_tagline`, `booking_label` ("Book a 15-minute call"), `booking_url` (`{Booking link}` merge tag or fixed), `tagline/legal line`, `social_accounts[]` (LinkedIn, Facebook, Instagram, X), `icon_shape` (Square/Rounded/Circle), `photo_shape` (Square/Rounded/Circle), `logo_width` (56/72/96px), `banner_campaign_id?`, `disclaimer_id?`.
- **Blocks** (Suite `BLOCKS`, with merge tag names): `logo` COMPANY_LOGO (left col) · `photo` EMPLOYEE_PHOTO (left) · `name` EMPLOYEE_NAME · `title` EMPLOYEE_TITLE · `company` COMPANY_NAME · `details` DETAILS (P/E/W rows) · `social` SOCIAL_ICONS · `cta` CTA_BUTTON · `banner` PROMO_BANNER · `disclaimer` DISCLAIMER. Studio adds: Text, Field, Computed, Contact rows, Divider, Spacer, Image, Animated image, Banner, Accreditations, CTA button, Booking link, QR code, Social icons, Disclaimer, Handwritten.
- **Merge tags** (fill per person at render/send): `{First name}`, `{Full name}`, `{Job title}`, `{Direct line}`, `{Email}`, `{Booking link}` (Builder/Suite); Studio uses `{{ ws.fullName }}`, `{{ ws.title }}`, `{{ ws.department }}`, `{{ ws.phone }}`, `{{ ws.email }}`, `{{ firm.website }}` and per-block `{{ ws.<blockId> }}`. Copy rule: *"These come from each person's profile. Change someone's title and their signature updates by itself."*
- **Fixed for everyone** (company fields): firm/trading name, main phone, website, booking button text; Company details adds legal name, ABN, address, office hours, tagline, offices.
- **Per person** (employee/profile): full name (locked, from directory), job title, pronouns, direct line, mobile, work email (locked), booking link, headshot, handwritten sign-off PNG, department/office code (conditions), seniority.
- **Brand kit**: primary logo, reverse logo (dark mode, swapped automatically), square mark, brand colour (4 preset swatches: Firm navy `#213aa8`, Deep green `#1f9355`, Burgundy `#7e3b8a`, Charcoal `#323338` — washes `#eceffa/#e9f5ee/#f3ecf5/#eef0f4`), font, up to 3 accreditation badges. **All images hosted by OZee** — this is the lock-in ("Images and buttons stay on our servers"; "Hosted for you — no broken images").
- **Rules** (which signature on which mail, first match wins): `when[]` (New email / Reply / Forward), `audience` (External only / Internal only / Any recipient), `group`, `signature`, `side` (Server side / Client side), enabled, schedule window.
- **Disclaimers**: name, scope (department · jurisdiction), locked, wording, applied count; changes audit-logged.
- **Deployment**: mode (Client side / Server side / Both), connections (Google Workspace directory sync + Gmail; Chrome extension for Gmail/Outlook Web; Microsoft 365/Entra ID add-in; Exchange on-prem mail-flow connector), coverage stats.
- **Outputs**: rendered HTML per person (all styles inlined, table-safe), **Copy HTML**, **Send myself a test / Send test email**, **Install** (per mailbox), push to N mailboxes on save, GIF generation for animated blocks, optional QR (vCard).

### Brand / rendering rules for the emitted HTML (from the mocks' own copy)
- Font stack `Figtree, Arial, sans-serif` in the block; Studio compatibility lint: *"Poppins is not web safe … set Georgia or Arial as the declared fallback"*; Brand kit note: *"Poppins is not web-safe in Outlook, so signatures fall back to Arial. Preview both before you publish."*
- Every style value inlined (*"no stylesheet, so it survives every client"*). Email clients only stack — reorder within/between two columns is the only movement (*dragHint*).
- Mobile: single column below 420px, contact rows stack. Classic Outlook (Word engine) shows only GIF frame one → frame one must carry the message.
- Dark mode: reverse logo swaps in; navy logo on transparent fails contrast (2.1:1) → add light plate or reverse logo.
- Compact layout "never breaks in any email client" — recommended as the reply/forward variant.
- Signature width 592px (Studio), preview canvas 720px desktop / 420px mobile (Suite).

---

## 5.6 `SignatureBlock.dc.html` — the signature fragment (component)

**Purpose.** Reusable rendered signature, mounted in the other mocks via `<dc-import name="SignatureBlock" …>`. No shell; preview 520×220. Root `font-family:Figtree,Arial,sans-serif; color:#323338; display:inline-block`.

### Props (= the render model)
`layout` enum `classic | stacked | photo | compact` (default classic) · `fullName` · `jobTitle` · `firmName` · `phone` · `email` · `website` · `accent` (colour; options `#213aa8 #1f9355 #f8a50d #7e3b8a`, default `#213aa8`; falls back to `var(--ozee-blue)`) · `wash` (default `#eceffa`) · `showBooking` (true) · `showSocial` (true) · `showTagline` (true) · `tagline` (default *"Liability limited by a scheme approved under Professional Standards Legislation."* — the law-firm "legal line") · `bookingLabel` (default "Book a 15-minute call") · `socialNames[]` (default LinkedIn, Facebook, Instagram; X available). Derived: `initials` = first letters of first two words upper-cased. Social icon files: `_ds/…/assets/social-{linkedin,facebook,instagram,x}.svg`.

### Layouts (exact geometry)
1. **Classic** — `display:flex; gap:16px`: 72×72 initials tile (radius 4, `wash` bg, `accent` text, Poppins 700 22/24) · 2px vertical rule in `accent` · column (`gap:2px`): name 700 17/22 · "{jobTitle} · {firmName}" 14/20 in `accent` · "{phone} · {email}" 13/19 grey (mt 4) · website 13/19 grey · booking pill (mt 8; `padding:7px 14px; radius 4; accent bg; white 600 13/16`) · social row (mt 10; 22px squares radius 4 in `accent`, 12px white-inverted glyph via `filter:brightness(0) invert(1)`) · tagline 11/16 `#9598a8` max-width 340.
2. **Stacked** — column `max-width:380px`: name Poppins 700 19/24 −0.2 · title 14/20 grey · 3×56 accent bar (radius 2, `margin:10px 0`) · firm 700 14/20 in accent · phone 13/19 · "{email} · {website}" 13/19 · booking **outlined** pill (1px accent border, accent text) · social row (16px icons at opacity .65, no tile) · tagline.
3. **Photo** ("With photo"/"Portrait") — `gap:16px; max-width:440px`: 76px circle (wash bg, 2px accent border, initials Poppins 700 24/26 — stands in for the headshot) · column: name 700 17/22 · "{jobTitle}, {firmName}" 14/20 grey · row: phone 600 13/19 accent · 4px dot · email grey · website · booking solid pill · social row (22px circles in `wash`, 12px glyph opacity .7) · tagline.
4. **Compact** — column `max-width:480px; gap:6px`: line 1 (wrap): name 700 15/20 · 1×14 divider `#d0d4e4` · title 14/20 grey · divider · firm 700 14/20 accent; line 2: phone · website (13/19 grey) · booking as underlined accent text 600 13/19; social row 14px icons opacity .6; **no tagline** (compact ignores `showTagline`).

### Port notes
Build as (a) a React preview component and (b) a server-side HTML email renderer (Blade/Twig template producing table-based, fully-inlined HTML with hosted image URLs). The React preview must visually match the email output — share a single layout spec. Initials tile → real `photo_url` when present (Photo layout) or logo (Suite's classic uses the client logo at 56/72/96px instead of initials — reconcile: **Suite/Studio classic = logo + rule + details; SignatureBlock classic = initials tile**. Decision: logo if brand kit has one, initials fallback).

---

## 5.13 `Client OS Signature Builder.dc.html` — simplest builder (gallery → stepped builder)

**Purpose / audience.** Office manager (header avatar "Nadia Cole") sets one firm-wide layout and applies it to staff. Two screens: **Gallery** and **Builder**. Preview 1440×940. No Tweaks props.

### Shell
Header: logo · divider · "Harper & Vale" · `IconButton Notifications` · `Avatar`. Rail = canonical Client OS rail with **Email "Signatures" active** (index 3). Main `overflow:auto`, full width (not the 780 column).

### Screen A — Gallery (`screen: gallery`)
- Page header band (white, `padding:24px 32px 16px`, hairline): h1 **"Email signatures"**; sub 16/22 grey max 640: *"Pick a layout once. Everyone's name, title and phone number fill in on their own — you never edit twelve signatures by hand."*
- Content `padding:24px 32px 64px; gap:24px; max-width:1240px`.
- **Status card**: white card `padding:14px 16px`; 32px green disc `Icon Team 16`; title 600 14/20 **"12 people, 12 signatures, one layout"**; sub 14/20 grey "{new starter} started Monday and hasn't got one yet."; right amber pill **"1 missing"** (`var(--ozee-amber)` bg, white 600 12/18).
- **"Choose a layout"** h2 Poppins 600 18/24 + caption "Shown with {owner first name}'s details. You can change any of it next." Grid `repeat(2, minmax(0,1fr)) gap 16`. Template card: white, 1px border (`--ozee-blue` when current, else `#d0d4e4`; hover blue), radius 8; header `padding:14px 18px` name 600 16/22 + green **"In use"** pill when current; body `padding:22px 18px; min-height:170px` with `SignatureBlock` (layout, accent, wash, booking/social per toggles, no tagline); footer `padding:12px 18px`: note 14/20 grey + outlined 32px **"Use this one"**. Click card or button → Builder with that layout.

| id | Name | Chip | Note |
|---|---|---|---|
| classic | Classic | Classic | "Initials block, firm colour rule, everything on two lines." |
| photo | With photo | Photo | "Adds a headshot. Best for people who meet clients." |
| stacked | Stacked | Stacked | "Narrow and tall — reads well on a phone." |
| compact | Compact | Compact | "One line, no images. Never breaks in any email client." |

### Screen B — Builder (`screen: builder`)
- **Top bar** (white, `padding:18px 32px`, hairline, wrap): back link `NavigationChevronLeft 14` **"Layouts"**; h1 Poppins 600 24/30 **"{Layout name} signature"** + sub 14/20 grey `appliedLine` = "Applied to {n} of {total} people · images hosted by OZee"; right: outlined 40px **"Send myself a test"** + primary 40px identity-blue `saveLabel` = **"Save for everyone"** (when all selected) / **"Save for {n}"**.
- **Body grid `360px minmax(0,1fr)`**, `align-items:start`.
- **Left settings panel** (white, right hairline, `padding:20px; gap:22px`), sections with eyebrow headings:
  1. **LAYOUT** — 2×2 chip grid (34px buttons; selected `#eceffa`/blue border+text): Classic · Photo · Stacked · Compact.
  2. **FIXED FOR EVERYONE** — 4 text inputs (label 14/20 grey above, input 36px, 1px `#c3c6d4`, focus `--ozee-blue`): **Firm name**, **Main phone**, **Website**, **Booking button text**.
  3. **FILLS IN PER PERSON** — merge-tag chips (28px, `#eceffa` bg, blue text, `Icon Tags 11`): `{First name}` `{Full name}` `{Job title}` `{Direct line}` `{Email}` `{Booking link}`; helper 12/16: *"These come from each person's profile. Change someone's title and their signature updates by itself."*
  4. **ACCENT COLOUR** — 4 swatches 36×36 radius 4, 2px ring `#323338` when selected (`aria-label` = name): Firm navy `#213aa8`/wash `#eceffa` · Deep green `#1f9355`/`#e9f5ee` · Burgundy `#7e3b8a`/`#f3ecf5` · Charcoal `#323338`/`#eef0f4`.
  5. **INCLUDE** — 3 hand-built toggles (40×22 pill, blue track when on; `role=switch`), label 14/20 + note 12/16: **Booking button** "Links to that person's own calendar." · **Social icons** "LinkedIn, Facebook and Instagram." · **Legal line** "Professional Standards Legislation wording."
- **Right column** (`padding:24px 32px 48px; gap:20px`):
  1. **"How it looks in an email"** card: header row (600 14/20 + "Preview for {person}" 12/16 grey); body `padding:24px`: fake email — "To: {recipient email}" and "Subject: …" 14/20 grey (subject row has hairline under), body 16/24 "Hi {first name}, thanks for getting in touch this morning. I've put a hold on Thursday at 2pm if that suits." / "Kind regards," then `SignatureBlock` with all live props (preview person = a fee earner, not the manager).
  2. **"Apply to"** card: header 600 14/20 + `appliedCount` "{n} of {total} selected" + 28px outlined **"Select all" / "Clear all"**. Rows (`padding:10px 20px`, hairline, click toggles): 18px checkbox square (radius 2; blue fill + white `Check 12` when on) · `Avatar small` · name 600 14/20 + "{title} · {email}" 12/16 grey (ellipsis) · status 12/16 right in tone: **"Live"** (green) / **"No signature yet"** (amber). Fixture: 5 staff (Managing partner, Partner, Senior associate, Office manager, Paralegal — the last has no signature).

### DS components used
`IconButton`, `Avatar` (medium, small), `Icon` (Team, NavigationChevronLeft, Tags, Check + rail). `SignatureBlock` fragment. Toggles/inputs hand-built → use DS `Toggle`, `TextField`, `Checkbox`.

### States / interactions
Gallery↔builder; layout chip changes preview live; toggles/fields re-render preview; select-all; save label reflects count. Not designed: saving progress, success toast (*inferred* "Saved for 12 people — live in every mailbox"), test-email confirmation, validation (empty firm name), image upload for Photo layout (headshot per person is implied but there is no upload UI here — the Suite's My profile covers it).

### Backend implied
`PUT /client/signatures/template` {layout, firm fields, accent, toggles}; `POST /client/signatures/apply` {employee_ids}; `POST /client/signatures/test` (send to self); per-employee `signature_status` (live / missing). Staff list = client's employees with title, email.

---

## 5.14 `Client OS Signature Studio.dc.html` — advanced block editor (`data-screen-label="Signature studio"`)

**Purpose / audience.** Power editor for a single signature *template* ("Fee earner — property", firm with 18 mailboxes). Two roles previewable: **Admin** (full control) and **Fee earner** (limited: own details, approved variants, photo). Preview 1600×1000; root `overflow:hidden` (three fixed panes, each scrolls).

**Tweaks props:** `role` enum `admin | staff` (default admin, section Preview); `startTab` enum `content | style | advanced` (Inspector); `accent` colour (`#213aa8 #1f9355 #7e3b8a #323338`, section Brand).

### Header (56px, `gap:12px`) — **no icon rail on this screen** (it is a full-bleed editor)
logo h24 · divider · two-line title (600 14/18 "Fee earner — property" / 11/14 grey "Harper & Vale · 18 mailboxes") · native `<select>` 32px **Variant: Australia / Variant: United Kingdom / Variant: Compact reply** · right: role segmented control (26px, `#f6f7fb` track: **Admin** · **Fee earner**) · **Conditions** button (32px outlined, `Bolt 14`, count badge "5" grey pill) · **Compatibility** button (`Security 14`, count badge "2" amber `#fdf3e0/#a06400`) · divider · outlined **"Send test"** · primary 32px **"Save & push to 18"**. Active drawer button gets `#f0f6ff` bg + blue border.

### Left pane — `<aside>` 276px, white, right hairline
Tabs (34px, 2px underline): **Blocks** · **Layers**.
- **Blocks** (palette; 2-col grid of draggable tiles 1px `#e6e9ef` radius 4 `padding:10px 8px`, 18px icon + 600 11/14 label, hover blue border + `#f0f6ff`, `cursor:grab`, `title`=hint):
  - **Text and fields**: Text (`Doc`, "Free text line") · Field (`Tags`, "Directory or custom field") · Computed (`Bolt`, "Derived from other fields") · Contact rows (`Email`, "Icon plus value, one per line")
  - **Layout**: Divider (`NavigationChevronLeft`, "Rule between sections") · Spacer (`Board`, "Vertical gap")
  - **Images**: Image (`Image`, "Logo, headshot or badge") · Animated image (`Bolt`, "Two frames, we build the GIF") · Banner (`Announcement`, "Campaign banner slot") · Accreditations (`Completed`, "Row of certification logos")
  - **Actions**: CTA button (`Add`, "Tracked link button") · Booking link (`Calendar`, "Calendar scheduling link") · QR code (`Duplicate`, "vCard or link as a QR") · Social icons (`Globe`, "Shape and style per set")
  - **Compliance**: Disclaimer (`Security`, "Firm wording, locked") · Handwritten (`Doc`, "Draw or upload a signature")
- **Layers** (ordered list, 38px rows, grip "::", icon, label 600 13/18, lock glyph `Security 13` when firm-locked, eye toggle `Show`/`Hide` 15px; hidden layers at opacity .45; selected row `#eceffa`/blue): Animated logo 🔒 · Name — computed · Title · department · Divider 🔒 · Contact rows · Social icons 🔒 · Booking link · Campaign banner 🔒 · Accreditations 🔒 · Disclaimer 🔒 · Handwritten signature (hidden by default) · vCard QR (hidden by default).

### Centre — canvas
- **Canvas toolbar** 44px white: mail-client segmented control **Gmail · New Outlook · Classic Outlook · Mobile** + `clientNote` (Gmail/New Outlook: "Renders everything, including animation." · Classic Outlook: "Word engine — shows only the first GIF frame." · Mobile: "Single column below 420px. Contact rows stack."); right: `widthNote` "Signature width 592px" / "Preview 380px" · divider · checkbox **"Show merge tags"** (checked).
- **Email frame** (max-width 680, white card): header `#f6f7fb` "To: …" 12/16 + subject 600 13/18; body 14/22 grey "Hi Sara, the bank has confirmed Thursday…"; then the **signature as selectable blocks** (each block = 1px ring, blue when selected, `#f7faff` wash; click selects → inspector). In order:
  1. **Animated logo** — 126×38 logo tile (`#eceffa`, "HARPER & VALE" Poppins 700 12 in `#213aa8`) + amber chip "GIF · {crossfade|slide up|…|frame one only|static}"; floating label "Animated logo" when selected; CSS `wsPulse` animation stands in for the GIF (disabled on Classic Outlook).
  2. **Name** 700 17/24 `#213aa8` + merge-tag hint 11/15 `#8b8ea3` "{{ ws.fullName }} · computed".
  3. **Title · department** 600 13/18 + "{{ ws.title }} · {{ ws.department }}".
  4. **Divider** 2×52 `#213aa8`.
  5. **Contact rows** — 18px circular icon discs (`#eceffa`/`#213aa8`, 11px glyph) + value 13/18 + tag hint: phone `{{ ws.phone }}` · direct line "computed from extension" · email `{{ ws.email }}` · website `{{ firm.website }}`.
  6. **Social icons** — three 22px circles `#213aa8` + note "Circle · solid · firm navy".
  7. **CTA** — 30px pill `#213aa8` with `Calendar 13` "Book a 15 minute call".
  8. **Campaign banner** — 56px navy banner: title Poppins 700 13 "WA stamp duty changes" + sub 11/15 `#c9d3f2` "What buyers need to know before 1 Sep" + outlined "Read" chip; animated when GIF on.
  9. **Accreditations** — 26px outlined chips uppercase 9px: "Law Society WA", "Accredited specialist".
  10. **Disclaimer** — `Security 12` + "LOCKED BY THE FIRM" eyebrow + 10/15 `#8b8ea3` confidentiality text.
- Below the frame: **AI change bar** (white card, `Robot 16`, borderless input placeholder *"Ask for a change — "tighten the spacing and drop the awards row""*, outlined **Apply**).

### Right pane — inspector `<aside>` 336px
Header: 26px icon disc + `selTitle` (layer label) + `selTag` ("{{ ws.<id> }}") + `IconButton Duplicate` "Duplicate block" + `IconButton Close` "Remove block". Tabs (36px underline): **Content · Style · Advanced**. Staff-role banner (`#f0f6ff`, `Announcement 14`): *"You can change your own details, swap approved variants and add a photo. Layout and styling belong to the firm."*

Field kinds rendered (all 34px controls): `text` (readOnly when locked, grey bg), `select`, `toggle` (34×19), `segmented` (26px pills in `#f6f7fb` track), `swatch` (26px squares + hint), `icons` (shape segmented Circle/Square/Plain + 6-col grid of 12 glyphs: Notifications, Inbox, Email, Globe, Home, Calendar, Team, Doc, Tags, Announcement, Security, Chart), `image` (dashed drop row: 46×32 thumb, filename 600 12/16, hint 11/15, 28px button Replace/Choose; empty = blue dashed + `#f7faff`), `anim` (3-col style tiles with animated chip), `note` (tone `info` `#f0f6ff/#cfe0fb`, `ok` `#e9f5ee/#cbe7d8`, `plain` `#f6f7fb/#e6e9ef`), `button` (36px primary full width). Label row shows "🔒 Firm" when locked. Staff role locks every kind except text/note/image/toggle.

**Content tab per block (transcribed):**
- **logo**: image "Frame one" (harpervale-logo.png · "126 × 38 · PNG" · Replace) · toggle "Animate this image" ("On — we generate the GIF" / "Off — static image") · [if on] image "Frame two" ("Choose a second image" / "We animate between the two") · anim "Animation style" (Crossfade · Slide up · Flip · Pulse · Wipe · Zoom) · segmented "Speed" Slow/Medium/Fast · select "Loop" (Loop forever / Play three times / Play once and hold) · note(info) *"You never upload a GIF — pick a second image and a style and we build the file. Classic Outlook for Windows shows frame one only, so keep it readable on its own."* · button **"Generate GIF · 148 KB"**.
- **name**: select "Source" (Computed — first + last / Directory field: displayName / Typed by the employee) · text "Separator" · text "Preview" (locked) · toggle "Also compute initials" ("JT — used by the avatar block") · note *"Computed fields update themselves the next time the directory syncs. Nobody types this."*
- **role**: select "Source" (Computed — title + department / Directory field: jobTitle / Typed by the employee) · segmented "Separator" (· | comma) · text "Preview" (locked) · toggle "Hide department if empty" ("Drops the separator too").
- **divider**: segmented "Width" Short/Half/Full · select "Thickness" 1px/2px/3px · swatch "Colour" (Firm navy `#213aa8`, Charcoal `#323338`, Slate `#676879`, Deep green `#1f9355`).
- **contact**: icons "Icon for this row" (*"Curated set. Staff may swap the glyph but not the shape — the firm locks that."*) · select "Row" (Phone · Direct line · computed from extension · Email · Website) · text "Label prefix" (locked for staff) · toggle "Hide row if the field is empty" ("Recommended for mobile") · toggle "Track clicks on this row" ("Tracked link, reported in Analytics").
- **social**: icons "Set and shape" (*"Shape applies to the whole set. Locked to the firm's choice."*) · select "Which accounts" (Firm accounts / Firm plus personal LinkedIn / Personal only) · swatch "Icon colour" (locked).
- **cta**: text "Button text" · text "Destination" (calendar.{domain}/{slug}) · segmented "Style" Solid/Outline/Text · toggle "Tracked link" ("Short link plus click reporting") · toggle "Also render as a QR code" ("Useful on printed cards").
- **banner**: image "Frame one" (600 × 100 · PNG) · toggle "Animate this banner" · [if on] image "Frame two" ("Second state of the message") · anim · segmented Speed · note(ok) *"Frame one carries the whole message on its own, so Classic Outlook readers lose nothing."* · text "Destination" · select "Campaign" (WA stamp duty changes / Spring seminar series / No campaign).
- **awards**: image "Logo one" (law-society-wa.png · "Accredited property specialist") · image "Logo two" ("Add another" · "Up to three fit on one line") · segmented "Height" 20px/26px/32px · note *"Shows for partners and accredited specialists only — see the Conditions tab."*
- **disclaimer**: select "Which disclaimer" (General confidentiality / Litigation privilege notice / UK data handling notice; locked) · text "Wording" ("Set by the firm — open Disclaimers to edit", locked) · note(info) *"Locked. Only an admin with the compliance permission can change this wording, and every change is written to the audit log."*
- nothing selected: note "Select a block on the canvas to edit it."

**Style tab (any block; all locked for staff):** select Font (Figtree — falls back to Arial / Georgia / Arial / Verdana / Times New Roman) · segmented Size 11/13/17 · Weight Regular/Semibold/Bold · swatch Colour · segmented Align Left/Centre/Right · select Line height (Tight 1.2 / Normal 1.4 / Loose 1.6) · Space above / Space below (0/2/4/8/12px) · note: staff *"Styling is set by the firm. Ask {admin} if something needs to change."* / admin *"Every value here is inlined into the sent HTML — no stylesheet, so it survives every client."*

**Advanced tab:** text "Merge tag" `{{ ws.<id> }}` (locked) · text "Alt text" · select "Who may edit this block" (Admins only / Admins and the employee / Employee — their own value only / Nobody — locked) · toggle "Hide if empty" ("Removes the block and its spacing") · toggle "Include in the compact reply variant" ("Reply and forward signatures"; on for name & contact) · select "Wrap on mobile" (Inherit / Stack below 420px / Never wrap) · note *"Merge tags resolve at send. Copy the tag if you need it in a server-side rule."*

### Drawers (right-side, 520px, `max-width:92vw`, backdrop navy 70%, shadow `0 8px 32px rgba(41,47,76,.24)`)
Header Poppins 600 18/24 + sub 13/18 + `IconButton Close`. Rows = bordered cards (26px icon disc, title 600 14/20, detail 13/19 grey, chips 11/17 `#f6f7fb`, 30px outlined CTA).
- **Conditions** ("Every rule in this template, in the order it is evaluated."; disc `#eceffa`/blue): 1 "Hide the mobile row when empty" — "Contact rows · the mobile line disappears rather than leaving a stray icon." [Contact rows · Hide if empty] · 2 "Direct line built from extension" — "Computed as {prefix} + extension. Falls back to the reception number." [Computed · Direct line] · 3 "Show accreditations for partners only" — "Awards row appears when seniority is Partner or Managing partner." [Accreditations · When seniority is] · 4 "Banner off for internal mail" — "Campaign banner is suppressed when every recipient is on {domain}." [Campaign banner · Internal only] · 5 "UK variant swaps the disclaimer" — "Staff whose office code is LON get the UK data handling notice instead." [Disclaimer · When office is LON]. CTA "Edit".
- **Compatibility** ("Two things worth fixing before this goes out to 18 mailboxes."; disc `#fdf3e0`/`#a06400`): 1 "Dark mode contrast on the logo" — "The navy logo sits on a transparent background — it drops to roughly 2.1:1 against the dark-mode canvas in Gmail and Outlook. Add a light plate behind it or ship a light-on-dark version." [Animated logo · Contrast 2.1:1] CTA **Fix** · 2 "Poppins is not web safe" — "The name and banner headline use Poppins, which no mail client has installed. Both will fall back — set Georgia or Arial as the declared fallback so the shape stays close." [Name · Campaign banner] CTA **Set fallback**.

### DS components used
`Icon` (many), `IconButton` (Duplicate, Close). Everything else hand-built (segmented controls, toggles, selects, drawers). *Port:* DS `Tabs`, `Toggle`, `Dropdown`, `ButtonGroup`, `Modal`/drawer, `Menu`.

### Backend implied (beyond §5.12)
Block schema with per-block `content`, `style`, `advanced` (permissions, hide-if-empty, compact-variant, mobile wrap); **computed fields** (first+last, title+department, direct line from extension with fallback); **directory sync** (Google Workspace/Entra) feeding `ws.*`; **conditions engine** (hide-if-empty, seniority, internal-only recipients, office code → disclaimer variant) evaluated at render/send (server-side deployment needed for recipient-based rules); **GIF generation service** (two frames + style + speed + loop → GIF, size reported); **compatibility linter** (contrast, font safety); **tracked links / short links + click analytics**; QR generation (vCard); variants (Australia / UK / Compact reply); audit log for disclaimer edits; test send; push to N mailboxes.

---

## 5.15 `Client OS Signature Suite.dc.html` — full "Signatures" app (multi-module)

**Purpose / audience.** The complete signature product as a standalone app inside Client OS, with three preview roles: **OZee staff** (avatar Zeeshan Sabri), **Office manager** (Nadia Cole), **Fee earner** (Josh Tan — sees only "My signature" and "My profile"). Preview 1560×980. Tweaks: `role` enum `staff | manager | individual` (default staff); `showMergeTagHints` bool; `accentPalette` colour pairs.

### Shell — a *three-tier* nav (app rail + secondary sidebar + main)
- **Header**: logo · divider · "Harper & Vale" · amber pill **"14 days left in trial"** (`#fdf3e0` bg, `--ozee-amber` text, fully rounded) · right: "Preview as" segmented control (OZee staff · Office manager · Fee earner — mock-only) · `IconButton Notifications` · `Avatar`.
- **App rail 64px** (`padding:8px 0`): items 52px wide with 20px icon **and a 9px label** (`short`), radius 4; active `#eceffa`/blue; non-live apps at opacity .4, `title` "{label} — later phase". Apps: `Email` **Sign** "Signatures" (live) · `Inbox` Leads "Leads & inbox" · `Calendar` Book "Scheduling" · `Globe` Domain "Domain & DNS" · `Doc` Agency "Agency hub" · `Chart` Results "Results dashboard" · then pushed to bottom (`margin-top:auto`): `Team` **People** (live) · `Settings` **Admin** "Workspace settings" (live). **This is the Product-Plan module list as a rail** — the most complete Client OS navigation in the export.
- **Secondary sidebar** `navW` 236px open / 48px collapsed (`transition:width 250ms`): header (app name Poppins 600 18/24 + note 12/16 + collapse chevron 28px) · optional **"New signature"** primary 36px button (Signatures app, non-individual) · grouped items 36px (icon 16 + label 14/20; active `#d3e4fd`/blue 600; "Soon" pill `10/14 #9598a8` for later-phase items at opacity .45; right count 11/16) · footer note 11/15 `#9598a8`.
  - App **Signatures** ("Templates, rules and delivery for all 24 mailboxes." / footer "Saved changes go live in every mailbox straight away."): **Get started** → Setup (`CheckList`) · **Build** → All signatures (`Email`, count 6) · Templates (`Board`) · Studio (`Image`) · Campaigns (`Announcement`, Soon) · **Control** → Rules (`Bolt`, 5) · Disclaimers (`Security`) · **Deliver** → Deployment (`Settings`) · Engagement (`Chart`, Soon). Individual role: **My email** → My signature only ("Your own signature, kept on brand.").
  - App **People** ("Shared across every module — one list of who works here." / "Synced from Google Workspace at 6:00 am daily."): **Directory** → Employees (`Team`, count) · Groups & teams (`Board`, Soon) · Roles & access (`Security`, Soon).
  - App **Workspace** ("Company details, brand and media — shared by every module." / "We host every uploaded image, so signatures keep working offline of your site."): **Company** → Company details (`Doc`) · Brand kit (`Image`) · Media library (`Tags`, 18) · **You** → My profile (`Team`) · Notifications (`Notifications`, Soon) · **Account** → Plan & billing (`Board`, Soon). Individual: **You** → My profile only.
- **Main** `overflow:hidden`, each module scrolls itself. Module page header pattern: white band `padding:24px 32px 16px`, h1 Poppins 600 32/40, sub 16/22 grey, actions right.

### Module: Setup wizard (`isWizard`)
h1 **"Setup"**; sub *"Five steps to your first signature. Most teams finish in under ten minutes."*; right: progress (12/16 "{pct} done" / "Step {n} of 5") + 8px pill bar `#e6e9ef` with blue fill (400ms). Accordion cards (`max-width:1000px; gap:12px`): 26px numbered disc (done = blue + `Check`, open = blue, pending grey) + title 600 16/22 + chevron rotates 90°/−90°. Steps: 1 **Edit your company info** · 2 **Edit your signature** · 3 **Who needs a signature?** (open; fixture doneSteps 1,2) · 4 **Add signature to email** · 5 **Choose a plan** (amber pill "14 days left"). Step 3 body (only one designed): two choice cards — **"Just me for now"** ("You can add teammates whenever you like — nothing you set up here is lost."; outlined 40px button) and **"My team"** (blue border, `#f0f6ff`; "Add people by email, upload a CSV, or sync from Google Workspace."; primary **"Add employees"** → Employees + overlapping `Avatar small` peek of 4 people).

### Module: All signatures (`isDashboard`)
h1 **"Signatures"**; sub (staff) *"Four signatures live across Harper & Vale. Everything here is set once and fills in per person."* / (manager) *"Set the layout once — everyone's details fill in from their profile."*; primary **"New signature"** (`Add 16`). Filter bar: labelled selects **Assigned to** (All / Partners / Fee earners / Admin) · **Sort** (Recent / Name / People assigned) · right search "Search signatures". Card grid `repeat(auto-fill, minmax(420px,1fr)) gap 20`: header name 600 16/22 + status pill (**Live** green · **Waiting to send** amber · **Draft** `#676879`); body `SignatureBlock` preview for a representative person; footer "Assigned: {scope}" 12/16 + outlined 32px **Edit** (→ Studio) · **Install** · `IconButton Duplicate` "More actions". Fixture: Partner brand (classic, Partners (2 people), Live) · Fee earner (photo, Fee earners (2 people), Live) · Spring campaign (stacked, All staff (5 people), Waiting to send, no booking) · Court-safe compact (compact, Nobody yet, Draft).

### Modal: New signature (`nfOpen`) — step 1 of 3
560px, radius 16, shadow `0 12px 40px rgba(41,47,76,.28)`. Title **"New signature"**, sub *"Name it and say who it covers. You pick the layout next."* Fields: **Signature name** (40px, placeholder "e.g. Litigation — full disclaimer", help *"Only your team sees this name. It is how you pick the signature in rules and on a person's profile."*) · **Who gets it** radio cards: **Everyone at {firm}** ("24 mailboxes. You can carve out exceptions later with a rule.") · **One group** ("Litigation, Property, Support — pick the group once it is built.") · **One person** ("For a single mailbox, or an archived one that redirects senders."). Footer: Cancel · primary **"Choose a template"** (disabled grey `#c3c6d4` until name typed). → Templates gallery in "picking" mode.

### Module: Templates gallery (`isGallery`)
h1 24/30 **"Pick a template"** / (picking) **"Choose a layout"**; sub *"Change the brand settings here and every template below updates as you go."* / *"Brand settings below apply to every layout — change them now or later."* Picking banner (`#f0f6ff`): blue pill **"Step 2 of 3"** + *"Pick the layout for "{name}" — it goes to {scope}. You can change the layout later in the studio."* + Cancel. **Brand bar** (white, hairline): Font select (Figtree / Arial / Georgia / Verdana) · **Brand colour** 4 swatches 28px · divider · "Client logo" chip · toggle chips **Booking button** / **Social icons**. Left category aside 220px: **STYLE** Classic / Corporate / Minimal · **INDUSTRY** Legal / Real estate / Tech / Finance / Healthcare / Trades · outlined **"Start from scratch"**. Grid `minmax(400px,1fr)`: template cards (name 600 14/20 + "In use" green pill, `SignatureBlock` preview, note 13/18, CTA **"Use this one"** / picking: primary **"Use this layout"** → Studio, creates Draft). Templates: **Classic two column** ("Logo beside the details, brand rule between.") · **Portrait** ("Adds a headshot — best for people who meet clients.") · **Stacked** ("Narrow and tall. Reads well on a phone.") · **Compact** ("One line, no images. Never breaks in any client."). (Style/Industry filters are cosmetic in the mock — the same 4 layouts show.)

### Module: Studio (`isStudio`) — the Suite's drag-and-drop editor (simpler than §5.14)
Top bar: back "Templates" · h1 18/24 `{signature name}` + sub "{layout} · applied to 4 of 5 people" + chip **Live** (green) / **Draft** (grey) · **Undo** · **Redo** · primary **"Save changes"** / **"Save & publish"** (draft → Live, returns to dashboard).
Grid `300px minmax(0,1fr) 300px`:
- **Left panel** tabs (42px, sticky): **Details · Images · Social · Banners**.
  - Details: fields Firm name, Main phone, Website (editable) + **Person's name** `{Full name}`, **Job title** `{Job title}`, **Direct line** `{Direct line}` (locked, "🔒 Set by admin", grey). **MERGE TAGS**: *"Drag a tag onto any text block in the canvas. It fills in from each person's profile when the email is sent."* — draggable chips (26px `#eceffa`, hover `#d3e4fd`): {First name} {Full name} {Job title} {Direct line} {Email} {Booking link}. Drop on Name/Title block appends the tag.
  - Images: **CLIENT LOGO** card (thumb, filename "harpervale-logo.png", "Hosted for you — no broken images", Replace) · **LOGO WIDTH** Small/Medium/Large (=56/72/96px) · **PHOTO SHAPE** Square/Rounded/Circle (4px/8px/50%).
  - Social: **ACCOUNTS SHOWN** toggles per network (LinkedIn, Facebook, Instagram, X; icon via CSS mask of the DS SVG) · **ICON SHAPE** Square/Rounded/Circle (2px/4px/50%).
  - Banners: **CALL TO ACTION** Button label ("Book a 15-minute call") + Where it goes (`{Booking link}`) · **CAMPAIGN BANNER** radio cards: **No banner** ("Signature stays plain") · **Spring conveyancing offer** ("Runs until 30 Sep · all staff") · **Office closed 24–26 Dec** ("Runs 20–27 Dec · all staff"); banner text rendered "Fixed-fee conveyancing until 30 September" / "Our office is closed 24–26 December".
- **Centre canvas**: toolbar "Previewing as" `<select>` of employees + client segmented **Gmail · Outlook · Mobile** (`canvasWidth` 720px / 420px mobile; labels "Gmail on the web" / "Outlook on the web" / "Mobile — iOS Mail"; hints "Renders as sent" / "Outlook strips some spacing" / "Narrow — check nothing wraps badly"). Email frame: To/Subject, body, then **two drop zones** (dashed `#e6e9ef`, blue when hovered): **left column** (logo at `logoW`, photo = initials tile in `photoRadius`) · 2px accent rule · **right column** blocks (name 700 17/23 in chosen font, title 14/20 grey, firm 700 14/20 accent, details rows "P/E/W" key letters in accent, social row in `iconRadius`, CTA pill, banner amber wash `#fdf3e0`/`#f4d9a5` with `Announcement` icon, legal line 11/16 `#9598a8`). Blocks are `draggable`; drag-enter reorders; selected block shows blue frame + floating merge-tag label (`COMPANY_LOGO`, `EMPLOYEE_NAME` …) when `showMergeTagHints`. Under the frame: `dragHint` *"Drag any part to reorder it, or across to the other column. Email clients only stack — so that's the only movement there is."* + outlined **Copy HTML** · **Send myself a test**. Then **"PARTS YOU CAN TURN OFF"** chip row (`Hide 14`): Logo · Photo · Name · Title · Firm · Contact · Social · Booking button · Banner · Legal line (on = `#eceffa` blue). Bottom AI bar (`Robot`, input placeholder *"Ask for a change — "add the legal disclaimer" or "make the phone number bold""*, primary **Apply**).
- **Right panel — deploy cards** (dot + title + body + button): **Google Workspace** (green; "Connected as admin. 4 of 5 mailboxes have this signature."; primary **Sync now**) · **Microsoft 365** (green; "Add-in active for Outlook desktop and web."; **Download manifest**) · **Manual install** (grey; "For anyone not on a managed mailbox — copy the HTML or send them a test."; **Copy HTML**) · **Campaign banner** (amber when running; "Runs on every signature until the end date." / "No banner running."; **Manage campaigns**).

### Module: Employees (`isEmployees`, People app)
h1 **"Employees"**; sub "Five people. One is still waiting on a signature."; outlined **Manage groups** · primary **Add employees**. Blue wash bar "Finished adding and editing your team?" + outlined-blue **"Back to setup"**. Search "Search by name or email" + checkbox "Show archived". Table (grid `44px 60px 1.4fr 1.6fr 1.4fr 110px 44px`, header 40px `#f6f7fb`, rows 56px): checkbox · **Active** toggle (36×20) · **Name** (`Avatar small` + name 600 + title 12/16) · **Email** · **Primary signature** `<select>` (Partner brand / Fee earner / Court-safe compact / Unassigned) · **Status** pill (**Active** green / **Pending** amber) · `IconButton Settings` "Row actions".

### Module: Rules (`isRules`, `data-screen-label="Rules"`)
h1 **"Rules"**; sub *"Decide which signature goes out on which email. Rules run top to bottom — the first match wins."*; primary **Create rule**. Amber notice (`#fdf3e0`/`#f3ddb0`, `Announcement`): *"Reply and forward rules only apply once server-side deployment is on. Right now three of these five rules are client-side only."* Rule cards (`max-width:1180px`): order square 24px · name 600 16/22 + side pill (**Server side** `#e9f5ee/#00854d` · **Client side** `#f6f7fb/#676879`) · note 13/18 · condition row: "WHEN" + blue-wash chips (`when[]`) · divider · grey chips audience + group · 16px line · blue pill signature name · right: enable toggle + `IconButton Settings`. Disabled rule card border `#e6e9ef`.

| # | Rule | When | Audience | Group | Signature | Side | Note | On |
|---|---|---|---|---|---|---|---|---|
| 1 | Litigation privilege | New email | External only | Litigation | Litigation — full disclaimer | Server | "Adds the confidentiality and privilege notice required on court correspondence." | ✓ |
| 2 | Short form on replies | Reply, Forward | Any recipient | All staff | Compact reply | Server | "Keeps long threads readable — name, firm and phone only, no banner." | ✓ |
| 3 | Internal mail | New email, Reply, Forward | Internal only | All staff | Internal — plain | Client | "No campaign banner and no disclaimer inside the firm." | ✓ |
| 4 | Settlement notice | New email | External only | Property | Property — settlement | Client | "Scheduled 1 Sep – 30 Nov while the WA duty changes are in effect." | ✗ (scheduled) |
| 5 | Departed staff handoff | New email, Reply | Any recipient | Archived mailboxes | Contact redirect | Client | "Points senders at the team inbox for anyone who has left the firm." | ✓ |

### Module: Deployment (`isDeploy`, `data-screen-label="Deployment"`)
h1 **"Deployment"**; sub *"Connect the firm's mail platform once. After that signatures install themselves and stay current."* Coverage stat tiles (`minmax(220px,1fr)`; value Poppins 700 32/40): **24** "Mailboxes covered" "Every active person in the firm" · **3** "Awaiting install" (amber) "Chrome extension not yet added" · **6:00 am** "Last directory sync" "Runs daily from Google Workspace". **Delivery method** radio cards: **Client side** ("Written into each person's signature settings. Fast to set up, no mail flow involved.") · **Server side** ("Appended at the mail layer after send. Covers every device, including phones.") · **Both** ("People see their signature while composing, and nothing is ever missed. Recommended." — default). **Connections** list (36px icon disc, name + state pill, sync line, seats, CTA): **Google Workspace** (`Board`) Connected · "24 of 24 mailboxes" · "Directory synced 6:00 am today · {domain}" · **Manage**; **Chrome extension** (`Bolt`) Deployed · "21 of 24 browsers" · "Gmail and Outlook Web · 3 people still to install" · **Remind 3**; **Microsoft 365 · Entra ID** (`Team`) Not connected · "Outlook add-in plus directory sync. IT does this once." · primary **Connect**; **Exchange on premises** (`Settings`) Not applicable · "Needs a mail-flow connector on the Exchange server." · **Learn more**. Footer card: *"Want to check it before the firm sees it? Send yourself the signature exactly as it will arrive."* + **Copy HTML** · primary **Send test email**.

### Module: Disclaimers (`isCompliance`, `data-screen-label="Disclaimers"`)
h1 **"Disclaimers"**; sub *"Wording set once by the firm, scoped by department and jurisdiction. Locked text cannot be edited by fee earners."*; primary **Add disclaimer**. Grid `1.25fr 0.75fr`. Disclaimer cards: name 600 16/22 + "🔒 Locked" chip + scope right (12/16) · wording block (`#f6f7fb`, 13/20) · footer applied count + **Edit wording**. Fixture: **General confidentiality** (All staff · Australia; locked; "On 24 of 24 mailboxes") · **Litigation privilege notice** (Litigation · Australia; locked; "On 6 mailboxes · required on all external mail") · **Financial services disclosure** (Commercial · Australia; locked; "On 5 mailboxes") · **Data handling notice** (All staff · United Kingdom; unlocked; "On 2 mailboxes · London office"). Right: **Audit log** card ("Last 30 days"): rows with 8px tone square, what 600 13/18, detail 12/18, "who · when" 11/16 — e.g. "Litigation privilege notice — Wording updated — clause 3 reworded after counsel review", "Settlement notice rule — Scheduled to go live 1 Sep, currently paused", "Google Directory sync — 2 employees added, 1 archived, 4 titles updated" (System), "General confidentiality — Locked for all departments — no longer editable by staff", "Roles and permissions — Fee earners may edit phone and title only"; footer **Export full log** (full width outlined).

### Module: My signature (`isMine`, fee-earner role)
h1 **"My signature"**; sub "Your firm's layout is set by {admin}. You can keep your own details up to date." Grid `340px 1fr`: **YOUR DETAILS** card — Your name (locked), Job title, Direct line, Firm name (locked), Layout (locked, shows layout name); primary **Save changes**. Right: "How it looks in an email" card with `SignatureBlock` (tagline on).

### Module: Company details (`isCompany`, Workspace)
h1 **"Company details"**; sub *"Set once. Every signature, invoice and booking page reads these fields — change a number here and 24 mailboxes update tonight."*; right `savedNote` ("Not saved yet" / "Saved — pushed to 24 mailboxes") + primary **Save changes**. Grid `1.5fr 1fr`. **Business identity** card (2-col fields, label 600 13/18 + hint 12/16): Legal name ("Appears on invoices and the legal line.") · Trading name ("The name people see in signatures.") · ABN ("Shown in the disclaimer block when enabled.") · Main phone ("Fallback when a person has no direct line.") · Website ("Linked from the signature and booking pages.") · Office hours ("Used by auto-replies and booking links.") · Head office address (span 2; "One line — long addresses wrap badly in Outlook.") · Tagline (span 2; "Optional line under the firm name."). **Offices** card (+ **Add an office**): rows name 600 + "address · phone" + green **"In signatures"** pill on main office + `IconButton Duplicate`. Right sticky **"Where this shows up"**: `SignatureBlock` + check list: "Every signature footer, all 24 mailboxes" · "Booking pages and calendar invites" · "Invoice header and payment emails" · "The disclaimer block, when ABN is switched on".

### Module: Brand kit (`isBrand`, `data-screen-label="Brand kit"`)
h1 **"Brand kit"**; sub *"Logos, colours and type. We host every image on our own servers, so signatures keep working even when a mail client blocks other pictures."*; primary **Save changes**. **Logos** card ("PNG or SVG, transparent, 600px wide or more"): three `image-slot`s (104px): **Primary logo** ("Used in every signature and on invoices.") · **Reverse logo** + dark pill "On dark" ("Swapped in automatically in dark mode.") · **Square mark** ("Compact layouts and social posts."); select **Logo width in signatures** 56 px — small / 72 px — standard / 96 px — large. **Colours** card: Brand colour swatches 32px + hex readout; blue wash note *"Checked against white and dark mode — this brand colour passes on both, and the reverse logo swaps in automatically."* **Type** card: Signature font select + note *"Poppins is not web-safe in Outlook, so signatures fall back to Arial. Preview both before you publish."* **Accreditations** card ("Shown as a row under the contact details"): 3 badge `image-slot`s 64px. Right sticky "Applied to a live signature" `SignatureBlock`.

### Module: Media library (`isMedia`, `data-screen-label="Media library"`)
h1 **"Media library"**; sub *"Every image the platform serves — headshots, banners, badges. Replace a file here and it updates everywhere it is used."*; primary **Upload** (`Add`). Filter chips All / Logos / Headshots / Banners / Badges (selected `#d3e4fd`/blue) + count "18 files · 4.2 MB · hosted by OZee". Grid `minmax(220px,1fr)`: card with 132px `image-slot`, filename 600 13/18, meta ("PNG · 840×220 · 46 KB"), footer usage ("Used in 6 signatures" / "Used in dark mode only" / "Scheduled 1 Sep – 30 Nov" / **"Not used anywhere"** in amber for orphans) + `IconButton Duplicate`. Fixture kinds: logo-primary, logo-reverse, two headshots, two banners (one orphan), two badges (one orphan).

### Module: My profile (`isProfile`, `data-screen-label="My profile"`)
h1 **"My profile"**; sub *"Your own details, your photo, your booking link. These fill the fields your admin left open in the signature."*; `savedNote` + **Save changes**. **You** card: 104px circle `image-slot` headshot ("Square, 400px or more") + 2-col fields: Full name (locked) · Job title · Pronouns · Direct line · Mobile · Work email (locked) · Booking link (span 2). **Handwritten sign-off** card ("Optional · transparent PNG"; 96px `image-slot` "Drop a scan of your signature"). **Account** card rows: Sign-in email → **Change** · Password ("Last changed 3 months ago") → **Update** · Two-step sign-in ("Coming with the Chrome extension release") → **Set up** (disabled .45). Right sticky "Your signature right now" `SignatureBlock`.

### DS components used
`IconButton` (Notifications, Duplicate, Settings, Close), `Avatar` (medium, small), `Icon` (Add, Check, Search, Hide, Robot, Announcement, Security, Image, Tags, NavigationChevronLeft, + rail set), `image-slot` (×9 — all become real upload fields). Everything else hand-built; port to DS `Tabs`, `Toggle`, `Dropdown`, `Checkbox`, `Chips`, `Table`, `Modal`, `Steps`.

### States / interactions summary
Role gating (individual sees My signature + My profile only; People app hidden); trial pill; sidebar collapse persists; wizard progress; new-signature 3-step flow (modal → gallery picking → studio draft → Save & publish → Live on dashboard); drag-reorder blocks and drag-drop merge tags; per-client preview width; save notes; rule enable toggles; media filters. Not designed: loading, errors, empty states (no signatures yet — the wizard covers first-run), delete confirmations, Install flow UI, Campaigns/Engagement/Groups/Roles/Notifications/Billing ("Soon").

### Backend implied (beyond §5.12/5.14)
Entities: `signatures`, `signature_blocks`, `employees` (directory-synced; active; primary_signature_id; status), `groups`, `rules`, `disclaimers` + `audit_log`, `campaign_banners` (schedule window, audience), `company` (legal/trading/ABN/phone/web/hours/address/tagline) + `offices`, `brand_kit` (logos ×3, colour, font, badges, logo width), `media_assets` (kind, size, usage refs, orphan flag), `deploy_settings` (mode), `connections` (Google Workspace, Chrome extension, Microsoft 365, Exchange), `profiles` (pronouns, mobile, booking link, headshot, handwriting). Services: directory sync (daily 6:00), push-to-mailboxes (Gmail API signature set / Outlook add-in manifest), Chrome extension, reminders to install, test email, Copy HTML export, trial/billing.

---

## 5.16 `uploads/OZee CRM Inbox (offline).html` — verdict

A self-contained "Bundled Page" (Claude Design offline export, 1.7 MB) of **`OZee CRM Inbox.dc.html`**: the page HTML is embedded as a JSON string in `<script type="__bundler/template">`, fonts (Figtree/Poppins woff2), the logo PNG and the DS bundle are inlined via a `__bundler/manifest` (one base64 blob) and resolved by an unpacker script ("Unpacking…"). Decoded and diffed against the mock: **markup identical** apart from the bundler canonicalising React prop attributes (`onClick` → `sc-camel-on-click`, `ariaLabel` → `sc-camel-aria-label`, `readOnly` → `sc-camel-read-only`) and rewriting asset `src`s to manifest ids; the `data-dc-script` logic is byte-identical bar a trailing newline; the `<helmet>` differs only by the inlined `@font-face` blocks replacing the `<link>` tags. **No design content differs — nothing to add to the Inbox notes.** Use it only as a quick way to open the Inbox mock without a server.

---

## Consolidated Client OS navigation (what the mocks actually show)

| Mock | Rail items (icon → label) | Active style |
|---|---|---|
| Home, Announcement, Signature Builder | Home Today · Inbox Enquiries · Calendar Calendar · Email Signatures · Globe Domain · Doc OZee | `#eceffa` / `--ozee-blue` |
| Signature Suite | Email Sign(atures) · Inbox Leads · Calendar Book · Globe Domain · Doc Agency · Chart Results · ‖ Team People · Settings Admin — with 9px text labels; plus a 236px secondary sidebar per app | `#eceffa` / `--ozee-blue` |
| SEO Reports | Home My work · Board Projects · CheckList Tasks · Chart SEO reports · Email Messages · Settings Settings | `#d3e4fd` / `#1a73e8` (internal style) |
| Product Plan | internal rail (My work, Plan, Inbox, Schedule, Tasks, Reports, Admin) | internal |
| Signature Studio | no rail (full-bleed editor) | — |
| Login | no shell | — |

**Recommended canonical Client OS rail (decision for the port):** Today (Home) · Enquiries (Inbox) · Calendar · Signatures (Email) · Domain (Globe) · Results (Chart — SEO reports) · OZee (Doc — agency hub / approvals) · bottom: People (Team) · Settings. Use the Suite's labelled-rail treatment (icon + 9px label) if labels are wanted; otherwise the 48×48 icon-only items from the guide. Active colour = identity blue wash `#eceffa` (Client OS) vs working blue `#d3e4fd` (internal) — confirm one; the guide's colour section says working blue is "every interactive state", so the Client OS identity-blue treatment is a deliberate brand deviation that needs sign-off.

## Cross-cutting issues for tickets
1. **Button component**: Client OS primaries are 44px (login/announcement), 40px (page actions), 36px (cards) and 32px (toolbars) in identity blue — add a `brand` colour variant to `Button` with sizes 32/40/44 rather than hand-rolling.
2. **Icon `Location`** used by SEO Reports is not in `assets/icons` (76 files) — add it or use `Globe`.
3. **Persona inconsistency**: SEO Reports fixture is a dental practice under a law-firm header; Home hero shows revenue for owner but not for manager — expect role-specific hero definitions.
4. **Signature "classic" layout** differs between `SignatureBlock` (initials tile) and Suite/Studio (logo) — pick one.
5. **Studio vs Suite Studio**: two editors of different depth; ship Suite's (blocks on/off, reorder, merge-tag drop) for P1 and treat §5.14 Studio features (animation, computed fields, conditions, lint, Style/Advanced tabs) as P2+ backlog.
6. **Product Plan** page: decide whether it ships; fix "7 modules" vs nine.
7. All `image-slot`s (Announcement media, Brand kit ×4, Media library, Profile ×2) → real upload fields backed by OZee-hosted storage/CDN (the lock-in asset).
8. Dark mode is not designed for any Client OS screen; the Inbox is the only dark reference.

