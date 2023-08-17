# SIR: Semantic Instrument Repository

This repository has been developed as a [custom module](https://www.drupal.org/docs/develop/creating-modules) for [Drupal 9+](https://www.drupal.org/about/9) and implemented mainly in PHP. The module depends on an external API called SIRAPI (SIRAPI code is available at https://github.com/HADatAc/sirapi). SIR content is stored inside of the API's knowledge graph.  

* Developer: HADatAc.org community (http://hadatac.org)

## Deployment: 

SIR deployment requires the availability of a Drupal instance (version 9 or above), and an user of this Drupal instance with adminstrative privileges. 

* upload SIR code
  * in the admin menu, go to `Extend` > `Add New Module` > `Add from a URL`
  * paste the following link from github: `https://github.com/HADatAc/sir/archive/refs/heads/main.zip`
* upload module dependencies. See below a list of current SIR dependencies:
  * <i>Key</i> (https://www.drupal.org/project/key)
* go to `Extend` and install both SIR and its dependencies
* clear all Drupal caches
  * in the admin menu, go to `Configuration` > `Performance` > `Clear All Caches`  
 
## Configuration setup:

User needs to have administrative privileges on Drupal to be able to setup SIR

* Step 1: setup secret key to connect to API
  * the secret key is a string used during the setup of the API. The secret key of SIR and its API must be exactly the same
  * In SIR, the key is added going to `[drupal_url]/admin/config/system/keys/add`
    * Provide a name that will be later selected in the SIR configuration page
    * Select type `Authentication`
    * Select provider `Configuration`
* Step 2: setup SIR
  * go to `Main Menu` > `Advanced` > `Configuration` (or alternativelly `[drupal_url]/admin/config/sir`)
    * Check whether or not you want SIR search page to be the main page of your website
    * Provide a short name
    * Provide a full name - used as the website's title
    * Provide a domain URL - this is the base of the URIs for all the SIR elements created in the current SIR instance 
    * Provide a namespace for the domain
    * Provide a description for the website
    * Provide the base URL for the API -- this is the URL of the back-end machine hosting the API
    * Provide the name of the key used to create API tokens -- the API is not going to respond if the token is missing or is incorrect
* Step 3: setup SIR's Knowledge Graph
  * go to `Main Menu` > `Advanced` > `Configuration` > `Manage Namespaces` (or alternativelly `[drupal_url]/admin/config/sir/namespace`)
    * verify that you can see a list of namespaces
    * for the namespaces with values for `Source URL`, verify if they have values for triples. If not, you need to select the `Reload Triples From All Namespaces With URL`
    * wait a while and press the refresh button of the browser to verify if the triples have been loaded
    * if needed, the triples can be deleted and reloaded again. Wait for the triples to be zeroed before reloading.   

## Usage:

Once the module is installed, SIR options are going to be available under `main menu` > `Advanced`. Access to SIR options depends on user permissions on Drupal. By default, an anonymous user of a SIR repository has access to the `search` and `about` pages. 

## Upgrade (in Pantheon): 

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

  
