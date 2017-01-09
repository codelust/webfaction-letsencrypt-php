<?php

namespace Frontiernxt;

use Frontiernxt\Logger;
use Milo\XmlRpc;
use Noodlehaus\Config;
use Analogic\ACME\Lescript;
use Milo\XmlRpc\Convertor;

class LeScriptUpdater
{
    
    public function __construct($yaml_path)
    {
    	try {

			$conf = Config::load($yaml_path);

		} catch (\Exception $e) {
	    
		    echo "\n".$e->getMessage()."\n";

	        exit(1);
		}

		$this->conf = $conf;
		$this->profiles = $conf->get('profiles');
		$this->domains = $conf->get('domains');
    }


    public function validateProfiles()
    {

		if (!isset($this->profiles) || !$this->profiles) 
		{
			throw new \Exception('No valid profiles found');
		}
    }

    public function validateDomains()
    {

		if (!isset($this->domains) || !$this->domains) 
		{
			throw new \Exception('No valid domains found');
		}
    }


    public function getValidDomains()
    {
    	$valid_domains = null;

    	if ($this->validateDomains())
    	{
    		return null;
    	}

    	foreach ($this->domains as $domain => $data)
		{
		
			/*set vars*/
			$profile = !empty($data['profile']) ? $data['profile'] : null;
			$active = !empty($data['active']) ? $data['active'] : false;
			$web_root = !empty($data['web_root']) ? $data['web_root'] : null;
			$cert_location = !empty($data['cert_location']) ? $data['cert_location'] : null;
			$certificate_name = !empty($data['certificate_name']) ? $data['certificate_name'] : null;

			$data_complete = true;

			if (!$profile)
			{

				$data_complete = false;
			}

			if (!$active)
			{

				$data_complete = false;
			}

			if (!$web_root)
			{
				$data_complete = false;
			}

			if (!file_exists($web_root) || !is_writeable($web_root))
			{
				$data_complete = false;

			}

			if (!$cert_location)
			{

				$data_complete = false;
			}

			if (!file_exists($cert_location) || !is_writeable($cert_location))
			{

				$data_complete = false;

			}

			if (!$certificate_name)
			{

				$data_complete = false;
			}



			if ($data_complete)
			{

				$valid_domains[$domain]= $data; 

			}

		}

		return $valid_domains;
    
    } // end get valid domains


    public function generateLECertificates()
    {

    	$logger = new Logger();

    	$valid_domains = $this->getValidDomains();

    	foreach ($valid_domains as $domain => $data)
    	{	

    		$cert_location = $data['cert_location'];
    		$web_root = $data['web_root'];

    		// Make sure our cert location exists
			
			if (!is_dir($cert_location)) {
		        // Make sure nothing is already there.
		        if (file_exists($cert_location)) {
		                unlink($cert_location);
		        }
		        mkdir ($cert_location);
			}

			// Do we need to create or upgrade our cert? Assume no to start with.
			$needsgen = false;

			$cert_file = "$cert_location/cert.pem";

	        if (!file_exists($cert_file)) {
	                // We don't have a cert, so we need to request one.
	                $needsgen = true;
	        } else {
	                // We DO have a certificate.
	                $cert_data = openssl_x509_parse(file_get_contents($cert_file));

	                // If it expires in less than a month, we want to renew it.
	                $renew_after = $cert_data['validTo_time_t']-(86400*30);
	                
	                if (time() > $renew_after) {
	                        // Less than a month left, we need to renew.
	                        $needsgen = true;
	                }
	        }

	        // Do we need to generate a certificate?
			if ($needsgen) {
			        try {
			                $le = new Lescript($cert_location, $web_root, $logger);
			                $le->initAccount();
			                $le->signDomains(array($domain));

			        } catch (\Exception $e) {
			                $logger->error($e->getMessage());
			                $logger->error($e->getTraceAsString());
			                // Exit with an error code, something went wrong.
			                //exit(1);
			        }
			}

    	}

    }

    public function getProfile($profile_name)
    {

    	if (isset($this->profiles[$profile_name]) && !empty($this->profiles[$profile_name]))
    	{

    		$profile_data = $this->profiles[$profile_name];

    		if (isset($profile_data['wf_username']) && !empty($profile_data['wf_username']))
    		{


    		} else {

    			throw new \Exception('wf_username is empty or not set');

    		}

    		if (isset($profile_data['wf_password']) && !empty($profile_data['wf_password']))
    		{


    		} else {

    			throw new \Exception('wf_password is empty or not set');

    		}

    		if (isset($profile_data['wf_machine_name']) && !empty($profile_data['wf_machine_name']))
    		{


    		} else {

    			throw new \Exception('wf_machine_name is empty or not set');

    		}

    		/*everything checks out return the profile*/

    		return $this->profiles[$profile_name];

    	} else {


    		throw new \Exception('Supplied profile segment not found');
    	}

    	return null;

    }

    public function updateWFCertificates()
    {

    	$valid_domains = $this->getValidDomains();

    	foreach ($valid_domains as $domain => $data)
    	{	

    		$cert_location = $data['cert_location'];
    		$web_root = $data['web_root'];
    		$certificate = file_get_contents("$cert_location/cert.pem");
			$private_key = file_get_contents("$cert_location/private.pem");
			$certificate_name = $data['certificate_name'];
			

			try {
				
				$profile_data = $this->getProfile($data['profile']);

			} catch (\Exception $e) {
				
				echo "\n".$e->getMessage()."\n";
			}


			# Converter between XML source and PHP classes
			$converter = new Convertor;

			# Method we are calling and its arguments

			$token_request = new XmlRpc\MethodCall('login', [$profile_data['wf_username'], $profile_data['wf_password'], $profile_data['wf_username'], 2]);


			# Perform request over HTTP
			$token_context = stream_context_create([
			    'http' => array(
			        'method' => 'POST',
			        'header' => 'Content-type: text/xml',
			        'content' => $converter->toXml($token_request),
			    ),
			]);

			$token_response = file_get_contents('https://api.webfaction.com/', FALSE, $token_context);

			$token = $token_response[0];

			/*add more error checking here*/

			$cert_update_request = new XmlRpc\MethodCall('update_certificate', [$token, $certificate_name, $certificate, $private_key]);

    	}

    /*	$cert = file_get_contents("$certlocation/$d/cert.pem");
		$private_key = file_get_contents("$certlocation/$d/private.pem");


		$client = new fXmlRpc\Client('https://api.webfaction.com/');
		$resp = $client->call('login', array('codelust', '^headsquid9', 'Web474', 2));

		$token = $resp[0];

		$update_resp = $client->call('update_certificate', array($token, 'fnxt_wpmu_ssl', $cert, $private_key));*/

    }


    public function run()
    {

    	$this->generateLECertificates();

    	$this->updateWFCertificates();
    }


}
