<?php include 'templates/header.php'; ?>

<style>
    .error-container {
        min-height: 60vh;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
    }
    .error-card {
        background: rgba(255,255,255,0.95);
        border-radius: 18px;
        box-shadow: 0 6px 32px rgba(30,58,138,0.10), 0 1.5px 6px rgba(96,165,250,0.10);
        padding: 2.5rem 2rem 2rem 2rem;
        max-width: 420px;
        text-align: center;
        margin: 2rem auto;
    }
    .error-icon {
        font-size: 3.5rem;
        color: #ef4444;
        margin-bottom: 1rem;
    }
    .error-title {
        font-size: 2rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 0.5rem;
    }
    .error-message {
        font-size: 1.1rem;
        color: #475569;
        margin-bottom: 1.5rem;
    }
    .error-btn {
        display: inline-block;
        background: linear-gradient(90deg, #3b82f6, #818cf8);
        color: #fff;
        font-weight: 600;
        padding: 0.7rem 1.6rem;
        border-radius: 8px;
        text-decoration: none;
        box-shadow: 0 2px 8px rgba(59,130,246,0.10);
        transition: background 0.2s, transform 0.2s;
    }
    .error-btn:hover {
        background: linear-gradient(90deg, #2563eb, #6366f1);
        transform: translateY(-2px) scale(1.03);
    }
    @media (max-width: 600px) {
        .error-card { padding: 1.2rem 0.5rem; }
        .error-title { font-size: 1.3rem; }
        .error-icon { font-size: 2.2rem; }
    }
</style>

<div class="error-container">
    <div class="error-card">
        <div class="error-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div class="error-title">
            <?php
            $error = isset($_SERVER['REDIRECT_STATUS']) ? $_SERVER['REDIRECT_STATUS'] : 'Error';
            if ($error == 404) {
                echo "404 - Page Not Found";
            } elseif ($error == 500) {
                echo "500 - Internal Server Error";
            } else {
                echo "Oops! Something went wrong.";
            }
            ?>
        </div>
        <div class="error-message">
            <?php
            if ($error == 404) {
                echo "Sorry, the page you are looking for doesn't exist or has been moved.";
            } elseif ($error == 500) {
                echo "Sorry, there was a problem with the server. Please try again later.";
            } else {
                echo "An unexpected error occurred. Please try again or contact support if the issue persists.";
            }
            ?>
        </div>
        <a href="/complaint_portal/" class="error-btn">Go to Homepage</a>
    </div>
</div>

<?php include 'templates/footer.php'; ?> 