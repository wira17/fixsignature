<?php
session_start();
require_once '../config.php';
require_once 'check_admin.php';


$date_from = isset($_GET['from']) && !empty($_GET['from']) ? $_GET['from'] : date('Y-m-01');
$date_to = isset($_GET['to']) && !empty($_GET['to']) ? $_GET['to'] : date('Y-m-d');


$where_date = "DATE(signed_at) BETWEEN '$date_from' AND '$date_to'";


$docs_in_range = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM signed_documents WHERE $where_date"));
$total_size_query = mysqli_query($conn, "SELECT SUM(file_size) as total FROM signed_documents WHERE $where_date");
$total_size = mysqli_fetch_assoc($total_size_query)['total'] ?? 0;
$total_pages_query = mysqli_query($conn, "SELECT SUM(total_pages) as total FROM signed_documents WHERE $where_date");
$total_pages = mysqli_fetch_assoc($total_pages_query)['total'] ?? 0;


$tte_in_range = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM tte_signatures WHERE DATE(created_at) BETWEEN '$date_from' AND '$date_to'"));

$active_users_query = mysqli_query($conn, "SELECT COUNT(DISTINCT user_id) as total FROM signed_documents WHERE $where_date");
$active_users = mysqli_fetch_assoc($active_users_query)['total'] ?? 0;


$daily_docs_query = "SELECT DATE(signed_at) as date, COUNT(*) as count 
                     FROM signed_documents 
                     WHERE $where_date 
                     GROUP BY DATE(signed_at) 
                     ORDER BY date ASC";
$daily_docs = mysqli_query($conn, $daily_docs_query);
$chart_dates = [];
$chart_counts = [];
while ($row = mysqli_fetch_assoc($daily_docs)) {
    $chart_dates[] = date('d M', strtotime($row['date']));
    $chart_counts[] = $row['count'];
}


$top_users_query = "SELECT u.nama, u.email, COUNT(sd.id) as doc_count 
                    FROM users u 
                    LEFT JOIN signed_documents sd ON u.id = sd.user_id AND $where_date
                    WHERE u.role = 'user' 
                    GROUP BY u.id 
                    HAVING doc_count > 0
                    ORDER BY doc_count DESC 
                    LIMIT 10";
$top_users = mysqli_query($conn, $top_users_query);


$status_query = "SELECT status, COUNT(*) as count FROM signed_documents WHERE $where_date GROUP BY status";
$status_breakdown = mysqli_query($conn, $status_query);

// Overall system stats (all time)
$total_users_all = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM users WHERE role = 'user'"));
$total_docs_all = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM signed_documents"));
$total_tte_all = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM tte_signatures"));
$total_storage_all = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(file_size) as total FROM signed_documents"))['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan & Statistik - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
            color: #2c3e50;
            font-size: 14px;
        }

        .navbar {
            background: white;
            border-bottom: 1px solid #e8ecf1;
            padding: 14px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-icon {
            width: 32px;
            height: 32px;
            background: #dc2626;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-icon i {
            color: white;
            font-size: 16px;
        }

        .brand-name {
            color: #1e293b;
            font-size: 16px;
            font-weight: 500;
        }

        .btn-back {
            background: white;
            color: #64748b;
            border: 1px solid #e2e8f0;
            padding: 7px 14px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        .btn-back:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 24px;
        }

        .page-header {
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title {
            font-size: 20px;
            font-weight: 500;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .page-subtitle {
            font-size: 13px;
            color: #64748b;
        }

        .btn-export {
            background: #6366f1;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            border: none;
            font-weight: 500;
        }

        .btn-export:hover {
            background: #4f46e5;
        }

        .date-filter {
            background: white;
            border: 1px solid #e8ecf1;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .filter-form {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 12px;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-label {
            font-size: 12px;
            font-weight: 500;
            color: #64748b;
        }

        .form-control {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 13px;
            outline: none;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .btn-primary {
            background: #6366f1;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            border: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-primary:hover {
            background: #4f46e5;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            border: 1px solid #e8ecf1;
            border-radius: 8px;
            padding: 20px;
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .stat-icon.blue { background: #eff6ff; color: #3b82f6; }
        .stat-icon.green { background: #f0fdf4; color: #22c55e; }
        .stat-icon.purple { background: #faf5ff; color: #a855f7; }
        .stat-icon.orange { background: #fff7ed; color: #f97316; }

        .stat-label {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .card {
            background: white;
            border: 1px solid #e8ecf1;
            border-radius: 8px;
            overflow: hidden;
        }

        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid #e8ecf1;
        }

        .card-title {
            font-size: 15px;
            font-weight: 500;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-title i {
            color: #6366f1;
        }

        .card-body {
            padding: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 10px 12px;
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            background: #f8fafc;
            border-bottom: 1px solid #e8ecf1;
        }

        td {
            padding: 12px;
            font-size: 13px;
            border-bottom: 1px solid #f1f5f9;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-primary {
            background: #dbeafe;
            color: #1e40af;
        }

        .system-stats {
            background: white;
            border: 1px solid #e8ecf1;
            border-radius: 8px;
            padding: 20px;
        }

        .system-stats h3 {
            font-size: 15px;
            font-weight: 500;
            color: #1e293b;
            margin-bottom: 16px;
        }

        .stat-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .stat-row:last-child {
            border-bottom: none;
        }

        .stat-row-label {
            font-size: 13px;
            color: #64748b;
        }

        .stat-row-value {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
        }

        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }

            .filter-form {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="logo-section">
            <div class="logo-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <span class="brand-name">Fix Signature - Admin</span>
        </div>
        <a href="dashboard.php" class="btn-back">
            <i class="fas fa-arrow-left"></i>
            Dashboard
        </a>
    </nav>

    <div class="container">
        <div class="page-header">
            <div>
                <h1 class="page-title">Laporan & Statistik</h1>
                <p class="page-subtitle">Analisis penggunaan sistem Fix Signature</p>
            </div>
            <button onclick="window.print()" class="btn-export">
                <i class="fas fa-print"></i>
                Cetak Laporan
            </button>
        </div>

    
        <div class="date-filter">
            <form method="GET" action="" class="filter-form">
                <div class="form-group">
                    <label class="form-label">Dari Tanggal</label>
                    <input type="date" name="from" class="form-control" value="<?php echo $date_from; ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Sampai Tanggal</label>
                    <input type="date" name="to" class="form-control" value="<?php echo $date_to; ?>" required>
                </div>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-filter"></i>
                    Tampilkan
                </button>
            </form>
        </div>

    
        <div style="background: #f8fafc; padding: 12px 20px; border-radius: 8px; margin-bottom: 20px; text-align: center;">
            <span style="font-size: 13px; color: #64748b;">Periode Laporan: </span>
            <span style="font-size: 14px; font-weight: 600; color: #1e293b;">
                <?php echo date('d F Y', strtotime($date_from)); ?> - <?php echo date('d F Y', strtotime($date_to)); ?>
            </span>
        </div>

   
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon blue">
                        <i class="fas fa-file-signature"></i>
                    </div>
                </div>
                <div class="stat-label">Dokumen Ditandatangani</div>
                <div class="stat-value"><?php echo $docs_in_range; ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon green">
                        <i class="fas fa-certificate"></i>
                    </div>
                </div>
                <div class="stat-label">TTE Dibuat</div>
                <div class="stat-value"><?php echo $tte_in_range; ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon purple">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="stat-label">Pengguna Aktif</div>
                <div class="stat-value"><?php echo $active_users; ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon orange">
                        <i class="fas fa-hdd"></i>
                    </div>
                </div>
                <div class="stat-label">Storage Digunakan</div>
                <div class="stat-value"><?php echo number_format($total_size / 1024 / 1024, 1); ?> MB</div>
            </div>
        </div>

    
        <div class="content-grid">
            <!-- Chart -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fas fa-chart-line"></i>
                        Tren Dokumen Harian
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="documentsChart" height="100"></canvas>
                </div>
            </div>

     
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fas fa-trophy"></i>
                        Top 10 Pengguna Aktif
                    </div>
                </div>
                <div class="card-body">
                    <?php if (mysqli_num_rows($top_users) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nama</th>
                                <th>Dokumen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $rank = 1;
                            while ($user = mysqli_fetch_assoc($top_users)): 
                            ?>
                            <tr>
                                <td><?php echo $rank++; ?></td>
                                <td>
                                    <div style="font-weight: 500;"><?php echo htmlspecialchars($user['nama']); ?></div>
                                    <div style="font-size: 11px; color: #64748b;"><?php echo htmlspecialchars($user['email']); ?></div>
                                </td>
                                <td>
                                    <span class="badge badge-primary"><?php echo $user['doc_count']; ?></span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p style="text-align: center; color: #94a3b8; padding: 40px;">Tidak ada data</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

      
        <div class="system-stats">
            <h3><i class="fas fa-server"></i> Statistik Sistem (Keseluruhan)</h3>
            <div class="stat-row">
                <span class="stat-row-label">Total Pengguna Terdaftar</span>
                <span class="stat-row-value"><?php echo $total_users_all; ?> pengguna</span>
            </div>
            <div class="stat-row">
                <span class="stat-row-label">Total Dokumen Ditandatangani</span>
                <span class="stat-row-value"><?php echo $total_docs_all; ?> dokumen</span>
            </div>
            <div class="stat-row">
                <span class="stat-row-label">Total TTE Dibuat</span>
                <span class="stat-row-value"><?php echo $total_tte_all; ?> TTE</span>
            </div>
            <div class="stat-row">
                <span class="stat-row-label">Total Storage Terpakai</span>
                <span class="stat-row-value"><?php echo number_format($total_storage_all / 1024 / 1024, 2); ?> MB</span>
            </div>
            <div class="stat-row">
                <span class="stat-row-label">Rata-rata Dokumen per Hari</span>
                <span class="stat-row-value">
                    <?php 
                    $days_diff = (strtotime($date_to) - strtotime($date_from)) / 86400 + 1;
                    echo number_format($docs_in_range / $days_diff, 1); 
                    ?> dokumen
                </span>
            </div>
        </div>
    </div>

    <script>
        // Chart
        const ctx = document.getElementById('documentsChart').getContext('2d');
        const chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chart_dates); ?>,
                datasets: [{
                    label: 'Dokumen',
                    data: <?php echo json_encode($chart_counts); ?>,
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>