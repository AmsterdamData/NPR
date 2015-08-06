<?php
set_time_limit(3600);
error_reporting(E_ALL ^E_NOTICE ^E_WARNING);
define("AREA_URL", "https://opendata.rdw.nl/resource/adw6-9hsg.json");
define("AREAGEOMETRY_URL", "https://opendata.rdw.nl/resource/nsk3-v9n7.json");
define("AREA_REGULATION_URL", "https://opendata.rdw.nl/resource/qtex-qwd8.json");
define("REGULATION_URL", "https://opendata.rdw.nl/resource/pezp-7mrc.json");
define("REGULATION_FARECALCULATION_URL","https://opendata.rdw.nl/resource/ixf8-gtwq.json"); //TIJDVAK
define("FARECALCULATION_URL","https://opendata.rdw.nl/resource/nfzq-8g7y.json");
define("TARIFFPART_URL","https://opendata.rdw.nl/resource/534e-5vdg.json");
define("INEXIT_URL","https://opendata.rdw.nl/resource/c653-u9z2.json");
define("GPSINEXIT_URL","https://opendata.rdw.nl/resource/k3dr-ge3w.json");

define("AREAUUID_URL","https://opendata.rdw.nl/resource/mz4f-59fw.json");

Class NPR{
    var $result;
    var $areamanagerid;
    
    var $areas, $areageometries, $area_regulations, $regulations, $fares, $regulation_farecalculations, $fare_calculations, $tariffparts, $inexits;
    
    function NPR(){
        
    }
    
    function getCompleteAreaManager($id = 363){
        $this->areamanagerid = $id;
        $this->areas = json_decode(file_get_contents(AREA_URL ."?areamanagerid=". $this->areamanagerid));
        $this->areageometries = json_decode(file_get_contents(AREAGEOMETRY_URL ."?areamanagerid=". $this->areamanagerid));
        $this->area_regulations = json_decode(file_get_contents(AREA_REGULATION_URL ."?areamanagerid=". $this->areamanagerid));
        $regulations = json_decode(file_get_contents(REGULATION_URL ."?areamanagerid=". $this->areamanagerid));
        $this->regulation_farecalculations = json_decode(file_get_contents(REGULATION_FARECALCULATION_URL ."?areamanagerid=". $this->areamanagerid));
        $this->farecalculations = json_decode(file_get_contents(FARECALCULATION_URL ."?areamanagerid=". $this->areamanagerid));
        $this->tariffparts = json_decode(file_get_contents(TARIFFPART_URL ."?areamanagerid=". $this->areamanagerid));
        
        foreach($this->areas as $area){
            if(substr($area->areaid,0,2) == "GR"){
                $this->result[$area->areaid] = $area;
            }
        }
        
        foreach($this->areageometries as $areageometry){
            if(array_key_exists($areageometry->areaid, $this->result)){
                $this->result[$areageometry->areaid]->areageometryastext = $areageometry->areageometryastext;
            }
        }
        
        foreach($this->farecalculations as $farecalc){
            $this->fares[$farecalc->farecalculationcode] = $farecalc;
        }
        
        foreach($this->tariffparts as $tariffpart){
            if($this->fares[$tariffpart->farecalculationcode]->parts){
                $this->fares[$tariffpart->farecalculationcode]->parts[] = $tariffpart;
            } else {
                $this->fares[$tariffpart->farecalculationcode]->parts = Array($tariffpart);
            }
        }
        
        $this->regulations = Array();
        foreach($regulations as $regulation){
            $this->regulations[$regulation->regulationid] = $regulation;
        }
        
        foreach($this->regulation_farecalculations as $regulation_farecalculation){
            if($this->regulations[$regulation_farecalculation->regulationid]->fares){
                $this->regulations[$regulation_farecalculation->regulationid]->fares[] = Array("timespan" => $regulation_farecalculation, "tariff" => $this->fares[$regulation_farecalculation->farecalculationcode]);
            } else {
                $this->regulations[$regulation_farecalculation->regulationid]->fares = Array(Array("timespan" => $regulation_farecalculation, "tariff" => $this->fares[$regulation_farecalculation->farecalculationcode]));
            }
        }

        foreach($this->area_regulations as $area_regulation){
            if($this->result[$area_regulation->areaid]){
                if($this->result[$area_regulation->areaid]->regulations){
                    $this->result[$area_regulation->areaid]->regulations[] = Array("regulation" => $area_regulation, "details" => $this->regulations[$area_regulation->regulationid]);
                } else {
                    $this->result[$area_regulation->areaid]->regulations = Array(Array("regulation" => $area_regulation, "details" => $this->regulations[$area_regulation->regulationid]));
                }
            }
        }
    }


    function getParkingGarageByArea($area = 363){
        $this->areamanagerid = $id;
        $this->areas = json_decode(file_get_contents(AREA_URL ."?\$where=areaid%20>%20%27". $area ."%27%20AND%20areaid%20<%20%27". ($area + 1) ."%27"));
        $this->areageometries = json_decode(file_get_contents(AREAGEOMETRY_URL ."?\$where=areaid%20>%20%27". $area ."%27%20AND%20areaid%20<%20%27". ($area + 1) ."%27"));

        $areamanagers = Array();
        
        foreach($this->areas as $area2){
            $this->result[$area2->areaid] = $area2;
            $areamanagers[$area2->areamanagerid] += 1;
        }
        
        foreach($this->areageometries as $areageometry){
            if(array_key_exists($areageometry->areaid, $this->result)){
                $this->result[$areageometry->areaid]->areageometryastext = $areageometry->areageometryastext;
            }
        }

        $select="\$where=areamanagerid=". $area;
        foreach(array_keys($areamanagers) as $areamanagerid){
            if($areamanagerid != $area){
                $select .= "%20OR%20areamanagerid=". $areamanagerid;
            }
        }
        
        $inexit =  json_decode(file_get_contents(INEXIT_URL ."?". $select));
        $gpslocations = json_decode(file_get_contents(GPSINEXIT_URL ."?\$where=locationreferencetype=%27I-O%27"));
        
        foreach($inexit as $in){
            $in->longitude = null;
            $in->latitude = null;
            $this->inexits[$in->entranceexitid] = $in;
        }
        
        foreach($gpslocations as $loc){
            $this->inexits[$loc->locationreference]->gps[] = $loc;
        }
        
        foreach($this->inexits as $inexit){
            if($this->result[$inexit->areaid]){
                $this->result[$inexit->areaid]->entranceandexits = $inexit;
            }
        }
        
        $this->regulations = Array();
        
        foreach($this->result as $key => $item){
            $this->result[$key]->regulations = Array();
            $select="\$where=areaid='". $item->areaid ."'";
            $area_regulations = json_decode(file_get_contents(AREA_REGULATION_URL ."?". $select));
            foreach($area_regulations as $area_regulation){
                if(array_key_exists($area_regulation->regulationid, $this->regulations)){
                    
                } else {
                    $select="\$where=regulationid='". $area_regulation->regulationid ."'";
                    $regulations = json_decode(file_get_contents(REGULATION_URL ."?". $select));
                    foreach($regulations as $regulation){ 
                        $this->result[$key]->regulations[$regulation->regulationid] = $regulation;
                    }
                }
            }
            /*
            print("<HR><PRE>");
            print_r($item);
            print("</PRE>");
            exit();
            */
        }
    }

    function getCompleteAreaManager2($areamanagerid = 363){
        $this->areas = json_decode(file_get_contents(AREAUUID_URL ."?\$limit=10000"));
        // "where=(areaid%20>%20%27". $area ."%27%20AND%20areaid%20<%20%27". ($area + 1) ."%27)%20OR%20(areamanagerid%20=%20%27". $area .")"));
        
        foreach($this->areas as $area){
            if($area->areamanagerid == $areamanagerid || substr($area->areaid,0,strlen($areamanagerid)+1) == $areamanagerid ."_"){
                $this->result[] = $area;                
            }
        }
        
        foreach($this->result as $key => $area){
            $url = "https://npropendata.rdw.nl/parkingdata/v2/static/" . $area->uuid;
            $json = json_decode(file_get_contents($url));
            $this->result[$key]->details = $json;
        }
    }
    
    function checkJSONandDetails(){
        echo "<table>";
        echo "<tr><th>AreaID</th><th>Naam</th><th>url</th><th>Locatie</th><TH>#Tarieven</th></tr>";
        $json = json_decode(file_get_contents("npr(1).json"));
        //print("<PRE>");
        //print_r($json);
        //print("</PRE>"); exit();
        foreach($json as $area){
            if(substr($area->areaid,0,4) == "363_" || substr($area->areaid,0,3) == "GRV"){
                $url = "https://npropendata.rdw.nl/parkingdata/v2/static/". $area->uuid;
                $json_detail = json_decode(file_get_contents($url));
                print("<TR>");
                print("<TD>". $area->areaid ."</TD>");
                print("<TD>". $json_detail->parkingFacilityInformation->name ."</TD>");
                print("<TD>". $json_detail->parkingFacilityInformation->operator->name ."</TD>");
                print("<TD><a href='". $url ."'>". $area->uuid ."</a></TD>");
                print("<TD>". count($json_detail->parkingFacilityInformation->accessPoints) ."</TD>");
                print("<TD>". count($json_detail->parkingFacilityInformation->tariffs) ."</TD>");
                print("</TR>");
            }
        }
        echo "</table>";
    }

    function checkJSON(){
        echo "<table>";
        echo "<tr><th>areaid</th><th>Naam</th><th>url</th><th>Details</th><TH>#Tarieven</th></tr>";
        $json = json_decode(file_get_contents("npr(1).json"));
        //print("<PRE>");
        //print_r($json);
        //print("</PRE>"); exit();
        foreach($json as $area){
            if(substr($area->areaid,0,4) == "363_" || substr($area->areaid,0,3) == "GRV"){
                print("<TR><TD>". $area->areaid ."</TD>");
                print("<TD>" . $area->details->parkingFacilityInformation->name ."</TD>");
                print("<TD><a href='https://npropendata.rdw.nl/parkingdata/v2/static/". $area->uuid ."'>". $area->uuid ."</a></TD>");
                if(count($area->details->parkingFacilityInformation) > 0) print("<TD>Ja</TD>"); else print ("<TD>Nee</TD>");
                print("<TD>" . count($area->details->parkingFacilityInformation->tariffs)) ."</TD>";
            }
        }
        echo "</table>";
    }                  
                  
                                                                                                                                                           
    function output(){
        $f = fopen("npr(1).json","w");
        fwrite($f, json_encode($this->result));
        fclose($f);
        print json_encode($this->result);
    }
}

$npr = new NPR();
//$npr->getCompleteAreaManager(363);
//$npr->getParkingGarageByArea(363);
//$npr->getCompleteAreaManager2(363);
//$npr->output(); 
//$npr->checkJSON();
$npr->checkJSONandDetails();
?>

