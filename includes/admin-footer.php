<?php
// Shared admin footer.
?>
<footer>
    <p><a href="https://pureblog.org">Pure Blog</a> was created with 💙 by <a href="https://kevquirk.com">Kev Quirk</a>.</p>

    <p><a href="https://pureblog.org/docs">Read the docs</a> | <a href="https://fosstodon.org/@purecommons">Find us on Mastodon</a> | <a href="https://github.com/kevquirk/pureblog">Source code</a></p>
</footer>
<script>
    document.addEventListener('keydown', (event) => {
        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 's') {
            const saveForm =
                document.getElementById('editor-form') ||
                document.getElementById('page-form') ||
                document.getElementById('settings-form');
            if (!saveForm) {
                return;
            }
            event.preventDefault();
            saveForm.requestSubmit();
        }
    });

    const notices = document.querySelectorAll('[data-auto-dismiss]');
    if (notices.length) {
        setTimeout(() => {
            notices.forEach((notice) => notice.remove());
            const url = new URL(window.location.href);
            ['saved', 'deleted', 'uploaded', 'upload_error', 'updated', 'setup'].forEach((param) => {
                url.searchParams.delete(param);
            });
            window.history.replaceState({}, document.title, url.toString());
        }, 2500);
    }
</script>
</body>
</html>
