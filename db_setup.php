<?php
/**
 * 1-Click Aiven Cloud MySQL Database Setup & Health Check Tool
 * Men's Salon Management System (MSMS)
 */
require_once('initialize.php');
require_once('classes/DBConnection.php');

$db = new DBConnection();
$conn = $db->conn;
$conn_error = $db->connect_error;

$expected_tables = array(
    'system_info'         => 'System Settings & Config',
    'users'               => 'Administrators & Staff Accounts',
    'service_list'        => 'Barber & Salon Services',
    'appointment_list'    => 'Appointments & Bookings',
    'appointment_service' => 'Appointment Service Mapping'
);

$sql_file = __DIR__ . '/database/msms_db.sql';
$action_msg = '';
$action_type = '';

function run_database_import($conn, $sql_file, &$action_msg, &$action_type) {
    if (!file_exists($sql_file)) {
        $action_msg = "SQL file not found at: {$sql_file}";
        $action_type = "danger";
        return false;
    }

    $sql_content = file_get_contents($sql_file);

    // Disable foreign key checks and strict zero dates for clean import
    @$conn->query("SET FOREIGN_KEY_CHECKS = 0");
    @$conn->query("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'");

    // Split SQL safely
    $queries = array();
    $lines = explode("\n", $sql_content);
    $query = '';
    $in_multiline_comment = false;

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') continue;

        // Skip single line comments
        if (substr($trimmed, 0, 2) === '--' || substr($trimmed, 0, 1) === '#') continue;

        // Handle multiline comments
        if (substr($trimmed, 0, 2) === '/*') {
            if (strpos($trimmed, '*/') !== false) {
                continue;
            }
            $in_multiline_comment = true;
            continue;
        }
        if ($in_multiline_comment) {
            if (strpos($trimmed, '*/') !== false) {
                $in_multiline_comment = false;
            }
            continue;
        }

        $query .= $line . "\n";
        if (substr($trimmed, -1) === ';') {
            $queries[] = $query;
            $query = '';
        }
    }

    $executed = 0;
    $errors = array();

    foreach ($queries as $q) {
        $q = trim($q);
        if (empty($q)) continue;
        try {
            if (!$conn->query($q)) {
                $errors[] = $conn->error;
            } else {
                $executed++;
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }

    @$conn->query("SET FOREIGN_KEY_CHECKS = 1");

    if ($executed > 0) {
        $action_msg = "Database successfully initialized! {$executed} SQL statements executed. All tables and default admin account are ready.";
        $action_type = "success";
        return true;
    } else {
        $action_msg = "Import encountered an issue. " . (!empty($errors) ? implode(', ', array_slice($errors, 0, 2)) : '');
        $action_type = "danger";
        return false;
    }
}

// Check if tables already exist
$initial_check = false;
if ($conn && !$conn->connect_errno) {
    try {
        $chk = @$conn->query("SHOW TABLES LIKE 'system_info'");
        $initial_check = ($chk && $chk->num_rows > 0);
    } catch (Throwable $e) {
        $initial_check = false;
    }
}

// Auto-run import if tables are missing OR if POST submitted
if ($conn && !$conn->connect_errno) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if ($_POST['action'] === 'reimport') {
            foreach ($expected_tables as $tbl => $desc) {
                @$conn->query("DROP TABLE IF EXISTS `{$tbl}`");
            }
        }
        run_database_import($conn, $sql_file, $action_msg, $action_type);
    } elseif (!$initial_check && !isset($_GET['skip_auto'])) {
        // Automatic 1-Click Import on first visit!
        run_database_import($conn, $sql_file, $action_msg, $action_type);
    }
}

// Fetch SSL Cipher Status if connected
$ssl_cipher = 'Disabled';
if ($conn && !$conn->connect_errno) {
    try {
        $ssl_res = @$conn->query("SHOW STATUS LIKE 'Ssl_cipher'");
        if ($ssl_res && $row = $ssl_res->fetch_assoc()) {
            if (!empty($row['Value'])) {
                $ssl_cipher = $row['Value'];
            }
        }
    } catch (Throwable $e) {}
}

// Fetch Table Status
$table_status = array();
$all_tables_exist = true;
$admin_exists = false;

if ($conn && !$conn->connect_errno) {
    foreach ($expected_tables as $tbl => $desc) {
        $exists = false;
        $count = 0;
        try {
            $check = @$conn->query("SHOW TABLES LIKE '{$tbl}'");
            $exists = ($check && $check->num_rows > 0);
            if ($exists) {
                $cnt_q = @$conn->query("SELECT COUNT(*) as cnt FROM `{$tbl}`");
                if ($cnt_q && $r = $cnt_q->fetch_assoc()) {
                    $count = (int)$r['cnt'];
                }
            } else {
                $all_tables_exist = false;
            }
        } catch (Throwable $e) {
            $all_tables_exist = false;
        }
        $table_status[$tbl] = array(
            'desc'   => $desc,
            'exists' => $exists,
            'count'  => $count
        );
    }

    if ($table_status['users']['exists']) {
        try {
            $adm_q = @$conn->query("SELECT id, username, type, status FROM `users` WHERE username = 'admin' LIMIT 1");
            if ($adm_q && $adm_q->num_rows > 0) {
                $admin_exists = true;
            }
        } catch (Throwable $e) {}
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Aiven Cloud MySQL Setup | MSMS</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="<?php echo base_url ?>plugins/fontawesome-free/css/all.min.css">
    <!-- AdminLTE Theme -->
    <link rel="stylesheet" href="<?php echo base_url ?>dist/css/adminlte.min.css">
    <style>
        body {
            background: #121418;
            color: #e9ecef;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px 0;
        }
        .setup-card {
            background: #1e2229;
            border: 1px solid #2d333b;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            width: 100%;
            max-width: 900px;
        }
        .header-gradient {
            background: linear-gradient(135deg, #1f6feb 0%, #1158c7 100%);
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
            padding: 24px;
        }
        .metric-box {
            background: #252b35;
            border: 1px solid #363d49;
            border-radius: 8px;
            padding: 14px 18px;
            margin-bottom: 12px;
        }
        .badge-ssl {
            background: #238636;
            color: #ffffff;
            font-size: 0.85rem;
            padding: 5px 10px;
            border-radius: 6px;
        }
        .badge-warn {
            background: #d29922;
            color: #121418;
            font-weight: 600;
        }
        .table-custom {
            color: #c9d1d9;
            background: #252b35;
            border-radius: 8px;
            overflow: hidden;
        }
        .table-custom th {
            background: #1c2128;
            border-bottom: 2px solid #30363d;
        }
        .table-custom td, .table-custom th {
            border-color: #30363d;
            vertical-align: middle;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="setup-card mx-auto">
        <!-- Header -->
        <div class="header-gradient text-white">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="mb-1 font-weight-bold"><i class="fas fa-database mr-2"></i> Aiven Cloud MySQL Setup & Importer</h3>
                    <p class="mb-0 text-white-50">Men's Salon Management System (MSMS) &bull; Render Cloud Deployment</p>
                </div>
                <div>
                    <?php if ($conn && !$conn->connect_errno): ?>
                        <span class="badge badge-success p-2 px-3"><i class="fas fa-check-circle mr-1"></i> Connected</span>
                    <?php else: ?>
                        <span class="badge badge-danger p-2 px-3"><i class="fas fa-times-circle mr-1"></i> Disconnected</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card-body p-4">
            <!-- Alert Messages -->
            <?php if (!empty($action_msg)): ?>
                <div class="alert alert-<?php echo $action_type ?> alert-dismissible fade show" role="alert">
                    <i class="fas <?php echo $action_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> mr-2"></i>
                    <?php echo htmlspecialchars($action_msg); ?>
                    <button type="button" class="close text-white" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>

            <!-- Connection Metrics Row -->
            <div class="row">
                <div class="col-md-6">
                    <div class="metric-box">
                        <div class="text-muted small">DATABASE HOST & PORT</div>
                        <div class="font-weight-bold text-white mt-1">
                            <?php echo htmlspecialchars(defined('DB_SERVER') ? DB_SERVER : 'localhost'); ?>:<?php echo htmlspecialchars(defined('DB_PORT') ? DB_PORT : 3306); ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="metric-box">
                        <div class="text-muted small">DATABASE NAME & USER</div>
                        <div class="font-weight-bold text-white mt-1">
                            <?php echo htmlspecialchars(defined('DB_NAME') ? DB_NAME : ''); ?> 
                            <span class="text-muted font-weight-normal">(User: <?php echo htmlspecialchars(defined('DB_USERNAME') ? DB_USERNAME : ''); ?>)</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="metric-box">
                        <div class="text-muted small">SSL ENCRYPTION (AIVEN REQUIREMENT)</div>
                        <div class="mt-1">
                            <?php if ($ssl_cipher !== 'Disabled'): ?>
                                <span class="badge-ssl"><i class="fas fa-lock mr-1"></i> TLS Active (<?php echo htmlspecialchars($ssl_cipher); ?>)</span>
                            <?php else: ?>
                                <span class="badge badge-secondary p-1 px-2"><i class="fas fa-lock-open mr-1"></i> Non-SSL / Local</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="metric-box">
                        <div class="text-muted small">BASE APP URL</div>
                        <div class="font-weight-bold text-white mt-1">
                            <a href="<?php echo base_url; ?>" class="text-info" target="_blank"><?php echo base_url; ?></a>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($conn_error)): ?>
                <div class="alert alert-danger mt-3">
                    <h5><i class="icon fas fa-ban mr-1"></i> Connection Error!</h5>
                    <?php echo htmlspecialchars($conn_error); ?>
                    <p class="mb-0 mt-2 small text-white-50">
                        Aiven MySQL connections require valid credentials and SSL encryption. Verify your Render Environment Variables (<code>DB_SERVER</code>, <code>DB_PORT</code>, <code>DB_USERNAME</code>, <code>DB_PASSWORD</code>, <code>DB_NAME</code>, <code>DB_SSL=true</code>).
                    </p>
                </div>
            <?php endif; ?>

            <!-- Table Health Check -->
            <h5 class="mt-4 mb-3 font-weight-bold"><i class="fas fa-table mr-2 text-primary"></i> Database Tables Status</h5>
            <div class="table-responsive">
                <table class="table table-custom mb-0">
                    <thead>
                        <tr>
                            <th>Table Name</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Total Rows</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expected_tables as $tbl => $desc): ?>
                            <?php 
                                $status = isset($table_status[$tbl]) ? $table_status[$tbl] : array('exists'=>false, 'count'=>0);
                            ?>
                            <tr>
                                <td><code><?php echo $tbl; ?></code></td>
                                <td><?php echo $desc; ?></td>
                                <td>
                                    <?php if ($status['exists']): ?>
                                        <span class="badge badge-success"><i class="fas fa-check mr-1"></i> Ready</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning badge-warn"><i class="fas fa-exclamation mr-1"></i> Missing</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge badge-secondary"><?php echo $status['count']; ?> records</span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Credentials Card -->
            <div class="card bg-dark border-secondary mt-4">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <div>
                            <span class="badge badge-info mr-2">Default Admin Account</span>
                            <strong>Username:</strong> <code>admin</code> &bull; 
                            <strong>Password:</strong> <code>admin123</code>
                        </div>
                        <div class="mt-2 mt-md-0">
                            <?php if ($admin_exists): ?>
                                <span class="text-success small font-weight-bold"><i class="fas fa-user-check mr-1"></i> Admin Account Verified</span>
                            <?php else: ?>
                                <span class="text-warning small font-weight-bold"><i class="fas fa-user-slash mr-1"></i> Admin Not Found (Run Import)</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Controls -->
            <div class="mt-4 pt-2 border-top border-secondary d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <?php if (!$all_tables_exist): ?>
                        <form method="POST" style="display:inline-block;">
                            <input type="hidden" name="action" value="import">
                            <button type="submit" class="btn btn-primary btn-lg px-4 font-weight-bold shadow">
                                <i class="fas fa-cloud-upload-alt mr-2"></i> 1-Click Import Database (msms_db.sql)
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="POST" style="display:inline-block;" onsubmit="return confirm('Warning: Re-importing will reset all database tables to initial state. Are you sure?');">
                            <input type="hidden" name="action" value="reimport">
                            <button type="submit" class="btn btn-outline-warning">
                                <i class="fas fa-redo-alt mr-1"></i> Reset & Re-import Database
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="mt-3 mt-md-0">
                    <a href="<?php echo base_url; ?>index.php" class="btn btn-outline-light mr-2">
                        <i class="fas fa-globe mr-1"></i> Go to Website
                    </a>
                    <a href="<?php echo base_url; ?>admin/login.php" class="btn btn-success btn-lg px-4 font-weight-bold shadow">
                        <i class="fas fa-sign-in-alt mr-2"></i> Admin Login
                    </a>
                </div>
            </div>
        </div>
        <!-- Card Footer -->
        <div class="card-footer bg-transparent border-top border-secondary text-center text-muted small py-3">
            MSMS Render Deploy &bull; Aiven Cloud MySQL Connector v2.0
        </div>
    </div>
</div>

<!-- jQuery & Bootstrap -->
<script src="<?php echo base_url ?>plugins/jquery/jquery.min.js"></script>
<script src="<?php echo base_url ?>plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
