DELETE FROM sf_checkin_responses WHERE DATE(submitted_at) = CURDATE();

UPDATE sf_checkin_availability 
SET is_completed = 0, completed_at = NULL
WHERE week_date = DATE(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY))
   OR DATE(completed_at) = CURDATE();

DELETE FROM sf_checkin_flow_answers WHERE DATE(created_at) = CURDATE();

DELETE FROM sf_checkin_flow_events WHERE DATE(created_at) = CURDATE();

