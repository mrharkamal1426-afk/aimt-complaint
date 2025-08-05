<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['student','faculty','nonteaching','outsourced_vendor','technician'])) {
    redirect('../login.php?error=unauthorized');
}
$user_id = $_SESSION['user_id'];
$show = false;
$complaint = null;
$error = '';
$pending_complaints = [];
$timeline = [];

// Fetch all complaints for the user
$stmt = $mysqli->prepare("SELECT token, category, status, created_at, updated_at FROM complaints WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$active_complaints = [];
$resolved_complaints = [];
$thirty_days_ago = (new DateTime('-30 days'))->format('Y-m-d H:i:s');
while ($row = $result->fetch_assoc()) {
    if ($row['status'] === 'resolved' && $row['updated_at'] >= $thirty_days_ago) {
        $resolved_complaints[] = $row;
    } elseif ($row['status'] !== 'resolved') {
        $active_complaints[] = $row;
    }
}
$stmt->close();

// Fetch resolved hostel-wide issues for the user's hostel
$student_hostel = 'boys';
if ($_SESSION['role'] === 'student') {
    $stmt = $mysqli->prepare("SELECT hostel_type FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($db_hostel_type);
    if ($stmt->fetch() && in_array($db_hostel_type, ['boys', 'girls'])) {
        $student_hostel = $db_hostel_type;
    }
    $stmt->close();
}
$hostel_types = ['boys' => 'Boys', 'girls' => 'Girls'];
$issue_types = [
    'wifi' => 'Wi-Fi not working',
    'water' => 'No water',
    'mess' => 'Mess food issue',
    'electricity' => 'Electricity problem',
    'cleanliness' => 'Cleanliness',
    'other' => 'Other',
];
$stmt = $mysqli->prepare("SELECT id, issue_type, updated_at FROM hostel_issues WHERE hostel_type = ? AND status = 'resolved' ORDER BY updated_at DESC");
$stmt->bind_param('s', $student_hostel);
$stmt->execute();
$resolved_hostel_issues = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include __DIR__.'/../templates/header.php';
?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header Section -->
        <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-900">Track Complaint</h2>
            <p class="mt-1 text-sm text-gray-500">View and manage your complaints</p>
        </div>

        <?php if ($error) show_error($error); ?>

        <?php if (empty($active_complaints) && empty($resolved_complaints)): ?>
        <div class="text-center py-12 bg-white rounded-lg shadow-md border border-gray-200">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <p class="mt-4 text-sm text-gray-500">No complaints found</p>
        </div>
        <?php else: ?>
        <!-- Tabs -->
        <div class="mb-6">
            <div class="flex border-b border-gray-200">
                <button id="tab-active" class="tab-btn px-4 py-2 text-sm font-medium focus:outline-none transition-colors duration-150 border-b-2 border-transparent text-gray-600 hover:text-primary focus:text-primary" onclick="showTab('active')">Active Complaints (<?= count($active_complaints) ?>)</button>
                <button id="tab-resolved" class="tab-btn px-4 py-2 text-sm font-medium focus:outline-none transition-colors duration-150 border-b-2 border-transparent text-gray-600 hover:text-primary focus:text-primary ml-4" onclick="showTab('resolved')">Resolved Complaints (<?= count($resolved_complaints) ?>)</button>
            </div>
        </div>
        <!-- Tab Content -->
        <div id="tab-content-active" class="tab-content">
            <?php if (!empty($active_complaints)): ?>
            <div class="table-container bg-white rounded-lg shadow-md border border-gray-200">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($active_complaints as $pending): ?>
                        <tr class="hover:bg-gray-50 cursor-pointer transition-colors duration-150" onclick="window.location.href='complaint_details.php?token=<?= htmlspecialchars($pending['token']) ?>'">
                            <td><?= htmlspecialchars(ucfirst($pending['category'])) ?></td>
                            <td>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?php
                                    switch($pending['status']) {
                                        case 'pending':
                                            echo 'bg-yellow-100 text-yellow-800 border border-yellow-200';
                                            break;
                                        case 'in_progress':
                                            echo 'bg-blue-100 text-blue-800 border border-blue-200';
                                            break;
                                        case 'resolved':
                                            echo 'bg-green-100 text-green-800 border border-green-200';
                                            break;
                                        case 'rejected':
                                            echo 'bg-red-100 text-red-800 border border-red-200';
                                            break;
                                    }
                                    ?>">
                                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $pending['status']))) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars(date('M j, Y', strtotime($pending['created_at']))) ?></td>
                            <td class="text-right">
                                <span class="text-blue-600 hover:text-blue-800 transition-colors duration-150">View Details →</span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-8 text-gray-500">No active complaints</div>
            <?php endif; ?>
        </div>
        <div id="tab-content-resolved" class="tab-content hidden">
        <?php if (!empty($resolved_complaints)): ?>
            <div class="table-container bg-white rounded-lg shadow-md border border-gray-200 mb-8">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resolved_complaints as $resolved): ?>
                        <tr class="hover:bg-gray-50 cursor-pointer transition-colors duration-150" onclick="window.location.href='complaint_details.php?token=<?= htmlspecialchars($resolved['token']) ?>'">
                            <td><?= htmlspecialchars(ucfirst($resolved['category'])) ?></td>
                            <td>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800 border border-green-200">Resolved</span>
                            </td>
                            <td><?= htmlspecialchars(date('M j, Y', strtotime($resolved['created_at']))) ?></td>
                            <td class="text-right">
                                <span class="text-blue-600 hover:text-blue-800 transition-colors duration-150">View Details →</span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-8 text-gray-500">No resolved complaints</div>
        <?php endif; ?>
        <?php if ($_SESSION['role'] === 'student' && !empty($resolved_hostel_issues)): ?>
            <div class="table-container bg-white rounded-lg shadow-md border border-gray-200 mt-8">
                <div class="px-4 py-2 font-semibold text-lg text-blue-700">Resolved Hostel-Wide Complaints (<?= htmlspecialchars($hostel_types[$student_hostel]) ?> Hostel)</div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Issue</th>
                            <th>Date Resolved</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resolved_hostel_issues as $issue): ?>
                        <tr>
                            <td><?= htmlspecialchars($issue_types[$issue['issue_type']] ?? ucfirst($issue['issue_type'])) ?></td>
                            <td><?= htmlspecialchars(date('M j, Y', strtotime($issue['updated_at']))) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        </div>
        <script>
        // Simple tab switcher
        function showTab(tab) {
            document.getElementById('tab-content-active').classList.add('hidden');
            document.getElementById('tab-content-resolved').classList.add('hidden');
            document.getElementById('tab-active').classList.remove('border-primary', 'text-primary');
            document.getElementById('tab-resolved').classList.remove('border-primary', 'text-primary');
            if(tab === 'active') {
                document.getElementById('tab-content-active').classList.remove('hidden');
                document.getElementById('tab-active').classList.add('border-primary', 'text-primary');
            } else {
                document.getElementById('tab-content-resolved').classList.remove('hidden');
                document.getElementById('tab-resolved').classList.add('border-primary', 'text-primary');
            }
        }
        // Set default tab
        showTab('active');
        </script>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__.'/../templates/footer.php'; ?> 