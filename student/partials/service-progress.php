<?php
/** Presentation of office visits without conflating them with the parent ticket. */
function smartserve_student_service_summary(array $booking): array {
    $services = $booking['services'] ?? [];
    $counts = ['total'=>count($services), 'completed'=>0, 'active'=>0, 'started'=>0, 'serving'=>0, 'rescheduled'=>0];
    foreach ($services as $service) {
        $status = (int)$service['status'];
        if (!empty($service['rescheduled_tracking_no'])) $counts['rescheduled']++;
        elseif ($status === 3) $counts['completed']++;
        elseif (in_array($status, [0,1,2], true)) {
            $counts['active']++;
            if ($status > 0) $counts['started']++;
            if ($status === 2) $counts['serving']++;
        }
    }
    if (!$services) {
        $bucket = [0=>'pending',1=>'progress',2=>'completed',3=>'cancelled'][(int)($booking['status'] ?? 0)] ?? 'pending';
    } elseif ($counts['active']) {
        $bucket = $counts['started'] || $counts['completed'] ? 'progress' : 'pending';
    } elseif ($counts['completed'] === $counts['total']) $bucket = 'completed';
    elseif ($counts['completed']) $bucket = 'partial';
    elseif ($counts['rescheduled']) $bucket = 'rescheduled';
    else $bucket = 'cancelled';
    $labels = ['pending'=>'Pending','progress'=>'In Progress','completed'=>'Completed','partial'=>'Partially completed','rescheduled'=>'Rescheduled','cancelled'=>'Cancelled'];
    return $counts + ['bucket'=>$bucket, 'label'=>$labels[$bucket]];
}

function smartserve_service_state(array $service): array {
    if (!empty($service['rescheduled_tracking_no'])) return ['rescheduled','Rescheduled'];
    if (!empty($service['blocked_notice_id']) && in_array((int)$service['status'], [0,1], true)) return ['blocked','Schedule blocked'];
    return [0=>['pending','Pending check-in'],1=>['queued','Queued'],2=>['serving','Being served'],3=>['completed','Completed'],4=>['cancelled','Cancelled / No-show']][(int)$service['status']] ?? ['pending','Pending'];
}

function smartserve_render_service_progress(array $booking, bool $compact = false): void {
    $services = $booking['services'] ?? [];
    if (!$services) return;
    $summary = smartserve_student_service_summary($booking);
    $escape = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    ?>
    <div class="ss-service-progress<?php echo $compact ? ' ss-service-progress-compact' : ''; ?>">
        <p class="ss-service-count"><strong><?php echo $summary['completed']; ?> of <?php echo $summary['total']; ?> services completed</strong><?php if ($summary['active']): ?> · <?php echo $summary['active']; ?> still open<?php endif; ?></p>
        <ul class="ss-service-list" aria-label="Service progress">
        <?php foreach ($services as $service): [$state, $label] = smartserve_service_state($service); ?>
            <li class="ss-service-row">
                <div class="ss-service-heading"><strong><?php echo $escape($service['office_name']); ?></strong><span class="ss-service-status ss-service-<?php echo $state; ?>"><?php echo $label; ?></span></div>
                <?php if (!$compact): ?>
                    <?php if (in_array($state, ['queued','serving'], true) && !empty($service['queue_number'])): ?><p>Queue: <strong><?php echo $escape($service['queue_number']); ?></strong><?php if (!empty($service['served_at_window'])): ?> · <?php echo $escape($service['served_at_window']); ?><?php endif; ?></p><?php endif; ?>
                    <?php if ($state === 'completed' && !empty($service['time_completed'])): ?><p>Completed <?php echo $escape(date('M d, Y h:i A', strtotime($service['time_completed']))); ?></p><?php endif; ?>
                    <?php if ($state === 'blocked'): ?><p><?php echo $escape($service['block_reason'] ?? 'Please choose a new schedule or cancel this service.'); ?></p><a href="booking#blocked-appointment-<?php echo (int)$service['blocked_notice_id']; ?>">Review this service update</a><?php endif; ?>
                    <?php if (!empty($service['office_notes']) && $state !== 'rescheduled'): ?><p class="ss-service-note"><?php echo nl2br($escape($service['office_notes'])); ?></p><?php endif; ?>
                <?php endif; ?>
                <?php if ($state === 'rescheduled'): ?>
                    <a href="booking-details?id=<?php echo rawurlencode($service['rescheduled_tracking_no']); ?>">View new ticket and QR<?php if (!$compact && !empty($service['rescheduled_date'])): ?> · <?php echo $escape(date('M d, Y', strtotime($service['rescheduled_date']))); ?> <?php echo $escape($service['rescheduled_time'] ?? ''); ?><?php endif; ?></a>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
        </ul>
    </div>
    <?php
}
