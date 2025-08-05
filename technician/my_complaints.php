<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';

// Ensure user is logged in as technician
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'technician') {
    redirect('../auth/login.php?error=unauthorized');
}

$tech_id = $_SESSION['user_id'];

// Fetch complaints submitted by this technician (he can also submit complaints like any normal user)
$complaints = [];
$query = "SELECT c.*, t.full_name as tech_name 
          FROM complaints c 
          LEFT JOIN users t ON t.id = c.technician_id 
          WHERE c.user_id = ? 
          ORDER BY c.created_at DESC";
$stmt = $mysqli->prepare($query);
$stmt->bind_param('i', $tech_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $complaints[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>My Submitted Complaints | Technician Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .card-hover { transition: all 0.3s ease; }
        .card-hover:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.06); }
        /* Smooth scrollbar for long lists */
        ::-webkit-scrollbar { width: 6px; height:6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background:#888; border-radius:3px; }
        ::-webkit-scrollbar-thumb:hover { background:#666; }
    </style>
</head>
<body class="bg-gray-50 dark:bg-dark-bg-primary min-h-screen font-['Inter'] transition-colors duration-200">
    <!-- Sidebar Backdrop (mobile) -->
    <div id="sidebar-backdrop" class="fixed inset-0 bg-black bg-opacity-30 z-50 hidden md:hidden"></div>

    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside id="sidebar" class="fixed md:static inset-y-0 left-0 transform -translate-x-full md:translate-x-0 z-[55] w-64 bg-gradient-to-b from-blue-800 to-blue-900 text-white flex flex-col transition-transform duration-200 ease-in-out shadow-xl">
            <div class="px-6 py-6 bg-gradient-to-r from-blue-700 to-blue-800">
                <div class="text-2xl font-bold">Complaint<span class="text-cyan-300">System</span></div>
            </div>
            <div class="px-6 py-4 border-b border-blue-700/50 bg-blue-800/30">
                <div class="font-semibold text-lg text-white truncate"><?= htmlspecialchars($_SESSION['full_name'] ?? 'Technician') ?></div>
                <div class="text-cyan-300 text-sm">Technician</div>
            </div>
            <nav class="flex-1 px-4 py-4 space-y-2 overflow-y-auto">
                <a href="dashboard.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-blue-800/40">
                    <span class="material-icons mr-3">dashboard</span> Dashboard
                </a>
                <a href="complaints.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-blue-800/40">
                    <span class="material-icons mr-3">fact_check</span> Assigned Complaints
                </a>
                <a href="my_complaints.php" class="flex items-center px-4 py-3 rounded-lg bg-blue-800/50">
                    <span class="material-icons mr-3">list_alt</span> My Complaints
                </a>
                <a href="hostel_issues.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-blue-800/40">
                    <span class="material-icons mr-3">campaign</span> Hostel Issues
                </a>
                <!-- Help Guide Button -->
                <button id="help-button" type="button" class="flex items-center w-full px-4 py-3 rounded-lg hover:bg-blue-800/40">
                    <span class="material-icons mr-3">help</span> Help Guide
                </button>
                <a href="../auth/logout.php" class="flex items-center px-4 py-3 rounded-lg mt-auto hover:bg-blue-800/40">
                    <span class="material-icons mr-3">logout</span> Logout
                </a>
            </nav>
        </aside>

        <!-- Main content -->
        <main id="main-content" class="flex-1 flex flex-col w-0 md:w-auto md:ml-0 transition-all duration-200 overflow-hidden">
            <!-- Header -->
            <header class="sticky top-0 z-40 bg-gradient-to-r from-blue-600 to-blue-700 text-white shadow-lg">
                <div class="flex items-center justify-between px-4 py-3">
                    <!-- Mobile menu toggle -->
                    <button id="mobile-menu-button" class="md:hidden text-white focus:outline-none">
                        <span class="material-icons">menu</span>
                    </button>
                    <h1 class="text-lg font-semibold">My Submitted Complaints</h1>
                    <div></div>
                </div>
            </header>

            <!-- Page Content -->
            <?php if (isset($_SESSION['status_message'])): ?>
            <div class="mx-4 md:mx-6 mt-4 bg-green-500 text-white p-4 rounded-xl text-center shadow-lg flex items-center justify-center">
                <span><?= htmlspecialchars($_SESSION['status_message']) ?></span>
            </div>
            <?php unset($_SESSION['status_message']); endif; ?>
            <div class="flex-1 overflow-y-auto p-4 md:p-6 space-y-6">
                <div class="bg-white dark:bg-dark-bg-secondary rounded-xl shadow-sm border border-gray-200 dark:border-dark-border">
                    <div class="p-4 border-b border-gray-200 dark:border-dark-border">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-dark-text-primary">My Complaints (<?= count($complaints) ?>)</h2>
                    </div>
                    <?php if (!empty($complaints)): ?>
                        <div class="p-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                            <?php foreach ($complaints as $complaint): ?>
                                <div class="relative border border-gray-200 rounded-xl bg-white shadow-sm card-hover p-6">
                                    <!-- Status badge -->
                                    <span class="absolute top-4 right-4 px-3 py-1 rounded-full text-xs font-semibold
                                        <?php
                                            echo $complaint['status'] === 'resolved' ? 'bg-green-100 text-green-700' :
                                                 ($complaint['status'] === 'pending' ? 'bg-yellow-100 text-yellow-700' :
                                                 'bg-gray-100 text-gray-700');
                                        ?>">
                                        <?= htmlspecialchars(ucfirst($complaint['status'])) ?>
                                    </span>

                                    <!-- Category & token -->
                                    <div class="mb-4">
                                        <div class="text-lg font-semibold text-gray-900 capitalize mb-1"><?= htmlspecialchars($complaint['category']) ?></div>
                                        <div class="text-xs bg-gray-50 text-gray-600 px-2 py-1 rounded-md inline-block">Token: <?= htmlspecialchars(substr($complaint['token'], 0, 8)) ?>...</div>
                                    </div>

                                    <!-- Dates -->
                                    <div class="text-sm text-gray-600 space-y-1 mb-4">
                                        <div class="flex items-center justify-between">
                                            <span>Created:</span> <span class="font-medium"><?= date('M j, Y', strtotime($complaint['created_at'])) ?></span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span>Updated:</span> <span class="font-medium"><?= date('M j, Y', strtotime($complaint['updated_at'])) ?></span>
                                        </div>
                                    </div>

                                    <!-- Assigned technician info -->
                                    <div class="text-sm text-gray-600 mb-4">
                                        Technician: 
                                        <?php if ($complaint['tech_name']): ?>
                                            <span class="font-medium text-gray-800"><?= htmlspecialchars($complaint['tech_name']) ?></span>
                                        <?php else: ?>
                                            <span class="italic text-gray-500">Not Assigned</span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="flex justify-between">
                                        <button class="show-qr-btn inline-flex items-center px-3 py-2 text-sm font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg" data-token="<?= htmlspecialchars($complaint['token']) ?>">
                                            <span class="material-icons text-sm mr-1">qr_code</span> QR
                                        </button>

                                        <form method="POST" action="delete_complaint.php" onsubmit="return confirm('Are you sure you want to delete this complaint?');">
                                            <input type="hidden" name="token" value="<?= htmlspecialchars($complaint['token']) ?>">
                                            <button type="submit" class="inline-flex items-center px-3 py-2 text-sm font-medium text-red-600 bg-red-50 hover:bg-red-100 rounded-lg">
                                                <span class="material-icons text-sm mr-1">delete</span> Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="p-6 text-center text-gray-500">
                            <span class="material-icons text-gray-400 text-6xl mb-4">inbox</span>
                            <p class="text-lg font-medium mb-2">No complaints yet</p>
                            <p class="text-sm">You haven't submitted any complaints.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <!-- QR Code Modal -->
    <div id="qr-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="relative bg-white rounded-xl shadow-lg p-6 w-full max-w-xs text-center">
                <button id="qr-close" class="absolute -top-3 -right-3 bg-white rounded-full shadow p-1 focus:outline-none">
                    <span class="material-icons text-gray-600 text-base">close</span>
                </button>
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Complaint QR Code</h3>
                <div id="qr-code-container" class="flex justify-center"></div>
            </div>
        </div>
    </div>

    <script src="../assets/js/qr-gen/qrcode.min.js"></script>
    <script>
        // Sidebar toggle (mobile)
        const sidebar = document.getElementById('sidebar');
        const backdrop = document.getElementById('sidebar-backdrop');
        const menuBtn = document.getElementById('mobile-menu-button');

        menuBtn.addEventListener('click', () => {
            sidebar.classList.toggle('-translate-x-full');
            backdrop.classList.toggle('hidden');
        });
        backdrop.addEventListener('click', () => {
            sidebar.classList.add('-translate-x-full');
            backdrop.classList.add('hidden');
        });

        // QR modal logic
        const qrModal = document.getElementById('qr-modal');
        const qrClose = document.getElementById('qr-close');
        const qrContainer = document.getElementById('qr-code-container');

        document.querySelectorAll('.show-qr-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const token = btn.dataset.token;
                qrContainer.innerHTML = '';
                new QRCode(qrContainer, {
                    text: token,
                    width: 200,
                    height: 200,
                });
                qrModal.classList.remove('hidden');
            });
        });

        qrClose.addEventListener('click', () => qrModal.classList.add('hidden'));
        qrModal.addEventListener('click', (e) => {
            if (e.target === qrModal) qrModal.classList.add('hidden');
        });

        // View details placeholder (could be expanded later)
        function viewComplaintDetails(token) {
            alert('Details view for token: ' + token + ' coming soon.');
        }
    </script>
    <?php include 'help_guide.php'; ?>
</body>
</html>
