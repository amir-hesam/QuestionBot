(function () {
  'use strict';

  var tg = window.Telegram && window.Telegram.WebApp ? window.Telegram.WebApp : null;
  var view = document.getElementById('view');
  var bottomNav = document.getElementById('bottomNav');
  var toastEl = document.getElementById('toast');
  var themeBtn = document.getElementById('themeBtn');
  var topbar = document.querySelector('.topbar');
  var THEME_KEY = 'miniapp-theme';

  var ICONS = {
    sun: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"></circle><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.66 6.34l1.41-1.41"></path></svg>',
    moon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A8.5 8.5 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"></path></svg>',
    clock: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path></svg>',
    book: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5v-16Z"></path><path d="M4 18.5A2.5 2.5 0 0 1 6.5 16H20"></path><path d="M8 7h8M8 11h5"></path></svg>',
    lock: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="10" width="14" height="10" rx="2.5"></rect><path d="M8 10V7.5a4 4 0 0 1 8 0V10"></path></svg>',
    play: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m8 5 11 7-11 7V5Z"></path></svg>',
    refresh: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 11a8 8 0 1 0-2.34 5.66"></path><path d="M20 4v7h-7"></path></svg>',
    chart: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19V5M4 19h16"></path><path d="M8 15v-4M12 15V7M16 15v-6"></path></svg>',
    arrowRight: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="m13 6 6 6-6 6"></path></svg>',
    arrowLeft: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"></path><path d="m11 18-6-6 6-6"></path></svg>',
    check: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><path d="m8 12 2.8 2.8L16.5 9"></path></svg>',
    close: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><path d="m9 9 6 6M15 9l-6 6"></path></svg>',
    chevron: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m15 6-6 6 6 6"></path></svg>',
    empty: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7V5h16v2"></path><path d="M5 5h14l-1.2 14H6.2L5 5Z"></path><path d="M9 10v5M15 10v5"></path></svg>',
    info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><path d="M12 11v5M12 8h.01"></path></svg>',
    trophy: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 4h8v4a4 4 0 0 1-8 0V4Z"></path><path d="M8 5H5v2a3 3 0 0 0 3 3M16 5h3v2a3 3 0 0 1-3 3"></path><path d="M12 12v4M9 20h6M10 20v-2h4v2"></path></svg>',
    home: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m4 11 8-7 8 7"></path><path d="M6 9.5V20h12V9.5"></path><path d="M10 20v-5h4v5"></path></svg>',
    user: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.5"></circle><path d="M5.5 20a6.5 6.5 0 0 1 13 0"></path></svg>',
    key: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="15" r="4"></circle><path d="m11 12 9-9M17 3l4 4M14 6l3 3"></path></svg>'
  };

  var state = {
    home: null,
    tab: 'exams',
    screen: 'home',
    exam: null,
    entryQuiz: null,
    result: null,
    timerId: null,
    deadline: 0,
    busy: false,
    finishing: false,
    toastTimer: null
  };

  function esc(value) {
    return String(value === null || value === undefined ? '' : value).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function fa(value) {
    return String(value === null || value === undefined ? '' : value).replace(/[0-9]/g, function (d) {
      return '۰۱۲۳۴۵۶۷۸۹'.charAt(Number(d));
    });
  }

  function faDur(seconds) {
    if (seconds === null || seconds === undefined) {
      return 'بدون محدودیت';
    }
    var total = Math.max(0, Math.floor(Number(seconds) || 0));
    var h = Math.floor(total / 3600);
    var m = Math.floor((total % 3600) / 60);
    var s = total % 60;
    function pad(n) {
      return n < 10 ? '0' + n : String(n);
    }
    return fa(h > 0 ? h + ':' + pad(m) + ':' + pad(s) : pad(m) + ':' + pad(s));
  }

  function normalizeCode(value) {
    return String(value === null || value === undefined ? '' : value)
      .replace(/[۰-۹]/g, function (d) {
        return String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d));
      })
      .replace(/[٠-٩]/g, function (d) {
        return String('٠١٢٣٤٥٦٧٨٩'.indexOf(d));
      })
      .replace(/[\s\-_]+/g, '')
      .toUpperCase();
  }

  function storedTheme() {
    try {
      var value = window.localStorage.getItem(THEME_KEY);
      return value === 'dark' || value === 'light' ? value : null;
    } catch (e) {
      return null;
    }
  }

  function setTheme(theme, persist) {
    theme = theme === 'light' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', theme);
    document.documentElement.style.colorScheme = theme;
    if (persist) {
      try {
        window.localStorage.setItem(THEME_KEY, theme);
      } catch (e) {}
    }
    var meta = document.querySelector('meta[name="theme-color"]');
    if (meta) {
      meta.setAttribute('content', theme === 'dark' ? '#070b16' : '#eef2f9');
    }
    if (themeBtn) {
      themeBtn.innerHTML = theme === 'dark' ? ICONS.sun : ICONS.moon;
      themeBtn.setAttribute('aria-label', theme === 'dark' ? 'تغییر به تم روشن' : 'تغییر به تم تاریک');
    }
    if (tg) {
      try {
        if (tg.setHeaderColor) {
          tg.setHeaderColor(theme === 'dark' ? '#070b16' : '#eef2f9');
        }
        if (tg.setBackgroundColor) {
          tg.setBackgroundColor(theme === 'dark' ? '#070b16' : '#eef2f9');
        }
      } catch (e) {}
    }
  }

  function haptic(style) {
    try {
      if (tg && tg.HapticFeedback) {
        tg.HapticFeedback.impactOccurred(style || 'light');
      }
    } catch (e) {}
  }

  function toast(message, type) {
    if (!toastEl) {
      return;
    }
    toastEl.textContent = String(message || '');
    toastEl.className = 'toast show' + (type ? ' ' + type : '');
    if (state.toastTimer) {
      window.clearTimeout(state.toastTimer);
    }
    state.toastTimer = window.setTimeout(function () {
      toastEl.className = 'toast' + (type ? ' ' + type : '');
    }, 3200);
  }

  function api(action, payload) {
    var headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
    var body = payload || {};
    if (tg && tg.initData) {
      headers['X-Telegram-Init-Data'] = tg.initData;
      body.init_data = tg.initData;
    }
    var request;
    try {
      request = window.fetch('api.php?action=' + encodeURIComponent(action), {
        method: 'POST',
        headers: headers,
        body: JSON.stringify(body),
        credentials: 'same-origin',
        cache: 'no-store'
      });
    } catch (e) {
      return Promise.reject(new Error('اتصال به سرور برقرار نشد.'));
    }
    return request.then(function (response) {
      return response.json().catch(function () {
        return null;
      }).then(function (json) {
        if (!response.ok || !json || json.ok !== true) {
          var message = json && json.error && json.error.message
            ? json.error.message
            : 'خطا در ارتباط با سرور. دوباره تلاش کنید.';
          var error = new Error(message);
          error.code = json && json.error ? json.error.code : 'http_' + response.status;
          error.status = response.status;
          error.meta = json && json.meta ? json.meta : {};
          throw error;
        }
        return json.data;
      });
    }, function () {
      throw new Error('اتصال به سرور برقرار نشد. اینترنت را بررسی کنید.');
    });
  }

  function syncChrome() {
    var isHome = state.screen === 'home';
    var showBack = state.screen === 'entry' || state.screen === 'exam' || state.screen === 'result';
    if (bottomNav) {
      bottomNav.classList.toggle('hidden', !isHome);
      var tabs = bottomNav.querySelectorAll('[data-tab]');
      for (var i = 0; i < tabs.length; i++) {
        tabs[i].classList.toggle('active', tabs[i].getAttribute('data-tab') === state.tab);
      }
    }
    if (tg) {
      try {
        if (tg.BackButton) {
          if (showBack) {
            tg.BackButton.show();
          } else {
            tg.BackButton.hide();
          }
        }
      } catch (e) {}
      try {
        if (tg.setClosingConfirmation) {
          tg.setClosingConfirmation(state.screen === 'exam');
        }
      } catch (e) {}
    }
  }

  function stopTimer() {
    if (state.timerId) {
      window.clearInterval(state.timerId);
      state.timerId = null;
    }
  }

  function updateTimerDisplay() {
    var el = document.getElementById('timerPill');
    if (!el || !state.exam) {
      return;
    }
    var remaining = state.exam.remaining;
    if (remaining === null || remaining === undefined) {
      el.classList.remove('warn');
      el.innerHTML = ICONS.clock + '<span>آزاد</span>';
      return;
    }
    var left = Math.max(0, Math.floor(Number(remaining) || 0));
    el.classList.toggle('warn', left <= 60);
    el.innerHTML = ICONS.clock + '<span>' + esc(faDur(left)) + '</span>';
  }

  function startTimer() {
    stopTimer();
    if (!state.exam) {
      return;
    }
    updateTimerDisplay();
    if (state.exam.remaining === null || state.exam.remaining === undefined) {
      return;
    }
    state.deadline = Date.now() + (Number(state.exam.remaining) || 0) * 1000;
    state.timerId = window.setInterval(function () {
      if (!state.exam) {
        stopTimer();
        return;
      }
      var left = Math.round((state.deadline - Date.now()) / 1000);
      state.exam.remaining = Math.max(0, left);
      updateTimerDisplay();
      if (left <= 0) {
        stopTimer();
        autoFinish();
      }
    }, 1000);
  }

  function renderLoading(message) {
    state.screen = 'loading';
    view.innerHTML = '<div class="boot"><div class="spinner" aria-hidden="true"></div><p>'
      + esc(message || 'در حال بارگذاری…') + '</p></div>';
    syncChrome();
  }

  function renderOutside(message) {
    state.screen = 'outside';
    stopTimer();
    view.innerHTML = '<section class="card state-card">'
      + '<div class="state-icon">' + ICONS.user + '</div>'
      + '<h2>ورود از داخل تلگرام</h2>'
      + '<p>' + esc(message || 'برای استفاده از مینی‌اپ، آن را از داخل ربات تلگرام باز کنید.') + '</p>'
      + '<p class="state-note">اگر مینی‌اپ را از داخل ربات باز کرده‌اید، یک بار آن را ببندید و دوباره از دکمه «آزمون‌ها» وارد شوید.</p>'
      + '</section>';
    syncChrome();
  }

  function renderError(error) {
    if (error && (error.code === 'unauthorized' || error.code === 'expired')) {
      renderOutside(error.message);
      return;
    }
    state.screen = 'error';
    stopTimer();
    view.innerHTML = '<section class="card state-card">'
      + '<div class="state-icon">' + ICONS.info + '</div>'
      + '<h2>مشکلی پیش آمد</h2>'
      + '<p>' + esc(error && error.message ? error.message : 'خطای نامشخص. دوباره تلاش کنید.') + '</p>'
      + '<button type="button" class="btn primary" data-action="reload">' + ICONS.refresh + ' تلاش دوباره</button>'
      + '</section>';
    syncChrome();
  }

  function averagePercent(history) {
    if (!history || history.length === 0) {
      return 0;
    }
    var sum = 0;
    for (var i = 0; i < history.length; i++) {
      sum += Number(history[i].percent) || 0;
    }
    return Math.round(sum / history.length);
  }

  function quizCard(quiz) {
    var active = Number(quiz.attempt_id) > 0;
    var meta = '<span class="meta-pill">' + ICONS.book + esc(fa(quiz.question_count)) + ' سوال</span>';
    meta += '<span class="meta-pill">' + ICONS.clock + (Number(quiz.time_limit) > 0
      ? esc(faDur(quiz.time_limit))
      : 'بدون محدودیت') + '</span>';
    if (quiz.has_code) {
      meta += '<span class="meta-pill locked">' + ICONS.lock + ' کد ورود دارد</span>';
    }
    var button = active
      ? '<button type="button" class="btn soft" data-action="resume" data-attempt-id="' + Number(quiz.attempt_id) + '">' + ICONS.play + ' ادامه آزمون</button>'
      : '<button type="button" class="btn primary" data-action="start" data-quiz-id="' + Number(quiz.id) + '">' + ICONS.play + ' شرکت در آزمون</button>';
    var hint = active
      ? '<span class="hint">در حال انجام</span>'
      : '<span class="hint">کد دانش‌آموزی لازم است</span>';
    return '<article class="card quiz-card">'
      + '<div class="quiz-top">'
      + '<span class="quiz-icon">' + ICONS.book + '</span>'
      + '<div class="quiz-main"><h3>' + esc(quiz.title) + '</h3>'
      + (quiz.description ? '<p class="quiz-desc">' + esc(quiz.description) + '</p>' : '')
      + '</div></div>'
      + '<div class="quiz-meta">' + meta + '</div>'
      + '<div class="quiz-foot">' + hint + button + '</div>'
      + '</article>';
  }

  function activeCard(attempt) {
    var countdown = attempt.remaining === null || attempt.remaining === undefined
      ? 'بدون محدودیت'
      : faDur(attempt.remaining) + ' باقی‌مانده';
    return '<article class="card active-card">'
      + '<div class="active-top">'
      + '<span class="pulse-dot" aria-hidden="true"></span>'
      + '<div class="quiz-main"><h3>' + esc(attempt.title) + '</h3>'
      + '<p class="active-meta">' + esc(attempt.student_name || '') + ' · سوال '
      + esc(fa(Number(attempt.current_index) + 1)) + ' از ' + esc(fa(attempt.total)) + '</p>'
      + '</div></div>'
      + '<div class="active-foot">'
      + '<span class="countdown-pill">' + ICONS.clock + esc(countdown) + '</span>'
      + '<button type="button" class="btn primary small" data-action="resume" data-attempt-id="'
      + Number(attempt.attempt_id) + '">' + ICONS.play + ' ادامه</button>'
      + '</div>'
      + '</article>';
  }

  function historyCard(item) {
    var pct = Number(item.percent) || 0;
    var cls = pct >= 80 ? '' : (pct >= 50 ? ' warn' : ' bad');
    var date = formatDate(item.finished_at);
    return '<button type="button" class="card history-card" data-action="open-result" data-attempt-id="'
      + Number(item.attempt_id) + '">'
      + '<span class="score-badge' + cls + '">' + esc(fa(pct)) + '٪</span>'
      + '<span class="history-main"><h3>' + esc(item.title) + '</h3>'
      + '<p class="history-sub"><span>' + esc(fa(item.score)) + ' از ' + esc(fa(item.total)) + '</span>'
      + (date ? '<span>' + esc(date) + '</span>' : '')
      + '</p></span>'
      + '<span class="chevron">' + ICONS.chevron + '</span>'
      + '</button>';
  }

  function formatDate(value) {
    if (!value) {
      return '';
    }
    var date = new Date(String(value).replace(' ', 'T'));
    if (isNaN(date.getTime())) {
      return String(value);
    }
    try {
      return date.toLocaleDateString('fa-IR', { year: 'numeric', month: 'long', day: 'numeric' });
    } catch (e) {
      return String(value);
    }
  }

  function renderHome() {
    if (!state.home) {
      return;
    }
    var data = state.home;
    var quizzes = data.quizzes || [];
    var active = data.active || [];
    var history = data.history || [];
    var user = (tg && tg.initDataUnsafe && tg.initDataUnsafe.user) ? tg.initDataUnsafe.user : {};
    var name = user.first_name || 'دانش‌آموز';
    var avg = averagePercent(history);

    var html = '<section class="hero">'
      + '<p class="hero-kicker">خوش آمدی</p>'
      + '<h1>' + esc(name) + '</h1>'
      + '<p class="hero-sub">آزمون‌ها و نتایجت، یک‌جا و آماده.</p>'
      + '<div class="stat-row">'
      + '<div class="stat-chip"><strong>' + esc(fa(quizzes.length)) + '</strong><span>آزمون فعال</span></div>'
      + '<div class="stat-chip"><strong>' + esc(fa(history.length)) + '</strong><span>آزمون تمام‌شده</span></div>'
      + '<div class="stat-chip"><strong>' + esc(fa(avg)) + '٪</strong><span>میانگین نتایج</span></div>'
      + '</div></section>';

    if (state.tab === 'history') {
      html += '<section class="section">'
        + '<div class="section-head"><h2>نتایج من</h2>'
        + '<button type="button" class="link-btn" data-action="refresh">' + ICONS.refresh + ' بروزرسانی</button></div>';
      if (history.length === 0) {
        html += '<div class="empty">' + ICONS.empty + '<h3>هنوز نتیجه‌ای نداری</h3>'
          + '<p>بعد از اولین آزمون، نتیجه‌ات اینجا نمایش داده می‌شود.</p></div>';
      } else {
        html += '<div class="stack">';
        for (var i = 0; i < history.length; i++) {
          html += historyCard(history[i]);
        }
        html += '</div>';
      }
      html += '</section>';
    } else {
      if (active.length > 0) {
        html += '<section class="section">'
          + '<div class="section-head"><h2>در حال انجام</h2><span class="count">'
          + esc(fa(active.length)) + ' آزمون</span></div><div class="stack">';
        for (var a = 0; a < active.length; a++) {
          html += activeCard(active[a]);
        }
        html += '</div></section>';
      }

      html += '<section class="section">'
        + '<div class="section-head"><h2>آزمون‌های فعال</h2>'
        + '<button type="button" class="link-btn" data-action="refresh">' + ICONS.refresh + ' بروزرسانی</button></div>';
      if (quizzes.length === 0) {
        html += '<div class="empty">' + ICONS.empty + '<h3>آزمونی در دسترس نیست</h3>'
          + '<p>به‌محض فعال شدن آزمون جدید، اینجا نمایش داده می‌شود.</p></div>';
      } else {
        html += '<div class="stack">';
        for (var q = 0; q < quizzes.length; q++) {
          html += quizCard(quizzes[q]);
        }
        html += '</div>';
      }
      html += '</section>';
    }

    state.screen = 'home';
    view.innerHTML = html;
    syncChrome();
  }

  function renderEntry(quiz) {
    state.screen = 'entry';
    var hasCode = !!quiz.has_code;
    var meta = '<span class="meta-pill">' + ICONS.book + esc(fa(quiz.question_count)) + ' سوال</span>'
      + '<span class="meta-pill">' + ICONS.clock
      + (Number(quiz.time_limit) > 0 ? esc(faDur(quiz.time_limit)) : 'بدون محدودیت') + '</span>'
      + (hasCode ? '<span class="meta-pill locked">' + ICONS.lock + ' نیازمند کد آزمون</span>' : '');

    view.innerHTML = '<div class="screen-head">'
      + '<button type="button" class="back-btn" data-action="back-home">' + ICONS.arrowRight + ' بازگشت</button>'
      + '</div>'
      + '<section class="card entry-card">'
      + '<p class="eyebrow">شرکت در آزمون</p>'
      + '<h1>' + esc(quiz.title) + '</h1>'
      + (quiz.description ? '<p class="entry-desc">' + esc(quiz.description) + '</p>' : '')
      + '<div class="entry-meta">' + meta + '</div>'
      + '<form id="entryForm" novalidate>'
      + '<div class="form-grid">'
      + '<label class="field"><span>کد آزمون'
      + (hasCode ? '' : ' <span class="optional">(اختیاری)</span>') + '</span>'
      + '<input type="text" name="exam_code" dir="ltr" inputmode="text" autocomplete="off" spellcheck="false"'
      + (hasCode ? ' required' : '') + ' placeholder="مثلاً EXAM99">'
      + '<small>' + (hasCode
        ? 'کد ورود آزمون را از معلم یا ادمین بگیر.'
        : 'این آزمون کد ورود ندارد؛ می‌توانی خالی بگذاری.') + '</small></label>'
      + '<label class="field"><span>کد دانش‌آموزی</span>'
      + '<input type="text" name="student_code" dir="ltr" inputmode="text" autocomplete="off" spellcheck="false" required placeholder="مثلاً ST92K">'
      + '<small>کدی که ادمین برای تو ساخته است.</small></label>'
      + '</div>'
      + '<p class="form-error" id="entryError" role="alert"></p>'
      + '<button type="submit" class="btn primary block entry-submit">' + ICONS.play + ' شروع آزمون</button>'
      + '</form></section>';
    window.scrollTo(0, 0);
    syncChrome();
  }

  function showEntryError(message) {
    var el = document.getElementById('entryError');
    if (!el) {
      toast(message, 'err');
      return;
    }
    el.textContent = String(message || '');
    el.classList.add('show');
  }

  function renderExam() {
    if (!state.exam) {
      return;
    }
    var exam = state.exam;
    var question = exam.question;
    var options = question.options || [];
    var answered = {};
    for (var i = 0; i < (exam.answered_ids || []).length; i++) {
      answered[String(exam.answered_ids[i])] = true;
    }
    var progress = exam.total > 0 ? Math.round(((exam.index + 1) / exam.total) * 100) : 0;

    var optionsHtml = '';
    for (var o = 0; o < options.length; o++) {
      var option = options[o];
      var cls = 'option';
      if (option.selected) {
        cls += ' selected';
      }
      if (question.revealed) {
        if (option.correct) {
          cls += ' correct';
        } else if (option.wrong) {
          cls += ' wrong';
        }
      }
      var mark = '';
      if (question.revealed && option.correct) {
        mark = '<span class="option-mark">' + ICONS.check + '</span>';
      } else if (question.revealed && option.wrong) {
        mark = '<span class="option-mark">' + ICONS.close + '</span>';
      }
      optionsHtml += '<button type="button" class="' + cls + '" data-action="answer" data-question-id="'
        + Number(question.id) + '" data-option="' + esc(option.key) + '" aria-pressed="'
        + (option.selected ? 'true' : 'false') + '">'
        + '<span class="option-label">' + esc(option.label) + '</span>'
        + '<span class="option-text">' + esc(option.text) + '</span>'
        + mark + '</button>';
    }

    var note = '';
    if (question.revealed) {
      if (question.selected === question.correct) {
        note = '<div class="answer-note good">' + ICONS.check + '<span>پاسخ درست بود.</span></div>';
      } else {
        note = '<div class="answer-note bad">' + ICONS.close + '<span>پاسخ درست: '
          + esc(question.correct_label || question.correct) + '</span></div>';
      }
    }

    var palette = '';
    var ids = exam.question_ids || [];
    if (ids.length > 0) {
      for (var p = 0; p < ids.length; p++) {
        var pcls = 'palette-btn';
        if (answered[String(ids[p])]) {
          pcls += ' answered';
        }
        if (p === exam.index) {
          pcls += ' current';
        }
        palette += '<button type="button" class="' + pcls + '" data-action="nav" data-index="' + p
          + '" aria-label="سوال ' + esc(fa(p + 1)) + '">' + esc(fa(p + 1)) + '</button>';
      }
    } else {
      for (var n = 0; n < exam.total; n++) {
        palette += '<button type="button" class="palette-btn' + (n === exam.index ? ' current' : '')
          + '" data-action="nav" data-index="' + n + '" aria-label="سوال ' + esc(fa(n + 1)) + '">'
          + esc(fa(n + 1)) + '</button>';
      }
    }

    view.innerHTML = '<div class="exam-shell">'
      + '<header class="exam-top">'
      + '<button type="button" class="icon-btn" data-action="leave-exam" aria-label="خروج از آزمون">'
      + ICONS.arrowRight + '</button>'
      + '<div class="exam-top-main"><h1>' + esc(exam.quiz.title) + '</h1>'
      + '<p>' + esc(exam.student.name) + (exam.student.code ? ' · ' + esc(exam.student.code) : '') + '</p></div>'
      + '<div class="timer-pill" id="timerPill">' + ICONS.clock + '<span>—</span></div>'
      + '</header>'
      + '<section class="card progress-card">'
      + '<div class="progress-label"><span>سوال ' + esc(fa(exam.index + 1)) + ' از ' + esc(fa(exam.total))
      + '</span><b>' + esc(fa(exam.answered_count)) + ' پاسخ داده‌شده</b></div>'
      + '<div class="progress"><span id="examProgress"></span></div>'
      + '</section>'
      + '<section class="card question-card">'
      + '<span class="q-index">سوال ' + esc(fa(exam.index + 1)) + ' از ' + esc(fa(exam.total)) + '</span>'
      + '<p class="q-text">' + esc(question.text) + '</p>'
      + '<div class="options">' + optionsHtml + '</div>'
      + note
      + '</section>'
      + '<section class="card palette-card">'
      + '<div class="palette-head"><span>نمای کلی سوال‌ها</span><b>'
      + esc(fa(exam.answered_count)) + ' / ' + esc(fa(exam.total)) + '</b></div>'
      + '<div class="palette">' + palette + '</div>'
      + '</section>'
      + '</div>'
      + '<div class="exam-actions"><div class="exam-actions-inner">'
      + '<button type="button" class="btn nav-arrow" data-action="nav" data-index="' + (exam.index - 1)
      + '" aria-label="سوال قبلی"' + (exam.can_prev ? '' : ' disabled') + '>' + ICONS.arrowRight + '</button>'
      + '<button type="button" class="btn finish-btn" data-action="finish">پایان آزمون</button>'
      + '<button type="button" class="btn nav-arrow" data-action="nav" data-index="' + (exam.index + 1)
      + '" aria-label="سوال بعدی"' + (exam.can_next ? '' : ' disabled') + '>' + ICONS.arrowLeft + '</button>'
      + '</div></div>';

    var bar = document.getElementById('examProgress');
    if (bar) {
      bar.style.width = progress + '%';
    }
    updateTimerDisplay();
    window.scrollTo(0, 0);
    syncChrome();
  }

  function badSelect(selected) {
    if (!selected) {
      return '<div class="answer-box empty"><span>پاسخ تو</span><strong>بدون پاسخ</strong></div>';
    }
    return '<div class="answer-box bad"><span>پاسخ تو</span><strong>'
      + esc(selected.label) + ') ' + esc(selected.text) + '</strong></div>';
  }

  function renderResult(result) {
    stopTimer();
    state.result = result;
    state.exam = null;
    state.screen = 'result';
    state.finishing = false;
    state.busy = false;

    var pct = Number(result.percent) || 0;
    var ringClass = pct >= 80 ? 'good' : (pct >= 50 ? 'warn' : 'bad');
    var title = result.finish_reason === 'timeout' ? 'زمان آزمون تمام شد' : 'آزمون تمام شد';
    if (result.finish_reason === 'admin') {
      title = 'آزمون توسط ادمین بسته شد';
    }

    var html = '<section class="result-hero">'
      + '<p class="eyebrow">' + esc(title) + '</p>'
      + '<div class="score-ring ' + ringClass + '" id="scoreRing"><strong>' + esc(fa(pct)) + '٪</strong></div>'
      + '<h1>' + esc(result.quiz.title) + '</h1>'
      + '<p class="result-sub">' + esc(result.student.name)
      + (result.student.code ? ' · ' + esc(result.student.code) : '') + '</p>'
      + '<div class="result-stats">'
      + '<div class="result-stat"><strong>' + esc(fa(result.score)) + '</strong><span>پاسخ درست</span></div>'
      + '<div class="result-stat"><strong>' + esc(fa(result.total)) + '</strong><span>کل سوال</span></div>'
      + '<div class="result-stat"><strong>' + esc(faDur(result.spent)) + '</strong><span>زمان صرف‌شده</span></div>'
      + '</div></section>';

    if (result.show_details) {
      var wrong = result.wrong || [];
      if (wrong.length === 0) {
        html += '<section class="section"><div class="empty">' + ICONS.trophy
          + '<h3>عالی بود!</h3><p>همه پاسخ‌ها درست بود.</p></div></section>';
      } else {
        html += '<section class="section wrong-section">'
          + '<div class="section-head"><h2>مرور پاسخ‌های نادرست</h2><span class="count">'
          + esc(fa(wrong.length)) + ' مورد</span></div>';
        for (var i = 0; i < wrong.length; i++) {
          var item = wrong[i];
          html += '<article class="wrong-item">'
            + '<div class="wrong-head"><span class="wrong-num">' + esc(fa(item.n)) + '</span>'
            + '<p>' + esc(item.question) + '</p></div>'
            + '<div class="wrong-grid">'
            + badSelect(item.selected)
            + '<div class="answer-box good"><span>پاسخ درست</span><strong>'
            + esc(item.correct.label) + ') ' + esc(item.correct.text) + '</strong></div>'
            + '</div></article>';
        }
        html += '</section>';
      }
    }

    html += '<div class="result-actions">'
      + '<button type="button" class="btn primary block" data-action="back-home">' + ICONS.home + ' بازگشت به خانه</button>'
      + '<button type="button" class="btn ghost block" data-action="go-history">' + ICONS.chart + ' دیدن همه نتایج</button>'
      + '</div>';

    view.innerHTML = html;
    var ring = document.getElementById('scoreRing');
    if (ring) {
      ring.style.setProperty('--pct', String(Math.max(0, Math.min(100, pct))));
    }
    haptic('medium');
    window.scrollTo(0, 0);
    syncChrome();
  }

  function handleExamPayload(data) {
    if (!data) {
      return;
    }
    if (data.finished) {
      renderResult(data.result);
      return;
    }
    if (state.screen !== 'exam' && state.screen !== 'loading') {
      return;
    }
    state.exam = data.exam;
    state.screen = 'exam';
    renderExam();
    startTimer();
  }

  function loadExam(attemptId) {
    renderLoading('در حال آماده‌سازی آزمون…');
    api('state', { attempt_id: attemptId }).then(function (data) {
      handleExamPayload(data);
    }).catch(function (error) {
      toast(error.message, 'err');
      goHome();
    });
  }

  function openResult(attemptId) {
    renderLoading('در حال دریافت نتیجه…');
    api('result', { attempt_id: attemptId }).then(function (result) {
      renderResult(result);
    }).catch(function (error) {
      toast(error.message, 'err');
      goHome();
    });
  }

  function openEntry(quizId) {
    var quiz = null;
    var list = state.home && state.home.quizzes ? state.home.quizzes : [];
    for (var i = 0; i < list.length; i++) {
      if (Number(list[i].id) === Number(quizId)) {
        quiz = list[i];
        break;
      }
    }
    if (!quiz) {
      toast('این آزمون در دسترس نیست.', 'err');
      return;
    }
    if (Number(quiz.attempt_id) > 0) {
      loadExam(Number(quiz.attempt_id));
      return;
    }
    state.entryQuiz = quiz;
    state.finishing = false;
    renderEntry(quiz);
  }

  function submitEntry(form) {
    if (state.busy || !state.entryQuiz) {
      return;
    }
    var quiz = state.entryQuiz;
    var examCode = normalizeCode(form.elements.exam_code ? form.elements.exam_code.value : '');
    var studentCode = normalizeCode(form.elements.student_code ? form.elements.student_code.value : '');
    var errorEl = document.getElementById('entryError');
    if (errorEl) {
      errorEl.classList.remove('show');
      errorEl.textContent = '';
    }
    if (quiz.has_code && examCode === '') {
      showEntryError('کد آزمون را وارد کن.');
      return;
    }
    if (studentCode === '') {
      showEntryError('کد دانش‌آموزی را وارد کن.');
      return;
    }

    var button = form.querySelector('button[type="submit"]');
    state.busy = true;
    if (button) {
      button.disabled = true;
      button.innerHTML = ICONS.clock + ' در حال شروع…';
    }

    api('enter', {
      quiz_id: quiz.id,
      exam_code: examCode,
      student_code: studentCode
    }).then(function (data) {
      if (data.finished) {
        return api('result', { attempt_id: data.attempt_id }).then(function (result) {
          renderResult(result);
        });
      }
      loadExam(data.attempt_id);
      return null;
    }).catch(function (error) {
      showEntryError(error.message);
      toast(error.message, 'err');
      if (button && form.isConnected) {
        button.disabled = false;
        button.innerHTML = ICONS.play + ' شروع آزمون';
      }
    }).then(function () {
      state.busy = false;
    });
  }

  function answerQuestion(questionId, option) {
    if (state.busy || !state.exam) {
      return;
    }
    state.busy = true;
    api('answer', {
      attempt_id: state.exam.attempt_id,
      question_id: questionId,
      option: option
    }).then(function (data) {
      haptic('light');
      handleExamPayload(data);
    }).catch(function (error) {
      toast(error.message, 'err');
    }).then(function () {
      state.busy = false;
    });
  }

  function navigateExam(index) {
    if (state.busy || !state.exam) {
      return;
    }
    var target = Number(index);
    if (isNaN(target) || target < 0 || target >= state.exam.total || target === state.exam.index) {
      return;
    }
    state.busy = true;
    api('navigate', {
      attempt_id: state.exam.attempt_id,
      index: target
    }).then(function (data) {
      handleExamPayload(data);
    }).catch(function (error) {
      toast(error.message, 'err');
    }).then(function () {
      state.busy = false;
    });
  }

  function finishExam() {
    if (state.busy || state.finishing || !state.exam) {
      return;
    }
    var attemptId = state.exam.attempt_id;
    state.busy = true;
    state.finishing = true;
    api('finish', { attempt_id: attemptId }).then(function (data) {
      handleExamPayload(data);
    }).catch(function (error) {
      state.finishing = false;
      state.busy = false;
      toast(error.message, 'err');
    });
  }

  function autoFinish() {
    if (!state.exam || state.finishing) {
      return;
    }
    state.finishing = true;
    state.busy = true;
    api('finish', { attempt_id: state.exam.attempt_id }).then(function (data) {
      handleExamPayload(data);
      state.busy = false;
    }).catch(function () {
      state.busy = false;
      state.finishing = false;
      goHome();
    });
  }

  function confirmFinish() {
    if (!state.exam) {
      return;
    }
    var message = 'آزمون را تمام می‌کنی؟ سوالات بی‌پاسخ غلط حساب می‌شوند.';
    if (tg && tg.showConfirm) {
      tg.showConfirm(message, function (confirmed) {
        if (confirmed) {
          finishExam();
        }
      });
      return;
    }
    if (window.confirm(message)) {
      finishExam();
    }
  }

  function askLeaveExam() {
    var message = 'آزمون در جریان است. بدون پایان دادن به آن خارج می‌شوی؟';
    function leave() {
      state.busy = false;
      goHome();
    }
    if (tg && tg.showConfirm) {
      tg.showConfirm(message, function (confirmed) {
        if (confirmed) {
          leave();
        }
      });
      return;
    }
    if (window.confirm(message)) {
      leave();
    }
  }

  function goHome() {
    stopTimer();
    state.exam = null;
    state.result = null;
    state.entryQuiz = null;
    state.finishing = false;
    state.busy = false;
    state.screen = 'home';
    if (state.home) {
      renderHome();
    } else {
      renderLoading('در حال بارگذاری…');
    }
    api('home').then(function (data) {
      state.home = data;
      state.screen = 'home';
      renderHome();
    }).catch(function (error) {
      if (!state.home) {
        renderError(error);
      } else {
        toast(error.message, 'err');
      }
    });
  }

  function loadHome() {
    renderLoading('در حال بارگذاری…');
    api('home').then(function (data) {
      state.home = data;
      state.screen = 'home';
      renderHome();
    }).catch(function (error) {
      renderError(error);
    });
  }

  function refreshHome() {
    if (state.screen !== 'home') {
      return;
    }
    api('home').then(function (data) {
      state.home = data;
      renderHome();
    }).catch(function (error) {
      toast(error.message, 'err');
    });
  }

  function handleBack() {
    if (state.screen === 'home' || state.screen === 'outside') {
      return;
    }
    if (state.screen === 'exam') {
      askLeaveExam();
      return;
    }
    goHome();
  }

  function bindEvents() {
    if (themeBtn) {
      themeBtn.addEventListener('click', function () {
        var current = document.documentElement.getAttribute('data-theme');
        setTheme(current === 'dark' ? 'light' : 'dark', true);
        haptic('light');
      });
    }

    if (bottomNav) {
      bottomNav.addEventListener('click', function (event) {
        var button = event.target.closest ? event.target.closest('[data-tab]') : null;
        if (!button) {
          return;
        }
        state.tab = button.getAttribute('data-tab') === 'history' ? 'history' : 'exams';
        if (state.screen === 'home') {
          renderHome();
        } else {
          goHome();
        }
      });
    }

    if (view) {
      view.addEventListener('click', function (event) {
        var target = event.target.closest ? event.target.closest('[data-action]') : null;
        if (!target) {
          return;
        }
        if (target.hasAttribute('disabled')) {
          return;
        }
        var action = target.getAttribute('data-action');
        if (action === 'reload') {
          loadHome();
        } else if (action === 'refresh') {
          refreshHome();
        } else if (action === 'start') {
          openEntry(Number(target.getAttribute('data-quiz-id')));
        } else if (action === 'resume') {
          loadExam(Number(target.getAttribute('data-attempt-id')));
        } else if (action === 'open-result') {
          openResult(Number(target.getAttribute('data-attempt-id')));
        } else if (action === 'back-home') {
          goHome();
        } else if (action === 'go-history') {
          state.tab = 'history';
          goHome();
        } else if (action === 'answer') {
          answerQuestion(Number(target.getAttribute('data-question-id')), target.getAttribute('data-option'));
        } else if (action === 'nav') {
          navigateExam(Number(target.getAttribute('data-index')));
        } else if (action === 'finish') {
          confirmFinish();
        } else if (action === 'leave-exam') {
          askLeaveExam();
        }
      });

      view.addEventListener('submit', function (event) {
        if (event.target && event.target.id === 'entryForm') {
          event.preventDefault();
          submitEntry(event.target);
        }
      });
    }

    window.addEventListener('scroll', function () {
      if (topbar) {
        topbar.classList.toggle('scrolled', window.scrollY > 6);
      }
    }, { passive: true });
  }

  function initTelegram() {
    if (!tg) {
      return false;
    }
    try {
      tg.ready();
      tg.expand();
    } catch (e) {}
    try {
      if (tg.BackButton && tg.BackButton.onClick) {
        tg.BackButton.onClick(handleBack);
      }
    } catch (e) {}
    try {
      if (tg.onEvent) {
        tg.onEvent('themeChanged', function () {
          if (!storedTheme()) {
            setTheme(tg.colorScheme === 'dark' ? 'dark' : 'light', false);
          }
        });
      }
    } catch (e) {}
    return true;
  }

  function boot() {
    var theme = storedTheme();
    if (!theme) {
      theme = tg && tg.colorScheme === 'dark' ? 'dark' : 'light';
    }
    setTheme(theme, false);
    bindEvents();
    initTelegram();

    if (!tg || !tg.initData) {
      renderOutside('برای استفاده از مینی‌اپ، آن را از داخل ربات تلگرام باز کنید.');
      return;
    }
    loadHome();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
