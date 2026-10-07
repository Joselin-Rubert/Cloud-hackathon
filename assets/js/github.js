/* StudentFlow - GitHub Explorer page (profile, repos, chart, activity, compare, sync) */
(function () {
  'use strict';

  const API = 'api/github.php';
  const FILTERS = ['All', 'Java', 'Python', 'JavaScript', 'PHP', 'C', 'C++', 'HTML', 'CSS', 'Other'];
  const PALETTE = ['#6366f1', '#0ea5e9', '#f59e0b', '#10b981', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316', '#84cc16'];
  const ICONS = {
    PushEvent: 'git-commit', CreateEvent: 'folder-plus', ForkEvent: 'git-fork',
    IssuesEvent: 'circle-alert', PullRequestEvent: 'git-pull-request', WatchEvent: 'star',
    DeleteEvent: 'trash-2', IssueCommentEvent: 'message-square', PullRequestReviewEvent: 'user-check',
    ReleaseEvent: 'tag', PublicEvent: 'globe', GollumEvent: 'book-open', MemberEvent: 'user-plus',
    SponsorshipEvent: 'heart',
  };

  const SF = window.SF || {};
  const $ = function (id) { return document.getElementById(id); };

  let payload = null;
  let state = { q: '', lang: 'All', sort: 'updated' };

  /* ---------------- formatting ---------------- */
  function fmt(n) { return Number(n || 0).toLocaleString('en-US'); }

  function friendlyDate(dt) {
    if (!dt) return 'Recently';
    const d = new Date(String(dt).replace(' ', 'T'));
    if (isNaN(d.getTime())) return dt;
    const diff = Date.now() - d.getTime();
    if (diff < 60000) return 'just now';
    if (diff < 3600000) return Math.floor(diff / 60000) + 'm ago';
    if (diff < 86400000) return Math.floor(diff / 3600000) + 'h ago';
    if (diff < 604800000) return Math.floor(diff / 86400000) + 'd ago';
    return d.toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric' });
  }

  function fullDate(dt) {
    if (!dt) return 'never';
    const d = new Date(String(dt).replace(' ', 'T'));
    if (isNaN(d.getTime())) return dt;
    return d.toLocaleString('en-US', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
  }

  function initialsAvatar(name, username) {
    const text = String(name || username || '?')
      .trim()
      .split(/\s+/)
      .map(function (w) { return w.charAt(0); })
      .join('')
      .slice(0, 2)
      .toUpperCase() || '?';
    const svg =
      '<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80">' +
      '<rect width="80" height="80" rx="16" fill="#6366f1" opacity="0.14"/>' +
      '<text x="50%" y="52%" font-family="Inter, sans-serif" font-size="30" font-weight="700" fill="#6366f1"' +
      ' text-anchor="middle" dominant-baseline="middle">' + text + '</text></svg>';
    return 'data:image/svg+xml;utf8,' + encodeURIComponent(svg);
  }

  /* ---------------- main render ---------------- */
  function renderAll(data) {
    payload = data;
    if (!payload || !payload.profile) return;
    renderProfile(payload.profile);
    renderStats(payload);
    renderRepos();
    renderChart(payload.languages);
    renderActivity(payload.recent_events);
    updateLastSynced(payload.profile.last_synced);

    const empty = $('githubEmptyState');
    const results = $('githubResults');
    if (empty) empty.classList.add('hidden');
    if (results) results.classList.remove('hidden');

    if (window.lucide) lucide.createIcons();
    if (payload.issues && payload.issues.length) {
      payload.issues.forEach(function (msg) { SF.toast && SF.toast(msg, 'info'); });
    }
  }

  function renderProfile(p) {
    const img = $('ghAvatar');
    img.alt = '@' + p.username;
    if (p.avatar_url) {
      img.src = p.avatar_url;
      img.onerror = function () { img.src = initialsAvatar(p.name, p.username); };
    } else {
      img.src = initialsAvatar(p.name, p.username);
    }

    $('ghName').textContent = p.name || p.username;
    const link = $('ghUsernameLink');
    link.textContent = '@' + p.username;
    link.href = p.github_profile_url || ('https://github.com/' + p.username);

    const meta = $('ghMeta');
    meta.innerHTML = '';
    const chip = function (icon, text, show) {
      if (!show || !text) return '';
      return '<span class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 px-2.5 py-1">' +
        '<i data-lucide="' + icon + '" class="w-3.5 h-3.5"></i>' + SF.esc(text) + '</span>';
    };
    meta.innerHTML = (chip('map-pin', p.location, true) || '') +
      (chip('building-2', p.company, true) || '') +
      (chip('calendar-clock', 'Synced ' + friendlyDate(p.last_synced), true));

    if (!p.location && !p.company) {
      meta.innerHTML = '<span class="text-xs text-slate-400">No public location or company listed.</span>';
    }

    $('ghBio').textContent = p.bio || 'This profile does not have a public bio.';

    const profile = $('btnGithubProfile');
    profile.href = p.github_profile_url || ('https://github.com/' + p.username);
  }

  function renderStats(data) {
    const stat = function (id, value) { const el = $(id); if (el) el.textContent = fmt(value); };
    const p = data.profile;
    stat('ghStatRepos', p.public_repos);
    stat('ghStatFollowers', p.followers);
    stat('ghStatFollowing', p.following);
    stat('ghStatStars', data.stats.total_stars);
    stat('ghStatForks', data.stats.total_forks);
  }

  /* ---------------- repositories: search + filter + sort ---------------- */
  function visibleRepos() {
    let list = (payload.repositories || []).slice();
    if (state.q) {
      const q = state.q.toLowerCase();
      list = list.filter(function (r) {
        return (r.repo_name || '').toLowerCase().indexOf(q) !== -1 ||
          (r.description || '').toLowerCase().indexOf(q) !== -1;
      });
    }
    if (state.lang !== 'All') {
      list = list.filter(function (r) {
        const lang = r.language || '';
        if (state.lang === 'Other') {
          return lang === '' || FILTERS.indexOf(lang) === -1;
        }
        return lang === state.lang;
      });
    }
    return list;
  }

  function sortRepos(list) {
    const arr = list.slice();
    switch (state.sort) {
      case 'stars': arr.sort(function (a, b) { return b.stars - a.stars; }); break;
      case 'forks': arr.sort(function (a, b) { return b.forks - a.forks; }); break;
      case 'name':
        arr.sort(function (a, b) { return String(a.repo_name || '').localeCompare(String(b.repo_name || '')); });
        break;
      default:
        arr.sort(function (a, b) { return String(b.repo_updated_at || '').localeCompare(String(a.repo_updated_at || '')); });
    }
    return arr;
  }

  function repoCard(r) {
    const langBadge = r.language
      ? '<span class="inline-flex items-center gap-1.5 rounded-lg bg-sky-50 dark:bg-sky-500/15 text-sky-600 dark:text-sky-400 px-2.5 py-1 text-xs font-medium">' +
        '<span class="w-2 h-2 rounded-full bg-sky-500"></span>' + SF.esc(r.language) + '</span>'
      : '<span class="text-xs text-slate-400">No language detected</span>';

    const desc = r.description ? SF.esc(r.description) : 'No description provided.';

    return (
      '<div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-soft flex flex-col">' +
      '<a href="' + SF.esc(r.html_url || '#') + '" target="_blank" rel="noopener" class="truncate font-semibold text-slate-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 transition">' +
      SF.esc(r.repo_name) + '</a>' +
      '<p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400 line-clamp-2 min-h-[2rem]">' + desc + '</p>' +
      '<div class="mt-3 flex flex-wrap items-center gap-x-3.5 gap-y-2 text-xs text-slate-500 dark:text-slate-400">' +
      langBadge +
      '<span class="inline-flex items-center gap-1"><i data-lucide="star" class="w-3.5 h-3.5 text-amber-400"></i>' + fmt(r.stars) + '</span>' +
      '<span class="inline-flex items-center gap-1"><i data-lucide="git-fork" class="w-3.5 h-3.5 text-slate-400"></i>' + fmt(r.forks) + '</span>' +
      '<span class="inline-flex items-center gap-1"><i data-lucide="circle-alert" class="w-3.5 h-3.5 text-slate-400"></i>' + fmt(r.open_issues) + '</span>' +
      '</div>' +
      '<div class="mt-auto pt-3 flex items-center justify-between gap-2">' +
      '<span class="text-[11px] text-slate-400 inline-flex items-center gap-1"><i data-lucide="clock" class="w-3.5 h-3.5"></i>Updated ' + friendlyDate(r.repo_updated_at) + '</span>' +
      '<a href="' + SF.esc(r.html_url || '#') + '" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-[11px] font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">' +
      'View Repository <i data-lucide="external-link" class="w-3 h-3"></i></a>' +
      '</div></div>'
    );
  }

  function renderRepos() {
    const grid = $('repoGrid');
    const empty = $('repoEmpty');
    const count = $('repoCount');
    if (!grid) return;

    const all = payload.repositories || [];
    const shown = sortRepos(visibleRepos());
    grid.innerHTML = shown.length ? shown.map(repoCard).join('') : '';
    if (empty) empty.classList.toggle('hidden', shown.length > 0);
    if (count) {
      count.textContent = all.length
        ? 'Showing ' + shown.length + ' of ' + all.length + ' repositories'
        : 'No public repositories found for this profile.';
    }
    if (window.lucide) lucide.createIcons();
  }

  /* ---------------- language chart ---------------- */
  function renderChart(langs) {
    langs = langs || { labels: [], data: [], total: 0 };
    const note = $('langNote');
    if (!langs.labels.length || !langs.total) {
      SFCharts.destroy('languageChart');
      if (note) note.textContent = 'No language data available — repositories without a detected primary language are ignored.';
      return;
    }
    const colors = langs.labels.map(function (_, i) { return PALETTE[i % PALETTE.length]; });
    const border = SFCharts.isDark() ? 'rgba(15,23,42,0.9)' : '#ffffff';
    SFCharts.render('languageChart', {
      type: 'doughnut',
      data: {
        labels: langs.labels,
        datasets: [{
          data: langs.data,
          backgroundColor: colors,
          borderColor: border,
          borderWidth: 2,
          hoverOffset: 6,
        }],
      },
      options: {
        cutout: '62%',
        plugins: {
          legend: { position: 'right' },
          tooltip: { callbacks: { label: function (ctx) { return ' ' + ctx.label + ': ' + ctx.parsed + '%'; } } },
        },
      },
    });
    if (note) note.textContent = 'Percentages are computed from the primary language of each public repository.';
  }

  /* ---------------- recent public activity ---------------- */
  function renderActivity(events) {
    events = events || [];
    const list = $('activityList');
    const empty = $('activityEmpty');
    if (!list) return;
    list.innerHTML = '';
    if (!events.length) {
      if (empty) empty.classList.remove('hidden');
      return;
    }
    if (empty) empty.classList.add('hidden');

    events.forEach(function (ev) {
      const icon = ICONS[ev.type] || 'activity';
      const repo = ev.repo ? SF.esc(ev.repo) : 'a repository';
      const repoHtml = ev.url
        ? '<a href="' + SF.esc(ev.url) + '" target="_blank" rel="noopener" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">' + repo + '</a>'
        : '<span class="font-medium text-slate-700 dark:text-slate-200">' + repo + '</span>';

      const li = document.createElement('li');
      li.className = 'flex items-start gap-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4';
      li.innerHTML =
        '<span class="w-9 h-9 shrink-0 rounded-lg bg-indigo-50 dark:bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">' +
        '<i data-lucide="' + icon + '" class="w-4 h-4"></i></span>' +
        '<div class="min-w-0 flex-1">' +
        '<p class="text-sm text-slate-700 dark:text-slate-200">' + SF.esc(ev.label) + ' in ' + repoHtml + '</p>' +
        '<p class="mt-0.5 text-xs text-slate-400 inline-flex items-center gap-1"><i data-lucide="calendar-clock" class="w-3 h-3"></i>' + friendlyDate(ev.created_at) + '</p>' +
        '</div>';
      list.appendChild(li);
    });
    if (window.lucide) lucide.createIcons();
  }

  /* ---------------- sync state ---------------- */
  function updateLastSynced(dt) {
    const el = $('lastSyncedText');
    if (el) el.textContent = dt ? 'Last synced: ' + fullDate(dt) : 'Not synced yet.';
  }

  /* ---------------- comparison ---------------- */
  function cellValue(x) {
    if (typeof x === 'number') return fmt(x);
    return x ? SF.esc(String(x)) : '<span class="text-slate-400">Not available</span>';
  }

  function renderCompare(json) {
    const users = (json.data && json.data.users) || [];
    const u1 = users[0] || {};
    const u2 = users[1] || {};
    const wrap = $('compareWrap');
    if (!wrap) return;
    wrap.classList.remove('hidden');

    const head = function (u) {
      const base = u.name ? u.name + ' · @' + u.username : '@' + (u.username || '?');
      return u.error ? base + ' <span class="text-rose-500 font-medium">(unavailable)</span>' : base;
    };
    $('cmpHead1').innerHTML = head(u1);
    $('cmpHead2').innerHTML = head(u2);

    const rows = [
      ['Public repositories', u1.public_repos, u2.public_repos],
      ['Followers', u1.followers, u2.followers],
      ['Following', u1.following, u2.following],
      ['Total stars', u1.total_stars, u2.total_stars],
      ['Total forks', u1.total_forks, u2.total_forks],
      ['Top language', u1.top_language, u2.top_language],
      ['Recent public activity', u1.recent_activity, u2.recent_activity],
      ['Recent activity count', u1.recent_activity_count, u2.recent_activity_count],
    ];

    $('compareTable').innerHTML = rows.map(function (r, i) {
      const rowClass = i % 2 ? 'border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-800/30' : 'border-slate-200 dark:border-slate-800';
      return '<tr class="'+ (i===0 ? 'border-b' : rowClass) +'">' +
        '<td class="py-3 pr-4 text-xs font-semibold tracking-wider text-slate-400 uppercase">' + SF.esc(r[0]) + '</td>' +
        '<td class="py-3 px-4 text-sm text-slate-700 dark:text-slate-200">' + (u1.error ? '<span class="text-rose-500 text-xs">' + SF.esc(u1.error) + '</span>' : cellValue(r[1])) + '</td>' +
        '<td class="py-3 px-4 text-sm text-slate-700 dark:text-slate-200">' + (u2.error ? '<span class="text-rose-500 text-xs">' + SF.esc(u2.error) + '</span>' : cellValue(r[2])) + '</td>' +
        '</tr>';
    }).join('');
  }

  /* ---------------- actions ---------------- */
  function onSearchSubmit(e) {
    e.preventDefault();
    const u = $('ghUsername').value.trim();
    if (!u) {
      if (SF.toast) SF.toast('Enter a GitHub username.', 'error');
      return;
    }
    const btn = $('btnSearchGitHub');
    const label = $('btnSearchLabel');
    btn.disabled = true;
    label.textContent = 'Searching...';
    SF.post(API, { action: 'search', username: u }).then(function (json) {
      if (json.ok) {
        renderAll(json.data);
        if (SF.toast) SF.toast('GitHub profile loaded.');
      }
    }).finally(function () {
      btn.disabled = false;
      label.textContent = 'View Profile';
    });
  }

  function onSyncClick() {
    if (!payload || !payload.profile) {
      if (SF.toast) SF.toast('Search a GitHub username first.', 'info');
      return;
    }
    const btn = $('btnGithubSync');
    const label = $('btnSyncLabel');
    btn.disabled = true;
    label.textContent = 'Syncing GitHub...';
    SF.post(API, { action: 'sync' }, true).then(function (json) {
      if (json.ok) {
        renderAll(json.data);
        if (SF.toast) SF.toast('GitHub data synced successfully.');
      } else if (json.message) {
        if (SF.toast) SF.toast(json.message, 'error');
      }
    }).finally(function () {
      btn.disabled = false;
      label.textContent = 'Sync GitHub';
    });
  }

  function onCompareSubmit(e) {
    e.preventDefault();
    const a = $('cmpUser1').value.trim();
    const b = $('cmpUser2').value.trim();
    if (!a || !b) {
      if (SF.toast) SF.toast('Enter two GitHub usernames to compare.', 'error');
      return;
    }
    const btn = $('btnCompareLabel');
    const formBtn = document.getElementById('compareForm').querySelector('button[type="submit"]');
    formBtn.disabled = true;
    btn.textContent = 'Comparing...';
    SF.get(API + '?action=compare&user1=' + encodeURIComponent(a) + '&user2=' + encodeURIComponent(b))
      .then(function (json) {
        if (json.ok) { renderCompare(json); }
        else if (SF.toast) { SF.toast(json.message || 'Could not compare those usernames.', 'error'); }
      })
      .finally(function () {
        formBtn.disabled = false;
        btn.textContent = 'Compare Profiles';
      });
  }

  function chipClass(active) {
    return 'repo-chip rounded-full px-3.5 py-1.5 text-xs font-semibold transition border ' +
      (active
        ? 'border-indigo-600 bg-indigo-600 text-white'
        : 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:border-indigo-400 hover:text-indigo-600');
  }

  function bindEvents() {
    $('githubSearchForm').addEventListener('submit', onSearchSubmit);
    $('btnGithubSync').addEventListener('click', onSyncClick);
    $('compareForm').addEventListener('submit', onCompareSubmit);
    $('repoSearch').addEventListener('input', function () { state.q = this.value.trim(); renderRepos(); });
    $('repoSort').addEventListener('change', function () { state.sort = this.value; renderRepos(); });
    $('repoFilters').addEventListener('click', function (e) {
      const chip = e.target.closest('.repo-chip');
      if (!chip) return;
      state.lang = chip.dataset.lang;
      document.querySelectorAll('.repo-chip').forEach(function (c) {
        c.className = chipClass(c.dataset.lang === state.lang);
      });
      renderRepos();
    });
    window.addEventListener('sf:theme', function () {
      if (payload) renderChart(payload.languages);
    });

    if (window.lucide) lucide.createIcons();
  }

  function boot() {
    FILTERS.forEach(function (f) {
      const b = document.createElement('button');
      b.type = 'button';
      b.dataset.lang = f;
      b.textContent = f;
      b.className = chipClass(f === state.lang);
      $('repoFilters').appendChild(b);
    });

    bindEvents();

    const initial = (window.SF_DATA && window.SF_DATA.github) || null;
    if (initial && initial.profile) {
      $('ghUsername').value = initial.profile.username;
      renderAll(initial);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();