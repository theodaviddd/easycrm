-- Table pour stocker les événements d'appel Keyyo
CREATE TABLE IF NOT EXISTS llx_easycrm_call_events (
    rowid int(11) NOT NULL AUTO_INCREMENT,
    fk_user int(11) NOT NULL,
    fk_contact int(11) NOT NULL,
    caller varchar(50) NOT NULL,
    callee varchar(50) NOT NULL,
    call_date datetime NOT NULL,
    status varchar(20) NOT NULL DEFAULT 'new',
    date_creation datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tms timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (rowid),
    KEY idx_fk_user (fk_user),
    KEY idx_fk_contact (fk_contact),
    KEY idx_status (status),
    KEY idx_call_date (call_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8; 