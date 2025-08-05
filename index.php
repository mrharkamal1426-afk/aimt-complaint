<?php
if (isset($_SERVER['PATH_INFO']) && $_SERVER['PATH_INFO'] !== '/') {
    header('Location: /complaint_portal/error.php?code=404');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AIMT - Complaint Management Portal</title>
    <link rel="icon" type="image/png" href="assets/images/aimt-logo.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        * {
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .hero-pattern {
            background-color: #1a2f4e;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23233656' fill-opacity='0.4'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            animation: bg-move 16s linear infinite alternate;
        }
        @keyframes bg-move {
            0% { background-position: 0 0; }
            100% { background-position: 60px 30px; }
        }

        .military-gradient {
            background: linear-gradient(135deg, #1a2f4e 0%, #2c4875 100%);
        }

        .feature-card {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
            transition: all 0.3s cubic-bezier(.4,2,.3,1) 0.3s;
            opacity: 0;
            transform: translateY(40px);
            animation: fadeInUp 1s cubic-bezier(.4,2,.3,1) forwards;
        }
        .feature-card:nth-child(1) { animation-delay: 0.2s; }
        .feature-card:nth-child(2) { animation-delay: 0.4s; }
        .feature-card:nth-child(3) { animation-delay: 0.6s; }
        .feature-card:nth-child(4) { animation-delay: 0.8s; }
        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .feature-card:hover {
            transform: translateY(-8px) scale(1.04);
            box-shadow: 0 8px 32px rgba(26, 47, 78, 0.18);
        }
        .action-button {
            transition: all 0.3s ease;
            background: linear-gradient(135deg, #1a2f4e 0%, #2c4875 100%);
        }
        .action-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(26, 47, 78, 0.3);
        }
        .logo-glow {
            filter: drop-shadow(0 0 10px rgba(59, 130, 246, 0.3));
            background-color: white;
            padding: 4px;
            border-radius: 8px;
        }
        .fade-in-hero {
            opacity: 0;
            transform: translateY(40px);
            animation: fadeInUp 1.2s cubic-bezier(.4,2,.3,1) 0.1s forwards;
        }
        .feature-icon {
            transition: transform 0.3s cubic-bezier(.4,2,.3,1);
        }
        .feature-icon:hover {
            animation: icon-bounce 0.7s cubic-bezier(.4,2,.3,1);
        }
        @keyframes icon-bounce {
            0% { transform: scale(1); }
            20% { transform: scale(1.2) translateY(-6px); }
            40% { transform: scale(0.95) translateY(2px); }
            60% { transform: scale(1.05) translateY(-2px); }
            80% { transform: scale(1.02) translateY(1px); }
            100% { transform: scale(1) translateY(0); }
        }
        
        /* Logo fallback styles */
        .logo-fallback {
            display: none;
            background: white;
            padding: 8px;
            border-radius: 8px;
            font-weight: bold;
            color: #1a2f4e;
            text-align: center;
            line-height: 1;
        }
        .logo-fallback.large {
            padding: 12px;
            border-radius: 12px;
            font-size: 1.5rem;
        }
    </style>
</head>
<body class="bg-slate-50">
    <!-- Header -->
    <header class="military-gradient text-white">
        <div class="container mx-auto px-4 py-3">
            <div class="flex items-center justify-center gap-4">
                <img src="assets/images/aimt-logo.png" alt="AIMT Logo" class="w-14 h-14 logo-glow" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                <div class="logo-fallback w-14 h-14">AIMT</div>
                <div>
                    <h1 class="text-xl font-semibold">Army Institute of Management & Technology</h1>
                    <div class="text-sm text-blue-200">Greater Noida</div>
                </div>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero-pattern text-white relative py-20 fade-in-hero">
        <div class="absolute inset-0 bg-black/30"></div>
        <div class="container mx-auto px-4 relative">
            <div class="max-w-3xl mx-auto text-center">
                <h1 class="text-4xl md:text-5xl font-bold mb-6">Complaint Management Portal</h1>
                <p class="text-xl text-blue-100 mb-12">Your voice matters – Report, Track, and Resolve campus issues easily.</p>
                <div class="flex gap-6 justify-center">
                    <a href="auth/login.php" class="action-button px-8 py-3 rounded-lg text-white font-medium inline-flex items-center gap-2">
                        <i class="lucide-log-in"></i>
                        Login
                    </a>
                    <a href="auth/register.php" class="bg-white/10 backdrop-blur-sm px-8 py-3 rounded-lg text-white font-medium hover:bg-white/20 transition-all inline-flex items-center gap-2">
                        <i class="lucide-user-plus"></i>
                        Register
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-20 bg-slate-50">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <!-- Submit Complaints -->
                <div class="feature-card rounded-2xl p-6 shadow-lg">
                    <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center mb-4">
                        <i data-lucide="file-plus" class="text-blue-600 text-2xl feature-icon"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-900 mb-2">Submit Complaints</h3>
                    <p class="text-slate-600">Instantly raise issues like Wi-Fi, Mess, Electrician, and more through our streamlined system.</p>
                </div>

                <!-- Track Progress -->
                <div class="feature-card rounded-2xl p-6 shadow-lg">
                    <div class="w-12 h-12 bg-emerald-100 rounded-xl flex items-center justify-center mb-4">
                        <i data-lucide="qr-code" class="text-emerald-600 text-2xl feature-icon"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-900 mb-2">Track Progress</h3>
                    <p class="text-slate-600">Use unique QR codes to view real-time updates on your complaint status.</p>
                </div>

                <!-- Suggestions & Feedback -->
                <div class="feature-card rounded-2xl p-6 shadow-lg">
                    <div class="w-12 h-12 bg-yellow-100 rounded-xl flex items-center justify-center mb-4">
                        <i data-lucide="lightbulb" class="text-yellow-600 text-2xl feature-icon"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-900 mb-2">Suggestions & Feedback</h3>
                    <p class="text-slate-600">Share your ideas to improve campus life! Submit new suggestions or vote on others' ideas to make your voice heard.</p>
                </div>

                <!-- Technician Resolution -->
                <div class="feature-card rounded-2xl p-6 shadow-lg">
                    <div class="w-12 h-12 bg-yellow-100 rounded-xl flex items-center justify-center mb-4">
                        <i data-lucide="wrench" class="text-yellow-600 text-2xl feature-icon"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-900 mb-2">Expert Resolution</h3>
                    <p class="text-slate-600">Dedicated technicians scan QR codes and provide swift resolution to your issues.</p>
                </div>

                <!-- Admin Oversight -->
                <div class="feature-card rounded-2xl p-6 shadow-lg">
                    <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center mb-4">
                        <i data-lucide="bar-chart" class="text-purple-600 text-2xl feature-icon"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-900 mb-2">Admin Oversight</h3>
                    <p class="text-slate-600">Comprehensive management system with analytics and detailed reporting.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-slate-900 text-white py-8">
        <div class="container mx-auto px-4">
            <div class="text-center">
                <img src="assets/images/aimt-logo.png" alt="AIMT Logo" class="w-20 h-20 mx-auto mb-4 logo-glow" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                <div class="logo-fallback large w-20 h-20 mx-auto mb-4">AIMT</div>
                <h2 class="text-xl font-semibold mb-2">Army Institute of Management & Technology</h2>
                <p class="text-slate-400 mb-1">Plot M-1, Pocket P-5, Greater Noida, Gautam Buddha Nagar (UP) - 201310</p>
                <div class="w-24 h-1 bg-blue-500 mx-auto my-4"></div>
                <p class="text-sm text-slate-500">Designed & Developed by Student, for Students.</p>
            </div>
        </div>
    </footer>
    
    <script>
        // Initialize Lucide icons with error handling
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        } else {
            // Lucide icons not loaded, using fallback
            // Replace icon elements with text fallbacks
            document.querySelectorAll('[data-lucide]').forEach(function(element) {
                const iconName = element.getAttribute('data-lucide');
                element.innerHTML = iconName.charAt(0).toUpperCase() + iconName.slice(1);
                element.style.fontSize = '1.5rem';
                element.style.fontWeight = 'bold';
            });
        }
    </script>

    <!-- 
┌───────────────────────────────────────────────────────────────────────────────┐
│ [ SYSTEM SIGNATURE: THE LEGACY OF CODE ]                                      │
│ DEVELOPER:   MR.HARKAMAL                                                      │
│ STATUS:      AUTHORIZED MASTER                                                │
│ ACCESS:      ROOT                                                             │
│ YEAR:        2025                                                             │
│ LOCATION:    AIMT_GREATER_NOIDA                                               │ 
│                                                                               │
│ [ SYSTEM MESSAGE ]                                                            │
│ {                                                                             │
│    "creator": "MR.HARKAMAL",                                                  │
│    "mission": "To empower and connect",                                       │
│    "purpose": "Serve AIMT students",                                          │
│    "status": "DEPLOYED"                                                       │
│ }                                                                             │
│                                                                               │
│ ENCRYPTED SIGNATURE:                                                          │
│ H4X0R-HKML-2025-AIMT                                                          │
│                                                                               │
│ [END OF TRANSMISSION]                                                         │
│ "Through Adversity to the Stars"                                              │
└───────────────────────────────────────────────────────────────────────────────┘
-->
</body>
</html> 