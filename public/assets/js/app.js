/**
 * Gitweb 前端交互（零依赖）
 * 下拉菜单 / 移动端导航 / 消息关闭 / 复制 / 危险确认 / clone 切换
 */
(function () {
    'use strict';

    // ---------- 下拉菜单 ----------
    document.querySelectorAll('[data-dropdown]').forEach(function (dropdown) {
        var button = dropdown.querySelector('button');
        if (!button) return;
        button.addEventListener('click', function (e) {
            e.stopPropagation();
            var isOpen = dropdown.classList.contains('open');
            document.querySelectorAll('[data-dropdown].open').forEach(function (d) {
                d.classList.remove('open');
            });
            dropdown.classList.toggle('open', !isOpen);
        });
    });
    document.addEventListener('click', function () {
        document.querySelectorAll('[data-dropdown].open').forEach(function (d) {
            d.classList.remove('open');
        });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('[data-dropdown].open').forEach(function (d) {
                d.classList.remove('open');
            });
        }
    });

    // ---------- 移动端导航 ----------
    var navToggle = document.getElementById('navToggle');
    var topnav = document.getElementById('topnav');
    if (navToggle && topnav) {
        navToggle.addEventListener('click', function () {
            topnav.classList.toggle('open');
        });
    }

    // ---------- 消息关闭 ----------
    document.querySelectorAll('[data-flash-close]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var flash = btn.closest('[data-flash]');
            if (flash) flash.remove();
        });
    });
    // 成功消息 5 秒后自动消失
    document.querySelectorAll('.flash-success').forEach(function (flash) {
        setTimeout(function () { flash.remove(); }, 5000);
    });

    // ---------- 复制到剪贴板 ----------
    function copyText(text, btn) {
        function done() {
            var original = btn.innerHTML;
            btn.innerHTML = '已复制';
            setTimeout(function () { btn.innerHTML = original; }, 1500);
        }
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done, function () { fallback(); });
        } else {
            fallback();
        }
        function fallback() {
            var input = document.createElement('input');
            input.value = text;
            document.body.appendChild(input);
            input.select();
            try { document.execCommand('copy'); done(); } catch (e) { /* ignore */ }
            input.remove();
        }
    }
    document.querySelectorAll('[data-copy]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            copyText(btn.getAttribute('data-copy') || '', btn);
        });
    });

    // ---------- 危险操作二次确认 ----------
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    });

    // ---------- clone 协议切换 ----------
    document.querySelectorAll('[data-clone-tab]').forEach(function (tab) {
        tab.addEventListener('click', function () {
            var target = tab.getAttribute('data-clone-tab');
            document.querySelectorAll('[data-clone-tab]').forEach(function (t) {
                t.classList.toggle('active', t === tab);
            });
            var https = document.getElementById('cloneUrlHttps');
            var ssh = document.getElementById('cloneUrlSsh');
            var copyBtn = document.getElementById('copyCloneBtn');
            if (target === 'ssh') {
                if (https) https.hidden = true;
                if (ssh) ssh.hidden = false;
                if (copyBtn && ssh) copyBtn.setAttribute('data-copy', ssh.value);
            } else {
                if (ssh) ssh.hidden = true;
                if (https) https.hidden = false;
                if (copyBtn && https) copyBtn.setAttribute('data-copy', https.value);
            }
        });
    });

    // ---------- 文本域 Tab 缩进 ----------
    document.querySelectorAll('textarea').forEach(function (textarea) {
        textarea.addEventListener('keydown', function (e) {
            if (e.key === 'Tab') {
                e.preventDefault();
                var start = textarea.selectionStart;
                var end = textarea.selectionEnd;
                textarea.value = textarea.value.slice(0, start) + '    ' + textarea.value.slice(end);
                textarea.selectionStart = textarea.selectionEnd = start + 4;
            }
        });
    });
})();
