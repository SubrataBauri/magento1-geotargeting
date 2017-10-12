<?php

class Dat_GeoTargeting_Helper_Data extends Mage_Core_Helper_Abstract {

    public function getDistanceBetweenPoints($prodLat, $prodLong) {
        // usage : Mage::helper('geotargeting')->getDistanceBetweenPoints($prodLat, $prodLong);
        $userLatLong = $this->getUserLatLong();
        if (!$userLatLong['userlat'] && !$userLatLong['userlong']) {
            return '';
        }
        return $this->getDistance($prodLat, $prodLong, $userLatLong['userlat'], $userLatLong['userlong']);
    }

    public function prepareProductCollection($userlat, $userlong) {
        $expectedMiles = $this->getExpectedMiles();
        $productCollection = Mage::getModel('catalog/product')->getCollection()
                ->addAttributeToSelect(array('lat', 'long'));
		Mage::log($productCollection->getSelectSql(true), null, 'mandira.log', true);
        $prodIds = array();
        if ($expectedMiles) {
            foreach ($productCollection as $eachProdCollection) {
                $prodLat = $eachProdCollection['lat'];
                $prodLong = $eachProdCollection['long'];
                $distanceMiles = $this->getDistance($prodLat, $prodLong, $userlat, $userlong);
                if ($distanceMiles < $expectedMiles) {
                    $prodId = $eachProdCollection->getId();
                    $prodIds[] = $prodId;
                }
            }
        }
        return $prodIds;
    }

    public function getDistance($prodLat, $prodLong, $userLat, $userLong) {
        // usage : Mage::helper('geotargeting')->getDistance($prodLat, $prodLong, $userLat, $userLong);
        // $theta = $prodLong - $userLong;
        // $miles = (sin(deg2rad($prodLat)) * sin(deg2rad($userLat))) + (cos(deg2rad($prodLat)) * cos(deg2rad($userLat)) * cos(deg2rad($theta)));
        // $miles = acos($miles);
        // $miles = rad2deg($miles);
        // $miles = $miles * 60 * 1.1515;
        //$kilometers = $miles * 1.609344;
		
		
		 $miles = (((acos(sin(( $userLat *pi()/180)) * 
            sin(($prodLat*pi()/180))+cos(($userLat *pi()/180)) * 
            cos(($prodLat*pi()/180)) * cos((($userLong - $prodLong)* 
            pi()/180))))*180/pi())*60*1.1515
        ) * 1.609344;
        return $miles;
    }

    public function getUserLatLong() {
        // usage : Mage::helper('geotargeting')->getUserLatLong();
        $userLat = Mage::getSingleton('core/session')->getLat();
        $userLong = Mage::getSingleton('core/session')->getLong();
        return array('userlat' => $userLat, 'userlong' => $userLong);
    }

    public function getExpectedMiles() {
        // usage : Mage::helper('geotargeting')->getExpectedMiles();
        return $expectedMiles = 1000;
    }

    public function getDiscountDateDifference($product_id) {
        // usage : Mage::helper('geotargeting')->getDiscountDateDifference($product_id);
        $product = Mage::getModel('catalog/product')->load($product_id);
        $diff = round(abs(time() - strtotime($product->getDiscStartDate())) / 86400);
//        Mage::log($diff, null, 'mandira.log', true);
        // difference less then 0 or greater then 7 pass 0
        if ($diff < 1 && $diff > 7) {
            return 0;
        } else {
            // else check the percentage on each
            $descArray[] = $product->getBreakfastDisc();
            $descArray[] = $product->getLunchDisc();
            $descArray[] = $product->getDinnerDisc();
            $descArray[] = $product->getLateDisc();


            $samePercentage = true;
            $lastvalue = -1;
            foreach ($descArray as $value) {
                $temp = explode(',', $value);
                // look for next 7 days
                for ($i = 0; $i < 7; $i++) {
                    if (isset($temp[($diff + $i)])) {
                        Mage::log('value set ' . $temp[($diff + $i)], null, 'sekhar.log', true);
                        if ($lastvalue != -1) {
                            if ($lastvalue != $temp[($diff + $i)]) {
                                $samePercentage = false;
                                break;
                            }
                        } else {
                            $lastvalue = $temp[($diff + $i)];
                        }
                    } else {
                        $samePercentage = false;
                        break;
                    }
                }
                if (!$samePercentage) {
                    break;
                }
            }

            if ($samePercentage) {
                return $lastvalue;
            } else {
                return 0;
            }
        }
    }

    public function getNext7Days($product, $days = 7, $dateFormat = "D m/d") {
        // usage : Mage::helper('geotargeting')->getNext7Days($product, $days);
        $start = new DateTime();
        $end = new DateTime();
        $end = $end->modify('+' . $days . ' days');
        $interval = new DateInterval('P1D');
        $daterange = new DatePeriod($start, $interval, $end);
        $next7Days;
        foreach ($daterange as $date) {
            $next7Days[] = date_format($date, $dateFormat);
        }

        $diff = round(floor(abs(time() - strtotime($product->getDiscStartDate())) / 86400));
        $descArray['Breakfast'] = $product->getBreakfastDisc();
        $descArray['Lunch'] = $product->getLunchDisc();
        $descArray['Dinner'] = $product->getDinnerDisc();
        $descArray['Late'] = $product->getLateDisc();

        $samePercentage = true;
        $lastvalue = -1;
        $returnArray;

        $counter = 0;
        foreach ($next7Days as $value) {
            $Breakfast = explode(',', $descArray['Breakfast']);
            $Lunch = explode(',', $descArray['Lunch']);
            $Dinner = explode(',', $descArray['Dinner']);
            $Late = explode(',', $descArray['Late']);

            $returnArray[$value] = array(
                $Breakfast[$diff + $counter],
                $Lunch[$diff + $counter],
                $Dinner[$diff + $counter],
                $Late[$diff + $counter],
            );

            $counter++;
        }

        return $returnArray;
    }

    /**
     * check if the product id in customer's favourite list
     * @param type $productid
     * @param type $customer_id
     * @return boolean
     */
    public function isCustomerFav($productid, $customer_id) {
        // usage : Mage::helper('geotargeting')->isCustomerFav($product_id, $customer_id);
        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $tableName = Mage::getSingleton("core/resource")->getTableName('customer_favourite');
        $select = $db->select()->from($tableName)
                ->where('customer_id=?', $customer_id)
                ->where('product_id = ?', $productid);
        $result = $db->fetchRow($select);
        if (is_array($result)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Return customer's prefrence products id
     * @param type $customer_id
     * @return array
     */
    public function getCustomerPrefrence($customer_id) {
        // usage : Mage::helper('geotargeting')->getCustomerPrefrence($customer_id)
        $db = Mage::getSingleton('core/resource')->getConnection('core_read');
        $tableName = Mage::getSingleton("core/resource")->getTableName('customer_preferences');
        $select = $db->select()->from($tableName)
                ->where('customer_id=?', $customer_id);
        
        $result = $db->fetchAll($select);
        $data = array();
        foreach ($result as $eachRow) {
            $data[] = $eachRow['value_id'];
        }
        return $data;
    }
}
?>
