CREATE TABLE /*_*/pagepermissions (
	pper_page_id int unsigned NOT NULL,
	pper_user_id int unsigned NOT NULL,
	pper_role varchar(60) NOT NULL,
	pper_timestamp varbinary(14) NOT NULL default '',
	PRIMARY KEY (pper_page_id, pper_user_id)
) /*$wgDBTableOptions*/;
