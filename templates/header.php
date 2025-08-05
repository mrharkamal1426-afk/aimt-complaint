<?php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Complaint Management Portal - Army Institute of Management & Technology">
    <meta name="theme-color" content="#1e3a8a">
    <title>Complaint Management Portal - AIMT</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/complaint_portal/assets/images/aimt-logo.png">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Styles -->
    <link rel="stylesheet" href="/complaint_portal/assets/css/portal.css">
    
    <!-- Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    
    <!-- Modern Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    
    <!-- Error Handler Script -->
    <script src="/complaint_portal/assets/js/error-handler.js"></script>

    <!-- Theme System -->
    <style>
        :root {
            /* Light Theme */
            --primary-50: #eff6ff;
            --primary-500: #3b82f6;
            --primary-900: #1e3a8a;
            --success-500: #10b981;
            --warning-500: #f59e0b;
            --error-500: #ef4444;
            --neutral-50: #f9fafb;
            --neutral-100: #f3f4f6;
            --neutral-200: #e5e7eb;
            --neutral-300: #d1d5db;
            --neutral-400: #9ca3af;
            --neutral-500: #6b7280;
            --neutral-600: #4b5563;
            --neutral-700: #374151;
            --neutral-800: #1f2937;
            --neutral-900: #111827;
            
            /* Spacing System */
            --space-1: 0.25rem;
            --space-2: 0.5rem;
            --space-3: 0.75rem;
            --space-4: 1rem;
            --space-5: 1.25rem;
            --space-6: 1.5rem;
            --space-8: 2rem;
            --space-10: 2.5rem;
            --space-12: 3rem;
            
            /* Typography */
            --font-sans: 'Inter', system-ui, -apple-system, sans-serif;
            --font-size-xs: 0.75rem;
            --font-size-sm: 0.875rem;
            --font-size-base: 1rem;
            --font-size-lg: 1.125rem;
            --font-size-xl: 1.25rem;
            --font-size-2xl: 1.5rem;
            --font-size-3xl: 1.875rem;
            
            /* Shadows */
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            
            /* Transitions */
            --transition-all: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-transform: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-opacity: opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        [data-theme="dark"] {
            --primary-50: #1e293b;
            --primary-500: #3b82f6;
            --primary-900: #eff6ff;
            --neutral-50: #111827;
            --neutral-100: #1f2937;
            --neutral-200: #374151;
            --neutral-300: #4b5563;
            --neutral-400: #6b7280;
            --neutral-500: #9ca3af;
            --neutral-600: #d1d5db;
            --neutral-700: #e5e7eb;
            --neutral-800: #f3f4f6;
            --neutral-900: #f9fafb;
        }

        /* Base Styles */
        body {
            font-family: var(--font-sans);
            background-color: var(--neutral-50);
            color: var(--neutral-900);
            line-height: 1.5;
            transition: var(--transition-all);
        }

        /* Utility Classes */
        .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .gradient-bg {
            background: linear-gradient(135deg, var(--primary-500), #8b5cf6);
        }

        .text-gradient {
            background: linear-gradient(135deg, var(--primary-500), #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Animation Classes */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .animate-fade-in {
            animation: fadeIn 0.3s ease-out;
        }

        .animate-slide-up {
            animation: slideUp 0.3s ease-out;
        }

        .animate-pulse {
            animation: pulse 2s infinite;
        }
    </style>
<?php if (function_exists('generate_csrf_token')) { ?>
<script>
// Auto-inject CSRF token into every POST form if not already present
 document.addEventListener('DOMContentLoaded',()=>{
   const token = '<?= generate_csrf_token() ?>';
   document.querySelectorAll('form[method="post"]').forEach(form=>{
     if(!form.querySelector('input[name="csrf_token"]')){
       const hidden = document.createElement('input');
       hidden.type = 'hidden';
       hidden.name = 'csrf_token';
       hidden.value = token;
       form.appendChild(hidden);
     }
   });
 });
</script>
<?php } ?>
</head>
<body>
    <header class="portal-header">
        <div class="header-container">
            <div class="header-brand">
                <img src="/complaint_portal/assets/images/aimt-logo.png" alt="AIMT Logo" class="header-logo">
                <div>
                    <h1 class="header-title">Army Institute of Management & Technology</h1>
                    <p class="header-subtitle">Complaint Management Portal</p>
                </div>
            </div>
            <nav class="header-nav">
                <?php if (isset($_SESSION['role'])): ?>
                    <?php if ($_SESSION['role'] === 'superadmin'): ?>
                        <a href="/complaint_portal/superadmin/dashboard.php">
                            <span class="material-icons">dashboard</span>
                            <span>Dashboard</span>
                        </a>
                        <a href="/complaint_portal/superadmin/register_codes.php">
                            <span class="material-icons">key</span>
                            <span>Generate Codes</span>
                        </a>
                        <a href="/complaint_portal/auth/logout.php">
                            <span class="material-icons">logout</span>
                            <span>Logout</span>
                        </a>
                    <?php elseif ($_SESSION['role'] === 'technician'): ?>
                        <a href="/complaint_portal/technician/dashboard.php">
                            <span class="material-icons">dashboard</span>
                            <span>Dashboard</span>
                        </a>
                        <a href="/complaint_portal/auth/logout.php">
                            <span class="material-icons">logout</span>
                            <span>Logout</span>
                        </a>
                    <?php elseif (in_array($_SESSION['role'], ['student','faculty','nonteaching'])): ?>
                        <a href="/complaint_portal/user/dashboard.php">
                            <span class="material-icons">dashboard</span>
                            <span>Dashboard</span>
                        </a>
                        <a href="/complaint_portal/auth/logout.php">
                            <span class="material-icons">logout</span>
                            <span>Logout</span>
                        </a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="/complaint_portal/index.php">
                        <span class="material-icons">home</span>
                        <span>Home</span>
                    </a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <div class="page-container">
        <main class="container"> 