<?php

class Mage_ProductsUpdates_Model_Cron
{
    public function import()
    {
		try{
			$url =  str_replace('index.php/','',Mage::getBaseUrl()).Mage::getStoreConfig("dev/automatic_updates_products_group/text_field");
			if($url=='' || strlen ($url)<3 || strpos($url, '.')===false){
				Mage::log('url address to CSV file in Automatic updating of products is fail', null, 'import.log', true);
				return 0;
			}
			$data = file_get_contents($url);
			if($data==null || $data==''){
				Mage::log('file is not exist', null, 'import.log', true);
				return 0;
			}
			$rows = explode("\n",$data);
			if(count($rows)==0 || $rows==null){
				Mage::log('rows is empty', null, 'import.log', true);
				return 0;
			}
			$delimiter = ";";
			$result = array();
			//Load rows from file
			foreach($rows as $row) {
				$tmp = str_getcsv($row);
				if(count($tmp)>=1){
					if(count($tmp[0])!=0){
						$result[] = explode($delimiter,str_getcsv($row)[0]);
					}
				}  
			}
			//Correct rows
			$headers = array_shift($result);
			$products_to_add = array();
			$product_tmp = array();
			foreach($result as $result_value) {
				foreach($headers as $header_key => $header) {
					$product_tmp[$header] = $result_value[$header_key];		
				}
				$products_to_add[] = $product_tmp;
			}
			$product_tmp=null;
			//Add new products
			Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
			//Load colors
			$attribute = Mage::getModel('eav/entity_attribute');
			$attribute->loadByCode( 4, 'color' );
			$valuesCollection = Mage::getResourceModel('eav/entity_attribute_option_collection')
			->setAttributeFilter( $attribute->getId() )
			->setStoreFilter( Mage_Core_Model_App::ADMIN_STORE_ID, false)
			->load();
			$valuesColors = array();
			foreach ($valuesCollection as $item) {
				$valuesColors[$item->getValue()] = $item->getId();
			}
		}
		catch (Exception $ex) {
			Mage::log($ex, null, 'import.log', true);
		}
		foreach($products_to_add as $product_) {
			try {
				//It checks are exist some product
				$sku = $product_['sku'];
				$id = Mage::getModel('catalog/product')->getIdBySku($sku);
				if (false !== $id) {
				   $product = Mage::getModel('catalog/product')->load($id);
				   $product->setName($product_['name']);
				   $product->setTypeId('simple');
				   $product->setPrice($product_['price']);
				   $product->setData('color', $valuesColors[$product_['color']]);
				   $product->save();
				}
				else {
				   $product = Mage::getModel('catalog/product');
				   $product->setSku($product_['sku']);
				   $product->setName($product_['name']);
				   $product->setTypeId('simple');
				   $product->setAttributeSetId(4);
				   $product->setWebsiteIDs(array(1));
				   $product->setStatus(Mage_Catalog_Model_Product_Status::STATUS_DISABLED);
				   $product->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH);
				   $product->setPrice($product_['price']); /* */	   
				   $product->setData('color', $valuesColors[$product_['color']]);
				   $product->setCreatedAt(strtotime('now'));
				   /* * */
				   $product->save();	   
				}
			}
			catch (Exception $ex) {
				Mage::log($ex, null, 'import.log', true);
			}
		}
		//Delete file
		try{
			unlink(Mage::getStoreConfig("dev/automatic_updates_products_group/text_field"));
		}
		catch (Exception $ex) {
			Mage::log($ex, null, 'import.log', true);
		}
    }
}