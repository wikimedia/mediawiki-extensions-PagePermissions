BEGIN;

CREATE TABLE pagepermissions_rights (
	page_id int unsigned NOT NULL,
	userid int unsigned NOT NULL,
	permission varchar(60) NOT NULL,
	page_namespace int NOT NULL,
	right_timestamp varbinary(14) NOT NULL default '',
	PRIMARY KEY (page_namespace, page_id, userid)
)/*$wgDBTableOptions*/;

COMMIT;