<?php

namespace Drupal\sir\Controller;

use Drupal\Core\Controller\ControllerBase;
use BorderCloud\SPARQL\SparqlClient;


class Teste extends ControllerBase{

    public function insert()
    {
    
        #$endpoint = "https://query.wikidata.org/sparql";
        $endpoint = "http://192.168.50.163:3030/sir/query";

        $sc = new SparqlClient();

        $sc->setEndpointRead($endpoint);
        $sc->setMethodHTTPRead("GET");
        $str = "";
       # $q = "select *  where {?x ?y ?z.} LIMIT 50";
        $q = "select ?x ?y ?z  where {?x ?y ?z.} LIMIT 5";

        $rows = $sc->query($q, 'rows');
        $err = $sc->getErrors();

       print_r($rows);
        echo "<hr><hr>";
        #exit();
      
        $tamanho = sizeof($rows["result"]["rows"]);
       
        foreach ($rows["result"]["rows"] as $row) {
            foreach ($rows["result"]["variables"] as $variable) {
              #  printf("%-20.20s", $row[$variable]);
                echo " ".$row[$variable];

                $str = $str.$variable;
                echo ' || ';
            }
            echo "<br>";
        }


        echo "<hr><hr><hr>";
        
        foreach ($rows["result"]["rows"] as $row) {
            echo "s:".$row['x']." ";
            echo "| p:".$row['y']." ";
            echo "| o:".$row['z']." ";
            foreach ($rows["result"]["variables"] as $variable) {
              ##  printf("%-20.20s", $row[$variable]);
             #   echo " ".$row[$variable];

              #  $str = $str.$variable;
              #  echo ' | ';
            }
            echo "<br>10";
        }


        #update
        echo "<hr><hr><hr>14";
        echo "<hr><hr><hr>14";


        $sc = new SparqlClient();
        $sc->setEndpointRead('http://192.168.50.30:3030/sir/update');
        $sc->setEndpointWrite('http://192.168.50.30:3030/sir/update');
        echo "\nInsert :";
        $q = "
        INSERT DATA { <http://kook.com/d> <http://kook.com/e> <http://kook.com/f>. }";
        $res = $sc->query($q,'raw');
        $err = $sc->getErrors();
        if ($err) {
            print_r($err);
            throw new Exception(print_r($err,true));
        }
        var_dump($res);

        /*
        $endpoint2 = "http://192.168.50.30:3030/sir/update";

        $q2 = 'INSERT DATA {
            <http://kook.com/d> <http://kook.com/e> <http://kook.com/f>.
            }';

        $sc2 = new SparqlClient();

        $sc2->setEndpointWrite($endpoint2);

        $rows2 = $sc2->query($q2, 'rows');

        $err2 = $sc2->getErrors();

        print_r($rows2);
      #  $sc2->setMethodHTTP("POST");

        /*

        return[
            '#type' => 'markup',
            '#markup' => $this->t("err 37 -  ".$str)
            ];

            */
    }

}