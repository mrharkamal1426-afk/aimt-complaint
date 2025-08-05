<?php
require_once __DIR__.'/../includes/config.php';

// Get selected time range from GET parameter
$range = $_GET['range'] ?? 'month';
switch ($range) {
    case 'month':
        $interval = '1 MONTH';
        $label = 'This Month';
        break;
    case 'year':
        $interval = '1 YEAR';
        $label = 'This Year';
        break;
    case 'all':
        $interval = null; // No filter
        $label = 'All Time';
        break;
    case 'week':
        $interval = '1 WEEK';
        $label = 'This Week';
        break;
    case 'month':
    default:
        $interval = '1 MONTH';
        $label = 'This Month';
        break;
}

// Auto-calculate thresholds based on overall data analysis
$threshold_analysis_sql = "
    SELECT 
        COUNT(*) as total_complaints,
        AVG(CASE WHEN status = 'resolved' THEN TIMESTAMPDIFF(HOUR, created_at, updated_at) END) / 24 as avg_resolution_days,
        COUNT(DISTINCT category) as total_categories,
        COUNT(DISTINCT room_no) as total_rooms
    FROM complaints
    WHERE " . ($interval ? "created_at >= DATE_SUB(NOW(), INTERVAL $interval)" : '1=1') . "
";
$threshold_data = $mysqli->query($threshold_analysis_sql)->fetch_assoc();

// Calculate dynamic thresholds
$critical_threshold = max(15, round($threshold_data['total_complaints'] * 0.05)); // At least 15, or 5% of total
$workload_threshold = max(10, round($threshold_data['total_complaints'] / max($threshold_data['total_categories'], 1) * 1.5)); // 1.5x average per category
$pending_threshold = max(5, round($threshold_data['total_complaints'] * 0.03)); // At least 5, or 3% of total
$room_threshold = max(5, round($threshold_data['total_rooms'] * 0.02)); // At least 5, or 2% of total rooms

// Key Performance Indicators with Week-over-Week comparison
$kpi_sql = "
    SELECT 
        -- Current week metrics
        COUNT(*) as total_complaints,
        SUM(CASE WHEN c.status IN ('pending', 'in_progress') THEN 1 ELSE 0 END) as active_complaints,
        SUM(CASE WHEN c.status = 'resolved' THEN 1 ELSE 0 END) as resolved_count,
        AVG(CASE WHEN c.status = 'resolved' THEN TIMESTAMPDIFF(HOUR, c.created_at, c.updated_at) END) as avg_resolution_hours,
        -- Previous week comparison
        (SELECT COUNT(*) FROM complaints 
         WHERE created_at BETWEEN DATE_SUB(NOW(), INTERVAL 2 WEEK) AND DATE_SUB(NOW(), INTERVAL 1 WEEK)) as prev_week_complaints,
        (SELECT SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) FROM complaints 
         WHERE created_at BETWEEN DATE_SUB(NOW(), INTERVAL 2 WEEK) AND DATE_SUB(NOW(), INTERVAL 1 WEEK)) as prev_week_resolved,
        -- User type distribution
        SUM(CASE WHEN u.role = 'student' THEN 1 ELSE 0 END) as student_complaints,
        SUM(CASE WHEN u.role = 'faculty' THEN 1 ELSE 0 END) as faculty_complaints,
        SUM(CASE WHEN u.role = 'nonteaching' THEN 1 ELSE 0 END) as nonteaching_complaints,
        SUM(CASE WHEN u.role = 'outsourced_vendor' THEN 1 ELSE 0 END) as vendor_complaints
    FROM complaints c
    JOIN users u ON c.user_id = u.id
    WHERE " . ($interval ? "c.created_at >= DATE_SUB(NOW(), INTERVAL $interval)" : '1=1') . "
";
$kpi = $mysqli->query($kpi_sql)->fetch_assoc();

// Calculate week-over-week changes
$complaint_change = (($kpi['total_complaints'] - $kpi['prev_week_complaints']) / ($kpi['prev_week_complaints'] ?: 1)) * 100;
$resolution_change = (($kpi['resolved_count'] - $kpi['prev_week_resolved']) / ($kpi['prev_week_resolved'] ?: 1)) * 100;

// Get current week's pending complaints (created this week)
$current_week_pending_sql = "
    SELECT COUNT(*) as current_week_pending
    FROM complaints 
    WHERE status IN ('pending', 'in_progress')
    AND " . ($interval ? "created_at >= DATE_SUB(NOW(), INTERVAL $interval)" : '1=1') . "
";
$current_week_pending_result = $mysqli->query($current_week_pending_sql)->fetch_assoc();
$current_week_pending = $current_week_pending_result['current_week_pending'];

// Get previous week's pending complaints for comparison
$prev_week_pending_sql = "
    SELECT COUNT(*) as prev_week_pending
    FROM complaints 
    WHERE status IN ('pending', 'in_progress')
    AND created_at BETWEEN DATE_SUB(NOW(), INTERVAL 2 WEEK) AND DATE_SUB(NOW(), INTERVAL 1 WEEK)
";
$prev_week_pending_result = $mysqli->query($prev_week_pending_sql)->fetch_assoc();
$prev_week_pending = $prev_week_pending_result['prev_week_pending'];

// Calculate pending change
$pending_change = $current_week_pending - $prev_week_pending;
$pending_change_percent = ($prev_week_pending > 0) ? (($pending_change / $prev_week_pending) * 100) : 0;

// Critical Areas Analysis (Categories with high pending rates)
$critical_areas_sql = "
    SELECT 
        c.category,
        COUNT(*) as total,
        SUM(CASE WHEN c.status IN ('pending', 'in_progress') THEN 1 ELSE 0 END) as pending,
        ROUND(AVG(CASE WHEN c.status = 'resolved' THEN TIMESTAMPDIFF(HOUR, c.created_at, c.updated_at) END) / 24, 1) as avg_days,
        ROUND(SUM(CASE WHEN c.status IN ('pending', 'in_progress') THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 1) as pending_rate,
        COUNT(DISTINCT c.room_no) as affected_rooms
    FROM complaints c
    WHERE " . ($interval ? "c.created_at >= DATE_SUB(NOW(), INTERVAL $interval)" : '1=1') . "
    GROUP BY c.category
    HAVING total >= $critical_threshold AND pending_rate > 30
    ORDER BY pending_rate DESC, total DESC
";
$critical_areas = $mysqli->query($critical_areas_sql)->fetch_all(MYSQLI_ASSOC);

// Technician Workload and Performance
$tech_performance_sql = "
    SELECT 
        u.full_name,
        u.specialization,
        COUNT(c.id) as current_load,
        SUM(CASE WHEN c.status = 'resolved' THEN 1 ELSE 0 END) as resolved_count,
        ROUND(AVG(CASE WHEN c.status = 'resolved' THEN TIMESTAMPDIFF(HOUR, c.created_at, c.updated_at) END) / 24, 1) as avg_days,
        GROUP_CONCAT(DISTINCT c.category) as categories,
        COUNT(DISTINCT c.room_no) as rooms_serviced,
        ROUND(SUM(CASE WHEN c.status = 'resolved' THEN 1 ELSE 0 END) * 100.0 / COUNT(c.id), 1) as efficiency_rate
    FROM users u
    JOIN complaints c ON u.id = c.technician_id
    WHERE u.role = 'technician' 
    AND " . ($interval ? "c.created_at >= DATE_SUB(NOW(), INTERVAL $interval)" : '1=1') . "
    GROUP BY u.id, u.full_name, u.specialization
    ORDER BY current_load DESC
";
$tech_performance = $mysqli->query($tech_performance_sql)->fetch_all(MYSQLI_ASSOC);

// Most Reported Rooms (potential systemic issues)
$problem_areas_sql = "
    SELECT 
        c.room_no,
        c.hostel_type,
        COUNT(*) as complaint_count,
        GROUP_CONCAT(DISTINCT c.category) as issue_types,
        COUNT(DISTINCT c.category) as unique_issues,
        ROUND(AVG(CASE WHEN c.status = 'resolved' THEN TIMESTAMPDIFF(HOUR, c.created_at, c.updated_at) END) / 24, 1) as avg_resolution_days
    FROM complaints c
    WHERE " . ($interval ? "c.created_at >= DATE_SUB(NOW(), INTERVAL $interval)" : '1=1') . "
    GROUP BY c.room_no, c.hostel_type
    HAVING complaint_count >= $room_threshold
    ORDER BY complaint_count DESC
    LIMIT 10
";
$problem_areas = $mysqli->query($problem_areas_sql)->fetch_all(MYSQLI_ASSOC);

// Hostel-wide Issues Analysis
$hostel_wide_sql = "
    SELECT 
        hi.hostel_type,
        hi.issue_type,
        COUNT(*) as total_issues,
        SUM(CASE WHEN hi.status = 'resolved' THEN 1 ELSE 0 END) as resolved_count,
        COUNT(DISTINCT hiv.user_id) as voter_count,
        ROUND(AVG(CASE WHEN hi.status = 'resolved' 
            THEN TIMESTAMPDIFF(HOUR, hi.created_at, hi.updated_at) END) / 24, 1) as avg_resolution_days
    FROM hostel_issues hi
    LEFT JOIN hostel_issue_votes hiv ON hi.id = hiv.issue_id
    WHERE " . ($interval ? "hi.created_at >= DATE_SUB(NOW(), INTERVAL $interval)" : '1=1') . "
    GROUP BY hi.hostel_type, hi.issue_type
    HAVING total_issues > 0
    ORDER BY voter_count DESC
";
$hostel_wide_issues = $mysqli->query($hostel_wide_sql)->fetch_all(MYSQLI_ASSOC);

// Urgent Hostel Issues
$hostel_issues_sql = "
    SELECT 
        c.hostel_type,
        c.category,
        COUNT(*) as total,
        COUNT(DISTINCT c.room_no) as affected_rooms,
        SUM(CASE WHEN c.status IN ('pending', 'in_progress') THEN 1 ELSE 0 END) as pending,
        GROUP_CONCAT(DISTINCT c.room_no SEPARATOR ', ') as sample_rooms,
        ROUND(AVG(CASE WHEN c.status = 'resolved' 
            THEN TIMESTAMPDIFF(HOUR, c.created_at, c.updated_at) 
            END) / 24, 1) as avg_resolution_days
    FROM complaints c
    WHERE c.status IN ('pending', 'in_progress')
    AND " . ($interval ? "c.created_at >= DATE_SUB(NOW(), INTERVAL $interval)" : '1=1') . "
    GROUP BY c.hostel_type, c.category
    HAVING pending > $pending_threshold
    ORDER BY pending DESC
";
$hostel_issues = $mysqli->query($hostel_issues_sql)->fetch_all(MYSQLI_ASSOC);

// Response Time Analysis
$response_time_sql = "
    SELECT 
        c.category,
        COUNT(*) as total_complaints,
        ROUND(AVG(CASE 
            WHEN c.status = 'in_progress' THEN TIMESTAMPDIFF(HOUR, c.created_at, NOW())
            WHEN c.status = 'resolved' THEN TIMESTAMPDIFF(HOUR, c.created_at, c.updated_at)
        END), 1) as avg_response_hours,
        ROUND(MIN(CASE 
            WHEN c.status = 'resolved' THEN TIMESTAMPDIFF(HOUR, c.created_at, c.updated_at)
        END), 1) as fastest_resolution,
        ROUND(MAX(CASE 
            WHEN c.status = 'resolved' THEN TIMESTAMPDIFF(HOUR, c.created_at, c.updated_at)
        END), 1) as slowest_resolution
    FROM complaints c
    WHERE " . ($interval ? "c.created_at >= DATE_SUB(NOW(), INTERVAL $interval)" : '1=1') . "
    AND c.status IN ('in_progress', 'resolved')
    GROUP BY c.category
    ORDER BY avg_response_hours DESC
";
$response_times = $mysqli->query($response_time_sql)->fetch_all(MYSQLI_ASSOC);

// Peak Complaint Times
$peak_times_sql = "
    SELECT 
        HOUR(c.created_at) as hour_of_day,
        COUNT(*) as complaint_count,
        GROUP_CONCAT(DISTINCT c.category) as common_issues
    FROM complaints c
    WHERE " . ($interval ? "c.created_at >= DATE_SUB(NOW(), INTERVAL $interval)" : '1=1') . "
    GROUP BY HOUR(c.created_at)
    ORDER BY complaint_count DESC
    LIMIT 5
";
$peak_times = $mysqli->query($peak_times_sql)->fetch_all(MYSQLI_ASSOC);

// Rejection Analysis
$rejection_sql = "
    SELECT 
        c.category,
        COUNT(*) as total_rejections,
        COUNT(DISTINCT c.room_no) as affected_rooms,
        GROUP_CONCAT(DISTINCT c.room_no SEPARATOR ', ') as rejection_rooms
    FROM complaints c
    WHERE c.status = 'rejected'
    AND " . ($interval ? "c.created_at >= DATE_SUB(NOW(), INTERVAL $interval)" : '1=1') . "
    GROUP BY c.category
    HAVING total_rejections > 0
    ORDER BY total_rejections DESC
";
$rejections = $mysqli->query($rejection_sql)->fetch_all(MYSQLI_ASSOC);

// Recurring Issues (Same room, same category within 7 days)
$recurring_sql = "
    SELECT 
        c1.room_no,
        c1.category,
        c1.hostel_type,
        COUNT(*) as occurrence_count,
        GROUP_CONCAT(
            DISTINCT DATE_FORMAT(c1.created_at, '%Y-%m-%d') 
            ORDER BY c1.created_at 
            SEPARATOR ', '
        ) as complaint_dates
    FROM complaints c1
    INNER JOIN complaints c2 ON 
        c1.room_no = c2.room_no 
        AND c1.category = c2.category
        AND c1.id != c2.id
        AND c2.created_at BETWEEN 
            c1.created_at 
            AND DATE_ADD(c1.created_at, INTERVAL 7 DAY)
    WHERE " . ($interval ? "c1.created_at >= DATE_SUB(NOW(), INTERVAL $interval)" : '1=1') . "
    GROUP BY c1.room_no, c1.category, c1.hostel_type
    HAVING occurrence_count > 1
    ORDER BY occurrence_count DESC
    LIMIT 10
";
$recurring_issues = $mysqli->query($recurring_sql)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AIMT - Critical Insights Report</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-gray-50 min-h-screen font-[Inter]">
    <div class="max-w-7xl mx-auto py-10 px-4">
        <form method="get" class="mb-6">
            <label for="range" class="mr-2 font-medium">Show data for:</label>
            <select name="range" id="range" onchange="this.form.submit()" class="border rounded px-2 py-1">
                <option value="week" <?= $range == 'week' ? 'selected' : '' ?>>This Week</option>
                <option value="month" <?= $range == 'month' ? 'selected' : '' ?>>This Month</option>
                <option value="year" <?= $range == 'year' ? 'selected' : '' ?>>This Year</option>
                <option value="all" <?= $range == 'all' ? 'selected' : '' ?>>All Time</option>
            </select>
            <span class="ml-4 text-gray-500 text-sm">Currently showing: <span class="font-semibold text-gray-700"><?= $label ?></span></span>
        </form>
        <div class="flex items-center justify-between mb-8">
            <div class="flex items-center gap-4">
                <img src="../assets/images/aimt-logo.png" alt="AIMT Logo" class="w-16 h-16 bg-white p-1 rounded-lg shadow-lg">
                <h1 class="text-3xl font-bold text-gray-800">Critical Insights Report</h1>
            </div>
            <p class="text-sm text-gray-500">Last Updated: <?= date('d M Y, h:i A') ?></p>
        </div>

        <!-- Executive Summary (Action-Focused, Only Unresolved/Actionable) -->
        <?php
        // Prepare live data for summary
        $urgent_count = isset($oldest_pending) ? count($oldest_pending) : 0;
        $urgent_ids = isset($oldest_pending) && $urgent_count > 0 ? array_column($oldest_pending, 'id') : [];
        $critical_category = !empty($critical_areas) ? $critical_areas[0]['category'] : null;
        $critical_pending = !empty($critical_areas) ? $critical_areas[0]['pending'] : null;
        $slowest_category = !empty($slow_categories) ? $slow_categories[0]['category'] : null;
        $slowest_days = !empty($slow_categories) ? $slow_categories[0]['avg_resolution_days'] : null;
        $top_tech = !empty($tech_performance) ? $tech_performance[0]['full_name'] : null;
        $top_tech_load = !empty($tech_performance) ? $tech_performance[0]['current_load'] : null;
        $most_pending_category = !empty($pending_categories) ? $pending_categories[0]['category'] : null;
        $most_pending_count = !empty($pending_categories) ? $pending_categories[0]['pending_count'] : null;
        $rejection_category = !empty($rejections) ? $rejections[0]['category'] : null;
        $rejection_count = !empty($rejections) ? $rejections[0]['total_rejections'] : null;
        $backlog_trend = ($complaint_change > 0) ? 'increasing' : 'decreasing';
        $backlog_trend_color = ($complaint_change > 0) ? 'text-red-600' : 'text-green-600';
        
        // Only show hostel-wide issues that are not resolved
        $unresolved_hostel_issue = null;
        $unresolved_hostel_votes = null;
        if (!empty($hostel_wide_issues)) {
            foreach ($hostel_wide_issues as $issue) {
                if ($issue['resolved_count'] < $issue['total_issues']) {
                    $unresolved_hostel_issue = $issue['issue_type'];
                    $unresolved_hostel_votes = $issue['voter_count'];
                    break;
                }
            }
        }
        
        // Calculate overall system health score
        $health_score = 100;
        $health_issues = [];
        
        if ($complaint_change > 0) {
            $health_score -= 20;
            $health_issues[] = "Backlog increasing";
        }
        if ($urgent_count > 0) {
            $health_score -= 25;
            $health_issues[] = "Urgent complaints pending";
        }
        if ($critical_category && $critical_pending > 5) {
            $health_score -= 15;
            $health_issues[] = "Critical category backlog";
        }
        if ($slowest_category && $slowest_days > 5) {
            $health_score -= 10;
            $health_issues[] = "Slow resolution times";
        }
        
        $health_score = max(0, $health_score);
        $health_color = $health_score >= 80 ? 'text-green-600' : ($health_score >= 60 ? 'text-yellow-600' : 'text-red-600');
        $health_status = $health_score >= 80 ? 'Good' : ($health_score >= 60 ? 'Fair' : 'Poor');
        ?>
        
        <!-- Executive Summary Card -->
        <div class="bg-gradient-to-r from-slate-50 to-gray-50 border border-slate-200 p-6 rounded-xl shadow-sm mb-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Executive Summary
                </h2>
                <div class="text-right text-sm text-slate-600">
                    <div>Week ending <?= date('d M Y') ?></div>
                    <div>Generated <?= date('h:i A') ?></div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white p-4 rounded-lg border border-slate-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-sm font-medium text-slate-600">System Status</div>
                            <div class="text-lg font-bold <?= $health_color ?>"><?= $health_status ?></div>
                        </div>
                        <div class="text-2xl font-bold <?= $health_color ?>"><?= $health_score ?>%</div>
                    </div>
                </div>
                
                <div class="bg-white p-4 rounded-lg border border-slate-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-sm font-medium text-slate-600">Critical Issues</div>
                            <div class="text-lg font-bold <?= $urgent_count > 0 ? 'text-red-600' : 'text-green-600' ?>">
                                <?= $urgent_count > 0 ? $urgent_count . ' Urgent' : 'None' ?>
                            </div>
                        </div>
                        <?php if ($urgent_count > 0): ?>
                        <div class="text-red-500">
                            <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <?php else: ?>
                        <div class="text-green-500">
                            <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="bg-white p-4 rounded-lg border border-slate-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-sm font-medium text-slate-600">Backlog Trend</div>
                            <div class="text-lg font-bold <?= $complaint_change > 0 ? 'text-red-600' : 'text-green-600' ?>">
                                <?= $complaint_change > 0 ? 'Increasing' : 'Decreasing' ?>
                            </div>
                        </div>
                        <div class="text-2xl <?= $complaint_change > 0 ? 'text-red-500' : 'text-green-500' ?>">
                            <?= $complaint_change > 0 ? '↗' : '↘' ?>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white p-4 rounded-lg border border-slate-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-sm font-medium text-slate-600">Resolution Rate</div>
                            <div class="text-lg font-bold <?= $resolution_change >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                <?= $resolution_change >= 0 ? '+' : '' ?><?= round($resolution_change, 1) ?>%
                            </div>
                        </div>
                        <div class="text-2xl <?= $resolution_change >= 0 ? 'text-green-500' : 'text-red-500' ?>">
                            <?= $resolution_change >= 0 ? '↗' : '↘' ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if ($urgent_count > 0 || $complaint_change > 0 || ($critical_category && $critical_pending > 0)): ?>
            <div class="mt-4 p-3 bg-amber-50 border border-amber-200 rounded-lg">
                <div class="flex items-center gap-2 text-amber-800">
                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                    <span class="text-sm font-medium">Attention Required</span>
                </div>
                <div class="text-sm text-amber-700 mt-1">
                    <?php if ($urgent_count > 0): ?>• <?= $urgent_count ?> urgent complaints need immediate action<?php endif; ?>
                    <?php if ($complaint_change > 0): ?><?= $urgent_count > 0 ? ' • ' : '• ' ?>Backlog increasing by <?= round($complaint_change, 1) ?>%<?php endif; ?>
                    <?php if ($critical_category && $critical_pending > 0): ?><?= ($urgent_count > 0 || $complaint_change > 0) ? ' • ' : '• ' ?><?= ucfirst($critical_category) ?> category has <?= $critical_pending ?> pending cases<?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Priority Actions Section -->
        <div class="bg-white border border-gray-200 p-6 rounded-xl shadow-sm mb-8">
            <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                </svg>
                Priority Actions Required
            </h2>
            
            <div class="space-y-4">
                <?php if ($urgent_count > 0): ?>
                <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-lg">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-semibold text-red-800 uppercase tracking-wide">Critical Priority</h3>
                            <div class="mt-1 text-sm text-red-700">
                                <p class="font-medium"><?= $urgent_count ?> high-priority complaints require immediate resolution.</p>
                                <p class="mt-1 text-xs">Complaint IDs: <?= implode(', ', $urgent_ids) ?></p>
                                <p class="mt-2 font-medium">Recommended Action: Assign to senior technicians with immediate effect.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($complaint_change > 0): ?>
                <div class="bg-orange-50 border-l-4 border-orange-500 p-4 rounded-lg">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-orange-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-semibold text-orange-800 uppercase tracking-wide">Backlog Escalation</h3>
                            <div class="mt-1 text-sm text-orange-700">
                                <p class="font-medium">Complaint volume exceeds resolution capacity by <?= round(abs($complaint_change), 1) ?>%.</p>
                                <p class="mt-1">Weekly comparison indicates increasing backlog trend.</p>
                                <p class="mt-2 font-medium">Recommended Action: Review resource allocation and consider temporary staffing adjustments.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($critical_category && $critical_pending > 0): ?>
                <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 rounded-lg">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-semibold text-yellow-800 uppercase tracking-wide">Category Performance Alert</h3>
                            <div class="mt-1 text-sm text-yellow-700">
                                <p class="font-medium"><?= ucfirst($critical_category) ?> category has <?= $critical_pending ?> pending cases requiring attention.</p>
                                <p class="mt-1">This represents a significant backlog in this service area.</p>
                                <p class="mt-2 font-medium">Recommended Action: Reallocate specialized resources or engage external support for this category.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($top_tech && $top_tech_load > $workload_threshold): ?>
                <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded-lg">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-semibold text-blue-800 uppercase tracking-wide">Resource Allocation</h3>
                            <div class="mt-1 text-sm text-blue-700">
                                <p class="font-medium">Technician <?= htmlspecialchars($top_tech) ?> currently managing <?= $top_tech_load ?> active cases.</p>
                                <p class="mt-1">Workload exceeds recommended capacity threshold (<?= $workload_threshold ?> cases).</p>
                                <p class="mt-2 font-medium">Recommended Action: Redistribute workload across available team members.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($unresolved_hostel_issue): ?>
                <div class="bg-purple-50 border-l-4 border-purple-500 p-4 rounded-lg">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-purple-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M10.707 2.293a1 1 0 00-1.414 0l-.707.707a1 1 0 01-1.414 0l-.707-.707A1 1 0 003.293 3.293L4 4a1 1 0 001.414 0L6 3.293A1 1 0 017.414 4L8 4.707a1 1 0 001.414 0L10 4a1 1 0 011.414 0l.707.707a1 1 0 001.414 0l.707-.707A1 1 0 0116.707 2.293L16 3a1 1 0 01-1.414 0L14 2.293A1 1 0 0112.586 3L12 3.707a1 1 0 01-1.414 0L10 3a1 1 0 01-1.414 0L8 3.707A1 1 0 016.586 3L6 2.293A1 1 0 015.293 2.293z" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-semibold text-purple-800 uppercase tracking-wide">Infrastructure Issue</h3>
                            <div class="mt-1 text-sm text-purple-700">
                                <p class="font-medium"><?= ucfirst($unresolved_hostel_issue) ?> affecting multiple residents (<?= $unresolved_hostel_votes ?> reported cases).</p>
                                <p class="mt-1">This represents a systemic issue requiring coordinated response.</p>
                                <p class="mt-2 font-medium">Recommended Action: Escalate to facilities management for comprehensive resolution.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (
                    ($complaint_change <= 0) &&
                    ($urgent_count == 0) &&
                    (!$critical_category || $critical_pending == 0) &&
                    (!$top_tech || $top_tech_load <= $workload_threshold) &&
                    !$unresolved_hostel_issue
                ): ?>
                <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-lg">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-semibold text-green-800 uppercase tracking-wide">System Status: Optimal</h3>
                            <div class="mt-1 text-sm text-green-700">
                                <p class="font-medium">All systems operating within normal parameters.</p>
                                <p class="mt-1">No critical issues requiring immediate attention.</p>
                                <p class="mt-2 font-medium">Recommended Action: Continue monitoring and maintain current performance standards.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Insights -->
        <div class="bg-gray-50 border border-gray-200 p-6 rounded-xl shadow-sm mb-8">
            <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 20c4.418 0 8-3.582 8-8s-3.582-8-8-8-8 3.582-8 8 3.582 8 8 8z" />
                </svg>
                Quick Insights & Trends
            </h2>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Performance Metrics -->
                <div class="space-y-3">
                    <h3 class="text-sm font-semibold text-gray-700 border-b border-gray-200 pb-1">Performance Metrics</h3>
                    <div class="space-y-2 text-sm">
                        <?php if ($slowest_category && $slowest_days > 3): ?>
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 bg-yellow-400 rounded-full"></span>
                            <span><strong>Slowest category:</strong> <?= ucfirst($slowest_category) ?> (<?= $slowest_days ?> days avg)</span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($most_pending_category && $most_pending_count > $pending_threshold): ?>
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 bg-orange-400 rounded-full"></span>
                            <span><strong>Most backlogged:</strong> <?= ucfirst($most_pending_category) ?> (<?= $most_pending_count ?> pending)</span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($rejection_category && $rejection_count > 0): ?>
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 bg-red-400 rounded-full"></span>
                            <span><strong>High rejections:</strong> <?= ucfirst($rejection_category) ?> (<?= $rejection_count ?> rejections)</span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 bg-blue-400 rounded-full"></span>
                            <span><strong>Avg resolution:</strong> <?= round($kpi['avg_resolution_hours'] / 24, 1) ?> days</span>
                        </div>
                    </div>
                </div>
                
                <!-- User Distribution -->
                <div class="space-y-3">
                    <h3 class="text-sm font-semibold text-gray-700 border-b border-gray-200 pb-1">User Distribution</h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 bg-blue-400 rounded-full"></span>
                            <span><strong>Students:</strong> <?= $kpi['student_complaints'] ?> (<?= round(($kpi['student_complaints'] / max($kpi['total_complaints'], 1)) * 100, 1) ?>%)</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 bg-green-400 rounded-full"></span>
                            <span><strong>Faculty:</strong> <?= $kpi['faculty_complaints'] ?> (<?= round(($kpi['faculty_complaints'] / max($kpi['total_complaints'], 1)) * 100, 1) ?>%)</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 bg-purple-400 rounded-full"></span>
                            <span><strong>Non-Teaching Staff:</strong> <?= $kpi['nonteaching_complaints'] ?> (<?= round(($kpi['nonteaching_complaints'] / max($kpi['total_complaints'], 1)) * 100, 1) ?>%)</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 bg-orange-400 rounded-full"></span>
                            <span><strong>Outsourced Vendors:</strong> <?= $kpi['vendor_complaints'] ?> (<?= round(($kpi['vendor_complaints'] / max($kpi['total_complaints'], 1)) * 100, 1) ?>%)</span>
                        </div>
                    </div>
                </div>
                
                <!-- Week-over-Week Trends -->
                <div class="space-y-3">
                    <h3 class="text-sm font-semibold text-gray-700 border-b border-gray-200 pb-1">This Week vs Last Week</h3>
                    <div class="space-y-3 text-sm">
                        <div class="bg-white p-3 rounded-lg border border-gray-200">
                            <div class="flex items-center justify-between">
                                <span class="font-medium">New Complaints</span>
                                <div class="text-right">
                                    <div class="text-lg font-bold <?= $complaint_change >= 0 ? 'text-red-600' : 'text-green-600' ?>">
                                        <?= $complaint_change >= 0 ? '+' : '' ?><?= round($complaint_change, 1) ?>%
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <?= $complaint_change >= 0 ? 'More than last week' : 'Fewer than last week' ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-white p-3 rounded-lg border border-gray-200">
                            <div class="flex items-center justify-between">
                                <span class="font-medium">Resolved Complaints</span>
                                <div class="text-right">
                                    <div class="text-lg font-bold <?= $resolution_change >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                                        <?= $resolution_change >= 0 ? '+' : '' ?><?= round($resolution_change, 1) ?>%
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <?= $resolution_change >= 0 ? 'More than last week' : 'Fewer than last week' ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-white p-3 rounded-lg border border-gray-200">
                            <div class="flex items-center justify-between">
                                <span class="font-medium">This Week's Pending</span>
                                <div class="text-right">
                                    <div class="text-lg font-bold text-blue-600">
                                        <?= $current_week_pending ?>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <?php if ($prev_week_pending > 0): ?>
                                            <?= $pending_change >= 0 ? '+' : '' ?><?= $pending_change ?> from last week
                                            <br>
                                            <span class="<?= $pending_change >= 0 ? 'text-red-500' : 'text-green-500' ?>">
                                                <?= $pending_change >= 0 ? 'More pending' : 'Fewer pending' ?>
                                            </span>
                                        <?php else: ?>
                                            Created this week
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Action Summary -->
            <div class="mt-6 pt-4 border-t border-gray-200">
                <h3 class="text-sm font-semibold text-gray-700 mb-2">Recommended Next Steps:</h3>
                <div class="text-sm text-gray-600 space-y-1">
                    <?php if ($urgent_count > 0): ?>
                    <div>• <strong>Immediate:</strong> Address <?= $urgent_count ?> urgent complaints (IDs: <?= implode(', ', $urgent_ids) ?>)</div>
                    <?php endif; ?>
                    <?php if ($complaint_change > 0): ?>
                    <div>• <strong>Short-term:</strong> Review staffing for <?= ucfirst($most_pending_category ?? 'busy categories') ?> to reduce backlog (threshold: <?= $pending_threshold ?> cases)</div>
                    <?php endif; ?>
                    <?php if ($critical_category && $critical_pending > 0): ?>
                    <div>• <strong>Medium-term:</strong> Develop specialized response plan for <?= ucfirst($critical_category) ?> issues</div>
                    <?php endif; ?>
                    <?php if ($slowest_category && $slowest_days > 5): ?>
                    <div>• <strong>Long-term:</strong> Optimize process for <?= ucfirst($slowest_category) ?> to improve resolution times</div>
                    <?php endif; ?>
                    <?php if (
                        ($urgent_count == 0) &&
                        ($complaint_change <= 0) &&
                        (!$critical_category || $critical_pending == 0) &&
                        (!$slowest_category || $slowest_days <= 5)
                    ): ?>
                    <div>• <strong>Maintenance:</strong> Continue current operations and monitor for emerging trends</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Weekly Performance Overview -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <h3 class="text-sm font-medium text-gray-500 mb-2">New Complaints (This Week)</h3>
                <p class="text-3xl font-bold text-blue-600"><?= number_format($kpi['total_complaints']) ?></p>
                <p class="text-sm <?= $complaint_change >= 0 ? 'text-red-500' : 'text-green-500' ?>">
                    <?= round(abs($complaint_change), 1) ?>% <?= $complaint_change >= 0 ? '↑' : '↓' ?> from last week
                </p>
                <div class="mt-2 text-xs text-gray-500">
                    Students: <?= $kpi['student_complaints'] ?> | 
                    Faculty: <?= $kpi['faculty_complaints'] ?> | 
                    Non-Teaching: <?= $kpi['nonteaching_complaints'] ?> | 
                    Vendors: <?= $kpi['vendor_complaints'] ?>
                </div>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <h3 class="text-sm font-medium text-gray-500 mb-2">Active Complaints</h3>
                <p class="text-3xl font-bold text-orange-600"><?= number_format($kpi['active_complaints']) ?></p>
                <p class="text-sm text-gray-500">Require immediate attention</p>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <h3 class="text-sm font-medium text-gray-500 mb-2">Resolved (This Week)</h3>
                <p class="text-3xl font-bold text-green-600"><?= number_format($kpi['resolved_count']) ?></p>
                <p class="text-sm <?= $resolution_change >= 0 ? 'text-green-500' : 'text-red-500' ?>">
                    <?= round(abs($resolution_change), 1) ?>% <?= $resolution_change >= 0 ? '↑' : '↓' ?> from last week
                </p>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <h3 class="text-sm font-medium text-gray-500 mb-2">Avg. Resolution Time</h3>
                <p class="text-3xl font-bold text-purple-600"><?= round($kpi['avg_resolution_hours'] / 24, 1) ?> days</p>
                <p class="text-sm text-gray-500">This week's average</p>
            </div>
        </div>

        <!-- Top 5 Oldest Pending Complaints (Very Urgent) -->
        <?php
        $oldest_pending_sql = "
            SELECT 
                c.id,
                c.token,
                c.category,
                c.room_no,
                c.hostel_type,
                u.full_name,
                TIMESTAMPDIFF(HOUR, c.created_at, NOW()) as age_hours,
                c.created_at
            FROM complaints c
            JOIN users u ON c.user_id = u.id
            WHERE c.status IN ('pending', 'in_progress')
            ORDER BY age_hours DESC
            LIMIT 5
        ";
        $oldest_pending = $mysqli->query($oldest_pending_sql)->fetch_all(MYSQLI_ASSOC);
        ?>
        <?php if (!empty($oldest_pending)): ?>
        <div class="bg-white p-6 rounded-xl shadow-sm border border-red-200 mb-8">
            <h2 class="text-xl font-semibold mb-4 text-red-700 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-600 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9 9 4.03 9 9z" /></svg>
                Top 5 Very Urgent Pending Complaints
                <span class="text-sm font-normal text-gray-500">(Click any row to view details)</span>
            </h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Complaint ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Room</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Hostel</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reported By</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Age</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($oldest_pending as $row): ?>
                        <tr class="bg-red-50 hover:bg-red-100 cursor-pointer" onclick="window.location.href='./complaint_details.php?token=<?= $row['token'] ?>'">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-red-700 hover:text-red-800">#<?= $row['id'] ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= ucfirst($row['category']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= $row['room_no'] ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= ucfirst($row['hostel_type']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= htmlspecialchars($row['full_name']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-red-800 font-semibold">
                                <?php
                                    $days = floor($row['age_hours'] / 24);
                                    $hours = $row['age_hours'] % 24;
                                    echo $days > 0 ? $days . 'd ' : '';
                                    echo $hours . 'h';
                                ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-1 text-xs font-bold rounded-full bg-red-600 text-white animate-pulse">Very Urgent</span>
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

         <!-- Categories with Highest Average Resolution Time (<?= $label ?>) -->
         <?php
        $slow_categories_sql = "
            SELECT 
                c.category,
                COUNT(*) as total_complaints,
                ROUND(AVG(CASE WHEN c.status = 'resolved' THEN TIMESTAMPDIFF(HOUR, c.created_at, c.updated_at) END) / 24, 1) as avg_resolution_days
            FROM complaints c
            WHERE " . ($interval ? "c.created_at >= DATE_SUB(NOW(), INTERVAL $interval)" : '1=1') . "
            AND c.status = 'resolved'
            GROUP BY c.category
            HAVING total_complaints > 0
            ORDER BY avg_resolution_days DESC
            LIMIT 5
        ";
        $slow_categories = $mysqli->query($slow_categories_sql)->fetch_all(MYSQLI_ASSOC);
        ?>
        <?php if (!empty($slow_categories)): ?>
        <div class="bg-white p-6 rounded-xl shadow-sm border border-yellow-200 mb-8">
            <h2 class="text-xl font-semibold mb-4 text-yellow-700 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 20c4.418 0 8-3.582 8-8s-3.582-8-8-8-8 3.582-8 8 3.582 8 8 8z" /></svg>
                Top 5 Slowest Categories (<?= $label ?>)
            </h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Resolved</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Avg. Resolution Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($slow_categories as $cat): ?>
                        <tr class="bg-yellow-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-yellow-700"><?= ucfirst($cat['category']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= $cat['total_complaints'] ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-yellow-900 font-semibold">
                                <?= $cat['avg_resolution_days'] ?> days
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Rejection Analysis -->
        <?php if (!empty($rejections)): ?>
        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 mb-8">
            <h2 class="text-xl font-semibold mb-4 text-gray-800">Rejected Complaints Analysis</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Rejections</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Affected Rooms</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Room Numbers</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($rejections as $rej): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= ucfirst($rej['category']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800">
                                    <?= $rej['total_rejections'] ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $rej['affected_rooms'] ?></td>
                            <td class="px-6 py-4 text-sm text-gray-500"><?= $rej['rejection_rooms'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>


         <!-- Categories with Most Pending Complaints (<?= $label ?>) -->
         <?php
        $pending_categories_sql = "
            SELECT 
                c.category,
                COUNT(*) as pending_count
            FROM complaints c
            WHERE " . ($interval ? "c.created_at >= DATE_SUB(NOW(), INTERVAL $interval)" : '1=1') . "
            AND c.status IN ('pending', 'in_progress')
            GROUP BY c.category
            HAVING pending_count > 0
            ORDER BY pending_count DESC
            LIMIT 5
        ";
        $pending_categories = $mysqli->query($pending_categories_sql)->fetch_all(MYSQLI_ASSOC);
        ?>
        <?php if (!empty($pending_categories)): ?>
        <div class="bg-white p-6 rounded-xl shadow-sm border border-orange-200 mb-8">
            <h2 class="text-xl font-semibold mb-4 text-orange-700 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 20c4.418 0 8-3.582 8-8s-3.582-8-8-8-8 3.582-8 8 3.582 8 8 8z" /></svg>
                Top 5 Categories with Most Pending Complaints (<?= $label ?>)
            </h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pending Complaints</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($pending_categories as $cat): ?>
                        <tr class="bg-orange-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-orange-700"><?= ucfirst($cat['category']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-orange-900 font-semibold">
                                <?= $cat['pending_count'] ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Critical Areas (High Pending Rate) -->
        <?php if (!empty($critical_areas)): ?>
        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 mb-8">
            <h2 class="text-xl font-semibold mb-4 text-gray-800">Critical Areas Needing Attention</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Cases</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pending</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pending Rate</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Affected Rooms</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Avg. Resolution</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($critical_areas as $area): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= ucfirst($area['category']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $area['total'] ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $area['pending'] ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span class="px-2 py-1 text-xs font-medium rounded-full <?= $area['pending_rate'] > 50 ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800' ?>">
                                    <?= $area['pending_rate'] ?>%
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $area['affected_rooms'] ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $area['avg_days'] ?> days</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Technician Workload -->
        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 mb-8">
            <h2 class="text-xl font-semibold mb-4 text-gray-800">Technician Performance Analysis</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Technician</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Specialization</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Active Cases</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Resolved</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Efficiency</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rooms Serviced</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Avg. Time</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categories</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($tech_performance as $tech): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($tech['full_name']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= ucfirst($tech['specialization']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span class="px-2 py-1 text-xs font-medium rounded-full <?= $tech['current_load'] > 5 ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' ?>">
                                    <?= $tech['current_load'] ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $tech['resolved_count'] ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span class="px-2 py-1 text-xs font-medium rounded-full <?= $tech['efficiency_rate'] >= 80 ? 'bg-green-100 text-green-800' : ($tech['efficiency_rate'] >= 60 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                                    <?= $tech['efficiency_rate'] ?>%
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $tech['rooms_serviced'] ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $tech['avg_days'] ?> days</td>
                            <td class="px-6 py-4 text-sm text-gray-500"><?= htmlspecialchars($tech['categories']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Hostel-wide Issues -->
        <?php if (!empty($hostel_wide_issues)): ?>
        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 mb-8">
            <h2 class="text-xl font-semibold mb-4 text-gray-800">Hostel-wide Issues (<?= $label ?>)</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Hostel</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Issue Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Issues</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Resolved</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Student Votes</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Avg. Resolution</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($hostel_wide_issues as $issue): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= ucfirst($issue['hostel_type']) ?> Hostel</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= ucfirst($issue['issue_type']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $issue['total_issues'] ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $issue['resolved_count'] ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span class="px-2 py-1 text-xs font-medium rounded-full <?= $issue['voter_count'] > 10 ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800' ?>">
                                    <?= $issue['voter_count'] ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $issue['avg_resolution_days'] ?> days</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Urgent Hostel Issues -->
        <?php if (!empty($hostel_issues)): ?>
        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 mb-8">
            <h2 class="text-xl font-semibold mb-4 text-gray-800">Urgent Room-Level Issues (<?= $label ?>)</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Hostel</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Cases</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Affected Rooms</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pending</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sample Rooms</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Avg. Resolution</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($hostel_issues as $issue): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <?= ucfirst($issue['hostel_type']) ?> Hostel
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= ucfirst($issue['category']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $issue['total'] ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $issue['affected_rooms'] ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span class="px-2 py-1 text-xs font-medium rounded-full <?= $issue['pending'] > 5 ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800' ?>">
                                    <?= $issue['pending'] ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500"><?= $issue['sample_rooms'] ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $issue['avg_resolution_days'] ?> days</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Response Time Analysis -->
        <?php if (!empty($response_times)): ?>
        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 mb-8">
            <h2 class="text-xl font-semibold mb-4 text-gray-800">Response Time Analysis (<?= $label ?>)</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Complaints</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Avg. Response Time</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fastest Resolution</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Slowest Resolution</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($response_times as $rt): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= ucfirst($rt['category']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $rt['total_complaints'] ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span class="px-2 py-1 text-xs font-medium rounded-full <?= $rt['avg_response_hours'] > 72 ? 'bg-red-100 text-red-800' : ($rt['avg_response_hours'] > 48 ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800') ?>">
                                    <?= round($rt['avg_response_hours'] / 24, 1) ?> days
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= round($rt['fastest_resolution'] / 24, 1) ?> days</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= round($rt['slowest_resolution'] / 24, 1) ?> days</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        

        <div class="text-center">
            <a href="dashboard.php" class="inline-block bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors">Back to Dashboard</a>
        </div>
    </div>
</body>
</html>