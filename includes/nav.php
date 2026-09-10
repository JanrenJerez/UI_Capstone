<?php
$isKiosk = isset($_GET['mode']) && $_GET['mode'] === 'kiosk';
$base_path = (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/capstone') === 0) ? '/capstone' : '';
?>

<?php if (!$isKiosk): ?>
<nav style="
    background: rgba(15, 23, 42, 0.95); 
    backdrop-filter: blur(10px);
    padding: 15px 40px; 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    position: sticky;
    top: 0;
    z-index: 1000;
    font-family: 'Segoe UI', sans-serif;
">
    <div style="font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-weight: 900; color: #ff0000; font-size: 1.4rem; letter-spacing: 1px;">
    <a style="text-decoration: none !important; color: red !important;" href="<?php echo $base_path; ?>/pos/dashboard.php">PROJECT -E<span style="color: white;"> <img src="<?php echo $base_path; ?>/assets/logo.jpg" alt="Logo" style="height: 2em; width: auto; vertical-align: middle; margin-left: 4px;"></span></a>
</div>

    <ul id="dynamicNav" style="list-style: none; display: flex; gap: 20px; margin: 0; padding: 0; align-items: center;">
        </ul>
</nav>

<style>
    #dynamicNav a {
        color: white;
        text-decoration: none;
        font-size: 0.85rem;
        font-weight: 500;
        transition: 0.3s;
    }
    #dynamicNav a:hover {
        color: #38bdf8;
    }
</style>

<script>
    if (!window.location.search.includes('mode=kiosk')) {
        const navLinks = document.getElementById('dynamicNav');

        <?php if(isset($_SESSION) && isset($_SESSION['isAdminLoggedIn'])): ?>
            sessionStorage.setItem('isAdminLoggedIn', 'true');
        <?php else: ?>
            sessionStorage.removeItem('isAdminLoggedIn');
        <?php endif; ?>

        const isLoggedIn = sessionStorage.getItem('isAdminLoggedIn') === 'true';
        const basePath = '<?php echo $base_path; ?>';

        if (isLoggedIn) {
            navLinks.innerHTML = `
                <li><a href="${basePath}/pos/dashboard.php">Dashboard</a></li>
                <li><a href="${basePath}/admin/Manage.php" style="font-weight: bold">MANAGE MEMBERS</a></li>
                <li><a href="${basePath}/admin/register.php">Register</a></li>
                <li><a href="${basePath}/pos/sales.php">Products</a></li>
                <li><a href="${basePath}/admin/staff.php">Staff</a></li>
                <li><a href="${basePath}/logout.php" style="color: #ef4444; font-weight: bold;">Log out</a></li>
            `;
        } else {
            navLinks.innerHTML = `
                <li><a href="${basePath}/index.php">Home</a></li>
                <li><a href="${basePath}/login.php">Admin Login</a></li>
            `;
        }
    }
</script>
<?php endif; ?> 