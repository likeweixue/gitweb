/* =============================================================
   Gitweb 开机启动动画控制器 · dsh-startup-screen 风格
   六幕：黑幕标识 → 过曝洗白 → 暖白档案底板 → 五步授权 → 色散揭幕 → 欢迎
   ============================================================= */
(function () {
    var boot = document.getElementById('gw-boot');
    if (!boot) return;

    var STORAGE_KEY = 'gw_boot_seen_v1';
    var force = /[?&]splash=1\b/.test(location.search);
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function finish(immediate) {
        if (boot.dataset.done) return;
        boot.dataset.done = '1';
        try { sessionStorage.setItem(STORAGE_KEY, '1'); } catch (e) {}
        if (immediate || reduce) {
            boot.style.display = 'none';
            if (boot.parentNode) boot.parentNode.removeChild(boot);
            return;
        }
        boot.classList.add('is-done');
        window.setTimeout(function () {
            if (boot.parentNode) boot.parentNode.removeChild(boot);
        }, 700);
    }

    // 已看过且未强制重播：直接跳过，避免每次导航都播放
    var seen = false;
    try { seen = sessionStorage.getItem(STORAGE_KEY) === '1'; } catch (e) {}
    if (seen && !force) { finish(true); return; }

    var stepsEl = document.getElementById('gw-steps');
    var bar = document.getElementById('gw-progress-bar');
    var skipBtn = document.getElementById('gw-boot-skip');

    var STEPS = [
        { zh: '需要访问许可', en: 'ACCESS PERMISSION REQUIRED' },
        { zh: '身份资料确认', en: 'IDENTITY VERIFICATION' },
        { zh: '接收访问需求', en: 'ACCESS REQUEST RECEIVED' },
        { zh: '开始读取权限', en: 'PERMISSION READ STARTED' },
        { zh: '读取完毕 · 核验通过', en: 'READ COMPLETE · VERIFIED' }
    ];

    // 构建步骤行
    var rows = [];
    STEPS.forEach(function (s) {
        var row = document.createElement('div');
        row.className = 'gw-step';
        row.innerHTML =
            '<span class="gw-step-dot"></span>' +
            '<span class="gw-step-body">' +
            '<span class="gw-step-zh is-pending">' + s.zh + '</span>' +
            '<span class="gw-step-en"></span>' +
            '</span>';
        stepsEl.appendChild(row);
        rows.push(row);
    });

    function typeEn(el, text, done) {
        el.textContent = '';
        var caret = document.createElement('span');
        caret.className = 'gw-caret';
        caret.innerHTML = '&nbsp;';
        el.appendChild(caret);
        var i = 0;
        (function tick() {
            if (i <= text.length) {
                el.textContent = text.slice(0, i);
                el.appendChild(caret);
                i++;
                setTimeout(tick, 26);
            } else {
                caret.remove();
                if (done) done();
            }
        })();
    }

    var timers = [];
    function at(ms, fn) { timers.push(setTimeout(fn, ms)); }

    // 跳过 / Esc
    function onSkip() { timers.forEach(clearTimeout); finish(false); }
    if (skipBtn) skipBtn.addEventListener('click', onSkip);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') onSkip();
    });
    // 点击准星也可跳过
    boot.addEventListener('click', function (e) {
        if (e.target === boot || e.target.classList.contains('gw-boot-grid')) onSkip();
    });

    // 时间线
    at(120, function () { boot.classList.add('act-brand'); });
    at(950, function () { boot.classList.add('act-flash'); });
    at(1700, function () { boot.classList.add('act-dossier'); runSteps(); });
    at(4700, function () { boot.classList.add('act-reveal'); });
    at(5450, function () { finish(false); });

    var stepDur = 560;
    function runSteps() {
        rows.forEach(function (row, idx) {
            at(1700 + idx * stepDur, function () {
                row.classList.add('is-active');
                var zh = row.querySelector('.gw-step-zh');
                zh.classList.remove('is-pending');
                var en = row.querySelector('.gw-step-en');
                typeEn(en, STEPS[idx].en, function () {
                    row.classList.remove('is-active');
                    row.classList.add('is-done');
                    if (bar) bar.style.width = Math.round(((idx + 1) / STEPS.length) * 100) + '%';
                });
            });
        });
    }
})();
