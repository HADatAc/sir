# sir
Semantic Instrument Repository

### Deployment: 

* go to Drupal > Extend > Add New Module > Add from a URL
* paste the URL from Download.zip from https://github.com/HADatAc/sir/

* install module dependencies:
** Key

### Upgrade (in Pantheon): 

* put website under maintenance
* uninstall module
* clear caches
* move website from git to sftp mode
* use sftp to remove module files under ./modules
* use sftp to remove module files cached under /tmp
* install new SIR
* clear caches
* remove website from maintenance
* restore sir configuration including key

  
