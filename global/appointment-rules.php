<?php

final class SmartServeAppointmentRules {
    public const ARRIVAL_GRACE_MINUTES = 10;
    public const SMS_REMINDER_MINUTES = 10;
    public const SCHEDULED_AT_SQL = "TIMESTAMP(a.appointment_date, TIME(COALESCE(
        STR_TO_DATE(a.appointment_time, '%h:%i %p'),
        STR_TO_DATE(a.appointment_time, '%H:%i:%s'),
        STR_TO_DATE(a.appointment_time, '%H:%i')
    )))";

    public static function arrivalOpenSQL() {
        return '(a.is_walkin = 1 OR ' . self::SCHEDULED_AT_SQL . ' > DATE_SUB(NOW(), INTERVAL ' . self::ARRIVAL_GRACE_MINUTES . ' MINUTE))';
    }

    public static function unblockedServiceSQL() {
        return "NOT EXISTS (SELECT 1 FROM blocked_schedule_appointments hold
            JOIN blocked_schedules block ON block.id=hold.block_id AND block.status=1
            WHERE hold.tracking_no=ao.tracking_no AND hold.office_id=ao.office_id
            AND hold.action_status IN ('pending','notified','reschedule_requested'))";
    }
}
