BEGIN;

CREATE TABLE pagepermissions (
	pper_page_id int unsigned NOT NULL,
	pper_user_id int unsigned NOT NULL,
	pper_permission varchar(60) NOT NULL,
	pper_right_timestamp varbinary(14) NOT NULL default '',
	PRIMARY KEY (pper_page_id, pper_user_id)
)/*$wgDBTableOptions*/;

COMMIT;