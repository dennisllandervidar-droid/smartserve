-- Keep the legacy specific_slot value while supporting the form's specific_purpose value.
ALTER TABLE blocked_schedules
    MODIFY block_type ENUM('full_day','time_range','specific_slot','specific_purpose')
    NOT NULL DEFAULT 'full_day';
