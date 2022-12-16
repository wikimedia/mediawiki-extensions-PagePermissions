# PagePermissions  
Manages access per user per page  
== Installation ==  
1) [Download](https://www.mediawiki.org/wiki/Special:ExtensionDistributor/PagePermissions) the extension and place it in the **extensions/** directory.  
2) Add the following line in **LocalSettings.php**  
```
wfLoadExtension( 'PagePermissions' );
```  
3) Run the [update script](https://www.mediawiki.org/wiki/Special:MyLanguage/Manual:Update.php) which will automatically create the necessary database tables that this extension needs.  
4) ✅ Done - Navigate to [Special:Version](https://www.mediawiki.org/wiki/Special:Version) on your wiki to verify that the extension is successfully installed.  
  

  
Instead of downloading the zip archive you may also check this extension out via Git:
```
git clone https://gerrit.wikimedia.org/r/mediawiki/extensions/PagePermissions
```
== Configuration ==   
=== PagePermissionsRoles ===  
Add the custom roles and their respective permissions in **extension.json**  
  
**Example:**  
```
"PagePermissionsRoles": {
	"reader": ["read"],
	"editor": ["read", "edit"],
	"manager": ["read", "edit", "move", "rollback"],
	"owner": ["read", "edit", "move", "rollback", "delete", "pagepermissions"]
}
```
=== User Rights ===  
Allows users to use the "PagePermissions" page action in order to add or remove user rights for this page. Defaults to:
```
$wgGroupPermissions['sysop']['pagepermissions'] = true;
```  
