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
        <a style="text-decoration: none !important; color: red !important;" href="../index.php">PROJECT -E<span style="color: white;"> <img src="../assets/logo.jpg" alt="Logo" style="height: 2em; width: auto; vertical-align: middle; margin-left: 4px;"></span></a>
    </div>

    <ul style="list-style: none; display: flex; gap: 20px; margin: 0; padding: 0; align-items: center;" id="staticNav">
        <li><a href="../index.php" style="font-weight: bold">Home</a></li>
        <li><a href="../admin/manage.php" style="font-weight: bold">MANAGE MEMBERS</a></li>
        <li><a href="../pos/sales.php">Products</a></li>
        <li><a href="../pos/expenses.php">Expenses</a></li>
        <li><a href="../admin/staff.php">Staff</a></li>
    </ul>
</nav>

<style>
    #staticNav a {
        color: white;
        text-decoration: none;
        font-size: 0.85rem;
        font-weight: 500;
        transition: 0.3s;
    }
    #staticNav a:hover {
        color: #38bdf8;
    }
</style>