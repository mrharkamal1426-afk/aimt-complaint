// Assumes html5-qrcode.min.js is loaded
function startQRScanner(elementId, redirectBase) {
    var qrScanner = new Html5Qrcode(elementId);
    qrScanner.start(
        { facingMode: "environment" },
        {
            fps: 10,
            qrbox: 250
        },
        (decodedText, decodedResult) => {
            window.location.href = redirectBase + '?token=' + encodeURIComponent(decodedText);
            qrScanner.stop();
        },
        (errorMessage) => {
            // Optionally show scan errors
        }
    ).catch(err => {
        alert('Unable to start QR scanner: ' + err);
    });
}
// Usage: startQRScanner('qr-reader', 'update_status.php'); 