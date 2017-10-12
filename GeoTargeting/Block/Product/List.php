<?php

class Dat_GeoTargeting_Block_Product_List extends Mage_Catalog_Block_Product_List {

    protected function _getProductCollection() {
		if($this->getRequest()->getControllerName() == 'advanced'){
			return parent::_getProductCollection();
		}
		
		if (Mage::helper('geotargeting')->getDistanceBetweenPoints(1, 1) != '') {
			
			$allProdIds = $this->prepareProductCollection();
			if ($this->_productCollection) {
				
				$randNumber = rand(1, 50000000000000);
				$userLatLong = Mage::helper('geotargeting')->getUserLatLong();
				if ($userLatLong['userlat'] && $userLatLong['userlong']) {
					$this->_productCollection->getSelect()->join(array('lattable'.$randNumber => 
					new Zend_Db_Expr('('.'select 
						ROUND(6371 * 2 * ASIN(SQRT(
									POWER(SIN((`lat` - abs('.$userLatLong['userlat'].' )) * pi()/180 / 2),
									2) + COS(`lat` * pi()/180 ) * COS(abs('.$userLatLong['userlat'].' ) *
									pi()/180) * POWER(SIN((`long` - '.$userLatLong['userlat'].' ) *
									pi()/180 / 2), 2) )) , 2)

						as distance' . $randNumber . ', entity_id from catalog_product_entity'
					.')')), 'e.entity_id = lattable'.$randNumber.'.entity_id', array('lattable'.$randNumber.'.distance'.$randNumber));
					$this->_productCollection->getSelect()->order('distance' . $randNumber, 'ASC');
				}				
				
				// location wise product list view
				//$this->_productCollection->addFieldToFilter('entity_id', array('in' => $allProdIds)); 
				
				$this->_productCollection->addAttributeToSelect(array('lat', 'long')); 
			}
		}
        return parent::_getProductCollection();
    }

    public function prepareProductCollection() {
        $expectedMiles = Mage::helper('geotargeting')->getExpectedMiles();
        $productCollection = Mage::getModel('catalog/product')->getCollection()
                ->addAttributeToSelect(array('lat', 'long'));
        $prodIds = array();
        if ($expectedMiles) {

            foreach ($productCollection as $eachProdCollection) {
                $prodLat = $eachProdCollection['lat'];
                $prodLong = $eachProdCollection['long'];
                $distanceMiles = Mage::helper('geotargeting')->getDistanceBetweenPoints($prodLat, $prodLong);
                if ($distanceMiles < $expectedMiles) {
                    $prodId = $eachProdCollection->getId();
                    $prodIds[] = $prodId;
                }
            }
        }
        return $prodIds;
    }

}
