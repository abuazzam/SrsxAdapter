<?php
/**
 * SRSX REGISTRAR MODULE FOR BOXBILLING
 *
 * Created by Agung Nugroho
 * For hosting please visit https://www.pahinhoster.com
 * For reseller Domain ID visit https://dotid.pahin.web.id/reseller/register
 */
 
/**
 * HTTP API documentation https://kb.srs-x.com/en/
 */
class Registrar_Adapter_Srsx extends Registrar_AdapterAbstract implements \Box\InjectionAwareInterface
{
	
    public $config = array(
        'resellerId'  => null,
        'apiUsername' => null,
        'apiPassword' => null,
    );
    
    protected $di = null;
    
    public function setDi($di)
    {
        $this->di = $di;
    }

    public function getDi()
    {
        return $this->di;
    }

	public function __construct($options)
    {
 
        if (!extension_loaded('curl')) {
            throw new Registrar_Exception('CURL extension is not enabled');
        }
 
        if(isset($options['resellerId']) && !empty($options['resellerId'])) {
            $this->config['resellerId'] = $options['resellerId'];
            unset($options['resellerId']);
        } else {
            throw new Registrar_Exception('Domain registrar "SRSX" is not configured properly. Please update configuration parameter "SRSX Reseller ID" at "Configuration -> General -> Profile".');
        }
 
        if(isset($options['apiUsername']) && !empty($options['apiUsername'])) {
            $this->config['apiUsername'] = $options['apiUsername'];
            unset($options['apiUsername']);
        } else {
            throw new Registrar_Exception('Domain registrar "SRSX" is not configured properly. Please update configuration parameter "SRSX Username" at "Configuration -> General -> API".');
        }
 
        if(isset($options['apiPassword']) && !empty($options['apiPassword'])) {
            $this->config['apiPassword'] = $options['apiPassword'];
            unset($options['apiPassword']);
        } else {
            throw new Registrar_Exception('Domain registrar "SRSX" is not configured properly. Please update configuration parameter "SRSX API Key" at "Configuration -> General -> API".');
        }
        
    }
    
    public static function getConfig()
    {
        return array(
            'label'     =>  'Manages domains on SRSX via API. SRSX requires your server IP in order to work. Login to the SRSX control panel (the url will be in the email you received when you signed up with them) and then go to Configuration -> General > API and enter the IP address of the server where BoxBilling is installed to authorize it for API access.',
            'form'  => array(
 
                'resellerId' => array('text', array(
                            'label' => 'RESELLER ID',
                            'description'=> 'You can get this at SRSX control panel, go to Configuration -> General -> Profile'
                        ),
                     ),
 
                'apiUsername' => array('text', array(
                        'label' => 'Username Reseller. You can get this at SRSX control panel Configuration -> General > API',
                        'description'=> 'SRSX Reseller API Username'
                            ),
                        ),
 
                 'apiPassword' => array('password', array(
                    'label' => 'Password Reseller. You can get this at SRSX control panel Configuration -> General > API',
                    'description'=> 'SRSX API Password'
                ),
             ),
 
            ),
        );
    }
    
    public function getTlds() 
    {
      	return array();
    }
    
    public function isDomainAvailable(Registrar_Domain $domain)
    {
        $params = array(
            'domain'	=> $domain->getName(),
        );
 
        $response = $this->_callApi("domain/check", $params); 
		if (sprintf($response->result->resultCode)==1000) {
			return true;
		} else {
			return false;
		}
    }
    
    public function isDomainCanBeTransfered(Registrar_Domain $domain)
    {
      	throw new Registrar_Exception('Domain transfer checking is not implemented');
    }
    
    public function modifyNs(Registrar_Domain $domain)
    {
        $ns = array();
        $ns[] = $domain->getNs1();
        $ns[] = $domain->getNs2();
        if($domain->getNs3())  {
            $ns[] = $domain->getNs3();
        }
        if($domain->getNs4())  {
            $ns[] = $domain->getNs4();
        }

        $params = array(
            'api_id'  		=>  $this->_getOrderId($domain->getName()),
            'nameserver' 	=>  implode(',', $ns),
            'domain' 		=> 	$domain->getName()
        );

        $response = $this->_callApi('domain/updatens', $params);
        if (sprintf($response->result->resultCode)==1000) {
          	return true;
        } else {
          	return false;
        }
    }
    
    public function modifyContact(Registrar_Domain $domain)
    {
		throw new Registrar_Exception('modify contact is not implemented');
    }
	
	public function transferDomain(Registrar_Domain $domain)
	{
		throw new Registrar_Exception('Domain transfer checking is not implemented');
	}
	
	public function getDomainDetails(Registrar_Domain $domain)
	{
		$params = array(
			'domain'	=> $domain->getName(),
			'api_id'	=> $this->_getOrderId($domain->getName())
		);
		
		$result = $this->_callApi('domain/info', $params, 'POST');
		
		return $domain;
	}
	
	public function getEpp(Registrar_Domain $domain)
    {
       	# Get EPP Code
		$params = array(
			"domain" => $domain->getName(),
			"api_id" => $this->_getOrderId($domain->getName())
		);
		$response = $this->_callApi("domain/getepp", $params);
		if (sprintf($response->result->resultCode)==1000) {
			$epp = "EPP Code: ".sprintf($response->resultData->epp);
			return $epp;
		} else {
			throw new Registrar_Exception(sprintf($response->result->resultMsg));
		}
    }
    
    public function registerDomain(Registrar_Domain $domain)
    {
		$tld = $domain->getTld();
		$contact = $domain->getContactRegistrar();
		$company = $contact->getCompany();
        if (!isset($company) || strlen(trim($company)) == 0 ){
            $company = 'N/A';
        }
        $phoneNum = $contact->getTel();
        $phoneNum = preg_replace( "/[^0-9]/", "", $phoneNum);
        $phoneNum = substr($phoneNum, 0, 12);
                
        $params = array(
			'api_id' 			=> $_REQUEST['id'],
			'domain'			=> $domain->getName(),
			'periode'          	=> $domain->getRegistrationPeriod(),
			
			"fname"         	=> $contact->getFirstName(),
            "lname"         	=> $contact->getLastName(), 
			"company"       	=> $company,
			"address1"       	=> $contact->getAddress1(),
			"address2"      	=> $contact->getAddress2(),
			"city"          	=> $contact->getCity(),
			"state"      		=> $contact->getState(),
			"country"       	=> $contact->getCountry(),
			"postcode"   		=> $contact->getZip(),
			"phonenumber"       => $phoneNun,
			"email"   			=> $contact->getEmail(),
					
			"user_username"    	=> $contact->getEmail(),
			"user_fname"       	=> $contact->getFirstName(),
			"user_lname"       	=> $contact->getLastName(),
			"user_email"       	=> $contact->getEmail(),
			"user_company"     	=> $company,
			"user_address"     	=> $contact->getAddress1(),
			"user_address2"    	=> $contact->getAddress2(),
			"user_city"        	=> $contact->getCity(),
			"user_province"    	=> $contact->getState(),
			"user_phone"       	=> $phoneNun,
			"user_country"     	=> $contact->getCountry(),
			"user_postal_code" 	=> $contact->getZip(),
			"randomhash"       	=> $this->randomhash(64)
         );
        
        if (!in_array($tld, array('.web.id', '.co.id', '.ac.id'))) 
        {
			$params['autoactive'] = 'on';
		}
		
        $params['ns1'] = $domain->getNs1();
        $params['ns2'] = $domain->getNs2();
        if($domain->getNs3())  {
            $params['ns3'] = $domain->getNs3();
        }
        if($domain->getNs4())  {
            $params['ns4'] = $domain->getNs4();
        }
		
		$response = $this->_callApi("domain/register", $params);
		if (sprintf($response->result->resultCode)==1000) {
			return true;
		} else {
			return false;
		}
	}
	
	public function renewDomain(Registrar_Domain $domain)
	{
		$params = array(
			'domain' 	=> $domain->getName(),
			'api_id'	=> $this->_getOrderId($domain->getName()),
			'periode' 	=> $domain->getRegistrationPeriod()
		);
		$response = $this->_callApi("domain/renew", $params);
		if (sprintf($response->result->resultCode)==1000) {
			return true;
		} else {
			return false;
		}
	}
	
	public function deleteDomain(Registrar_Domain $domain)
	{
		$params = array(
			'domain' 		=> $domain->getName(),
			'api_id'		=> $this->_getOrderId($domain->getName()),
		);
		$response = $this->_callApi("domain/cancel", $params);
		if (sprintf($response->result->resultCode)==1000) {
			return true;
		} else {
			return false;
		}
	}
	
	public function enablePrivacyProtection(Registrar_Domain $domain)
	{
		$params = array(
			'domain' 		=> $domain->getName(),
			'api_id'		=> $this->_getOrderId($domain->getName()),
			'idprotection' 	=> 1
		);
		$response = $this->_callApi("domain/set_idprotection", $params);
		if (sprintf($response->result->resultCode)==1000) {
			return true;
		} else {
			return false;
		}
	}
	
	public function disablePrivacyProtection(Registrar_Domain $domain)
	{
		$params = array(
			'domain' 		=> $domain->getName(),
			'api_id'		=> $this->_getOrderId($domain->getName()),
			'idprotection' 	=> 0
		);
		$response = $this->_callApi("domain/set_idprotection", $params);
		if (sprintf($response->result->resultCode)==1000) {
			return true;
		} else {
			return false;
		}
	}
	
	public function lock(Registrar_Domain $domain) 
	{
		$params = array(
			'domain' 		=> $domain->getName(),
			'api_id'		=> $this->_getOrderId($domain->getName()),
			'reseller_lock' => 1
		);
		$response = $this->_callApi("domain/set_lock", $params);
		if (sprintf($response->result->resultCode)==1000) {
			return true;
		} else {
			return false;
		}
	}
	
	public function unlock(Registrar_Domain $domain)
	{
		$params = array(
			'domain' 		=> $domain->getName(),
			'api_id'		=> $this->_getOrderId($domain->getName()),
			'reseller_lock' => 0
		);
		$response = $this->_callApi("domain/set_lock", $params);
		if (sprintf($response->result->resultCode)==1000) {
			return true;
		} else {
			return false;
		}
	}
	
	protected function _callApi($query=true,$postfields=true) 
	{
 
		if ($query && is_array($postfields)) {
			# Get URL
			$apiUrl = "https://srb{$this->config["resellerId"]}.srs-x.com";
			# Basic authentication
			$postfields["username"] = $this->config["apiUsername"];
            $postfields["password"] = hash('sha256',$this->config["apiPassword"]);
 
			# CURL
            $ch = curl_init();
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_URL, "{$apiUrl}/api/{$query}");
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postfields));
			$apiXml = curl_exec($ch);
			curl_close($ch);
            $apiResult = simplexml_load_string($apiXml);
 
            return $apiResult;
            #echo json_encode($apiResult);
 
		}
		return false;
    }
 
    protected function randomhash($length=6) 
    {
		$base = 'ABCDEFGHKLMNOPQRSTWXYZ123456789';
		$max = strlen($base)-1;
		$randomResult = "";
		mt_srand((double)microtime()*1000000);
		while (strlen($randomResult)<$length) {
			$randomResult .= $base[mt_rand(0,$max)];
		}
		return $randomResult;
	}
	
	protected function _getOrderId($domain) 
	{
		
		#$sql = "title LIKE '%s{$domain}%s'";
		#$row = $this->di['db']->findOne('ClientOrder', $sql);
		return 0;#$row->id;
	}
}
