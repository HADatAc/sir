# SIR: Semantic Instrument Repository

* Developer: HADatAc.org community (http://hadatac.org)

The PHP code in this repository has been developed as a <i>custom module</i> for Drupal 8+. 

### Deployment: 

SIR deployment requires the availability of a Drupal instance (version 8 or above), and an user of this Drupal instance with adminstrative privileges. 

* upload SIR code
  * in the administration menu of Drupal, go to <i>Extend</i> > <i>Add New Module</i> > <i>Add from a URL</i>
  * paste the URL from Download.zip from https://github.com/HADatAc/sir/
* upload module dependencies:
  * Key (https://www.drupal.org/project/key)
* go to <i>Extend</i> and install both SIR and its dependencies
 
### Configuration setup:

User needs to have administrative privileges on Drupal to be able to setup SIR

* setup secret key to connect to API
  * the secret key is a string used during the setup of the API. The secret key of SIR and its API must be exactly the same
  * In SIR, the key is added going to [drupal_url]/admin/config/system/keys/add
    * Provide a name that will be later selected in the SIR configuration page
    * Select type <i>Authentication</i>
    * Select provider <i>Configuration</i>

### Usage:

Once the module is installed, SIR options are going to be available under the option <b>Advanced</b> of the main menu. Access to SIR options depends on user permissions on Drupal. 

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

  
