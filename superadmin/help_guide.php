<?php
/**
 * Super-Admin Help Guide Modal (include in super-admin pages)
 * Optimized for content restrictions and proper modal display
 */
?>
<!-- Super-Admin Help Guide Modal -->
<div id="help-modal" class="fixed inset-0 bg-black bg-opacity-50 z-[9999] items-center justify-center p-4" style="display: none;">
    <div class="bg-white dark:bg-gray-800 rounded-xl max-w-5xl w-full max-h-[85vh] overflow-hidden shadow-2xl border border-gray-200 dark:border-gray-700">
        <!-- Header -->
        <div class="flex justify-between items-center border-b border-gray-200 dark:border-gray-700 px-6 py-4 bg-gray-50 dark:bg-gray-900">
            <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Super-Admin Dashboard Guide</h3>
            <button onclick="closeHelpModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        
        <!-- Content -->
        <div class="overflow-y-auto max-h-[75vh] p-6">
            <div class="space-y-6">
                <!-- Overview -->
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/30 dark:to-indigo-900/30 p-4 rounded-lg border border-blue-200 dark:border-blue-800">
                    <h4 class="flex items-center text-lg font-medium text-blue-900 dark:text-blue-100 mb-2">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                        Control Center Overview
                    </h4>
                    <p class="text-blue-800 dark:text-blue-200 text-sm">
                        Access system analytics, monitor KPIs, and manage platform performance.
                    </p>
                </div>

                <!-- Features Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Dashboard -->
                    <div class="bg-green-50 dark:bg-green-900/30 p-4 rounded-lg border border-green-200 dark:border-green-800">
                        <h4 class="flex items-center font-medium text-green-900 dark:text-green-100 mb-2">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                            Dashboard & Analytics
                        </h4>
                        <p class="text-green-800 dark:text-green-200 text-sm">
                            Monitor real-time metrics and system performance.
                        </p>
                    </div>

                    <!-- Auto Assignment -->
                    <div class="bg-purple-50 dark:bg-purple-900/30 p-4 rounded-lg border border-purple-200 dark:border-purple-800">
                        <h4 class="flex items-center font-medium text-purple-900 dark:text-purple-100 mb-2">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                            Auto-Assignment
                        </h4>
                        <p class="text-purple-800 dark:text-purple-200 text-sm">
                            Configure complaint distribution systems.
                        </p>
                    </div>

                    <!-- User Management -->
                    <div class="bg-orange-50 dark:bg-orange-900/30 p-4 rounded-lg border border-orange-200 dark:border-orange-800">
                        <h4 class="flex items-center font-medium text-orange-900 dark:text-orange-100 mb-2">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                            </svg>
                            User Management
                        </h4>
                        <p class="text-orange-800 dark:text-orange-200 text-sm">
                            Create, modify, and manage user accounts.
                        </p>
                    </div>

                    <!-- Reports -->
                    <div class="bg-cyan-50 dark:bg-cyan-900/30 p-4 rounded-lg border border-cyan-200 dark:border-cyan-800">
                        <h4 class="flex items-center font-medium text-cyan-900 dark:text-cyan-100 mb-2">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Reports & Export
                        </h4>
                        <p class="text-cyan-800 dark:text-cyan-200 text-sm">
                            Generate reports in PDF, Excel, or CSV formats.
                        </p>
                    </div>

                    <!-- Complaint Management -->
                    <div class="bg-indigo-50 dark:bg-indigo-900/30 p-4 rounded-lg border border-indigo-200 dark:border-indigo-800">
                        <h4 class="flex items-center font-medium text-indigo-900 dark:text-indigo-100 mb-2">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                            </svg>
                            Complaint Management
                        </h4>
                        <p class="text-indigo-800 dark:text-indigo-200 text-sm">
                            Filter and assign complaints to technicians.
                        </p>
                    </div>

                    <!-- Security -->
                    <div class="bg-red-50 dark:bg-red-900/30 p-4 rounded-lg border border-red-200 dark:border-red-800">
                        <h4 class="flex items-center font-medium text-red-900 dark:text-red-100 mb-2">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                            Security & Audit
                        </h4>
                        <p class="text-red-800 dark:text-red-200 text-sm">
                            Monitor audit logs and user activities.
                        </p>
                    </div>
                </div>

                <!-- Best Practices -->
                <div class="bg-yellow-50 dark:bg-yellow-900/30 p-4 rounded-lg border border-yellow-200 dark:border-yellow-800">
                    <h4 class="flex items-center font-medium text-yellow-900 dark:text-yellow-100 mb-3">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                        </svg>
                        Best Practices
                    </h4>
                    <ul class="text-yellow-800 dark:text-yellow-200 text-sm space-y-2">
                        <li class="flex items-center">
                            <span class="w-1.5 h-1.5 bg-yellow-500 rounded-full mr-2"></span>
                            Review system metrics daily
                        </li>
                        <li class="flex items-center">
                            <span class="w-1.5 h-1.5 bg-yellow-500 rounded-full mr-2"></span>
                            Audit user permissions regularly
                        </li>
                        <li class="flex items-center">
                            <span class="w-1.5 h-1.5 bg-yellow-500 rounded-full mr-2"></span>
                            Generate weekly reports
                        </li>
                        <li class="flex items-center">
                            <span class="w-1.5 h-1.5 bg-yellow-500 rounded-full mr-2"></span>
                            Monitor audit logs for security
                        </li>
                    </ul>
                </div>

                <!-- Support -->
                <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700 text-center">
                    <p class="text-gray-600 dark:text-gray-300 text-sm">
                        Need support? Check system status or contact technical support.
                    </p>
                </div>

                <!-- Footer -->
                <div class="text-center opacity-60 hover:opacity-100 transition-opacity duration-300">
                    <div class="text-[10px] tracking-widest text-gray-400 dark:text-gray-500 mb-1">
                        DEVELOPED BY
                    </div>
                    <div class="text-[9px] tracking-widest font-mono text-gray-500 dark:text-gray-400">
                        MR.HARKAMAL
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showHelpModal() {
    const modal = document.getElementById('help-modal');
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

function closeHelpModal() {
    const modal = document.getElementById('help-modal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Help button
    const helpBtn = document.getElementById('help-button');
    if (helpBtn) {
        helpBtn.addEventListener('click', function(e) {
            e.preventDefault();
            showHelpModal();
        });
    }
    
    // Close on backdrop click
    const modal = document.getElementById('help-modal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeHelpModal();
            }
        });
    }
    
    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeHelpModal();
        }
    });
});
</script>

<style>
/* Additional CSS for better modal behavior */
#help-modal {
    backdrop-filter: blur(4px);
}

#help-modal > div {
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: scale(0.9) translateY(-20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

/* Ensure proper z-index stacking */
.z-\[9999\] {
    z-index: 9999;
}
</style>