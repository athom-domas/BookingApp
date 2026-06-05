(function () {
    var menu     = document.getElementById('sf-mob-menu');
    var burger   = document.getElementById('sf-hamburger');
    var closeBtn = document.getElementById('sf-mob-close');

    if (burger)   burger.addEventListener('click',   function () { menu.classList.add('open'); });
    if (closeBtn) closeBtn.addEventListener('click', function () { menu.classList.remove('open'); });
    if (menu)     menu.querySelectorAll('a').forEach(function (a) {
        a.addEventListener('click', function () { menu.classList.remove('open'); });
    });

    // ── LIGHT / DARK MODE TOGGLE ──
    var root     = document.documentElement;
    var modeBtn  = document.getElementById('sf-mode-toggle');
    var iconMoon = document.getElementById('sf-icon-moon');
    var iconSun  = document.getElementById('sf-icon-sun');

    function syncIcon(isDark) {
        if (iconMoon) iconMoon.style.display = isDark ? 'none'  : 'block';
        if (iconSun)  iconSun.style.display  = isDark ? 'block' : 'none';
    }

    syncIcon(root.classList.contains('mode-dark'));

    if (modeBtn) {
        modeBtn.addEventListener('click', function () {
            var goingDark = !root.classList.contains('mode-dark');
            root.classList.toggle('mode-dark', goingDark);
            localStorage.setItem('sf-mode', goingDark ? 'dark' : 'light');
            syncIcon(goingDark);
        });
    }
})();
