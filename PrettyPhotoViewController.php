<?php
define('MODULE', 'prettyPhotoView/');
define('MODULE_HOME', _MODULE_DIR_.MODULE);
define( 'ALL_CATEGORIES', 'All Galleries' );
class PrettyPhotoViewControllerCore extends FrontController
{
  
  public function setMedia()
  {
      parent::setMedia();
	  Tools::addCSS(MODULE_HOME.'css/prettyPhoto.css');
	  Tools::addJS(MODULE_HOME.'js/jquery.prettyPhoto.js');
  }
 
  public function displayContent()
  {
        parent::displayContent();
		$file = _PS_MODULE_DIR_.MODULE.'data.xml';
		if (file_exists($file))
		{
			$xml = @simplexml_load_file($file);
			$selectedCategory = ALL_CATEGORIES;
			$categories = array(ALL_CATEGORIES => ALL_CATEGORIES);
			$shouldFilterByCategory = isset($_GET["categorySelector"]) && $_GET["categorySelector"] != ALL_CATEGORIES;
			if ($shouldFilterByCategory){
				$selectedCategory = $_GET["categorySelector"];
			}
			foreach ($xml->item AS $item){
				$categoryIsSet = isset($item->category);
				if ($categoryIsSet){
					$categories[(string)$item->category]=(string)$item->category;
				}
				$shouldDisplayCurrentItem = $categoryIsSet && $item->category == $_GET["categorySelector"];
				if (!$shouldFilterByCategory || $shouldFilterByCategory && $shouldDisplayCurrentItem){
					$displayedItems[] = $item;
				}
			}

			global $smarty;
			$smarty->assign(array(
				'xml' => $displayedItems,
				'title' => 'title_1',
				'text' => 'text_1',
				'url' => 'url',
				'path' => MODULE_HOME,
				'categories' => $categories,
				'selectedCategory' => $selectedCategory
			));
			self::$smarty->display(_PS_MODULE_DIR_.MODULE.'PrettyPhotoView.tpl');
	}
			 
  }
}
?>