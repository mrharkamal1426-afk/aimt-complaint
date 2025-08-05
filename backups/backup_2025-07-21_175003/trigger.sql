-- Trigger to automatically update complaint history when status changes
CREATE TRIGGER IF NOT EXISTS complaint_status_trigger
AFTER UPDATE ON complaints
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO complaint_history (complaint_id, status, note, created_at)
        VALUES (NEW.id, NEW.status, 
                CASE 
                    WHEN NEW.status = 'in_progress' THEN 'Complaint assigned to technician'
                    WHEN NEW.status = 'resolved' THEN 'Complaint resolved successfully'
                    WHEN NEW.status = 'rejected' THEN 'Complaint rejected'
                    ELSE CONCAT('Status changed to ', NEW.status)
                END,
                NOW());
    END IF;
END 