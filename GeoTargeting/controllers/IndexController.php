<?php

class Dat_GeoTargeting_IndexController extends Mage_Core_Controller_Front_Action {

    public function indexAction() {
        if (!empty($_POST['latitude']) && !empty($_POST['longitude'])) {

            Mage::getSingleton('core/session')->setLat($_POST['latitude']);
            Mage::getSingleton('core/session')->setLong($_POST['longitude']);
            //Send request and receive json data by latitude and longitude
            $url = 'http://maps.googleapis.com/maps/api/geocode/json?latlng=' . trim($_POST['latitude']) . ',' . trim($_POST['longitude']) . '&sensor=false';
            $json = @file_get_contents($url);
            $data = json_decode($json);
            $status = $data->status;
            if ($status == "OK") {
                //Get address from json data
                $location = $data->results[0]->formatted_address;
            } else {
                $location = '';
            }
            //Print address
            $this->getResponse()->setBody($location);
        }
    }

}
