<nav class="pe-nav">
    <a class="pe-brand" href="/index.php">PROJECT -E<span> <img src="/assets/logo.jpg" alt="Logo"></span></a>

    <ul id="staticNav">
        <li><a href="/index.php" style="font-weight: bold">Home</a></li>
        <li><a href="/admin/manage.php" style="font-weight: bold">MANAGE MEMBERS</a></li>
        <li><a href="/pos/sales.php">Products</a></li>
        <li><a href="/pos/expenses.php">Expenses</a></li>
        <li><a href="/admin/staff.php">Staff</a></li>
        <li><a href="/admin/settings.php">Settings</a></li>
        <li><a href="/logout.php" class="pe-lock-btn">🔒 Lock</a></li>
    </ul>
</nav>

<style>
    .pe-nav {
        background: rgba(15, 23, 42, 0.95);
        backdrop-filter: blur(10px);
        padding: 15px 40px;
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        position: sticky;
        top: 0;
        z-index: 1000;
        font-family: 'Segoe UI', sans-serif;
    }
    .pe-brand {
        font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        font-weight: 900; font-size: 1.4rem; letter-spacing: 1px;
        text-decoration: none !important; color: red !important;
    }
    .pe-brand span { color: white; }
    .pe-brand img { height: 2em; width: auto; vertical-align: middle; margin-left: 4px; }
    #staticNav { list-style: none; display: flex; flex-wrap: wrap; gap: 20px; margin: 0; padding: 0; align-items: center; }
    #staticNav a {
        color: white;
        text-decoration: none;
        font-size: 0.85rem;
        font-weight: 500;
        transition: 0.3s;
    }
    #staticNav a:hover { color: #38bdf8; }
    #staticNav a.pe-lock-btn { background: #4b5563; padding: 6px 12px; border-radius: 6px; font-weight: 700; }
    #staticNav a.pe-lock-btn:hover { background: #dc2626; color: #fff; }

    /* Shared pagination links (see render_pagination in includes/helpers.php) */
    .pagination { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; padding: 15px; }
    .pagination a, .pagination span.current {
        padding: 6px 12px; border-radius: 6px; font-size: 0.8rem; font-weight: 700; text-decoration: none;
        background: #262626; color: #a3a3a3; border: 1px solid #404040;
    }
    .pagination span.current { background: #dc2626; color: #fff; border-color: #dc2626; }
    .pagination .page-info { color: #737373; font-size: 0.8rem; margin-left: 8px; }

    @media (max-width: 800px) {
        .pe-nav { padding: 12px 16px; }
        #staticNav { gap: 12px; }
        #staticNav a { font-size: 0.8rem; }
    }
</style>

<script>
    // Lock the app after 5 minutes without mouse/keyboard activity
    (function () {
        const LOCK_AFTER_MS = 5 * 60 * 1000;
        let timer;
        function reset() {
            clearTimeout(timer);
            timer = setTimeout(() => { window.location.href = '/logout.php'; }, LOCK_AFTER_MS);
        }
        ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'].forEach(evt =>
            window.addEventListener(evt, reset, { passive: true })
        );
        reset();
    })();
</script>
