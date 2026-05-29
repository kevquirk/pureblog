<?php
// Shared admin footer.
?>
<footer>
    <p><a href="https://pureblog.org">Pure Blog</a> <?= e(t('admin.footer.created_by')) ?> <a href="https://kevquirk.com">Kev Quirk</a>.</p>

    <p><a href="https://pureblog.org/docs"><?= e(t('admin.footer.docs')) ?></a> | <a href="https://fosstodon.org/@purecommons"><?= e(t('admin.footer.mastodon')) ?></a> | <a href="https://codeberg.org/kevquirk/pureblog"><?= e(t('admin.footer.source')) ?></a></p>
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

    const sidebarLayoutPicker = document.getElementById('sidebar-layout-picker');
    const sidebarLayoutPickerClose = document.getElementById('layout-picker-close');
    if (sidebarLayoutPicker) {
        document.querySelectorAll('.js-open-layout-picker').forEach(btn => {
            btn.addEventListener('click', () => sidebarLayoutPicker.showModal());
        });
        if (sidebarLayoutPickerClose) {
            sidebarLayoutPickerClose.addEventListener('click', () => sidebarLayoutPicker.close());
        }
        sidebarLayoutPicker.addEventListener('click', (e) => { if (e.target === sidebarLayoutPicker) sidebarLayoutPicker.close(); });
    }

    const sidebarToggle = document.getElementById('sidebar-toggle');
    const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
    const sidebarOverlay = document.getElementById('sidebar-overlay');
    const isMobile = () => window.matchMedia('(max-width: 768px)').matches;

    function closeMobileNav() {
        document.documentElement.classList.remove('mobile-nav-open');
    }

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', () => {
            if (isMobile()) {
                closeMobileNav();
            } else {
                const collapsed = document.documentElement.getAttribute('data-sidebar') === 'collapsed';
                if (collapsed) {
                    document.documentElement.removeAttribute('data-sidebar');
                    try { localStorage.setItem('pb-sidebar', 'expanded'); } catch(e) {}
                } else {
                    document.documentElement.setAttribute('data-sidebar', 'collapsed');
                    try { localStorage.setItem('pb-sidebar', 'collapsed'); } catch(e) {}
                }
            }
        });
    }

    if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener('click', () => {
            document.documentElement.classList.add('mobile-nav-open');
        });
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', closeMobileNav);
    }
</script>
    </div><!-- /.admin-content -->
    </div><!-- /.admin-main -->
    </div><!-- /.admin-shell -->
</body>
</html>
