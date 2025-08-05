// Assumes qrcode.min.js is loaded
function generateQRCode(token, elementId) {
    var qrDiv = document.getElementById(elementId);
    qrDiv.innerHTML = '';
    new QRCode(qrDiv, {
        text: token,
        width: 180,
        height: 180
    });
}
// Usage: generateQRCode(document.getElementById('token').dataset.token, 'qr-code'); 