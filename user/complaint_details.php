<?php
require_once __DIR__.'/../includes/config.php';
require_once __DIR__.'/../includes/functions.php';
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['student','faculty','nonteaching','outsourced_vendor','technician'])) {
    redirect('../login.php?error=unauthorized');
}
$user_id = $_SESSION['user_id'];
$complaint = null;
$error = '';
$timeline = [];

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    $stmt = $mysqli->prepare("SELECT c.*, t.full_name AS tech_name FROM complaints c LEFT JOIN users t ON t.id = c.technician_id WHERE c.token = ? AND c.user_id = ?");
    $stmt->bind_param('si', $token, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $complaint = $row;
        // Add initial status to timeline
        $timeline[] = [
            'created_at' => $complaint['created_at'],
            'status' => $complaint['status'],
            'note' => 'Complaint created'
        ];
        // Try to fetch complaint timeline
        try {
            $history_stmt = $mysqli->prepare("SELECT * FROM complaint_history WHERE complaint_id = ? ORDER BY created_at DESC");
            if ($history_stmt) {
                $history_stmt->bind_param('i', $complaint['id']);
                $history_stmt->execute();
                $timeline_result = $history_stmt->get_result();
                while ($timeline_row = $timeline_result->fetch_assoc()) {
                    $timeline[] = $timeline_row;
                }
                $history_stmt->close();
            }
        } catch (Exception $e) {
            // Log error silently for production
        }
    } else {
        $error = 'No complaint found for this token.';
    }
    $stmt->close();
} else {
    $error = 'No token provided.';
}

include __DIR__.'/../templates/header.php';
?>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AIMT - Complaint Details</title>

<style>
@media (max-width: 640px) {
  .mobile-card { border-radius: 0; box-shadow: none; border: none; }
  .mobile-section { padding: 1.25rem 0.5rem; }
  .mobile-title { font-size: 1.1rem; }
  .mobile-label { font-size: 0.95rem; }
  .mobile-value { font-size: 1.05rem; }
}
</style>

<div class="min-h-screen bg-white">
  <div class="max-w-xl mx-auto px-2 py-6">
    <a href="track_complaint.php" class="text-blue-600 text-sm mb-4 inline-block">&larr; Back</a>

    <?php if ($error): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 rounded p-3 mb-4 text-sm">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <?php if ($complaint): ?>
      <div class="bg-gray-50 rounded-lg shadow-sm p-4 mb-4 mobile-card">
        <div class="mb-2 mobile-title font-semibold text-gray-800">Complaint Details</div>
        <div class="mb-1 mobile-label text-gray-500">Token</div>
        <div class="mb-2 mobile-value font-mono text-gray-700 break-all"><?= htmlspecialchars($complaint['token']) ?></div>
        <div class="mb-1 mobile-label text-gray-500">Status</div>
        <span class="inline-block px-2 py-1 rounded text-xs font-semibold mb-2"
          style="background:<?php
            switch($complaint['status']) {
              case 'pending': echo '#FEF3C7'; break;
              case 'in_progress': echo '#DBEAFE'; break;
              case 'resolved': echo '#D1FAE5'; break;
              case 'rejected': echo '#FECACA'; break;
              default: echo '#F3F4F6';
            }
          ?>; color:<?php
            switch($complaint['status']) {
              case 'pending': echo '#92400E'; break;
              case 'in_progress': echo '#1E40AF'; break;
              case 'resolved': echo '#065F46'; break;
              case 'rejected': echo '#991B1B'; break;
              default: echo '#374151';
            }
          ?>;">
          <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $complaint['status']))) ?>
        </span>
        <div class="mb-1 mobile-label text-gray-500">Category</div>
        <div class="mb-2 mobile-value text-gray-700"><?= htmlspecialchars(ucfirst($complaint['category'])) ?></div>
        <div class="mb-1 mobile-label text-gray-500">Assigned To</div>
        <div class="mb-2 mobile-value text-gray-700"><?= htmlspecialchars($complaint['tech_name'] ?? 'Not assigned') ?></div>
        <div class="mb-1 mobile-label text-gray-500">Last Updated</div>
        <div class="mb-2 mobile-value text-gray-700"><?= htmlspecialchars(date('M j, Y g:i A', strtotime($complaint['updated_at']))) ?></div>
      </div>

      <div class="bg-gray-50 rounded-lg shadow-sm p-4 mb-4 mobile-card">
        <div class="mobile-title font-semibold text-gray-800 mb-2">Description</div>
        <div class="mobile-value text-gray-700 whitespace-pre-line">
          <?= nl2br(htmlspecialchars($complaint['description'])) ?>
        </div>
      </div>

      <?php if (!empty($complaint['tech_note'])): ?>
        <div class="bg-gray-50 rounded-lg shadow-sm p-4 mb-4 mobile-card">
          <div class="mobile-title font-semibold text-gray-800 mb-2">Technician's Note</div>
          <div class="mobile-value text-gray-700 whitespace-pre-line">
            <?= nl2br(htmlspecialchars($complaint['tech_note'])) ?>
          </div>
        </div>
      <?php endif; ?>

      <div class="bg-gray-50 rounded-lg shadow-sm p-4 mb-4 mobile-card">
        <div class="mobile-title font-semibold text-gray-800 mb-2">Status Timeline</div>
        <?php if (!empty($timeline)): ?>
          <ul class="space-y-3">
            <?php foreach ($timeline as $event): ?>
              <li class="border-l-2 border-blue-200 pl-3">
                <div class="mobile-label text-gray-500 mb-1">Status: <span class="font-semibold text-gray-700"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $event['status']))) ?></span></div>
                <?php if (!empty($event['note'])): ?>
                  <div class="mobile-value text-gray-700 mb-1">Note: <?= nl2br(htmlspecialchars($event['note'])) ?></div>
                <?php endif; ?>
                <div class="text-xs text-gray-400 mb-1"><?= htmlspecialchars(date('M j, Y g:i A', strtotime($event['created_at']))) ?></div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <div class="text-gray-500 text-sm">No timeline entries found.</div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__.'/../templates/footer.php'; ?>