<?php
/**
 * Technician Help Guide Modal (include in technician pages)
 * Updated to match simple aesthetic design
 */
?>
<!-- Technician Help Guide Modal -->
<div id="help-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white dark:bg-dark-bg-secondary rounded-xl max-w-4xl w-full p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-semibold text-gray-900 dark:text-dark-text-primary">Technician Dashboard Guide</h3>
            <button onclick="closeHelpModal()" class="text-gray-400 dark:text-dark-text-secondary hover:text-gray-600 dark:hover:text-dark-text-primary p-1 rounded-full hover:bg-gray-100 dark:hover:bg-dark-bg-primary">
                <span class="material-icons">close</span>
            </button>
        </div>
        
        <div class="space-y-6">
            <!-- Quick Overview -->
            <div class="bg-gradient-to-r from-blue-50 to-purple-50 dark:from-blue-900/20 dark:to-purple-900/20 p-4 rounded-lg border border-blue-100 dark:border-blue-800">
                <h4 class="flex items-center text-lg font-medium text-blue-900 dark:text-blue-100 mb-2">
                    <span class="material-icons mr-2">dashboard</span>
                    Dashboard Overview
                </h4>
                <p class="text-blue-800 dark:text-blue-200 text-sm leading-relaxed">
                    Your central workspace to view all assigned complaints, track pending and resolved counts, monitor your resolution rate, and access comprehensive statistics about your workload and performance metrics.
                </p>
            </div>

            <!-- Main Features Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- My Complaints Page -->
                <div class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg border border-green-100 dark:border-green-800">
                    <h4 class="flex items-center font-medium text-green-900 dark:text-green-100 mb-2">
                        <span class="material-icons mr-2">person_pin</span>
                        My Complaints Page
                    </h4>
                    <p class="text-green-800 dark:text-green-200 text-sm leading-relaxed">
                        View all complaints that <strong>you have submitted</strong> during your maintenance rounds or inspections. Track the status and progress of issues you've reported to the system.
                    </p>
                </div>

                <!-- All Complaints (Assigned) -->
                <div class="bg-purple-50 dark:bg-purple-900/20 p-4 rounded-lg border border-purple-100 dark:border-purple-800">
                    <h4 class="flex items-center font-medium text-purple-900 dark:text-purple-100 mb-2">
                        <span class="material-icons mr-2">assignment</span>
                        All Complaints (Assigned)
                    </h4>
                    <p class="text-purple-800 dark:text-purple-200 text-sm leading-relaxed">
                        View all complaints <strong>assigned to you</strong> based on your specialization. Update status, add remarks, and manage resolution workflow for issues requiring your expertise.
                    </p>
                </div>

                <!-- QR Code Scanner -->
                <div class="bg-orange-50 dark:bg-orange-900/20 p-4 rounded-lg border border-orange-100 dark:border-orange-800">
                    <h4 class="flex items-center font-medium text-orange-900 dark:text-orange-100 mb-2">
                        <span class="material-icons mr-2">qr_code_scanner</span>
                        QR Code Scanner
                    </h4>
                    <p class="text-orange-800 dark:text-orange-200 text-sm leading-relaxed">
                        Visit complainants on-site and scan their unique QR codes to instantly update complaint status, add detailed resolution notes, and mark issues as resolved with real-time updates.
                    </p>
                </div>

                <!-- Complaint Submission -->
                <div class="bg-cyan-50 dark:bg-cyan-900/20 p-4 rounded-lg border border-cyan-100 dark:border-cyan-800">
                    <h4 class="flex items-center font-medium text-cyan-900 dark:text-cyan-100 mb-2">
                        <span class="material-icons mr-2">add_circle</span>
                        Submit New Complaints
                    </h4>
                    <p class="text-cyan-800 dark:text-cyan-200 text-sm leading-relaxed">
                        Report new complaints you discover during maintenance rounds or routine inspections. All your submitted complaints can be tracked on the "My Complaints" page.
                    </p>
                </div>
            </div>

            <!-- Status Management -->
            <div class="bg-indigo-50 dark:bg-indigo-900/20 p-4 rounded-lg border border-indigo-100 dark:border-indigo-800">
                <h4 class="flex items-center font-medium text-indigo-900 dark:text-indigo-100 mb-3">
                    <span class="material-icons mr-2">toggle_on</span>
                    Availability Status Management
                </h4>
                <p class="text-indigo-800 dark:text-indigo-200 text-sm mb-3 leading-relaxed">
                    Control your availability status to manage complaint assignments effectively:
                </p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                    <div class="flex items-center text-indigo-700 dark:text-indigo-300 bg-white dark:bg-indigo-900/30 p-2 rounded">
                        <span class="material-icons text-green-500 mr-2 text-lg">radio_button_checked</span>
                        <span><strong>Online:</strong> Receive new assignments</span>
                    </div>
                    <div class="flex items-center text-indigo-700 dark:text-indigo-300 bg-white dark:bg-indigo-900/30 p-2 rounded">
                        <span class="material-icons text-red-500 mr-2 text-lg">radio_button_unchecked</span>
                        <span><strong>Offline:</strong> No new assignments (contact admin)</span>
                    </div>
                </div>
            </div>

            <!-- Best Practices -->
            <div class="bg-yellow-50 dark:bg-yellow-900/20 p-4 rounded-lg border border-yellow-100 dark:border-yellow-800">
                <h4 class="flex items-center font-medium text-yellow-900 dark:text-yellow-100 mb-3">
                    <span class="material-icons mr-2">tips_and_updates</span>
                    Best Practices & Workflow
                </h4>
                <div class="space-y-3">
                    <div class="flex items-start text-yellow-800 dark:text-yellow-200 text-sm">
                        <span class="material-icons text-yellow-500 mr-3 text-lg mt-0.5">trending_up</span>
                        <div>
                            <strong>Status Flow:</strong> Follow the proper workflow sequence<br>
                            <span class="text-xs opacity-75">Admin Assigned → In-Progress → Resolved</span>
                        </div>
                    </div>
                    <div class="flex items-start text-yellow-800 dark:text-yellow-200 text-sm">
                        <span class="material-icons text-yellow-500 mr-3 text-lg mt-0.5">qr_code</span>
                        <div>
                            <strong>QR Code Usage:</strong> Always scan QR codes on-site<br>
                            <span class="text-xs opacity-75">Ensures accurate status updates and location verification</span>
                        </div>
                    </div>
                    <div class="flex items-start text-yellow-800 dark:text-yellow-200 text-sm">
                        <span class="material-icons text-yellow-500 mr-3 text-lg mt-0.5">edit_note</span>
                        <div>
                            <strong>Documentation:</strong> Add detailed remarks for every action<br>
                            <span class="text-xs opacity-75">Include status changes, resolution steps, and completion notes</span>
                        </div>
                    </div>
                    <div class="flex items-start text-yellow-800 dark:text-yellow-200 text-sm">
                        <span class="material-icons text-yellow-500 mr-3 text-lg mt-0.5">schedule</span>
                        <div>
                            <strong>Performance Monitoring:</strong> Track your resolution rate<br>
                            <span class="text-xs opacity-75">Maintain high performance standards and timely responses</span>
                        </div>
                    </div>
                    <div class="flex items-start text-yellow-800 dark:text-yellow-200 text-sm">
                        <span class="material-icons text-yellow-500 mr-3 text-lg mt-0.5">admin_panel_settings</span>
                        <div>
                            <strong>Status Management:</strong> Contact admin for availability changes<br>
                            <span class="text-xs opacity-75">Required for offline status during breaks or emergencies</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Page Distinction Info -->
            <div class="bg-gray-50 dark:bg-gray-900/20 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                <h4 class="flex items-center font-medium text-gray-900 dark:text-gray-100 mb-3">
                    <span class="material-icons mr-2">info</span>
                    Important Page Distinctions
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div class="bg-white dark:bg-gray-800 p-3 rounded border-l-4 border-green-500">
                        <div class="font-medium text-green-700 dark:text-green-400 mb-1">My Complaints</div>
                        <div class="text-gray-600 dark:text-gray-300">Complaints YOU have submitted to the system</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 p-3 rounded border-l-4 border-purple-500">
                        <div class="font-medium text-purple-700 dark:text-purple-400 mb-1">All Complaints</div>
                        <div class="text-gray-600 dark:text-gray-300">Complaints ASSIGNED to you based on specialization</div>
                    </div>
                </div>
            </div>

            <!-- Contact Support -->
            <div class="bg-gray-50 dark:bg-gray-900/20 p-4 rounded-lg border border-gray-200 dark:border-gray-700 text-center">
                <p class="text-gray-600 dark:text-gray-300 text-sm leading-relaxed">
                    Need technical support, want to change availability status, or have questions about the system?<br>
                    <strong>Contact the administrator for assistance.</strong>
                </p>
            </div>

            <!-- Developer Signature -->
            <div class="text-center opacity-60 hover:opacity-100 transition-opacity duration-300 cursor-default select-none">
                <div class="font-mono text-[11px] tracking-[0.3em] text-gray-400 dark:text-gray-500">
                    DEVELOPED BY
                </div>
                <div class="font-mono text-[10px] tracking-[0.4em] text-transparent bg-clip-text bg-gradient-to-r from-gray-600 to-gray-400 dark:from-gray-400 dark:to-gray-600">
                    MR.HARKAMAL
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showHelpModal() {
    // Auto-close sidebar on small screens
    if (window.innerWidth < 768) {
        const sidebar = document.getElementById('sidebar');
        const backdrop = document.getElementById('sidebar-backdrop');
        if (sidebar && !sidebar.classList.contains('-translate-x-full')) {
            sidebar.classList.add('-translate-x-full');
        }
        if (backdrop && !backdrop.classList.contains('hidden')) {
            backdrop.classList.add('hidden');
        }
        document.body.style.overflow = '';
    }
    document.getElementById('help-modal').classList.remove('hidden');
}

function closeHelpModal() {
    document.getElementById('help-modal').classList.add('hidden');
}

// Attach handlers after DOM ready
window.addEventListener('DOMContentLoaded', function() {
    const btn = document.getElementById('help-button');
    if (btn) {
        btn.addEventListener('click', showHelpModal);
    }
    
    const modal = document.getElementById('help-modal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeHelpModal();
            }
        });
    }
});
</script>