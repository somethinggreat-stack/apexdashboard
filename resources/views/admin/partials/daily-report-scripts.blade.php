<script>
document.querySelectorAll('[data-copy-el]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var el = document.getElementById(btn.getAttribute('data-copy-el'));
        if (!el) return;
        var text = el.value;
        var done = function () {
            var old = btn.textContent; btn.textContent = 'Copied!';
            setTimeout(function () { btn.textContent = old; }, 1500);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done, function () { el.focus(); el.select(); document.execCommand('copy'); done(); });
        } else {
            el.focus(); el.select(); document.execCommand('copy'); done();
        }
    });
});
</script>
