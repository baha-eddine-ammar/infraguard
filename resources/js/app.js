/*
|--------------------------------------------------------------------------
| File Purpose
|--------------------------------------------------------------------------
| This is the main frontend bootstrap file loaded by Vite.
|
| What it does:
| - loads shared JS dependencies
| - registers Alpine components used by pages
| - defines global Alpine stores for theme and sidebar behavior
| - boots optional interactive pages such as the 3D landing scene
|
| FILES TO READ (IN ORDER):
| 1. resources/js/app.js
| 2. resources/js/calendar-workspace.js
| 3. resources/js/chat-workspace.js
| 4. resources/views/layouts/app.blade.php
*/
import './bootstrap';
import Alpine from 'alpinejs';

window.Alpine = Alpine;
window.SERVER_ROOM_THEME_KEY = 'tailadmin-theme-v1';

const escapeHtml = (value) => {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
};

const hideBrandPreloader = () => {
    const preloader = document.querySelector('[data-brand-preloader]');

    document.documentElement.classList.remove('brand-preloader-pending');

    if (!preloader) {
        return;
    }

    preloader.classList.add('is-complete');

    window.setTimeout(() => {
        preloader.remove();
    }, 400);
};

const isStandaloneDisplayMode = () => {
    return window.matchMedia('(display-mode: standalone)').matches
        || window.matchMedia('(display-mode: fullscreen)').matches
        || window.navigator.standalone === true;
};

const syncPwaStandaloneClass = () => {
    const isStandalone = isStandaloneDisplayMode();
    document.documentElement.classList.toggle('pwa-standalone', isStandalone);
    document.body?.classList.toggle('pwa-standalone', isStandalone);
};

syncPwaStandaloneClass();

const watchDisplayMode = (mediaQuery) => {
    const matcher = window.matchMedia(mediaQuery);

    if (typeof matcher.addEventListener === 'function') {
        matcher.addEventListener('change', syncPwaStandaloneClass);
        return;
    }

    if (typeof matcher.addListener === 'function') {
        matcher.addListener(syncPwaStandaloneClass);
    }
};

watchDisplayMode('(display-mode: standalone)');
watchDisplayMode('(display-mode: fullscreen)');

const registerServiceWorker = () => {
    if (document.body.hasAttribute('data-pages-preview') || !('serviceWorker' in navigator)) {
        return;
    }

    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch((error) => {
            console.warn('InfraGuard service worker registration failed.', error);
        });
    }, { once: true });
};

const setupPwaInstallPrompt = () => {
    const installButton = document.querySelector('[data-pwa-install-button]');

    if (!installButton) {
        return;
    }

    if (isStandaloneDisplayMode()) {
        installButton.remove();
        return;
    }

    let deferredPrompt = null;

    const hideInstallButton = () => {
        installButton.classList.add('hidden');
        installButton.classList.remove('inline-flex');
    };

    const showInstallButton = () => {
        installButton.classList.remove('hidden');
        installButton.classList.add('inline-flex');
    };

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredPrompt = event;
        showInstallButton();
    });

    window.addEventListener('appinstalled', () => {
        deferredPrompt = null;
        hideInstallButton();
    });

    installButton.addEventListener('click', async () => {
        if (!deferredPrompt) {
            return;
        }

        deferredPrompt.prompt();
        await deferredPrompt.userChoice.catch(() => null);
        deferredPrompt = null;
        hideInstallButton();
    });
};

const setupPublicHomeLiveMonitoring = () => {
    const livePanel = document.querySelector('[data-home-panel="live"]');
    const landingPanel = document.querySelector('[data-home-panel="landing"]');
    const switchButton = document.querySelector('[data-home-view-switch]');
    const cardsRoot = document.querySelector('[data-public-live-cards]');

    if (!livePanel || !landingPanel || !switchButton || !cardsRoot) {
        return;
    }

    let failedOnce = false;

    const endpoints = {
        climate: '/api/public/climate-latest',
        power: '/api/public/power-latest',
        ai: '/api/public/ai-prediction-latest',
    };

    const setText = (name, value) => {
        document.querySelectorAll(`[data-public-live-field="${name}"]`).forEach((element) => {
            element.textContent = value;
        });
    };

    const statusClasses = [
        'public-live-status--loading',
        'public-live-status--stable',
        'public-live-status--warning',
        'public-live-status--critical',
        'public-live-status--awaiting',
        'public-live-status--offline',
    ];

    const setStatus = (name, status, label = null) => {
        document.querySelectorAll(`[data-public-live-status="${name}"]`).forEach((element) => {
            element.classList.remove(...statusClasses);
            element.classList.add(`public-live-status--${status}`);
            element.textContent = label || status.replaceAll('_', ' ');
        });
    };

    const formatNumber = (value, digits = 1) => {
        const number = Number(value);
        return Number.isFinite(number) ? number.toFixed(digits) : null;
    };

    const formatRelative = (isoString) => {
        if (!isoString) {
            return 'No update yet';
        }

        const timestamp = new Date(isoString);
        const seconds = Math.max(0, Math.round((Date.now() - timestamp.getTime()) / 1000));

        if (!Number.isFinite(seconds)) {
            return 'Update time unavailable';
        }

        if (seconds < 60) {
            return `Updated ${seconds}s ago`;
        }

        const minutes = Math.round(seconds / 60);

        if (minutes < 60) {
            return `Updated ${minutes}m ago`;
        }

        const hours = Math.round(minutes / 60);
        return `Updated ${hours}h ago`;
    };

    const isLiveVisible = () => !livePanel.classList.contains('hidden');

    const setConnectionProblem = () => {
        ['temperature', 'humidity', 'power', 'ai'].forEach((name) => {
            setStatus(name, 'offline', 'Offline');
        });

        setText('temperature.updated', 'Connection problem');
        setText('humidity.updated', 'Connection problem');
        setText('power.updated', 'Connection problem');
        setText('ai.updated', 'Connection problem');
    };

    const updateClimate = (payload) => {
        const temperature = payload?.data?.temperature;
        const humidity = payload?.data?.humidity;
        const airflow = payload?.data?.air_flow;

        if (temperature?.value === null || temperature?.value === undefined) {
            setText('temperature.value', 'Awaiting sensor');
            setStatus('temperature', 'awaiting', 'Awaiting sensor');
        } else {
            setText('temperature.value', `${formatNumber(temperature.value, 1)} deg C`);
            setStatus('temperature', temperature.status || 'stable');
        }

        setText('temperature.updated', formatRelative(temperature?.recorded_at));

        if (humidity?.value === null || humidity?.value === undefined) {
            setText('humidity.value', 'Awaiting sensor');
            setStatus('humidity', 'awaiting', 'Awaiting sensor');
        } else {
            setText('humidity.value', `${formatNumber(humidity.value, 1)}%`);
            setStatus('humidity', humidity.status || 'stable');
        }

        setText('humidity.updated', formatRelative(humidity?.recorded_at));
        setText('airflow.value', airflow?.value === null || airflow?.value === undefined ? 'Awaiting sensor' : `${formatNumber(airflow.value, 1)} m/s`);
        setStatus('airflow', airflow?.status || 'awaiting', 'Awaiting sensor');
        setText('airflow.updated', airflow?.recorded_at ? formatRelative(airflow.recorded_at) : 'Feed not connected yet');
    };

    const updatePower = (payload) => {
        const power = payload?.data;

        if (!power) {
            setText('power.value', 'Awaiting sensor');
            setText('power.detail', 'Voltage/current unavailable');
            setText('power.updated', 'No update yet');
            setStatus('power', 'awaiting', 'Awaiting sensor');
            return;
        }

        setText('power.value', `${formatNumber(power.power_w, 0)} W`);
        setText('power.detail', `Voltage ${formatNumber(power.voltage_v, 1) || '--'} V / Current ${formatNumber(power.current_a, 2) || '--'} A`);
        setText('power.updated', formatRelative(power.recorded_at));
        setStatus('power', power.status || 'stable');
    };

    const updateAi = (payload) => {
        const prediction = payload?.data;
        const card = document.querySelector('[data-public-ai-card]');

        if (!prediction) {
            setStatus('ai', 'awaiting', 'Awaiting AI');
            setText('ai.health', '--');
            setText('ai.failure', '--');
            setText('ai.temperature', '--');
            setText('ai.power', '--');
            setText('ai.anomaly', '--');
            setText('ai.cause', 'Awaiting first prediction');
            setText('ai.explanation', 'The public dashboard will show the latest AI prediction after Laravel receives it.');
            setText('ai.recommendation', 'Continue monitoring live telemetry.');
            setText('ai.updated', 'No AI update yet');
            card?.setAttribute('data-risk-level', 'normal');
            return;
        }

        const riskLevel = prediction.risk_level || 'normal';
        card?.setAttribute('data-risk-level', riskLevel);
        setStatus('ai', riskLevel === 'normal' ? 'stable' : riskLevel, riskLevel);
        setText('ai.health', `${prediction.rack_health_score ?? '--'}%`);
        setText('ai.failure', `${prediction.failure_probability ?? '--'}%`);
        setText('ai.temperature', prediction.temperature === null ? '--' : `${formatNumber(prediction.temperature, 1)} deg C`);
        setText('ai.power', prediction.power_w === null ? '--' : `${formatNumber(prediction.power_w, 0)} W`);
        setText('ai.anomaly', prediction.anomaly_score === null ? '--' : formatNumber(prediction.anomaly_score, 2));
        setText('ai.cause', prediction.main_cause || 'Not provided');
        setText('ai.explanation', prediction.explanation || 'Not provided');
        setText('ai.recommendation', prediction.recommendation || 'Not provided');
        setText('ai.updated', formatRelative(prediction.created_at));
    };

    const fetchJson = async (url) => {
        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
            },
            cache: 'no-store',
        });

        if (!response.ok) {
            throw new Error(`Request failed with ${response.status}`);
        }

        return response.json();
    };

    const poll = async () => {
        if (!isLiveVisible()) {
            return;
        }

        try {
            const [climate, power, ai] = await Promise.all([
                fetchJson(endpoints.climate),
                fetchJson(endpoints.power),
                fetchJson(endpoints.ai),
            ]);

            updateClimate(climate);
            updatePower(power);
            updateAi(ai);
            failedOnce = false;
        } catch (error) {
            if (!failedOnce) {
                failedOnce = true;
            }

            setConnectionProblem();
        }
    };

    switchButton.addEventListener('click', () => {
        const showLanding = isLiveVisible();

        livePanel.classList.toggle('hidden', showLanding);
        landingPanel.classList.toggle('hidden', !showLanding);
        switchButton.textContent = showLanding ? 'Switch to Live View' : 'Switch to Landing View';

        if (!showLanding) {
            void poll();
        }
    });

    void poll();
    window.setInterval(() => {
        void poll();
    }, 5000);
};

document.addEventListener('alpine:init', () => {
    Alpine.store('theme', {
        theme: 'light',
        // Loads the saved theme from localStorage and applies it to <html>/<body>.
        init() {
            localStorage.removeItem('server-room-theme');
            localStorage.removeItem('theme');
            const savedTheme = localStorage.getItem(window.SERVER_ROOM_THEME_KEY);
            this.theme = savedTheme || 'light';
            this.apply();
        },
        // Used by the topbar theme toggle.
        toggle() {
            this.theme = this.theme === 'dark' ? 'light' : 'dark';
            localStorage.setItem(window.SERVER_ROOM_THEME_KEY, this.theme);
            this.apply();
        },
        // Synchronizes CSS classes and emits a custom event so charts can react.
        apply() {
            const html = document.documentElement;
            const body = document.body;

            if (this.theme === 'dark') {
                html.classList.add('dark');
                html.style.colorScheme = 'dark';
                body?.classList.add('dark');
            } else {
                html.classList.remove('dark');
                html.style.colorScheme = 'light';
                body?.classList.remove('dark');
            }

            window.dispatchEvent(new CustomEvent('theme-changed', {
                detail: { theme: this.theme },
            }));
        },
    });

    Alpine.store('sidebar', {
        open: window.innerWidth >= 1024,
        desktopCollapsed: false,
        // Desktop and mobile behavior differ, so this helper centralizes that rule.
        isDesktop() {
            return window.innerWidth >= 1024;
        },
        // Toggle either collapses the desktop sidebar or opens the mobile drawer.
        toggle() {
            if (this.isDesktop()) {
                this.desktopCollapsed = !this.desktopCollapsed;
                this.open = !this.desktopCollapsed;
                return;
            }

            this.open = !this.open;
        },
        // Closes the drawer after navigation on smaller screens.
        closeOnMobile() {
            if (!this.isDesktop()) {
                this.open = false;
            }
        },
        // Recomputes sidebar state after viewport changes.
        sync() {
            this.open = this.isDesktop() ? !this.desktopCollapsed : false;
        },
    });
});

// Loads extra scripts only on the pages that need them.
const bootInteractivePages = async () => {
    if (document.getElementById('server-3d')) {
        const { initServer3D } = await import('./server-3d');
        initServer3D();
    }

    if (document.querySelector('[data-server-rack-landing]')) {
        const { initServerRackLanding } = await import('./server-rack-landing');
        initServerRackLanding();
    }
};

// Heavy page-only dependencies are loaded lazily so the global app bundle
// stays smaller for routes that do not need charts or advanced workspace logic.
const registerPageDependencies = async () => {
    const pageModules = [];

    if (document.querySelector('[data-dashboard-page]')) {
        pageModules.push(
            import('apexcharts').then(({ default: ApexCharts }) => {
                window.ApexCharts = ApexCharts;
            })
        );
    }

    if (document.querySelector('[data-calendar-workspace]')) {
        pageModules.push(
            import('./calendar-workspace').then(({ default: calendarWorkspace }) => {
                Alpine.data('calendarWorkspace', calendarWorkspace);
            })
        );
    }

    if (document.querySelector('[data-chat-workspace]')) {
        pageModules.push(
            import('./chat-workspace').then(({ default: chatWorkspace }) => {
                Alpine.data('chatWorkspace', chatWorkspace);
            })
        );
    }

    await Promise.all(pageModules);
};

const startApplication = async () => {
    syncPwaStandaloneClass();

    const shouldBootBrandPreloader = document.documentElement.dataset.brandPreloaderState === 'active';

    const preloaderBoot = shouldBootBrandPreloader && document.querySelector('[data-brand-preloader]')
        ? import('./brand-preloader')
            .then(({ default: initBrandPreloader }) => initBrandPreloader())
            .catch((error) => {
                console.error('Unable to start the brand preloader.', error);
                hideBrandPreloader();
            })
        : Promise.resolve();

    await Promise.all([
        registerPageDependencies(),
        preloaderBoot,
    ]);

    Alpine.start();
    setupPwaInstallPrompt();
    setupPublicHomeLiveMonitoring();
    await bootInteractivePages();

    window.addEventListener('user-notification-created', (event) => {
        const payload = event.detail || {};
        const unreadCount = Number(payload.unreadCount || 0);
        const badgeHost = document.querySelector('[data-notification-button]');
        const unreadText = document.querySelector('[data-notification-unread-text]');
        const list = document.querySelector('[data-notification-list]');
        let badge = document.querySelector('[data-notification-count-badge]');

        if (unreadText) {
            unreadText.textContent = `${unreadCount} unread`;
        }

        if (badgeHost && unreadCount > 0 && !badge) {
            badge = document.createElement('span');
            badge.dataset.notificationCountBadge = 'true';
            badge.className = 'absolute right-2 top-2 inline-flex min-w-[1.15rem] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold text-white';
            badgeHost.appendChild(badge);
        }

        if (badge) {
            if (unreadCount > 0) {
                badge.textContent = unreadCount > 9 ? '9+' : String(unreadCount);
            } else {
                badge.remove();
            }
        }

        if (list && payload.notification) {
            const notification = payload.notification;
            const toneClass = {
                'maintenance.assigned': 'bg-amber-400',
                'chat.mentioned': 'bg-violet-500',
                'report.generated': 'bg-sky-400',
                'alert.critical': 'bg-rose-500',
                'user.approved': 'bg-emerald-400',
                'user.rejected': 'bg-rose-400',
            }[notification.type] || 'bg-brand-500';

            const item = document.createElement('a');
            item.href = notification.url || '/notifications';
            item.className = 'block w-full rounded-2xl border border-brand-100 bg-brand-50/70 px-4 py-3 text-left transition hover:-translate-y-0.5 hover:shadow-sm dark:border-brand-900/30 dark:bg-brand-500/10';
            item.innerHTML = `
                <div class="flex items-start gap-3">
                    <span class="mt-1 inline-flex h-2.5 w-2.5 rounded-full ${toneClass}"></span>
                    <div class="min-w-0">
                        <div class="flex items-center justify-between gap-3">
                            <p class="truncate font-medium text-gray-900 dark:text-white">${escapeHtml(notification.title ?? 'Notification')}</p>
                            <span class="text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-400 dark:text-gray-500">Now</span>
                        </div>
                        ${notification.body ? `<p class="mt-1 line-clamp-2 text-sm leading-6 text-gray-500 dark:text-gray-400">${escapeHtml(notification.body)}</p>` : ''}
                    </div>
                </div>
            `;

            list.prepend(item);

            while (list.children.length > 6) {
                list.removeChild(list.lastElementChild);
            }
        }
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        registerServiceWorker();
        void startApplication();
    }, { once: true });
} else {
    registerServiceWorker();
    void startApplication();
}
