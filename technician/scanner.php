<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';

// Check if user is logged in as technician
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'technician') {
    redirect('../login.php?error=unauthorized');
}

$tech_id = $_SESSION['user_id'];
$token = isset($_GET['token']) ? trim($_GET['token']) : '';

// Get technician's specialization
$stmt = $mysqli->prepare("SELECT specialization FROM users WHERE id = ?");
$stmt->bind_param('i', $tech_id);
$stmt->execute();
$stmt->bind_result($specialization);
$stmt->fetch();
$stmt->close();

// If token is provided, pre-load complaint details
$preloaded_complaint = null;
if (!empty($token)) {
    $stmt = $mysqli->prepare("
        SELECT c.*, u.full_name, u.phone
        FROM complaints c
        JOIN users u ON u.id = c.user_id
        WHERE c.token = ? AND (c.category = ? OR c.technician_id = ?)
    ");
    $stmt->bind_param('ssi', $token, $specialization, $tech_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $preloaded_complaint = $result->fetch_assoc();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>QR Scanner | AIMT Complaint Portal</title>
    
    <!-- Critical CSS to prevent flash -->
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        -webkit-tap-highlight-color: transparent;
    }

    html, body {
        background: #000 !important;
        color: #fff;
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
        min-height: 100vh;
        overflow: hidden;
    }

    .app-container {
        background: #000;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }

    .header {
        background: rgba(0,0,0,0.8);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }

    .scanner-container {
        background: #000;
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }

    .scanner-frame {
        background: #000;
        opacity: 1;
        visibility: visible;
    }

    #qr-reader {
        background: #000;
    }
    </style>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="preload" href="/complaint_portal/assets/images/aimt-logo.png" as="image">
    
    <script>
    // Prevent flash of unstyled content
    document.documentElement.style.visibility = 'hidden';
    window.addEventListener('load', function() {
        document.documentElement.style.visibility = 'visible';
    });
    
    // Pre-loaded complaint data from PHP
    const preloadedComplaint = <?= $preloaded_complaint ? json_encode($preloaded_complaint) : 'null' ?>;
    </script>
</head>
<body style="background: #000; margin: 0; padding: 0; overflow: hidden;">
    <div class="app-container">
        <header class="header">
            <div class="header-left">
                <img src="/complaint_portal/assets/images/aimt-logo.png" alt="AIMT Logo" class="logo">
                <div class="header-titles">
                    <div class="header-title">QR Scanner</div>
                    <div class="header-subtitle">Technician Portal</div>
                </div>
            </div>
            <div class="header-actions">
                <button class="help-btn" onclick="toggleHelp()">
                    <i class="fas fa-question-circle"></i>
                </button>
                <a href="dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i>
                </a>
            </div>
        </header>

        <div class="scanner-container">
            <div class="scanner-frame">
                <div id="qr-reader"></div>
                <div class="scan-overlay">
                    <div class="scan-frame">
                        <div class="scan-corners corner-tl"></div>
                        <div class="scan-corners corner-tr"></div>
                        <div class="scan-corners corner-bl"></div>
                        <div class="scan-corners corner-br"></div>
                        <div class="scan-line"></div>
                    </div>
                </div>
            </div>

            <div id="camera-error" class="camera-error"></div>
            <div id="scan-status" class="scan-status">Initializing camera...</div>

            <div id="troubleshoot-tips" class="troubleshoot-tips" style="display: none;">
                <strong>Having trouble?</strong>
                <ul>
                    <li>Allow camera access when prompted</li>
                    <li>Use Chrome or Safari for best results</li>
                    <li>Ensure good lighting conditions</li>
                    <li>Hold the QR code steady</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Complaint Modal -->
    <div id="complaint-modal" class="complaint-modal">
        <div class="complaint-modal-overlay"></div>
        <div class="complaint-modal-container">
            <div class="complaint-modal-header">
                <div class="modal-title">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Complaint Details</span>
                </div>
                <button class="modal-close" onclick="closeComplaintModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="complaint-modal-content">
                <div id="complaint-user-info"></div>
                
                <div class="form-group">
                    <label for="tech-remark">Work Performed / Notes <span style="color:#94a3b8;font-size:0.8rem;">(Optional)</span></label>
                    <textarea 
                        id="tech-remark" 
                        placeholder="Add any notes about the work performed..."
                        rows="4"
                    ></textarea>
                </div>
                
                <div class="modal-actions">
                    <button class="btn btn-secondary" onclick="closeComplaintModal()">
                        <i class="fas fa-times"></i>
                        Cancel
                    </button>
                    <button class="btn btn-primary" id="resolve-btn">
                        <i class="fas fa-check"></i>
                        Mark as Resolved
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notifications -->
    <div id="toast-container" class="toast-container"></div>

    <script src="https://unpkg.com/html5-qrcode"></script>
    <script>
    // QR Scanner Controller
    const QRScanner = {
        instance: null,
        currentToken: null,
        isScanning: false,
        
        async init() {
            this.updateStatus('Initializing...');
            
            // Check if we have a pre-loaded complaint
            if (preloadedComplaint) {
                this.currentToken = preloadedComplaint.token;
                this.updateStatus('Complaint loaded from URL. Showing details...', 'success');
                
                // Show the complaint details immediately
                ComplaintView.show(preloadedComplaint);
                return;
            }
            
            // Otherwise, start normal QR scanning
            this.updateStatus('Initializing camera...');
            
            try {
                // Create scanner instance
                this.instance = new Html5Qrcode("qr-reader");
                
                // Start camera immediately
                await this.start();
                
            } catch (error) {
                this.handleError(error);
            }
        },

        handleError(error) {
            console.error('Scanner error:', error);
            this.updateStatus(
                error.message?.includes('NotAllowedError') 
                    ? 'Camera access denied. Please allow camera access and reload.'
                    : 'Failed to initialize camera. Please check permissions.',
                'error'
            );
        },
        
        async start() {
            if (!this.instance || this.isScanning) return;
            
            try {
                await this.instance.start(
                    { facingMode: "environment" },
                    {
                        fps: 10,
                        qrbox: { width: 250, height: 250 },
                        aspectRatio: 1
                    },
                    this.onScanSuccess.bind(this),
                    this.onScanFailure.bind(this)
                );
                
                this.isScanning = true;
                this.updateStatus('Ready to scan');
            } catch (error) {
                this.updateStatus('Failed to start scanner. Please try again.', 'error');
                console.error('Start scanner error:', error);
            }
        },
        
        async stop() {
            if (!this.instance || !this.isScanning) return;
            
            try {
                await this.instance.stop();
                this.isScanning = false;
            } catch (error) {
                console.error('Stop scanner error:', error);
            }
        },
        
        updateStatus(message, type = 'info') {
            const status = document.getElementById('scan-status');
            status.textContent = message;
            status.className = `scan-status ${type}`;
            
            const cameraErrorDiv = document.getElementById('camera-error');
            if(type === 'error') {
                cameraErrorDiv.style.display = 'block';
                cameraErrorDiv.innerHTML = `<b>Camera Error:</b> ${message}<br><br><span style='font-size:0.95em;'>Try these steps:<ul><li>Allow camera access when prompted</li><li>Use Chrome/Safari, not in-app browsers</li><li>Reload the page</li><li>Use HTTPS for best results</li></ul></span>`;
            } else {
                cameraErrorDiv.style.display = 'none';
            }
            
            // Haptic feedback
            if (type === 'error' && navigator.vibrate) {
                navigator.vibrate([100, 50, 100]);
            } else if (type === 'success' && navigator.vibrate) {
                navigator.vibrate(200);
            }
        },
        
        async onScanSuccess(decodedText) {
            const cleanToken = decodedText.trim();
            // Token scanned successfully
            this.currentToken = cleanToken;
            this.updateStatus('QR code scanned! Loading details...', 'success');
            await this.stop();
            
            try {
                // Sending token to backend
                const response = await fetch('get_complaint_details.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `token=${encodeURIComponent(cleanToken)}`
                });
                
                if (!response.ok) throw new Error('Network response was not ok');
                
                const data = await response.json();
                
                if (data.success) {
                    // Check if complaint is already resolved
                    if (data.complaint.status === 'resolved') {
                        this.updateStatus('This complaint is already resolved!', 'error');
                        Toast.show('This complaint has already been resolved. Please scan a different QR code.', 'info');
                        await this.start();
                        return;
                    }
                    
                    ComplaintView.show(data.complaint);
                } else {
                    throw new Error(data.message || 'Failed to load complaint details');
                }
            } catch (error) {
                this.updateStatus(error.message, 'error');
                console.error('Fetch error:', error);
                await this.start();
            }
        },
        
        onScanFailure(error) {
            // Only log technical errors, ignore normal scan failures
            if (error?.message?.includes('NotFoundException')) return;
            console.warn('Scan error:', error);
        }
    };

    // Complaint View Controller
    const ComplaintView = {
        show(complaint) {
            const modal = document.getElementById('complaint-modal');
            
            // Check if complaint is already resolved
            if (complaint.status === 'resolved') {
                QRScanner.updateStatus('This complaint is already resolved!', 'error');
                Toast.show('This complaint has already been resolved.', 'info');
                return;
            }
            
            // Prepare simplified content before showing modal
            document.getElementById('complaint-user-info').innerHTML = `
                <div class="space-y-6">
                    <!-- Status Badge -->
                    <div class="status-section">
                        <span class="status-badge status-${complaint.status.replace('_', '-')}">
                            ${complaint.status.replace('_', ' ').toUpperCase()}
                        </span>
                    </div>
                    
                    <!-- Essential Info Only -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="info-item">
                            <i class="fas fa-user info-icon"></i>
                            <div>
                                <p class="info-label">Name</p>
                                <p class="info-value">${this.escapeHtml(complaint.full_name)}</p>
                            </div>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-door-open info-icon"></i>
                            <div>
                                <p class="info-label">Room No.</p>
                                <p class="info-value">${this.escapeHtml(complaint.room_no)}</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Reset form
            document.getElementById('tech-remark').value = '';
            
            // Show modal with proper display first
            modal.style.display = 'flex';
            
            // Force reflow
            modal.offsetHeight;
            
            // Then add active class for animation
            requestAnimationFrame(() => {
                modal.classList.add('active');
            });
        },
        
        escapeHtml(unsafe) {
            if (!unsafe) return '';
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    };

    // Toast Notification System
    const Toast = {
        container: null,
        
        init() {
            this.container = document.getElementById('toast-container');
            if (!this.container) {
                this.container = document.createElement('div');
                this.container.id = 'toast-container';
                this.container.className = 'toast-container';
                document.body.appendChild(this.container);
            }
        },
        
        show(message, type = 'info', duration = 3000) {
            if (!this.container) this.init();
            
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.textContent = message;
            
            // Add to container
            this.container.appendChild(toast);
            
            // Trigger reflow for animation
            toast.offsetHeight;
            
            // Add show class for animation
            requestAnimationFrame(() => {
                toast.classList.add('show');
            });
            
            // Remove after duration
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => {
                    if (toast.parentNode === this.container) {
                        this.container.removeChild(toast);
                    }
                }, 300);
            }, duration);
        }
    };

    function closeComplaintModal() {
        const modal = document.getElementById('complaint-modal');
        if (modal) {
            modal.classList.remove('active');
        }
        
        // Reset form
        const remarkField = document.getElementById('tech-remark');
        if (remarkField) {
            remarkField.value = '';
        }
        
        // If we have a pre-loaded complaint, redirect back to dashboard
        if (preloadedComplaint) {
            window.location.href = 'dashboard.php';
        } else {
            // Restart scanner for normal QR scanning
            QRScanner.start();
        }
    }

    async function markAsResolved() {
        // Check if we have a current token
        if (!QRScanner.currentToken) {
            Toast.show('No complaint token found. Please scan a QR code first.', 'error');
            return;
        }
        
        const remark = document.getElementById('tech-remark').value.trim();
        
        try {
            // Show loading state
            const originalText = document.getElementById('resolve-btn').innerHTML;
            document.getElementById('resolve-btn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            document.getElementById('resolve-btn').disabled = true;
            
            const formData = new FormData();
            formData.append('token', QRScanner.currentToken);
            formData.append('status', 'resolved');
            formData.append('tech_remark', remark || 'No remarks provided');
            
            const response = await fetch('update_status.php', {
                method: 'POST',
                body: formData
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const responseText = await response.text();
            
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (e) {
                throw new Error('Invalid response from server');
            }
            
            if (data.success) {
                
                // Close modal first
                closeComplaintModal();
                
                // Clear the current token to prevent any further issues
                QRScanner.currentToken = null;
                
                // Show success message with complaint details
                const successMessage = `
                    <div class="success-card">
                        <div class="success-icon-bg">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="success-title">Complaint Resolved!</div>
                        <div class="success-desc">The complaint has been successfully marked as resolved.<br>Thank you for your quick response and dedication!</div>
                        <div class="success-details">
                            <div><span>Room:</span> <b>${data.data.room_no}</b></div>
                            <div><span>User:</span> <b>${data.data.user_name}</b></div>
                            <div><span>Category:</span> <b>${data.data.category}</b></div>
                        </div>
                        <button class="success-btn" id="success-continue-btn">CONTINUE</button>
                    </div>
                `;
                
                // Create and show the success modal
                const successModal = document.createElement('div');
                successModal.className = 'complaint-modal active success-modal-enter';
                successModal.innerHTML = `
                    <div class="complaint-modal-overlay"></div>
                    <div class="complaint-modal-container success-modal-container">
                        ${successMessage}
                    </div>
                `;
                document.body.appendChild(successModal);
                
                // Add click handler for CONTINUE button
                setTimeout(() => {
                    const btn = document.getElementById('success-continue-btn');
                    if (btn) {
                        btn.focus();
                        btn.onclick = () => {
                            successModal.classList.remove('active');
                            setTimeout(() => {
                                if (successModal.parentNode) successModal.parentNode.removeChild(successModal);
                                // If we have a pre-loaded complaint, redirect back to dashboard
                                if (preloadedComplaint) {
                                    window.location.href = 'dashboard.php';
                                } else {
                                    // Restart scanner for normal QR scanning
                                    QRScanner.start();
                                }
                            }, 300);
                        };
                    }
                }, 50);
            } else {
                console.error('API returned error:', data.message);
                throw new Error(data.message || 'Failed to update status');
            }
        } catch (error) {
            console.error('Update error:', error);
            Toast.show(error.message, 'error');
        } finally {
            // Always reset button state
            document.getElementById('resolve-btn').innerHTML = '<i class="fas fa-check"></i> Mark as Resolved';
            document.getElementById('resolve-btn').disabled = false;
        }
    }

    // Close modal when clicking overlay
    document.addEventListener('DOMContentLoaded', () => {
        Toast.init();
        QRScanner.init();
        
        // Close modal on overlay click
        document.querySelector('.complaint-modal-overlay').addEventListener('click', closeComplaintModal);
        
        // Close modal on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeComplaintModal();
            }
        });
        
        // Add event listener for Mark as Resolved button
        const resolveBtn = document.getElementById('resolve-btn');
        if (resolveBtn) {
            resolveBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                markAsResolved();
            });
        }
    });

    // Add this new function
    function toggleHelp() {
        const helpBtn = document.querySelector('.help-btn');
        const tips = document.getElementById('troubleshoot-tips');
        
        helpBtn.classList.toggle('active');
        tips.style.display = tips.style.display === 'none' ? 'block' : 'none';
        
        // Add show class after display is set to block for animation
        if (tips.style.display === 'block') {
            setTimeout(() => tips.classList.add('show'), 10);
        } else {
            tips.classList.remove('show');
        }
    }
    </script>

    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        -webkit-tap-highlight-color: transparent;
    }

    body {
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
        background: #000;
        color: #fff;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .app-container {
        flex: 1;
        display: flex;
        flex-direction: column;
        position: relative;
        height: 100vh;
        background: linear-gradient(180deg, rgba(0,0,0,0.9) 0%, rgba(0,0,0,0.95) 100%);
    }

    .header {
        padding: 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: rgba(0,0,0,0.8);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border-bottom: 1px solid rgba(255,255,255,0.1);
        position: relative;
        z-index: 100;
    }

    .header-left {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .logo {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: #fff;
        padding: 6px;
    }

    .header-titles {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }

    .header-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: #fff;
    }

    .header-subtitle {
        font-size: 0.85rem;
        color: rgba(255,255,255,0.7);
    }

    .header-actions {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .help-btn {
        width: 40px;
        height: 40px;
        border: none;
        border-radius: 10px;
        background: rgba(255,255,255,0.1);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 1.2rem;
    }

    .help-btn:hover {
        background: rgba(255,255,255,0.2);
    }

    .help-btn.active {
        background: rgba(37,99,235,0.3);
        color: #60a5fa;
    }

    .back-btn {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: rgba(255,255,255,0.1);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        transition: all 0.2s ease;
    }

    .back-btn:hover {
        background: rgba(255,255,255,0.2);
    }

    .scanner-container {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 20px;
        position: relative;
        overflow: hidden;
    }

    .scanner-frame {
        width: 100%;
        max-width: 400px;
        aspect-ratio: 1;
        position: relative;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 0 0 9999px rgba(0,0,0,0.8);
        opacity: 1;
        transform: scale(1);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        will-change: transform, opacity;
        visibility: visible;
    }

    #qr-reader {
        width: 100%;
        height: 100%;
        background: #000;
        position: relative;
    }

    .scan-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
    }

    .scan-frame {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 70%;
        height: 70%;
        border: 2px solid rgba(255,255,255,0.5);
        border-radius: 20px;
    }

    .scan-line {
        position: absolute;
        top: 50%;
        left: 0;
        width: 100%;
        height: 2px;
        background: linear-gradient(90deg, 
            rgba(255,255,255,0) 0%,
            rgba(255,255,255,0.8) 50%,
            rgba(255,255,255,0) 100%
        );
        animation: scan 2s cubic-bezier(0.4, 0, 0.2, 1) infinite;
    }

    @keyframes scan {
        0% { transform: translateY(-100px); opacity: 0; }
        50% { opacity: 1; }
        100% { transform: translateY(100px); opacity: 0; }
    }

    @keyframes pulse {
        0% { transform: scale(1); box-shadow: 0 8px 32px rgba(74,222,128,0.3); }
        50% { transform: scale(1.05); box-shadow: 0 12px 40px rgba(74,222,128,0.4); }
        100% { transform: scale(1); box-shadow: 0 8px 32px rgba(74,222,128,0.3); }
    }

    .scan-corners {
        position: absolute;
        width: 20px;
        height: 20px;
        border: 2px solid #fff;
    }

    .corner-tl { top: 0; left: 0; border-right: 0; border-bottom: 0; }
    .corner-tr { top: 0; right: 0; border-left: 0; border-bottom: 0; }
    .corner-bl { bottom: 0; left: 0; border-right: 0; border-top: 0; }
    .corner-br { bottom: 0; right: 0; border-left: 0; border-top: 0; }

    .scan-status {
        margin-top: 24px;
        padding: 12px 24px;
        background: rgba(255,255,255,0.1);
        border-radius: 12px;
        font-size: 0.9rem;
        color: rgba(255,255,255,0.8);
        text-align: center;
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        max-width: 400px;
        width: 100%;
    }

    .scan-status.success {
        background: rgba(34,197,94,0.2);
        color: #4ade80;
    }

    .scan-status.error {
        background: rgba(239,68,68,0.2);
        color: #f87171;
    }

    .camera-error {
        background: rgba(239,68,68,0.1);
        color: #f87171;
        border-radius: 12px;
        padding: 16px;
        margin: 16px auto;
        font-size: 0.9rem;
        text-align: left;
        max-width: 400px;
        width: 100%;
        display: none;
    }

    .troubleshoot-tips {
        margin-top: 24px;
        background: rgba(255,255,255,0.05);
        border-radius: 12px;
        padding: 16px;
        font-size: 0.85rem;
        color: rgba(255,255,255,0.7);
        max-width: 400px;
        width: 100%;
        transform: translateY(20px);
        opacity: 0;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .troubleshoot-tips.show {
        transform: translateY(0);
        opacity: 1;
    }

    @media (max-width: 480px) {
        .header {
            padding: 12px;
        }

        .header-title {
            font-size: 1rem;
        }

        .header-subtitle {
            font-size: 0.8rem;
        }

        .scanner-container {
            padding: 12px;
        }

        .scan-status {
            font-size: 0.85rem;
            padding: 10px 20px;
        }
    }

    /* Complaint Modal Styles */
    .complaint-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        z-index: 1000;
        display: none;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        padding: 16px;
    }

    .complaint-modal.active {
        display: flex;
        opacity: 1;
        visibility: visible;
    }

    .complaint-modal-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
    }

    .complaint-modal-container {
        position: relative;
        background: #fff;
        border-radius: 20px;
        width: 100%;
        max-width: 500px;
        max-height: 90vh;
        overflow: hidden;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        transform: scale(0.95);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .complaint-modal.active .complaint-modal-container {
        transform: scale(1);
    }

    .complaint-modal-header {
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 50%, #1e40af 100%);
        color: #fff;
        padding: 24px 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: relative;
        overflow: hidden;
    }

    .complaint-modal-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.1)"/><circle cx="10" cy="60" r="0.5" fill="rgba(255,255,255,0.1)"/><circle cx="90" cy="40" r="0.5" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
        opacity: 0.3;
    }

    .modal-title {
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 1.2rem;
        font-weight: 700;
        position: relative;
        z-index: 1;
    }

    .modal-title i {
        font-size: 1.3rem;
        color: rgba(255,255,255,0.9);
    }

    .modal-close {
        width: 40px;
        height: 40px;
        border: none;
        background: rgba(255,255,255,0.15);
        color: #fff;
        border-radius: 12px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        transition: all 0.2s ease;
        position: relative;
        z-index: 1;
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
    }

    .modal-close:hover {
        background: rgba(255,255,255,0.25);
        transform: scale(1.05);
    }

    .complaint-modal-content {
        padding: 24px;
        max-height: calc(90vh - 76px);
        overflow-y: auto;
        color: #334155;
    }

    .info-item {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        background: #f8fafc;
        padding: 16px;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        transition: all 0.2s ease;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }

    .info-item:hover {
        background: #f1f5f9;
        border-color: #cbd5e1;
        box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        transform: translateY(-1px);
    }

    .info-icon {
        font-size: 1.1rem;
        color: #64748b;
        margin-top: 2px;
        width: 20px;
        text-align: center;
        background: #e2e8f0;
        padding: 8px;
        border-radius: 8px;
        transition: all 0.2s ease;
    }

    .info-item:hover .info-icon {
        background: #cbd5e1;
        color: #475569;
    }

    .info-label {
        font-size: 0.8rem;
        color: #64748b;
        margin-bottom: 4px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 600;
    }

    .info-value {
        font-size: 1rem;
        color: #1e293b;
        font-weight: 600;
        line-height: 1.4;
    }
    
    .info-value.link {
        color: #2563eb;
        text-decoration: none;
        transition: color 0.2s;
    }
    .info-value.link:hover {
        color: #1d4ed8;
    }

    .info-group {
        margin-top: 24px;
    }

    .info-group-title {
        font-size: 0.9rem;
        font-weight: 600;
        color: #475569;
        margin-bottom: 12px;
        padding-bottom: 6px;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .description-box {
        background: #f8fafc;
        border-radius: 8px;
        padding: 12px;
        font-size: 0.95rem;
        line-height: 1.6;
        color: #334155;
        white-space: pre-wrap;
    }

    .form-group label {
        display: block;
        font-size: 0.9rem;
        font-weight: 600;
        color: #475569;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .form-group label::before {
        content: "📝";
        font-size: 1rem;
    }

    .form-group textarea {
        width: 100%;
        padding: 14px;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        font-size: 0.95rem;
        line-height: 1.5;
        resize: vertical;
        min-height: 100px;
        font-family: inherit;
        background: #f8fafc;
        transition: all 0.2s ease;
    }

    .form-group textarea:focus {
        outline: none;
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
        background: #fff;
    }

    .form-group textarea::placeholder {
        color: #94a3b8;
        font-style: italic;
    }

    .modal-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin-top: 24px;
        padding-top: 20px;
        border-top: 1px solid #e2e8f0;
    }

    .btn {
        padding: 14px 20px;
        border: none;
        border-radius: 12px;
        font-size: 0.95rem;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: all 0.2s ease;
        position: relative;
        z-index: 10;
        user-select: none;
        -webkit-user-select: none;
        -webkit-tap-highlight-color: transparent;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .btn:active {
        transform: translateY(1px);
        box-shadow: 0 1px 2px rgba(0,0,0,0.1);
    }

    .btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none;
    }

    .btn-primary {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        color: #fff;
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #1d4ed8, #1e40af);
        box-shadow: 0 4px 8px rgba(37,99,235,0.3);
    }

    .btn-primary:active {
        background: linear-gradient(135deg, #1e40af, #1e3a8a);
    }

    .btn-secondary {
        background: #f1f5f9;
        color: #475569;
        border: 1px solid #e2e8f0;
    }

    .btn-secondary:hover {
        background: #e2e8f0;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    /* Status Badges */
    .status-badge {
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-badge.status-pending {
        background: #fef3c7;
        color: #92400e;
    }

    .status-badge.status-in-progress {
        background: #dbeafe;
        color: #1e40af;
    }

    .status-badge.status-resolved {
        background: #dcfce7;
        color: #166534;
    }

    .status-badge.status-rejected {
        background: #fee2e2;
        color: #991b1b;
    }

    /* Status Section */
    .status-section {
        display: flex;
        justify-content: center;
        margin-bottom: 8px;
    }

    /* Section Titles */
    .section-title {
        font-size: 0.9rem;
        font-weight: 600;
        color: #475569;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
        padding-bottom: 6px;
        border-bottom: 1px solid #e2e8f0;
    }

    .section-title i {
        color: #64748b;
        font-size: 0.85rem;
    }

    /* Description Section */
    .description-section {
        margin-top: 20px;
    }

    .description-box {
        background: #f8fafc;
        border-radius: 10px;
        padding: 14px;
        font-size: 0.95rem;
        line-height: 1.6;
        color: #334155;
        white-space: pre-wrap;
        border: 1px solid #e2e8f0;
        border-left: 3px solid #94a3b8;
    }

    /* Notes Section */
    .notes-section {
        margin-top: 20px;
    }

    .notes-box {
        background: #fef3c7;
        border-radius: 10px;
        padding: 14px;
        font-size: 0.95rem;
        line-height: 1.6;
        color: #92400e;
        white-space: pre-wrap;
        border: 1px solid #fde68a;
        border-left: 3px solid #f59e0b;
    }

    /* Grid utilities */
    .grid { display: grid; }
    .grid-cols-1 { grid-template-columns: repeat(1, minmax(0, 1fr)); }
    .sm\\:grid-cols-2 {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .gap-4 { gap: 1rem; }
    .col-span-full { grid-column: 1 / -1; }
    .space-y-6 > :not([hidden]) ~ :not([hidden]) {
        --tw-space-y-reverse: 0;
        margin-top: calc(1.5rem * calc(1 - var(--tw-space-y-reverse)));
        margin-bottom: calc(1.5rem * var(--tw-space-y-reverse));
    }

    /* Description styling */
    .description {
        font-size: 14px;
        line-height: 1.6;
        color: #475569;
        white-space: pre-wrap;
        background: #f8fafc;
        padding: 12px;
        border-radius: 8px;
        border-left: 3px solid #94a3b8;
        margin-top: 8px;
    }

    .capitalize {
        text-transform: capitalize;
    }

    /* Success Modal Styles */
    .success-card {
        background: #fff;
        border-radius: 18px;
        box-shadow: 0 8px 32px rgba(16, 185, 129, 0.08), 0 1.5px 8px rgba(0,0,0,0.04);
        padding: 36px 20px 24px 20px;
        max-width: 340px;
        width: 90vw;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        align-items: center;
        animation: successModalEnter 0.4s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        z-index: 10001;
    }

    .success-icon-bg {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        background: #e6f9f0;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 18px;
        box-shadow: 0 2px 8px rgba(16, 185, 129, 0.10);
    }

    .success-icon-bg i {
        color: #22c55e;
        font-size: 2.2rem;
    }

    .success-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: #1f2937;
        margin-bottom: 6px;
    }

    .success-desc {
        font-size: 1rem;
        color: #64748b;
        margin-bottom: 18px;
        text-align: center;
    }

    .success-details {
        background: #f8fafc;
        border-radius: 10px;
        padding: 12px 14px;
        margin-bottom: 18px;
        border: 1px solid #e2e8f0;
        font-size: 0.97rem;
        color: #334155;
        width: 100%;
        box-sizing: border-box;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .success-details span {
        color: #64748b;
        font-weight: 500;
        margin-right: 6px;
    }

    .success-btn {
        background: #22c55e;
        color: #fff;
        border: none;
        border-radius: 999px;
        padding: 12px 36px;
        font-size: 1rem;
        font-weight: 600;
        box-shadow: 0 2px 8px rgba(16, 185, 129, 0.10);
        cursor: pointer;
        margin-top: 8px;
        transition: background 0.18s, box-shadow 0.18s;
        width: 100%;
        max-width: 220px;
    }

    .success-btn:hover, .success-btn:focus {
        background: #16a34a;
        box-shadow: 0 4px 16px rgba(16, 185, 129, 0.16);
        outline: none;
    }

    .success-modal-container {
        background: transparent;
        box-shadow: none;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 100vh;
        width: 100vw;
        position: fixed;
        top: 0; left: 0;
        z-index: 10000;
        overflow-y: auto;
    }

    .success-modal-enter .complaint-modal-overlay {
        background: rgba(0,0,0,0.18);
        backdrop-filter: blur(2.5px);
        -webkit-backdrop-filter: blur(2.5px);
    }

    @keyframes successModalEnter {
        0% {
            opacity: 0;
            transform: scale(0.9) translateY(20px);
        }
        100% {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }

    @media (max-width: 480px) {
        .success-card {
            padding: 24px 6vw 18px 6vw;
            max-width: 98vw;
        }
        .success-title {
            font-size: 1.08rem;
        }
        .success-desc {
            font-size: 0.97rem;
        }
        .success-btn {
            font-size: 0.97rem;
            padding: 12px 0;
        }
    }

    /* Toast Notifications */
    .toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 10000;
        display: flex;
        flex-direction: column;
        gap: 12px;
        max-width: 350px;
    }

    .toast {
        background: #fff;
        color: #1e293b;
        padding: 16px 20px;
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        font-size: 0.95rem;
        font-weight: 500;
        line-height: 1.4;
        border-left: 4px solid #64748b;
        transform: translateX(100%);
        opacity: 0;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
    }

    .toast.show {
        transform: translateX(0);
        opacity: 1;
    }

    .toast.success {
        border-left-color: #22c55e;
        background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
    }

    .toast.error {
        border-left-color: #ef4444;
        background: linear-gradient(135deg, #fef2f2 0%, #fef2f2 100%);
    }

    .toast.info {
        border-left-color: #3b82f6;
        background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
    }

    .toast.warning {
        border-left-color: #f59e0b;
        background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
    }

    @media (max-width: 480px) {
        .toast-container {
            top: 10px;
            right: 10px;
            left: 10px;
            max-width: none;
        }
        
        .toast {
            padding: 14px 16px;
            font-size: 0.9rem;
        }
    }
    </style>
</body>
</html> 