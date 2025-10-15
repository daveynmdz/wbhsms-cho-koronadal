<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Environment Switcher - WBHSMS</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .card { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 10px 0; border-left: 4px solid #007bff; }
        .production { border-left-color: #dc3545; }
        .local { border-left-color: #28a745; }
        .btn { padding: 10px 20px; margin: 5px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .status { padding: 10px; border-radius: 4px; margin: 10px 0; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
    </style>
</head>
<body>
    <h1>🔄 Environment Switcher</h1>
    <p>Switch between local development and production environments without modifying core files.</p>

    <?php
    $root_path = dirname(__DIR__, 2);
    $env_local_path = $root_path . '/.env.local';
    $env_backup_path = $root_path . '/.env.local.backup';
    
    // Handle environment switching
    if ($_POST['action'] ?? false) {
        $action = $_POST['action'];
        $message = '';
        $type = '';
        
        if ($action === 'switch_to_local') {
            if (!file_exists($env_local_path)) {
                $message = 'Error: .env.local file not found. Cannot switch to local environment.';
                $type = 'error';
            } else {
                $message = 'Switched to LOCAL DEVELOPMENT environment. Your app now uses XAMPP database.';
                $type = 'success';
            }
        } elseif ($action === 'switch_to_production') {
            if (file_exists($env_local_path)) {
                // Backup the current .env.local
                if (!file_exists($env_backup_path)) {
                    copy($env_local_path, $env_backup_path);
                }
                // Rename .env.local to disable it
                rename($env_local_path, $root_path . '/.env.local.disabled');
                $message = 'Switched to PRODUCTION environment. Your app now uses production database.';
                $type = 'warning';
            } else {
                $message = 'Already using PRODUCTION environment.';
                $type = 'info';
            }
        } elseif ($action === 'restore_local') {
            if (file_exists($root_path . '/.env.local.disabled')) {
                rename($root_path . '/.env.local.disabled', $env_local_path);
                $message = 'Restored LOCAL DEVELOPMENT environment.';
                $type = 'success';
            } elseif (file_exists($env_backup_path)) {
                copy($env_backup_path, $env_local_path);
                $message = 'Restored LOCAL DEVELOPMENT environment from backup.';
                $type = 'success';
            } else {
                $message = 'No local environment backup found.';
                $type = 'error';
            }
        }
        
        if ($message) {
            echo "<div class='status $type'>$message</div>";
        }
    }
    
    // Determine current environment
    $current_env = 'PRODUCTION';
    $env_status = 'production';
    if (file_exists($env_local_path)) {
        $current_env = 'LOCAL DEVELOPMENT';
        $env_status = 'local';
    }
    ?>

    <div class="card <?php echo $env_status; ?>">
        <h3>🎯 Current Environment: <?php echo $current_env; ?></h3>
        <?php if ($env_status === 'local'): ?>
            <p><strong>Database:</strong> Local XAMPP (localhost:3306)</p>
            <p><strong>Debug Mode:</strong> Enabled</p>
            <p><strong>URL:</strong> http://localhost/wbhsms-cho-koronadal-1</p>
        <?php else: ?>
            <p><strong>Database:</strong> Production Server</p>
            <p><strong>Debug Mode:</strong> Disabled</p>
            <p><strong>URL:</strong> Production Domain</p>
        <?php endif; ?>
    </div>

    <h3>🔄 Environment Actions</h3>
    
    <form method="POST" style="display: inline;">
        <input type="hidden" name="action" value="switch_to_local">
        <button type="submit" class="btn btn-success" 
                <?php echo ($env_status === 'local') ? 'disabled' : ''; ?>>
            🏠 Switch to Local Development
        </button>
    </form>
    
    <form method="POST" style="display: inline;">
        <input type="hidden" name="action" value="switch_to_production">
        <button type="submit" class="btn btn-danger"
                <?php echo ($env_status === 'production') ? 'disabled' : ''; ?>>
            🚀 Switch to Production
        </button>
    </form>
    
    <?php if (file_exists($root_path . '/.env.local.disabled') || file_exists($env_backup_path)): ?>
    <form method="POST" style="display: inline;">
        <input type="hidden" name="action" value="restore_local">
        <button type="submit" class="btn btn-primary">
            🔄 Restore Local Environment
        </button>
    </form>
    <?php endif; ?>

    <h3>📋 How It Works</h3>
    <ul>
        <li><strong>Local Development:</strong> Uses <code>.env.local</code> file (XAMPP database)</li>
        <li><strong>Production:</strong> Uses <code>.env</code> file (production database)</li>
        <li><strong>Switching:</strong> Simply enables/disables the <code>.env.local</code> file</li>
        <li><strong>No Core Changes:</strong> Your <code>db.php</code> and <code>env.php</code> remain untouched</li>
    </ul>

    <div class="card">
        <h4>🔒 Security Notes</h4>
        <p><strong>Important:</strong> Delete this file before deploying to production!</p>
        <p>This tool is for development environments only.</p>
    </div>

    <p><a href="../../index.php" class="btn btn-primary">← Back to Application</a></p>
</body>
</html>