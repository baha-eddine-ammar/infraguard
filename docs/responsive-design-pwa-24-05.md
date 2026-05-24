# Responsive Design and PWA Update - 24/05

## Branch

- Branch name: `responsive-design-24`
- Scope: favicon replacement, careful responsive improvements, Progressive Web App (PWA) setup, native installed-app mode improvements, and documentation.

## Files Changed

### Created

| File | Purpose |
|---|---|
| `public/favicon.png` | PNG favicon generated from the InfraGuard logo image. |
| `public/icon-192.png` | 192x192 PWA icon generated from the InfraGuard logo image. |
| `public/icon-512.png` | 512x512 PWA icon generated from the InfraGuard logo image. |
| `public/manifest.json` | PWA manifest describing the installable InfraGuard application. |
| `public/sw.js` | Service worker for safe static-asset caching and offline fallback. |
| `public/offline.html` | Native-like offline screen for installed PWA use. |
| `resources/views/layouts/partials/pwa-head.blade.php` | Shared favicon, manifest, theme color, and mobile app metadata links. |
| `resources/views/layouts/pwa-bottom-nav.blade.php` | Mobile bottom navigation shown only in installed PWA mode. |
| `docs/responsive-design-pwa-24-05.md` | This implementation report. |

### Modified

| File | Reason |
|---|---|
| `public/favicon.ico` | Replaced the empty/default favicon with an InfraGuard icon generated from the provided logo. |
| `resources/views/layouts/app.blade.php` | Added PWA metadata, cleaner title, overflow safety, and the PWA bottom navigation partial. |
| `resources/views/layouts/guest.blade.php` | Added PWA metadata and cleaner title. |
| `resources/views/welcome.blade.php` | Added PWA metadata and cleaner title. |
| `resources/views/launch.blade.php` | Added PWA metadata and cleaner title. |
| `resources/views/layouts/topbar.blade.php` | Added optional install button, PWA app-mode badge, and mobile-safe dropdown sizing. |
| `resources/js/app.js` | Registered the service worker, added install-prompt handling, and added installed PWA mode detection. |
| `resources/css/app.css` | Added small responsive safeguards and PWA-only native app styling. |
| `resources/views/components/dashboard/live-metric-card.blade.php` | Improved mobile wrapping and touch readability without changing the desktop design. |
| `resources/views/dashboard/partials/ai-prediction-card.blade.php` | Made the AI prediction card stack more cleanly on phone-sized screens. |

## Favicon Implementation

The existing logo source file `public/ChatGPT Image 24 mai 2026, 17_49_47.png` was used to generate favicon and app icon assets. The application now links to `favicon.ico`, `favicon.png`, `icon-192.png`, and `icon-512.png` through the shared `resources/views/layouts/partials/pwa-head.blade.php` partial.

The partial is included in the authenticated layout, guest layout, welcome page, and launch page. This ensures the InfraGuard icon appears in normal browser tabs and is also available to installed app contexts.

## Responsive Design Changes

The dashboard design was not rewritten. The changes are focused on preventing horizontal overflow and improving readability on tablet and phone screens.

The authenticated layout now prevents accidental horizontal scrolling and gives the main content area `min-w-0`, which helps large cards and charts shrink correctly inside flex layouts. The topbar title now truncates safely on small screens, and the notification dropdown uses a viewport-aware width instead of a fixed width that could overflow.

The live metric card now wraps its header and content more safely on mobile. Large values keep their existing desktop scale but reduce slightly on small screens. The AI prediction card now uses a single-column layout for its score tiles on phones and returns to two columns on larger screens.

## PWA Implementation

The website is now installable as a Progressive Web App (PWA). A PWA is a web application that can be installed on desktop, tablet, or phone and opened in a standalone window without the normal browser address bar.

The manifest file is located at `public/manifest.json` and uses the following core settings:

- `name`: `InfraGuard Server Room Supervision`
- `short_name`: `InfraGuard`
- `start_url`: `/dashboard`
- `scope`: `/`
- `display`: `standalone`
- `background_color`: `#f8fbff`
- `theme_color`: `#465fff`

The PWA still works normally in a browser. The standalone behavior only applies after the user installs it through the browser install option or the optional install button.

## Service Worker Caching Strategy

The service worker is intentionally conservative. It caches static assets such as icons, the manifest, the offline page, built JavaScript, built CSS, images, and fonts. It does not aggressively cache application pages or live data.

All `/api/*` routes are excluded from caching. This protects real-time endpoints such as:

- `/api/climate-readings/latest`
- `/api/ai-input/latest`
- `/api/ai-predictions`
- `/api/power-readings`
- `/api/sensor-readings`
- `/api/server-metrics`

This strategy protects the real-time behavior of the monitoring dashboard. Sensor readings, AI input, AI predictions, power readings, and server metrics should always come from the network.

## Laravel 419 Page Expired Protection

After the PWA work, the service worker was reviewed to prevent Laravel authentication pages from being cached or served from an offline fallback. A `419 Page Expired` error in Laravel usually means the Cross-Site Request Forgery (CSRF) token in the form is missing, expired, or does not match the current session. In a PWA, this can happen if an old login or registration page is served from cache with a stale CSRF token.

The auth Blade forms were checked and the relevant POST forms include `@csrf`, including login, register, logout, password reset, email verification, two-factor authentication, profile update, and profile deletion forms. The fix was therefore applied in the service worker.

The service worker cache version was incremented to `infraguard-static-v3` so old caches are removed during activation. The service worker now bypasses:

- `/login`
- `/register`
- `/logout`
- `/dashboard`
- password reset and verification paths
- `/two-factor`
- `/profile`
- `/admin`
- all `/api/*` routes
- every non-GET request
- credentialed or authenticated requests

This keeps authentication and dashboard navigation fully network-driven so Laravel can always generate fresh session pages and CSRF tokens. The service worker continues to cache only static assets such as CSS, JavaScript, icons, the manifest, and the offline page.

## Optional Install Button

A small `Install App` button was added to the authenticated topbar. It remains hidden by default. The button appears only when the browser fires the native PWA install prompt event. After installation or prompt dismissal, it hides again.

If the browser does not support the install prompt, the button stays hidden and the app remains installable through the browser's normal install menu when supported. In installed PWA mode, the button is removed or hidden so the interface no longer feels like a normal website.

## Public Live Monitoring Home Page

### Default Home View

The public home page now opens by default in a live monitoring view. Visitors who are not logged in can see display-safe server room information before authentication. The view includes cards for temperature, humidity, air flow, power usage, and AI predictive maintenance.

The previous landing page was not deleted. It remains in the same Blade file and can be displayed by clicking the `Switch` button.

### Switch Button Behavior

The public header includes a `Switch` button. By default, it shows `Switch to Landing View` because the live monitoring view is active. Clicking it hides the live monitoring panel and shows the original landing page design without a page reload. Clicking it again switches back to the live monitoring view.

Login, Register, and Dashboard buttons remain accessible in both modes.

### Public Read-Only API Endpoints

Three public read-only endpoints were added for the home page:

| Endpoint | Method | Purpose |
|---|---|---|
| `/api/public/climate-latest` | `GET` | Returns latest temperature, humidity, and air-flow display state. |
| `/api/public/power-latest` | `GET` | Returns latest power, voltage, current, status, and timestamp. |
| `/api/public/ai-prediction-latest` | `GET` | Returns the latest AI prediction values for the public AI card. |

These endpoints do not accept writes. They do not expose sensor tokens, private user data, or ingestion credentials. Protected POST routes such as `/api/sensor-readings`, `/api/power-readings`, and `/api/ai-predictions` still require the existing `X-Sensor-Token` header.

### JavaScript Polling Strategy

The public home page uses `fetch()` from `resources/js/app.js`. It polls the three public endpoints every 5 seconds and updates the cards without reloading the page.

The polling code:

- uses `cache: 'no-store'`;
- shows loading states before the first response;
- shows `Awaiting sensor` when a feed has no data;
- shows `Offline` or `Connection problem` when requests fail;
- avoids putting any secret token in frontend JavaScript;
- runs only when the public home page live cards exist.

### AI Card Visual Refinement

The dashboard AI prediction card and the public home AI prediction card now share a softer risk-based style:

- normal/stable: light cyan/green gradient with green-blue tone;
- warning: light amber/orange gradient with amber tone;
- critical: light rose/red gradient with red tone.

The existing AI content was preserved, including risk level, rack health score, failure probability, temperature, power usage, anomaly score, main cause, explanation, recommendation, and last update.

### Files Changed for Public Home Monitoring

| File | Purpose |
|---|---|
| `app/Http/Controllers/Api/PublicTelemetryController.php` | Provides safe public read-only telemetry endpoints. |
| `routes/api.php` | Registers `/api/public/*` GET routes. |
| `resources/views/welcome.blade.php` | Adds live monitoring view and Switch button while preserving the old landing design. |
| `resources/js/app.js` | Adds public home polling and no-reload view switching. |
| `resources/css/app.css` | Adds public live card styles and risk-based AI styling. |
| `resources/views/dashboard/partials/ai-prediction-card.blade.php` | Refines the authenticated dashboard AI card style. |

## PWA Native App Mode Improvements

### PWA Mode Detection

Installed app mode is detected in `resources/js/app.js` using `window.matchMedia('(display-mode: standalone)')`, `window.matchMedia('(display-mode: fullscreen)')`, and the iOS fallback `window.navigator.standalone === true`. When one of these checks is true, the JavaScript adds the class `pwa-standalone` to both `<html>` and `<body>`.

All native-app visual changes are scoped under `.pwa-standalone` or `@media (display-mode: standalone)`. This means the normal browser version keeps its current layout and behavior.

### Native App Chrome

In installed PWA mode, the topbar becomes slightly more app-like. A small `App Mode` badge appears beside the page section label, and the install button is hidden. Safe area padding is used on small screens so the app works better on devices with notches or system gesture areas.

The user/profile area, notifications, and important dashboard actions remain visible. Critical navigation is not removed on desktop. On small installed PWA screens, the mobile sidebar toggle is hidden because the PWA bottom navigation provides direct access to the most important sections.

### Mobile Bottom Navigation

The file `resources/views/layouts/pwa-bottom-nav.blade.php` adds a fixed native-style bottom navigation bar for installed PWA mode only. It appears only on small screens and only when `.pwa-standalone` is present.

The bottom navigation includes:

- Dashboard
- AI
- Reports
- Admin, shown only for Department Head users

The normal web browser version does not show this bottom navigation. Desktop PWA mode also keeps the normal sidebar experience.

### PWA-Only Responsive Improvements

In installed PWA mode, the main content receives extra bottom padding on small screens so dashboard cards are not hidden behind the fixed bottom navigation. Touch targets are kept large enough for app-style interaction. Cards and glass panels receive slightly tighter mobile radii, while the desktop design identity remains unchanged.

The AI prediction card and live metric cards keep the mobile wrapping improvements from the responsive update. These changes prevent text overlap and horizontal scrolling on phone-sized screens.

### Offline Fallback

The file `public/offline.html` provides a simple native-like fallback screen. It shows the InfraGuard icon, the title `InfraGuard Offline`, the message `Live sensor data requires an internet connection.`, a retry button, and a note explaining that real-time monitoring is unavailable offline.

The service worker returns this offline page only when a page navigation fails because the network is unavailable. It does not cache API responses and does not pretend that live telemetry is available offline.

### Native-Feeling Animations

PWA mode adds a subtle live pulsing dot to the `App Mode` badge. Critical red dots receive a soft pulse in installed mode only. The animations are lightweight and respect `prefers-reduced-motion: reduce`, which disables them for users who prefer less motion.

## How to Test

Run these commands from the `myapp` folder:

```bash
php artisan view:clear
php artisan cache:clear
npm run build
php artisan serve
```

Then test normal browser mode:

1. Open `http://127.0.0.1:8000/dashboard`.
2. Confirm the website looks almost unchanged.
3. Confirm the sidebar still works.
4. Confirm no bottom navigation appears in normal browser mobile mode.
5. Confirm the favicon appears in the browser tab.

Then test the public live home page:

1. Open `http://127.0.0.1:8000/`.
2. Confirm the live monitoring view appears by default.
3. Confirm temperature, humidity, air flow, power usage, and AI cards are visible without login.
4. Confirm the cards refresh every 5 seconds when the public API endpoints return data.
5. Confirm the `Switch` button shows the original landing page.
6. Click `Switch` again and confirm the live monitoring view returns.
7. Inspect frontend source and confirm no `X-Sensor-Token` value is present.

Then test PWA behavior:

1. Open `http://127.0.0.1:8000/manifest.json`.
2. Confirm the manifest loads and references the icons.
3. Open browser developer tools and confirm the service worker registers without errors.
4. Install the app from Chrome or Edge.
5. Open the installed app.
6. Confirm the app opens without the browser URL bar.
7. Confirm the `App Mode` badge appears.
8. On small installed PWA screens, confirm the bottom navigation appears.
9. Temporarily disconnect the network and navigate to a page to confirm the offline screen appears.

## Limitations

- The service worker is intentionally simple and does not provide full offline dashboard functionality.
- Live monitoring data is not cached because the system depends on real-time sensor updates.
- The install prompt is controlled by the browser, so the button appears only when the browser decides the app is installable.
- Some browsers, especially on iOS, may handle PWA installation differently from Chrome or Edge.
- PWA standalone detection is handled with current browser APIs, but exact behavior can vary by platform.

## Future Improvements

- Add manifest screenshots for richer install prompts.
- Add automated visual regression checks for mobile and tablet breakpoints.
- Add a dedicated cached app shell for non-sensitive static pages.
- Review every secondary page for the same level of mobile polish as the dashboard.
- Consider push notifications later if the production notification strategy requires it.
