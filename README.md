# PagePermissions  
Manages access per user per page
## Installation  
1) <a href = "https://github.com/sanjay-thiyagarajan/PagePermissions/archive/refs/heads/master.zip">Download</a> the extension and place it in the ```extensions/``` directory.  
2) Add the following line in **LocalSettings.php**  
```
wfLoadExtension( 'PagePermissions' );
```  
3) Run the [update script](https://www.mediawiki.org/wiki/Special:MyLanguage/Manual:Update.php) which will automatically create the necessary database tables that this extension needs.  
4) ✅ Done - Navigate to [Special:Version](https://www.mediawiki.org/wiki/Special:Version) on your wiki to verify that the extension is successfully installed.  
  

  
Instead of downloading the zip archive you may also check this extension out via Git:
```
git clone https://github.com/sanjay-thiyagarajan/PagePermissions.git
```
## Configuration  
### Parameters
#### PagePermissionsRestrictionTypes  
Add the custom roles and their respective restrictions, in **extension.json**  
_Note that the permissions mentioned for each role **will be removed** from the users with the corresponding role with respect to the target page_  
  
**Example:**  
```
"PagePermissionsRestrictionTypes": {
		"reader": ["edit", "move", "rollback", "delete", "pagepermissions"],
		"editor": ["move", "rollback", "delete", "pagepermissions"],
		"manager": ["delete"],
		"owner": []
}
```
#### User Rights  
Allows users to use the "PagePermissions" page action in order to add or remove user rights for this page. Defaults to:
```
$wgGroupPermissions['sysop']['pagepermissions'] = true;
```  
