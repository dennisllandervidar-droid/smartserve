<div class="col-6">
    <div class="form-group">
        <label for="start_time">Start Time</label>
        <select name="start_time" id="start_time" class="form-control" aria-describedby="time_range_help">
            <option value="">Select start time</option>
            <?php foreach (SmartServeValidation::BLOCK_HOURS as $hour): ?>
                <option value="<?php echo $hour; ?>"<?php echo $hour === '16:00' ? ' disabled' : ''; ?>><?php echo date('g A', strtotime($hour)); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>
<div class="col-6">
    <div class="form-group">
        <label for="end_time">End Time</label>
        <select name="end_time" id="end_time" class="form-control" aria-describedby="time_range_help">
            <option value="">Select end time</option>
            <?php foreach (SmartServeValidation::BLOCK_HOURS as $hour): ?>
                <option value="<?php echo $hour; ?>"><?php echo date('g A', strtotime($hour)); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>
<div class="col-12">
    <small id="time_range_help" class="text-muted d-block mb-3">Choose hourly times from 7 AM to 4 PM. End time must be later than start time.</small>
</div>
