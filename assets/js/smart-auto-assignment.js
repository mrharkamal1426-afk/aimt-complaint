/**
 * Smart Auto-Assignment JavaScript Engine
 * Automatically runs every 5 minutes to validate and assign complaints
 * No cron jobs required - completely self-contained
 */

class SmartAutoAssignmentRunner {
    constructor() {
        this.isRunning = false;
        this.lastRun = null;
        this.runCount = 0;
        this.interval = null;
        this.autoRunInterval = 5 * 60 * 1000; // 5 minutes in milliseconds
        this.init();
    }
    
    init() {
        // Start the auto-runner
        this.startAutoRunner();
        
        // Add visual indicators to the page
        this.addStatusIndicator();
        
        // Run immediately on page load
        this.runAutoAssignment();
    }
    
    startAutoRunner() {
        // Run every 5 minutes
        this.interval = setInterval(() => {
            this.runAutoAssignment();
        }, this.autoRunInterval);
    }
    
    async runAutoAssignment() {
        if (this.isRunning) {
            return;
        }
        
        this.isRunning = true;
        this.updateStatusIndicator('running');
        
        try {
            
            const response = await fetch('../ajax/run_smart_auto_assignment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=run_smart_auto_assignment'
            });
            
            const result = await response.json();
            
            if (result.status === 'success') {
                this.handleSuccess(result);
            } else {
                this.handleError(result.message);
            }
            
        } catch (error) {
            this.handleError('Network error: ' + error.message);
        } finally {
            this.isRunning = false;
            this.lastRun = new Date();
            this.runCount++;
            this.updateStatusIndicator('idle');
        }
    }
    
    handleSuccess(result) {
        // Update status display
        this.updateStatusDisplay(result);
        
        // Show notification if there were assignments
        const totalAssigned = (result.assignments?.assigned || 0) + (result.hostel_assignments?.assigned || 0);
        const totalReassigned = (result.validation?.reassigned_complaints || 0) + (result.validation?.reassigned_hostel_issues || 0);
        
        if (totalAssigned > 0 || totalReassigned > 0) {
            this.showNotification(`Auto-assignment completed: ${totalAssigned} new assignments, ${totalReassigned} reassignments`);
        }
        
        // Log results
        this.logResults(result);
    }
    
    handleError(message) {
        this.updateStatusIndicator('error');
        this.showNotification('Auto-assignment failed: ' + message, 'error');
    }
    
    updateStatusDisplay(result) {
        // Update any status elements on the page
        const statusElements = document.querySelectorAll('.auto-assignment-status');
        statusElements.forEach(element => {
            if (result.health_report) {
                element.innerHTML = `
                    <div class="text-sm">
                        <div>Health Score: ${result.health_report.system_health_score}/100</div>
                        <div>Online Techs: ${result.health_report.online_technicians}/${result.health_report.total_technicians}</div>
                        <div>Unassigned: ${result.health_report.unassigned_complaints} complaints, ${result.health_report.unassigned_hostel_issues} hostel issues</div>
                    </div>
                `;
            }
        });
        
        // Update last run time
        const lastRunElements = document.querySelectorAll('.last-auto-assignment-run');
        lastRunElements.forEach(element => {
            element.textContent = new Date().toLocaleString();
        });
    }
    
    addStatusIndicator() {
        // Create status indicator if it doesn't exist
        if (!document.getElementById('auto-assignment-status')) {
            const statusDiv = document.createElement('div');
            statusDiv.id = 'auto-assignment-status';
            statusDiv.className = 'fixed bottom-4 right-4 bg-white rounded-lg shadow-lg p-4 border border-gray-200 z-50';
            statusDiv.innerHTML = `
                <div class="flex items-center space-x-3">
                    <div class="w-3 h-3 rounded-full bg-gray-400" id="status-indicator"></div>
                    <div>
                        <div class="text-sm font-medium text-gray-900">Auto-Assignment</div>
                        <div class="text-xs text-gray-500" id="status-text">Initializing...</div>
                    </div>
                    <button onclick="smartAutoRunner.runAutoAssignment()" class="text-blue-600 hover:text-blue-800 text-sm">
                        Run Now
                    </button>
                </div>
            `;
            document.body.appendChild(statusDiv);
        }
    }
    
    updateStatusIndicator(status) {
        const indicator = document.getElementById('status-indicator');
        const statusText = document.getElementById('status-text');
        
        if (indicator && statusText) {
            switch (status) {
                case 'running':
                    indicator.className = 'w-3 h-3 rounded-full bg-yellow-400 animate-pulse';
                    statusText.textContent = 'Running...';
                    break;
                case 'idle':
                    indicator.className = 'w-3 h-3 rounded-full bg-green-400';
                    statusText.textContent = `Last run: ${this.lastRun ? this.lastRun.toLocaleTimeString() : 'Never'}`;
                    break;
                case 'error':
                    indicator.className = 'w-3 h-3 rounded-full bg-red-400';
                    statusText.textContent = 'Error occurred';
                    break;
                default:
                    indicator.className = 'w-3 h-3 rounded-full bg-gray-400';
                    statusText.textContent = 'Unknown status';
            }
        }
    }
    
    showNotification(message, type = 'success') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 max-w-sm ${
            type === 'error' ? 'bg-red-100 border border-red-400 text-red-700' : 'bg-green-100 border border-green-400 text-green-700'
        }`;
        notification.innerHTML = `
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${
                            type === 'error' ? 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z' : 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'
                        }"></path>
                    </svg>
                    <span class="text-sm font-medium">${message}</span>
                </div>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-gray-400 hover:text-gray-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
    }
    
    logResults(result) {
        // Store minimal log for system monitoring
        const logEntry = {
            timestamp: new Date().toISOString(),
            status: result.status,
            runCount: this.runCount
        };
        
        const logs = JSON.parse(localStorage.getItem('autoAssignmentLogs') || '[]');
        logs.push(logEntry);
        
        // Keep only last 10 entries for production
        if (logs.length > 10) {
            logs.splice(0, logs.length - 10);
        }
        
        localStorage.setItem('autoAssignmentLogs', JSON.stringify(logs));
    }
    
    // Public method to manually trigger auto-assignment
    runNow() {
        this.runAutoAssignment();
    }
    
    // Public method to get status
    getStatus() {
        return {
            isRunning: this.isRunning,
            lastRun: this.lastRun,
            runCount: this.runCount,
            nextRun: this.lastRun ? new Date(this.lastRun.getTime() + this.autoRunInterval) : null
        };
    }
    
    // Public method to stop auto-runner
    stop() {
        if (this.interval) {
            clearInterval(this.interval);
            this.interval = null;
            console.log('Auto-assignment runner stopped');
        }
    }
}

// Initialize the auto-assignment runner when the page loads
let smartAutoRunner;

document.addEventListener('DOMContentLoaded', function() {
    smartAutoRunner = new SmartAutoAssignmentRunner();
    
    // Make it globally accessible
    window.smartAutoRunner = smartAutoRunner;
    
    
});

// Handle page visibility changes to pause/resume when tab is not active
document.addEventListener('visibilitychange', function() {
    if (smartAutoRunner) {
        if (document.hidden) {
            console.log('Page hidden - auto-assignment continues in background');
        } else {
            console.log('Page visible - auto-assignment active');
        }
    }
});

// Handle page unload to clean up
window.addEventListener('beforeunload', function() {
    if (smartAutoRunner) {
        smartAutoRunner.stop();
    }
}); 