<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    redirect('../login.php?error=unauthorized');
}

$user_id = $_SESSION['user_id'];
$complaints = [];

// Get user's complaints
$query = "SELECT c.*, t.full_name as tech_name 
          FROM complaints c 
          LEFT JOIN users t ON t.id = c.technician_id 
          WHERE c.user_id = ? 
          ORDER BY c.created_at DESC";
$stmt = $mysqli->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $complaints[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AIMT - My Complaints</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/lucide-static@0.298.0/font/lucide.css" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        .glassmorphism {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }

        .sidebar-link {
            transition: all 0.2s ease;
        }

        .sidebar-link:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }

        .gradient-border {
            position: relative;
            border-radius: 16px;
            background: linear-gradient(to right, #0f172a, #1e293b);
        }

        .gradient-border::after {
            content: '';
            position: absolute;
            top: -1px;
            left: -1px;
            right: -1px;
            bottom: -1px;
            background: linear-gradient(60deg, #10b981, #0ea5e9);
            border-radius: 17px;
            z-index: -1;
            opacity: 0.3;
        }

        /* QR Code Modal */
        .qr-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 100;
            align-items: center;
            justify-content: center;
        }

        .qr-modal.active {
            display: flex;
        }

        .qr-modal-content {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            text-align: center;
            max-width: 90%;
            width: 300px;
        }

        .qr-modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: white;
            border-radius: 50%;
            width: 2rem;
            height: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .qr-code-small {
            cursor: pointer;
            transition: transform 0.2s;
        }

        .qr-code-small:hover {
            transform: scale(1.05);
        }

        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
                position: fixed;
                z-index: 50;
                width: 280px;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0 !important;
                padding: 1rem !important;
                padding-top: 5rem !important; /* Add space for hamburger menu */
            }

            .complaints-table {
                overflow-x: auto;
            }

            .complaints-table th,
            .complaints-table td {
                white-space: nowrap;
                padding: 0.75rem;
            }

            /* Improved hamburger menu positioning */
            #menu-toggle {
                top: 1rem !important;
                left: 1rem !important;
                width: 48px !important;
                height: 48px !important;
                z-index: 60 !important;
                transition: all 0.3s ease !important;
            }

            /* Hide hamburger menu when sidebar is active */
            #menu-toggle.sidebar-active {
                transform: translateX(-100%) !important;
                opacity: 0 !important;
                pointer-events: none !important;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 0.75rem !important;
                padding-top: 4.5rem !important;
            }

            #menu-toggle {
                top: 0.75rem !important;
                left: 0.75rem !important;
                width: 44px !important;
                height: 44px !important;
            }

            /* Hide hamburger menu when sidebar is active on small screens */
            #menu-toggle.sidebar-active {
                transform: translateX(-100%) !important;
                opacity: 0 !important;
                pointer-events: none !important;
            }
        }

        @keyframes hamburger-pulse {
            0% { box-shadow: 0 0 0 0 rgba(16,185,129,0.3); }
            70% { box-shadow: 0 0 0 8px rgba(16,185,129,0); }
            100% { box-shadow: 0 0 0 0 rgba(16,185,129,0); }
        }
        
        .animate-hamburger-pulse {
            animation: hamburger-pulse 2s infinite;
        }
    </style>
</head>
<body class="bg-slate-50">
    <!-- QR Code Modal -->
    <div class="qr-modal">
        <div class="qr-modal-content">
            <div class="qr-modal-close">
            <button onclick="closeModal()" 
        class="absolute  text-gray-500 hover:text-black text-3xl font-bold focus:outline-none">
  &times;
</button>
            </div>
            <h3 class="text-lg font-semibold text-slate-900 mb-4">Complaint QR Code</h3>
            <div id="qr-modal-code" class="flex justify-center mb-4"></div>
            <div class="text-sm text-slate-500">Show this QR code to the technician to get your complaint resolved</div>
        </div>
    </div>

    <!-- Menu Button (always visible, improved style) -->
    <button id="menu-toggle"
        class="fixed top-4 left-4 z-50 flex items-center justify-center w-12 h-12 rounded-full bg-white/90 shadow-2xl border border-emerald-300 hover:bg-emerald-100 focus:bg-emerald-200 transition-all duration-200 outline-none ring-emerald-400 ring-offset-2 ring-offset-white focus:ring-4 animate-hamburger-pulse"
        aria-label="Toggle navigation menu">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="block">
            <line x1="4" y1="6" x2="20" y2="6" />
            <line x1="4" y1="12" x2="20" y2="12" />
            <line x1="4" y1="18" x2="20" y2="18" />
        </svg>
    </button>
    <div id="sidebar-overlay" class="fixed inset-0 bg-black bg-opacity-40 z-40 hidden"></div>
    <div class="flex min-h-screen">
        <!-- Sidebar (hidden by default, overlays content) -->
        <aside class="sidebar w-64 bg-slate-900 text-white flex flex-col fixed h-full shadow-xl z-50 hidden" tabindex="-1">
            <div class="px-6 py-4 gradient-border flex items-center gap-4">
                <div class="bg-white p-1.5 rounded-lg">
                    <img src="../assets/images/aimt-logo.png" alt="AIMT Logo" class="w-10 h-10 logo-glow" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                    <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center text-white font-bold text-lg" style="display:none;">A</div>
                </div>
                <div>
                    <h1 class="text-base font-bold tracking-tight">Complaint<span class="text-emerald-400">System</span></h1>
                    <div class="text-xs text-slate-400">AIMT Portal</div>
                </div>
            </div>
            <div class="px-6 py-4 border-b border-slate-700/50">
                <div class="font-semibold text-lg"><?= htmlspecialchars($_SESSION['full_name'] ?? 'Superadmin') ?></div>
                <div class="text-emerald-400 text-sm">Superadmin</div>
            </div>
            <nav class="flex-1 px-4 py-4 space-y-3 overflow-y-auto">
                <a href="dashboard.php" class="sidebar-link flex items-center px-4 py-3 rounded-xl">
                    <i class="lucide-layout-dashboard mr-3 text-slate-400"></i>
                    <span>Dashboard</span>
                </a>
                <a href="view_complaints.php" class="sidebar-link flex items-center px-4 py-3 rounded-xl">
                    <i class="lucide-database mr-3 text-slate-400"></i>
                    <span>View All Complaints</span>
                </a>
                <a href="submit_complaint.php" class="sidebar-link flex items-center px-4 py-3 rounded-xl">
                    <i class="lucide-plus-circle mr-3 text-slate-400"></i>
                    <span>Submit Complaint</span>
                </a>
                <a href="my_complaints.php" class="sidebar-link flex items-center px-4 py-3 rounded-xl bg-slate-800/50">
                    <i class="lucide-list-checks mr-3 text-emerald-400"></i>
                    <span>My Complaints</span>
                </a>
                <a href="user_management.php" class="sidebar-link flex items-center px-4 py-3 rounded-xl">
                    <i class="lucide-users mr-3 text-slate-400"></i>
                    <span>User Management</span>
                </a>
                <a href="admin_management.php" class="sidebar-link flex items-center px-4 py-3 rounded-xl">
                    <i class="lucide-shield mr-3 text-slate-400"></i>
                    <span>Admin Management</span>
                </a>
                <a href="auto_assignment.php" class="sidebar-link flex items-center px-4 py-3 rounded-xl">
                    <i class="lucide-zap mr-3 text-slate-400"></i>
                    <span>Auto Assignment</span>
                </a>
                <a href="technician_status.php" class="sidebar-link flex items-center px-4 py-3 rounded-xl">
                    <i class="lucide-toggle-left mr-3 text-slate-400"></i>
                    <span>Technician Status</span>
                </a>
                <a href="reports.php" class="sidebar-link flex items-center px-4 py-3 rounded-xl">
                    <i class="lucide-bar-chart mr-3 text-slate-400"></i>
                    <span>Reports</span>
                </a>
                <a href="manage_suggestions.php" class="sidebar-link flex items-center px-4 py-3 rounded-xl">
                    <i class="lucide-lightbulb mr-3 text-slate-400"></i>
                    <span>Manage Suggestions</span>
                </a>
                <a href="register_codes.php" class="sidebar-link flex items-center px-4 py-3 rounded-xl">
                    <i class="lucide-key mr-3 text-slate-400"></i>
                    <span>Generate Codes</span>
                </a>
                <a href="../auth/logout.php" class="sidebar-link flex items-center px-4 py-3 rounded-xl mt-auto">
                    <i class="lucide-log-out mr-3 text-slate-400"></i>
                    <span>Logout</span>
                </a>
            </nav>
        </aside>
        <!-- Main Content -->
        <div class="main-content flex-1 flex flex-col md:ml-64 w-full">
            <!-- Include the new header template -->
            <?php include '../templates/superadmin_header.php'; ?>
            
            <!-- Content Container with proper spacing -->
            <div class="px-4 sm:px-6 md:px-8 py-6"><!-- Complaints List -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="p-4 border-b border-gray-100">
                    <h2 class="text-lg font-semibold text-gray-800">My Complaints</h2>
                </div>
                <?php if ($complaints): ?>
                    <div class="overflow-x-auto">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 p-6">
                                <?php foreach ($complaints as $complaint): ?>
                            <div class="relative bg-white border border-gray-200 rounded-xl shadow-sm hover:shadow-lg hover:-translate-y-1 transition-all duration-200 p-6">
                                <!-- Status badge -->
                                <span class="absolute top-4 right-4 px-3 py-1 rounded-full text-xs font-semibold
                                    <?= $complaint['status'] === 'resolved' ? 'bg-green-100 text-green-700 border border-green-200' : 
                                       ($complaint['status'] === 'pending' ? 'bg-yellow-100 text-yellow-700 border border-yellow-200' : 
                                       'bg-gray-100 text-gray-700 border border-gray-200') ?>">
                                    <?= htmlspecialchars(ucfirst($complaint['status'])) ?>
                                            </span>
                                
                                <!-- Header with Category and Token -->
                                <div class="flex items-center space-x-3 mb-4">
                                    <div class="bg-blue-50 rounded-lg p-3">
                                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                    </div>
                                    <div class="flex-1">
                                        <div class="text-lg font-semibold text-gray-900 mb-1"><?= htmlspecialchars(ucfirst($complaint['category'])) ?></div>
                                        <div class="text-xs text-gray-500 bg-gray-50 px-2 py-1 rounded-md inline-block">Token: <?= htmlspecialchars(substr($complaint['token'], 0, 8)) ?>...</div>
                                    </div>
                                </div>
                                
                                <!-- QR Code Button -->
                                <div class="flex justify-center mb-4">
                                    <button class="show-qr-btn inline-flex items-center px-4 py-2 bg-blue-50 hover:bg-blue-100 text-blue-600 rounded-lg border border-blue-200 transition-colors text-sm font-medium" data-token="<?= htmlspecialchars($complaint['token']) ?>">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                                        </svg>
                                        Show QR Code
                                    </button>
                                </div>
                                
                                <!-- Dates -->
                                <div class="bg-gray-50 rounded-lg p-3 mb-4">
                                    <div class="flex flex-col space-y-1 text-sm text-gray-600">
                                        <div class="flex items-center justify-between">
                                            <span>Created:</span>
                                            <span class="font-medium"><?= htmlspecialchars(date('M j, Y', strtotime($complaint['created_at']))) ?></span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span>Updated:</span>
                                            <span class="font-medium"><?= htmlspecialchars(date('M j, Y', strtotime($complaint['updated_at']))) ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Technician Info -->
                                <div class="bg-blue-50 rounded-lg p-3 mb-4">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm text-gray-600">Technician:</span>
                                        <?php if ($complaint['tech_name']): ?>
                                            <span class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($complaint['tech_name']) ?></span>
                                        <?php else: ?>
                                            <span class="text-sm text-gray-500 italic">Not Assigned</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Action Button -->
                                <div class="flex justify-end">
                                            <form method="POST" action="delete_complaint.php" onsubmit="return confirm('Are you sure you want to delete this complaint?');" style="display:inline;">
                                                <input type="hidden" name="token" value="<?= htmlspecialchars($complaint['token']) ?>">
                                                                                <button type="submit" class="inline-flex items-center px-4 py-2 rounded-lg bg-red-50 hover:bg-red-100 text-red-600 text-sm font-medium transition-colors border border-red-200">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                            Delete
                                        </button>
                                            </form>
                                </div>
                            </div>
                                <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="p-6 text-center">
                        <div class="text-gray-500 mb-4">
                            <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <div class="text-lg font-medium">No complaints yet</div>
                            <div class="text-sm">You haven't submitted any complaints yet.</div>
                        </div>
                        <a href="submit_complaint.php" class="inline-flex items-center justify-center px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition-colors font-medium text-sm">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Submit Your First Complaint
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../assets/js/qr-gen/qrcode.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // QR Modal functionality
            const modal = document.querySelector('.qr-modal');
            const modalClose = document.querySelector('.qr-modal-close');
            const modalQR = document.getElementById('qr-modal-code');

            document.querySelectorAll('.show-qr-btn').forEach(button => {
                button.addEventListener('click', () => {
                    const token = button.dataset.token;
                    modalQR.innerHTML = ''; // Clear previous QR code
                    new QRCode(modalQR, {
                        text: token,
                        width: 200,
                        height: 200,
                        colorDark: "#0f172a",
                        colorLight: "#ffffff",
                        correctLevel: QRCode.CorrectLevel.H
                    });
                    modal.classList.add('active');
                });
            });

            modalClose.addEventListener('click', () => {
                modal.classList.remove('active');
            });

            // Close modal when clicking outside
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.classList.remove('active');
                }
            });

            // Mobile menu toggle
            const menuToggle = document.getElementById('menu-toggle');
            const sidebar = document.querySelector('.sidebar');
            const sidebarOverlay = document.getElementById('sidebar-overlay');

            menuToggle.addEventListener('click', () => {
                sidebar.classList.add('active');
                sidebar.classList.remove('hidden');
                sidebarOverlay.classList.add('active');
                sidebarOverlay.classList.remove('hidden');
                menuToggle.classList.add('sidebar-active');
                sidebar.focus();
            });

            function closeSidebar() {
                sidebar.classList.remove('active');
                sidebar.classList.add('hidden');
                sidebarOverlay.classList.remove('active');
                menuToggle.classList.remove('sidebar-active');
                setTimeout(() => {
                    sidebarOverlay.classList.add('hidden');
                }, 300);
            }

            sidebarOverlay.addEventListener('click', closeSidebar);
            window.addEventListener('resize', () => {
                if (window.innerWidth > 768) {
                    closeSidebar();
                }
            });

            // Close sidebar when pressing Escape key
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && sidebar.classList.contains('active')) {
                    closeSidebar();
                }
            });
        });
    </script>
</body>
</html> 