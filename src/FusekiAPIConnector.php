<?php

namespace Drupal\sir;

use \Drupal\Core\Http\ClientFactory;
use GuzzleHttp\Client;
use Symfony\Component\HttpFoundation\JsonResponse;


class FusekiAPIConnector
{
    private $client;
    private $query;

    public function __construct(ClientFactory $client){
        
      

    }

    
  

    public function instrumentsList($api_url,$endpoint)
    {
      $instruments = [];
      //if we add auth in future
      $data = [
            'auth' => ['user', 'pass']
      ];
     
     $instruments = $this->perform_http_request('GET',$api_url.$endpoint,$data);   
      
      return($instruments);
    }

    public function instrumentAdd($api_url,$endpoint, $data)
    {
      return $this->perform_http_request('POST',$api_url.$endpoint,$data);          
    }

    public function repositoryConf($api_url,$endpoint, $data)
    {
      return $this->perform_http_request('GET',$api_url.$endpoint,$data);          
    }

    public static function perform_http_request($method, $url, $data = false) {
       
        try 
        {
            $client = new Client();
            $res = $client->request($method, $url, $data);
            if($res->getStatusCode() != '200')
            {
                echo "error";
                exit();
            }
            return($res->getBody());
       }
       catch (RequestException $e) {
        // log exception
        print_r($e);
      }
    }
}