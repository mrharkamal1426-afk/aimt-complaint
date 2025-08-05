<?php


require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
    redirect('../login.php?error=unauthorized');
}

$categories = ['mess','carpenter','wifi','housekeeping','plumber','electrician','laundry','ac'];
$user_id = $_SESSION['user_id'];
$errors = [];
$success = false;
$token = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $room_no = trim($_POST['room_no'] ?? '');
    $category = $_POST['category'] ?? '';
    $description = trim($_POST['description'] ?? '');
    if (!$room_no || !$category || !$description) {
        $errors[] = 'All fields are required.';
    }
    if (!in_array($category, $categories)) {
        $errors[] = 'Invalid category.';
    }
    if (!$errors) {
        $bytes = random_bytes(16);
        $token = bin2hex($bytes);
        $stmt = $mysqli->prepare("INSERT INTO complaints (user_id, token, room_no, category, description) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('issss', $user_id, $token, $room_no, $category, $description);
        if ($stmt->execute()) {
            $complaint_id = $stmt->insert_id;
            $stmt->close();
            
            // Auto-assign the complaint to a technician based on workload
            $assigned_technician = auto_assign_complaint($complaint_id);
            
            ?>
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Complaint Submitted Successfully</title>
                <script src="https://cdn.tailwindcss.com"></script>
                <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
                <link href="https://unpkg.com/lucide-static@0.298.0/font/lucide.css" rel="stylesheet">
                <style>
                    * { font-family: 'Inter', sans-serif; }
                    .success-container {
                        min-height: 100vh;
                        background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        padding: 1rem;
                    }
                    .success-card {
                        background: white;
                        border-radius: 1.5rem;
                        box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
                        padding: 2.5rem;
                        max-width: 500px;
                        width: 100%;
                        text-align: center;
                    }
                    .success-icon {
                        width: 80px;
                        height: 80px;
                        background: #10b981;
                        border-radius: 50%;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        margin: 0 auto 1.5rem;
                        animation: scaleIn 0.5s ease-out;
                    }
                    .token-box {
                        background: #f0fdf4;
                        border: 2px dashed #10b981;
                        border-radius: 0.75rem;
                        padding: 1rem;
                        margin: 1.5rem auto;
                        font-family: monospace;
                        font-size: 1.1rem;
                        color: #047857;
                        user-select: all;
                        max-width: 90%;
                        overflow-x: auto;
                        white-space: nowrap;
                        text-align: center;
                    }
                    .qr-container {
                        background: white;
                        padding: 1rem;
                        border-radius: 0.75rem;
                        display: inline-block;
                        margin: 1.5rem auto;
                        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
                        max-width: 90%;
                    }
                    .back-btn {
                        background: #10b981;
                        color: white;
                        padding: 0.75rem 1.5rem;
                        border-radius: 0.5rem;
                        font-weight: 500;
                        transition: all 0.2s;
                        display: inline-flex;
                        align-items: center;
                        text-decoration: none;
                        margin-top: 1rem;
                    }
                    .back-btn:hover {
                        background: #059669;
                        transform: translateY(-1px);
                    }
                    @keyframes scaleIn {
                        from { transform: scale(0); opacity: 0; }
                        to { transform: scale(1); opacity: 1; }
                    }
                    @media (max-width: 640px) {
                        .success-card {
                            padding: 1.5rem;
                            margin: 1rem;
                        }
                        .success-icon {
                            width: 60px;
                            height: 60px;
                        }
                        .token-box {
                            font-size: 0.9rem;
                            padding: 0.75rem;
                            margin: 1rem auto;
                        }
                        .qr-container {
                            padding: 0.75rem;
                            margin: 1rem auto;
                        }
                        .success-container {
                            padding: 0.5rem;
                        }
                    }
                </style>
            </head>
            <body>
                <div class="success-container">
                    <div class="success-card">
                        <div class="success-icon">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                        </div>
                        
                        <h1 class="text-2xl font-bold text-gray-900 mb-2">Complaint Submitted!</h1>
                        <p class="text-gray-600 mb-6">Your complaint has been successfully registered.</p>
                        
                        <div class="token-box" id="token" data-token="<?= htmlspecialchars($token) ?>">
                            <?= htmlspecialchars($token) ?>
                        </div>
                        
                        <p class="text-sm text-gray-500 mb-4">Show this qr code to the technician to get your complaint resolved</p>
                        
                        <div class="qr-container">
                            <div id="qr-code"></div>
                        </div>
                        
                        <a href="dashboard.php" class="back-btn">
                            <i class="lucide-arrow-left mr-2 h-4 w-4"></i>
                            Back to Dashboard
                        </a>
                    </div>
                </div>

                <script src="../assets/js/qr-gen/qrcode.min.js"></script>
                <script>
                    function generateQRCode() {
                        const token = document.getElementById('token').dataset.token;
                        const qrContainer = document.getElementById('qr-code');
                        const size = window.innerWidth < 640 ? 150 : 180;
                        new QRCode(qrContainer, {
                            text: token,
                            width: size,
                            height: size,
                            colorDark: "#10b981",
                            colorLight: "#ffffff",
                            correctLevel: QRCode.CorrectLevel.H
                        });
                    }
                    window.addEventListener('DOMContentLoaded', generateQRCode);
                    window.addEventListener('resize', function() {
                        document.getElementById('qr-code').innerHTML = '';
                        generateQRCode();
                    });
                </script>
            </body>
            </html>
            <?php
            exit;
        } else {
            $errors[] = 'Something went wrong. Please try again.';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Complaint - Superadmin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/lucide-static@0.298.0/font/lucide.css" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        .form-container {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
            padding: 2rem;
            max-width: 800px;
            margin: 2rem auto;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            margin-top: 0.5rem;
            transition: all 0.2s;
        }

        .form-input:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }

        .form-label {
            display: block;
            font-weight: 500;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .error-message {
            background: #fee2e2;
            color: #dc2626;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }

        .submit-btn {
            background: #10b981;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .submit-btn:hover {
            background: #059669;
        }

        .back-btn {
            color: #64748b;
            transition: all 0.2s;
        }

        .back-btn:hover {
            color: #1e293b;
        }

        @media (max-width: 768px) {
            .form-container {
                margin: 1rem;
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <div class="form-container">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Submit New Complaint</h1>
                <p class="text-gray-600 mt-1">Create a new complaint ticket</p>
            </div>

            <?php if ($errors): ?>
                <?php foreach ($errors as $error): ?>
                    <div class="error-message"><?= htmlspecialchars($error) ?></div>
                <?php endforeach; ?>
            <?php endif; ?>

            <form method="post" autocomplete="off" class="space-y-6">
                <div>
                    <label class="form-label">Room Number</label>
                    <input type="text" name="room_no" value="<?= htmlspecialchars($_POST['room_no'] ?? '') ?>" 
                           class="form-input" placeholder="Enter room number" required>
                </div>

                <div>
                    <label class="form-label">Category</label>
                    <select name="category" class="form-input" required>
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat ?>" <?= (($_POST['category'] ?? '')==$cat)?'selected':'' ?>><?= ucfirst($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-input min-h-[120px]" 
                              placeholder="Describe your complaint in detail..." required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>

                <div class="flex items-center justify-between pt-4">
                    <a href="dashboard.php" class="back-btn inline-flex items-center">
                        <i class="lucide-arrow-left mr-2 h-4 w-4"></i>
                        Back to Dashboard
                    </a>
                    <button type="submit" class="submit-btn inline-flex items-center">
                        <i class="lucide-send mr-2 h-4 w-4"></i>
                        Submit Complaint
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html> 