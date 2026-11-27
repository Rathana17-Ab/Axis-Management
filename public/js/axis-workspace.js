// =====================================================================
// EOT CoreSystem — Axis Project Management workspace (Axis Suite v4.5)
// State is hydrated from Laravel (`window.AxisBootstrap`, rendered by
// resources/views/axis/workspace.blade.php) and every mutation is
// applied optimistically, then persisted through `window.AxisAPI`.
// =====================================================================
const AXIS_BOOT = window.AxisBootstrap || {};

// 1. Hydrate from the Laravel controller instead of hardcoded arrays
const HANDLERS = AXIS_BOOT.handlers || [];
let projects = AXIS_BOOT.projects || [];
let ctoReports = AXIS_BOOT.ctoReports || [];
let teamMembers = AXIS_BOOT.teamMembers || [];
let departments = AXIS_BOOT.departments || [];
let positions = AXIS_BOOT.positions || [];
// CTO model: Project (e.g. EOT Axis) → Module (`projects` above) → Task, assigned to an employee by a leader.
let axisProjects = AXIS_BOOT.axisProjects || [];

// Sales workspace access state, hydrated by AxisWorkspaceController.
let workspaceRoles = AXIS_BOOT.roles || [];
let workspaceTeams = AXIS_BOOT.teams || [];
let workQueues = AXIS_BOOT.workQueues || [];
let recordShares = AXIS_BOOT.recordShares || [];
let featureUpgrades = AXIS_BOOT.featureUpgrades || [];
const workspacePermissionKeys = AXIS_BOOT.permissions || [];
const workspaceIsSignedIn = AXIS_BOOT.isSignedIn === true;
const hasAssignedWorkspaceRole = (AXIS_BOOT.roleKeys || []).length > 0;
// Role-less members keep the legacy open console only while signed in; guests get no admin abilities.
const workspaceCan = permission => workspaceIsSignedIn && (!hasAssignedWorkspaceRole || workspacePermissionKeys.includes(permission));
const canViewQueues = () => workspaceCan('queues.view_all') || workspaceCan('queues.view_shared');
const canViewSharing = () => workspaceCan('records.view_all') || workspaceCan('records.view_shared');
const canViewP18 = () => workspaceCan('p18.view');
const canManageQueues = () => workspaceCan('work_queues.manage');
const canManageRecords = () => workspaceCan('records.manage_all');
const canReorderModules = AXIS_BOOT.canReorderModules !== false;
const canManageP18 = () => workspaceCan('p18.manage');
const canManageAdministration = () => workspaceCan('administration.access');

// Only active employees can be given work; `keepIds` keeps a currently assigned (now inactive) person selectable.
const employees = (keepIds = []) => teamMembers
    .filter(m => m.status !== 'inactive' || keepIds.includes(m.id))
    .sort((a, b) => a.name.localeCompare(b.name));
const leaders = (keepIds = []) => employees(keepIds).filter(m => m.position === 'leader');
const developers = (keepIds = []) => employees(keepIds).filter(m => m.position === 'developer');
// Shared status for projects, modules and tasks (CTO sheet).
const WORK_STATUSES = ['Not yet started', 'Processing', 'Finished'];
const workStatusOptions = current => WORK_STATUSES
    .map(status => `<option value="${status}" ${current === status ? 'selected' : ''}>${status}</option>`).join('');
const employeeName = id => (teamMembers.find(m => m.id === Number(id)) || {}).name || '';

projects.forEach(p => (p.tasks || []).forEach(t => { if (!Array.isArray(t.issues)) t.issues = []; }));

// =====================================================================
// HELPER: ESCAPE USER-SUPPLIED TEXT BEFORE INTERPOLATING INTO innerHTML
// =====================================================================
function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

/** Encode a value as a JS string literal safe inside an inline on*="" attribute. */
function jsArg(value) {
    return escapeHtml(JSON.stringify(String(value ?? '')));
}

// =====================================================================
// 2. UNIVERSAL CSRF FETCH WRAPPER (`window.AxisAPI`)
// =====================================================================
window.AxisAPI = {
    routes: AXIS_BOOT.routes || {},
    debounceTimers: {},

    /** Build an endpoint URL from a named route template, e.g. url('tasks.update', 102). */
    url(name, id) {
        const tpl = this.routes[name];
        if (!tpl) throw new Error(`Unknown Axis route: ${name}`);
        return id === undefined ? tpl : tpl.replace('__ID__', encodeURIComponent(id));
    },

    pending: 0,
    lastFailedAt: null,

    /** Broadcast save activity so the UI (context bar "Up to date" pill) reflects real sync state. */
    notifySync() {
        document.dispatchEvent(new CustomEvent('axis:sync', { detail: { pending: this.pending, failed: this.lastFailedAt !== null } }));
    },

    async request(url, method = 'POST', payload = {}) {
        const isWrite = method !== 'GET';
        if (isWrite) { this.pending++; this.notifySync(); }
        try {
            const body = await this.send(url, method, payload);
            if (isWrite) this.lastFailedAt = null;
            return body;
        } catch (err) {
            if (isWrite && err.status !== 422) this.lastFailedAt = Date.now();
            throw err;
        } finally {
            if (isWrite) { this.pending--; this.notifySync(); }
        }
    },

    async send(url, method, payload) {
        const res = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: method === 'GET' ? undefined : JSON.stringify(payload)
        });
        if (res.status === 204) return null;
        const body = await res.json().catch(() => null);
        if (!res.ok) {
            const firstError = body && body.errors ? Object.values(body.errors)[0]?.[0] : null;
            const err = new Error(firstError || (body && body.message) || `HTTP ${res.status}`);
            err.status = res.status;
            err.body = body;
            throw err;
        }
        return body;
    },

    /**
     * Resolve the server id of an entity. Entities created optimistically carry a
     * `_pending` promise until the server responds with their real id.
     */
    async idOf(entity) {
        if (entity && entity._pending) await entity._pending;
        if (entity && entity._failed) throw new Error('This item was never saved to the server');
        return entity.id;
    },

    /** Persist after the user pauses typing/scrubbing (per entity + field key). */
    debounce(key, fn, wait = 450) {
        clearTimeout(this.debounceTimers[key]);
        this.debounceTimers[key] = setTimeout(fn, wait);
    },

    /** Uniform failure feedback; `rollback` restores the optimistic change. */
    fail(err, rollback) {
        console.error('[AxisAPI]', err);
        if (typeof rollback === 'function') rollback();
        showToast(`Couldn't save: ${err.message || 'network error'}`);
    }
};

/**
 * Fields the server derives from an update (status side-effects & the server-accurate chronometer).
 * User-editable fields are NOT merged back, so an in-flight debounced edit of another field
 * (e.g. typing a task name while a timer toggle response arrives) is never clobbered.
 */
const SERVER_DERIVED_TASK_FIELDS = ['status', 'progress', 'running', 'actSeconds'];

/** Merge the authoritative server copy into a local task (`full` for newly created tasks). */
function mergeServerTask(t, serverTask, full = false) {
    if (!t || !serverTask) return t;
    if (full) {
        const keepIssues = t.issues;
        Object.assign(t, serverTask);
        if (!Array.isArray(serverTask.issues)) t.issues = keepIssues || [];
        return t;
    }
    SERVER_DERIVED_TASK_FIELDS.forEach(field => {
        if (field in serverTask) t[field] = serverTask[field];
    });
    return t;
}

function findTaskById(taskId) {
    for (const p of projects) {
        const t = (p.tasks || []).find(x => x.id === taskId);
        if (t) return t;
    }
    return null;
}

let activeProjectId = AXIS_BOOT.activeProjectId || (projects[0] ? projects[0].id : null);
let currentUser = teamMembers.find(m => m.id === AXIS_BOOT.currentUserId) || teamMembers[0] || { name: 'Guest', position: 'Developer', email: '', active: true };
let tickerId = null;
let taskIssueFilter = 'all'; // 'all' | 'running' | 'open' | 'resolved'
let taskDetailViewMode = 'table'; // 'table' | 'cards'
let activeDrawerTaskId = (() => {
    const p = projects.find(x => x.id === activeProjectId);
    const withIssues = p ? p.tasks.find(t => t.issues.length > 0) : null;
    return withIssues ? withIssues.id : (p && p.tasks[0] ? p.tasks[0].id : null);
})();
let drawerFilterMode = 'all';

// =====================================================================
// HELPER: FORMAT SECONDS TO HH:MM:SS
// =====================================================================
function formatHMS(totalSeconds) {
    const sec = Math.max(0, parseInt(totalSeconds, 10) || 0);
    const h = Math.floor(sec / 3600);
    const m = Math.floor((sec % 3600) / 60);
    const s = sec % 60;
    return [h, m, s].map(v => String(v).padStart(2, '0')).join(':');
}

function computeProjectCompletion(proj) {
    if (!proj.tasks || proj.tasks.length === 0) return 0;
    const total = proj.tasks.reduce((acc, t) => acc + (parseFloat(t.progress) || 0), 0);
    return +(total / proj.tasks.length).toFixed(2);
}

// =====================================================================
// BULLETPROOF GLOBAL PAGE ROUTER (`window.switchPage`)
// =====================================================================
let activePageId = 'projects';

window.updateDynamicHeader = function(pageId) {
    activePageId = pageId || activePageId || 'projects';
    // Fallback keeps the header working when no module exists yet (fresh, empty workspace)
    const activeProj = projects.find(p => p.id === activeProjectId) || projects[0] || { id: '', code: '', name: 'Tasks', tasks: [] };

    const headerConfig = {
        'dashboard': {
            section: 'Overview',
            route: 'admin/dashboard',
            title: 'Dashboard',
            badge: 'Axis v4.5',
            searchPlaceholder: 'Search projects…',
            createLabel: 'New module',
            createModal: 'modal-add-project'
        },
        'profile': {
            section: 'Account',
            route: 'admin/profile',
            title: 'Profile',
            badge: currentUser.positionName || currentUser.title || 'Member',
            searchPlaceholder: 'Search your workspace…'
        },
        'projects': {
            section: 'Project Management',
            route: 'admin/project',
            title: 'Projects',
            badge: `${axisProjects.length} project${axisProjects.length === 1 ? '' : 's'}`,
            searchPlaceholder: 'Search projects…',
            createLabel: 'New project',
            createModal: 'drawer-project-create'
        },
        'modules': {
            section: moduleProjectFilterName() || 'Project Management',
            route: 'admin/modules',
            title: 'Modules',
            badge: `${projects.length} module${projects.length === 1 ? '' : 's'}`,
            searchPlaceholder: 'Search modules…',
            createLabel: 'New module',
            createModal: 'modal-add-project'
        },
        'task-detail': {
            section: activeProj.projectName || 'Project Management',
            route: `admin/project-task-list/${activeProj.id}?code=${activeProj.code}`,
            title: `${activeProj.name} · Tasks`,
            badge: `${activeProj.code} · ${(activeProj.tasks || []).length} tasks`,
            searchPlaceholder: `Search ${activeProj.name} tasks…`,
            createLabel: 'New task',
            createModal: 'modal-create-task'
        },
        'reports': {
            section: 'Reports',
            route: 'admin/cto-reports',
            title: 'Reports',
            badge: `${ctoReports.length} reports`,
            searchPlaceholder: 'Search reports…',
            createLabel: 'New report',
            createModal: 'modal-report'
        },
        'team': {
            section: 'Admin',
            route: 'admin/user-management',
            title: 'Team',
            badge: `${teamMembers.length} people`,
            searchPlaceholder: 'Search people…',
            createLabel: 'Add person',
            createModal: 'modal-team-member'
        },
        'roles': {
            section: 'Administration',
            route: 'admin/roles-teams',
            title: 'Administration',
            badge: `${workspaceRoles.length} roles`,
            searchPlaceholder: 'Search roles or teams…'
        },
        'work-queues': {
            section: 'Sales operations',
            route: 'admin/work-queues',
            title: 'Work queues',
            badge: `${workQueues.length} queues`,
            searchPlaceholder: 'Search queues…'
        },
        'sharing': {
            section: 'Sales operations',
            route: 'admin/record-sharing',
            title: 'Record sharing',
            badge: `${recordShares.length} shares`,
            searchPlaceholder: 'Search shared records…'
        },
        'p18': {
            section: 'Sales operations',
            route: 'admin/p18',
            title: 'Access & upgrades',
            badge: `${featureUpgrades.length} features`,
            searchPlaceholder: 'Search features…'
        }
    };

    const cfg = headerConfig[activePageId] || headerConfig['projects'];

    const secEl = document.getElementById('header-section-label');
    const titleEl = document.getElementById('header-page-title');
    const badgeEl = document.getElementById('header-page-badge');
    const createLbl = document.getElementById('header-create-label');
    const hdrModSelect = document.getElementById('header-module-select');

    if (secEl) secEl.textContent = cfg.section;
    if (titleEl) titleEl.textContent = cfg.title;
    if (badgeEl) badgeEl.textContent = cfg.badge;
    if (createLbl) createLbl.textContent = cfg.createLabel;
    const createBtn = document.getElementById('header-create-btn');
    if (createBtn) {
        createBtn.title = cfg.createLabel;
        createBtn.setAttribute('aria-label', cfg.createLabel);
        createBtn.classList.toggle('hidden', !['dashboard', 'projects', 'modules', 'task-detail', 'reports', 'team'].includes(activePageId));
    }

    if (hdrModSelect) {
        hdrModSelect.value = activePageId === 'task-detail' ? String(activeProj.id) : '';
    }
};


// =====================================================================
// GLOBAL SEARCH / COMMAND PALETTE (`AxisSearch`) — ⌘K / Ctrl+K
// Searches pages, projects, tasks, issue notes,
// models, reports and people, plus quick actions. All data comes
// from the live client state, so it always reflects unsaved-to-reload edits.
// Recent choices are validated against that state; stale links from an older
// workspace/project are discarded instead of pointing at current records.
// =====================================================================
const IS_MAC = /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent);

const AxisSearch = (() => {
    const RECENT_KEY = 'axis.search.recent.v3';
    const LEGACY_RECENT_KEYS = ['axis.search.recent.v2'];
    const PER_GROUP = 6;
    const TYPE_STYLE = {
        page: { icon: 'fa-compass', cls: 'bg-slate-500/15 text-slate-200 border-white/10', label: 'Page' },
        action: { icon: 'fa-bolt', cls: 'bg-sky-500/15 text-sky-300 border-sky-400/25', label: 'Action' },
        project: { icon: 'fa-layer-group', cls: 'bg-cyan-500/15 text-cyan-300 border-cyan-400/25', label: 'Project' },
        module: { icon: 'fa-briefcase', cls: 'bg-sky-500/15 text-sky-300 border-sky-400/25', label: 'Module' },
        task: { icon: 'fa-list-check', cls: 'bg-sky-500/15 text-sky-300 border-sky-400/25', label: 'Task' },
        issue: { icon: 'fa-bug', cls: 'bg-amber-500/15 text-amber-300 border-amber-400/25', label: 'Note' },
        report: { icon: 'fa-file-lines', cls: 'bg-teal-500/15 text-teal-300 border-teal-400/25', label: 'Report' },
        person: { icon: 'fa-user', cls: 'bg-rose-500/15 text-rose-300 border-rose-400/25', label: 'Person' },
    };
    const GROUP_TITLES = {
        recent: 'Recent', action: 'Quick actions', page: 'Go to', project: 'Projects', module: 'Modules', task: 'Tasks', issue: 'Issue notes',
        report: 'Reports', person: 'People',
    };
    const GROUP_ORDER = ['recent', 'action', 'project', 'module', 'task', 'issue', 'person', 'report', 'page'];

    let results = [];
    let activeIndex = 0;
    let returnFocus = null;

    try { LEGACY_RECENT_KEYS.forEach(key => localStorage.removeItem(key)); } catch (_) { /* storage unavailable */ }

    const el = id => document.getElementById(id);
    const normalize = v => String(v ?? '').toLowerCase().normalize('NFKD').replace(/[̀-ͯ]/g, '');

    /** Build the searchable index from current state. Each entry: { type, key, title, subtitle, haystack, run }. */
    function buildIndex() {
        const items = [];
        const pages = [
            ['dashboard', 'Dashboard', 'Overview of every project'],
            ['projects', 'Projects', 'Every project and its modules'],
            ['modules', 'Modules', 'All modules and their progress'],
            ['reports', 'Reports', 'Daily & weekly reports'],
            ['team', 'Team', 'People and sign-in access'],
        ];
        pages.forEach(([id, title, subtitle]) => items.push({
            type: 'page', key: id, title, subtitle, haystack: `${title} ${subtitle}`,
            run: () => switchPage(id),
        }));

        const activeProj = projects.find(p => p.id === activeProjectId);
        [
            ['new-project', 'New project', 'Create a project that groups modules', () => openModal('drawer-project-create')],
            ['new-module', 'New module', 'Add a module to a project', () => openModal('modal-add-project')],
            ['new-task', 'New task', activeProj ? `Add a task to ${activeProj.name}` : 'Add a task to the current project', () => {
                if (activeProj) openProjectTaskList(activeProj.id);
                openModal('modal-create-task');
            }],
            ['add-person', 'Add a person', 'Invite someone to the team', () => openModal('modal-team-member')],
            ['new-report', 'Send a report', 'Daily or weekly update', () => openModal('modal-report')],
        ].forEach(([key, title, subtitle, run]) => items.push({ type: 'action', key, title, subtitle, haystack: `${title} ${subtitle}`, run }));

        axisProjects.forEach(ap => items.push({
            type: 'project', key: ap.id, title: ap.name,
            subtitle: [`${modulesOfProject(ap.id).length} module${modulesOfProject(ap.id).length === 1 ? '' : 's'}`, ap.status, ap.description].filter(Boolean).join(' · '),
            meta: `${Math.round(axisProjectProgress(ap.id))}%`,
            haystack: [ap.name, ap.description, ap.status].join(' '),
            run: () => openModulesOfProject(ap.id),
        }));

        projects.forEach(p => {
            items.push({
                type: 'module', key: p.id, title: p.name,
                subtitle: `${p.code} · ${[p.customer, (p.customerSub || '').replace(/^\((.*)\)$/, '$1')].filter(Boolean).join(', ')} · Lead: ${p.manager}`,
                meta: `${Math.round(computeProjectCompletion(p))}%`,
                haystack: [p.name, p.code, p.customer, p.customerSub, p.manager, p.product, ...(p.team || [])].join(' '),
                team: (p.team || []).filter(m => m !== p.manager),
                run: () => openProjectTaskList(p.id),
            });
            (p.tasks || []).forEach(t => {
                items.push({
                    type: 'task', key: t.id, title: t.name,
                    subtitle: `${p.name} · ${t.handler} · ${t.status}`,
                    meta: `${Math.round(t.progress)}%`,
                    haystack: [t.name, t.description, t.handler, p.name, p.code, `task-${t.id}`].join(' '),
                    run: () => focusTaskRow(p.id, t.id),
                });
                (t.issues || []).forEach(i => items.push({
                    type: 'issue', key: i.id, title: i.encountered,
                    subtitle: `${t.name} · ${p.name} · ${i.status}`,
                    haystack: [i.encountered, i.resolution, i.type, i.author, t.name, p.name].join(' '),
                    run: () => { openProjectTaskList(p.id); openDevIssueDrawer(t.id); },
                }));
            });
        });

        ctoReports.forEach(r => items.push({
            type: 'report', key: r.id, title: r.summary, subtitle: `${r.type} · ${r.handler} · ${r.module} · ${r.date}`,
            haystack: [r.summary, r.handler, r.module, r.blocker, r.type].join(' '), run: () => switchPage('reports'),
        }));

        const people = new Map();
        teamMembers.forEach(m => people.set(m.name, [m.title, m.position === 'leader' ? 'Leader' : null, m.department, m.email].filter(Boolean).join(' · ')));
        HANDLERS.forEach(h => { if (!people.has(h)) people.set(h, 'Engineer'); });
        people.forEach((subtitle, name) => {
            const count = projects.filter(p => p.manager === name || (p.team || []).includes(name)).length;
            items.push({
                type: 'person', key: name, title: name, subtitle,
                meta: count ? `${count} project${count === 1 ? '' : 's'}` : '',
                haystack: `${name} ${subtitle}`,
                run: () => {
                    switchPage('modules');
                    const search = el('flt-search');
                    if (search) { search.value = name; renderProjectsTable(true); }
                },
            });
        });

        return items;
    }

    /** Every word must match; earlier / title / word-start matches rank higher. */
    function score(item, words, phrase) {
        const title = normalize(item.title);
        const hay = normalize(`${item.title} ${item.haystack}`);
        if (!words.every(w => hay.includes(w))) return 0;
        let s = 1;
        if (title === phrase) s += 100;
        else if (title.startsWith(phrase)) s += 60;
        else if (title.includes(phrase)) s += 30;
        words.forEach(w => {
            if (title.includes(w)) s += 10;
            if (new RegExp(`(^|\\s|[^a-z0-9])${w.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}`).test(title)) s += 6;
        });
        return s;
    }

    function highlight(text, words) {
        let html = escapeHtml(text);
        words.filter(Boolean).sort((a, b) => b.length - a.length).forEach(w => {
            const safe = escapeHtml(w).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            html = html.replace(new RegExp(`(${safe})(?![^<]*>)`, 'gi'), '<mark>$1</mark>');
        });
        return html;
    }

    function writeRecent(entries) {
        try { localStorage.setItem(RECENT_KEY, JSON.stringify(entries)); } catch (_) { /* storage unavailable: recent list is a convenience only */ }
    }

    /**
     * Recent entries are only kept when their key and title still match live
     * workspace data. Otherwise an old project could inherit a reused id and
     * appear to be linked to this project.
     */
    function readRecent(index = null) {
        try {
            const entries = JSON.parse(localStorage.getItem(RECENT_KEY) || '[]');
            if (!index) return entries;

            const valid = entries.filter(r => index.some(i =>
                i.type === r.type && String(i.key) === String(r.key) && i.title === r.title));

            if (valid.length !== entries.length) writeRecent(valid);
            return valid;
        } catch (_) { return []; }
    }

    function remember(item) {
        if (item.type === 'action') return;
        const entry = { type: item.type, key: item.key, title: item.title };
        const next = [entry, ...readRecent().filter(r => !(r.type === item.type && String(r.key) === String(item.key)))].slice(0, 5);
        writeRecent(next);
    }

    function search(query) {
        const index = buildIndex();
        const recent = readRecent(index);
        const phrase = normalize(query).trim();
        if (!phrase) {
            const recentItems = recent
                .map(r => index.find(i => i.type === r.type && String(i.key) === String(r.key) && i.title === r.title))
                .filter(Boolean)
                .map(i => ({ ...i, group: 'recent' }));
            return [
                ...recentItems,
                ...index.filter(i => i.type === 'action').slice(0, 4).map(i => ({ ...i, group: 'action' })),
                ...index.filter(i => i.type === 'page').map(i => ({ ...i, group: 'page' })),
            ];
        }
        const words = phrase.split(/\s+/).filter(Boolean);
        const scored = index.map(i => ({ ...i, group: i.type, s: score(i, words, phrase) })).filter(i => i.s > 0);
        // Explain project matches that came from a teammate (not visible in the default subtitle)
        scored.forEach(i => {
            if (i.type !== 'project' || !i.team) return;
            const visible = normalize(`${i.title} ${i.subtitle}`);
            if (words.every(w => visible.includes(w))) return;
            const member = i.team.find(m => words.every(w => normalize(`${visible} ${m}`).includes(w)) && words.some(w => normalize(m).includes(w)));
            if (member) i.subtitle += ` · Team: ${member}`;
        });
        const out = [];
        GROUP_ORDER.forEach(g => {
            out.push(...scored.filter(i => i.group === g).sort((a, b) => b.s - a.s).slice(0, PER_GROUP));
        });
        // best overall match leads its own group order
        return out;
    }

    function render() {
        const query = el('axis-palette-input').value;
        const words = normalize(query).trim().split(/\s+/).filter(Boolean);
        results = search(query);
        activeIndex = Math.min(activeIndex, Math.max(0, results.length - 1));
        const box = el('axis-palette-results');

        if (results.length === 0) {
            box.innerHTML = `<div class="axis-palette__empty"><i class="fa-solid fa-magnifying-glass text-slate-500 text-lg mb-2 block"></i>No results for “${escapeHtml(query)}”.<div class="text-xs text-slate-500 mt-1">Try a project code, a person's name, or part of a task.</div></div>`;
            el('axis-palette-count').textContent = 'No results';
            el('axis-palette-input').removeAttribute('aria-activedescendant');
            return;
        }

        let html = '';
        let lastGroup = null;
        results.forEach((item, i) => {
            if (item.group !== lastGroup) {
                html += `<div class="axis-palette__group" role="presentation">${GROUP_TITLES[item.group]}</div>`;
                lastGroup = item.group;
            }
            const style = TYPE_STYLE[item.type];
            html += `<div id="axis-palette-opt-${i}" class="axis-palette__item" role="option" aria-selected="${i === activeIndex}" data-index="${i}">
                <span class="axis-palette__icon ${style.cls}" aria-hidden="true"><i class="fa-solid ${style.icon}"></i></span>
                <span class="min-w-0 flex-1">
                    <span class="axis-palette__title block">${highlight(item.title, words)}</span>
                    ${item.subtitle ? `<span class="axis-palette__subtitle block">${highlight(item.subtitle, words)}</span>` : ''}
                </span>
                <span class="axis-palette__meta">${escapeHtml(item.meta || (item.group === 'recent' ? style.label : ''))}</span>
            </div>`;
        });
        box.innerHTML = html;
        const matches = results.filter(r => r.group !== 'recent' && r.group !== 'action' && r.group !== 'page').length;
        el('axis-palette-count').textContent = query.trim() ? `${results.length} result${results.length === 1 ? '' : 's'}` : (matches ? '' : 'Type to search everything');
        syncActive(false);
    }

    function syncActive(scroll = true) {
        const box = el('axis-palette-results');
        box.querySelectorAll('[role="option"]').forEach(opt => opt.setAttribute('aria-selected', String(Number(opt.dataset.index) === activeIndex)));
        const active = el(`axis-palette-opt-${activeIndex}`);
        if (active) {
            el('axis-palette-input').setAttribute('aria-activedescendant', active.id);
            if (scroll) active.scrollIntoView({ block: 'nearest' });
        }
    }

    function choose(index) {
        const item = results[index];
        if (!item) return;
        remember(item);
        close(false);
        // run after the dialog has released focus so drawers/inputs can take it
        setTimeout(() => item.run(), 0);
    }

    function open() {
        const dialog = el('axis-command-palette');
        if (!dialog || !dialog.hidden) return;
        if (document.documentElement.classList.contains('axis-drawer-open')) return;
        returnFocus = document.activeElement;
        activeIndex = 0;
        const input = el('axis-palette-input');
        input.value = '';
        render();
        dialog.hidden = false;
        void dialog.offsetWidth;
        dialog.classList.add('is-open');
        document.documentElement.classList.add('axis-search-open');
        input.focus();
    }

    function close(restoreFocus = true) {
        const dialog = el('axis-command-palette');
        if (!dialog || dialog.hidden) return;
        dialog.classList.remove('is-open');
        document.documentElement.classList.remove('axis-search-open');
        setTimeout(() => { if (!dialog.classList.contains('is-open')) dialog.hidden = true; }, 180);
        if (restoreFocus && returnFocus && document.contains(returnFocus)) returnFocus.focus({ preventScroll: true });
    }

    function isOpen() {
        const dialog = el('axis-command-palette');
        return !!dialog && !dialog.hidden && dialog.classList.contains('is-open');
    }

    document.addEventListener('DOMContentLoaded', () => {
        const hint = el('header-search-shortcut');
        if (hint) hint.textContent = IS_MAC ? '⌘K' : 'Ctrl K';
        const input = el('axis-palette-input');
        if (!input) return;
        input.addEventListener('input', () => { activeIndex = 0; render(); });
        input.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                if (!results.length) return;
                activeIndex = (activeIndex + (e.key === 'ArrowDown' ? 1 : -1) + results.length) % results.length;
                syncActive();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                choose(activeIndex);
            } else if (e.key === 'Tab') {
                e.preventDefault(); // keep focus inside the dialog
            }
        });
        const box = el('axis-palette-results');
        box.addEventListener('mousemove', (e) => {
            const opt = e.target.closest('[role="option"]');
            if (opt && Number(opt.dataset.index) !== activeIndex) { activeIndex = Number(opt.dataset.index); syncActive(false); }
        });
        box.addEventListener('click', (e) => {
            const opt = e.target.closest('[role="option"]');
            if (opt) choose(Number(opt.dataset.index));
        });
    });

    document.addEventListener('keydown', (e) => {
        const isShortcut = (IS_MAC ? e.metaKey : e.ctrlKey) && !e.shiftKey && !e.altKey && e.key.toLowerCase() === 'k';
        if (isShortcut) {
            // Drawers trap focus; don't pull focus out from under an open form
            if (document.documentElement.classList.contains('axis-drawer-open')) return;
            e.preventDefault();
            isOpen() ? close() : open();
            return;
        }
        if (e.key === 'Escape' && isOpen()) {
            e.preventDefault();
            e.stopPropagation();
            close();
        }
    });

    return { open, close, isOpen };
})();

window.openCommandPalette = () => AxisSearch.open();
window.closeCommandPalette = () => AxisSearch.close();

/** Open a project's task list and draw attention to one task row. */
function focusTaskRow(projectId, taskId) {
    openProjectTaskList(projectId);
    const tableSearch = document.getElementById('task-inline-search');
    if (tableSearch && tableSearch.value) { tableSearch.value = ''; renderTasksTableRows(); }
    requestAnimationFrame(() => {
        const row = document.querySelector(`#tasks-tbody [data-task-row="${taskId}"]`);
        if (!row) return;
        row.scrollIntoView({ behavior: prefersReducedMotion() ? 'auto' : 'smooth', block: 'center' });
        row.classList.remove('axis-row-flash');
        void row.offsetWidth;
        row.classList.add('axis-row-flash');
    });
}

window.triggerHeaderContextCreate = function() {
    // Opens the create drawer for the current page straight away (no button text swap: it is an icon button)
    const openTargetModal = () => {
        if (activePageId === 'task-detail') {
            openModal('modal-create-task');
        } else if (activePageId === 'projects') {
            openModal('drawer-project-create');
        } else if (activePageId === 'reports') {
            openModal('modal-report');
        } else if (activePageId === 'team') {
            openModal('modal-team-member');
        } else {
            openModal('modal-add-project');
        }
    };
    openTargetModal();
};

/** Breadcrumb section link: back to the current project's modules, or to the projects list. */
window.openHeaderSection = function() {
    const projectId = activePageId === 'task-detail'
        ? (projects.find(p => p.id === activeProjectId) || {}).projectId
        : activePageId === 'modules' ? Number(document.getElementById('flt-project')?.value) || null : null;
    if (projectId) {
        openModulesOfProject(projectId);
    } else if (['projects', 'modules', 'task-detail'].includes(activePageId)) {
        switchPage('projects');
    }
};

window.switchPage = function(pageId, history = true) {
    // Server-rendered flags decide which pages this session may open.
    const guardedPages = {
        roles: canManageAdministration,
        'work-queues': canViewQueues,
        sharing: canViewSharing,
        p18: canViewP18,
    };
    if (guardedPages[pageId] && !guardedPages[pageId]()) {
        if (typeof showToast === 'function') showToast('You do not have permission to open that section.');
        pageId = 'dashboard';
    }

    // The task list belongs to a module; with none yet, guide the user to create one first
    if (pageId === 'task-detail' && projects.length === 0) {
        if (typeof showToast === 'function') showToast('Create a project and a module first, then add tasks to it.');
        pageId = 'projects';
    }
    const allPages = document.querySelectorAll('.app-page');
    allPages.forEach(p => p.classList.remove('active-page'));

    const target = document.getElementById('page-' + pageId);
    if (target) {
        target.classList.add('active-page');
    }

    // Ensure only the appropriate navigation item appears active
    document.querySelectorAll('.sidebar-link').forEach(btn => {
        btn.classList.remove('nav-active');
    });
    const activeNav = document.getElementById('nav-' + pageId);
    if (activeNav) {
        activeNav.classList.add('nav-active');
    }


    // Trigger fresh render for the destination page
    if (pageId === 'dashboard') {
        renderDashboard();
    }
    if (pageId === 'projects') {
        prepareKpiEntrance('axis-project-kpi-strip');
        renderAxisProjectsPage();
    }
    if (pageId === 'modules') {
        prepareKpiEntrance();
        renderProjectsTable();
    }
    if (pageId === 'task-detail') {
        prepareKpiEntrance('task-kpi-strip');
        renderTaskDetailPage();
    }
    if (pageId === 'reports') renderReportsPage();
    if (pageId === 'team') renderTeamTable();
    if (pageId === 'roles') renderRolesPage();
    if (pageId === 'work-queues') renderWorkQueuesPage();
    if (pageId === 'sharing') renderRecordSharesPage();
    if (pageId === 'p18') renderFeatureUpgradesPage();

    toggleSidebar(false);

    // Dynamically sync Top Header Title, Section Trail, Route, Search & Create Button
    updateDynamicHeader(pageId);
    syncQuickModulePercentages();

    // Keep the browser URL in sync with the Laravel route for this page (deep-linkable & refresh-safe)
    syncPageUrl(pageId, history);
};

function pageUrlFor(pageId) {
    const urls = AXIS_BOOT.pageUrls || {};
    if (pageId === 'task-detail') {
        return (urls['task-detail'] || '').replace('__ID__', encodeURIComponent(activeProjectId));
    }
    if (pageId === 'modules' && urls.modules) {
        const projectFilter = document.getElementById('flt-project')?.value;
        return projectFilter && projectFilter !== 'all' ? `${urls.modules}?project=${encodeURIComponent(projectFilter)}` : urls.modules;
    }
    return urls[pageId] || null;
}

function syncPageUrl(pageId, mode) {
    const url = pageUrlFor(pageId);
    if (!url || mode === false) return;
    const state = { axisPage: pageId, projectId: activeProjectId };
    if (mode === 'replace') {
        window.history.replaceState(state, '', url);
    } else if (window.location.pathname + window.location.search !== new URL(url, window.location.origin).pathname + new URL(url, window.location.origin).search) {
        window.history.pushState(state, '', url);
    }
}

window.addEventListener('popstate', (e) => {
    const st = e.state;
    if (!st || !st.axisPage) return;
    if (st.projectId) activeProjectId = st.projectId;
    switchPage(st.axisPage, false);
});

window.openProjectTaskList = function(projectId) {
    const found = projects.find(p => p.id === projectId);
    if (found) {
        activeProjectId = found.id;
    }
    switchPage('task-detail');
};

// ---------------------------------------------------------------------
// Theme: dark is the default; a saved light preference is applied before first paint
// ---------------------------------------------------------------------
const THEME_KEY = 'axis.theme';

window.setTheme = function(theme, save = true) {
    const isLight = theme === 'light';
    document.documentElement.classList.toggle('dark', !isLight);
    const button = document.getElementById('header-theme-toggle');
    if (button) {
        const label = isLight ? 'Switch to dark theme' : 'Switch to light theme';
        button.setAttribute('aria-label', label);
        button.setAttribute('aria-pressed', isLight ? 'true' : 'false');
        button.title = label;
        const icon = button.querySelector('i');
        if (icon) icon.className = isLight ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
    }
    if (save) {
        try { localStorage.setItem(THEME_KEY, isLight ? 'light' : 'dark'); } catch (e) { /* keep for this visit */ }
    }
};

window.toggleTheme = function() {
    setTheme(document.documentElement.classList.contains('dark') ? 'light' : 'dark');
};

function initTheme() {
    let theme = 'dark';
    try {
        const saved = localStorage.getItem(THEME_KEY);
        if (saved === 'light' || saved === 'dark') theme = saved;
    } catch (e) { /* default to dark */ }
    setTheme(theme, false);
}

// ---------------------------------------------------------------------
// Left sidebar: always visible on desktop; below 1024px it slides in from the header's menu button
// ---------------------------------------------------------------------
const SIDEBAR_PIN_KEY = 'axis.sidebar.pinned';
const isDesktopSidebar = () => window.matchMedia('(min-width: 1024px)').matches;
let sidebarHoverTimer = null;

window.isSidebarPinned = () => !document.getElementById('eot-sidebar')?.classList.contains('is-collapsed');

/** Desktop: pinned = full menu; unpinned = icon rail that expands on hover. Remembered per browser. */
window.setSidebarPinned = function(pinned, save = true) {
    const sidebar = document.getElementById('eot-sidebar');
    if (!sidebar) return;
    sidebar.classList.toggle('is-collapsed', !pinned);
    sidebar.classList.remove('is-expanded');
    const pin = document.getElementById('sb-pin-btn');
    if (pin) {
        const label = pinned ? 'Collapse the menu to icons' : 'Keep the menu open';
        pin.setAttribute('aria-pressed', pinned ? 'true' : 'false');
        pin.setAttribute('aria-label', label);
        pin.title = label;
    }
    if (save) {
        try { localStorage.setItem(SIDEBAR_PIN_KEY, pinned ? '1' : '0'); } catch (e) { /* keep for this visit */ }
    }
};

/** Unpinned rail: expand while the pointer or keyboard focus is on it (short delays avoid flicker). */
window.hoverSidebar = function(enter) {
    const sidebar = document.getElementById('eot-sidebar');
    if (!sidebar || !isDesktopSidebar() || isSidebarPinned()) return;
    clearTimeout(sidebarHoverTimer);
    sidebarHoverTimer = setTimeout(() => sidebar.classList.toggle('is-expanded', enter), enter ? 120 : 180);
};

function initSidebar() {
    let pinned = window.innerWidth >= 1280;
    try {
        const saved = localStorage.getItem(SIDEBAR_PIN_KEY);
        if (saved !== null) pinned = saved === '1';
    } catch (e) { /* default */ }
    setSidebarPinned(pinned, false);
    const sidebar = document.getElementById('eot-sidebar');
    sidebar?.addEventListener('focusin', () => hoverSidebar(true));
    sidebar?.addEventListener('focusout', (e) => { if (!sidebar.contains(e.relatedTarget)) hoverSidebar(false); });
}

window.toggleSidebar = function(open) {
    const sidebar = document.getElementById('eot-sidebar');
    if (!sidebar) return;
    const isOpen = typeof open === 'boolean' ? open : !sidebar.classList.contains('is-open');
    sidebar.classList.toggle('is-open', isOpen);
    document.getElementById('axis-side-backdrop')?.classList.toggle('hidden', !isOpen);
    document.getElementById('sb-open-btn')?.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    if (isOpen) (sidebar.querySelector('.axis-sidebar__item.nav-active') || sidebar.querySelector('.axis-sidebar__item'))?.focus();
};

// =====================================================================
// DASHBOARD — the whole system at a glance (live data only)
// =====================================================================
/** Whole days from today to an ISO date (negative = past). */
function daysFromToday(iso) {
    const today = new Date(); today.setHours(0, 0, 0, 0);
    return Math.round((new Date(iso + 'T00:00:00') - today) / 86400000);
}

/** "1h 12m" style duration for totals (the table rows use HH:MM:SS). */
function formatDuration(totalSeconds) {
    const sec = Math.max(0, parseInt(totalSeconds, 10) || 0);
    const h = Math.floor(sec / 3600);
    const m = Math.floor((sec % 3600) / 60);
    if (h === 0 && m === 0) return sec > 0 ? '< 1m' : '0m';
    return h ? `${h}h ${m}m` : `${m}m`;
}

function renderDashboard() {
    renderDashboardModules();
    renderDashboardProjectCards();
    renderDashboardTaskCards();
    renderDashboardTeam();
}

/** Shared helpers for the dashboard card rows: search, visible count, sort label and scroll arrows. */
function dashboardSearchMatch(query, values) {
    return !query || values.some(value => String(value ?? '').toLowerCase().includes(query));
}

function dashboardFilterValue(id) {
    return document.getElementById(id)?.value || 'all';
}

function dashboardNoMatchText(search, noun) {
    const query = search.trim();
    return query ? `No ${noun} match "${escapeHtml(query)}".` : `No ${noun} match these filters.`;
}

function setDashboardCardMeta(countId, sortSelectId, sortLabelId, shown, total, noun) {
    const count = document.getElementById(countId);
    if (count) {
        count.textContent = shown === total
            ? `${total} ${noun}${total === 1 ? '' : 's'}`
            : `${shown} of ${total} ${noun}${total === 1 ? '' : 's'}`;
    }
    const select = document.getElementById(sortSelectId);
    const label = document.getElementById(sortLabelId);
    if (select && label) {
        label.textContent = `· Sorted by: ${select.options[select.selectedIndex]?.textContent || ''}`;
    }
}

function updateDashboardScrollButtons(containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;
    const controls = container.closest('[data-dashboard-row]')?.querySelector('[data-dashboard-scroll]');
    if (!controls) return;
    const previous = controls.querySelector('[data-scroll-dir="-1"]');
    const next = controls.querySelector('[data-scroll-dir="1"]');
    const maxScroll = Math.max(0, container.scrollWidth - container.clientWidth);
    if (previous) previous.disabled = container.scrollLeft <= 1;
    if (next) next.disabled = container.scrollLeft >= maxScroll - 1;
}

function initDashboardScrollButtons() {
    const ids = ['dash-project-cards', 'dash-modules-cards', 'dash-task-cards'];
    ids.forEach(id => {
        const container = document.getElementById(id);
        if (!container || container.dataset.scrollBound === '1') return;
        container.dataset.scrollBound = '1';
        container.addEventListener('scroll', () => updateDashboardScrollButtons(id), { passive: true });
        updateDashboardScrollButtons(id);
    });
    if (!window.dashboardScrollResizeBound) {
        window.dashboardScrollResizeBound = true;
        window.addEventListener('resize', () => ids.forEach(updateDashboardScrollButtons));
    }
}

window.scrollDashboardCards = function(containerId, direction) {
    const container = document.getElementById(containerId);
    if (!container) return;
    const card = container.querySelector('.axis-list-card');
    const amount = card ? card.offsetWidth + 12 : container.clientWidth * 0.8;
    container.scrollBy({ left: direction * amount, behavior: 'smooth' });
};

let dashboardProjectOrder = 'name:asc';
let dashboardProjectSearch = '';

window.setDashboardProjectSearch = function(value) {
    dashboardProjectSearch = value;
    renderDashboardProjectCards();
};

window.setDashboardProjectStatusFilter = function() {
    renderDashboardProjectCards();
};

window.setDashboardProjectOrder = function(value) {
    dashboardProjectOrder = value;
    renderDashboardProjectCards();
};

function renderDashboardProjectCards() {
    const container = document.getElementById('dash-project-cards');
    if (!container) return;

    const query = dashboardProjectSearch.trim().toLowerCase();
    const status = dashboardFilterValue('dash-project-status-filter');
    const candidates = axisProjects.filter(ap =>
        (status === 'all' || ap.status === status)
        && dashboardSearchMatch(query, [ap.name, ap.description, ap.status]));
    setDashboardCardMeta('dash-project-count', 'dash-project-order', 'dash-project-sort-label', candidates.length, axisProjects.length, 'project');

    if (axisProjects.length === 0 || candidates.length === 0) {
        container.innerHTML = axisProjects.length === 0
            ? '<p class="py-6 text-center text-xs text-slate-400">No projects yet.</p>'
            : `<p class="py-6 text-center text-xs text-slate-400">${dashboardNoMatchText(dashboardProjectSearch, 'projects')}</p>`;
        updateDashboardScrollButtons('dash-project-cards');
        return;
    }

    const [key, dir = 'asc'] = dashboardProjectOrder.split(':');
    const direction = dir === 'desc' ? -1 : 1;
    const sortValue = {
        name: ap => ap.name.toLowerCase(),
        status: ap => AXIS_STATUS_ORDER[ap.status] ?? 9,
        progress: ap => axisProjectProgress(ap.id),
        deadline: ap => ap.deadline || '9999-12-31',
        issues: ap => axisProjectStats(ap).openIssues,
        modules: ap => axisProjectStats(ap).mods.length,
    }[key] ?? (ap => ap.name.toLowerCase());

    const sorted = candidates.slice().sort((a, b) => {
        const left = sortValue(a), right = sortValue(b);
        return (left < right ? -1 : left > right ? 1 : 0) * direction || a.name.localeCompare(b.name);
    });
    const open = "switchPage('projects')";

    container.innerHTML = sorted.map(ap => {
        const st = axisProjectStats(ap);
        const due = deadlineInfo(ap);
        const name = escapeHtml(ap.name);
        return `
            <article class="axis-list-card min-w-0" role="button" tabindex="0"
                     onclick="${open}" onkeydown="if (event.key === 'Enter' && event.target === this) ${open}"
                     aria-label="Open the Projects page">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h3 class="text-sm font-semibold text-white truncate" title="${name}">${name}</h3>
                        <p class="text-xs text-slate-400 mt-0.5 line-clamp-2">${escapeHtml(ap.description || 'No description')}</p>
                    </div>
                    <span class="text-[10px] text-slate-400 whitespace-nowrap shrink-0">${escapeHtml(ap.status)}</span>
                </div>
                <div class="mt-3">${progressBarHtml(Math.round(st.progress), projectProgressExplain(ap))}</div>
                <dl class="grid grid-cols-3 gap-x-4 gap-y-2 mt-3 text-xs">
                    <div><dt class="text-slate-500">Modules</dt><dd class="text-slate-100 font-mono mt-0.5">${st.mods.length}</dd></div>
                    <div><dt class="text-slate-500">Tasks done</dt><dd class="text-slate-100 font-mono mt-0.5">${st.tasksDone} / ${st.tasksTotal}</dd></div>
                    <div><dt class="text-slate-500">Open issues</dt><dd class="mt-0.5 ${st.openIssues ? 'text-amber-300 font-semibold' : 'text-slate-400'}">${st.openIssues || 'None'}</dd></div>
                </dl>
                <div class="flex items-center justify-between gap-3 mt-3 pt-3 border-t border-white/[0.06]">
                    <span class="text-[11px] font-semibold text-sky-300 whitespace-nowrap shrink-0">Project list <i class="fa-solid fa-arrow-right text-[10px] ml-1" aria-hidden="true"></i></span>
                    <div class="text-right text-xs">
                        <div class="text-slate-300 whitespace-nowrap">${shortDate(ap.deadline)}</div>
                        <div class="${due.cls}">${escapeHtml(due.text)}</div>
                    </div>
                </div>
            </article>`;
    }).join('');
    updateDashboardScrollButtons('dash-project-cards');
}

let dashboardModuleOrder = 'order:asc';
let dashboardModuleSearch = '';

window.setDashboardModuleSearch = function(value) {
    dashboardModuleSearch = value;
    renderDashboardModules();
};

window.setDashboardModuleProjectFilter = function() {
    renderDashboardModules();
};

window.setDashboardModuleStatusFilter = function() {
    renderDashboardModules();
};

window.setDashboardModuleOrder = function(value) {
    dashboardModuleOrder = value;
    renderDashboardModules();
};

function renderDashboardModules() {
    const container = document.getElementById('dash-modules-cards');
    if (!container) return;

    const query = dashboardModuleSearch.trim().toLowerCase();
    const projectFilter = dashboardFilterValue('dash-module-project-filter');
    const status = dashboardFilterValue('dash-module-status-filter');
    const candidates = projects.filter(m =>
        (projectFilter === 'all' || Number(m.projectId) === parseInt(projectFilter, 10))
        && (status === 'all' || m.status === status)
        && dashboardSearchMatch(query, [m.name, m.code, m.projectName, m.manager, m.customer, m.customerSub, m.status]));
    setDashboardCardMeta('dash-modules-count', 'dash-modules-order', 'dash-modules-sort-label', candidates.length, projects.length, 'module');

    if (projects.length === 0 || candidates.length === 0) {
        container.innerHTML = projects.length === 0
            ? '<p class="py-6 text-center text-xs text-slate-400">No modules yet.</p>'
            : `<p class="py-6 text-center text-xs text-slate-400">${dashboardNoMatchText(dashboardModuleSearch, 'modules')}</p>`;
        updateDashboardScrollButtons('dash-modules-cards');
        return;
    }

    const openIssueCount = m => (m.tasks || []).reduce(
        (sum, t) => sum + (t.issues || []).filter(i => i.status !== 'Resolved').length, 0);
    const [key, dir = 'asc'] = dashboardModuleOrder.split(':');
    const direction = dir === 'desc' ? -1 : 1;
    const sortValue = {
        order: m => Number(m.sortOrder || 0),
        deadline: m => `${m.status === 'Finished' ? 1 : 0}-${m.deadline || '9999-12-31'}`,
        name: m => (m.name || '').toLowerCase(),
        progress: m => computeProjectCompletion(m),
        issues: m => openIssueCount(m),
    }[key] ?? (m => Number(m.sortOrder || 0));

    const sorted = candidates.slice().sort((a, b) => {
        const left = sortValue(a), right = sortValue(b);
        return (left < right ? -1 : left > right ? 1 : 0) * direction || a.name.localeCompare(b.name);
    });
    const open = 'openModulesOfProject(null)';

    container.innerHTML = sorted.map(m => {
        const tasks = m.tasks || [];
        const done = tasks.filter(t => t.status === 'Finished').length;
        const pct = Math.round(computeProjectCompletion(m));
        const due = deadlineInfo(m);
        const issues = openIssueCount(m);
        const order = Number(m.sortOrder || 0);
        const name = escapeHtml(m.name);
        return `
            <article class="axis-list-card dash-module-card min-w-0" role="button" tabindex="0"
                     onclick="${open}" onkeydown="if (event.key === 'Enter' && event.target === this) ${open}"
                     aria-label="Open the Modules page">
                <div class="flex items-center gap-2.5 min-w-0">
                    <span class="w-6 h-6 rounded-md grid place-items-center shrink-0 bg-sky-500/10 text-sky-300 text-[11px] font-mono" title="Order ${order}">${order}</span>
                    <span class="w-8 h-8 rounded-lg ${m.badgeClass} font-bold text-xs flex items-center justify-center shrink-0">${escapeHtml(m.projBadge)}</span>
                    <div class="min-w-0">
                        <h3 class="text-sm font-semibold text-white truncate" title="${name}">${name}</h3>
                        <p class="text-xs text-slate-400 truncate mt-0.5"><span class="font-mono text-slate-500">${escapeHtml(m.code)}</span> · ${escapeHtml(m.projectName || 'No project')}</p>
                    </div>
                </div>
                <div class="mt-3">${progressBarHtml(pct, moduleProgressExplain(m))}</div>
                <dl class="grid grid-cols-3 gap-x-4 gap-y-2 mt-3 text-xs">
                    <div class="min-w-0"><dt class="text-slate-500">Lead</dt><dd class="text-slate-100 truncate mt-0.5" title="${escapeHtml(m.manager || '')}">${escapeHtml(m.manager || '—')}</dd></div>
                    <div><dt class="text-slate-500">Tasks done</dt><dd class="text-slate-100 font-mono mt-0.5">${done} / ${tasks.length}</dd></div>
                    <div><dt class="text-slate-500">Open issues</dt><dd class="mt-0.5 ${issues ? 'text-amber-300 font-semibold' : 'text-slate-400'}">${issues || 'None'}</dd></div>
                </dl>
                <div class="flex justify-end mt-3 pt-3 border-t border-white/[0.06]">
                    <div class="text-right text-xs shrink-0">
                        <div class="text-slate-300 whitespace-nowrap">${m.deadline ? escapeHtml(shortDate(m.deadline)) : 'Not set'}</div>
                        <div class="${due.cls}">${escapeHtml(due.text)}</div>
                    </div>
                </div>
            </article>`;
    }).join('');
    updateDashboardScrollButtons('dash-modules-cards');
}

let dashboardTaskOrder = 'deadline:asc';
let dashboardTaskSearch = '';

window.setDashboardTaskSearch = function(value) {
    dashboardTaskSearch = value;
    renderDashboardTaskCards();
};

window.setDashboardTaskModuleFilter = function() {
    renderDashboardTaskCards();
};

window.setDashboardTaskStatusFilter = function() {
    renderDashboardTaskCards();
};

window.setDashboardTaskPriorityFilter = function() {
    renderDashboardTaskCards();
};

window.setDashboardTaskOrder = function(value) {
    dashboardTaskOrder = value;
    renderDashboardTaskCards();
};

function renderDashboardTaskCards() {
    const container = document.getElementById('dash-task-cards');
    if (!container) return;

    const tasks = projects.flatMap(m => (m.tasks || []).map(t => ({ t, m })));
    const query = dashboardTaskSearch.trim().toLowerCase();
    const moduleFilter = dashboardFilterValue('dash-task-module-filter');
    const status = dashboardFilterValue('dash-task-status-filter');
    const priority = dashboardFilterValue('dash-task-priority-filter');
    const candidates = tasks.filter(({ t, m }) =>
        (moduleFilter === 'all' || m.id === parseInt(moduleFilter, 10))
        && (status === 'all' || t.status === status)
        && (priority === 'all' || t.priority === priority)
        && dashboardSearchMatch(query, [t.name, `TASK-${t.id}`, m.name, t.handler, t.status, t.priority]));
    setDashboardCardMeta('dash-task-count', 'dash-task-order', 'dash-task-sort-label', candidates.length, tasks.length, 'task');

    if (tasks.length === 0 || candidates.length === 0) {
        container.innerHTML = tasks.length === 0
            ? '<p class="py-6 text-center text-xs text-slate-400">No tasks yet.</p>'
            : `<p class="py-6 text-center text-xs text-slate-400">${dashboardNoMatchText(dashboardTaskSearch, 'tasks')}</p>`;
        updateDashboardScrollButtons('dash-task-cards');
        return;
    }

    const urgency = ({ t }) => {
        const days = t.endDate ? daysFromToday(t.endDate) : null;
        if (t.status !== 'Finished' && days !== null && days < 0) return 0;
        if (t.status !== 'Finished' && days !== null && days <= 7) return 1;
        if (t.status !== 'Finished') return 2;
        return 3;
    };
    const priorityRank = { High: 0, Medium: 1, Low: 2 };
    const statusRank = { Processing: 0, 'Not yet started': 1, Finished: 2 };
    const [key, dir = 'asc'] = dashboardTaskOrder.split(':');
    const direction = dir === 'desc' ? -1 : 1;
    const sortValue = {
        deadline: ({ t }) => `${t.status === 'Finished' ? 1 : 0}-${t.endDate || '9999-12-31'}`,
        priority: ({ t }) => priorityRank[t.priority] ?? 9,
        status: ({ t }) => statusRank[t.status] ?? 9,
        name: ({ t }) => (t.name || '').toLowerCase(),
    }[key] ?? (({ t }) => `${t.status === 'Finished' ? 1 : 0}-${t.endDate || '9999-12-31'}`);

    const sorted = candidates.slice().sort((a, b) => {
        const left = sortValue(a), right = sortValue(b);
        return (left < right ? -1 : left > right ? 1 : 0) * direction || a.t.name.localeCompare(b.t.name);
    });

    container.innerHTML = sorted.map(({ t, m }) => {
        const level = urgency({ t });
        const toneCls = level === 0 ? 'bg-rose-500/15 text-rose-300'
            : level === 1 ? 'bg-amber-500/15 text-amber-300'
            : level === 3 ? 'bg-emerald-500/15 text-emerald-300'
            : 'bg-sky-500/15 text-sky-300';
        const icon = level === 0 ? 'fa-calendar-xmark'
            : level === 1 ? 'fa-calendar-day'
            : level === 3 ? 'fa-circle-check'
            : 'fa-list-check';
        const statusCls = t.status === 'Finished' ? 'text-emerald-300'
            : t.status === 'Processing' ? 'text-sky-300'
            : 'text-amber-300';
        const issues = (t.issues || []).filter(i => i.status !== 'Resolved').length;
        const pct = Math.round(Number(t.progress) || 0);
        const due = deadlineInfo({ status: t.status, deadline: t.endDate });
        const taskName = escapeHtml(t.name);
        const moduleName = escapeHtml(m.name);
        const open = `openProjectTaskList(${m.id})`;
        return `
            <article class="axis-list-card min-w-0" role="button" tabindex="0"
                     onclick="${open}" onkeydown="if (event.key === 'Enter' && event.target === this) ${open}"
                     aria-label="Open the task page for ${moduleName}">
                <div class="flex items-center gap-2.5 min-w-0">
                    <span class="w-8 h-8 rounded-lg grid place-items-center shrink-0 ${toneCls}"><i class="fa-solid ${icon} text-xs" aria-hidden="true"></i></span>
                    <div class="min-w-0">
                        <h3 class="text-sm font-semibold text-white truncate" title="${taskName}">${taskName}</h3>
                        <p class="text-[11px] text-slate-400 truncate mt-0.5">TASK-${t.id} · ${moduleName} · ${escapeHtml(t.handler || 'Unassigned')}</p>
                    </div>
                </div>
                <div class="mt-3">${progressBarHtml(pct, `Task progress: ${pct}%`)}</div>
                <dl class="grid grid-cols-3 gap-x-4 gap-y-2 mt-3 text-xs">
                    <div><dt class="text-slate-500">Status</dt><dd class="${statusCls} mt-0.5 truncate">${escapeHtml(t.status || '—')}</dd></div>
                    <div><dt class="text-slate-500">Priority</dt><dd class="text-slate-100 mt-0.5 truncate">${escapeHtml(t.priority || '—')}</dd></div>
                    <div><dt class="text-slate-500">Open issues</dt><dd class="mt-0.5 ${issues ? 'text-amber-300 font-semibold' : 'text-slate-400'}">${issues || 'None'}</dd></div>
                </dl>
                <div class="mt-3 pt-3 border-t border-white/[0.06] flex items-center justify-between gap-3 text-[11px]">
                    <span class="text-slate-300">${t.endDate ? escapeHtml(shortDate(t.endDate)) : 'No deadline'}</span>
                    <span class="${due.cls}">${escapeHtml(due.text)}</span>
                </div>
            </article>`;
    }).join('');
    updateDashboardScrollButtons('dash-task-cards');
}

function renderDashboardTeam() {
    const body = document.getElementById('dash-team-body');
    if (!body) return;
    const all = projects.flatMap(m => (m.tasks || []).map(t => ({ t, m })));
    // Active employees, plus anyone inactive who still has unfinished work
    const people = teamMembers.filter(p => p.status !== 'inactive' || all.some(({ t }) => t.employeeId === p.id && t.status !== 'Finished'));
    const rows = people.map(p => {
        const mine = all.filter(({ t }) => t.employeeId === p.id);
        const open = mine.filter(({ t }) => t.status !== 'Finished');
        return {
            p,
            open: open.length,
            overdue: open.filter(({ t }) => t.endDate && daysFromToday(t.endDate) < 0).length,
            finished: mine.length - open.length,
            seconds: mine.reduce((sum, { t }) => sum + (parseInt(t.actSeconds, 10) || 0), 0),
            running: mine.find(({ t }) => t.running),
            leads: projects.filter(m => m.manager === p.name).length,
        };
    }).sort((a, b) => b.open - a.open || b.overdue - a.overdue || a.p.name.localeCompare(b.p.name));

    if (rows.length === 0) {
        body.innerHTML = '<tr><td colspan="6" class="py-10 text-center text-sm text-slate-400">No employees yet.</td></tr>';
        return;
    }
    body.innerHTML = rows.map(r => `
        <tr>
            <td class="py-2.5 pl-5 pr-3">
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-full ${avatarToneFor(r.p.name)} text-xs font-semibold inline-flex items-center justify-center shrink-0" aria-hidden="true">${escapeHtml(initialsOf(r.p.name))}</span>
                    <div class="min-w-0">
                        <div class="text-sm font-medium text-white truncate">${escapeHtml(r.p.name)}${r.p.status === 'inactive' ? ' <span class="text-xs text-slate-500">(inactive)</span>' : ''}</div>
                        <div class="text-xs text-slate-400 truncate">${escapeHtml(r.p.title || (r.p.position === 'leader' ? 'Leader' : 'Developer'))}${r.leads ? ` · leads ${plural(r.leads, 'module')}` : ''}</div>
                    </div>
                </div>
            </td>
            <td class="py-2.5 px-3 text-sm font-mono ${r.open ? 'text-white' : 'text-slate-500'}">${r.open}</td>
            <td class="py-2.5 px-3 text-sm font-mono ${r.overdue ? 'text-rose-300 font-semibold' : 'text-slate-500'}">${r.overdue}</td>
            <td class="py-2.5 px-3 text-sm font-mono ${r.finished ? 'text-emerald-300' : 'text-slate-500'}">${r.finished}</td>
            <td class="py-2.5 px-3 text-sm font-mono ${r.seconds ? 'text-slate-200' : 'text-slate-500'}">${formatDuration(r.seconds)}</td>
            <td class="py-2.5 pl-3 pr-5">${r.running
                ? `<button type="button" onclick="openProjectTaskList(${r.running.m.id})" class="inline-flex items-center gap-1.5 text-xs font-semibold text-sky-300 hover:text-sky-200 max-w-[220px]" title="Timer running on ${escapeHtml(r.running.t.name)}"><span class="w-1.5 h-1.5 rounded-full bg-sky-400 animate-pulse shrink-0" aria-hidden="true"></span><span class="truncate">${escapeHtml(r.running.t.name)}</span></button>`
                : `<span class="text-xs text-slate-500">${r.open ? 'Timer paused' : 'No open tasks'}</span>`}</td>
        </tr>`).join('');

    function plural(n, word) { return `${n} ${word}${n === 1 ? '' : 's'}`; }
}

function syncQuickModulePercentages() {
    // Quick-module ids are rendered server-side (sidebar + dashboard grid)
    const ids = [...document.querySelectorAll('[data-qm-id]')].map(el => parseInt(el.dataset.qmId, 10));
    [...new Set(ids)].forEach(id => {
        const proj = projects.find(p => p.id === id);
        if (!proj) return;
        const pct = Math.round(computeProjectCompletion(proj));
        const pctEl = document.getElementById('qm-pct-' + id);
        const barEl = document.getElementById('qm-bar-' + id);
        if (pctEl) pctEl.textContent = pct + '%';
        if (barEl) barEl.style.width = pct + '%';
        const dashPct = document.getElementById('dash-qm-pct-' + id);
        const dashBar = document.getElementById('dash-qm-bar-' + id);
        if (dashPct) dashPct.textContent = pct + '%';
        if (dashBar) dashBar.style.width = pct + '%';
    });
}

// =====================================================================
// LIST VIEWS (Table / Cards / Board) shared by the Projects and Modules pages
// =====================================================================
const LIST_VIEW_MODES = ['table', 'cards', 'board'];
const LIST_VIEW_IDS = {
    projects: { table: 'ap-view-table', cards: 'ap-view-cards', board: 'ap-view-board', hint: 'ap-view-hint' },
    modules: { table: 'mod-view-table', cards: 'mod-view-cards', board: 'mod-view-board' },
};

/** The remembered view for a list (a per-browser convenience; falls back to the table). */
function readListView(list) {
    try {
        const saved = localStorage.getItem(`axis.view.${list}`);
        return LIST_VIEW_MODES.includes(saved) ? saved : 'table';
    } catch (e) {
        return 'table';
    }
}
const listViews = { projects: readListView('projects'), modules: readListView('modules') };

window.setListView = function(list, mode) {
    if (!LIST_VIEW_MODES.includes(mode)) return;
    listViews[list] = mode;
    try { localStorage.setItem(`axis.view.${list}`, mode); } catch (e) { /* storage unavailable: keep it for this visit */ }
    if (list === 'projects') renderAxisProjectsTable(); else renderProjectsTable();
};

/** Show the container for the list's current view and mark its switch button. */
function applyListView(list) {
    const mode = listViews[list];
    const ids = LIST_VIEW_IDS[list];
    LIST_VIEW_MODES.forEach(m => document.getElementById(ids[m])?.classList.toggle('hidden', m !== mode));
    if (ids.hint) document.getElementById(ids.hint)?.classList.toggle('sm:inline', mode === 'table');
    document.querySelectorAll(`[data-list-view^="${list}:"]`).forEach(btn => {
        const active = btn.dataset.listView === `${list}:${mode}`;
        btn.classList.toggle('is-active', active);
        btn.setAttribute('aria-pressed', active ? 'true' : 'false');
    });
    return mode;
}

/** Keep a "Sort by" select in step with the table's sort (header clicks can pick orders the select doesn't list). */
function syncSortSelect(selectId, sort) {
    const select = document.getElementById(selectId);
    if (!select) return;
    const value = `${sort.key}:${sort.dir}`;
    select.value = [...select.options].some(o => o.value === value) ? value : '';
}

const STATUS_SELECT_BASE = 'axis-status-select border text-xs font-medium pl-2.5 pr-6 py-1 rounded-full cursor-pointer focus:outline-none';
const statusSelectClass = status => `${PROJECT_STATUS_STYLES[status] || PROJECT_STATUS_STYLES['Processing']} ${STATUS_SELECT_BASE}`;

function statusSelectHtml(onchange, status, label, id = '') {
    return `<select ${id ? `id="${id}"` : ''} onclick="event.stopPropagation()" onchange="${onchange}" aria-label="Status of ${label}"
        class="${statusSelectClass(status)}">${workStatusOptions(status)}</select>`;
}

function progressBarHtml(pct, title = `${pct}% complete`) {
    const barColor = pct >= 90 ? 'bg-emerald-400' : pct >= 60 ? 'bg-sky-400' : pct >= 40 ? 'bg-violet-400' : 'bg-amber-400';
    return `<div class="flex items-center gap-2" title="${escapeHtml(title)}">
        <div class="flex-1 h-1.5 rounded-full bg-white/[0.08] overflow-hidden"><div class="h-full rounded-full ${barColor}" style="width: ${pct}%"></div></div>
        <span class="text-xs font-mono font-semibold text-slate-200 w-9 text-right">${pct}%</span>
    </div>`;
}

/** Hover text for a module's bar: where the number comes from and how to move it. */
function moduleProgressExplain(m) {
    const tasks = m.tasks || [];
    const pct = Math.round(computeProjectCompletion(m));
    if (tasks.length === 0) return `${pct}%: no tasks yet. Add tasks to track progress.`;
    const values = tasks.map(t => `${Math.round(parseFloat(t.progress) || 0)}%`);
    const listed = values.length > 6 ? `${values.slice(0, 6).join(', ')}, …` : values.join(', ');
    return `${pct}%: average of ${tasks.length} task${tasks.length === 1 ? '' : 's'} (${listed}). Update its tasks to change it.`;
}

/** Hover text for a project's bar: the average of its modules. */
function projectProgressExplain(ap) {
    const mods = modulesOfProject(ap.id);
    const pct = Math.round(axisProjectProgress(ap.id));
    if (mods.length === 0) return `${pct}%: no modules yet. Add modules and tasks to track progress.`;
    const values = mods.map(m => `${m.name} ${Math.round(computeProjectCompletion(m))}%`);
    const listed = values.length > 4 ? `${values.slice(0, 4).join(', ')}, …` : values.join(', ');
    return `${pct}%: average of ${mods.length} module${mods.length === 1 ? '' : 's'} (${listed}). Update their tasks to change it.`;
}

/**
 * When a project/module status disagrees with the progress worked out from its tasks,
 * say how — so the status can't quietly drift from the real work. Null when they agree.
 */
function statusMismatchText(status, pct, itemCount, itemWord) {
    if (status === 'Finished' && pct < 100) return `Marked Finished, but its ${itemWord}s are only ${pct}% done.`;
    if (status === 'Not yet started' && pct > 0) return `Marked Not yet started, but its ${itemWord}s are already ${pct}% done.`;
    if (status === 'Processing' && itemCount > 0 && pct >= 100) return `Every ${itemWord} is done. Mark it Finished?`;
    return null;
}

function statusMismatchHtml(text) {
    if (!text) return '';
    return `<span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-amber-500/15 text-amber-300 shrink-0" title="${escapeHtml(text)}" role="img" aria-label="${escapeHtml(text)}">
        <i class="fa-solid fa-triangle-exclamation text-[10px]" aria-hidden="true"></i>
    </span>`;
}

function avatarStackHtml(names, max = 4) {
    if (!names.length) return '<span class="text-xs text-slate-500 whitespace-nowrap">No one yet</span>';
    const shown = names.slice(0, max);
    return `<div class="flex items-center" title="${escapeHtml(names.join(', '))}">
        ${shown.map(n => `<span class="w-6 h-6 -ml-1.5 first:ml-0 rounded-full ring-2 ring-[#0D2650] ${avatarToneFor(n)} text-[10px] font-semibold inline-flex items-center justify-center">${escapeHtml(initialsOf(n))}</span>`).join('')}
        ${names.length > max ? `<span class="w-6 h-6 -ml-1.5 rounded-full ring-2 ring-[#0D2650] bg-white/10 text-slate-300 text-[10px] font-semibold inline-flex items-center justify-center">+${names.length - max}</span>` : ''}
    </div>`;
}

const shortDate = iso => iso ? new Date(iso + 'T00:00:00').toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '—';

/** Three status columns; `renderCard` draws one item. */
function boardHtml(items, statusOf, renderCard, emptyText) {
    const dots = { 'Not yet started': 'bg-amber-400', 'Processing': 'bg-sky-400', 'Finished': 'bg-emerald-400' };
    return WORK_STATUSES.map(status => {
        const inColumn = items.filter(item => statusOf(item) === status);
        return `<section class="axis-board-col" aria-label="${status}">
            <header class="flex items-center justify-between px-1 pb-2">
                <span class="flex items-center gap-2 text-xs font-semibold text-slate-200"><span class="w-2 h-2 rounded-full ${dots[status]}"></span>${status}</span>
                <span class="text-xs font-mono text-slate-400">${inColumn.length}</span>
            </header>
            <div class="space-y-2.5">${inColumn.length ? inColumn.map(renderCard).join('') : `<p class="axis-board-empty">${emptyText}</p>`}</div>
        </section>`;
    }).join('');
}

// =====================================================================
// PROJECTS PAGE (`projects`): top level of Project → Module → Task
// =====================================================================
/** Modules that belong to a top-level project. */
function modulesOfProject(projectId) {
    return projects.filter(p => p.projectId === projectId);
}

/** A project's progress: the mean completion of its modules (0 when it has none). */
function axisProjectProgress(projectId) {
    const mods = modulesOfProject(projectId);
    return mods.length ? mods.reduce((sum, p) => sum + computeProjectCompletion(p), 0) / mods.length : 0;
}

/** Name of the project picked in the module list's Project filter, if any. */
function moduleProjectFilterName() {
    const value = document.getElementById('flt-project')?.value;
    if (!value || value === 'all') return '';
    return (axisProjects.find(ap => String(ap.id) === value) || {}).name || '';
}

/** Modules shown on the module list KPIs: those of the picked project, or all of them. */
function scopedModules() {
    const value = document.getElementById('flt-project')?.value;
    return !value || value === 'all' ? projects : projects.filter(p => String(p.projectId) === value);
}

/** Open the module list filtered to one project (or unfiltered when no id is given). */
window.openModulesOfProject = function(projectId) {
    const select = document.getElementById('flt-project');
    if (select) select.value = projectId && axisProjects.some(ap => ap.id === projectId) ? String(projectId) : 'all';
    switchPage('modules');
};

window.resetAxisProjectFilters = function() {
    document.getElementById('ap-flt-search').value = '';
    document.getElementById('ap-flt-status').value = 'all';
    renderAxisProjectsTable();
};

window.renderAxisProjectsPage = function() {
    renderAxisProjectKpis();
    renderAxisProjectsTable();
};

function renderAxisProjectKpis() {
    if (!document.getElementById('axis-project-kpi-strip')) return;
    const total = axisProjects.length;
    const byStatus = status => axisProjects.filter(ap => ap.status === status).length;
    const plural = (n, word) => `${n} ${word}${n === 1 ? '' : 's'}`;

    // 1. Projects — total, split by status
    const segments = [
        { label: 'Processing', count: byStatus('Processing'), opacity: 1 },
        { label: 'Finished', count: byStatus('Finished'), color: '#34D399' },
        { label: 'Not yet started', count: byStatus('Not yet started'), color: '#FBBF24' },
    ];
    setKpiValue(document.getElementById('kpi-ap-total'), total);
    setKpiText('kpi-ap-total-desc', total ? segments.filter(seg => seg.count).map(seg => `${seg.count} ${seg.label.toLowerCase()}`).join(' · ') : 'No projects yet');
    document.getElementById('kpi-ap-total-visual').innerHTML = total ? kpiBarHtml(segments, `Projects: ${segments.map(seg => `${seg.count} ${seg.label.toLowerCase()}`).join(', ')}`) : '';
    document.getElementById('kpi-ap-total-footer').innerHTML = `<span>${plural(total, 'project')}</span>`;

    // 2. Modules — how many modules sit under the projects, one bar segment per project
    const counts = axisProjects.map(ap => ({ label: ap.name, count: modulesOfProject(ap.id).length }));
    const moduleTotal = projects.length;
    setKpiValue(document.getElementById('kpi-ap-modules'), moduleTotal);
    setKpiText('kpi-ap-modules-desc', `Across ${plural(total, 'project')}`);
    const opacity = [1, 0.7, 0.45, 0.28, 0.18];
    document.getElementById('kpi-ap-modules-visual').innerHTML = moduleTotal
        ? kpiBarHtml(counts.map((c, i) => ({ ...c, opacity: opacity[Math.min(i, opacity.length - 1)] })), `Modules per project: ${counts.map(c => `${c.count} in ${c.label}`).join(', ')}`)
        : '';
    const biggest = [...counts].sort((x, y) => y.count - x.count)[0];
    document.getElementById('kpi-ap-modules-footer').innerHTML = biggest && biggest.count
        ? `<span title="${escapeHtml(biggest.label)}">Most in <span class="text-slate-200">${escapeHtml(biggest.label)}</span></span><span class="text-slate-500 shrink-0">${biggest.count}</span>`
        : '<span>No modules yet</span>';

    // 3. Average progress — mean of each project's module completion
    const progress = axisProjects.map(ap => ({ name: ap.name, pct: axisProjectProgress(ap.id) })).sort((x, y) => y.pct - x.pct);
    const avg = total ? progress.reduce((sum, ap) => sum + ap.pct, 0) / total : 0;
    setKpiValue(document.getElementById('kpi-ap-avg'), +avg.toFixed(1), { decimals: 1, unit: '%' });
    setKpiText('kpi-ap-avg-desc', `Average across ${plural(total, 'project')}`);
    document.getElementById('kpi-ap-avg-visual').innerHTML = total
        ? `<div class="axis-kpi-columns" role="img" aria-label="Completion per project, highest to lowest">
            ${progress.map((ap, i) => `<span class="axis-kpi-columns__bar" style="height:${Math.max(4, ap.pct)}%;transition-delay:${i * 40}ms" title="${escapeHtml(ap.name)}: ${Math.round(ap.pct)}%"></span>`).join('')}
           </div>`
        : '';
    document.getElementById('kpi-ap-avg-footer').innerHTML = total
        ? `<span>Lowest <span class="text-slate-200 font-mono">${Math.round(progress[total - 1].pct)}%</span></span><span>Highest <span class="text-slate-200 font-mono">${Math.round(progress[0].pct)}%</span></span>`
        : '<span>No projects yet</span>';

    // 4. Next deadline — the nearest deadline among unfinished projects
    const upcoming = axisProjects
        .filter(ap => ap.deadline && ap.status !== 'Finished')
        .sort((x, y) => x.deadline.localeCompare(y.deadline));
    const next = upcoming[0];
    const nextEl = document.getElementById('kpi-ap-next');
    if (next) {
        const info = deadlineInfo(next);
        nextEl.textContent = new Date(next.deadline + 'T00:00:00').toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
        setKpiStatus('ap-next', info.text, info.cls.includes('rose') || info.cls.includes('amber') ? 'warn' : null);
        setKpiText('kpi-ap-next-desc', next.name);
    } else {
        nextEl.textContent = '—';
        setKpiStatus('ap-next', 'None');
        setKpiText('kpi-ap-next-desc', total ? 'No open project has a deadline' : 'No projects yet');
    }
    document.getElementById('kpi-ap-next-visual').innerHTML = '';
    document.getElementById('kpi-ap-next-footer').innerHTML = `<span>${plural(upcoming.length, 'open project')} with a deadline</span>`;

    revealKpiCards('axis-project-kpi-strip');
}

/** Live roll-up of a project's modules: tasks, people, open issues and time elapsed. */
function axisProjectStats(ap) {
    const mods = modulesOfProject(ap.id);
    const tasks = mods.flatMap(p => p.tasks || []);
    const people = new Set();
    mods.forEach(p => { if (p.manager) people.add(p.manager); (p.team || []).forEach(m => people.add(m)); });
    tasks.forEach(t => { if (t.handler) people.add(t.handler); });
    const openIssues = tasks.reduce((sum, t) => sum + (t.issues || []).filter(i => i.status !== 'Resolved').length, 0);
    let elapsed = null;
    if (ap.startDate && ap.deadline) {
        const start = new Date(ap.startDate + 'T00:00:00');
        const end = new Date(ap.deadline + 'T00:00:00');
        const span = end - start;
        elapsed = span > 0 ? Math.min(100, Math.max(0, ((Date.now() - start) / span) * 100)) : 100;
    }
    return {
        mods,
        progress: axisProjectProgress(ap.id),
        tasksTotal: tasks.length,
        tasksDone: tasks.filter(t => t.status === 'Finished').length,
        people: [...people],
        openIssues,
        elapsed,
    };
}

let axisProjectSort = { key: 'name', dir: 'asc' };

/** Reflect a table's sort state on its header buttons (`aria-sort` + arrow icon). */
function syncSortHeaders(attribute, sort) {
    document.querySelectorAll(`[${attribute}]`).forEach(th => {
        const active = th.getAttribute(attribute) === sort.key;
        th.setAttribute('aria-sort', active ? (sort.dir === 'asc' ? 'ascending' : 'descending') : 'none');
        const icon = th.querySelector('i');
        if (icon) icon.className = `fa-solid ${active ? (sort.dir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort'}`;
    });
}

/** Next sort state when a header is clicked: same column flips, a new column starts in its natural order. */
function nextSort(current, key, ascendingKeys) {
    return current.key === key
        ? { key, dir: current.dir === 'asc' ? 'desc' : 'asc' }
        : { key, dir: ascendingKeys.includes(key) ? 'asc' : 'desc' };
}

window.setAxisProjectSortFromSelect = function(value) {
    const [key, dir] = value.split(':');
    if (!key) return;
    axisProjectSort = { key, dir };
    renderAxisProjectsTable();
};

window.sortAxisProjects = function(key) {
    axisProjectSort = nextSort(axisProjectSort, key, ['name', 'deadline']);
    renderAxisProjectsTable();
};

const AXIS_STATUS_ORDER = { 'Not yet started': 0, 'Processing': 1, 'Finished': 2 };

window.renderAxisProjectsTable = function() {
    const tbody = document.getElementById('axis-projects-tbody');
    if (!tbody) return;
    const q = (document.getElementById('ap-flt-search')?.value || '').toLowerCase().trim();
    const statusFilter = document.getElementById('ap-flt-status')?.value || 'all';
    const fmt = iso => iso ? new Date(iso + 'T00:00:00').toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '—';

    const rows = axisProjects
        .filter(ap => (statusFilter === 'all' || ap.status === statusFilter)
            && (!q || [ap.name, ap.description, ...modulesOfProject(ap.id).map(m => m.name)].join(' ').toLowerCase().includes(q)))
        .map(ap => ({ ap, st: axisProjectStats(ap) }));

    const sortValue = {
        name: r => r.ap.name.toLowerCase(),
        status: r => AXIS_STATUS_ORDER[r.ap.status] ?? 9,
        progress: r => r.st.progress,
        modules: r => r.st.mods.length,
        tasks: r => r.st.tasksTotal ? r.st.tasksDone / r.st.tasksTotal : -1,
        issues: r => r.st.openIssues,
        deadline: r => r.ap.deadline || '9999-12-31',
    }[axisProjectSort.key];
    const dir = axisProjectSort.dir === 'asc' ? 1 : -1;
    rows.sort((x, y) => {
        const a = sortValue(x), b = sortValue(y);
        return (a < b ? -1 : a > b ? 1 : 0) * dir || x.ap.name.localeCompare(y.ap.name);
    });

    syncSortHeaders('data-ap-sort', axisProjectSort);
    syncSortSelect('ap-flt-sort', axisProjectSort);
    const view = applyListView('projects');

    document.getElementById('axis-projects-showing-text').textContent = `Showing ${rows.length} of ${axisProjects.length} project${axisProjects.length === 1 ? '' : 's'}`;

    const emptyText = axisProjects.length ? 'No projects match these filters' : 'No projects yet. Create one to start adding modules.';
    if (!rows.length) {
        tbody.innerHTML = `<tr><td colspan="9" class="py-12 text-center text-slate-400">${emptyText}</td></tr>`;
        document.getElementById('ap-view-cards').innerHTML = `<p class="col-span-full py-10 text-center text-sm text-slate-400">${emptyText}</p>`;
        document.getElementById('ap-view-board').innerHTML = boardHtml([], () => null, () => '', 'None');
        return;
    }

    if (view === 'cards') {
        document.getElementById('ap-view-cards').innerHTML = rows.map(r => renderAxisProjectCard(r)).join('');
        return;
    }
    if (view === 'board') {
        document.getElementById('ap-view-board').innerHTML = boardHtml(rows, r => r.ap.status, r => renderAxisProjectCard(r, true), 'No projects');
        return;
    }

    tbody.innerHTML = rows.map(({ ap, st }) => {
        const pct = Math.round(st.progress);
        const barColor = pct >= 90 ? 'bg-emerald-400' : pct >= 60 ? 'bg-sky-400' : pct >= 40 ? 'bg-violet-400' : 'bg-amber-400';
        const due = deadlineInfo(ap);
        const statusCls = PROJECT_STATUS_STYLES[ap.status] || PROJECT_STATUS_STYLES['Processing'];
        const moduleNames = st.mods.map(m => m.name);
        const shownPeople = st.people.slice(0, 4);
        const name = escapeHtml(ap.name);
        const peopleHtml = st.people.length
            ? `<div class="flex items-center" title="${escapeHtml(st.people.join(', '))}">
                ${shownPeople.map(n => `<span class="w-7 h-7 -ml-1.5 first:ml-0 rounded-full ring-2 ring-[#0D2650] ${avatarToneFor(n)} text-xs font-semibold inline-flex items-center justify-center">${escapeHtml(initialsOf(n))}</span>`).join('')}
                ${st.people.length > shownPeople.length ? `<span class="w-7 h-7 -ml-1.5 rounded-full ring-2 ring-[#0D2650] bg-white/10 text-slate-300 text-xs font-semibold inline-flex items-center justify-center">+${st.people.length - shownPeople.length}</span>` : ''}
               </div>`
            : '<span class="text-xs text-slate-500">No one yet</span>';
        const timeline = st.elapsed === null
            ? `<div class="text-sm text-slate-100">${fmt(ap.startDate)} → ${fmt(ap.deadline)}</div>`
            : `<div class="text-sm text-slate-100 whitespace-nowrap">${fmt(ap.startDate)} <span class="text-slate-500">→</span> ${fmt(ap.deadline)}</div>
               <div class="mt-1.5 h-1 w-full rounded-full bg-white/[0.08] overflow-hidden" title="${Math.round(st.elapsed)}% of the time used">
                   <div class="h-full rounded-full bg-sky-400/70" style="width:${st.elapsed}%"></div>
               </div>`;

        return `
        <tr class="cursor-pointer transition-colors group" onclick="openModulesOfProject(${ap.id})">
            <td class="py-3 pl-5 pr-3 axis-sticky-left">
                <div class="w-[240px]">
                    <div class="text-sm font-semibold text-white truncate group-hover:text-sky-300 transition-colors" title="${name}">${name}</div>
                    <div class="text-xs text-slate-400 truncate mt-0.5" title="${escapeHtml(ap.description || '')}">${escapeHtml(ap.description || 'No description')}</div>
                </div>
            </td>
            <td class="py-3 px-3" onclick="event.stopPropagation()">
                <div class="flex items-center gap-1.5">
                    <select onchange="updateAxisProjectStatus(${ap.id}, this.value)" aria-label="Status of ${name}"
                        class="axis-status-select ${statusCls} border text-xs font-medium pl-2.5 pr-6 py-1 rounded-full cursor-pointer focus:outline-none">
                        ${workStatusOptions(ap.status)}
                    </select>
                    ${statusMismatchHtml(statusMismatchText(ap.status, pct, st.mods.length, 'module'))}
                </div>
            </td>
            <td class="py-3 px-3">
                <div class="flex items-center gap-2 w-[120px]" title="${escapeHtml(projectProgressExplain(ap))}">
                    <div class="flex-1 h-1.5 rounded-full bg-white/[0.08] overflow-hidden">
                        <div class="h-full rounded-full ${barColor}" style="width: ${pct}%"></div>
                    </div>
                    <span class="text-xs font-mono font-semibold text-slate-200 w-9 text-right">${pct}%</span>
                </div>
            </td>
            <td class="py-3 px-3">
                <div class="text-sm text-slate-100">${st.mods.length}</div>
                <div class="text-xs text-slate-400 truncate w-[150px] mt-0.5" title="${escapeHtml(moduleNames.join(', '))}">${escapeHtml(moduleNames.join(', ') || 'None yet')}</div>
            </td>
            <td class="py-3 px-3 whitespace-nowrap">
                <span class="text-sm font-mono text-slate-100">${st.tasksDone}</span><span class="text-sm font-mono text-slate-500"> / ${st.tasksTotal}</span>
            </td>
            <td class="py-3 px-3">${peopleHtml}</td>
            <td class="py-3 px-3 whitespace-nowrap">
                ${st.openIssues
                    ? `<span class="inline-flex items-center gap-1.5 text-xs font-semibold text-amber-300"><span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>${st.openIssues} open</span>`
                    : '<span class="text-xs text-slate-500">None</span>'}
            </td>
            <td class="py-3 px-3">
                <div class="w-[230px]">
                    ${timeline}
                    <div class="text-xs mt-1 ${due.cls}">${escapeHtml(due.text)}</div>
                </div>
            </td>
            <td class="py-3 pl-3 pr-5 axis-sticky-right" onclick="event.stopPropagation()">
                <div class="flex items-center justify-end gap-1.5">
                    <button type="button" onclick="openModulesOfProject(${ap.id})" class="axis-row-btn axis-row-btn--label" aria-label="Open the modules of ${name}">
                        <span>Modules</span><i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i>
                    </button>
                    <div class="axis-row-actions" role="group" aria-label="Actions for ${name}">
                        <button type="button" onclick="openAddModuleFor(${ap.id})" class="axis-row-btn" title="Add a module" aria-label="Add a module to ${name}">
                            <i class="fa-solid fa-plus text-xs" aria-hidden="true"></i>
                        </button>
                        <button type="button" onclick="openAxisProjectEdit(${ap.id})" class="axis-row-btn" title="Edit project" aria-label="Edit ${name}">
                            <i class="fa-solid fa-pen text-xs" aria-hidden="true"></i>
                        </button>
                        <button type="button" onclick="deleteAxisProject(${ap.id})" class="axis-row-btn axis-row-btn--danger" title="${st.mods.length ? 'Remove its modules before deleting' : 'Delete project'}" aria-label="Delete ${name}">
                            <i class="fa-solid fa-trash-can text-xs" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
            </td>
        </tr>`;
    }).join('');
};

/** One project as a card (Cards view) or a compact card (Board view). */
function renderAxisProjectCard({ ap, st }, compact = false) {
    const name = escapeHtml(ap.name);
    const due = deadlineInfo(ap);
    const actions = `
        <div class="flex items-center justify-between gap-2 pt-3 mt-3 border-t border-white/[0.06]" onclick="event.stopPropagation()">
            <button type="button" onclick="openModulesOfProject(${ap.id})" class="axis-row-btn axis-row-btn--label" aria-label="Open the modules of ${name}">
                <span>Modules</span><i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i>
            </button>
            <div class="axis-row-actions" role="group" aria-label="Actions for ${name}">
                <button type="button" onclick="openAddModuleFor(${ap.id})" class="axis-row-btn" title="Add a module" aria-label="Add a module to ${name}"><i class="fa-solid fa-plus text-xs" aria-hidden="true"></i></button>
                <button type="button" onclick="openAxisProjectEdit(${ap.id})" class="axis-row-btn" title="Edit project" aria-label="Edit ${name}"><i class="fa-solid fa-pen text-xs" aria-hidden="true"></i></button>
                <button type="button" onclick="deleteAxisProject(${ap.id})" class="axis-row-btn axis-row-btn--danger" title="Delete project" aria-label="Delete ${name}"><i class="fa-solid fa-trash-can text-xs" aria-hidden="true"></i></button>
            </div>
        </div>`;
    return `
    <article class="axis-list-card" role="button" tabindex="0" onclick="openModulesOfProject(${ap.id})" onkeydown="if(event.key==='Enter'&&event.target===this)openModulesOfProject(${ap.id})">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <h3 class="text-sm font-semibold text-white truncate" title="${name}">${name}</h3>
                <p class="text-xs text-slate-400 mt-0.5 ${compact ? 'truncate' : 'line-clamp-2'}">${escapeHtml(ap.description || 'No description')}</p>
            </div>
            ${compact ? '' : `<div class="flex items-center gap-1.5 shrink-0" onclick="event.stopPropagation()">${statusSelectHtml(`updateAxisProjectStatus(${ap.id}, this.value)`, ap.status, name)}${statusMismatchHtml(statusMismatchText(ap.status, Math.round(st.progress), st.mods.length, 'module'))}</div>`}
        </div>
        <div class="mt-3">${progressBarHtml(Math.round(st.progress), projectProgressExplain(ap))}</div>
        <dl class="grid grid-cols-3 gap-x-4 gap-y-2 mt-3 text-xs">
            <div><dt class="text-slate-500">Modules</dt><dd class="text-slate-100 font-mono mt-0.5">${st.mods.length}</dd></div>
            <div><dt class="text-slate-500">Tasks done</dt><dd class="text-slate-100 font-mono mt-0.5">${st.tasksDone} / ${st.tasksTotal}</dd></div>
            <div><dt class="text-slate-500">Open issues</dt><dd class="mt-0.5 ${st.openIssues ? 'text-amber-300 font-semibold' : 'text-slate-400'}">${st.openIssues || 'None'}</dd></div>
        </dl>
        <div class="flex items-center justify-between gap-3 mt-3">
            ${avatarStackHtml(st.people)}
            <div class="text-right text-xs">
                <div class="text-slate-300 whitespace-nowrap">${shortDate(ap.deadline)}</div>
                <div class="${due.cls}">${escapeHtml(due.text)}</div>
            </div>
        </div>
        ${compact ? `<div class="mt-3" onclick="event.stopPropagation()">${statusSelectHtml(`updateAxisProjectStatus(${ap.id}, this.value)`, ap.status, name)}</div>` : actions}
    </article>`;
}

/** Open the New module drawer with this project already picked. */
window.openAddModuleFor = function(projectId) {
    openModal('modal-add-project');
    const select = document.getElementById('add-proj-project');
    if (select) select.value = String(projectId);
};

window.exportAxisProjectsCSV = function() {
    const cell = v => `"${String(v ?? '').replace(/"/g, '""')}"`;
    const rows = [['Project', 'Description', 'Status', 'Progress %', 'Modules', 'Tasks done', 'Tasks total', 'People', 'Open issues', 'Start date', 'Deadline']];
    axisProjects.forEach(ap => {
        const st = axisProjectStats(ap);
        rows.push([ap.name, ap.description, ap.status, Math.round(st.progress), st.mods.map(m => m.name).join('; '),
            st.tasksDone, st.tasksTotal, st.people.join('; '), st.openIssues, ap.startDate, ap.deadline]);
    });
    const csv = rows.map(r => r.map(cell).join(',')).join('\n');
    const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8;' }));
    const link = document.createElement('a');
    link.href = url;
    link.download = 'Axis_Projects_Export.csv';
    link.click();
    URL.revokeObjectURL(url);
    showToast('Downloaded the project list');
};

let editingAxisProjectId = null;

window.openAxisProjectEdit = function(id) {
    const ap = axisProjects.find(x => x.id === id);
    if (!ap) return;
    editingAxisProjectId = id;
    document.getElementById('edit-axis-project-name').value = ap.name;
    document.getElementById('edit-axis-project-start').value = ap.startDate || '';
    document.getElementById('edit-axis-project-deadline').value = ap.deadline || '';
    document.getElementById('edit-axis-project-status').value = ap.status;
    document.getElementById('edit-axis-project-desc').value = ap.description || '';
    AxisDrawer.open('drawer-axis-project-edit', { title: `Edit ${ap.name}` });
};

window.handleUpdateAxisProject = function(e) {
    e.preventDefault();
    const ap = axisProjects.find(x => x.id === editingAxisProjectId);
    if (!ap) return;
    AxisDrawer.submit('drawer-axis-project-edit', async () => {
        const res = await AxisAPI.request(AxisAPI.url('projects.update', ap.id), 'PATCH', {
            name: document.getElementById('edit-axis-project-name').value.trim(),
            start_date: document.getElementById('edit-axis-project-start').value || null,
            deadline: document.getElementById('edit-axis-project-deadline').value || null,
            status: document.getElementById('edit-axis-project-status').value,
            description: document.getElementById('edit-axis-project-desc').value.trim() || null
        });
        Object.assign(ap, res.data);
        projects.filter(p => p.projectId === ap.id).forEach(p => { p.projectName = ap.name; });
        syncAxisProjectOptions();
        closeModal('drawer-axis-project-edit');
        renderAxisProjectsPage();
        showToast(`Saved ${ap.name}`);
    });
};

window.deleteAxisProject = async function(id) {
    const ap = axisProjects.find(x => x.id === id);
    if (!ap) return;
    const moduleCount = modulesOfProject(id).length;
    if (moduleCount) {
        showToast(`${ap.name} still has ${moduleCount} module${moduleCount === 1 ? '' : 's'}. Move or remove them first.`);
        return;
    }
    const confirmed = await AxisDrawer.confirm({
        title: 'Delete this project?',
        breadcrumb: 'Projects',
        message: `${ap.name} has no modules. This can't be undone.`,
        confirmLabel: 'Delete project'
    });
    if (!confirmed) return;
    try {
        await AxisAPI.request(AxisAPI.url('projects.destroy', id), 'DELETE');
        axisProjects = axisProjects.filter(x => x.id !== id);
        syncAxisProjectOptions();
        renderAxisProjectsPage();
        syncQuickModulePercentages();
        updateDynamicHeader(activePageId);
        showToast(`Deleted ${ap.name}`);
    } catch (err) {
        AxisAPI.fail(err);
    }
};

// =====================================================================
// PAGE 1: PROJECT LIST TABLE & FILTERS
// =====================================================================
function populateModuleDropdowns() {
    const hdrMod = document.getElementById('header-module-select');

    if (hdrMod) {
        hdrMod.innerHTML = '<option value="">Jump to a module…</option>' +
            projects.map(p => `<option value="${p.id}">${escapeHtml(p.code)}: ${escapeHtml(p.name)}</option>`).join('');
    }
}

window.resetProjectFilters = function() {
    document.getElementById('flt-project').value = 'all';
    document.getElementById('flt-product').value = 'all';
    document.getElementById('flt-status').value = 'all';
    document.getElementById('flt-search').value = '';
    renderProjectsTable(true);
    showToast('Filters cleared');
};

window.clearSingleFilter = function(filterKey) {
    if (filterKey === 'project') document.getElementById('flt-project').value = 'all';
    if (filterKey === 'product') document.getElementById('flt-product').value = 'all';
    if (filterKey === 'status') document.getElementById('flt-status').value = 'all';
    if (filterKey === 'search') document.getElementById('flt-search').value = '';
    renderProjectsTable(true);
};

window.scrollToProjectResults = function() {
    const card = document.getElementById('project-list-results-card');
    if (!card) return;
    card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    card.classList.remove('filter-result-flash');
    void card.offsetWidth; // trigger reflow for highlight animation
    card.classList.add('filter-result-flash');
};

window.applyAndNavigateFilters = function() {
    renderProjectsTable(true);
};

function syncActiveFilterUI(filteredCount) {
    const projEl = document.getElementById('flt-project');
    const prodEl = document.getElementById('flt-product');
    const statEl = document.getElementById('flt-status');
    const srchEl = document.getElementById('flt-search');

    const projVal = projEl?.value || 'all';
    const prodVal = prodEl?.value || 'all';
    const statVal = statEl?.value || 'all';
    const srchVal = (srchEl?.value || '').trim();

    // Toggle Clear Search (X) button
    const clearBtn = document.getElementById('flt-clear-search-btn');
    if (clearBtn) {
        clearBtn.classList.toggle('hidden', !srchVal);
    }

    // Build Active Filter Chips
    const chips = [];
    if (projVal !== 'all') {
        chips.push({ key: 'project', label: `Project: ${moduleProjectFilterName() || projVal}` });
    }
    if (prodVal !== 'all') {
        chips.push({ key: 'product', label: `Domain: ${prodVal}` });
    }
    if (statVal !== 'all') {
        chips.push({ key: 'status', label: `Status: ${statVal}` });
    }
    if (srchVal) {
        chips.push({ key: 'search', label: `Search: "${srchVal}"` });
    }

    const chipsBar = document.getElementById('active-filter-chips-bar');
    const chipsList = document.getElementById('active-filter-chips-list');
    if (chipsBar && chipsList) {
        if (chips.length > 0) {
            chipsBar.classList.remove('hidden');
            chipsBar.classList.add('flex');
            chipsList.innerHTML = chips.map(c => `
                <button type="button" onclick="clearSingleFilter(${jsArg(c.key)})" class="axis-filter-chip" title="Remove this filter">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i><span>${escapeHtml(c.label)}</span>
                </button>
            `).join('');
        } else {
            chipsBar.classList.add('hidden');
            chipsBar.classList.remove('flex');
            chipsList.innerHTML = '';
        }
    }

    const matchBadge = document.getElementById('filter-match-badge');
    if (matchBadge) {
        matchBadge.textContent = `Showing ${filteredCount} of ${projects.length} modules`;

    }
}

// =====================================================================
// PROJECTS PAGE META PILLS + GLOBAL HEADER SYNC STATUS / REFRESH
// =====================================================================
function peopleOnProjects() {
    const counts = new Map();
    projects.forEach(p => {
        new Set([p.manager, ...(p.team || [])].filter(Boolean)).forEach(name => counts.set(name, (counts.get(name) || 0) + 1));
    });
    return [...counts.entries()];
}

/** Keep the Projects page metadata pills in step with the live data. */
/**
 * Projects page status banner — reflects the real open issue notes
 * (any note not marked Resolved, the same rule as the People KPI card).
 */
function renderAttentionBanner() {
    const title = document.getElementById('axis-attention-title');
    const desc = document.getElementById('axis-attention-desc');
    const icon = document.getElementById('axis-attention-icon');
    if (!title || !desc || !icon) return;

    const affected = projects
        .map(p => ({ name: p.name, open: (p.tasks || []).reduce((n, t) => n + (t.issues || []).filter(i => i.status !== 'Resolved').length, 0) }))
        .filter(p => p.open > 0)
        .sort((a, b) => b.open - a.open);
    const total = affected.reduce((n, p) => n + p.open, 0);
    const toneClasses = ['bg-amber-500/15', 'border-amber-400/30', 'text-amber-300', 'bg-emerald-500/15', 'border-emerald-400/30', 'text-emerald-300'];
    icon.classList.remove(...toneClasses);

    if (total === 0) {
        title.textContent = 'No open issues';
        desc.textContent = 'Every issue note on every module is resolved.';
        icon.classList.add('bg-emerald-500/15', 'border-emerald-400/30', 'text-emerald-300');
        return;
    }

    const names = affected.map(p => p.open > 1 ? `${p.name} (${p.open})` : p.name);
    const listed = names.length <= 3 ? names : [...names.slice(0, 3), `${names.length - 3} more`];
    const sentence = listed.length > 1 ? `${listed.slice(0, -1).join(', ')} and ${listed[listed.length - 1]}` : listed[0];
    title.textContent = `${total} open issue${total === 1 ? '' : 's'} across ${affected.length} module${affected.length === 1 ? '' : 's'}`;
    desc.textContent = `Needs attention: ${sentence}.`;
    icon.classList.add('bg-amber-500/15', 'border-amber-400/30', 'text-amber-300');
}

function renderContextBar() {
    const active = document.getElementById('meta-active-count');
    const people = document.getElementById('meta-people-count');
    if (active) active.textContent = `${projects.filter(p => p.status === 'Processing').length} active`;
    if (people) { const count = peopleOnProjects().length; people.textContent = `${count} ${count === 1 ? 'person' : 'people'}`; }
    const updatedAt = document.getElementById('meta-updated-at');
    if (updatedAt && updatedAt.dateTime) {
        const at = new Date(updatedAt.dateTime);
        updatedAt.textContent = `${at.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })}, ${at.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', hour12: false })}`;
        updatedAt.title = at.toLocaleString();
    }
    renderAttentionBanner();
}

window.refreshWorkspaceData = function(btn) {
    const icon = btn?.querySelector('i');
    if (icon) icon.classList.add('fa-spin');
    const reload = () => window.location.reload();
    if (AxisAPI.pending > 0) {
        showToast('Finishing your last change first…');
        document.addEventListener('axis:sync', function waitForIdle(e) {
            if (e.detail.pending === 0) { document.removeEventListener('axis:sync', waitForIdle); reload(); }
        });
        return;
    }
    reload();
};

// Live save status pill in the top header
document.addEventListener('axis:sync', (e) => {
    const pill = document.getElementById('header-sync-pill');
    const label = document.getElementById('header-sync-label');
    if (!pill || !label) return;
    const icon = pill.querySelector('i');
    if (e.detail.pending > 0) {
        pill.dataset.state = 'saving';
        label.textContent = 'Saving…';
        icon.className = 'fa-solid fa-circle-notch fa-spin';
    } else if (e.detail.failed) {
        pill.dataset.state = 'error';
        label.textContent = "Couldn't save";
        icon.className = 'fa-solid fa-triangle-exclamation';
    } else {
        pill.dataset.state = 'idle';
        label.textContent = 'Up to date';
        icon.className = 'fa-regular fa-circle-check';
    }
    pill.title = label.textContent;
});

// =====================================================================
// EXECUTIVE KPI STRIP (Projects page) — <x-axis.kpi-card> tiles
// Everything here is derived from the live project data. There is no
// historical/time-series data, so the visuals show current breakdowns
// (never trends or invented comparisons).
// =====================================================================
const prefersReducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/** Set a KPI number, counting up from its previous value when it changes. */
function setKpiValue(el, value, { decimals = 0, unit = '' } = {}) {
    if (!el) return;
    const unitHtml = unit ? `<span class="axis-kpi__value-unit">${escapeHtml(unit)}</span>` : '';
    const render = v => { el.innerHTML = `${v.toFixed(decimals)}${unitHtml}`; };
    const previous = parseFloat(el.dataset.kpiCurrent);
    el.dataset.kpiCurrent = String(value);
    el.setAttribute('aria-label', `${value.toFixed(decimals)}${unit}`);
    if (Number.isNaN(previous) ? prefersReducedMotion() : (previous === value || prefersReducedMotion())) {
        render(value);
        return;
    }
    const from = Number.isNaN(previous) ? 0 : previous;
    const start = performance.now();
    const duration = 650;
    cancelAnimationFrame(el._kpiFrame);
    const step = now => {
        const t = Math.min(1, (now - start) / duration);
        const eased = 1 - Math.pow(1 - t, 3);
        render(from + (value - from) * eased);
        if (t < 1) el._kpiFrame = requestAnimationFrame(step);
    };
    el._kpiFrame = requestAnimationFrame(step);
}

function setKpiStatus(key, label, tone = null) {
    const badge = document.getElementById(`kpi-${key}-status`);
    if (!badge) return;
    badge.querySelector('[data-kpi-status-label]').textContent = label;
    if (tone) badge.dataset.tone = tone; else delete badge.dataset.tone;
}

function setKpiText(id, text) {
    const el = document.getElementById(id);
    if (el) el.textContent = text;
}

/** Segmented proportion bar + legend. Segments: [{ label, count, opacity|color }]. */
function kpiBarHtml(segments, ariaLabel, legendMax = 0) {
    const total = segments.reduce((sum, seg) => sum + seg.count, 0) || 1;
    const visible = segments.filter(seg => seg.count > 0);
    const segStyle = seg => seg.color ? `background:${seg.color};` : `opacity:${seg.opacity ?? 1};`;
    return `
        <div class="axis-kpi-bar" role="img" aria-label="${escapeHtml(ariaLabel)}">
            ${visible.map((seg, i) => `<span class="axis-kpi-bar__seg" style="flex:${seg.count / total};${segStyle(seg)}transition-delay:${i * 70}ms" title="${escapeHtml(seg.label)}: ${seg.count}"></span>`).join('')}
        </div>
        ${legendMax === 0 ? '' : `<div class="axis-kpi-legend">
            ${visible.slice(0, legendMax).map(seg => `<span class="axis-kpi-legend__item"><span class="axis-kpi-legend__dot" style="${seg.color ? `background:${seg.color}` : `background:rgb(var(--kpi-rgb));opacity:${seg.opacity ?? 1}`}"></span>${escapeHtml(seg.label)} <span class="text-slate-300 font-medium">${seg.count}</span></span>`).join('')}
            ${visible.length > legendMax ? `<span class="axis-kpi-legend__item text-slate-500" title="${escapeHtml(visible.slice(legendMax).map(seg => `${seg.label}: ${seg.count}`).join(', '))}">+${visible.length - legendMax} more</span>` : ''}
        </div>`}`;
}

/**
 * Replay the KPI strip's entrance each time the Projects page is opened:
 * cards fade/rise in with a stagger, numbers count up from 0 and the
 * micro-charts grow in again (renderProjectKpis() then animates them).
 */
function prepareKpiEntrance(stripId = 'project-kpi-strip') {
    const strip = document.getElementById(stripId);
    if (!strip || prefersReducedMotion()) return;
    const cards = [...strip.querySelectorAll('.axis-kpi')];
    cards.forEach((card, i) => {
        clearTimeout(card._entranceTimer);
        card.classList.remove('is-revealed');
        card.classList.add('is-entering');
        card.style.transitionDelay = '0ms';
        const value = card.querySelector('[data-kpi-value]');
        if (value) value.dataset.kpiCurrent = '0';
        // next frames: drop the hidden state with a small stagger
        requestAnimationFrame(() => requestAnimationFrame(() => {
            card.style.transitionDelay = `${i * 70}ms`;
            card.classList.remove('is-entering');
            card._entranceTimer = setTimeout(() => { card.style.transitionDelay = ''; }, 600 + i * 70);
        }));
    });
}

function revealKpiCards(stripId = 'project-kpi-strip') {
    const cards = document.querySelectorAll(`#${stripId} .axis-kpi`);
    if (prefersReducedMotion()) { cards.forEach(c => c.classList.add('is-revealed')); return; }
    requestAnimationFrame(() => requestAnimationFrame(() => cards.forEach(c => c.classList.add('is-revealed'))));
}

function renderProjectKpis() {
    if (!document.getElementById('project-kpi-strip')) return;
    // Scoped to the project picked in the module filter (all modules when none is picked)
    const mods = scopedModules();
    const total = mods.length;
    const byStatus = status => mods.filter(p => p.status === status).length;
    const inProgress = byStatus('Processing');

    // 1. Active modules — real processing count, with the full status breakdown
    setKpiValue(document.getElementById('kpi-proj-active'), inProgress);
    setKpiText('kpi-proj-active-desc', `of ${total} module${total === 1 ? '' : 's'} processing`);
    const statusSegments = [
        { label: 'Processing', count: inProgress, opacity: 1 },
        { label: 'Finished', count: byStatus('Finished'), color: '#34D399' },
        { label: 'Not yet started', count: byStatus('Not yet started'), color: '#FBBF24' },
    ];
    document.getElementById('kpi-proj-active-visual').innerHTML = kpiBarHtml(statusSegments, `Module status: ${statusSegments.map(s => `${s.count} ${s.label.toLowerCase()}`).join(', ')}`);
    const notActive = total - inProgress;
    const breakdown = statusSegments.filter(seg => seg.count > 0 && seg.label !== 'Processing').map(seg => `${seg.count} ${seg.label.toLowerCase()}`);
    document.getElementById('kpi-proj-active-footer').innerHTML = `<span title="${escapeHtml(statusSegments.map(seg => `${seg.label}: ${seg.count}`).join(', '))}">${total === 0 ? 'No modules yet' : notActive === 0 ? 'Every module is processing' : escapeHtml(breakdown.join(' · '))}</span>`;

    // 2. Projects you can see — total, split by product
    setKpiValue(document.getElementById('kpi-proj-total'), total);
    const productCounts = {};
    mods.forEach(p => { const key = (p.product || 'Other').replace(/^Axis\s+/, ''); productCounts[key] = (productCounts[key] || 0) + 1; });
    const products = Object.entries(productCounts).sort((a, b) => b[1] - a[1]);
    setKpiText('kpi-proj-total-desc', `Across ${products.length} product${products.length === 1 ? '' : 's'}`);
    const productOpacity = [1, 0.7, 0.45, 0.28, 0.18];
    document.getElementById('kpi-proj-total-visual').innerHTML = kpiBarHtml(
        products.map(([label, count], i) => ({ label, count, opacity: productOpacity[Math.min(i, productOpacity.length - 1)] })),
        `Modules by product: ${products.map(([l, c]) => `${c} ${l}`).join(', ')}`
    );
    const [topProduct, topCount] = products[0] || ['—', 0];
    document.getElementById('kpi-proj-total-footer').innerHTML = total
        ? `<span title="${escapeHtml(topProduct)}">Mostly <span class="text-slate-200">${escapeHtml(topProduct)}</span></span><span class="text-slate-500 shrink-0">${Math.round((topCount / total) * 100)}%</span>`
        : '<span>No modules yet</span>';

    // 3. Average progress — mean of project completion, with one bar per project
    const completions = mods.map(p => computeProjectCompletion(p));
    const avg = total ? completions.reduce((s, v) => s + v, 0) / total : 0;
    setKpiValue(document.getElementById('kpi-proj-avg'), +avg.toFixed(1), { decimals: 1, unit: '%' });
    setKpiText('kpi-proj-avg-desc', `Average across ${total} module${total === 1 ? '' : 's'}`);
    const sorted = mods.map((p, i) => ({ name: p.name, pct: completions[i] })).sort((a, b) => b.pct - a.pct);
    document.getElementById('kpi-proj-avg-visual').innerHTML = `
        <div class="axis-kpi-columns" role="img" aria-label="Completion per module, highest to lowest">
            ${sorted.map((p, i) => `<span class="axis-kpi-columns__bar" style="height:${Math.max(4, p.pct)}%;transition-delay:${i * 40}ms" title="${escapeHtml(p.name)}: ${Math.round(p.pct)}%"></span>`).join('')}
        </div>`;
    document.getElementById('kpi-proj-avg-footer').innerHTML = total
        ? `<span>Lowest <span class="text-slate-200 font-mono">${Math.round(sorted[sorted.length - 1].pct)}%</span></span><span>Highest <span class="text-slate-200 font-mono">${Math.round(sorted[0].pct)}%</span></span>`
        : '<span>No modules yet</span>';

    // 4. People on modules — unique leads + team members; status from real open issue notes
    const people = new Set();
    const leads = new Set();
    mods.forEach(p => {
        if (p.manager) { people.add(p.manager); leads.add(p.manager); }
        (p.team || []).forEach(m => people.add(m));
    });
    setKpiValue(document.getElementById('kpi-proj-squad'), people.size);
    setKpiText('kpi-proj-squad-desc', `${leads.size} lead${leads.size === 1 ? '' : 's'} · ${people.size - leads.size} teammate${people.size - leads.size === 1 ? '' : 's'}`);
    const shown = [...people].slice(0, 6);
    document.getElementById('kpi-proj-squad-visual').innerHTML = `
        <div class="axis-kpi-avatars" role="img" aria-label="${escapeHtml([...people].join(', '))}">
            ${shown.map(name => `<span class="${avatarToneFor(name)}" title="${escapeHtml(name)}">${escapeHtml(initialsOf(name))}</span>`).join('')}
            ${people.size > shown.length ? `<span class="bg-white/10 text-slate-300">+${people.size - shown.length}</span>` : ''}
        </div>`;
    const openIssues = mods.flatMap(p => (p.tasks || []).flatMap(t => (t.issues || []).filter(i => i.status !== 'Resolved')));
    const projectsWithIssues = mods.filter(p => (p.tasks || []).some(t => (t.issues || []).some(i => i.status !== 'Resolved'))).length;
    if (openIssues.length === 0) {
        setKpiStatus('proj-squad', 'Healthy');
        document.getElementById('kpi-proj-squad-footer').innerHTML = '<span title="Based on task notes">No open blockers</span>';
    } else {
        setKpiStatus('proj-squad', `${openIssues.length} open`, 'warn');
        document.getElementById('kpi-proj-squad-footer').innerHTML = `<span title="Based on task notes"><span class="text-amber-300">${openIssues.length} open issue${openIssues.length === 1 ? '' : 's'}</span> across ${projectsWithIssues} module${projectsWithIssues === 1 ? '' : 's'}</span>`;
    }

    revealKpiCards();
}

/** Whether a module passes the current Modules page filters. Shared by the list and the create flow. */
function moduleMatchesModuleFilters(p) {
    const value = id => document.getElementById(id)?.value || 'all';
    const projVal = value('flt-project');
    const prodVal = value('flt-product');
    const statVal = value('flt-status');
    const srchVal = (document.getElementById('flt-search')?.value || '').toLowerCase().trim();

    return (projVal === 'all' || Number(p.projectId) === parseInt(projVal, 10))
        && (prodVal === 'all' || p.product === prodVal)
        && (statVal === 'all' || p.status === statVal)
        && (!srchVal || [p.name, p.code, p.manager, ...(p.team || [])]
            .join(' ').toLowerCase().includes(srchVal));
}

window.renderProjectsTable = function(shouldScroll = false) {
    const tbody = document.getElementById('projects-tbody');
    if (!tbody) return;

    renderProjectKpis();

    const projVal = document.getElementById('flt-project')?.value || 'all';
    const filtered = projects.filter(moduleMatchesModuleFilters);

    syncActiveFilterUI(filtered.length);
    renderContextBar();

    if (shouldScroll) {
        scrollToProjectResults();
    }

    const moduleView = applyListView('modules');
    if (filtered.length === 0) {
        const emptyText = projects.length === 0 ? 'No modules yet. Create a project, then add a module to it.' : 'No modules match these filters';
        document.getElementById('mod-view-cards').innerHTML = `<p class="col-span-full py-10 text-center text-sm text-slate-400">${emptyText}</p>`;
        document.getElementById('mod-view-board').innerHTML = boardHtml([], () => null, () => '', 'None');
        tbody.innerHTML = projects.length === 0
            ? `<tr><td colspan="9" class="py-12 text-center text-slate-400">No modules yet. Create a project, then add a module to it.</td></tr>`
            : `<tr><td colspan="9" class="py-12 text-center text-slate-400">No modules match these filters</td></tr>`;
        document.getElementById('projects-showing-text').textContent = `Showing 0 of ${projects.length} modules`;
        return;
    }

    const sortValue = {
        custom: p => Number(p.sortOrder) || 9999,
        name: p => p.name.toLowerCase(),
        status: p => AXIS_STATUS_ORDER[p.status] ?? 9,
        progress: p => computeProjectCompletion(p),
        lead: p => (p.manager || '').toLowerCase(),
        tasks: p => moduleStats(p).tasksTotal ? moduleStats(p).tasksDone / moduleStats(p).tasksTotal : -1,
        issues: p => moduleStats(p).openIssues,
        deadline: p => p.deadline || '9999-12-31',
    }[moduleSort.key];
    const dir = moduleSort.dir === 'asc' ? 1 : -1;
    const sorted = [...filtered].sort((x, y) => {
        const a = sortValue(x), b = sortValue(y);
        if (moduleSort.key === 'custom') return a - b || x.id - y.id;
        return (a < b ? -1 : a > b ? 1 : 0) * dir || x.name.localeCompare(y.name);
    });
    syncSortHeaders('data-mod-sort', moduleSort);
    syncSortSelect('flt-sort', moduleSort);
    if (moduleView === 'cards') {
        document.getElementById('mod-view-cards').innerHTML = sorted.map(p => renderModuleCard(p, false, projVal === 'all')).join('');
    } else if (moduleView === 'board') {
        document.getElementById('mod-view-board').innerHTML = boardHtml(sorted, p => p.status, p => renderModuleCard(p, true, projVal === 'all'), 'No modules');
    } else {
        tbody.innerHTML = sorted.map(p => renderProjectRow(p, projVal === 'all')).join('');
    }

    document.getElementById('projects-showing-text').textContent = `Showing ${filtered.length} of ${projects.length} modules`;
};

const AVATAR_TONES = [
    'bg-sky-500/25 text-sky-200',
    'bg-emerald-500/25 text-emerald-200',
    'bg-violet-500/25 text-violet-200',
    'bg-amber-500/25 text-amber-200',
    'bg-rose-500/25 text-rose-200',
];

const PRODUCT_DOTS = {
    'Axis Core Suite': 'bg-emerald-400',
    'Axis Hospitality PMS': 'bg-indigo-400',
    'Axis Commercial POS': 'bg-amber-400',
    'Axis Enterprise Finance': 'bg-cyan-400',
};

const PROJECT_STATUS_STYLES = {
    'Processing': 'bg-sky-500/15 text-sky-200 border-sky-400/30',
    'Finished': 'bg-emerald-500/15 text-emerald-200 border-emerald-400/30',
    'Not yet started': 'bg-amber-500/15 text-amber-200 border-amber-400/30',
};

function initialsOf(name) {
    return String(name || '?').split(/\s+/).filter(Boolean).map(w => w[0]).join('').substring(0, 2).toUpperCase();
}

/** Stable colour per person so the same name always gets the same avatar tone. */
function avatarToneFor(name) {
    let hash = 0;
    for (const ch of String(name || '')) hash = (hash * 31 + ch.charCodeAt(0)) >>> 0;
    return AVATAR_TONES[hash % AVATAR_TONES.length];
}

/** Days until the deadline, computed live (stored labels go stale). */
function deadlineInfo(p) {
    if (p.status === 'Finished') {
        return p.maintEnd && p.maintEnd !== '-'
            ? { text: `Until ${p.maintEnd}`, title: `Support until ${p.maintEnd}`, cls: 'text-teal-300/80' }
            : { text: 'Done', cls: 'text-slate-500' };
    }
    if (!p.deadline) return { text: 'No deadline', cls: 'text-slate-500' };
    const today = new Date(); today.setHours(0, 0, 0, 0);
    const due = new Date(p.deadline + 'T00:00:00');
    const days = Math.round((due - today) / 86400000);
    if (days < 0) return { text: `${-days} day${days === -1 ? '' : 's'} late`, cls: 'text-rose-300' };
    if (days === 0) return { text: 'Due today', cls: 'text-amber-300' };
    return { text: `${days} day${days === 1 ? '' : 's'} left`, cls: days <= 14 ? 'text-amber-300' : 'text-emerald-300' };
}

let moduleSort = { key: 'custom', dir: 'asc' };
let moduleDragId = null;

window.setModuleSortFromSelect = function(value) {
    const [key, dir] = value.split(':');
    if (!key) return;
    moduleSort = { key, dir };
    renderProjectsTable();
};

window.sortModules = function(key) {
    moduleSort = nextSort(moduleSort, key, ['name', 'lead', 'deadline']);
    renderProjectsTable();
};

/** Drag handle shown on module rows/cards for users who can reorder the list. */
function moduleDragHandle(name, compact = false) {
    if (!canReorderModules) return '';
    return `<button type="button" data-module-drag-handle onclick="event.stopPropagation()" onkeydown="handleModuleDragKeydown(event)"
        class="axis-module-drag ${compact ? '-mt-0.5' : ''}" title="Drag to reorder · Arrow Up/Down when focused"
        aria-label="Drag ${name} to reorder; press Arrow Up or Arrow Down to move it" aria-keyshortcuts="ArrowUp ArrowDown">
        <i class="fa-solid fa-grip-vertical text-[11px]" aria-hidden="true"></i>
    </button>`;
}

function moduleItemsContainer(zone) {
    return zone.id === 'mod-view-table' ? document.getElementById('projects-tbody') : zone;
}

/** Keyboard equivalent of dragging: move the focused module one place in its view. */
window.handleModuleDragKeydown = function(event) {
    if (event.key !== 'ArrowUp' && event.key !== 'ArrowDown') return;

    const handle = event.target.closest?.('[data-module-drag-handle]');
    const item = handle?.closest('[data-module-id]');
    const zone = item?.closest('[id^="mod-view-"]');
    if (!handle || !item || !zone) return;

    const scope = zone.id === 'mod-view-board' ? item.closest('.axis-board-col') : zone;
    const container = scope ? moduleItemsContainer(scope) : null;
    if (!container) return;

    const items = [...container.querySelectorAll('[data-module-id]')];
    const index = items.indexOf(item);
    const neighbour = items[index + (event.key === 'ArrowUp' ? -1 : 1)];
    if (!neighbour) return;

    event.preventDefault();
    event.stopPropagation();
    const movedId = item.dataset.moduleId;
    const focusMovedHandle = () => zone.querySelector(`[data-module-id="${movedId}"] [data-module-drag-handle]`)?.focus({ preventScroll: true });
    saveModuleOrder(container, Number(movedId), {
        element: neighbour,
        after: event.key === 'ArrowDown',
    }, focusMovedHandle);
    focusMovedHandle();
};

function moduleDragScope(event, zone) {
    if (zone.id === 'mod-view-board') {
        return event.target.closest?.('.axis-board-col') || null;
    }

    return zone;
}

/** Find the row/card the pointer is nearest, even when it is over empty space. */
function moduleDropPosition(event, zone) {
    const scope = moduleDragScope(event, zone);
    if (!scope) return null;

    const items = [...scope.querySelectorAll('[data-module-id]')];
    if (!items.length) return null;

    const direct = event.target.closest?.('[data-module-id]');
    const element = direct && scope.contains(direct)
        ? direct
        : items.reduce((nearest, item) => {
            const rect = item.getBoundingClientRect();
            const distance = Math.hypot(
                Math.max(rect.left - event.clientX, 0, event.clientX - rect.right),
                Math.max(rect.top - event.clientY, 0, event.clientY - rect.bottom)
            );
            return !nearest || distance < nearest.distance ? { item, distance } : nearest;
        }, null)?.item;

    if (!element) return null;

    const rect = element.getBoundingClientRect();
    const pointerBelow = event.clientY > rect.top + rect.height / 2;
    const pointerRight = event.clientX > rect.left + rect.width / 2;
    const isCard = zone.id !== 'mod-view-table';
    const after = pointerBelow || (isCard && event.clientY >= rect.top && event.clientY <= rect.bottom && pointerRight);

    return { element, after };
}

function moduleDropAllowed(zone, point) {
    if (!point) return false;
    if (zone.id !== 'mod-view-board') return true;

    const dragged = zone.querySelector(`[data-module-id="${moduleDragId}"]`);
    const draggedColumn = dragged?.closest('.axis-board-col');
    const targetColumn = point.element.closest('.axis-board-col');

    return !draggedColumn || !targetColumn || draggedColumn === targetColumn;
}

function clearModuleDropMarkers() {
    document.querySelectorAll('[data-module-id].is-drop-before, [data-module-id].is-drop-after')
        .forEach(el => el.classList.remove('is-drop-before', 'is-drop-after'));
}

function scrollModuleDropEdges(event) {
    const edge = 56;
    if (event.clientY < edge) {
        window.scrollBy(0, -14);
    } else if (event.clientY > window.innerHeight - edge) {
        window.scrollBy(0, 14);
    }
}

window.handleModuleDragStart = function(event) {
    const item = event.target.closest?.('[data-module-id]');
    if (!item || !item.draggable) return;

    const handle = event.target.closest?.('[data-module-drag-handle]');
    const interactive = event.target.closest?.('button:not([data-module-drag-handle]), select, input, textarea, a');
    if (!handle && interactive) {
        event.preventDefault();
        return;
    }

    const draggedId = Number(item.dataset.moduleId);
    moduleDragId = draggedId;
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', String(draggedId));
    // Defer the fade until after the browser has snapshotted the drag image.
    setTimeout(() => {
        if (moduleDragId === draggedId) item.classList.add('is-dragging');
    }, 0);
};

window.handleModuleDragOver = function(event) {
    if (moduleDragId === null) return;

    const zone = event.currentTarget;
    const point = moduleDropPosition(event, zone);
    if (!moduleDropAllowed(zone, point)) {
        clearModuleDropMarkers();
        event.dataTransfer.dropEffect = 'none';
        return;
    }

    event.preventDefault();
    const isSelf = Number(point.element.dataset.moduleId) === moduleDragId;
    event.dataTransfer.dropEffect = isSelf ? 'none' : 'move';
    clearModuleDropMarkers();
    scrollModuleDropEdges(event);

    if (!isSelf) {
        point.element.classList.add(point.after ? 'is-drop-after' : 'is-drop-before');
    }
};

window.handleModuleDrop = function(event) {
    if (moduleDragId === null) return;

    const zone = event.currentTarget;
    const point = moduleDropPosition(event, zone);
    if (!moduleDropAllowed(zone, point)) {
        clearModuleDropMarkers();
        event.dataTransfer.dropEffect = 'none';
        return;
    }

    event.preventDefault();
    const movedId = moduleDragId;
    const itemContainer = moduleItemsContainer(zone);
    handleModuleDragEnd();

    if (Number(point.element.dataset.moduleId) === movedId || !itemContainer) return;
    saveModuleOrder(itemContainer, movedId, point);
};

window.handleModuleDragEnd = function() {
    moduleDragId = null;
    document.querySelectorAll('[data-module-id].is-dragging')
        .forEach(el => el.classList.remove('is-dragging'));
    clearModuleDropMarkers();
};

/** Reorder only the modules the member can see, keeping hidden modules in their relative positions. */
function saveModuleOrder(container, movedId, point, onFailedReorder = null) {
    const targetId = Number(point.element.dataset.moduleId);
    const isVisible = id => Boolean(container.querySelector(`[data-module-id="${id}"]`));
    if (!isVisible(movedId) || !isVisible(targetId) || targetId === movedId) return;

    const previousProjects = projects.map(p => ({ ...p }));
    const previousSort = { ...moduleSort };
    const remainingIds = projects.map(p => p.id).filter(id => id !== movedId);
    const targetIndex = remainingIds.indexOf(targetId);
    if (targetIndex === -1) return;

    remainingIds.splice(point.after ? targetIndex + 1 : targetIndex, 0, movedId);

    const byId = new Map(projects.map(p => [p.id, p]));
    projects = remainingIds.map(id => byId.get(id)).filter(Boolean);
    projects.forEach((p, index) => { p.sortOrder = index + 1; });
    moduleSort = { key: 'custom', dir: 'asc' };
    renderProjectsTable();

    AxisAPI.request(AxisAPI.url('modules.reorder'), 'POST', { ordered_ids: projects.map(p => p.id) })
        .then(() => showToast('Module order saved'))
        .catch(err => AxisAPI.fail(err, () => {
            projects = previousProjects;
            moduleSort = previousSort;
            renderProjectsTable();
            onFailedReorder?.();
        }));
}

function initModuleDragAndDrop() {
    if (!canReorderModules) return;
    ['mod-view-table', 'mod-view-cards', 'mod-view-board'].forEach(id => {
        const zone = document.getElementById(id);
        if (!zone) return;
        zone.addEventListener('dragstart', handleModuleDragStart);
        zone.addEventListener('dragover', handleModuleDragOver);
        zone.addEventListener('drop', handleModuleDrop);
        zone.addEventListener('dragend', handleModuleDragEnd);
        zone.addEventListener('dragleave', event => {
            if (!zone.contains(event.relatedTarget)) clearModuleDropMarkers();
        });
    });
}

/** Task roll-up for one module: finished / total tasks and unresolved issue notes. */
function moduleStats(p) {
    const tasks = p.tasks || [];
    return {
        tasksTotal: tasks.length,
        tasksDone: tasks.filter(t => t.status === 'Finished').length,
        openIssues: tasks.reduce((sum, t) => sum + (t.issues || []).filter(i => i.status !== 'Resolved').length, 0),
    };
}

/** One module as a card (Cards view) or a compact card (Board view). */
function renderModuleCard(p, compact = false, showProject = false) {
    const name = escapeHtml(p.name);
    const st = moduleStats(p);
    const due = deadlineInfo(p);
    const people = [p.manager, ...(p.team || [])].filter((n, i, all) => n && all.indexOf(n) === i);
    const actions = `
        <div class="flex items-center justify-between gap-2 pt-3 mt-3 border-t border-white/[0.06]" onclick="event.stopPropagation()">
            <button type="button" onclick="openProjectTaskList(${p.id})" class="axis-row-btn axis-row-btn--label" aria-label="Open the tasks of ${name}">
                <span>Tasks</span><i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i>
            </button>
            <div class="axis-row-actions" role="group" aria-label="Actions for ${name}">
                <button type="button" onclick="openAddTaskFor(${p.id})" class="axis-row-btn" title="Add a task" aria-label="Add a task to ${name}"><i class="fa-solid fa-plus text-xs" aria-hidden="true"></i></button>
                <button type="button" onclick="openProjectDetails(${p.id})" class="axis-row-btn" title="Module details" aria-label="Details of ${name}"><i class="fa-solid fa-circle-info text-xs" aria-hidden="true"></i></button>
                <button type="button" onclick="openProjectEdit(${p.id})" class="axis-row-btn" title="Edit module" aria-label="Edit ${name}"><i class="fa-solid fa-pen text-xs" aria-hidden="true"></i></button>
            </div>
        </div>`;
    return `
    <article class="axis-list-card" data-module-id="${p.id}" draggable="${canReorderModules ? 'true' : 'false'}" onclick="openProjectTaskList(${p.id})">
        <div class="flex items-start justify-between gap-3">
            <div class="flex items-center gap-2.5 min-w-0">
                ${moduleDragHandle(name, compact)}
                <span class="w-8 h-8 rounded-lg ${p.badgeClass} font-bold text-xs flex items-center justify-center shrink-0">${escapeHtml(p.projBadge)}</span>
                <div class="min-w-0">
                    <h3 class="text-sm font-semibold text-white truncate" title="${name}">${name}</h3>
                    <p class="text-xs text-slate-400 truncate mt-0.5">${showProject && p.projectName ? `${escapeHtml(p.projectName)} · ` : ''}<span class="font-mono">${escapeHtml(p.code)}</span> · ${escapeHtml((p.product || '').replace(/^Axis\s+/, '') || 'No product')}</p>
                </div>
            </div>
            ${compact ? '' : `<div class="flex items-center gap-1.5 shrink-0" onclick="event.stopPropagation()">${statusSelectHtml(`updateProjectStatus(${p.id}, this.value); renderProjectsTable();`, p.status, name)}${statusMismatchHtml(statusMismatchText(p.status, Math.round(computeProjectCompletion(p)), (p.tasks || []).length, 'task'))}</div>`}
        </div>
        <div class="mt-3">${progressBarHtml(Math.round(computeProjectCompletion(p)), moduleProgressExplain(p))}</div>
        <dl class="grid grid-cols-3 gap-x-4 gap-y-2 mt-3 text-xs">
            <div class="min-w-0"><dt class="text-slate-500">Lead</dt><dd class="text-slate-100 truncate mt-0.5" title="${escapeHtml(p.manager)}">${escapeHtml(p.manager)}</dd></div>
            <div><dt class="text-slate-500">Tasks done</dt><dd class="text-slate-100 font-mono mt-0.5">${st.tasksDone} / ${st.tasksTotal}</dd></div>
            <div><dt class="text-slate-500">Open issues</dt><dd class="mt-0.5 ${st.openIssues ? 'text-amber-300 font-semibold' : 'text-slate-400'}">${st.openIssues || 'None'}</dd></div>
        </dl>
        <div class="flex items-center justify-between gap-3 mt-3">
            ${avatarStackHtml(people)}
            <div class="text-right text-xs">
                <div class="text-slate-300 whitespace-nowrap">${shortDate(p.deadline)}</div>
                <div class="${due.cls}">${escapeHtml(due.text)}</div>
            </div>
        </div>
        ${compact ? `<div class="mt-3" onclick="event.stopPropagation()">${statusSelectHtml(`updateProjectStatus(${p.id}, this.value); renderProjectsTable();`, p.status, name)}</div>` : actions}
    </article>`;
}

/** Open a module's task list with the New task drawer ready. */
window.openAddTaskFor = function(moduleId) {
    openProjectTaskList(moduleId);
    openModal('modal-create-task');
};

function renderProjectRow(p, showProject = false) {
    const pct = Math.round(computeProjectCompletion(p));
    const barColor = pct >= 90 ? 'bg-emerald-400' : pct >= 60 ? 'bg-sky-400' : pct >= 40 ? 'bg-violet-400' : 'bg-amber-400';
    const due = deadlineInfo(p);
    const st = moduleStats(p);
    const team = p.team || [];
    const shown = team.slice(0, 3);
    const extra = team.length - shown.length;
    const statusCls = PROJECT_STATUS_STYLES[p.status] || PROJECT_STATUS_STYLES['Processing'];
    const name = escapeHtml(p.name);
    const fmt = iso => iso ? new Date(iso + 'T00:00:00').toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '—';

    let elapsed = null;
    if (p.startDate && p.deadline) {
        const start = new Date(p.startDate + 'T00:00:00');
        const span = new Date(p.deadline + 'T00:00:00') - start;
        elapsed = span > 0 ? Math.min(100, Math.max(0, ((Date.now() - start) / span) * 100)) : 100;
    }

    const teamHtml = shown.map(m => `
        <span class="w-7 h-7 -ml-1.5 first:ml-0 rounded-full ring-2 ring-[#0D2650] ${avatarToneFor(m)} text-xs font-semibold inline-flex items-center justify-center">${escapeHtml(initialsOf(m))}</span>
    `).join('') + (extra > 0
        ? `<span class="w-7 h-7 -ml-1.5 rounded-full ring-2 ring-[#0D2650] bg-white/10 text-slate-300 text-xs font-semibold inline-flex items-center justify-center">+${extra}</span>`
        : '');

    return `
        <tr class="cursor-pointer transition-colors group" data-module-id="${p.id}" draggable="${canReorderModules ? 'true' : 'false'}" onclick="openProjectTaskList(${p.id})">
            <td class="py-3 pl-5 pr-3 axis-sticky-left">
                <div class="flex items-center gap-3 w-[274px]">
                    ${moduleDragHandle(name)}
                    <span class="w-9 h-9 rounded-xl ${p.badgeClass} font-bold text-sm flex items-center justify-center shrink-0">${escapeHtml(p.projBadge)}</span>
                    <div class="min-w-0">
                        <div class="text-sm font-semibold text-white truncate group-hover:text-sky-300 transition-colors" title="${name}">${name}</div>
                        <div class="flex items-center gap-1.5 text-xs text-slate-400 mt-0.5 whitespace-nowrap">
                            ${showProject && p.projectName ? `<span class="truncate max-w-[80px] text-slate-300" title="Project: ${escapeHtml(p.projectName)}">${escapeHtml(p.projectName)}</span><span class="text-slate-600">·</span>` : ''}
                            <span class="font-mono text-slate-300">${escapeHtml(p.code)}</span>
                            <span class="text-slate-600">·</span>
                            <span class="w-1.5 h-1.5 rounded-full ${PRODUCT_DOTS[p.product] || 'bg-slate-400'} shrink-0"></span>
                            <span class="truncate" title="${escapeHtml(p.product)}">${escapeHtml((p.product || '').replace(/^Axis\s+/, ''))}</span>
                        </div>
                    </div>
                </div>
            </td>
            <td class="py-3 px-3" onclick="event.stopPropagation()">
                <div class="flex items-center gap-1.5">
                    <select onchange="updateProjectStatus(${p.id}, this.value); renderProjectsTable();" aria-label="Status of ${name}"
                        class="axis-status-select ${statusCls} border text-xs font-medium pl-2.5 pr-6 py-1 rounded-full cursor-pointer focus:outline-none">
                        ${workStatusOptions(p.status)}
                    </select>
                    ${statusMismatchHtml(statusMismatchText(p.status, pct, (p.tasks || []).length, 'task'))}
                </div>
            </td>
            <td class="py-3 px-3">
                <div class="flex items-center gap-2 w-[120px]" title="${escapeHtml(moduleProgressExplain(p))}">
                    <div class="flex-1 h-1.5 rounded-full bg-white/[0.08] overflow-hidden">
                        <div class="h-full rounded-full ${barColor}" style="width: ${pct}%"></div>
                    </div>
                    <span class="text-xs font-mono font-semibold text-slate-200 w-9 text-right">${pct}%</span>
                </div>
            </td>
            <td class="py-3 px-3">
                <div class="flex items-center gap-2 whitespace-nowrap">
                    <span class="w-7 h-7 rounded-full ${avatarToneFor(p.manager)} text-xs font-semibold inline-flex items-center justify-center shrink-0">${escapeHtml(initialsOf(p.manager))}</span>
                    <span class="text-sm text-slate-100 truncate max-w-[130px]" title="${escapeHtml(p.manager)}">${escapeHtml(p.manager)}</span>
                </div>
            </td>
            <td class="py-3 px-3">
                <div class="flex items-center" title="${escapeHtml(team.join(', '))}">${teamHtml || '<span class="text-slate-500 text-xs whitespace-nowrap">No one yet</span>'}</div>
            </td>
            <td class="py-3 px-3 whitespace-nowrap">
                <span class="text-sm font-mono text-slate-100">${st.tasksDone}</span><span class="text-sm font-mono text-slate-500"> / ${st.tasksTotal}</span>
            </td>
            <td class="py-3 px-3 whitespace-nowrap">
                ${st.openIssues
                    ? `<span class="inline-flex items-center gap-1.5 text-xs font-semibold text-amber-300"><span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>${st.openIssues} open</span>`
                    : '<span class="text-xs text-slate-500">None</span>'}
            </td>
            <td class="py-3 px-3">
                <div class="w-[230px]">
                    <div class="text-sm text-slate-100 whitespace-nowrap">${fmt(p.startDate)} <span class="text-slate-500">→</span> ${fmt(p.deadline)}</div>
                    ${elapsed === null ? '' : `<div class="mt-1.5 h-1 w-full rounded-full bg-white/[0.08] overflow-hidden" title="${Math.round(elapsed)}% of the time used"><div class="h-full rounded-full bg-sky-400/70" style="width:${elapsed}%"></div></div>`}
                    <div class="text-xs mt-1 truncate ${due.cls}" title="${escapeHtml(due.title || due.text)}">${escapeHtml(due.text)}</div>
                </div>
            </td>
            <td class="py-3 pl-3 pr-5 axis-sticky-right" onclick="event.stopPropagation()">
                <div class="flex items-center justify-end gap-1.5">
                    <button type="button" onclick="openProjectTaskList(${p.id})" class="axis-row-btn axis-row-btn--label" aria-label="Open the tasks of ${name}">
                        <span>Tasks</span><i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i>
                    </button>
                    <div class="axis-row-actions" role="group" aria-label="Actions for ${name}">
                        <button type="button" onclick="openAddTaskFor(${p.id})" class="axis-row-btn" title="Add a task" aria-label="Add a task to ${name}">
                            <i class="fa-solid fa-plus text-xs" aria-hidden="true"></i>
                        </button>
                        <button type="button" onclick="openProjectDetails(${p.id})" class="axis-row-btn" title="Module details" aria-label="Details of ${name}">
                            <i class="fa-solid fa-circle-info text-xs" aria-hidden="true"></i>
                        </button>
                        <button type="button" onclick="openProjectEdit(${p.id})" class="axis-row-btn" title="Edit module" aria-label="Edit ${name}">
                            <i class="fa-solid fa-pen text-xs" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
            </td>
        </tr>
    `;
}

window.updateProjectStatus = function(projId, newStatus) {
    const p = projects.find(x => x.id === projId);
    if (p) {
        const prevStatus = p.status;
        p.status = newStatus;
        showToast(`${p.code} is now ${newStatus}`);

        AxisAPI.idOf(p)
            .then(id => AxisAPI.request(AxisAPI.url('modules.update', id), 'PATCH', { status: newStatus }))
            .catch(err => AxisAPI.fail(err, () => { p.status = prevStatus; renderProjectsTable(); }));
    }
};

window.handleAddProject = function(e) {
    e.preventDefault();
    const projectSelect = document.getElementById('add-proj-project');
    const name = document.getElementById('add-proj-name').value.trim();
    const deadline = document.getElementById('add-proj-deadline').value;
    const projectId = projectSelect.value;
    const description = document.getElementById('add-proj-desc').value.trim();
    const startDate = document.getElementById('add-proj-start').value;

    // Customer and Lead are no longer collected in the form: inherit the customer
    // from the selected project and use the signed-in member as the lead.
    const customer = projectSelect.options[projectSelect.selectedIndex]?.text.trim() || '';
    const teamMembers = window.AxisBootstrap?.teamMembers || [];
    const manager = teamMembers.find(member => member.id === window.AxisBootstrap?.currentUserId)?.name
        || teamMembers[0]?.name
        || '';

    AxisDrawer.submit('modal-add-project', async () => {
        const res = await AxisAPI.request(AxisAPI.url('modules.store'), 'POST', {
            project_id: projectId ? Number(projectId) : null,
            name: name,
            customer_name: customer,
            manager_name: manager,
            description: description || null,
            start_date: startDate || null,
            deadline: deadline
        });
        const created = res.data;
        projects.push(created);
        const parent = axisProjects.find(ap => ap.id === created.projectId);
        if (parent && typeof parent.moduleCount === 'number') parent.moduleCount++;

        // A just-created module must be visible on the Modules page. If the current
        // filters would hide it, clear them so the new row is not silently lost.
        if (!moduleMatchesModuleFilters(created)) {
            ['flt-project', 'flt-product', 'flt-status'].forEach(id => {
                const select = document.getElementById(id);
                if (select) select.value = 'all';
            });
            const search = document.getElementById('flt-search');
            if (search) search.value = '';
        }

        populateModuleDropdowns();
        renderProjectsTable(true);
        renderAxisProjectsPage();
        syncQuickModulePercentages();
        updateDynamicHeader(activePageId);
        e.target.reset();
        closeModal('modal-add-project');
        showToast(`Created ${res.data.name}`);
    });
};

/** Create a top-level project (e.g. "EOT Axis") that modules belong to. */
window.handleCreateAxisProject = function(e) {
    e.preventDefault();
    AxisDrawer.submit('drawer-project-create', async () => {
        const res = await AxisAPI.request(AxisAPI.url('projects.store'), 'POST', {
            name: document.getElementById('new-axis-project-name').value.trim(),
            start_date: document.getElementById('new-axis-project-start').value || null,
            deadline: document.getElementById('new-axis-project-deadline').value || null,
            status: document.getElementById('new-axis-project-status').value,
            description: document.getElementById('new-axis-project-desc').value.trim() || null
        });
        axisProjects.push(res.data);
        axisProjects.sort((a, b) => a.name.localeCompare(b.name));
        syncAxisProjectOptions(res.data.id);
        renderAxisProjectsPage();
        syncQuickModulePercentages();
        updateDynamicHeader(activePageId);
        e.target.reset();
        closeModal('drawer-project-create');
        showToast(`Created project ${res.data.name}`);
    });
};

/** Change a top-level project's status (Not yet started / Processing / Finished). */
window.updateAxisProjectStatus = function(id, status) {
    const project = axisProjects.find(ap => ap.id === id);
    if (!project) return;
    const previous = project.status;
    project.status = status;
    renderAxisProjectsPage();
    AxisAPI.request(AxisAPI.url('projects.update', id), 'PATCH', { status })
        .then(res => { Object.assign(project, res.data); renderAxisProjectsPage(); showToast(`${project.name} is now ${status.toLowerCase()}`); })
        .catch(err => AxisAPI.fail(err, () => { project.status = previous; renderAxisProjectsPage(); }));
};

/** Keep the project pickers (New module drawer, module list filter) in step with the projects list. */
function syncAxisProjectOptions(selectId = null) {
    const options = axisProjects.map(ap => `<option value="${ap.id}">${escapeHtml(ap.name)}</option>`).join('');
    const select = document.getElementById('add-proj-project');
    if (select) {
        const current = selectId ?? select.value;
        select.innerHTML = options;
        if (current && axisProjects.some(ap => String(ap.id) === String(current))) select.value = String(current);
    }
    const filter = document.getElementById('flt-project');
    if (filter) {
        const current = filter.value;
        filter.innerHTML = '<option value="all">All projects</option>' + options;
        filter.value = axisProjects.some(ap => String(ap.id) === current) ? current : 'all';
    }
}

// =====================================================================
// PAGE 2: PROJECT TASK LIST DETAIL (`task-detail`)
// =====================================================================
window.setTaskDetailViewMode = function(mode) {
    taskDetailViewMode = mode === 'cards' ? 'cards' : 'table';

    const tblWrap = document.getElementById('td-table-view-wrap');
    const crdWrap = document.getElementById('td-cards-view-wrap');
    const btnTbl = document.getElementById('td-view-table-btn');
    const btnCrd = document.getElementById('td-view-cards-btn');

    if (btnTbl) btnTbl.classList.toggle('is-active', taskDetailViewMode === 'table');
    if (btnCrd) btnCrd.classList.toggle('is-active', taskDetailViewMode === 'cards');

    if (taskDetailViewMode === 'cards') {
        if (tblWrap) tblWrap.classList.add('hidden');
        if (crdWrap) crdWrap.classList.remove('hidden');

    } else {
        if (tblWrap) tblWrap.classList.remove('hidden');
        if (crdWrap) crdWrap.classList.add('hidden');

    }
    renderTasksTableRows();
};

window.resetTaskDetailFilters = function() {
    taskIssueFilter = 'all';
    const hSel = document.getElementById('td-flt-handler');
    const sSel = document.getElementById('td-flt-status');
    const pSel = document.getElementById('td-flt-priority');
    const sortSel = document.getElementById('td-flt-sort');
    const srch = document.getElementById('task-inline-search');

    if (hSel) hSel.value = 'all';
    if (sSel) sSel.value = 'all';
    if (pSel) pSel.value = 'all';
    if (sortSel) sortSel.value = 'default';
    if (srch) srch.value = '';

    setTaskIssueFilter('all');
    showToast('Filters cleared');
};

window.clearSingleTaskFilter = function(key) {
    if (key === 'issue') taskIssueFilter = 'all';
    if (key === 'handler') document.getElementById('td-flt-handler').value = 'all';
    if (key === 'status') document.getElementById('td-flt-status').value = 'all';
    if (key === 'priority') document.getElementById('td-flt-priority').value = 'all';
    if (key === 'sort') document.getElementById('td-flt-sort').value = 'default';
    if (key === 'search') document.getElementById('task-inline-search').value = '';
    setTaskIssueFilter(taskIssueFilter);
};

/**
 * POST an optimistically-added task and swap in the server copy (real id, issues).
 */
function persistNewTask(p, draft, initialIssue = null) {
    const tempId = draft.id;
    draft._pending = AxisAPI.idOf(p)
        .then(projectId => AxisAPI.request(AxisAPI.url('tasks.store', projectId), 'POST', {
            name: draft.name,
            description: draft.description,
            priority: draft.priority,
            start_date: draft.startDate || null,
            deadline: draft.endDate || null,
            emp_id: draft.employeeId,
            assigned_by_id: draft.assignedById ?? null,
            status: draft.status,
            progress: draft.progress,
            est_hours: draft.estHours,
            initial_issue: initialIssue
        }))
        .then(res => {
            mergeServerTask(draft, res.data, true);
            delete draft._pending;
            if (activeDrawerTaskId === tempId) activeDrawerTaskId = draft.id;
            if (activeProjectId === p.id && activePageId === 'task-detail') {
                renderTasksTableRows();
                refreshDetailSummaryCards();
            }
        })
        .catch(err => {
            draft._failed = true;
            delete draft._pending;
            if (activeDrawerTaskId === tempId) activeDrawerTaskId = null;
            p.tasks = p.tasks.filter(x => x !== draft);
            renderTasksTableRows();
            refreshDetailSummaryCards();
            AxisAPI.fail(err);
        });
    return draft._pending;
}

window.exportActiveTasksCSV = function() {
    const p = projects.find(x => x.id === activeProjectId) || projects[0];
    if (!p) return;
    const rows = [
        ['Task ID', 'Task Name', 'Handler', 'Priority', 'Status', 'Progress %', 'Start Date', 'End Date', 'Est Hours', 'Actual Time', 'Dev Notes Count']
    ];
    (p.tasks || []).forEach(t => {
        rows.push([
            'TASK-' + t.id,
            `"${t.name.replace(/"/g, '""')}"`,
            `"${t.handler}"`,
            t.priority,
            t.status,
            t.progress + '%',
            t.startDate,
            t.endDate,
            t.estHours,
            formatHMS(t.actSeconds),
            (t.issues || []).length
        ]);
    });
    const csv = rows.map(r => r.join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${p.code}_${p.name.replace(/\s+/g, '_')}_Tasks.csv`;
    a.click();
    URL.revokeObjectURL(url);
    showToast(`Downloaded ${p.code} tasks`);
};

/**
 * Module switcher on the task page: the title opens a list of modules grouped by project.
 * Choosing one navigates (the whole page changes), so it lives in the header, not the filter bar.
 */
const MODULE_SWITCHER_SEARCH_FROM = 8;

window.renderModuleSwitcher = function(query = '') {
    const list = document.getElementById('td-switch-list');
    if (!list) return;
    const q = query.trim().toLowerCase();
    const matches = projects.filter(m => !q || `${m.name} ${m.code} ${m.projectName || ''}`.toLowerCase().includes(q));
    if (matches.length === 0) {
        list.innerHTML = '<div class="axis-module-menu__empty">No module matches that.</div>';
        return;
    }
    const groups = new Map();
    matches.forEach(m => {
        const key = m.projectName || 'Other';
        if (!groups.has(key)) groups.set(key, []);
        groups.get(key).push(m);
    });
    const dot = { Finished: 'bg-emerald-400', 'Not yet started': 'bg-amber-400' };
    list.innerHTML = [...groups].map(([projectName, mods]) => `
        <div role="group" aria-label="${escapeHtml(projectName)}">
            <div class="axis-module-menu__group">${escapeHtml(projectName)}</div>
            ${mods.map(m => `
                <button type="button" role="option" aria-selected="${m.id === activeProjectId}" data-module-option="${m.id}"
                    onclick="chooseModuleFromSwitcher(${m.id})" class="axis-module-menu__item${m.id === activeProjectId ? ' is-current' : ''}">
                    <span class="w-1.5 h-1.5 rounded-full shrink-0 ${dot[m.status] || 'bg-sky-400'}" title="${escapeHtml(m.status)}" aria-hidden="true"></span>
                    <span class="truncate">${escapeHtml(m.name)}</span>
                    <span class="ml-auto font-mono text-[11px] text-slate-500 shrink-0">${escapeHtml(m.code)}</span>
                    ${m.id === activeProjectId ? '<i class="fa-solid fa-check text-[11px] text-sky-300 shrink-0" aria-hidden="true"></i>' : ''}
                </button>`).join('')}
        </div>`).join('');
};

window.toggleModuleSwitcher = function(force) {
    const menu = document.getElementById('td-switch-menu');
    const btn = document.getElementById('td-switch-btn');
    if (!menu || !btn) return;
    const open = force ?? menu.classList.contains('hidden');
    menu.classList.toggle('hidden', !open);
    btn.setAttribute('aria-expanded', String(open));
    if (!open) return;
    const search = document.getElementById('td-switch-search');
    const searchable = projects.length >= MODULE_SWITCHER_SEARCH_FROM;
    search.classList.toggle('hidden', !searchable);
    search.value = '';
    renderModuleSwitcher();
    (searchable ? search : (menu.querySelector('.is-current') || menu.querySelector('[data-module-option]')))?.focus();
};

window.chooseModuleFromSwitcher = function(moduleId) {
    toggleModuleSwitcher(false);
    document.getElementById('td-switch-btn')?.focus();
    if (moduleId !== activeProjectId) openProjectTaskList(moduleId);
};

/** Arrow keys move through the list, Escape closes and returns focus to the title. */
window.handleModuleSwitcherKey = function(e) {
    const options = [...document.querySelectorAll('#td-switch-list [data-module-option]')];
    const idx = options.indexOf(document.activeElement);
    if (e.key === 'Escape') {
        e.preventDefault();
        toggleModuleSwitcher(false);
        document.getElementById('td-switch-btn')?.focus();
    } else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        if (options.length === 0) return;
        const next = e.key === 'ArrowDown' ? Math.min(options.length - 1, idx + 1) : Math.max(0, idx - 1);
        options[idx === -1 ? 0 : next].focus();
    } else if (e.key === 'Tab') {
        toggleModuleSwitcher(false);
    }
};

document.addEventListener('click', e => {
    const menu = document.getElementById('td-switch-menu');
    if (!menu || menu.classList.contains('hidden')) return;
    if (!e.target.closest('#td-switch-menu, #td-switch-btn')) toggleModuleSwitcher(false);
});

/**
 * Toggle an account menu and keep its ARIA state and chevron in sync.
 */
function setAxisAccountMenu(buttonId, menuId, force) {
    const button = document.getElementById(buttonId);
    const menu = document.getElementById(menuId);
    if (!button || !menu) return;

    const open = typeof force === 'boolean' ? force : menu.classList.contains('hidden');
    menu.classList.toggle('hidden', !open);
    button.setAttribute('aria-expanded', String(open));

    const chevron = button.querySelector('.fa-chevron-down, .fa-chevron-up');
    if (chevron) {
        chevron.classList.toggle('fa-chevron-down', !open);
        chevron.classList.toggle('fa-chevron-up', open);
    }
}

const axisAccountMenus = [
    { button: 'header-profile-btn', menu: 'header-profile-menu' },
    { button: 'sidebar-profile-btn', menu: 'sidebar-profile-menu' },
];

window.toggleProfileMenu = function(force) {
    setAxisAccountMenu('header-profile-btn', 'header-profile-menu', force);
};

window.toggleSidebarProfileMenu = function(force) {
    setAxisAccountMenu('sidebar-profile-btn', 'sidebar-profile-menu', force);
};

document.addEventListener('click', e => {
    if (e.target.closest('[data-profile-menu-item]')) {
        axisAccountMenus.forEach(({ button, menu }) => setAxisAccountMenu(button, menu, false));
        return;
    }

    axisAccountMenus.forEach(({ button, menu }) => {
        const menuEl = document.getElementById(menu);
        if (!menuEl || menuEl.classList.contains('hidden')) return;
        if (!e.target.closest(`#${menu}, #${button}`)) {
            setAxisAccountMenu(button, menu, false);
        }
    });
});

document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;

    axisAccountMenus.forEach(({ button, menu }) => {
        const menuEl = document.getElementById(menu);
        if (!menuEl || menuEl.classList.contains('hidden')) return;

        setAxisAccountMenu(button, menu, false);
        document.getElementById(button)?.focus();
    });
});

/**
 * Global typography inspector. Hovering real text previews its computed font
 * details; clicking pins the popover. The header icon restores the fixed AXIS
 * specification. Markup lives in x-axis.typography-inspector.
 */
const AXIS_TYPOGRAPHY_DEFAULT = Object.freeze({
    fontName: 'San Francisco',
    fontSource: 'System font',
    fontFamily: 'Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif',
    fontSize: '12px',
    lineHeight: '19px',
    letterSpacing: '-0.11px',
    fontWeight: '650',
    tag: 'strong',
    color: '#132033',
});
const AXIS_TYPOGRAPHY_LABELS = Object.freeze({
    fontSize: 'Font size',
    lineHeight: 'Line height',
    letterSpacing: 'Letter spacing',
    fontWeight: 'Font weight',
    tag: 'Tag',
    color: 'Color',
});
const typographyFeedbackTimers = new WeakMap();
const typographyFeedbackStyles = new WeakMap();
let typographyCurrentStyles = { ...AXIS_TYPOGRAPHY_DEFAULT };
let typographyMode = 'fixed';
let typographyPinned = false;
let typographyActiveElement = null;
let typographyHoverTimer = null;
let typographyHideTimer = null;

function typographyRoot() {
    return document.querySelector('[data-axis-typography-inspector]');
}

function typographyPopover() {
    return document.getElementById('axis-typography-popover');
}

function typographyResolvedFontName(fontFamily) {
    const genericFamilies = new Set(['sans-serif', 'serif', 'monospace', 'system-ui', 'ui-sans-serif', 'ui-serif', 'ui-monospace', 'cursive', 'fantasy']);
    const families = (fontFamily || '')
        .split(',')
        .map(family => family.trim().replace(/^["']|["']$/g, ''))
        .filter(Boolean);

    return families.find(family => !genericFamilies.has(family.toLowerCase())) || families[0] || 'System font';
}

function typographyColorParts(color) {
    const rgb = (color || '').match(/rgba?\(([^)]+)\)/);
    if (rgb) {
        const parts = rgb[1].split(',').map(value => parseFloat(value));
        return [parts[0] || 0, parts[1] || 0, parts[2] || 0];
    }

    const hex = (color || '').match(/^#([0-9a-f]{3}|[0-9a-f]{6})$/i);
    if (!hex) return [19, 32, 51];

    const value = hex[1].length === 3 ? hex[1].split('').map(part => part + part).join('') : hex[1];
    return [0, 2, 4].map(index => parseInt(value.slice(index, index + 2), 16));
}

function typographyColorHex(color) {
    return '#' + typographyColorParts(color)
        .map(value => Math.max(0, Math.min(255, Math.round(value))).toString(16).padStart(2, '0'))
        .join('')
        .toUpperCase();
}

function typographyNeedsDarkSample(color) {
    const [red, green, blue] = typographyColorParts(color);
    return (red * 299 + green * 587 + blue * 114) / 1000 > 150;
}

function typographyCssFor(styles) {
    return [
        `font-family: ${styles.fontFamily};`,
        `font-size: ${styles.fontSize};`,
        `line-height: ${styles.lineHeight};`,
        `letter-spacing: ${styles.letterSpacing};`,
        `font-weight: ${styles.fontWeight};`,
        `color: ${styles.color};`,
    ].join('\n');
}

function setTypographyContent(styles) {
    const root = typographyRoot();
    if (!root) return;

    typographyCurrentStyles = styles;
    root.querySelector('[data-typography-font-name]').textContent = styles.fontName;
    root.querySelector('[data-typography-font-source]').textContent = styles.fontSource;

    Object.keys(AXIS_TYPOGRAPHY_LABELS).forEach(key => {
        const button = root.querySelector(`[data-typography-key="${key}"]`);
        const value = root.querySelector(`[data-typography-value="${key}"]`);
        if (!button || !value) return;

        value.textContent = styles[key];
        button.dataset.typographyCopy = styles[key];
        button.setAttribute('aria-label', `Copy ${AXIS_TYPOGRAPHY_LABELS[key]}: ${styles[key]}`);
    });

    const swatch = root.querySelector('[data-typography-swatch]');
    if (swatch) swatch.style.backgroundColor = styles.color;

    const stackButton = root.querySelector('[data-typography-stack]');
    if (stackButton) {
        stackButton.textContent = styles.fontFamily;
        stackButton.dataset.typographyCopy = styles.fontFamily;
    }

    const sample = root.querySelector('[data-typography-sample]');
    const sampleWrap = root.querySelector('[data-typography-sample-wrap]');
    if (sample) {
        sample.style.fontFamily = styles.fontFamily;
        sample.style.fontSize = styles.fontSize;
        sample.style.lineHeight = styles.lineHeight;
        sample.style.letterSpacing = styles.letterSpacing;
        sample.style.fontWeight = styles.fontWeight;
        sample.style.color = styles.color;
    }
    if (sampleWrap) sampleWrap.style.backgroundColor = typographyNeedsDarkSample(styles.color) ? '#1e1e1e' : '#f5f7fa';
}

function typographyStylesFor(element) {
    const computed = window.getComputedStyle(element);
    const fontFamily = computed.fontFamily || window.getComputedStyle(document.body).fontFamily || AXIS_TYPOGRAPHY_DEFAULT.fontFamily;
    return {
        fontName: typographyResolvedFontName(fontFamily),
        fontSource: 'Computed style',
        fontFamily: fontFamily,
        fontSize: computed.fontSize,
        lineHeight: computed.lineHeight,
        letterSpacing: computed.letterSpacing,
        fontWeight: computed.fontWeight,
        tag: element.tagName.toLowerCase(),
        color: typographyColorHex(computed.color),
    };
}

function typographyTextElement(node) {
    const root = typographyRoot();
    if (!root || !node) return null;

    let element = node.nodeType === Node.ELEMENT_NODE ? node : node.parentElement;
    while (element && element !== document.body) {
        if (root.contains(element)) return null;
        if (element.matches('script, style, textarea, input, select, option, [hidden], .sr-only')) return null;

        const hasText = Array.from(element.childNodes).some(child => child.nodeType === Node.TEXT_NODE && child.textContent.trim());
        const computed = window.getComputedStyle(element);
        if (hasText && computed.display !== 'none' && computed.visibility !== 'hidden' && element.getClientRects().length) {
            return element;
        }

        element = element.parentElement;
    }

    return null;
}

function positionTypographyPopover(anchor) {
    const popover = typographyPopover();
    const button = document.getElementById('header-typography-btn');
    if (!popover || !button) return;

    let target = anchor;
    if (!target || !target.isConnected || !target.getClientRects().length) target = button;

    const rect = target.getBoundingClientRect();
    const width = popover.offsetWidth || 352;
    const height = popover.offsetHeight || 360;
    const margin = 12;

    let left = rect.right + 10;
    if (left + width > window.innerWidth - margin) left = rect.left - width - 10;
    left = Math.max(margin, Math.min(left, window.innerWidth - width - margin));

    let top = rect.top - 8;
    const maxTop = Math.max(margin, window.innerHeight - height - margin);
    top = Math.max(margin, Math.min(top, maxTop));

    popover.style.left = `${left}px`;
    popover.style.top = `${top}px`;
}

function showTypographyPopover(anchor, focusClose = false) {
    const popover = typographyPopover();
    const button = document.getElementById('header-typography-btn');
    if (!popover || !button) return;

    popover.classList.remove('hidden');
    button.setAttribute('aria-expanded', 'true');
    positionTypographyPopover(anchor);

    if (focusClose) popover.querySelector('[data-typography-close]')?.focus();
}

function hideTypographyPopover() {
    const popover = typographyPopover();
    const button = document.getElementById('header-typography-btn');
    if (popover) popover.classList.add('hidden');
    if (button) button.setAttribute('aria-expanded', 'false');

    window.clearTimeout(typographyHoverTimer);
    window.clearTimeout(typographyHideTimer);
    typographyHoverTimer = null;
    typographyHideTimer = null;
    typographyPinned = false;
    typographyActiveElement = null;
}

function inspectTypographyText(element, pin) {
    setTypographyContent(typographyStylesFor(element));
    typographyMode = 'computed';
    typographyActiveElement = element;
    typographyPinned = pin;
    showTypographyPopover(element);
}

function resetTypographyToFixed() {
    setTypographyContent({ ...AXIS_TYPOGRAPHY_DEFAULT });
    typographyMode = 'fixed';
}

window.toggleTypographyInspector = function() {
    const popover = typographyPopover();
    const button = document.getElementById('header-typography-btn');
    if (!popover || !button) return;

    if (!popover.classList.contains('hidden') && typographyMode === 'fixed') {
        hideTypographyPopover();
        return;
    }

    window.clearTimeout(typographyHoverTimer);
    window.clearTimeout(typographyHideTimer);
    axisAccountMenus.forEach(({ button: accountButton, menu }) => setAxisAccountMenu(accountButton, menu, false));
    resetTypographyToFixed();
    typographyPinned = true;
    showTypographyPopover(button, true);
};

async function writeTypographyClipboard(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        try {
            await navigator.clipboard.writeText(text);
            return true;
        } catch (error) {
            // Fall through to the legacy copy path below.
        }
    }

    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.focus();
    textarea.select();

    let copied = false;
    try {
        copied = document.execCommand('copy');
    } catch (error) {
        copied = false;
    }
    document.body.removeChild(textarea);
    return copied;
}

async function copyTypographyValue(text, label, button) {
    const copied = await writeTypographyClipboard(text);
    const popover = typographyPopover();
    const status = popover?.querySelector('[data-typography-status]');
    if (!status) return;

    status.textContent = copied ? `${label} copied` : `Could not copy ${label.toLowerCase()}`;
    if (!button) return;

    if (!typographyFeedbackStyles.has(button)) {
        typographyFeedbackStyles.set(button, {
            borderColor: button.style.borderColor,
            backgroundColor: button.style.backgroundColor,
        });
    }
    const original = typographyFeedbackStyles.get(button);
    button.style.borderColor = copied ? 'rgba(52, 211, 153, 0.6)' : 'rgba(251, 113, 133, 0.6)';
    button.style.backgroundColor = copied ? 'rgba(16, 185, 129, 0.12)' : 'rgba(244, 63, 94, 0.12)';

    const existingTimer = typographyFeedbackTimers.get(button);
    if (existingTimer) window.clearTimeout(existingTimer);
    const timer = window.setTimeout(() => {
        button.style.borderColor = original.borderColor;
        button.style.backgroundColor = original.backgroundColor;
        status.textContent = '';
        typographyFeedbackTimers.delete(button);
        typographyFeedbackStyles.delete(button);
    }, 1200);
    typographyFeedbackTimers.set(button, timer);
}

document.addEventListener('mouseover', e => {
    const root = typographyRoot();
    if (!root || typographyPinned) return;

    const element = typographyTextElement(e.target);
    if (!element) return;

    window.clearTimeout(typographyHideTimer);
    window.clearTimeout(typographyHoverTimer);
    typographyHoverTimer = window.setTimeout(() => {
        if (!typographyPinned && element.isConnected) inspectTypographyText(element, false);
    }, 120);
});

document.addEventListener('mouseout', e => {
    const root = typographyRoot();
    if (!root || typographyPinned) return;
    if (e.relatedTarget && root.contains(e.relatedTarget)) return;

    const from = typographyTextElement(e.target);
    const to = typographyTextElement(e.relatedTarget);
    if (!from || from === to) return;

    window.clearTimeout(typographyHoverTimer);
    typographyHideTimer = window.setTimeout(() => {
        if (!typographyPinned) hideTypographyPopover();
    }, 300);
});

document.addEventListener('click', e => {
    const root = typographyRoot();
    if (!root) return;

    if (e.target.closest('[data-typography-close]')) {
        hideTypographyPopover();
        document.getElementById('header-typography-btn')?.focus();
        return;
    }

    const cssButton = e.target.closest('[data-typography-copy-css]');
    if (cssButton && root.contains(cssButton)) {
        copyTypographyValue(typographyCssFor(typographyCurrentStyles), cssButton.dataset.typographyLabel || 'CSS', cssButton);
        return;
    }

    const copyButton = e.target.closest('[data-typography-copy]');
    if (copyButton && root.contains(copyButton)) {
        copyTypographyValue(copyButton.dataset.typographyCopy, copyButton.dataset.typographyLabel || 'Value', copyButton);
        return;
    }

    if (e.target.closest('#header-typography-btn')) return;

    const element = typographyTextElement(e.target);
    if (element) {
        window.clearTimeout(typographyHoverTimer);
        window.clearTimeout(typographyHideTimer);
        inspectTypographyText(element, true);
        return;
    }

    if (!e.target.closest('[data-axis-typography-inspector]')) hideTypographyPopover();
});

document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    const popover = typographyPopover();
    if (!popover || popover.classList.contains('hidden')) return;

    hideTypographyPopover();
    document.getElementById('header-typography-btn')?.focus();
});

window.addEventListener('scroll', () => {
    const popover = typographyPopover();
    if (!popover || popover.classList.contains('hidden')) return;
    positionTypographyPopover(typographyActiveElement);
}, true);

window.addEventListener('resize', () => {
    const popover = typographyPopover();
    if (!popover || popover.classList.contains('hidden')) return;
    positionTypographyPopover(typographyActiveElement);
});

window.renderTaskDetailPage = function() {
    const p = projects.find(x => x.id === activeProjectId) || projects[0];
    if (!p) return;
    activeProjectId = p.id;

    const titleEl = document.getElementById('td-title');
    if (titleEl) titleEl.textContent = p.name;
    const statusBadge = document.getElementById('td-status-badge');
    if (statusBadge) {
        statusBadge.textContent = p.status;
        statusBadge.className = `px-2.5 py-0.5 rounded-full border text-xs font-semibold ${PROJECT_STATUS_STYLES[p.status] || PROJECT_STATUS_STYLES['Processing']}`;
    }
    const codeEl = document.getElementById('td-code-badge');
    if (codeEl) codeEl.textContent = p.code;
    const compEl = document.getElementById('td-company');
    if (compEl) compEl.textContent = [p.customer, (p.customerSub || '').replace(/^\((.*)\)$/, '$1')].filter(Boolean).join(', ') || '—';
    const domPill = document.getElementById('td-domain-pill');
    if (domPill) domPill.textContent = p.product || '—';
    const mgrPill = document.getElementById('td-mgr-pill');
    if (mgrPill) mgrPill.textContent = p.manager || '—';
    const dlPill = document.getElementById('td-dl-pill');
    if (dlPill) dlPill.textContent = p.deadline ? shortDate(p.deadline) : 'Not set';
    const dlLeft = document.getElementById('td-dl-left');
    if (dlLeft) {
        const due = deadlineInfo(p);
        dlLeft.textContent = p.deadline ? `· ${due.text}` : '';
        dlLeft.className = `text-xs ${due.cls}`;
    }

    // Populate Handler Filter Dropdown & Quick-Add Handler Select
    const hSelect = document.getElementById('td-flt-handler');
    if (hSelect) {
        const prevH = hSelect.value || 'all';
        const modHandlers = Array.from(new Set([...(p.team || []), ...(p.tasks || []).map(t => t.handler), ...HANDLERS]));
        hSelect.innerHTML = '<option value="all">Everyone</option>' +
            modHandlers.map(h => `<option value="${escapeHtml(h)}">${escapeHtml(h)}</option>`).join('');
        if (modHandlers.includes(prevH)) hSelect.value = prevH;
    }

    renderTasksTableRows();
    refreshDetailSummaryCards();
};

// =====================================================================
// PROJECT DRAWERS — View / Edit / Assign (built on the universal drawer)
// =====================================================================
let viewingProjectId = null;

window.openProjectDetails = function(projectId) {
    const p = projects.find(x => x.id === projectId);
    if (!p) return;
    viewingProjectId = p.id;
    const d = document.getElementById('drawer-project-view');
    const set = (key, value) => { const el = d.querySelector(`[data-project-view="${key}"]`); if (el) el.textContent = value; };
    const fmt = iso => iso ? new Date(iso + 'T00:00:00').toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) : '—';
    const pct = Math.round(computeProjectCompletion(p));
    const done = (p.tasks || []).filter(t => t.status === 'Finished').length;

    set('customer', [p.customer, (p.customerSub || '').replace(/^\((.*)\)$/, '$1')].filter(Boolean).join(' · '));
    set('product', p.product || '—');
    set('status', p.status);
    set('manager', p.manager || '—');
    set('startDate', fmt(p.startDate));
    set('description', p.description || '—');
    set('deadline', fmt(p.deadline));
    set('maintEnd', p.maintEnd && p.maintEnd !== '-' ? p.maintEnd : '—');
    set('timeLeft', deadlineInfo(p).text);
    set('tasksDone', String(done));
    set('tasksTotal', String((p.tasks || []).length));
    set('progress', pct + '%');
    d.querySelector('[data-project-view="progressBar"]').style.width = pct + '%';
    d.querySelector('[data-project-view="team"]').innerHTML = (p.team || []).map(m => `
        <span class="inline-flex items-center gap-2 pl-1 pr-3 py-1 rounded-full bg-white/[0.04] border border-white/10 text-sm text-slate-200">
            <span class="w-6 h-6 rounded-full ${avatarToneFor(m)} text-xs font-semibold inline-flex items-center justify-center">${escapeHtml(initialsOf(m))}</span>${escapeHtml(m)}
        </span>`).join('') || '<span class="text-sm text-slate-500">No one yet</span>';

    AxisDrawer.open('drawer-project-view', { title: p.name, breadcrumb: p.code, description: `Led by ${p.manager}. ${pct}% done.` });
};

window.openProjectEdit = function(projectId) {
    const p = projects.find(x => x.id === projectId);
    if (!p) return;
    viewingProjectId = p.id;
    const managerSel = document.getElementById('edit-proj-manager');
    if (p.manager && ![...managerSel.options].some(o => o.value === p.manager)) {
        managerSel.add(new Option(p.manager, p.manager));
    }
    managerSel.value = p.manager || '';
    document.getElementById('edit-proj-project').value = String(p.projectId);
    document.getElementById('edit-proj-name').value = p.name || '';
    document.getElementById('edit-proj-customer').value = p.customer || '';
    document.getElementById('edit-proj-status').value = p.status;
    document.getElementById('edit-proj-start').value = p.startDate || '';
    document.getElementById('edit-proj-desc').value = p.description || '';
    document.getElementById('edit-proj-deadline').value = p.deadline || '';
    syncDateRangeField(document.getElementById('edit-proj-schedule'));
    // Team: everyone who can be assigned, plus anyone already on the team who is no longer in that list
    const team = p.team || [];
    const people = [...new Set([...HANDLERS, ...team])];
    document.getElementById('edit-proj-team').innerHTML = people.map(name => `
        <label class="axis-team-chip">
            <input type="checkbox" value="${escapeHtml(name)}" ${team.includes(name) ? 'checked' : ''}>
            <span>${escapeHtml(name)}</span>
        </label>`).join('');

    AxisDrawer.close('drawer-project-view');
    AxisDrawer.open('drawer-project-edit', { title: `Edit ${p.name}`, breadcrumb: p.code });
};

window.handleUpdateProject = function(e) {
    e.preventDefault();
    const p = projects.find(x => x.id === viewingProjectId);
    if (!p) return;

    const name = document.getElementById('edit-proj-name').value.trim();
    const customer = document.getElementById('edit-proj-customer').value.trim();
    const deadline = document.getElementById('edit-proj-deadline').value;
    const errors = {};
    if (!name) errors.name = ['Give the module a name.'];
    if (!customer) errors.customer_name = ['Who is this module for?'];
    if (!deadline) errors.deadline = ['Pick a deadline.'];
    if (Object.keys(errors).length) {
        AxisDrawer.showErrors('drawer-project-edit', errors);
        return;
    }

    AxisDrawer.submit('drawer-project-edit', async () => {
        const id = await AxisAPI.idOf(p);
        const res = await AxisAPI.request(AxisAPI.url('modules.update', id), 'PATCH', {
            project_id: parseInt(document.getElementById('edit-proj-project').value, 10),
            name,
            customer_name: customer,
            manager_name: document.getElementById('edit-proj-manager').value,
            status: document.getElementById('edit-proj-status').value,
            start_date: document.getElementById('edit-proj-start').value || null,
            description: document.getElementById('edit-proj-desc').value.trim() || null,
            deadline: document.getElementById('edit-proj-deadline').value || null,
            team_members: [...document.querySelectorAll('#edit-proj-team input:checked')].map(cb => cb.value)
        });
        const updated = res.data;
        Object.assign(p, {
            projectId: updated.projectId,
            projectName: updated.projectName,
            name: updated.name,
            projBadge: updated.projBadge,
            customer: updated.customer,
            customerSub: updated.customerSub,
            custBadge: updated.custBadge,
            manager: updated.manager,
            team: updated.team,
            status: updated.status,
            startDate: updated.startDate,
            description: updated.description,
            deadline: updated.deadline,
            overdue: updated.overdue
        });
        closeModal('drawer-project-edit');
        populateModuleDropdowns();
        renderProjectsTable();
        renderAxisProjectsTable();
        if (activePageId === 'task-detail' && activeProjectId === p.id) renderTaskDetailPage();
        showToast(`Saved ${p.name}`);
    });
};

function refreshDetailSummaryCards() {
    const p = projects.find(x => x.id === activeProjectId);
    if (!p) return;
    syncQuickModulePercentages();
    if (!document.getElementById('task-kpi-strip')) return;

    const tasks = p.tasks || [];
    const count = tasks.length;
    const byStatus = status => tasks.filter(t => t.status === status).length;
    const done = byStatus('Finished');
    const pct = computeProjectCompletion(p);
    const estTotal = tasks.reduce((sum, t) => sum + (parseFloat(t.estHours) || 0), 0);
    const actSecTotal = tasks.reduce((sum, t) => sum + (parseInt(t.actSeconds, 10) || 0), 0);
    const anyRunning = tasks.some(t => t.running);
    const issues = tasks.flatMap(t => t.issues || []);
    const openIssues = issues.filter(i => i.status === 'Open').length;
    const investigating = issues.filter(i => i.status === 'Investigating').length;
    const fixed = issues.length - openIssues - investigating;
    const strip = document.getElementById('task-kpi-strip');
    const cache = strip._kpiCache || (strip._kpiCache = {});
    /** Rebuild a card's visual/footer only when its inputs change (the timer refreshes every second). */
    const paint = (key, signature, build) => {
        if (cache[key] === signature) return;
        cache[key] = signature;
        build();
    };
    const plural = (n, word) => `${n} ${word}${n === 1 ? '' : 's'}`;

    // 1. Overall progress — mean task progress, one column per task
    setKpiValue(document.getElementById('kpi-td-progress'), +pct.toFixed(1), { decimals: 1, unit: '%' });
    setKpiStatus('td-progress', p.status);
    setKpiText('kpi-td-progress-desc', `Average across ${plural(count, 'task')}`);
    const progressSig = tasks.map(t => `${t.id}:${Math.round(t.progress)}`).join('|');
    paint('progress', progressSig, () => {
        const sorted = [...tasks].sort((a, b) => b.progress - a.progress);
        document.getElementById('kpi-td-progress-visual').innerHTML = count
            ? `<div class="axis-kpi-columns" role="img" aria-label="Progress per task, highest to lowest">
                ${sorted.map((t, i) => `<span class="axis-kpi-columns__bar" style="height:${Math.max(4, t.progress)}%;transition-delay:${i * 40}ms" title="${escapeHtml(t.name)}: ${Math.round(t.progress)}%"></span>`).join('')}
               </div>`
            : '';
        document.getElementById('kpi-td-progress-footer').innerHTML = count
            ? `<span>Lowest <span class="text-slate-200 font-mono">${Math.round(sorted[count - 1].progress)}%</span></span><span>Highest <span class="text-slate-200 font-mono">${Math.round(sorted[0].progress)}%</span></span>`
            : '<span>No tasks yet</span>';
    });

    // 2. Tasks done — completed count with the status breakdown
    setKpiValue(document.getElementById('kpi-td-done'), done, { unit: ` / ${count}` });
    setKpiStatus('td-done', `${count ? Math.round((done / count) * 100) : 0}% done`);
    const segments = [
        { label: 'Finished', count: done, opacity: 1 },
        { label: 'Processing', count: byStatus('Processing'), color: '#38BDF8' },
        { label: 'Not yet started', count: byStatus('Not yet started'), color: '#FBBF24' },
    ];
    const remaining = segments.slice(1).filter(seg => seg.count > 0).map(seg => `${seg.count} ${seg.label.toLowerCase()}`);
    setKpiText('kpi-td-done-desc', remaining.length ? remaining.join(' · ') : (count ? 'Everything is done' : 'No tasks yet'));
    paint('done', segments.map(seg => seg.count).join('|'), () => {
        document.getElementById('kpi-td-done-visual').innerHTML = count ? kpiBarHtml(segments, `Tasks: ${segments.map(seg => `${seg.count} ${seg.label.toLowerCase()}`).join(', ')}`) : '';
        document.getElementById('kpi-td-done-footer').innerHTML = `<span>${plural(count, 'task')} in this project</span>`;
    });

    // 3. Time logged — live clock against the estimate
    const timeEl = document.getElementById('kpi-td-time');
    timeEl.textContent = formatHMS(actSecTotal);
    timeEl.setAttribute('aria-label', `${formatHMS(actSecTotal)} logged`);
    const statusDot = document.querySelector('#kpi-td-time-status .axis-kpi__badge-dot');
    if (statusDot) statusDot.classList.toggle('axis-kpi__badge-dot--live', anyRunning);
    setKpiStatus('td-time', anyRunning ? 'Live' : 'Paused');
    const loggedHours = actSecTotal / 3600;
    const usedPct = estTotal > 0 ? (loggedHours / estTotal) * 100 : 0;
    setKpiText('kpi-td-time-desc', `Estimate ${+estTotal.toFixed(1)}h · ${+loggedHours.toFixed(1)}h logged`);
    paint('time', `${Math.round(usedPct)}|${estTotal}`, () => {
        const over = usedPct > 100;
        document.getElementById('kpi-td-time-visual').innerHTML = estTotal > 0
            ? `<div class="axis-kpi-bar" role="img" aria-label="${Math.round(usedPct)}% of the estimate used">
                <span class="axis-kpi-bar__seg" style="flex:${Math.min(100, usedPct)};${over ? 'background:#FBBF24;' : ''}"></span>
                ${usedPct < 100 ? `<span class="axis-kpi-bar__seg" style="flex:${100 - usedPct};opacity:0.18"></span>` : ''}
               </div>`
            : '';
        document.getElementById('kpi-td-time-footer').innerHTML = estTotal > 0
            ? (over
                ? `<span class="text-amber-300">${Math.round(usedPct - 100)}% over the estimate</span>`
                : `<span><span class="text-slate-200 font-mono">${Math.round(usedPct)}%</span> of the estimate used</span>`)
            : '<span>No estimate set</span>';
    });

    // 4. Issues & fixes — notes by status, with a shortcut to the drawer
    setKpiValue(document.getElementById('kpi-td-issues'), issues.length, { unit: issues.length === 1 ? ' note' : ' notes' });
    setKpiStatus('td-issues', openIssues + investigating > 0 ? `${openIssues + investigating} open` : 'All fixed', openIssues + investigating > 0 ? 'warn' : null);
    setKpiText('kpi-td-issues-desc', issues.length ? `${openIssues} open · ${investigating} investigating · ${fixed} fixed` : 'No notes logged yet');
    paint('issues', `${openIssues}|${investigating}|${fixed}`, () => {
        const issueSegments = [
            { label: 'Open', count: openIssues, color: '#FBBF24' },
            { label: 'Investigating', count: investigating, color: '#38BDF8' },
            { label: 'Fixed', count: fixed, color: '#34D399' },
        ];
        document.getElementById('kpi-td-issues-visual').innerHTML = issues.length ? kpiBarHtml(issueSegments, `Notes: ${openIssues} open, ${investigating} investigating, ${fixed} fixed`) : '';
        const withNotes = tasks.filter(t => (t.issues || []).length > 0).length;
        document.getElementById('kpi-td-issues-footer').innerHTML = `<span>On ${plural(withNotes, 'task')}</span>
            <button type="button" onclick="openDevIssueDrawer()" class="text-amber-300 hover:text-amber-200 font-medium shrink-0 inline-flex items-center gap-1">View notes <i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i></button>`;
    });

    revealKpiCards('task-kpi-strip');
}

window.setTaskIssueFilter = function(filterMode) {
    taskIssueFilter = filterMode || 'all';

    const issueSelect = document.getElementById('td-flt-issue');
    if (issueSelect) issueSelect.value = taskIssueFilter;

    renderTasksTableRows(true);
};

/** The note just saved gets a short highlight so it is easy to spot in the list. */
let lastAddedIssue = null;
let lastAddedIssueTimer = null;

window.openDevIssueDrawer = function(taskId, projectId = null) {
    if (projectId !== null && projectId !== undefined) {
        const target = projects.find(x => String(x.id) === String(projectId));
        if (target) activeProjectId = target.id;
    }

    const p = projects.find(x => x.id === activeProjectId) || projects[0];
    if (!p || !p.tasks || p.tasks.length === 0) return;

    if (taskId) {
        activeDrawerTaskId = taskId;
    } else if (!p.tasks.some(t => t.id === activeDrawerTaskId)) {
        activeDrawerTaskId = p.tasks[0].id;
    }

    drawerFilterMode = 'all';
    renderDevIssueDrawerContent();
    const openTask = p.tasks.find(t => t.id === activeDrawerTaskId);
    setIssueComposerOpen(!(openTask && openTask.issues && openTask.issues.length), { focus: false });
    AxisDrawer.open('dev-issue-drawer');
    renderTasksTableRows();
};

window.closeDevIssueDrawer = function() {
    // The drawer's on-close hook re-renders the task rows
    AxisDrawer.close('dev-issue-drawer');
};

window.toggleTaskIssueDrawer = function(taskId) {
    openDevIssueDrawer(taskId);
};

window.stepDevIssueTask = function(direction) {
    const p = projects.find(x => x.id === activeProjectId) || projects[0];
    if (!p || !p.tasks || p.tasks.length === 0) return;
    const idx = p.tasks.findIndex(t => t.id === activeDrawerTaskId);
    const nextIdx = (idx + direction + p.tasks.length) % p.tasks.length;
    activeDrawerTaskId = p.tasks[nextIdx].id;
    renderDevIssueDrawerContent();
    renderTasksTableRows();
};

/**
 * Show or hide the "Add a note" form. The footer follows it: Close only while it is hidden,
 * Cancel + Save note while it is open, so Save never looks like it saves the notes above.
 */
window.setIssueComposerOpen = function(open, { focus = true } = {}) {
    const composer = document.getElementById('did-composer');
    const opener = document.getElementById('did-compose-open');
    if (!composer || !opener) return;
    composer.classList.toggle('hidden', !open);
    opener.classList.toggle('hidden', open);
    document.getElementById('did-footer-close')?.classList.toggle('hidden', open);
    document.getElementById('did-footer-cancel')?.classList.toggle('hidden', !open);
    document.getElementById('did-footer-save')?.classList.toggle('hidden', !open);

    const firstField = document.getElementById('did-input-enc');
    // AxisDrawer focuses [data-drawer-autofocus] when the drawer opens
    firstField?.toggleAttribute('data-drawer-autofocus', open);
    opener.toggleAttribute('data-drawer-autofocus', !open);
    if (!open) AxisDrawer.clearErrors?.('dev-issue-drawer');
    if (focus) (open ? firstField : opener)?.focus({ preventScroll: !open });
};

window.setDrawerIssueFilter = function(mode) {
    drawerFilterMode = mode || 'all';
    renderDevIssueDrawerContent();
};

window.renderDevIssueDrawerContent = function() {
    const p = projects.find(x => x.id === activeProjectId) || projects[0];
    if (!p) return;
    const tIdx = p.tasks.findIndex(x => x.id === activeDrawerTaskId);
    const t = tIdx >= 0 ? p.tasks[tIdx] : p.tasks[0];
    if (!t) return;
    activeDrawerTaskId = t.id;
    if (!Array.isArray(t.issues)) t.issues = [];

    const statusBadge = document.getElementById('did-task-status');
    const titleEl = document.getElementById('did-task-title');
    const authorEl = document.getElementById('did-author-pill');

    if (statusBadge) statusBadge.textContent = `${t.status} (${Math.round(t.progress)}%)`;
    if (titleEl) titleEl.textContent = `Task ${tIdx + 1}: ${t.name} · ${t.handler}`;
    AxisDrawer.configure('dev-issue-drawer', {
        title: `Task ${tIdx + 1}: ${t.name}`,
        breadcrumb: `${p.code} · ${p.name}`,
        description: `Assigned to ${t.handler}. Bugs hit while building it, and how they were fixed.`,
    });
    if (authorEl) authorEl.textContent = `Posting as ${currentUser.name}`;

    const total = t.issues.length;
    const openCnt = t.issues.filter(i => i.status !== 'Resolved').length;
    const resCnt = t.issues.filter(i => i.status === 'Resolved').length;

    document.getElementById('did-kpi-total').textContent = String(total);
    document.getElementById('did-kpi-open').textContent = String(openCnt);
    document.getElementById('did-kpi-resolved').textContent = String(resCnt);

    const tabAll = document.getElementById('did-tab-all');
    const tabOpen = document.getElementById('did-tab-open');
    const tabRes = document.getElementById('did-tab-resolved');
    [[tabAll, 'all'], [tabOpen, 'open'], [tabRes, 'resolved']].forEach(([tab, mode]) => {
        if (!tab) return;
        tab.className = `axis-note-tab${drawerFilterMode === mode ? ' is-active' : ''}`;
        tab.setAttribute('aria-pressed', drawerFilterMode === mode ? 'true' : 'false');
    });
    // Previous / next task only make sense when the module has more than one task
    document.querySelectorAll('#dev-issue-drawer [onclick^="stepDevIssueTask"]').forEach(btn => { btn.disabled = p.tasks.length < 2; });

    const filtered = t.issues.filter(iss => {
        if (drawerFilterMode === 'open') return iss.status !== 'Resolved';
        if (drawerFilterMode === 'resolved') return iss.status === 'Resolved';
        return true;
    });

    const listEl = document.getElementById('did-notes-list');
    if (!listEl) return;

    if (filtered.length === 0) {
        const emptyText = total === 0
            ? `Hit a bug or found a fix? Add the first note for <strong class="text-white">${escapeHtml(t.name)}</strong>.`
            : drawerFilterMode === 'open' ? 'Nothing open — every note on this task is fixed.' : 'No fixed notes yet.';
        listEl.innerHTML = `
            <div class="axis-note-empty">
                <span class="axis-note-empty__icon" aria-hidden="true"><i class="fa-solid fa-clipboard-check"></i></span>
                <div class="text-sm font-semibold text-white mt-3">${total === 0 ? 'No notes yet' : 'Nothing to show'}</div>
                <p class="text-xs text-slate-400 mt-1 max-w-xs mx-auto">${emptyText}</p>
            </div>`;
        return;
    }

    const SEVERITY_CLS = { Critical: 'is-critical', High: 'is-high', Medium: 'is-medium', Low: 'is-low' };
    const STATUS_META = {
        Open: { cls: 'is-open', label: 'Open', icon: 'fa-circle-exclamation' },
        Investigating: { cls: 'is-investigating', label: 'Investigating', icon: 'fa-magnifying-glass' },
        Resolved: { cls: 'is-resolved', label: 'Fixed', icon: 'fa-circle-check' },
    };
    listEl.innerHTML = filtered.map(iss => {
        const status = STATUS_META[iss.status] || STATUS_META.Open;
        const sev = iss.severity || 'High';
        const nextLabel = { Open: 'Investigating', Investigating: 'Fixed', Resolved: 'Open' }[iss.status] || 'Investigating';
        return `
            <article class="axis-note ${status.cls}${iss === lastAddedIssue ? ' is-new' : ''}">
                <header class="flex items-start justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-1.5 min-w-0">
                        <span class="axis-note__chip">${escapeHtml(iss.type)}</span>
                        <span class="axis-note__sev ${SEVERITY_CLS[sev] || 'is-high'}">${escapeHtml(sev)}</span>
                        <span class="text-xs text-slate-400 whitespace-nowrap">${escapeHtml(iss.author)} · ${escapeHtml(iss.timestamp || 'Just now')}</span>
                    </div>
                    <div class="flex items-center gap-1.5 shrink-0">
                        <button type="button" onclick="cycleIssueStatus(${t.id}, ${iss.id})" class="axis-note__status ${status.cls}"
                            title="Change to ${nextLabel}" aria-label="Status: ${status.label}. Change to ${nextLabel}.">
                            <i class="fa-solid ${status.icon} text-[10px]" aria-hidden="true"></i>${status.label}
                        </button>
                        <button type="button" onclick="deleteTaskIssueNote(${t.id}, ${iss.id})" class="axis-row-btn axis-row-btn--danger" title="Delete note" aria-label="Delete this note">
                            <i class="fa-regular fa-trash-can text-xs" aria-hidden="true"></i>
                        </button>
                    </div>
                </header>
                <div class="mt-3">
                    <div class="axis-note__label">What went wrong</div>
                    <p class="text-sm text-slate-100 leading-relaxed mt-1 whitespace-pre-line">${escapeHtml(iss.encountered)}</p>
                </div>
                <div class="mt-3">
                    <label class="axis-note__label" for="issue-fix-${iss.id}">How it was fixed</label>
                    <input type="text" id="issue-fix-${iss.id}" value="${escapeHtml(iss.resolution || '')}"
                        placeholder="${iss.status === 'Resolved' ? 'No fix described yet. Add one' : 'Not fixed yet. Describe the fix here'}"
                        oninput="updateIssueResolutionText(${t.id}, ${iss.id}, this.value)"
                        class="axis-input w-full h-9 px-3 mt-1 text-sm">
                </div>
            </article>`;
    }).join('');
};

/** New note: the status follows the fix field (Resolved when a fix is written) until you pick one yourself. */
window.syncNewIssueStatusFromFix = function(value) {
    const select = document.getElementById('did-input-status');
    if (!select || select.dataset.touched === 'true') return;
    select.value = value.trim() ? 'Resolved' : 'Open';
};

window.submitDrawerIssueNote = function(e) {
    e.preventDefault();
    // The whole drawer is one form: ignore Enter pressed inside an existing note's inline editor
    if (!e.submitter && !document.activeElement?.closest('[data-note-composer]')) return;
    const p = projects.find(x => x.id === activeProjectId) || projects[0];
    if (!p) return;
    const t = p.tasks.find(x => x.id === activeDrawerTaskId) || p.tasks[0];
    if (!t) return;
    if (!Array.isArray(t.issues)) t.issues = [];

    const typeVal = document.getElementById('did-input-type').value;
    const sevVal = document.getElementById('did-input-severity').value;
    const statusVal = document.getElementById('did-input-status').value;
    const encEl = document.getElementById('did-input-enc');
    const fixEl = document.getElementById('did-input-fix');

    const encountered = encEl.value.trim();
    const resolutionInput = fixEl.value.trim();
    const resolution = resolutionInput;
    if (!encountered) {
        AxisDrawer.showErrors('dev-issue-drawer', { encountered: ['Tell us what went wrong.'] });
        return;
    }

    const now = new Date();
    const draft = {
        id: -Date.now(),
        type: typeVal,
        severity: sevVal,
        status: statusVal,
        encountered: encountered,
        resolution: resolution,
        author: currentUser.name,
        timestamp: now.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' }) + ' ' +
            now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false })
    };
    t.issues.unshift(draft);
    lastAddedIssue = draft;
    clearTimeout(lastAddedIssueTimer);
    lastAddedIssueTimer = setTimeout(() => { lastAddedIssue = null; }, 2000);
    drawerFilterMode = 'all';

    draft._pending = AxisAPI.idOf(t)
        .then(taskId => AxisAPI.request(AxisAPI.url('issues.store', taskId), 'POST', {
            category: typeVal,
            severity: sevVal,
            status: statusVal,
            encountered: encountered,
            resolution: resolutionInput || null
        }))
        .then(res => {
            Object.assign(draft, res.data);
            delete draft._pending;
            renderDevIssueDrawerContent();
            renderTasksTableRows();
        })
        .catch(err => {
            draft._failed = true;
            delete draft._pending;
            t.issues = t.issues.filter(i => i !== draft);
            renderDevIssueDrawerContent();
            renderTasksTableRows();
            AxisAPI.fail(err);
        });

    encEl.value = '';
    fixEl.value = '';
    const statusSelect = document.getElementById('did-input-status');
    statusSelect.value = 'Open';
    delete statusSelect.dataset.touched;
    setIssueComposerOpen(false);
    const notesList = document.getElementById('did-notes-list');
    if (notesList) notesList.closest('[data-drawer-body]').scrollTo({ top: 0, behavior: 'smooth' });

    renderDevIssueDrawerContent();
    renderTasksTableRows();
    showToast(`Note added to "${t.name}"`);
};

window.cycleIssueStatus = function(taskId, issueId) {
    const p = projects.find(x => x.id === activeProjectId);
    if (!p) return;
    const t = p.tasks.find(x => x.id === taskId);
    if (!t || !t.issues) return;
    const iss = t.issues.find(i => i.id === issueId);
    if (!iss) return;

    const order = ['Open', 'Investigating', 'Resolved'];
    const prevStatus = iss.status;
    const nextIdx = (order.indexOf(iss.status) + 1) % order.length;
    iss.status = order[nextIdx];

    if (iss.status === 'Resolved') {
        showToast('Marked as fixed');
    } else if (iss.status === 'Open') {
        showToast('Reopened');
    } else {
        showToast('Looking into it');
    }

    renderDevIssueDrawerContent();
    renderTasksTableRows();

    AxisAPI.idOf(iss)
        .then(id => AxisAPI.request(AxisAPI.url('issues.cycle', id), 'POST'))
        .then(res => {
            if (res.data.status !== iss.status) {
                iss.status = res.data.status;
                renderDevIssueDrawerContent();
                renderTasksTableRows();
            }
        })
        .catch(err => AxisAPI.fail(err, () => {
            iss.status = prevStatus;
            renderDevIssueDrawerContent();
            renderTasksTableRows();
        }));
};

window.updateIssueResolutionText = function(taskId, issueId, newText) {
    const p = projects.find(x => x.id === activeProjectId);
    if (!p) return;
    const t = p.tasks.find(x => x.id === taskId);
    if (!t || !t.issues) return;
    const iss = t.issues.find(i => i.id === issueId);
    if (iss) {
        iss.resolution = newText;
        AxisAPI.debounce(`issue:${issueId}:resolution`, () => {
            AxisAPI.idOf(iss)
                .then(id => AxisAPI.request(AxisAPI.url('issues.update', id), 'PATCH', { resolution: iss.resolution }))
                .catch(err => AxisAPI.fail(err));
        }, 600);
    }
};

window.deleteTaskIssueNote = async function(taskId, issueId) {
    const p = projects.find(x => x.id === activeProjectId);
    if (!p) return;
    const t = p.tasks.find(x => x.id === taskId);
    if (!t || !t.issues) return;
    const note = t.issues.find(i => i.id === issueId);
    if (!note) return;
    const confirmed = await AxisDrawer.confirm({
        title: 'Delete this note?',
        breadcrumb: t.name,
        message: note.encountered,
        confirmLabel: 'Delete note'
    });
    if (!confirmed) return;
    const idx = t.issues.findIndex(i => i.id === issueId);
    if (idx === -1) return;
    const removed = t.issues[idx];
    t.issues = t.issues.filter(i => i.id !== issueId);
    renderDevIssueDrawerContent();
    renderTasksTableRows();
    showToast('Note deleted');

    AxisAPI.idOf(removed)
        .then(id => AxisAPI.request(AxisAPI.url('issues.destroy', id), 'DELETE'))
        .catch(err => AxisAPI.fail(err, () => {
            t.issues.splice(idx, 0, removed);
            renderDevIssueDrawerContent();
            renderTasksTableRows();
        }));
};

function syncTaskFilterChipsUI(filteredCount, totalCount) {
    const hVal = document.getElementById('td-flt-handler')?.value || 'all';
    const sVal = document.getElementById('td-flt-status')?.value || 'all';
    const pVal = document.getElementById('td-flt-priority')?.value || 'all';
    const sortVal = document.getElementById('td-flt-sort')?.value || 'default';
    const qVal = (document.getElementById('task-inline-search')?.value || '').trim();

    const clrBtn = document.getElementById('td-clear-search-btn');
    if (clrBtn) clrBtn.classList.toggle('hidden', !qVal);

    const chips = [];
    if (taskIssueFilter !== 'all') {
        chips.push({ key: 'issue', label: `Show: ${{ running: 'Timer running', open: 'Open issues', resolved: 'Fixed' }[taskIssueFilter] || taskIssueFilter}` });
    }
    if (hVal !== 'all') {
        chips.push({ key: 'handler', label: `Assignee: ${hVal}` });
    }
    if (sVal !== 'all') {
        chips.push({ key: 'status', label: `Status: ${sVal}` });
    }
    if (pVal !== 'all') {
        chips.push({ key: 'priority', label: `Priority: ${pVal}` });
    }
    if (sortVal !== 'default') {
        const sortLabel = document.getElementById('td-flt-sort')?.selectedOptions[0]?.textContent || sortVal;
        chips.push({ key: 'sort', label: `Sorted: ${sortLabel}` });
    }
    if (qVal) {
        chips.push({ key: 'search', label: `Search: "${qVal}"` });
    }

    const bar = document.getElementById('td-active-chips-bar');
    const list = document.getElementById('td-active-chips-list');
    if (bar && list) {
        if (chips.length > 0) {
            bar.classList.remove('hidden');
            bar.classList.add('flex');
            list.innerHTML = chips.map(c => `
                <button type="button" onclick="clearSingleTaskFilter(${jsArg(c.key)})" class="axis-filter-chip" title="Remove this filter">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i><span>${escapeHtml(c.label)}</span>
                </button>
            `).join('');
        } else {
            bar.classList.add('hidden');
            bar.classList.remove('flex');
            list.innerHTML = '';
        }
    }

    const showPill = document.getElementById('td-showing-count-pill');
    if (showPill) {
        showPill.textContent = `Showing ${filteredCount} of ${totalCount} tasks`;
    }
}

function renderTasksTableRows(flashCard = false) {
    const p = projects.find(x => x.id === activeProjectId);
    const tbody = document.getElementById('tasks-tbody');
    const cardsWrap = document.getElementById('td-cards-view-wrap');
    if (!tbody || !p) return;

    p.tasks.forEach(t => {
        if (!Array.isArray(t.issues)) t.issues = [];
    });

    // Update Quick Filter Pill Counters
    const allCount = p.tasks.length;
    const runCount = p.tasks.filter(t => t.running).length;
    const openCount = p.tasks.filter(t => t.issues.some(i => i.status !== 'Resolved')).length;
    const resCount = p.tasks.filter(t => t.issues.length > 0 && t.issues.every(i => i.status === 'Resolved')).length;

    const elAll = document.getElementById('cnt-tasks-all');
    const elRun = document.getElementById('cnt-tasks-running');
    const elOpen = document.getElementById('cnt-tasks-open');
    const elRes = document.getElementById('cnt-tasks-resolved');
    if (elAll) elAll.textContent = `All tasks (${allCount})`;
    if (elRun) elRun.textContent = `Timer running (${runCount})`;
    if (elOpen) elOpen.textContent = `Open issues (${openCount})`;
    if (elRes) elRes.textContent = `Fixed (${resCount})`;

    const hVal = document.getElementById('td-flt-handler')?.value || 'all';
    const sVal = document.getElementById('td-flt-status')?.value || 'all';
    const pVal = document.getElementById('td-flt-priority')?.value || 'all';
    const sortVal = document.getElementById('td-flt-sort')?.value || 'default';
    const inlineQuery = (document.getElementById('task-inline-search')?.value || '').toLowerCase().trim();

    let filteredTasks = p.tasks.filter(t => {
        const matchIssue =
            taskIssueFilter === 'running' ? Boolean(t.running) :
            taskIssueFilter === 'open' ? t.issues.some(i => i.status !== 'Resolved') :
            taskIssueFilter === 'resolved' ? (t.issues.length > 0 && t.issues.every(i => i.status === 'Resolved')) :
            true;
        const matchH = hVal === 'all' || t.handler === hVal;
        const matchS = sVal === 'all' || t.status === sVal;
        const matchP = pVal === 'all' || t.priority === pVal;
        const matchSearch = !inlineQuery ||
            t.name.toLowerCase().includes(inlineQuery) ||
            t.description.toLowerCase().includes(inlineQuery) ||
            t.handler.toLowerCase().includes(inlineQuery);
        return matchIssue && matchH && matchS && matchP && matchSearch;
    });

    // Apply Sort Order
    const prioRank = { High: 3, Medium: 2, Low: 1 };
    if (sortVal === 'progress-desc') filteredTasks.sort((a, b) => (parseFloat(b.progress) || 0) - (parseFloat(a.progress) || 0));
    if (sortVal === 'progress-asc') filteredTasks.sort((a, b) => (parseFloat(a.progress) || 0) - (parseFloat(b.progress) || 0));
    if (sortVal === 'hours-desc') filteredTasks.sort((a, b) => (parseInt(b.actSeconds, 10) || 0) - (parseInt(a.actSeconds, 10) || 0));
    if (sortVal === 'priority') filteredTasks.sort((a, b) => (prioRank[b.priority] || 0) - (prioRank[a.priority] || 0));
    if (sortVal.startsWith('deadline-')) {
        // Tasks without a deadline always go last, whichever direction
        const dir = sortVal === 'deadline-asc' ? 1 : -1;
        filteredTasks.sort((a, b) => (!a.endDate) - (!b.endDate) || (a.endDate && b.endDate ? dir * a.endDate.localeCompare(b.endDate) : 0));
    }
    syncSortHeaders('data-task-sort', sortVal.startsWith('deadline-') ? { key: 'deadline', dir: sortVal.slice(9) } : { key: null });

    syncTaskFilterChipsUI(filteredTasks.length, p.tasks.length);

    if (flashCard) {
        const resCard = document.getElementById('td-results-card');
        if (resCard) {
            resCard.classList.remove('filter-result-flash');
            void resCard.offsetWidth;
            resCard.classList.add('filter-result-flash');
        }
    }

    if (filteredTasks.length === 0) {
        // A module with no tasks at all needs "add one", not "clear your filters"
        const emptyHtml = p.tasks.length === 0 ? `
            <div class="py-12 text-center space-y-2">
                <div class="text-sm font-semibold text-white">No tasks yet</div>
                <p class="text-xs text-slate-400">Add the first task for ${escapeHtml(p.name)}.</p>
                <button type="button" onclick="openModal('modal-create-task')" class="btn-primary-glass px-3.5 py-1.5 text-xs font-semibold mt-1">New task</button>
            </div>
        ` : `
            <div class="py-12 text-center space-y-2">
                <div class="text-sm font-semibold text-white">No tasks match these filters</div>
                <p class="text-xs text-slate-400">Try clearing a filter.</p>
                <button type="button" onclick="resetTaskDetailFilters()" class="btn-glass px-3.5 py-1.5 text-xs font-semibold mt-1">Reset filters</button>
            </div>
        `;
        tbody.innerHTML = `<tr><td colspan="9">${emptyHtml}</td></tr>`;
        if (cardsWrap) cardsWrap.innerHTML = `<div class="col-span-2">${emptyHtml}</div>`;
        return;
    }

    // Render Bento Card Grid View if active
    if (cardsWrap) {
        cardsWrap.innerHTML = filteredTasks.map((t, idx) => {
            const hms = formatHMS(t.actSeconds);
            const prog = Math.min(100, Math.max(0, parseFloat(t.progress) || 0));
            const issues = t.issues || [];
            const openIss = issues.filter(i => i.status !== 'Resolved').length;
            return `
                <div class="p-4 rounded-2xl bg-white/[0.03] border ${t.running ? 'border-emerald-400/50 shadow-lg shadow-emerald-500/10' : 'border-sky-400/20'} space-y-3 flex flex-col justify-between">
                    <div class="space-y-2">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <span class="font-mono text-xs font-bold text-cyan-300 bg-cyan-500/15 border border-cyan-400/30 px-2 py-0.5 rounded">TASK-${t.id}</span>
                                <h3 class="text-sm font-bold text-white mt-1">${escapeHtml(t.name)}</h3>
                            </div>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold ${t.status === 'Finished' ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-400/35' : 'bg-sky-500/20 text-sky-300 border-sky-400/35'}">${escapeHtml(t.status)}</span>
                        </div>
                        <p class="text-xs text-slate-300 whitespace-pre-line bg-white/[0.04] p-2.5 rounded-xl border border-sky-400/15">${escapeHtml(t.description)}</p>
                    </div>
                    <div class="space-y-2.5 pt-2 border-t border-sky-400/15">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-indigo-200 font-semibold"><i class="fa-solid fa-user-astronaut text-indigo-400 mr-1"></i>${escapeHtml(t.handler)}</span>
                            <span class="font-mono font-bold text-emerald-300">${hms} / ${t.estHours}h</span>
                        </div>
                        <div class="w-full h-2 bg-white/[0.05] rounded-full overflow-hidden p-0.5 border border-sky-400/20">
                            <div class="h-full liquid-progress-bar rounded-full" style="width:${prog}%"></div>
                        </div>
                        <div class="flex items-center justify-between pt-1">
                            <button type="button" onclick="openDevIssueDrawer(${t.id})" class="px-2.5 py-1 rounded-lg text-xs font-semibold ${openIss > 0 ? 'bg-amber-500/20 text-amber-200 border border-amber-400/40' : 'bg-sky-500/15 text-sky-200 border-sky-400/30'}">
                                <i class="fa-solid fa-bug mr-1"></i>${issues.length} notes (${openIss} open)
                            </button>
                            <div class="flex items-center gap-2">
                                <button type="button" onclick="toggleTaskStopwatch(${t.id})" class="btn-glass px-2.5 py-1 text-xs font-semibold ${t.running ? 'text-amber-300' : 'text-sky-300'}" aria-label="${t.running ? 'Pause timer' : 'Start timer'}">
                                    <i class="fa-solid ${t.running ? 'fa-pause' : 'fa-play'} mr-1" aria-hidden="true"></i>${t.running ? 'Pause' : 'Start'}
                                </button>
                                ${taskActionsHtml(t)}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    // Render Compact 8-Column High-Density Table View (Zero Awkward Horizontal Squeezing)
    tbody.innerHTML = filteredTasks.map(t => renderTaskRow(t)).join('');
}


const TASK_PRIORITY_STYLES = {
    High: { cls: 'text-rose-300', dot: 'bg-rose-400' },
    Medium: { cls: 'text-amber-300', dot: 'bg-amber-400' },
    Low: { cls: 'text-sky-300', dot: 'bg-sky-400' },
};

const TASK_STATUS_STYLES = {
    'Finished': 'bg-emerald-500/15 text-emerald-200 border-emerald-400/30',
    'Processing': 'bg-sky-500/15 text-sky-200 border-sky-400/30',
    'Not yet started': 'bg-amber-500/15 text-amber-200 border-amber-400/30',
};

/** One-line summary of a task's "* main / - sub" detail lines. */
function taskSummary(t) {
    return (t.description || '').split('\n')
        .map(l => l.trim().replace(/^[\*\-]\s*/, ''))
        .filter(Boolean)
        .join(' · ');
}

/** Swap the summary line for a textarea so the task details can be edited in place. */
window.editTaskDescription = function(taskId) {
    const preview = document.getElementById('task-desc-preview-' + taskId);
    const editor = document.getElementById('task-desc-editor-' + taskId);
    if (!preview || !editor) return;
    preview.classList.add('hidden');
    editor.classList.remove('hidden');
    editor.focus();
    editor.setSelectionRange(editor.value.length, editor.value.length);
};

const GHOST_FIELD = 'bg-transparent border border-transparent hover:border-white/10 focus:border-sky-400/50 focus:bg-white/[0.06] rounded-md outline-none transition-colors';

/** Save a task's start and due dates together (one request), rolling back on failure. */
window.updateTaskSchedule = function(taskId, startDate, endDate) {
    const p = projects.find(x => x.id === activeProjectId);
    const t = p && p.tasks.find(x => x.id === taskId);
    if (!t) return;
    const snapshot = { startDate: t.startDate, endDate: t.endDate };
    if (snapshot.startDate === startDate && snapshot.endDate === endDate) return;
    t.startDate = startDate;
    t.endDate = endDate;
    renderTasksTableRows();
    persistTask(t, { start_date: startDate || null, deadline: endDate || null }, () => {
        Object.assign(t, snapshot);
        renderTasksTableRows();
    });
};

/** Form date-range field (<x-axis.date-range-field>): show its two hidden values on the button. */
function syncDateRangeField(btn) {
    const start = document.getElementById(btn.dataset.startInput)?.value || null;
    const end = document.getElementById(btn.dataset.endInput)?.value || null;
    const startEl = btn.querySelector('[data-range-start]');
    const endEl = btn.querySelector('[data-range-end]');
    const spanEl = btn.querySelector('[data-range-span]');
    if (startEl) startEl.textContent = start ? shortDate(start) : 'Not set';
    if (endEl) endEl.textContent = end ? shortDate(end) : 'Not set';
    if (spanEl) {
        const days = start && end ? Math.round((new Date(end + 'T00:00:00') - new Date(start + 'T00:00:00')) / 86400000) : null;
        spanEl.textContent = days === null ? '' : `${days} day${days === 1 ? '' : 's'}`;
    }
}

window.openDateRangeField = function(btn) {
    const startInput = document.getElementById(btn.dataset.startInput);
    const endInput = document.getElementById(btn.dataset.endInput);
    AxisDatePicker.open(btn, {
        start: startInput?.value || null,
        end: endInput?.value || null,
        onApply: ({ start, end }) => {
            if (startInput) startInput.value = start || '';
            if (endInput) endInput.value = end || '';
            syncDateRangeField(btn);
        },
    });
};

document.addEventListener('DOMContentLoaded', () => document.querySelectorAll('[data-date-range]').forEach(syncDateRangeField));

window.openTaskSchedulePicker = function(taskId, anchor) {
    const t = (projects.find(x => x.id === activeProjectId)?.tasks || []).find(x => x.id === taskId);
    if (!t) return;
    AxisDatePicker.open(anchor, {
        start: t.startDate || null,
        end: t.endDate || null,
        onApply: ({ start, end }) => updateTaskSchedule(taskId, start, end),
    });
};

/**
 * Date-range picker popover (start → due). Pick the start, then the due date; "Done" applies,
 * Cancel / Escape / clicking outside discards. Weeks start on Monday. No external library.
 */
const AxisDatePicker = (() => {
    const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    const WEEKDAYS = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];
    let el = null;
    let state = null;

    const pad = n => String(n).padStart(2, '0');
    const toIso = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
    const fromIso = iso => { const [y, m, d] = String(iso).split('-').map(Number); return new Date(y, m - 1, d); };
    const todayIso = () => toIso(new Date());
    const addDays = (iso, days) => { const d = fromIso(iso); d.setDate(d.getDate() + days); return toIso(d); };
    const addMonths = (iso, months) => { const d = fromIso(iso); d.setMonth(d.getMonth() + months); return toIso(d); };
    const endOfMonth = iso => { const d = fromIso(iso); return toIso(new Date(d.getFullYear(), d.getMonth() + 1, 0)); };

    function ensure() {
        if (el) return el;
        el = document.createElement('div');
        el.id = 'axis-date-picker';
        el.className = 'axis-date-picker hidden';
        el.dataset.ownKeys = 'true'; // drawers leave this popover's Escape / Tab alone
        el.setAttribute('role', 'dialog');
        el.setAttribute('aria-label', 'Choose the start date and deadline');
        el.addEventListener('click', onClick);
        el.addEventListener('keydown', e => { if (e.key === 'Escape') { e.stopPropagation(); close(); } });
        document.body.appendChild(el);
        document.addEventListener('pointerdown', e => {
            if (state && !el.contains(e.target) && !state.anchor.contains(e.target)) close();
        });
        window.addEventListener('resize', () => state && position());
        // Any scrolling container (page or a drawer body) moves the anchor: close instead of floating away
        window.addEventListener('scroll', e => { if (state && !el.contains(e.target)) close(false); }, { capture: true, passive: true });
        return el;
    }

    function open(anchor, { start, end, onApply }) {
        ensure();
        const focusIso = start || end || todayIso();
        const f = fromIso(focusIso);
        state = { anchor, start, end, onApply, field: start ? 'end' : 'start', view: new Date(f.getFullYear(), f.getMonth(), 1) };
        anchor.setAttribute('aria-expanded', 'true');
        el.classList.remove('hidden');
        render();
        position();
        // Focus the day being edited if it's in view, else the start, today, or the first day of the month
        const focusable = [state.field === 'end' ? end : start, start, todayIso()]
            .filter(Boolean)
            .map(iso => el.querySelector(`[data-date="${iso}"]:not(.is-outside)`))
            .find(Boolean) || el.querySelector('.axis-dp-day:not(.is-outside)');
        focusable?.focus();
    }

    function close(restoreFocus = true) {
        if (!state) return;
        const anchor = state.anchor;
        anchor.setAttribute('aria-expanded', 'false');
        el.classList.add('hidden');
        state = null;
        if (restoreFocus && document.body.contains(anchor)) anchor.focus({ preventScroll: true });
    }

    function position() {
        const r = state.anchor.getBoundingClientRect();
        const w = el.offsetWidth, h = el.offsetHeight, gap = 6;
        let left = Math.min(Math.max(8, r.left), window.innerWidth - w - 8);
        let top = r.bottom + gap;
        if (top + h > window.innerHeight - 8) top = Math.max(8, r.top - h - gap);
        el.style.left = `${left}px`;
        el.style.top = `${top}px`;
    }

    function render() {
        const { start, end, field, view } = state;
        const year = view.getFullYear(), month = view.getMonth();
        const first = new Date(year, month, 1);
        const offset = (first.getDay() + 6) % 7; // Monday first
        const gridStart = new Date(year, month, 1 - offset);
        const today = todayIso();
        const days = [];
        for (let i = 0; i < 42; i++) {
            const d = new Date(gridStart.getFullYear(), gridStart.getMonth(), gridStart.getDate() + i);
            const iso = toIso(d);
            const cls = ['axis-dp-day'];
            if (d.getMonth() !== month) cls.push('is-outside');
            if (iso === today) cls.push('is-today');
            if (start && end && iso > start && iso < end) cls.push('is-between');
            if (iso === start) cls.push('is-start');
            if (iso === end) cls.push('is-end');
            const label = d.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
            days.push(`<button type="button" class="${cls.join(' ')}" data-date="${iso}" aria-label="${label}"${iso === start || iso === end ? ' aria-pressed="true"' : ''}>${d.getDate()}</button>`);
        }
        const fieldBtn = (key, title, value) => `
            <button type="button" class="axis-dp-field${field === key ? ' is-active' : ''}" data-field="${key}" aria-pressed="${field === key}">
                <span class="axis-dp-field__label">${title}</span>
                <span class="axis-dp-field__value">${value ? shortDate(value) : 'Not set'}</span>
            </button>`;
        const base = start || today;
        el.innerHTML = `
            <div class="flex items-center gap-2">
                ${fieldBtn('start', 'Start', start)}
                <span class="text-slate-500 text-xs" aria-hidden="true">→</span>
                ${fieldBtn('end', 'Deadline', end)}
            </div>
            <div class="flex items-center justify-between mt-3 mb-2">
                <button type="button" class="axis-dp-nav" data-nav="-1" aria-label="Previous month"><i class="fa-solid fa-chevron-left text-[11px]" aria-hidden="true"></i></button>
                <span class="text-sm font-semibold text-white" aria-live="polite">${MONTHS[month]} ${year}</span>
                <button type="button" class="axis-dp-nav" data-nav="1" aria-label="Next month"><i class="fa-solid fa-chevron-right text-[11px]" aria-hidden="true"></i></button>
            </div>
            <div class="axis-dp-grid" role="grid">
                ${WEEKDAYS.map(w => `<span class="axis-dp-weekday">${w}</span>`).join('')}
                ${days.join('')}
            </div>
            <div class="mt-3">
                <div class="text-[11px] font-medium text-slate-500 mb-1.5">Deadline in</div>
                <div class="flex flex-wrap gap-1.5">
                    <button type="button" class="axis-dp-preset" data-preset="${addDays(base, 7)}">1 week</button>
                    <button type="button" class="axis-dp-preset" data-preset="${addDays(base, 14)}">2 weeks</button>
                    <button type="button" class="axis-dp-preset" data-preset="${addMonths(base, 1)}">1 month</button>
                    <button type="button" class="axis-dp-preset" data-preset="${endOfMonth(base)}">End of month</button>
                </div>
            </div>
            <div class="flex items-center justify-between gap-2 mt-3 pt-3 border-t border-white/[0.08]">
                <button type="button" class="text-xs font-semibold text-slate-400 hover:text-white" data-action="today">Today</button>
                <div class="flex items-center gap-2">
                    <button type="button" class="btn-glass h-9 px-3.5 text-xs font-semibold" data-action="cancel">Cancel</button>
                    <button type="button" class="btn-primary-glass h-9 px-4 text-xs font-semibold" data-action="apply">Done</button>
                </div>
            </div>`;
    }

    function pick(iso) {
        if (state.field === 'start') {
            state.start = iso;
            if (state.end && state.end < iso) state.end = null;
            state.field = 'end';
        } else if (state.start && iso < state.start) {
            state.start = iso; // a due date can't be before the start: treat it as the new start
        } else {
            state.end = iso;
        }
    }

    function onClick(e) {
        const target = e.target.closest('button');
        if (!target || !state) return;
        if (target.dataset.date) {
            pick(target.dataset.date);
            render();
            el.querySelector(`[data-date="${target.dataset.date}"]`)?.focus();
        } else if (target.dataset.field) {
            state.field = target.dataset.field;
            render();
        } else if (target.dataset.nav) {
            state.view = new Date(state.view.getFullYear(), state.view.getMonth() + Number(target.dataset.nav), 1);
            render();
        } else if (target.dataset.preset) {
            if (!state.start) state.start = todayIso();
            state.end = target.dataset.preset;
            const d = fromIso(state.end);
            state.view = new Date(d.getFullYear(), d.getMonth(), 1);
            render();
        } else if (target.dataset.action === 'today') {
            const d = new Date();
            state.view = new Date(d.getFullYear(), d.getMonth(), 1);
            pick(todayIso());
            render();
        } else if (target.dataset.action === 'cancel') {
            close();
        } else if (target.dataset.action === 'apply') {
            const { start, end, onApply } = state;
            close();
            onApply({ start, end });
        }
    }

    return { open, close };
})();

/** Small grey caption above an inline-editable field in the task table. */
const taskCaption = text => `<span class="block text-[11px] font-medium text-slate-500 mb-0.5">${text}</span>`;

/** Deadline header: first click sorts soonest first, the next flips it. */
window.sortTasksByDeadline = function() {
    const sel = document.getElementById('td-flt-sort');
    if (!sel) return;
    sel.value = sel.value === 'deadline-asc' ? 'deadline-desc' : 'deadline-asc';
    renderTasksTableRows(true);
};

/** Work has begun once the timer runs or has logged time (mirrors ProjectTask::hasStartedWork). */
function taskHasStartedWork(t) {
    return Boolean(t.running) || (parseInt(t.actSeconds, 10) || 0) > 0;
}

/** Action buttons for a task, shared by the table and card views so both offer the same actions. */
function taskActionsHtml(t) {
    const name = escapeHtml(t.name);
    return `
        <div class="axis-row-actions" role="group" aria-label="Actions for ${name}">
            <button type="button" onclick="openTaskEdit(${t.id})" class="axis-row-btn" title="Edit task" aria-label="Edit ${name}">
                <i class="fa-solid fa-pen-to-square text-xs" aria-hidden="true"></i>
            </button>
            <button type="button" onclick="openDevIssueDrawer(${t.id})" class="axis-row-btn" title="Issue notes" aria-label="Issue notes for ${name}">
                <i class="fa-regular fa-note-sticky text-xs" aria-hidden="true"></i>
            </button>
            <button type="button" onclick="resetTaskStopwatch(${t.id})" class="axis-row-btn" title="Reset logged time" aria-label="Reset the logged time of ${name}">
                <i class="fa-solid fa-rotate-left text-xs" aria-hidden="true"></i>
            </button>
            <button type="button" onclick="deleteTaskRow(${t.id})" class="axis-row-btn axis-row-btn--danger" title="Delete task" aria-label="Delete ${name}">
                <i class="fa-regular fa-trash-can text-xs" aria-hidden="true"></i>
            </button>
        </div>`;
}

function renderTaskRow(t) {
    const hms = formatHMS(t.actSeconds);
    const prog = Math.min(100, Math.max(0, parseFloat(t.progress) || 0));
    const barColor = prog >= 100 ? 'bg-emerald-400' : 'bg-cyan-400';
    const priority = TASK_PRIORITY_STYLES[t.priority] || TASK_PRIORITY_STYLES.High;
    const summary = taskSummary(t);
    const name = escapeHtml(t.name);
    const due = deadlineInfo({ status: t.status, deadline: t.endDate });
    const estSeconds = (parseFloat(t.estHours) || 0) * 3600;
    const timeUsed = estSeconds ? Math.min(100, (t.actSeconds / estSeconds) * 100) : 0;
    const chevron = '<i class="fa-solid fa-chevron-down text-[9px] text-slate-500 opacity-0 group-hover:opacity-100 transition-opacity absolute right-1.5 top-1/2 -translate-y-1/2 pointer-events-none" aria-hidden="true"></i>';

    const handlerOptions = (t.employeeId ? '' : '<option value="" selected>Unassigned</option>') + developers([t.employeeId]).map(m =>
        `<option value="${m.id}" ${t.employeeId === m.id ? 'selected' : ''}>${escapeHtml(m.name)}</option>`
    ).join('');
    const assignerOptions = `<option value="" ${t.assignedById ? '' : 'selected'}>Not recorded</option>` + leaders([t.assignedById]).map(m =>
        `<option value="${m.id}" ${t.assignedById === m.id ? 'selected' : ''}>${escapeHtml(m.name)}</option>`
    ).join('');

    const issues = t.issues || [];
    const openIssues = issues.filter(i => i.status !== 'Resolved').length;
    const notesHtml = issues.length === 0
        ? '<span class="text-xs text-slate-500">None</span>'
        : openIssues > 0
            ? `<button type="button" onclick="openDevIssueDrawer(${t.id})" title="${escapeHtml((issues.find(i => i.status !== 'Resolved') || {}).encountered || '')}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-amber-300 hover:text-amber-200 whitespace-nowrap"><span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>${openIssues} open</button>`
            : `<button type="button" onclick="openDevIssueDrawer(${t.id})" class="inline-flex items-center gap-1.5 text-xs font-semibold text-sky-300 hover:text-sky-200 whitespace-nowrap"><i class="fa-solid fa-check text-[10px]" aria-hidden="true"></i>${issues.length} fixed</button>`;

    return `
        <tr data-task-row="${t.id}" class="${t.running ? 'timer-running-row' : ''} transition-colors group">
            <td class="py-3 pl-5 pr-3 axis-sticky-left">
                <div class="w-[290px]">
                    <input type="text" value="${name}" title="${name}" aria-label="Task name"
                        oninput="updateTaskField(${t.id}, 'name', this.value)"
                        class="${GHOST_FIELD} w-full px-1.5 py-0.5 -ml-1.5 text-sm font-semibold text-white truncate">
                    <button type="button" id="task-desc-preview-${t.id}" onclick="editTaskDescription(${t.id})"
                        class="w-full flex items-center gap-1.5 text-left text-xs text-slate-400 hover:text-slate-200 mt-0.5 min-w-0" title="${escapeHtml(summary || 'Add details')}">
                        <span class="font-mono text-slate-500 shrink-0">TASK-${t.id}</span>
                        <span class="text-slate-600 shrink-0">·</span>
                        <span class="truncate">${summary ? escapeHtml(summary) : '<span class="italic text-slate-500">Add details…</span>'}</span>
                    </button>
                    <textarea id="task-desc-editor-${t.id}" rows="3" aria-label="Task details"
                        oninput="updateTaskField(${t.id}, 'description', this.value)"
                        onblur="renderTasksTableRows()"
                        placeholder="* Main item&#10;- Sub-item"
                        class="hidden w-full mt-1 px-2 py-1.5 text-xs text-slate-200 leading-snug bg-white/[0.06] border border-sky-400/40 rounded-md outline-none resize-y">${escapeHtml(t.description)}</textarea>
                </div>
            </td>
            <td class="py-3 px-3">
                ${statusSelectHtml(`updateTaskStatus(${t.id}, this.value)`, t.status, name, `task-status-${t.id}`)}
            </td>
            <td class="py-3 px-3">
                <div class="flex items-center gap-2 w-[150px]">
                    <div class="axis-progress-track" id="task-track-${t.id}" role="slider" tabindex="0"
                        aria-label="Progress of ${name}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${Math.round(prog)}" aria-valuetext="${Math.round(prog)}%"
                        title="${prog <= 0 && t.status === 'Processing' ? 'Started (time is logged) but nothing marked done yet. ' : ''}Drag or click to set progress"
                        onpointerdown="startTaskProgressDrag(event, ${t.id}, this)"
                        onkeydown="handleTaskProgressSliderKey(event, ${t.id})">
                        <div class="axis-progress-track__rail">
                            <div id="task-bar-${t.id}" class="h-full ${barColor} rounded-full transition-all duration-300" style="width: ${prog}%"></div>
                        </div>
                        <span id="task-thumb-${t.id}" class="axis-progress-track__thumb" style="--p: ${prog}" aria-hidden="true"></span>
                    </div>
                    <span class="flex items-center shrink-0">
                        <input type="number" min="0" max="100" step="5" inputmode="numeric" value="${Math.round(prog)}" data-committed="${Math.round(prog)}"
                            id="task-pct-input-${t.id}" aria-label="Progress percent. Press Enter to save, Escape to cancel, arrow keys to change by 5."
                            title="Type a number, then press Enter (Esc cancels, ↑/↓ change by 5)"
                            oninput="previewTaskProgress(${t.id}, this.value)"
                            onchange="commitTaskProgressInput(${t.id}, this)"
                            onkeydown="handleTaskProgressKey(event, ${t.id}, this)"
                            class="${GHOST_FIELD} [field-sizing:content] min-w-[1.5rem] px-0.5 py-0 text-right text-xs font-mono font-semibold text-slate-200 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none">
                        <span class="text-xs text-slate-400">%</span>
                    </span>
                </div>
            </td>
            <td class="py-3 px-3">
                <div class="flex items-center gap-2.5 w-[240px]">
                    <span class="w-8 h-8 rounded-full ${avatarToneFor(t.handler)} text-xs font-semibold inline-flex items-center justify-center shrink-0" aria-hidden="true">${escapeHtml(initialsOf(t.handler))}</span>
                    <div class="min-w-0 flex-1">
                        <div class="relative">
                            <select onchange="updateTaskAssignment(${t.id}, 'emp_id', this.value)" title="${escapeHtml(t.handler || 'Unassigned')}" aria-label="Assignee"
                                class="${GHOST_FIELD} appearance-none w-full pl-1.5 pr-5 py-0.5 text-sm text-slate-100 truncate cursor-pointer">${handlerOptions}</select>
                            ${chevron}
                        </div>
                        <div class="relative flex items-center">
                            <span class="text-xs text-slate-500 pl-1.5 shrink-0 whitespace-nowrap">Assigned by</span>
                            <select onchange="updateTaskAssignment(${t.id}, 'assigned_by_id', this.value)" title="Assigned by ${escapeHtml(t.assignedBy || 'not recorded')}" aria-label="Assigned by"
                                class="${GHOST_FIELD} appearance-none w-full pl-1 pr-5 py-0 text-xs ${t.assignedById ? 'text-slate-300' : 'text-slate-500'} truncate cursor-pointer">${assignerOptions}</select>
                            ${chevron}
                        </div>
                    </div>
                </div>
            </td>
            <td class="py-3 px-3">
                <div class="relative inline-flex items-center">
                    <span class="w-1.5 h-1.5 rounded-full ${priority.dot} absolute left-2 pointer-events-none" aria-hidden="true"></span>
                    <select onchange="updateTaskField(${t.id}, 'priority', this.value); renderTasksTableRows();" aria-label="Priority"
                        class="${GHOST_FIELD} appearance-none pl-5 pr-5 py-1 text-sm font-medium ${priority.cls} cursor-pointer">
                        <option value="High" ${t.priority === 'High' ? 'selected' : ''}>High</option>
                        <option value="Medium" ${t.priority === 'Medium' ? 'selected' : ''}>Medium</option>
                        <option value="Low" ${t.priority === 'Low' ? 'selected' : ''}>Low</option>
                    </select>
                    ${chevron}
                </div>
            </td>
            <td class="py-3 px-3">
                <div class="flex items-center gap-2.5 w-[200px]">
                    <button type="button" onclick="toggleTaskStopwatch(${t.id})" aria-label="${t.running ? 'Pause timer' : 'Start timer'}" title="${t.running ? 'Pause timer' : 'Start timer'}"
                        class="w-8 h-8 rounded-full shrink-0 inline-flex items-center justify-center transition-colors ${t.running ? 'bg-amber-500/20 text-amber-300 hover:bg-amber-500/30' : 'bg-sky-500/15 text-sky-300 hover:bg-sky-500/25'}">
                        <i class="fa-solid ${t.running ? 'fa-pause' : 'fa-play'} text-[10px]" aria-hidden="true"></i>
                    </button>
                    <div class="min-w-0 flex-1">
                        <div id="task-main-act-${t.id}" class="font-mono text-sm font-semibold ${t.running ? 'text-emerald-300' : 'text-slate-100'}">${hms}</div>
                        <div class="flex items-center text-xs text-slate-400">
                            <span>of</span>
                            <input type="number" min="0" step="0.5" value="${t.estHours}" aria-label="Estimated work hours"
                                title="Estimated work hours: actual working time, compared with the timer"
                                oninput="updateTaskEst(${t.id}, this.value)"
                                class="${GHOST_FIELD} [field-sizing:content] min-w-[1.25rem] mx-0.5 px-0.5 py-0 text-xs font-mono text-slate-300 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none">
                            <span>h estimate</span>
                        </div>
                        <div class="mt-1 h-1 rounded-full bg-white/[0.08] overflow-hidden" title="${Math.round(timeUsed)}% of the estimated work hours used">
                            <div class="h-full rounded-full ${timeUsed >= 100 ? 'bg-rose-400/80' : 'bg-sky-400/70'}" style="width:${timeUsed}%"></div>
                        </div>
                    </div>
                </div>
            </td>
            <td class="py-3 px-3">
                <button type="button" class="axis-deadline-cell" onclick="openTaskSchedulePicker(${t.id}, this)" aria-haspopup="dialog"
                    title="When the work should be finished.${t.startDate ? ` Starts ${escapeHtml(shortDate(t.startDate))}.` : ''} Click to change the dates."
                    aria-label="Deadline ${t.endDate ? escapeHtml(shortDate(t.endDate)) : 'not set'}${t.endDate ? `, ${escapeHtml(due.text)}` : ''}. Click to change the dates.">
                    <span class="block text-sm ${t.endDate ? 'text-slate-100' : 'text-slate-500'} whitespace-nowrap">${t.endDate ? escapeHtml(shortDate(t.endDate)) : 'Set deadline'}</span>
                    ${t.endDate ? `<span class="block text-xs ${due.cls} whitespace-nowrap">${escapeHtml(due.text)}</span>` : ''}
                </button>
            </td>
            <td class="py-3 px-3">${notesHtml}</td>
            <td class="py-3 pl-3 pr-5 axis-sticky-right">
                <div class="flex items-center justify-end">
                    ${taskActionsHtml(t)}
                </div>
            </td>
        </tr>
    `;
}

const TASK_API_FIELDS = { name: 'name', description: 'description', priority: 'priority', startDate: 'start_date', endDate: 'deadline' };

/**
 * Change who a task is assigned to (`emp_id`) or which leader assigned it (`assigned_by_id`).
 * The server validates that the assigner is a leader; names come back with the response.
 */
window.updateTaskAssignment = function(taskId, apiField, value) {
    const p = projects.find(x => x.id === activeProjectId);
    const t = p && p.tasks.find(x => x.id === taskId);
    if (!t) return;
    const snapshot = { employeeId: t.employeeId, handler: t.handler, assignedById: t.assignedById, assignedBy: t.assignedBy };
    const id = value ? Number(value) : null;
    if (apiField === 'emp_id') { t.employeeId = id; t.handler = employeeName(id); }
    else { t.assignedById = id; t.assignedBy = id ? employeeName(id) : null; }
    renderTasksTableRows();
    persistTask(t, { [apiField]: id }, () => { Object.assign(t, snapshot); renderTasksTableRows(); })
        .then(serverTask => {
            if (!serverTask) return;
            Object.assign(t, {
                employeeId: serverTask.employeeId, handler: serverTask.handler,
                assignedById: serverTask.assignedById, assignedBy: serverTask.assignedBy,
            });
            renderTasksTableRows();
        });
};

/**
 * PATCH a subset of task attributes and merge the authoritative copy back.
 *
 * @returns {Promise<object|null>} the server task, or null on failure
 */
function persistTask(t, payload, rollback) {
    return AxisAPI.idOf(t)
        .then(id => AxisAPI.request(AxisAPI.url('tasks.update', id), 'PATCH', payload))
        .then(res => res.data)
        .catch(err => { AxisAPI.fail(err, rollback); return null; });
}

window.updateTaskField = function(taskId, field, value) {
    const p = projects.find(x => x.id === activeProjectId);
    if (!p) return;
    const t = p.tasks.find(x => x.id === taskId);
    if (!t) return;
    t[field] = value;

    const apiField = TASK_API_FIELDS[field];
    if (!apiField) return;
    const isText = field === 'name' || field === 'description';
    AxisAPI.debounce(`task:${taskId}:${field}`, () => {
        persistTask(t, { [apiField]: t[field] === '' && !isText ? null : t[field] });
    }, isText ? 600 : 0);
};

window.updateTaskStatus = function(taskId, newStatus) {
    const p = projects.find(x => x.id === activeProjectId);
    if (!p) return;
    const t = p.tasks.find(x => x.id === taskId);
    if (!t) return;
    const snapshot = { status: t.status, progress: t.progress, running: t.running };
    t.status = newStatus;
    // Same rules as the server (ProjectTask::applyStatus): Finished = 100%, Not yet started = 0%, Processing < 100%
    if (newStatus === 'Finished') {
        t.progress = 100;
        t.running = false;
    } else {
        if (newStatus === 'Not yet started') t.progress = 0;
        else if (t.progress >= 100) t.progress = 99;
    }
    renderTasksTableRows();
    refreshDetailSummaryCards();
    syncHeaderTimerPill();

    persistTask(t, { status: newStatus }, () => {
        Object.assign(t, snapshot);
        renderTasksTableRows();
        refreshDetailSummaryCards();
    }).then(serverTask => {
        if (!serverTask) return;
        mergeServerTask(t, serverTask);
        renderTasksTableRows();
        refreshDetailSummaryCards();
        syncHeaderTimerPill();
    });
};

/** Update a task row's Status dropdown in place (the progress input keeps focus while you type). */
function syncTaskStatusField(t) {
    const select = document.getElementById(`task-status-${t.id}`);
    if (select) {
        select.value = t.status;
        select.className = statusSelectClass(t.status);
    }
}

/** A typed progress value as a whole number 0–100, or null when it isn't a usable number. */
function parseProgressInput(raw) {
    const text = String(raw ?? '').trim();
    if (text === '') return null;
    const value = Number(text);
    return Number.isFinite(value) ? Math.min(100, Math.max(0, Math.round(value))) : null;
}

function paintTaskProgressBar(taskId, pct) {
    const bar = document.getElementById('task-bar-' + taskId);
    if (bar) {
        bar.style.width = pct + '%';
        bar.className = 'h-full rounded-full transition-all duration-300 ' + (pct >= 100 ? 'bg-emerald-400' : 'bg-cyan-400');
    }
    const thumb = document.getElementById('task-thumb-' + taskId);
    if (thumb) thumb.style.setProperty('--p', pct);
    const track = document.getElementById('task-track-' + taskId);
    if (track) {
        track.setAttribute('aria-valuenow', String(pct));
        track.setAttribute('aria-valuetext', pct + '%');
    }
}

/** Set the progress number then apply it once (same path as typing + Enter). */
function setTaskProgressFromSlider(taskId, pct) {
    const input = document.getElementById('task-pct-input-' + taskId);
    if (!input) return;
    input.value = Math.min(100, Math.max(0, pct));
    commitTaskProgressInput(taskId, input);
}

/**
 * Drag (or click) the progress bar. While dragging only the bar and number follow the pointer;
 * releasing applies the value once. Values snap to 5% steps, like the arrow keys.
 */
window.startTaskProgressDrag = function(e, taskId, track) {
    if (e.button !== 0) return;
    e.preventDefault();
    track.focus({ preventScroll: true });
    const input = document.getElementById('task-pct-input-' + taskId);
    if (!input) return;
    const startValue = input.value;
    const pctAt = clientX => {
        const rect = track.getBoundingClientRect();
        const ratio = rect.width ? (clientX - rect.left) / rect.width : 0;
        return Math.round(Math.min(100, Math.max(0, ratio * 100)) / 5) * 5;
    };
    const follow = ev => {
        const pct = pctAt(ev.clientX);
        input.value = pct;
        paintTaskProgressBar(taskId, pct);
    };
    const finish = ev => {
        track.removeEventListener('pointermove', follow);
        track.removeEventListener('pointerup', finish);
        track.removeEventListener('pointercancel', finish);
        track.classList.remove('is-dragging');
        if (ev.type === 'pointercancel') {
            input.value = startValue;
            paintTaskProgressBar(taskId, Number(input.dataset.committed) || 0);
            return;
        }
        commitTaskProgressInput(taskId, input);
    };
    try { track.setPointerCapture(e.pointerId); } catch (err) { /* keep listening on the track */ }
    track.classList.add('is-dragging');
    follow(e);
    track.addEventListener('pointermove', follow);
    track.addEventListener('pointerup', finish);
    track.addEventListener('pointercancel', finish);
};

/** Keyboard on the focused bar: ←/→ or ↑/↓ change by 5%, Home = 0%, End = 100%. */
window.handleTaskProgressSliderKey = function(e, taskId) {
    const input = document.getElementById('task-pct-input-' + taskId);
    if (!input) return;
    const current = Number(input.dataset.committed) || 0;
    const next = {
        ArrowRight: current + 5, ArrowUp: current + 5,
        ArrowLeft: current - 5, ArrowDown: current - 5,
        Home: 0, End: 100,
    }[e.key];
    if (next === undefined) return;
    e.preventDefault();
    setTaskProgressFromSlider(taskId, next);
};

/** While typing: only the bar previews the value — status and saving wait for the commit. */
window.previewTaskProgress = function(taskId, raw) {
    const pct = parseProgressInput(raw);
    if (pct !== null) paintTaskProgressBar(taskId, pct);
};

/** Enter / Tab / click away: apply the value once (invalid or blank input reverts). */
window.commitTaskProgressInput = function(taskId, input) {
    const committed = Number(input.dataset.committed) || 0;
    const pct = parseProgressInput(input.value);
    if (pct === null) {
        input.value = committed;
        paintTaskProgressBar(taskId, committed);
        showToast('Progress must be a number from 0 to 100');
        return;
    }
    input.value = pct;
    // Same number and a status that already matches it: nothing to do. (A status that doesn't match,
    // e.g. 0% still "Processing" from before, is corrected by re-applying the rules.)
    const t = (projects.find(x => x.id === activeProjectId)?.tasks || []).find(x => x.id === taskId);
    const expectedStatus = pct === 100 ? 'Finished' : (pct === 0 && !t?.running ? 'Not yet started' : 'Processing');
    if (pct === committed && t?.status === expectedStatus) {
        paintTaskProgressBar(taskId, pct);
        return;
    }
    input.dataset.committed = pct;
    updateTaskProgress(taskId, pct);
};

window.handleTaskProgressKey = function(e, taskId, input) {
    if (e.key === 'Enter') {
        e.preventDefault();
        input.blur(); // fires "change" → commit
    } else if (e.key === 'Escape') {
        e.preventDefault();
        e.stopPropagation();
        const committed = Number(input.dataset.committed) || 0;
        input.value = committed;
        paintTaskProgressBar(taskId, committed);
        input.blur();
    } else if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
        e.preventDefault();
        const base = parseProgressInput(input.value) ?? (Number(input.dataset.committed) || 0);
        input.value = Math.min(100, Math.max(0, base + (e.key === 'ArrowUp' ? 5 : -5)));
        commitTaskProgressInput(taskId, input);
    }
};

window.updateTaskProgress = function(taskId, val) {
    const p = projects.find(x => x.id === activeProjectId);
    if (!p) return;
    const t = p.tasks.find(x => x.id === taskId);
    if (!t) return;
    const prev = t.progress;
    const pct = Math.min(100, Math.max(0, parseFloat(val) || 0));
    t.progress = pct;
    // Same rules as the server (ProjectTask::applyProgress):
    // 100% = Finished; 0% = Not yet started (unless its timer is running); anything between = Processing
    if (pct === 100) {
        t.status = 'Finished';
    } else {
        t.status = pct === 0 && !taskHasStartedWork(t) ? 'Not yet started' : 'Processing';
    }
    syncTaskStatusField(t);

    paintTaskProgressBar(taskId, pct);
    const input = document.getElementById('task-pct-input-' + taskId);
    if (input && document.activeElement !== input) {
        input.value = Math.round(pct);
        input.dataset.committed = Math.round(pct);
    }
    refreshDetailSummaryCards();

    // Persist once edits settle (quick arrow-key steps combine into one save); a reply to an
    // older save is ignored so it can never overwrite a newer value
    const saveSeq = (t._progressSaveSeq = (t._progressSaveSeq || 0) + 1);
    AxisAPI.debounce(`task:${taskId}:progress`, () => {
        persistTask(t, { progress: t.progress }).then(serverTask => {
            if (!serverTask || saveSeq !== t._progressSaveSeq) return;
            const statusChanged = serverTask.status !== t.status || serverTask.running !== t.running;
            mergeServerTask(t, serverTask);
            if (statusChanged) {
                renderTasksTableRows();
                syncHeaderTimerPill();
            }
            refreshDetailSummaryCards();
        });
    }, 400);
};

window.updateTaskEst = function(taskId, val) {
    const p = projects.find(x => x.id === activeProjectId);
    if (!p) return;
    const t = p.tasks.find(x => x.id === taskId);
    if (t) {
        t.estHours = Math.max(0, parseFloat(val) || 0);
        refreshDetailSummaryCards();
        AxisAPI.debounce(`task:${taskId}:est`, () => {
            persistTask(t, { est_hours: t.estHours });
        }, 500);
    }
};

window.toggleTaskStopwatch = function(taskId) {
    const p = projects.find(x => x.id === activeProjectId);
    if (!p) return;
    const t = p.tasks.find(x => x.id === taskId);
    if (!t) return;
    const snapshot = { running: t.running, status: t.status };
    t.running = !t.running;
    if (t.running && t.status === 'Not yet started') t.status = 'Processing';
    renderTasksTableRows();
    syncHeaderTimerPill();

    // Server-accurate chronometer: the server banks elapsed seconds from `timer_started_at`
    AxisAPI.idOf(t)
        .then(id => AxisAPI.request(AxisAPI.url('tasks.timer.toggle', id), 'POST'))
        .then(res => {
            mergeServerTask(t, res.data);
            renderTasksTableRows();
            refreshDetailSummaryCards();
            syncHeaderTimerPill();
        })
        .catch(err => AxisAPI.fail(err, () => {
            Object.assign(t, snapshot);
            renderTasksTableRows();
            syncHeaderTimerPill();
        }));
};

window.resetTaskStopwatch = function(taskId) {
    const p = projects.find(x => x.id === activeProjectId);
    if (!p) return;
    const t = p.tasks.find(x => x.id === taskId);
    if (!t) return;
    const snapshot = { running: t.running, actSeconds: t.actSeconds, status: t.status };
    t.running = false;
    t.actSeconds = 0;
    // Same rule as the server: no time and nothing done means not started
    if (t.status === 'Processing' && (parseFloat(t.progress) || 0) <= 0) t.status = 'Not yet started';
    renderTasksTableRows();
    refreshDetailSummaryCards();
    syncHeaderTimerPill();

    AxisAPI.idOf(t)
        .then(id => AxisAPI.request(AxisAPI.url('tasks.timer.reset', id), 'POST'))
        .then(res => mergeServerTask(t, res.data))
        .catch(err => AxisAPI.fail(err, () => {
            Object.assign(t, snapshot);
            renderTasksTableRows();
            refreshDetailSummaryCards();
            syncHeaderTimerPill();
        }));
};

window.deleteTaskRow = async function(taskId) {
    const p = projects.find(x => x.id === activeProjectId);
    if (!p) return;
    const idx = p.tasks.findIndex(x => x.id === taskId);
    if (idx === -1) return;
    const removed = p.tasks[idx];
    const notes = (removed.issues || []).length;
    const confirmed = await AxisDrawer.confirm({
        title: 'Delete this task?',
        breadcrumb: `${p.code} · Tasks`,
        message: notes
            ? `"${removed.name}" and its ${notes} note${notes === 1 ? '' : 's'} will be removed, along with ${formatHMS(removed.actSeconds)} of logged time.`
            : `"${removed.name}" will be removed, along with ${formatHMS(removed.actSeconds)} of logged time.`,
        confirmLabel: 'Delete task'
    });
    if (!confirmed) return;
    if (!p.tasks.includes(removed)) return;

    p.tasks = p.tasks.filter(x => x.id !== taskId);
    renderTasksTableRows();
    refreshDetailSummaryCards();
    syncHeaderTimerPill();
    showToast('Task deleted');

    AxisAPI.idOf(removed)
        .then(id => AxisAPI.request(AxisAPI.url('tasks.destroy', id), 'DELETE'))
        .catch(err => AxisAPI.fail(err, () => {
            p.tasks.splice(idx, 0, removed);
            renderTasksTableRows();
            refreshDetailSummaryCards();
            syncHeaderTimerPill();
        }));
};

/** New task drawer: list every module (new ones included) and preselect the one being viewed. */
function syncNewTaskModuleSelect() {
    const select = document.getElementById('new-task-module');
    if (!select) return;
    select.innerHTML = [...projects].sort((a, b) => a.code.localeCompare(b.code)).map(m =>
        `<option value="${m.id}">${escapeHtml(m.code)} — ${escapeHtml(m.name)}${m.projectName ? ` (${escapeHtml(m.projectName)})` : ''}</option>`
    ).join('');
    if (projects.some(m => m.id === activeProjectId)) select.value = String(activeProjectId);
}

// =====================================================================
// EDIT TASK DRAWER — every field of one task in one place
// =====================================================================
let editingTaskId = null;

window.openTaskEdit = function(taskId) {
    const p = projects.find(x => x.id === activeProjectId);
    const t = p && p.tasks.find(x => x.id === taskId);
    if (!t) return;
    editingTaskId = t.id;

    document.getElementById('edit-task-name').value = t.name || '';
    document.getElementById('edit-task-desc').value = t.description || '';
    document.getElementById('edit-task-handler').innerHTML =
        (t.employeeId ? '' : '<option value="" selected disabled>Choose someone</option>') +
        developers([t.employeeId]).map(m => `<option value="${m.id}" ${m.id === t.employeeId ? 'selected' : ''}>${escapeHtml(m.name)}</option>`).join('');
    document.getElementById('edit-task-assigned-by').innerHTML =
        `<option value="" ${t.assignedById ? '' : 'selected'}>Not recorded</option>` +
        leaders([t.assignedById]).map(m => `<option value="${m.id}" ${m.id === t.assignedById ? 'selected' : ''}>${escapeHtml(m.name)}</option>`).join('');
    document.getElementById('edit-task-priority').value = t.priority || 'High';
    document.getElementById('edit-task-status').value = t.status;
    document.getElementById('edit-task-progress').value = Math.round(parseFloat(t.progress) || 0);
    document.getElementById('edit-task-start').value = t.startDate || '';
    document.getElementById('edit-task-end').value = t.endDate || '';
    syncDateRangeField(document.getElementById('edit-task-schedule'));
    document.getElementById('edit-task-est').value = t.estHours ?? '';

    AxisDrawer.open('drawer-task-edit', { title: `Edit ${t.name}`, breadcrumb: `${p.code} · TASK-${t.id}` });
};

/** Same rules as the table: Finished → 100%, Not yet started → 0%, Processing never shows 100%. */
window.syncTaskEditProgressFromStatus = function(status) {
    const input = document.getElementById('edit-task-progress');
    const value = parseFloat(input.value) || 0;
    if (status === 'Finished') input.value = 100;
    else if (status === 'Not yet started') input.value = 0;
    else if (value >= 100) input.value = 99;
};

window.syncTaskEditStatusFromProgress = function(raw) {
    if (raw === '') return;
    const value = Math.min(100, Math.max(0, parseFloat(raw) || 0));
    const t = projects.find(x => x.id === activeProjectId)?.tasks.find(x => x.id === editingTaskId);
    document.getElementById('edit-task-status').value =
        value >= 100 ? 'Finished' : (value <= 0 && !(t && taskHasStartedWork(t))) ? 'Not yet started' : 'Processing';
};

window.handleUpdateTask = function(e) {
    e.preventDefault();
    const p = projects.find(x => x.id === activeProjectId);
    const t = p && p.tasks.find(x => x.id === editingTaskId);
    if (!t) return;

    const name = document.getElementById('edit-task-name').value.trim();
    if (!name) {
        AxisDrawer.showErrors('drawer-task-edit', { name: ['Give the task a name.'] });
        return;
    }
    const progressRaw = document.getElementById('edit-task-progress').value;
    const estRaw = document.getElementById('edit-task-est').value;

    AxisDrawer.submit('drawer-task-edit', async () => {
        const id = await AxisAPI.idOf(t);
        const res = await AxisAPI.request(AxisAPI.url('tasks.update', id), 'PATCH', {
            name,
            description: document.getElementById('edit-task-desc').value.trim() || null,
            emp_id: Number(document.getElementById('edit-task-handler').value) || null,
            assigned_by_id: Number(document.getElementById('edit-task-assigned-by').value) || null,
            priority: document.getElementById('edit-task-priority').value,
            // Progress first, then status: the server applies them in that order, so the chosen status wins
            ...(progressRaw === '' ? {} : { progress: Math.min(100, Math.max(0, parseFloat(progressRaw) || 0)) }),
            status: document.getElementById('edit-task-status').value,
            start_date: document.getElementById('edit-task-start').value || null,
            deadline: document.getElementById('edit-task-end').value || null,
            ...(estRaw === '' ? {} : { est_hours: Math.max(0, parseFloat(estRaw) || 0) }),
        });
        const updated = res.data;
        if (!Array.isArray(updated.issues)) updated.issues = t.issues || [];
        Object.assign(t, updated);
        closeModal('drawer-task-edit');
        renderTasksTableRows();
        refreshDetailSummaryCards();
        showToast(`Saved ${t.name}`);
    });
};

window.handleCreateTask = function(e) {
    e.preventDefault();
    const moduleId = Number(document.getElementById('new-task-module')?.value) || activeProjectId;
    const p = projects.find(x => x.id === moduleId);
    if (!p) {
        AxisDrawer.showErrors('modal-create-task', { module: ['Choose the module this task belongs to.'] });
        return;
    }

    const prog = Math.min(100, Math.max(0, parseFloat(document.getElementById('new-task-progress').value) || 0));
    const initEnc = (document.getElementById('new-task-issue-enc')?.value || '').trim();
    const initFix = (document.getElementById('new-task-issue-fix')?.value || '').trim();

    AxisDrawer.submit('modal-create-task', async () => {
        const projectId = await AxisAPI.idOf(p);
        const res = await AxisAPI.request(AxisAPI.url('tasks.store', projectId), 'POST', {
            name: document.getElementById('new-task-name').value.trim(),
            description: document.getElementById('new-task-desc').value.trim(),
            priority: document.getElementById('new-task-priority').value,
            start_date: document.getElementById('new-task-start').value || null,
            deadline: document.getElementById('new-task-end').value || null,
            emp_id: Number(document.getElementById('new-task-handler').value) || null,
            assigned_by_id: Number(document.getElementById('new-task-assigned-by')?.value) || null,
            status: prog === 100 ? 'Finished' : document.getElementById('new-task-status').value,
            progress: prog,
            est_hours: parseFloat(document.getElementById('new-task-est').value) || 8,
            initial_issue: initEnc ? { encountered: initEnc, resolution: initFix || null } : null
        });
        const task = res.data;
        if (!Array.isArray(task.issues)) task.issues = [];
        p.tasks.push(task);
        if (initEnc) activeDrawerTaskId = task.id;

        ['new-task-name', 'new-task-desc', 'new-task-issue-enc', 'new-task-issue-fix'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        closeModal('modal-create-task');
        if (p.id !== activeProjectId || activePageId !== 'task-detail') {
            openProjectTaskList(p.id); // show the task in the module it was added to
        } else {
            renderTasksTableRows();
            refreshDetailSummaryCards();
            updateDynamicHeader(activePageId);
        }
        showToast(`Task created in ${p.name}`);
    });
};

function startGlobalStopwatchLoop() {
    if (tickerId) clearInterval(tickerId);
    let globalSec = 0;
    // Wall-clock based so throttled background tabs stay accurate (server remains authoritative)
    let lastTickAt = Date.now();
    let carryMs = 0;
    tickerId = setInterval(() => {
        let anyRunning = false;
        const nowMs = Date.now();
        carryMs += nowMs - lastTickAt;
        lastTickAt = nowMs;
        const step = Math.floor(carryMs / 1000);
        carryMs -= step * 1000;
        if (step <= 0) return;
        globalSec += step;
        projects.forEach(p => {
            p.tasks.forEach(t => {
                if (t.running) {
                    anyRunning = true;
                    t.actSeconds = (t.actSeconds || 0) + step;
                    if (p.id === activeProjectId) {
                        const hms = formatHMS(t.actSeconds);
                        const mainCell = document.getElementById('task-main-act-' + t.id);
                        if (mainCell) mainCell.textContent = hms;
                    }
                }
            });
        });
        if (anyRunning) {
            refreshDetailSummaryCards();
            syncHeaderTimerPill();
        }
    }, 1000);
}

function syncHeaderTimerPill() {
    const pill = document.getElementById('header-timer-pill');
    const lbl = document.getElementById('header-timer-label');
    if (!pill || !lbl) return;

    let runningTask = null;
    projects.forEach(p => {
        p.tasks.forEach(t => {
            if (t.running && !runningTask) runningTask = t;
        });
    });

    if (runningTask) {
        pill.classList.remove('hidden');
        pill.classList.add('flex');
        lbl.textContent = runningTask.name + ': ' + formatHMS(runningTask.actSeconds);
    } else {
        pill.classList.add('hidden');
        pill.classList.remove('flex');
    }
}

// =====================================================================
// PAGE 5: DAILY & WEEKLY CTO REPORTS (STRUCTURED & TELEGRAM READY)
// =====================================================================
let activeReportDetailId = null;
let reportBuilderSections = [];

window.renderReportsPage = function() {
    const totalReports = ctoReports.length;
    const weeklyCount = ctoReports.filter(r => r.type === 'Weekly').length;
    const dailyCount = ctoReports.filter(r => r.type === 'Daily').length;
    const totalHours = ctoReports.reduce((sum, r) => sum + (parseFloat(r.hours) || 0), 0);
    const uniqueModules = new Set(ctoReports.map(r => r.module)).size;

    const kpiGrid = document.getElementById('reports-kpi-grid');
    if (kpiGrid) {
        kpiGrid.innerHTML = `
            <div class="p-4 rounded-xl border border-white/[0.08] bg-white/[0.02]">
                <div class="text-xs font-semibold text-slate-400">Total filed</div>
                <div class="text-lg font-bold text-white mt-1">${totalReports} reports</div>
                <div class="text-[11px] text-slate-500 mt-0.5">${weeklyCount} weekly · ${dailyCount} daily</div>
            </div>
            <div class="p-4 rounded-xl border border-white/[0.08] bg-white/[0.02]">
                <div class="text-xs font-semibold text-slate-400">Total hours logged</div>
                <div class="text-lg font-bold text-emerald-400 mt-1 font-mono">${+totalHours.toFixed(1)}h</div>
                <div class="text-[11px] text-emerald-400 mt-0.5">Verified standups</div>
            </div>
            <div class="p-4 rounded-xl border border-white/[0.08] bg-white/[0.02]">
                <div class="text-xs font-semibold text-slate-400">Modules covered</div>
                <div class="text-lg font-bold text-sky-400 mt-1">${uniqueModules} modules</div>
                <div class="text-[11px] text-sky-400 mt-0.5">Tested across team</div>
            </div>
            <div class="p-4 rounded-xl border border-white/[0.08] bg-white/[0.02]">
                <div class="text-xs font-semibold text-slate-400">Telegram format</div>
                <div class="text-lg font-bold text-indigo-300 mt-1">100% Ready</div>
                <div class="text-[11px] text-indigo-300 mt-0.5">1-click copy &amp; paste</div>
            </div>
        `;
    }

    const tFilter = document.getElementById('rep-type-filter')?.value || 'all';
    const hFilter = document.getElementById('rep-handler-filter')?.value || 'all';
    const sFilter = (document.getElementById('rep-search-filter')?.value || '').toLowerCase().trim();

    const filtered = ctoReports.filter(r => {
        const matchesType = (tFilter === 'all' || r.type === tFilter);
        const matchesHandler = (hFilter === 'all' || r.handler === hFilter);
        const matchesSearch = !sFilter ||
            (r.module && r.module.toLowerCase().includes(sFilter)) ||
            (r.handler && r.handler.toLowerCase().includes(sFilter)) ||
            (r.summary && r.summary.toLowerCase().includes(sFilter)) ||
            (r.blocker && r.blocker.toLowerCase().includes(sFilter));
        return matchesType && matchesHandler && matchesSearch;
    });

    const tbody = document.getElementById('reports-tbody');
    if (tbody) {
        if (!filtered.length) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="py-12 text-center text-slate-500 text-xs">
                        <i class="fa-solid fa-file-circle-question text-2xl text-slate-500 block mb-2"></i>
                        No reports match your filters. Click <strong>New report</strong> to submit one.
                    </td>
                </tr>
            `;
        } else {
            tbody.innerHTML = filtered.map(r => {
                const isWeekly = r.type === 'Weekly';
                const periodLabel = (r.date_from && r.date_to)
                    ? `${formatShortDate(r.date_from)} – ${formatShortDate(r.date_to)}`
                    : (r.date || 'Today');

                // Compute scope summary if content_json exists
                let scopeHtml = '';
                const sections = r.content_json?.sections || [];
                if (sections.length > 0) {
                    const totalItems = sections.reduce((sum, s) => sum + (s.items?.length || 0), 0);
                    const sampleCodes = sections.flatMap(s => (s.items || []).map(i => i.code)).filter(Boolean).slice(0, 3);
                    scopeHtml = `
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="text-xs font-semibold text-white">${sections.length} Role${sections.length > 1 ? 's' : ''} · ${totalItems} Test Case${totalItems > 1 ? 's' : ''}</span>
                            ${sampleCodes.map(c => `<span class="px-1.5 py-0.5 rounded text-[10px] font-mono bg-sky-500/15 text-sky-300 border border-sky-400/20">${escapeHtml(c)}</span>`).join('')}
                        </div>
                        <div class="text-[11px] text-slate-400 truncate max-w-md mt-0.5">${escapeHtml(sections[0]?.title || '')}</div>
                    `;
                } else {
                    scopeHtml = `<div class="text-xs text-slate-300 line-clamp-2">${escapeHtml(r.summary || 'No summary text')}</div>`;
                }

                const blockerHtml = (!r.blocker || r.blocker.toLowerCase() === 'none')
                    ? `<span class="text-xs text-slate-500">None</span>`
                    : `<span class="px-2 py-0.5 rounded text-xs font-semibold bg-rose-500/15 text-rose-300 border border-rose-400/25 truncate max-w-[140px] inline-block" title="${escapeHtml(r.blocker)}">${escapeHtml(r.blocker)}</span>`;

                return `
                    <tr class="hover:bg-white/[0.02] cursor-pointer transition-colors group" onclick="openReportDetail(${r.id})">
                        <td class="py-3 px-3">
                            <span class="px-2 py-0.5 rounded text-xs font-semibold ${isWeekly ? 'bg-indigo-500/15 text-indigo-300 border border-indigo-400/25' : 'bg-sky-500/15 text-sky-300 border border-sky-400/25'}">${escapeHtml(r.type)}</span>
                            <div class="text-[11px] font-mono text-slate-400 mt-1">${escapeHtml(periodLabel)}</div>
                        </td>
                        <td class="py-3 px-3 font-medium text-white">
                            <div class="flex items-center gap-2">
                                <span class="w-6 h-6 rounded-full bg-slate-800 border border-white/10 text-[10px] font-bold text-sky-300 flex items-center justify-center">
                                    ${escapeHtml((r.handler || 'U').substring(0, 2).toUpperCase())}
                                </span>
                                <span>${escapeHtml(r.handler || 'Unknown')}</span>
                            </div>
                        </td>
                        <td class="py-3 px-3">
                            <span class="px-2 py-0.5 rounded text-xs font-medium bg-sky-500/10 text-sky-300 border border-sky-400/20">${escapeHtml(r.module || 'Axis')}</span>
                        </td>
                        <td class="py-3 px-3">${scopeHtml}</td>
                        <td class="py-3 px-3">${blockerHtml}</td>
                        <td class="py-3 px-3 text-center font-mono font-semibold text-emerald-300">${r.hours || 0}h</td>
                        <td class="py-3 px-3 text-right">
                            <div class="flex items-center justify-end gap-1 opacity-100 transition-opacity" onclick="event.stopPropagation()">
                                <button type="button" onclick="copyReportTelegramById(${r.id}, event)" class="p-1.5 rounded-lg text-sky-400 hover:text-white hover:bg-sky-500/20 text-xs transition-colors" title="Copy for Telegram">
                                    <i class="fa-solid fa-copy"></i>
                                </button>
                                <button type="button" onclick="openReportDetail(${r.id}); event.stopPropagation();" class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 text-xs transition-colors" title="View details">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                                <button type="button" onclick="editReportById(${r.id}, event)" class="p-1.5 rounded-lg text-amber-400 hover:text-white hover:bg-amber-500/20 text-xs transition-colors" title="Edit report">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <button type="button" onclick="deleteReportById(${r.id}, event)" class="p-1.5 rounded-lg text-rose-400 hover:text-white hover:bg-rose-500/20 text-xs transition-colors" title="Delete report">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        }
    }

    // Populate Latest Developer Issue & Fix Feed in CTO Reports
    const feedBox = document.getElementById('reports-dev-issues-feed');
    if (feedBox) {
        const allIssues = [];
        projects.forEach(p => {
            (p.tasks || []).forEach(t => {
                (t.issues || []).forEach(iss => {
                    allIssues.push({
                        moduleName: p.name,
                        projectId: p.id,
                        taskId: t.id,
                        taskName: t.name,
                        ...iss
                    });
                });
            });
        });

        feedBox.innerHTML = allIssues.slice(0, 6).map(item => `
            <div onclick="openDevIssueDrawer(${item.taskId}, ${item.projectId})" class="p-3.5 rounded-xl bg-slate-950/65 border border-white/[0.08] hover:border-sky-400/35 cursor-pointer transition-all space-y-1.5">
                <div class="flex items-center justify-between text-xs">
                    <span class="font-semibold text-sky-300">${escapeHtml(item.moduleName)} → <span class="text-white">${escapeHtml(item.taskName)}</span></span>
                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold ${item.status === 'Resolved' ? 'bg-emerald-500/15 text-emerald-300 border border-emerald-400/30' : 'bg-amber-500/15 text-amber-300 border-amber-400/30'}">${escapeHtml(item.status)}</span>
                </div>
                <div class="text-xs text-slate-200"><strong class="text-rose-300">Issue:</strong> ${escapeHtml(item.encountered)}</div>
                <div class="text-xs text-emerald-200/90"><strong class="text-emerald-300">Fix:</strong> ${escapeHtml(item.resolution)}</div>
                <div class="text-xs text-slate-500 pt-1">By ${escapeHtml(item.author)} • ${escapeHtml(item.timestamp)}</div>
            </div>
        `).join('');
    }
};

function formatShortDate(dateStr) {
    if (!dateStr) return '';
    const parts = dateStr.split('-');
    if (parts.length === 3) {
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const m = parseInt(parts[1], 10) - 1;
        return `${parseInt(parts[2], 10)} ${months[m] || parts[1]}`;
    }
    return dateStr;
}

// =====================================================================
// SMART TELEGRAM REPORT PARSER & BUILDER
// =====================================================================
window.openCreateReportDrawer = function() {
    const idInput = document.getElementById('rep-modal-id');
    if (idInput) idInput.value = '';

    AxisDrawer.configure('modal-report', {
        title: 'New report',
        breadcrumb: 'Reports',
        description: 'Share structured progress, test cases, pages tested and blockers.',
        submitLabel: 'Send report'
    });

    const typeEl = document.getElementById('rep-modal-type');
    if (typeEl) typeEl.value = 'Weekly';

    const recEl = document.getElementById('rep-modal-recipient');
    if (recEl) recEl.value = 'Dear Bong and team';

    const handlerEl = document.getElementById('rep-modal-handler');
    if (handlerEl) {
        const acting = AXIS_BOOT.currentUser?.name;
        if (acting) {
            handlerEl.value = acting;
        } else if (HANDLERS.length) {
            handlerEl.value = HANDLERS[0];
        }
    }

    const modEl = document.getElementById('rep-modal-module');
    if (modEl && projects.length) modEl.value = projects[0].name;

    const hoursEl = document.getElementById('rep-modal-hours');
    if (hoursEl) hoursEl.value = '30';

    const blockerEl = document.getElementById('rep-modal-blocker');
    if (blockerEl) blockerEl.value = 'None';

    // Set default dates: Monday through Friday of current week
    const now = new Date();
    const day = now.getDay();
    const diffToMon = now.getDate() - day + (day === 0 ? -6 : 1);
    const mon = new Date(now.setDate(diffToMon));
    const fri = new Date(mon);
    fri.setDate(mon.getDate() + 4);

    const fromInput = document.getElementById('rep-modal-date-from');
    if (fromInput) fromInput.value = mon.toISOString().split('T')[0];
    const toInput = document.getElementById('rep-modal-date-to');
    if (toInput) toInput.value = fri.toISOString().split('T')[0];

    reportBuilderSections = [];
    renderReportBuilderSections();

    const pasteText = document.getElementById('rep-telegram-paste-text');
    if (pasteText) pasteText.value = '';

    const summaryText = document.getElementById('rep-modal-summary');
    if (summaryText) summaryText.value = '';

    const statusEl = document.getElementById('rep-parse-status');
    if (statusEl) statusEl.textContent = 'Ready to parse';

    setReportDrawerMode('paste');
    AxisDrawer.open('modal-report');
};

window.setReportDrawerMode = function(mode) {
    const pasteTab = document.getElementById('rep-mode-paste-tab');
    const builderTab = document.getElementById('rep-mode-builder-tab');
    const pastePanel = document.getElementById('rep-panel-paste');

    if (mode === 'paste') {
        if (pasteTab) {
            pasteTab.className = 'flex-1 py-1.5 px-3 rounded-lg text-xs font-semibold bg-sky-500/20 text-sky-300 border border-sky-400/30 transition-all flex items-center justify-center gap-1.5';
        }
        if (builderTab) {
            builderTab.className = 'flex-1 py-1.5 px-3 rounded-lg text-xs font-semibold text-slate-400 hover:text-white transition-all flex items-center justify-center gap-1.5';
        }
        if (pastePanel) pastePanel.classList.remove('hidden');
    } else {
        if (builderTab) {
            builderTab.className = 'flex-1 py-1.5 px-3 rounded-lg text-xs font-semibold bg-sky-500/20 text-sky-300 border border-sky-400/30 transition-all flex items-center justify-center gap-1.5';
        }
        if (pasteTab) {
            pasteTab.className = 'flex-1 py-1.5 px-3 rounded-lg text-xs font-semibold text-slate-400 hover:text-white transition-all flex items-center justify-center gap-1.5';
        }
        if (pastePanel) pastePanel.classList.add('hidden');
    }
};

window.handleReportTypeChange = function() {
    syncReportFromBuilder();
};

window.loadSampleTelegramReport = function() {
    const sample = `Dear Bong and team,\nHere is my weekly report (10 Nov – 13 Nov 2026):\n\nI Axis system - Axis Sales\n1. Tested and Fixed on Team Lead Role:\n+ TL 04 — Watch the team on the Team Sales Dashboard and Salesperson Performance  : (100% | 0%)\n- P58 · Team Sales Dashboard\n- P59 · Salesperson Performance\n+ TL 05 — Read the branch pipeline and the forecast : (100% | 0%)\n- P62 · Branch Pipeline View : (100% | 0%)\n- P73 · Forecast : (100% | 0%)\n+ TL 06 — Read the hospitality and source dashboards : (100% | 0%)\n- P41 · Hospitality Sales Dashboard : (100% | 0%)\n- P42 · Inquiry / Booking Source Dashboard : (100% | 0%)\n+ TL 07 — View team targets read-only : (100% | 0%)\n- P60 · Targets : (100% | 0%)\n- P61 · Target Detail : (100% | 0%)\n+ TL 08 — Handle team client responses and read the commercial activity log : (100% | 0%)\n- P27 · Proposal Response Inbox : (100% | 0%)\n- P40 · Commercial Activity Log : (100% | 0%)\n+ TL 09 — Assignment rules and the reference states  : (100% | 0%)\n- P17 · Owner / Assignment Basic (Assignment Rules) : (100% | 0%)\n- P18 · Access & Upgrade States : (100% | 0%)\n2.Tested and fixed on Sales Manager role  \n+ SM 05 — Review a contract the Salesperson submitted  : (100% | 0%)\n- P33 · Contract / Agreement List : (100% | 0%)\n- P34 · Contract / Agreement Builder : (100% | 0%)\n- P23 · Internal Review / Approval Lite : (100% | 0%)\n+ SM 06 — Check contract document and renewal alerts  : (100% | 0%)\n- P35 · Contract PDF / Send / Sign Status : (100% | 0%)\n- P36 · Contract Renewal / Expiry Alerts\n+ SM 07 — Read corporate rate agreements : (100% | 0%)\n- P51 · Rate Agreement List : (100% | 0%)\n- P52 · Rate Agreement Detail : (100% | 0%)\n+ SM 08 — Create and approve a team sales target : (100% | 0%)\n- P60 · Targets : (100% | 0%)\n- P61 · Target Detail : (100% | 0%)\n+ SM 09 — File a forecast and read the KPI dashboard : (100% | 0%)\n - P73 · Forecast : (100% | 0%)\n - P67 · KPI Dashboard Advanced : (100% | 0%)\n+ SM 10 — Open reports from the hub and export summaries : (100% | 0%)\n - P68 · Reports Hub : (100% | 0%)\n - P69 · Lead Source Report : (100% | 0%)\n - P70 · Pipeline Report : (100% | 0%)\n\n🕔 Total time used: 30 hours\n\nThank you.`;

    const el = document.getElementById('rep-telegram-paste-text');
    if (el) el.value = sample;
    parseTelegramText();
};

window.parseTelegramText = function() {
    const text = document.getElementById('rep-telegram-paste-text')?.value || '';
    if (!text.trim()) {
        showToast('Paste telegram message text first');
        return;
    }

    const lines = text.split('\n').map(l => l.trim());

    // 1. Recipient
    const recipientMatch = text.match(/(Dear\s+[^,\n]+),?/i);
    if (recipientMatch) {
        const recEl = document.getElementById('rep-modal-recipient');
        if (recEl) recEl.value = recipientMatch[1].trim();
    }

    // 2. Report Type
    const isDaily = /daily\s+report/i.test(text);
    const typeEl = document.getElementById('rep-modal-type');
    if (typeEl) typeEl.value = isDaily ? 'Daily' : 'Weekly';

    // 3. Date Range
    const dateRangeMatch = text.match(/\((\d{1,2}\s+[A-Za-z]+)\s*[–\-—]\s*(\d{1,2}\s+[A-Za-z]+(?:\s+\d{4})?)\)/);
    if (dateRangeMatch) {
        const year = (text.match(/\b(202\d)\b/) || [null, new Date().getFullYear().toString()])[1];
        const fromStr = dateRangeMatch[1] + ' ' + year;
        const toStr = dateRangeMatch[2].includes(year) ? dateRangeMatch[2] : (dateRangeMatch[2] + ' ' + year);

        const dFrom = new Date(fromStr);
        const dTo = new Date(toStr);
        if (!isNaN(dFrom.getTime())) {
            const fEl = document.getElementById('rep-modal-date-from');
            if (fEl) fEl.value = dFrom.toISOString().split('T')[0];
        }
        if (!isNaN(dTo.getTime())) {
            const tEl = document.getElementById('rep-modal-date-to');
            if (tEl) tEl.value = dTo.toISOString().split('T')[0];
        }
    }

    // 4. Project / Module
    const projectMatch = text.match(/Axis\s+([A-Za-z0-9\s]+)/i);
    if (projectMatch) {
        const matchedName = projectMatch[0].trim();
        const modEl = document.getElementById('rep-modal-module');
        if (modEl) {
            // Find closest match in projects list
            for (let i = 0; i < modEl.options.length; i++) {
                if (modEl.options[i].value.toLowerCase().includes(matchedName.toLowerCase()) ||
                    matchedName.toLowerCase().includes(modEl.options[i].value.toLowerCase())) {
                    modEl.selectedIndex = i;
                    break;
                }
            }
        }
    }

    // 5. Total Hours
    const hoursMatch = text.match(/Total\s+time\s+used:\s*(\d+(?:\.\d+)?)\s*hours?/i);
    if (hoursMatch) {
        const hEl = document.getElementById('rep-modal-hours');
        if (hEl) hEl.value = hoursMatch[1];
    }

    // Check if any specific developer name is mentioned in the report
    const devSelect = document.getElementById('rep-modal-handler');
    if (devSelect && typeof HANDLERS !== 'undefined') {
        for (const h of HANDLERS) {
            if (h && text.toLowerCase().includes(h.toLowerCase())) {
                devSelect.value = h;
                break;
            }
        }
    }

    // 6. Parse Hierarchical Sections & Items
    reportBuilderSections = [];
    let currentSection = null;
    let currentItem = null;

    lines.forEach(line => {
        if (!line) return;

        // Check for Section Header: e.g. "1. Tested and Fixed on Team Lead Role:" or "2.Tested and fixed on Sales Manager role"
        const sectionMatch = line.match(/^(\d+[\.\)]\s*.+)/);
        if (sectionMatch) {
            currentSection = {
                title: sectionMatch[1].replace(/:$/, '').trim(),
                items: []
            };
            reportBuilderSections.push(currentSection);
            currentItem = null;
            return;
        }

        // Check for Test Item: starts with "+" e.g. "+ TL 04 — Watch the team on the Team Sales Dashboard... : (100% | 0%)"
        if (line.startsWith('+')) {
            if (!currentSection) {
                currentSection = { title: 'General Tasks & Verification', items: [] };
                reportBuilderSections.push(currentSection);
            }

            const cleanLine = line.substring(1).trim();
            // Match pattern: "CODE — TITLE : (DONE% | ISSUE%)"
            const itemMatch = cleanLine.match(/^([A-Z0-9\s-]+?)\s*[—\-–]\s*(.+?)(?:\s*:\s*\((.+?)\))?$/);

            let code = '';
            let title = cleanLine;
            let donePct = 100;
            let issuePct = 0;

            if (itemMatch) {
                code = itemMatch[1].trim();
                title = itemMatch[2].trim();
                if (itemMatch[3]) {
                    const pMatch = itemMatch[3].match(/(\d+)%\s*\|\s*(\d+)%/);
                    if (pMatch) {
                        donePct = parseInt(pMatch[1], 10);
                        issuePct = parseInt(pMatch[2], 10);
                    }
                }
            }

            currentItem = {
                code: code,
                title: title,
                done_percent: donePct,
                issue_percent: issuePct,
                sub_items: []
            };
            currentSection.items.push(currentItem);
            return;
        }

        // Check for Sub-item / Page: starts with "-" e.g. "- P58 · Team Sales Dashboard" or "- P62 · Branch Pipeline View : (100% | 0%)"
        if (line.startsWith('-') && currentItem) {
            const cleanSub = line.substring(1).trim();
            const subMatch = cleanSub.match(/^([A-Z0-9]+)\s*[·\-\–]\s*(.+?)(?:\s*:\s*\((.+?)\))?$/);
            if (subMatch) {
                currentItem.sub_items.push({
                    code: subMatch[1].trim(),
                    title: subMatch[2].trim()
                });
            } else {
                currentItem.sub_items.push({
                    code: '',
                    title: cleanSub
                });
            }
        }
    });

    renderReportBuilderSections();

    // Sync formatted Telegram summary
    const summaryEl = document.getElementById('rep-modal-summary');
    if (summaryEl) summaryEl.value = text;

    const totalItems = reportBuilderSections.reduce((sum, s) => sum + s.items.length, 0);
    const statusEl = document.getElementById('rep-parse-status');
    if (statusEl) {
        statusEl.innerHTML = `<span class="text-emerald-400 font-semibold"><i class="fa-solid fa-check-circle mr-1"></i>Parsed ${reportBuilderSections.length} roles, ${totalItems} test items</span>`;
    }

    showToast(`Parsed ${reportBuilderSections.length} roles and ${totalItems} test cases!`);
};

window.renderReportBuilderSections = function() {
    const container = document.getElementById('rep-sections-list');
    if (!container) return;

    if (!reportBuilderSections.length) {
        container.innerHTML = `
            <div class="p-4 rounded-xl border border-dashed border-white/10 text-center text-xs text-slate-500">
                No sections added yet. Paste a Telegram message above or click <strong>Add section / role</strong>.
            </div>
        `;
        return;
    }

    container.innerHTML = reportBuilderSections.map((s, sIdx) => `
        <div class="p-3.5 rounded-xl bg-slate-950/70 border border-white/[0.08] space-y-3">
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2 flex-1">
                    <span class="w-5 h-5 rounded-md bg-sky-500/20 text-sky-300 font-bold text-xs flex items-center justify-center">${sIdx + 1}</span>
                    <input type="text" value="${escapeHtml(s.title)}" oninput="reportBuilderSections[${sIdx}].title = this.value; syncReportFromBuilder();" placeholder="Role or section title (e.g. Tested on Team Lead Role)" class="axis-input text-xs font-semibold py-1">
                </div>
                <button type="button" onclick="removeReportSection(${sIdx})" class="text-rose-400 hover:text-rose-300 p-1 text-xs" title="Remove section">
                    <i class="fa-solid fa-trash-can"></i>
                </button>
            </div>

            <div class="space-y-2 pl-2 border-l-2 border-sky-500/30">
                ${(s.items || []).map((item, iIdx) => `
                    <div class="p-2.5 rounded-lg bg-white/[0.02] border border-white/[0.05] space-y-2">
                        <div class="flex items-center gap-2">
                            <input type="text" value="${escapeHtml(item.code || '')}" oninput="reportBuilderSections[${sIdx}].items[${iIdx}].code = this.value; syncReportFromBuilder();" placeholder="TL 04" class="axis-input text-xs font-mono w-20 py-1 text-center">
                            <input type="text" value="${escapeHtml(item.title || '')}" oninput="reportBuilderSections[${sIdx}].items[${iIdx}].title = this.value; syncReportFromBuilder();" placeholder="Task description..." class="axis-input text-xs flex-1 py-1">
                            <span class="text-[11px] font-mono text-emerald-300 px-2 py-0.5 rounded bg-emerald-500/10 border border-emerald-400/20 whitespace-nowrap">
                                (${item.done_percent || 100}% | ${item.issue_percent || 0}%)
                            </span>
                            <button type="button" onclick="removeReportItem(${sIdx}, ${iIdx})" class="text-slate-500 hover:text-rose-400 p-1 text-xs">
                                <i class="fa-solid fa-times"></i>
                            </button>
                        </div>

                        ${(item.sub_items || []).length > 0 ? `
                            <div class="flex flex-wrap items-center gap-1.5 pl-4">
                                ${(item.sub_items || []).map((sub, subIdx) => `
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-sky-500/10 border border-sky-400/20 text-[10px] text-slate-300">
                                        <strong class="text-sky-300 font-mono">${escapeHtml(sub.code || '')}</strong>
                                        <span>${escapeHtml(sub.title || '')}</span>
                                        <button type="button" onclick="removeReportSubItem(${sIdx}, ${iIdx}, ${subIdx})" class="hover:text-rose-400 ml-0.5">×</button>
                                    </span>
                                `).join('')}
                            </div>
                        ` : ''}

                        <div class="flex items-center gap-2 pl-4">
                            <button type="button" onclick="promptAddSubItem(${sIdx}, ${iIdx})" class="text-[10px] text-sky-400 hover:text-sky-300 font-medium flex items-center gap-1">
                                <i class="fa-solid fa-plus text-[9px]"></i>
                                <span>Add page / reference</span>
                            </button>
                        </div>
                    </div>
                `).join('')}

                <button type="button" onclick="addReportItem(${sIdx})" class="text-xs text-sky-400 hover:text-sky-300 font-medium flex items-center gap-1 pt-1">
                    <i class="fa-solid fa-plus text-[10px]"></i>
                    <span>Add test case / item</span>
                </button>
            </div>
        </div>
    `).join('');
};

window.addReportSection = function() {
    reportBuilderSections.push({
        title: `${reportBuilderSections.length + 1}. Tested and Fixed on Role:`,
        items: []
    });
    renderReportBuilderSections();
    syncReportFromBuilder();
};

window.removeReportSection = function(sIdx) {
    reportBuilderSections.splice(sIdx, 1);
    renderReportBuilderSections();
    syncReportFromBuilder();
};

window.addReportItem = function(sIdx) {
    if (!reportBuilderSections[sIdx]) return;
    reportBuilderSections[sIdx].items.push({
        code: `TC 0${reportBuilderSections[sIdx].items.length + 1}`,
        title: 'New test case description',
        done_percent: 100,
        issue_percent: 0,
        sub_items: []
    });
    renderReportBuilderSections();
    syncReportFromBuilder();
};

window.removeReportItem = function(sIdx, iIdx) {
    if (!reportBuilderSections[sIdx] || !reportBuilderSections[sIdx].items) return;
    reportBuilderSections[sIdx].items.splice(iIdx, 1);
    renderReportBuilderSections();
    syncReportFromBuilder();
};

window.promptAddSubItem = function(sIdx, iIdx) {
    const page = prompt('Enter page code & name (e.g. P58 · Team Sales Dashboard):', 'P01 · Dashboard');
    if (!page) return;
    const parts = page.split('·');
    const code = (parts.length > 1 ? parts[0] : '').trim();
    const title = (parts.length > 1 ? parts.slice(1).join('·') : page).trim();

    if (!reportBuilderSections[sIdx].items[iIdx].sub_items) {
        reportBuilderSections[sIdx].items[iIdx].sub_items = [];
    }
    reportBuilderSections[sIdx].items[iIdx].sub_items.push({ code, title });
    renderReportBuilderSections();
    syncReportFromBuilder();
};

window.removeReportSubItem = function(sIdx, iIdx, subIdx) {
    if (!reportBuilderSections[sIdx]?.items[iIdx]?.sub_items) return;
    reportBuilderSections[sIdx].items[iIdx].sub_items.splice(subIdx, 1);
    renderReportBuilderSections();
    syncReportFromBuilder();
};

window.syncReportFromBuilder = function() {
    const summaryEl = document.getElementById('rep-modal-summary');
    if (!summaryEl) return;

    const recipient = document.getElementById('rep-modal-recipient')?.value || 'Dear Bong and team';
    const type = document.getElementById('rep-modal-type')?.value || 'Weekly';
    const dFrom = document.getElementById('rep-modal-date-from')?.value;
    const dTo = document.getElementById('rep-modal-date-to')?.value;
    const moduleName = document.getElementById('rep-modal-module')?.value || 'Axis Sales';
    const hours = document.getElementById('rep-modal-hours')?.value || '30';

    let periodStr = '';
    if (dFrom && dTo) {
        periodStr = ` (${formatShortDate(dFrom)} – ${formatShortDate(dTo)} ${new Date().getFullYear()})`;
    } else {
        periodStr = ` (${new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })})`;
    }

    let out = `${recipient},\nHere is my ${type.toLowerCase()} report${periodStr}:\n\n`;
    out += `I Axis system - ${moduleName}\n`;

    reportBuilderSections.forEach(s => {
        out += `${s.title}\n`;
        (s.items || []).forEach(it => {
            const codePart = it.code ? `${it.code} — ` : '';
            out += `+ ${codePart}${it.title} : (${it.done_percent || 100}% | ${it.issue_percent || 0}%)\n`;
            (it.sub_items || []).forEach(sub => {
                const subCode = sub.code ? `${sub.code} · ` : '';
                out += `- ${subCode}${sub.title}\n`;
            });
        });
    });

    out += `\n🕔 Total time used: ${hours} hours\n\nThank you.`;
    summaryEl.value = out;
};

// =====================================================================
// REPORT PERSISTENCE & ACTIONS
// =====================================================================
window.handleSaveReport = function(e) {
    e.preventDefault();
    AxisDrawer.submit('modal-report', async () => {
        const id = document.getElementById('rep-modal-id')?.value;
        const payload = {
            report_type: document.getElementById('rep-modal-type').value,
            handler_name: document.getElementById('rep-modal-handler')?.value || undefined,
            recipient: document.getElementById('rep-modal-recipient').value.trim() || 'Dear Bong and team',
            module_name: document.getElementById('rep-modal-module').value,
            date_from: document.getElementById('rep-modal-date-from').value || null,
            date_to: document.getElementById('rep-modal-date-to').value || null,
            summary: document.getElementById('rep-modal-summary').value.trim(),
            content_json: reportBuilderSections.length > 0 ? { sections: reportBuilderSections } : null,
            blocker: document.getElementById('rep-modal-blocker').value.trim() || 'None',
            hours_logged: parseFloat(document.getElementById('rep-modal-hours').value) || 8
        };

        if (id) {
            // Update existing
            const res = await AxisAPI.request(AxisAPI.url('reports.update', id), 'PATCH', payload);
            const idx = ctoReports.findIndex(r => r.id == id);
            if (idx !== -1) ctoReports[idx] = res.data;
            showToast('Report updated successfully');
        } else {
            // Create new
            const res = await AxisAPI.request(AxisAPI.url('reports.store'), 'POST', payload);
            ctoReports.unshift(res.data);
            showToast('Weekly report submitted!');
        }

        closeModal('modal-report');
        renderReportsPage();
        updateDynamicHeader(activePageId);
    });
};

window.openReportDetail = function(id) {
    const report = ctoReports.find(r => r.id == id);
    if (!report) return;

    activeReportDetailId = id;

    const typeBadge = document.getElementById('repview-type-badge');
    if (typeBadge) {
        typeBadge.textContent = report.type;
        typeBadge.className = report.type === 'Weekly'
            ? 'px-2.5 py-1 rounded-md text-xs font-semibold bg-indigo-500/20 text-indigo-300 border border-indigo-400/30'
            : 'px-2.5 py-1 rounded-md text-xs font-semibold bg-sky-500/20 text-sky-300 border border-sky-400/30';
    }

    const modBadge = document.getElementById('repview-module-badge');
    if (modBadge) modBadge.textContent = report.module;

    const periodText = document.getElementById('repview-period-text');
    if (periodText) {
        periodText.textContent = (report.date_from && report.date_to)
            ? `${formatShortDate(report.date_from)} – ${formatShortDate(report.date_to)}`
            : (report.date || 'Today');
    }

    const hoursText = document.getElementById('repview-hours-text');
    if (hoursText) hoursText.textContent = `${report.hours}h`;

    const handlerText = document.getElementById('repview-handler-text');
    if (handlerText) handlerText.textContent = report.handler || 'Unknown';

    const blockersText = document.getElementById('repview-blockers-text');
    if (blockersText) blockersText.textContent = report.blocker || 'None';

    const rawTextEl = document.getElementById('repview-raw-text');
    if (rawTextEl) rawTextEl.textContent = report.summary || '';

    // Render structured sections
    const container = document.getElementById('repview-sections-container');
    if (container) {
        const sections = report.content_json?.sections || [];
        if (sections.length > 0) {
            container.innerHTML = sections.map((s, sIdx) => `
                <div class="p-4 rounded-xl bg-slate-950/70 border border-white/[0.08] space-y-3">
                    <div class="flex items-center gap-2">
                        <span class="w-6 h-6 rounded-lg bg-sky-500/20 text-sky-300 font-bold text-xs flex items-center justify-center">${sIdx + 1}</span>
                        <h3 class="text-xs font-bold text-white tracking-wide">${escapeHtml(s.title)}</h3>
                    </div>
                    <div class="space-y-2">
                        ${(s.items || []).map(it => `
                            <div class="p-3 rounded-lg bg-white/[0.02] border border-white/[0.05] space-y-2">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex items-start gap-2">
                                        ${it.code ? `<span class="px-2 py-0.5 rounded text-[11px] font-mono font-bold bg-sky-500/20 text-sky-300 border border-sky-400/30 whitespace-nowrap">${escapeHtml(it.code)}</span>` : ''}
                                        <div class="text-xs text-slate-200 font-medium leading-relaxed">${escapeHtml(it.title)}</div>
                                    </div>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-mono font-semibold bg-emerald-500/15 text-emerald-300 border border-emerald-400/30 whitespace-nowrap">
                                        ${it.done_percent || 100}% | ${it.issue_percent || 0}%
                                    </span>
                                </div>
                                ${(it.sub_items || []).length > 0 ? `
                                    <div class="flex flex-wrap gap-1.5 pl-6 pt-1">
                                        ${(it.sub_items || []).map(sub => `
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-slate-900 border border-white/[0.08] text-[11px] text-slate-300">
                                                <strong class="text-sky-300 font-mono">${escapeHtml(sub.code || '')}</strong>
                                                <span>${escapeHtml(sub.title || '')}</span>
                                            </span>
                                        `).join('')}
                                    </div>
                                ` : ''}
                            </div>
                        `).join('')}
                    </div>
                </div>
            `).join('');
        } else {
            container.innerHTML = `
                <div class="p-4 rounded-xl bg-slate-950/70 border border-white/[0.08] space-y-2">
                    <div class="text-xs font-semibold text-slate-400">Report Summary</div>
                    <div class="text-xs text-slate-200 whitespace-pre-wrap leading-relaxed">${escapeHtml(report.summary)}</div>
                </div>
            `;
        }
    }

    AxisDrawer.open('modal-report-view');
};

window.toggleRawTelegramPreview = function() {
    const rawEl = document.getElementById('repview-raw-text');
    const hintEl = document.getElementById('repview-raw-hint');
    const chevronEl = document.getElementById('repview-raw-chevron');
    if (!rawEl) return;
    const isHidden = rawEl.classList.contains('hidden');
    rawEl.classList.toggle('hidden', !isHidden);
    if (hintEl) hintEl.textContent = isHidden ? 'Hide text' : 'Show text';
    if (chevronEl) chevronEl.className = isHidden ? 'fa-solid fa-chevron-up text-[10px]' : 'fa-solid fa-chevron-down text-[10px]';
};

window.copyActiveReportTelegram = function() {
    if (!activeReportDetailId) return;
    copyReportTelegramById(activeReportDetailId);
};

window.copyReportTelegramById = function(id, e) {
    if (e) e.stopPropagation();
    const report = ctoReports.find(r => r.id == id);
    if (!report) return;

    const text = report.summary || '';
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(() => {
            showToast('Copied Telegram report to clipboard!');
        }).catch(() => fallbackCopy(text));
    } else {
        fallbackCopy(text);
    }
};

function fallbackCopy(text) {
    const ta = document.createElement('textarea');
    ta.value = text;
    document.body.appendChild(ta);
    ta.select();
    try {
        document.execCommand('copy');
        showToast('Copied Telegram report to clipboard!');
    } catch (e) {
        showToast('Failed to copy');
    }
    document.body.removeChild(ta);
}

window.copyReportDrawerTelegram = function() {
    const summary = document.getElementById('rep-modal-summary')?.value || '';
    if (!summary.trim()) {
        showToast('No report text to copy');
        return;
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(summary).then(() => {
            showToast('Copied Telegram report to clipboard!');
        }).catch(() => fallbackCopy(summary));
    } else {
        fallbackCopy(summary);
    }
};

window.editActiveReport = function() {
    if (!activeReportDetailId) return;
    closeModal('modal-report-view');
    editReportById(activeReportDetailId);
};

window.editReportById = function(id, e) {
    if (e) e.stopPropagation();
    const report = ctoReports.find(r => r.id == id);
    if (!report) return;

    const idInput = document.getElementById('rep-modal-id');
    if (idInput) idInput.value = report.id;

    AxisDrawer.configure('modal-report', {
        title: 'Edit report',
        breadcrumb: 'Reports',
        description: 'Update structured test cases, hours or notes.',
        submitLabel: 'Save changes'
    });

    const typeEl = document.getElementById('rep-modal-type');
    if (typeEl) typeEl.value = report.type;

    const handlerEl = document.getElementById('rep-modal-handler');
    if (handlerEl && report.handler) handlerEl.value = report.handler;

    const recEl = document.getElementById('rep-modal-recipient');
    if (recEl) recEl.value = report.recipient || 'Dear Bong and team';

    const modEl = document.getElementById('rep-modal-module');
    if (modEl) modEl.value = report.module;

    const hoursEl = document.getElementById('rep-modal-hours');
    if (hoursEl) hoursEl.value = report.hours;

    const blockerEl = document.getElementById('rep-modal-blocker');
    if (blockerEl) blockerEl.value = report.blocker || 'None';

    const fromInput = document.getElementById('rep-modal-date-from');
    if (fromInput) fromInput.value = report.date_from || '';

    const toInput = document.getElementById('rep-modal-date-to');
    if (toInput) toInput.value = report.date_to || '';

    const summaryText = document.getElementById('rep-modal-summary');
    if (summaryText) summaryText.value = report.summary || '';

    const pasteText = document.getElementById('rep-telegram-paste-text');
    if (pasteText) pasteText.value = report.summary || '';

    reportBuilderSections = report.content_json?.sections || [];
    renderReportBuilderSections();

    setReportDrawerMode('builder');
    AxisDrawer.open('modal-report');
};

window.deleteActiveReport = function() {
    if (!activeReportDetailId) return;
    deleteReportById(activeReportDetailId);
};

window.deleteReportById = async function(id, e) {
    if (e) e.stopPropagation();
    const report = ctoReports.find(r => r.id == id);
    if (!report) return;

    const confirmed = await AxisDrawer.confirm({
        title: 'Delete this report?',
        breadcrumb: 'Reports',
        message: `${report.type} report for ${report.module} by ${report.handler}`,
        confirmLabel: 'Delete report'
    });
    if (!confirmed) return;

    try {
        await AxisAPI.request(AxisAPI.url('reports.destroy', id), 'DELETE');
        ctoReports = ctoReports.filter(r => r.id != id);
        if (activeReportDetailId == id) {
            closeModal('modal-report-view');
        }
        renderReportsPage();
        updateDynamicHeader(activePageId);
        showToast('Report deleted');
    } catch (err) {
        AxisAPI.fail(err);
    }
};

// =====================================================================
// PAGE 6: TEAM & LOGIN MANAGEMENT
// =====================================================================
function directoryOptions(items, selectedId, includeNone = false) {
    const none = includeNone
        ? `<option value=""${selectedId == null ? ' selected' : ''}>No department</option>`
        : '';
    const selected = Number(selectedId);

    return none + items.map(item => {
        const isSelected = Number(item.id) === selected;
        const disabled = item.isActive === false && !isSelected ? ' disabled' : '';

        return `<option value="${item.id}"${isSelected ? ' selected' : ''}${disabled}>${escapeHtml(item.name)}</option>`;
    }).join('');
}

function renderTeamDirectory() {
    const departmentsBody = document.getElementById('departments-tbody');
    if (departmentsBody) {
        departmentsBody.innerHTML = departments.length
            ? departments.map(department => `
                <tr>
                    <td class="py-3 px-3">
                        <div class="text-sm font-semibold text-white">${escapeHtml(department.name)}${department.isActive === false ? '<span class="ml-2 text-xs font-normal text-slate-500">Inactive</span>' : ''}</div>
                        ${department.description ? `<div class="text-xs text-slate-500">${escapeHtml(department.description)}</div>` : ''}
                    </td>
                    <td class="py-3 px-3 text-center text-xs text-slate-300">${department.memberCount ?? 0}</td>
                    <td class="py-3 px-3 text-right whitespace-nowrap space-x-1.5">
                        <button type="button" onclick="openDepartmentEdit(${department.id})" class="axis-row-btn" title="Edit department" aria-label="Edit ${escapeHtml(department.name)}">
                            <i class="fa-solid fa-pen text-xs" aria-hidden="true"></i>
                        </button>
                        <button type="button" onclick="deleteDepartment(${department.id})" class="axis-row-btn axis-row-btn--danger" title="Remove department" aria-label="Remove ${escapeHtml(department.name)}">
                            <i class="fa-solid fa-trash-can text-xs" aria-hidden="true"></i>
                        </button>
                    </td>
                </tr>
            `).join('')
            : workspaceRow(3, 'No departments have been added yet.');
    }

    const positionsBody = document.getElementById('positions-tbody');
    if (positionsBody) {
        positionsBody.innerHTML = positions.length
            ? positions.map(position => `
                <tr>
                    <td class="py-3 px-3">
                        <div class="text-sm font-semibold text-white">${escapeHtml(position.name)}${position.isActive === false ? '<span class="ml-2 text-xs font-normal text-slate-500">Inactive</span>' : ''}</div>
                        ${position.description ? `<div class="text-xs text-slate-500">${escapeHtml(position.description)}</div>` : ''}
                    </td>
                    <td class="py-3 px-3 text-center text-xs text-slate-300">${position.memberCount ?? 0}</td>
                    <td class="py-3 px-3 text-right whitespace-nowrap space-x-1.5">
                        <button type="button" onclick="openPositionEdit(${position.id})" class="axis-row-btn" title="Edit position" aria-label="Edit ${escapeHtml(position.name)}">
                            <i class="fa-solid fa-pen text-xs" aria-hidden="true"></i>
                        </button>
                        <button type="button" onclick="deletePosition(${position.id})" class="axis-row-btn axis-row-btn--danger" title="Remove position" aria-label="Remove ${escapeHtml(position.name)}">
                            <i class="fa-solid fa-trash-can text-xs" aria-hidden="true"></i>
                        </button>
                    </td>
                </tr>
            `).join('')
            : workspaceRow(3, 'No positions have been added yet.');
    }

    const createDepartmentSelect = document.getElementById('tm-dept-input');
    if (createDepartmentSelect) createDepartmentSelect.innerHTML = directoryOptions(departments, null, true);

    const editDepartmentSelect = document.getElementById('tm-edit-dept');
    if (editDepartmentSelect) editDepartmentSelect.innerHTML = directoryOptions(departments, null, true);

    const createPositionSelect = document.getElementById('tm-position-input');
    if (createPositionSelect) createPositionSelect.innerHTML = directoryOptions(positions, null);

    const editPositionSelect = document.getElementById('tm-edit-position');
    if (editPositionSelect) editPositionSelect.innerHTML = directoryOptions(positions, null);
}

window.renderTeamTable = function() {
    renderTeamDirectory();

    const tbody = document.getElementById('team-tbody');
    if (!tbody) return;

    tbody.innerHTML = teamMembers.map((m, i) => `
        <tr>
            <td class="py-3.5 px-3 text-center font-mono text-slate-400">${i + 1}</td>
            <td class="py-3.5 px-3">
                <div class="font-medium text-white whitespace-nowrap">${escapeHtml(m.name)}</div>
                ${m.title ? `<div class="text-xs text-slate-500 mt-0.5">${escapeHtml(m.title)}</div>` : ''}
            </td>
            <td class="py-3.5 px-3 text-slate-300">${m.departmentName || m.department ? escapeHtml(m.departmentName || m.department) : '<span class="text-slate-500">—</span>'}</td>
            <td class="py-3.5 px-3">
                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold border ${m.position === 'leader' ? 'bg-sky-500/15 text-sky-200 border-sky-400/30' : 'bg-white/5 text-slate-300 border-white/10'}">${escapeHtml(m.positionName || (m.position === 'leader' ? 'Leader' : 'Developer'))}</span>
            </td>
            <td class="py-3.5 px-3 text-xs text-slate-400">
                <div class="max-w-[220px] truncate" title="${escapeHtml(m.description || '')}">${m.description ? escapeHtml(m.description) : '<span class="text-slate-500">—</span>'}</div>
            </td>
            <td class="py-3.5 px-3 font-mono text-slate-400">${m.email ? escapeHtml(m.email) : '<span class="text-slate-500">—</span>'}</td>
            <td class="py-3.5 px-3 text-center">
                <button type="button" onclick="toggleEmployeeStatus(${m.id})" title="${m.status === 'inactive' ? 'Mark as active' : 'Mark as inactive'}"
                    class="px-2.5 py-0.5 rounded-full text-xs font-semibold border ${m.status === 'inactive' ? 'bg-white/5 text-slate-400 border-white/10' : 'bg-emerald-500/15 text-emerald-300 border-emerald-400/30'}">
                    ${m.status === 'inactive' ? 'Inactive' : 'Active'}
                </button>
            </td>
            <td class="py-3.5 px-3 text-center whitespace-nowrap space-x-1.5">
                <button type="button" onclick="openTeamMemberEdit(${m.id})" class="px-2.5 py-0.5 rounded-full text-xs font-semibold border bg-sky-500/15 text-sky-200 border-sky-400/30">Edit</button>
                ${currentUser?.id !== m.id ? `
                <button type="button" onclick="deleteTeamMember(${m.id})" class="px-2.5 py-0.5 rounded-full text-xs font-semibold border bg-rose-500/15 text-rose-300 border-rose-400/30">Remove</button>` : ''}
            </td>
        </tr>
    `).join('');

};

let editingTeamMemberId = null;

window.openTeamMemberEdit = function(id) {
    const m = teamMembers.find(x => x.id === id);
    if (!m) return;
    editingTeamMemberId = id;
    document.getElementById('tm-edit-dept').value = m.departmentId ?? '';
    document.getElementById('tm-edit-position').value = m.positionId ?? '';
    document.getElementById('tm-edit-status').value = m.status || 'active';
    document.getElementById('tm-edit-desc').value = m.description || '';
    AxisDrawer.open('drawer-team-member-edit', { title: `Edit ${m.name}` });
};

window.handleUpdateTeamMember = function(e) {
    e.preventDefault();
    const m = teamMembers.find(x => x.id === editingTeamMemberId);
    if (!m) return;
    AxisDrawer.submit('drawer-team-member-edit', async () => {
        const id = await AxisAPI.idOf(m);
        const res = await AxisAPI.request(AxisAPI.url('team.update', id), 'PATCH', {
            department_id: Number(document.getElementById('tm-edit-dept').value) || null,
            position_id: Number(document.getElementById('tm-edit-position').value) || null,
            status: document.getElementById('tm-edit-status').value,
            description: document.getElementById('tm-edit-desc').value.trim() || null
        });
        Object.assign(m, res.data);
        closeModal('drawer-team-member-edit');
        renderTeamTable();
        showToast(`Saved ${m.name}`);
    });
};

/** Switch an employee between active and inactive (inactive people can't be given new tasks). */
window.toggleEmployeeStatus = function(id) {
    const m = teamMembers.find(x => x.id === id);
    if (!m) return;
    const previous = m.status;
    m.status = previous === 'inactive' ? 'active' : 'inactive';
    renderTeamTable();
    showToast(`${m.name} is now ${m.status}`);

    AxisAPI.idOf(m)
        .then(realId => AxisAPI.request(AxisAPI.url('team.update', realId), 'PATCH', { status: m.status }))
        .then(res => { m.status = res.data.status; renderTeamTable(); })
        .catch(err => AxisAPI.fail(err, () => { m.status = previous; renderTeamTable(); }));
};

window.toggleMemberLoginStatus = function(id) {
    const m = teamMembers.find(x => x.id === id);
    if (m) {
        m.active = !m.active;
        renderTeamTable();
        showToast(`${m.name} ${m.active ? 'can sign in again' : 'can no longer sign in'}`);

        AxisAPI.idOf(m)
            .then(realId => AxisAPI.request(AxisAPI.url('team.toggle-login', realId), 'PATCH'))
            .then(res => { m.active = res.data.active; renderTeamTable(); })
            .catch(err => AxisAPI.fail(err, () => { m.active = !m.active; renderTeamTable(); }));
    }
};

window.handleSaveTeamMember = function(e) {
    e.preventDefault();
    AxisDrawer.submit('modal-team-member', async () => {
        const res = await AxisAPI.request(AxisAPI.url('team.store'), 'POST', {
            name: document.getElementById('tm-name-input').value.trim(),
            job_title: document.getElementById('tm-pos-input').value.trim(),
            department_id: Number(document.getElementById('tm-dept-input').value) || null,
            position_id: Number(document.getElementById('tm-position-input').value) || null,
            status: document.getElementById('tm-status-input').value,
            description: document.getElementById('tm-desc-input').value.trim() || null,
            email: document.getElementById('tm-email-input').value.trim()
        });
        teamMembers.push(res.data);
        if (!HANDLERS.includes(res.data.name)) HANDLERS.push(res.data.name);
        ['tm-name-input', 'tm-pos-input', 'tm-dept-input', 'tm-desc-input', 'tm-email-input'].forEach(id => { document.getElementById(id).value = ''; });
        closeModal('modal-team-member');
        renderTeamTable();
        updateDynamicHeader(activePageId);
        showToast(`Added ${res.data.name} to the team`);
    });
};

// ---------------------------------------------------------------------
// Department and position directory CRUD
// ---------------------------------------------------------------------
let editingDepartmentId = null;
let editingPositionId = null;

window.handleCreateDepartment = async function(e) {
    e.preventDefault();
    const form = e.target;

    try {
        const res = await AxisAPI.request(AxisAPI.url('departments.store'), 'POST', {
            name: document.getElementById('new-department-name').value.trim(),
            description: document.getElementById('new-department-description').value.trim() || null
        });
        departments.push(res.data);
        departments.sort((a, b) => a.name.localeCompare(b.name));
        form.reset();
        closeModal('modal-department-create');
        renderTeamTable();
        showToast(`Added ${res.data.name}`);
    } catch (error) {
        AxisAPI.fail(error);
    }
};

window.openDepartmentEdit = function(id) {
    const department = departments.find(item => item.id === id);
    if (!department) return;

    editingDepartmentId = id;
    document.getElementById('dept-edit-name').value = department.name || '';
    document.getElementById('dept-edit-description').value = department.description || '';
    document.getElementById('dept-edit-active').checked = department.isActive !== false;
    AxisDrawer.open('drawer-department-edit', { title: `Edit ${department.name}` });
};

window.handleUpdateDepartment = function(e) {
    e.preventDefault();
    const index = departments.findIndex(item => item.id === editingDepartmentId);
    if (index === -1) return;

    AxisDrawer.submit('drawer-department-edit', async () => {
        const res = await AxisAPI.request(AxisAPI.url('departments.update', editingDepartmentId), 'PATCH', {
            name: document.getElementById('dept-edit-name').value.trim(),
            description: document.getElementById('dept-edit-description').value.trim() || null,
            is_active: document.getElementById('dept-edit-active').checked
        });
        departments[index] = res.data;
        departments.sort((a, b) => a.name.localeCompare(b.name));
        closeModal('drawer-department-edit');
        renderTeamTable();
        showToast(`Saved ${res.data.name}`);
    });
};

window.deleteDepartment = async function(id) {
    const department = departments.find(item => item.id === id);
    if (!department) return;

    if (department.memberCount > 0) {
        showToast('Move or unlink this department\'s team members before deleting it.');
        return;
    }

    if (!window.confirm(`Delete ${department.name}?`)) return;

    try {
        await AxisAPI.request(AxisAPI.url('departments.destroy', id), 'DELETE');
        departments = departments.filter(item => item.id !== id);
        renderTeamTable();
        showToast(`Deleted ${department.name}`);
    } catch (error) {
        AxisAPI.fail(error, 'Could not delete the department.');
    }
};

window.handleCreatePosition = async function(e) {
    e.preventDefault();
    const form = e.target;

    try {
        const res = await AxisAPI.request(AxisAPI.url('positions.store'), 'POST', {
            name: document.getElementById('new-position-name').value.trim(),
            description: document.getElementById('new-position-description').value.trim() || null,
            is_leader: document.getElementById('new-position-leader').checked
        });
        positions.push(res.data);
        form.reset();
        closeModal('modal-position-create');
        renderTeamTable();
        showToast(`Added ${res.data.name}`);
    } catch (error) {
        AxisAPI.fail(error);
    }
};

window.openPositionEdit = function(id) {
    const position = positions.find(item => item.id === id);
    if (!position) return;

    editingPositionId = id;
    document.getElementById('position-edit-name').value = position.name || '';
    document.getElementById('position-edit-description').value = position.description || '';
    document.getElementById('position-edit-leader').checked = position.isLeader === true;
    document.getElementById('position-edit-active').checked = position.isActive !== false;
    AxisDrawer.open('drawer-position-edit', { title: `Edit ${position.name}` });
};

window.handleUpdatePosition = function(e) {
    e.preventDefault();
    const index = positions.findIndex(item => item.id === editingPositionId);
    if (index === -1) return;

    AxisDrawer.submit('drawer-position-edit', async () => {
        const res = await AxisAPI.request(AxisAPI.url('positions.update', editingPositionId), 'PATCH', {
            name: document.getElementById('position-edit-name').value.trim(),
            description: document.getElementById('position-edit-description').value.trim() || null,
            is_leader: document.getElementById('position-edit-leader').checked,
            is_active: document.getElementById('position-edit-active').checked
        });
        positions[index] = res.data;
        closeModal('drawer-position-edit');
        renderTeamTable();
        showToast(`Saved ${res.data.name}`);
    });
};

window.deletePosition = async function(id) {
    const position = positions.find(item => item.id === id);
    if (!position) return;

    if (position.memberCount > 0) {
        showToast('Move or unlink this position\'s team members before deleting it.');
        return;
    }

    if (!window.confirm(`Delete ${position.name}?`)) return;

    try {
        await AxisAPI.request(AxisAPI.url('positions.destroy', id), 'DELETE');
        positions = positions.filter(item => item.id !== id);
        renderTeamTable();
        showToast(`Deleted ${position.name}`);
    } catch (error) {
        AxisAPI.fail(error, 'Could not delete the position.');
    }
};

// ---------------------------------------------------------------------
// Remove a member and keep directory counts in sync
// ---------------------------------------------------------------------
function adjustDirectoryCount(items, id, delta) {
    const item = items.find(entry => entry.id === id);
    if (item) item.memberCount = Math.max(0, (item.memberCount ?? 0) + delta);
}

window.deleteTeamMember = async function(id) {
    const member = teamMembers.find(item => item.id === id);
    if (!member) return;

    if (!window.confirm(`Remove ${member.name} from the team?`)) return;

    try {
        const res = await AxisAPI.request(AxisAPI.url('team.destroy', id), 'DELETE');
        adjustDirectoryCount(departments, res.data.departmentId, -1);
        adjustDirectoryCount(positions, res.data.positionId, -1);
        teamMembers = teamMembers.filter(item => item.id !== id);
        const handlerIndex = HANDLERS.indexOf(member.name);
        if (handlerIndex !== -1) HANDLERS.splice(handlerIndex, 1);
        renderTeamTable();
        showToast(res.data.message);
    } catch (error) {
        AxisAPI.fail(error);
    }
};

// ---------------------------------------------------------------------
// Secure password setup / reset — the password is never returned or shown
// ---------------------------------------------------------------------
let passwordMemberId = null;

window.openTeamMemberPasswordReset = function(id, needsEmail = false) {
    const member = teamMembers.find(item => item.id === id);
    if (!member) return;

    passwordMemberId = id;
    document.getElementById('tm-login-email-field').classList.toggle('hidden', !needsEmail);
    document.getElementById('tm-login-email').value = member.email || '';
    document.getElementById('tm-login-password').value = '';
    document.getElementById('tm-login-password-confirmation').value = '';
    AxisDrawer.open('drawer-team-member-password', {
        title: needsEmail ? `Set up ${member.name}'s login` : `Reset ${member.name}'s password`
    });
};

window.handleResetTeamMemberPassword = function(e) {
    e.preventDefault();
    const member = teamMembers.find(item => item.id === passwordMemberId);
    if (!member) return;

    AxisDrawer.submit('drawer-team-member-password', async () => {
        const payload = {
            password: document.getElementById('tm-login-password').value,
            password_confirmation: document.getElementById('tm-login-password-confirmation').value
        };
        const emailField = document.getElementById('tm-login-email-field');

        if (!emailField.classList.contains('hidden')) {
            payload.email = document.getElementById('tm-login-email').value.trim();
        }

        const res = await AxisAPI.request(AxisAPI.url('team.password', member.id), 'PATCH', payload);
        member.email = res.data.username;
        member.username = res.data.username;
        member.hasAccount = true;
        member.active = res.data.active;
        closeModal('drawer-team-member-password');
        renderTeamTable();
        showToast(res.data.message);
    });
};

// =====================================================================
// MODALS
// =====================================================================
window.openModal = function(id) {
    const el = document.getElementById(id);
    if (!el) return;
    if (id === 'modal-create-task') syncNewTaskModuleSelect();
    // Universal drawers manage their own transitions, focus, scroll lock and validation reset
    if (el.hasAttribute('data-axis-drawer')) {
        AxisDrawer.open(el);
        return;
    }
    el.classList.remove('hidden');
    el.classList.add('flex');
    el.querySelector('[data-autofocus]')?.focus();
};

window.closeModal = function(id) {
    const el = document.getElementById(id);
    if (!el) return;
    if (el.hasAttribute('data-axis-drawer')) {
        AxisDrawer.close(el);
        return;
    }
    if (el.classList.contains('hidden')) return;
    el.classList.add('hidden');
    el.classList.remove('flex');
};

window.showToast = function(msg) {
    const box = document.getElementById('toast-container');
    if (!box) return;
    const el = document.createElement('div');
    el.className = 'ent-card px-3.5 py-2 text-xs font-medium text-sky-200 border border-sky-400/35 shadow-xl flex items-center gap-2 pointer-events-auto';
    el.innerHTML = `<span class="w-2 h-2 rounded-full bg-emerald-400"></span><span>${escapeHtml(msg)}</span>`;
    box.appendChild(el);
    setTimeout(() => {
        el.style.opacity = '0';
        el.style.transition = 'opacity 0.25s ease';
        setTimeout(() => el.remove(), 260);
    }, 2400);
};

window.exportProjectsCSV = function() {
    const rows = [
        ['Code', 'Module Name', 'Product Domain', 'Manager', 'Deadline', 'Status', 'Completion %']
    ];
    projects.forEach(p => {
        rows.push([
            p.code,
            `"${p.name}"`,
            `"${p.product}"`,
            `"${p.manager}"`,
            p.deadline,
            p.status,
            computeProjectCompletion(p) + '%'
        ]);
    });
    const csv = rows.map(r => r.join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'Axis_Suite_Projects_Export.csv';
    a.click();
    URL.revokeObjectURL(url);
    showToast('Downloaded the project list');
};

// =====================================================================
// SALES WORKSPACE ACCESS PAGES — roles, queues, sharing and P18
// =====================================================================
let recordShareRecords = null;
let recordShareUsers = null;

function workspaceRow(colspan, message, loading = false) {
    return `<tr><td colspan="${colspan}" class="px-3 py-8 text-center text-xs ${loading ? 'text-slate-400' : 'text-slate-500'}">${escapeHtml(message)}</td></tr>`;
}

function workspacePermissionLabel(key) {
    return AXIS_BOOT.permissionLabels?.[key] || key;
}

function workspaceReadableState(state) {
    return AXIS_BOOT.upgradeStateLabels?.[state] || state;
}

function workspaceSelectedValues(select) {
    return Array.from(select?.selectedOptions || []).map(option => Number(option.value)).filter(Number.isInteger);
}

function handleWorkspaceActionError(error, fallback) {
    const message = error?.status === 403
        ? 'You do not have permission to make that change.'
        : (error?.message || fallback);
    showToast(message);
}

window.toggleWorkspaceForm = function(formId) {
    const form = document.getElementById(formId);
    if (form) form.classList.toggle('hidden');
};

function renderRolesPage() {
    const rolesBody = document.getElementById('roles-tbody');
    if (rolesBody) {
        rolesBody.innerHTML = workspaceRoles.length
            ? workspaceRoles.map(role => {
                const permissions = (role.permissions || []).map(permission =>
                    `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] border border-sky-400/20 bg-sky-400/10 text-sky-200">${escapeHtml(workspacePermissionLabel(permission))}</span>`
                ).join('');

                return `<tr>
                    <td class="py-3 px-3 align-top">
                        <div class="text-sm font-semibold text-white">${escapeHtml(role.label)}</div>
                        <div class="text-xs text-slate-500">${escapeHtml(role.description || '')}</div>
                    </td>
                    <td class="py-3 px-3">${permissions || '<span class="text-xs text-slate-500">No permissions</span>'}</td>
                </tr>`;
            }).join('')
            : workspaceRow(2, 'No workspace roles have been configured.');
    }

    const teamsBody = document.getElementById('teams-tbody');
    if (teamsBody) {
        teamsBody.innerHTML = workspaceTeams.length
            ? workspaceTeams.map(team => {
                const members = (team.members || []).map(member => escapeHtml(member.name)).join(', ') || '—';
                const actions = canManageAdministration()
                    ? `<button type="button" onclick="deleteWorkspaceTeam(${team.id})" class="text-xs font-semibold text-rose-300 hover:text-rose-200">Remove</button>`
                    : '—';

                return `<tr>
                    <td class="py-3 px-3 align-top">
                        <div class="text-sm font-semibold text-white">${escapeHtml(team.name)}</div>
                        <div class="text-xs text-slate-500">${escapeHtml(team.description || '')}</div>
                    </td>
                    <td class="py-3 px-3 text-xs text-slate-300">${members}</td>
                    <td class="py-3 px-3 text-right">${actions}</td>
                </tr>`;
            }).join('')
            : workspaceRow(3, 'No teams have been created yet.');
    }

    const userRolesBody = document.getElementById('user-roles-tbody');
    if (userRolesBody) {
        userRolesBody.innerHTML = teamMembers.length
            ? teamMembers.map(member => {
                const roleOptions = workspaceRoles.map(role => {
                    const selected = (member.roles || []).includes(role.slug) ? ' selected' : '';
                    return `<option value="${escapeHtml(role.slug)}"${selected}>${escapeHtml(role.label)}</option>`;
                }).join('');
                const names = (member.teams || []).map(team => escapeHtml(team)).join(', ') || '—';

                return `<tr>
                    <td class="py-3 px-3">
                        <div class="text-sm font-semibold text-white">${escapeHtml(member.name)}</div>
                        <div class="text-xs text-slate-500">${escapeHtml(member.email || '')}</div>
                    </td>
                    <td class="py-3 px-3 text-xs text-slate-300">${names}</td>
                    <td class="py-3 px-3">
                        <select multiple size="2" aria-label="Workspace roles for ${escapeHtml(member.name)}"
                                onchange="saveWorkspaceUserRoles(${member.id}, this)" ${canManageAdministration() ? '' : 'disabled'}
                                class="axis-input w-full px-2 py-1.5 text-xs">${roleOptions || '<option value="">No roles</option>'}</select>
                    </td>
                </tr>`;
            }).join('')
            : workspaceRow(3, 'No team members have been added yet.');
    }

    const teamSelect = document.getElementById('team-members-team');
    if (teamSelect) {
        teamSelect.innerHTML = workspaceTeams.map(team => `<option value="${team.id}">${escapeHtml(team.name)}</option>`).join('')
            || '<option value="">No teams</option>';
        teamSelect.onchange = syncWorkspaceTeamMemberSelection;
    }

    const memberSelect = document.getElementById('team-members-list');
    if (memberSelect) {
        memberSelect.innerHTML = teamMembers.map(member => `<option value="${member.id}">${escapeHtml(member.name)}</option>`).join('');
        syncWorkspaceTeamMemberSelection();
    }

    document.querySelectorAll('#page-roles [data-admin-only]').forEach(element => {
        element.classList.toggle('hidden', !canManageAdministration());
    });
}

function syncWorkspaceTeamMemberSelection() {
    const teamSelect = document.getElementById('team-members-team');
    const memberSelect = document.getElementById('team-members-list');
    if (!teamSelect || !memberSelect) return;

    const team = workspaceTeams.find(item => item.id === Number(teamSelect.value));
    const memberIds = new Set((team?.members || []).map(member => member.id));
    Array.from(memberSelect.options).forEach(option => {
        option.selected = memberIds.has(Number(option.value));
    });
}

function renderWorkQueuesPage() {
    const queueBody = document.getElementById('work-queues-tbody');
    if (queueBody) {
        queueBody.innerHTML = workQueues.length
            ? workQueues.map(queue => {
                const members = (queue.members || []).map(member => `${member.name} (${member.access})`).join(', ') || '—';
                const access = queue.access === 'editor' ? 'Editor' : queue.access === 'viewer' ? 'Viewer' : '—';
                const actions = canManageQueues()
                    ? `<button type="button" onclick="deleteWorkspaceQueue(${queue.id})" class="text-xs font-semibold text-rose-300 hover:text-rose-200">Remove</button>`
                    : '—';

                return `<tr>
                    <td class="py-3 px-3 align-top">
                        <div class="text-sm font-semibold text-white">${escapeHtml(queue.name)}</div>
                        <div class="text-xs text-slate-500">${escapeHtml(queue.description || '')}</div>
                    </td>
                    <td class="py-3 px-3 text-xs text-slate-300">${escapeHtml(queue.teamName || '—')}</td>
                    <td class="py-3 px-3 text-xs text-slate-300">${escapeHtml(queue.assignmentRule || '—')}</td>
                    <td class="py-3 px-3 text-xs text-slate-300">${escapeHtml(members)}</td>
                    <td class="py-3 px-3 text-center text-xs text-slate-200">${escapeHtml(access)}</td>
                    <td class="py-3 px-3 text-center text-xs ${queue.isActive ? 'text-emerald-300' : 'text-slate-500'}">${queue.isActive ? 'Active' : 'Paused'}</td>
                    <td class="py-3 px-3 text-right">${actions}</td>
                </tr>`;
            }).join('')
            : workspaceRow(7, 'No work queues are available to you.');
    }

    const createTeamSelect = document.getElementById('queue-team-select');
    if (createTeamSelect) {
        createTeamSelect.innerHTML = '<option value="">No team</option>' + workspaceTeams.map(team =>
            `<option value="${team.id}">${escapeHtml(team.name)}</option>`
        ).join('');
    }

    const shareQueueSelect = document.getElementById('queue-share-queue');
    if (shareQueueSelect) {
        shareQueueSelect.innerHTML = workQueues.map(queue => `<option value="${queue.id}">${escapeHtml(queue.name)}</option>`).join('')
            || '<option value="">No queues</option>';
    }

    const shareMemberSelect = document.getElementById('queue-share-members');
    if (shareMemberSelect) {
        shareMemberSelect.innerHTML = teamMembers.map(member => `<option value="${member.id}">${escapeHtml(member.name)}</option>`).join('');
    }

    document.querySelectorAll('#page-work-queues [data-queue-admin-only]').forEach(element => {
        element.classList.toggle('hidden', !canManageQueues());
    });
}

async function loadWorkspaceRecordOptions() {
    const recordSelect = document.getElementById('share-record-id');
    if (!recordSelect || !canManageRecords()) return;

    if (recordShareRecords === null || recordShareUsers === null) {
        recordSelect.innerHTML = '<option value="">Loading records…</option>';
        try {
            const response = await AxisAPI.request(AxisAPI.url('record-shares.index'), 'GET');
            recordShareRecords = response.records || {};
            recordShareUsers = response.users || [];
        } catch (error) {
            recordSelect.innerHTML = '<option value="">Could not load records</option>';
            handleWorkspaceActionError(error, 'Could not load record options.');
            return;
        }
    }

    const userSelect = document.getElementById('share-user-id');
    if (userSelect) {
        userSelect.innerHTML = (recordShareUsers || []).map(user =>
            `<option value="${user.id}">${escapeHtml(user.name)}</option>`
        ).join('') || '<option value="">No people</option>';
    }
    populateWorkspaceRecordOptions();
}

window.populateWorkspaceRecordOptions = function() {
    const recordSelect = document.getElementById('share-record-id');
    const typeSelect = document.getElementById('share-record-type');
    if (!recordSelect || !typeSelect) return;

    const records = (recordShareRecords || {})[typeSelect.value] || [];
    recordSelect.innerHTML = records.map(record => `<option value="${record.id}">${escapeHtml(record.title)}</option>`).join('')
        || '<option value="">No records available</option>';
};

function renderRecordSharesPage() {
    const shareBody = document.getElementById('record-shares-tbody');
    if (shareBody) {
        shareBody.innerHTML = recordShares.length
            ? recordShares.map(share => {
                const actions = canManageRecords()
                    ? `<button type="button" onclick="deleteWorkspaceRecordShare(${share.id})" class="text-xs font-semibold text-rose-300 hover:text-rose-200">Remove</button>`
                    : '—';

                return `<tr>
                    <td class="py-3 px-3 text-sm font-semibold text-white">${escapeHtml(share.title || `${share.type} #${share.shareableId}`)}</td>
                    <td class="py-3 px-3 text-xs text-slate-300">${escapeHtml(share.type)}</td>
                    <td class="py-3 px-3 text-xs text-slate-300">${escapeHtml(share.userName || '')}</td>
                    <td class="py-3 px-3 text-center text-xs text-slate-200">${share.ability === 'update' ? 'Update' : 'View'}</td>
                    <td class="py-3 px-3 text-right">${actions}</td>
                </tr>`;
            }).join('')
            : workspaceRow(5, 'No records have been shared with you.');
    }

    document.querySelectorAll('#page-sharing [data-record-admin-only]').forEach(element => {
        element.classList.toggle('hidden', !canManageRecords());
    });

    loadWorkspaceRecordOptions();
}

function renderFeatureUpgradesPage() {
    const upgradeBody = document.getElementById('feature-upgrades-tbody');
    if (!upgradeBody) return;

    upgradeBody.innerHTML = featureUpgrades.length
        ? featureUpgrades.map(feature => {
            const state = feature.state || 'locked';
            const stateClass = state === 'upgraded' ? 'text-emerald-300' : state === 'available' ? 'text-amber-300' : 'text-slate-400';
            const stateCell = canManageP18()
                ? `<select onchange="updateWorkspaceFeatureUpgrade(${feature.id}, this.value)" aria-label="State of ${escapeHtml(feature.label)}"
                         class="axis-input h-8 px-2 text-xs"><option value="locked"${state === 'locked' ? ' selected' : ''}>${escapeHtml(workspaceReadableState('locked'))}</option><option value="available"${state === 'available' ? ' selected' : ''}>${escapeHtml(workspaceReadableState('available'))}</option><option value="upgraded"${state === 'upgraded' ? ' selected' : ''}>${escapeHtml(workspaceReadableState('upgraded'))}</option></select>`
                : `<span class="text-xs font-semibold ${stateClass}">${escapeHtml(feature.stateLabel || workspaceReadableState(state))}</span>`;

            return `<tr>
                <td class="py-3 px-3 text-sm font-semibold text-white">${escapeHtml(feature.label)}</td>
                <td class="py-3 px-3 text-xs text-slate-400">${escapeHtml(feature.description || '')}</td>
                <td class="py-3 px-3 text-center">${stateCell}</td>
            </tr>`;
        }).join('')
        : workspaceRow(3, 'No P18 features have been configured.');
}

window.reloadWorkspaceSection = async function(section) {
    const config = {
        roles: { body: 'roles-tbody', columns: 2, route: 'roles.index', empty: 'No workspace roles have been configured.' },
        queues: { body: 'work-queues-tbody', columns: 7, route: 'work-queues.index', empty: 'No work queues are available to you.' },
        sharing: { body: 'record-shares-tbody', columns: 5, route: 'record-shares.index', empty: 'No records have been shared with you.' },
        p18: { body: 'feature-upgrades-tbody', columns: 3, route: 'feature-upgrades.index', empty: 'No P18 features have been configured.' },
    }[section];
    if (!config) return;

    const body = document.getElementById(config.body);
    if (body) body.innerHTML = workspaceRow(config.columns, 'Loading…', true);

    try {
        const response = await AxisAPI.request(AxisAPI.url(config.route), 'GET');
        if (section === 'roles') {
            workspaceRoles = response.data || [];
            renderRolesPage();
        } else if (section === 'queues') {
            workQueues = response.data || [];
            renderWorkQueuesPage();
        } else if (section === 'sharing') {
            recordShares = response.data || [];
            recordShareRecords = response.records || {};
            recordShareUsers = response.users || [];
            renderRecordSharesPage();
        } else {
            featureUpgrades = response.data || [];
            renderFeatureUpgradesPage();
        }
    } catch (error) {
        if (body) {
            const message = error?.status === 403 ? 'You do not have permission to open this section.' : 'Could not load this section.';
            body.innerHTML = workspaceRow(config.columns, message);
        }
        handleWorkspaceActionError(error, 'Could not refresh this section.');
    }
};

window.createWorkspaceTeam = async function(event) {
    event.preventDefault();
    const form = event.currentTarget;
    try {
        const response = await AxisAPI.request(AxisAPI.url('teams.store'), 'POST', Object.fromEntries(new FormData(form).entries()));
        workspaceTeams.push(response.data);
        form.reset();
        toggleWorkspaceForm('form-team-create');
        renderRolesPage();
        showToast('Team created.');
    } catch (error) {
        handleWorkspaceActionError(error, 'Could not create the team.');
    }
};

window.saveWorkspaceTeamMembers = async function(event) {
    event.preventDefault();
    const teamId = Number(document.getElementById('team-members-team')?.value);
    const memberSelect = document.getElementById('team-members-list');
    if (!teamId || !memberSelect) return;

    try {
        const response = await AxisAPI.request(AxisAPI.url('teams.members', teamId), 'PATCH', {
            members: workspaceSelectedValues(memberSelect),
        });
        const index = workspaceTeams.findIndex(team => team.id === teamId);
        if (index !== -1) workspaceTeams[index] = response.data;
        toggleWorkspaceForm('form-team-members');
        renderRolesPage();
        showToast('Team members updated.');
    } catch (error) {
        handleWorkspaceActionError(error, 'Could not update the team members.');
    }
};

window.deleteWorkspaceTeam = async function(teamId) {
    if (!window.confirm('Remove this team? Queue assignments will be cleared.')) return;

    try {
        await AxisAPI.request(AxisAPI.url('teams.destroy', teamId), 'DELETE');
        workspaceTeams = workspaceTeams.filter(team => team.id !== teamId);
        renderRolesPage();
        showToast('Team removed.');
    } catch (error) {
        handleWorkspaceActionError(error, 'Could not remove the team.');
    }
};

window.saveWorkspaceUserRoles = async function(userId, select) {
    try {
        const response = await AxisAPI.request(AxisAPI.url('user-roles.update', userId), 'PATCH', {
            roles: Array.from(select.selectedOptions).map(option => option.value),
        });
        const index = teamMembers.findIndex(member => member.id === userId);
        if (index !== -1) teamMembers[index] = { ...teamMembers[index], roles: response.data.roles || [], teams: response.data.teams || [] };
        renderRolesPage();
        showToast('User roles updated.');
    } catch (error) {
        handleWorkspaceActionError(error, 'Could not update the user roles.');
    }
};

window.createWorkspaceQueue = async function(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const payload = Object.fromEntries(new FormData(form).entries());
    payload.team_id = payload.team_id || null;
    payload.is_active = true;

    try {
        const response = await AxisAPI.request(AxisAPI.url('work-queues.store'), 'POST', payload);
        workQueues.push(response.data);
        form.reset();
        toggleWorkspaceForm('form-work-queue-create');
        renderWorkQueuesPage();
        showToast('Work queue created.');
    } catch (error) {
        handleWorkspaceActionError(error, 'Could not create the work queue.');
    }
};

window.saveWorkspaceQueueMembers = async function(event) {
    event.preventDefault();
    const queueId = Number(document.getElementById('queue-share-queue')?.value);
    const memberSelect = document.getElementById('queue-share-members');
    const accessSelect = document.getElementById('queue-share-access');
    if (!queueId || !memberSelect || !accessSelect) return;

    try {
        const response = await AxisAPI.request(AxisAPI.url('work-queues.members', queueId), 'PATCH', {
            members: workspaceSelectedValues(memberSelect),
            access: accessSelect.value,
        });
        const index = workQueues.findIndex(queue => queue.id === queueId);
        if (index !== -1) workQueues[index] = response.data;
        toggleWorkspaceForm('form-queue-share');
        renderWorkQueuesPage();
        showToast('Queue access updated.');
    } catch (error) {
        handleWorkspaceActionError(error, 'Could not update the queue access.');
    }
};

window.deleteWorkspaceQueue = async function(queueId) {
    if (!window.confirm('Remove this work queue?')) return;

    try {
        await AxisAPI.request(AxisAPI.url('work-queues.destroy', queueId), 'DELETE');
        workQueues = workQueues.filter(queue => queue.id !== queueId);
        renderWorkQueuesPage();
        showToast('Work queue removed.');
    } catch (error) {
        handleWorkspaceActionError(error, 'Could not remove the work queue.');
    }
};

window.createWorkspaceRecordShare = async function(event) {
    event.preventDefault();
    const payload = {
        shareable_type: document.getElementById('share-record-type')?.value,
        shareable_id: Number(document.getElementById('share-record-id')?.value),
        user_id: Number(document.getElementById('share-user-id')?.value),
        ability: document.getElementById('share-ability')?.value,
    };
    if (!payload.shareable_id || !payload.user_id) {
        showToast('Choose a record and a person first.');
        return;
    }

    try {
        const response = await AxisAPI.request(AxisAPI.url('record-shares.store'), 'POST', payload);
        recordShares.push(response.data);
        toggleWorkspaceForm('form-record-share');
        renderRecordSharesPage();
        showToast('Record shared.');
    } catch (error) {
        handleWorkspaceActionError(error, 'Could not share the record.');
    }
};

window.deleteWorkspaceRecordShare = async function(shareId) {
    if (!window.confirm('Remove this record share?')) return;

    try {
        await AxisAPI.request(AxisAPI.url('record-shares.destroy', shareId), 'DELETE');
        recordShares = recordShares.filter(share => share.id !== shareId);
        renderRecordSharesPage();
        showToast('Record share removed.');
    } catch (error) {
        handleWorkspaceActionError(error, 'Could not remove the record share.');
    }
};

window.updateWorkspaceFeatureUpgrade = async function(featureId, state) {
    try {
        const response = await AxisAPI.request(AxisAPI.url('feature-upgrades.update', featureId), 'PATCH', { state });
        const index = featureUpgrades.findIndex(feature => feature.id === featureId);
        if (index !== -1) featureUpgrades[index] = response.data;
        renderFeatureUpgradesPage();
        showToast('Upgrade state updated.');
    } catch (error) {
        handleWorkspaceActionError(error, 'Could not update the upgrade state.');
    }
};

// =====================================================================
// INITIALIZE ON LOAD
// =====================================================================
window.addEventListener('DOMContentLoaded', () => {
    populateModuleDropdowns();
    initDashboardScrollButtons();
    renderProjectsTable();
    initModuleDragAndDrop();
    renderTaskDetailPage();
    renderReportsPage();
    renderTeamTable();
    initTheme();
    initSidebar();
    syncQuickModulePercentages();
    syncHeaderTimerPill();
    startGlobalStopwatchLoop();

    // Close the Slide-Over Issue Drawer on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeDevIssueDrawer();
            toggleSidebar(false);
        }
    });

    // A module list opened from a project (`/admin/modules?project=ID`) starts filtered to it
    const projectFilterSelect = document.getElementById('flt-project');
    if (projectFilterSelect && AXIS_BOOT.moduleProjectFilter && axisProjects.some(ap => ap.id === AXIS_BOOT.moduleProjectFilter)) {
        projectFilterSelect.value = String(AXIS_BOOT.moduleProjectFilter);
    }

    // Open the page matching the Laravel route that served this request
    switchPage(AXIS_BOOT.initialPage || 'projects', 'replace');
});
