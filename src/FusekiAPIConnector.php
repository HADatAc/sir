<?php

namespace Drupal\sir;

use Drupal\Core\Http\ClientFactory;
use BorderCloud\SPARQL\SparqlClient;


class FusekiAPIConnector
{
   
    /**
   * Settings Variable.
   */
  Const CONFIGNAME = "sir.settings";

   /**
     * {@inheritdoc}
     */

     protected function getEditableConfigNames() {
        return [
            static::CONFIGNAME,
        ];
    }
   
    private $client;
    private $query;

    public function __construct(ClientFactory $client){

       

    }

    
  

    public function instrumentsList($endpoint)
    {
        $data = [];

       # print "<hr>2";
      #  print($endpoint);  
      #  exit();

            try {
                
                #$request = $this->client->get($endpoint,$options);
                #$result = $request->getbody()->getContents();
                #$data = json_decode($result);
                
                
               # $config = $this->config(static::CONFIGNAME);      
                #$sir_home = $config->get("sir_home");
            
               #$endpoint = $config->get("api_url")."/sir/query";
        
                $sc = new SparqlClient();
        
                $sc->setEndpointRead($endpoint);
                $sc->setMethodHTTPRead("GET");
                
                $query = "SELECT ?iname ?ilabel ?idesc ?s 
                WHERE { 
                  ?s <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://www.w3.org/2002/07/owl#NamedIndividual> .
                  ?s <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://hadatac.org/ont/hasco/Questionnaire> .
                  ?s <http://www.w3.org/2000/01/rdf-schema#label> ?iname .
                  ?s <http://hadatac.org/ont/hasco/hasShortName> ?ilabel .
                  ?s <http://www.w3.org/2000/01/rdf-schema#comment> ?idesc . 
                } LIMIT 250";
        $rows = $sc->query($query, 'rows');
        $err = $sc->getErrors();

        $data = $rows["result"]["rows"];


        /*
        print "<hr>";
        print_r($rows["result"]["rows"])."<hr>a<hr>a";

        if($rows)
            {
                echo "<table class='table' border='1'>";
                foreach ($rows["result"]["rows"] as $row){
                    echo "<tr>";
                    echo "<th>".$row['ilabel']."</th>";
                    echo "<td>".$row['iname']."</td></tr>";
                }
                echo "<table>";
            }

        exit();
        */

        #$size = sizeof($rows["result"]["rows"]);   
        /*   
            if($rows)
            {
                echo "<table class='table' border='1'>";
                foreach ($rows["result"]["rows"] as $row){
                    echo "<tr>";
                    echo "<th>".$row['ilabel']."</th>";
                    echo "<td>".$row['iname']."</td></tr>";
                }
                echo "<table>";
            }
            else
            {
                return[
                    '#type' => 'markup',
                    '#markup' => $this->t("No questionnaire registered yet")
                ];
            }
            */
        }
        catch(\GuzzleHttp\Exception\RequestException $e){
            watchdog_exception('instrumentlist',$e, $e->getMessage());
        }

       // print_r($data);
       // exit();

       return $data;
    }
}