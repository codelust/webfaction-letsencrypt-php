# PHP Letsencrypt Client For Webfaction Servers

PHP command line tool to automate the updates and renewal of Letsencrypt certificates on Webfaction.

## Installation

- Clone the repo
- Run composer update (make sure you have composer installed)
- Edit the YAML file
- Run the wle.php file or write your own custom file using the LeScriptUpdater class.

Important: You will have to make sure that you have enabled a non-secure application/website on webfaction for the verification to run. 