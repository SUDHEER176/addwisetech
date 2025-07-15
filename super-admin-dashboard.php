<?php
session_start();

// Check if super admin is logged in
if (!isset($_SESSION['super_admin_id'])) {
    header("Location: super-admin-login.php");
    exit();
}

// Database connection
$host = "localhost";
$user = "root";
$password = "";
$dbname = "addwise";

try {
    $conn = new mysqli($host, $user, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    error_log($e->getMessage());
    die("Database connection error. Please try again later.");
}

// Handle admin actions
$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_admin':
                $username = filter_var(trim($_POST['username']), FILTER_SANITIZE_STRING);
                $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
                $password = trim($_POST['password']);
                
                if (empty($username) || empty($email) || empty($password)) {
                    $message = "All fields are required.";
                    $messageType = "error";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $role = 'admin'; // Set default role as admin
                    $sql = "INSERT INTO admins (username, email, password, role) VALUES (?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssss", $username, $email, $hashed_password, $role);
                    
                    if ($stmt->execute()) {
                        $message = "Admin added successfully.";
                        $messageType = "success";
                    } else {
                        $message = "Error adding admin: " . $conn->error;
                        $messageType = "error";
                    }
                    $stmt->close();
                }
                break;

            case 'toggle_admin':
                $admin_id = (int)$_POST['admin_id'];
                $new_status = $_POST['current_status'] == 1 ? 0 : 1;
                
                $sql = "UPDATE admins SET is_active = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $new_status, $admin_id);
                
                if ($stmt->execute()) {
                    $message = $new_status ? "Admin activated successfully." : "Admin suspended successfully.";
                    $messageType = "success";
                } else {
                    $message = "Error updating admin status.";
                    $messageType = "error";
                }
                $stmt->close();
                break;
        }
    }
}

// Get statistics
$stats = [
    'total_devices' => 0,
    'total_users' => 0,
    'total_codes' => 0
];

// Get total devices
try {
    $result = $conn->query("SELECT COUNT(*) as count FROM devices");
    if ($row = $result->fetch_assoc()) {
        $stats['total_devices'] = $row['count'];
    }
} catch (Exception $e) {
    // Table doesn't exist yet, count remains 0
}

// Get total users
try {
    $result = $conn->query("SELECT COUNT(*) as count FROM users");
    if ($row = $result->fetch_assoc()) {
        $stats['total_users'] = $row['count'];
    }
} catch (Exception $e) {
    // Table doesn't exist yet, count remains 0
}

// Get total codes
$totalCodes = 0;
$result = $conn->query("SELECT COUNT(*) AS total FROM codes");
if ($result && $row = $result->fetch_assoc()) {
    $totalCodes = $row['total'];
}

// Get list of admins
$admins = [];
try {
    $result = $conn->query("SELECT id, username, email, created_at, last_login, is_active FROM admins ORDER BY created_at DESC");
    while ($row = $result->fetch_assoc()) {
        $admins[] = $row;
    }
} catch (Exception $e) {
    // Table doesn't exist yet, array remains empty
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AddWise - Super Admin Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-light: #3b82f6;
            --success-color: #059669;
            --error-color: #dc2626;
            --warning-color: #d97706;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --text-light: #9ca3af;
            --bg-primary: #ffffff;
            --bg-secondary: #f9fafb;
            --bg-tertiary: #f3f4f6;
            --border-color: #d1d5db;
            --border-light: #e5e7eb;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --radius-sm: 6px;
            --radius-md: 8px;
            --radius-lg: 12px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-secondary);
            min-height: 100vh;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px;
            background: var(--bg-primary);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
        }

        .header h1 {
            font-size: 1.8rem;
            color: var(--text-primary);
        }

        .logout-btn {
            padding: 8px 16px;
            background: var(--error-color);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .logout-btn:hover {
            background: #b91c1c;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--bg-primary);
            padding: 20px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
        }

        .stat-card h3 {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-bottom: 8px;
        }

        .stat-card .value {
            font-size: 2rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .admin-section {
            background: var(--bg-primary);
            padding: 20px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-header h2 {
            font-size: 1.5rem;
            color: var(--text-primary);
        }

        .add-admin-btn {
            padding: 8px 16px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .add-admin-btn:hover {
            background: var(--primary-dark);
        }

        .admin-table {
            width: 100%;
            border-collapse: collapse;
        }

        .admin-table th,
        .admin-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border-light);
        }

        .admin-table th {
            font-weight: 500;
            color: var(--text-secondary);
            background: var(--bg-secondary);
        }

        .admin-table tr:last-child td {
            border-bottom: none;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            font-weight: 500;
        }

        .status-active {
            background: #dcfce7;
            color: #166534;
        }

        .status-suspended {
            background: #fee2e2;
            color: #991b1b;
        }

        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .suspend-btn {
            background: var(--warning-color);
            color: white;
        }

        .activate-btn {
            background: var(--success-color);
            color: white;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--bg-primary);
            padding: 30px;
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 500px;
            box-shadow: var(--shadow-lg);
        }

        .modal-header {
            margin-bottom: 20px;
        }

        .modal-header h2 {
            font-size: 1.5rem;
            color: var(--text-primary);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 6px;
            color: var(--text-primary);
            font-weight: 500;
        }

        .form-input {
            width: 100%;
            padding: 10px;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 1rem;
            transition: all 0.2s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .cancel-btn {
            padding: 8px 16px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-weight: 500;
        }

        .submit-btn {
            padding: 8px 16px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-weight: 500;
        }

        .message {
            padding: 12px 16px;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .error {
            color: #991b1b;
            background: #fef2f2;
            border: 1px solid #fecaca;
        }

        .success {
            color: #065f46;
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --text-primary: #f9fafb;
                --text-secondary: #d1d5db;
                --text-light: #9ca3af;
                --bg-primary: #1f2937;
                --bg-secondary: #374151;
                --bg-tertiary: #4b5563;
                --border-color: #4b5563;
                --border-light: #374151;
            }

            body {
                background: #111827;
            }

            .status-active {
                background: #064e3b;
                color: #a7f3d0;
            }

            .status-suspended {
                background: #7f1d1d;
                color: #fecaca;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Super Admin Dashboard</h1>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <span><?php echo $messageType === 'success' ? '✅' : '⚠️'; ?></span>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Devices</h3>
                <div class="value"><?php echo number_format($stats['total_devices']); ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Users</h3>
                <div class="value"><?php echo number_format($stats['total_users']); ?></div>
            </div>
             
        </div>

        <div class="admin-section">
            <div class="section-header">
                <h2>Admin Management</h2>
                <button class="add-admin-btn" onclick="openAddAdminModal()">Add New Admin</button>
            </div>

            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Created At</th>
                        <th>Last Login</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($admins as $admin): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($admin['username']); ?></td>
                            <td><?php echo htmlspecialchars($admin['email']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($admin['created_at'])); ?></td>
                            <td><?php echo $admin['last_login'] ? date('M d, Y H:i', strtotime($admin['last_login'])) : 'Never'; ?></td>
                            <td>
                                <span class="status-badge <?php echo $admin['is_active'] ? 'status-active' : 'status-suspended'; ?>">
                                    <?php echo $admin['is_active'] ? 'Active' : 'Suspended'; ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="toggle_admin">
                                    <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                    <input type="hidden" name="current_status" value="<?php echo $admin['is_active']; ?>">
                                    <button type="submit" class="action-btn <?php echo $admin['is_active'] ? 'suspend-btn' : 'activate-btn'; ?>">
                                        <?php echo $admin['is_active'] ? 'Suspend' : 'Activate'; ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Admin Modal -->
    <div id="addAdminModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Admin</h2>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_admin">
                
                <div class="form-group">
                    <label class="form-label" for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-input" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-input" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-input" required>
                </div>

                <div class="modal-footer">
                    <button type="button" class="cancel-btn" onclick="closeAddAdminModal()">Cancel</button>
                    <button type="submit" class="submit-btn">Add Admin</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddAdminModal() {
            document.getElementById('addAdminModal').style.display = 'flex';
        }

        function closeAddAdminModal() {
            document.getElementById('addAdminModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('addAdminModal');
            if (event.target === modal) {
                closeAddAdminModal();
            }
        }

        // Auto-hide messages after 5 seconds
        setTimeout(function() {
            const messages = document.querySelectorAll('.message');
            messages.forEach(msg => {
                msg.style.opacity = '0';
                msg.style.transition = 'opacity 0.5s ease';
                setTimeout(() => msg.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>