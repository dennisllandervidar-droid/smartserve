<?php if (!empty($student_blocked_rows)): ?>
<section class="ss-block-notices" aria-labelledby="blocked-appointments-heading">
    <h5 id="blocked-appointments-heading"><i class="ti-alert" aria-hidden="true"></i> Appointment updates requiring your action</h5>
    <?php foreach ($student_blocked_rows as $blockedNotice): ?>
    <article class="ss-block-notice">
        <div>
            <strong><?php echo htmlspecialchars($blockedNotice['office_name'], ENT_QUOTES, 'UTF-8'); ?> blocked your appointment</strong>
            <p><?php echo date('M d, Y', strtotime($blockedNotice['original_date'])); ?> at <?php echo htmlspecialchars($blockedNotice['original_time'], ENT_QUOTES, 'UTF-8'); ?> · #<?php echo htmlspecialchars($blockedNotice['tracking_no'], ENT_QUOTES, 'UTF-8'); ?></p>
            <p><strong>Reason:</strong> <?php echo htmlspecialchars($blockedNotice['reason'], ENT_QUOTES, 'UTF-8'); ?></p>
            <?php if (!empty($blockedNotice['note'])): ?><p><strong>Office note:</strong> <?php echo htmlspecialchars($blockedNotice['note'], ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
            <p>Reschedule or cancel this service only. Rescheduling gives it a new ticket and QR; your other services keep their schedule and progress.</p>
        </div>
        <a href="booking#blocked-appointment-<?php echo (int)$blockedNotice['id']; ?>" class="ss-block-review">Review update</a>
    </article>
    <?php endforeach; ?>
</section>
<?php endif; ?>
