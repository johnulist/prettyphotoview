<?php
// This checks for the existence of a PHP constant, and if it doesn't exist, it quits. The sole purpose of this is to prevent visitors to load this file directly.
if ( !defined( '_PS_VERSION_' ) )
	exit;
/**
 * Module prettyPhotoView
 * uses JavaScript from http://www.no-margin-for-errors.com/projects/prettyphoto-jquery-lightbox-clone/
 * Creation author: Andrija Jambrovic
 * management inspired by module jGalleryView2 http://www.prestashop.com/forums/topic/49128-module-jgalleryview-version-2/
 *
 **/

define( 'THUMBNAIL_IMAGE_MAX_WIDTH', 90 );
require_once(dirname(__FILE__).'/phpThumb/ThumbLib.inc.php');

class PrettyPhotoView extends Module
{
	protected $maxImageSize = 307200;
	protected $imageDir = 'slides/';
	protected $thumbnailDir = 'slides/thumbnails/';

	protected $_defaultLanguage;
	protected $_languages;
	protected $_xml;

	public function __construct()
	{
		$this->name = 'prettyPhotoView';
		$this->tab = 'Home';
		$this->version = '1.0.0'; /* compatible PS 1.2.x, 1.3.x */
		$this->author = 'Andrija Jambrovic';

		parent::__construct();

		$this->page = basename(__FILE__,'.php');
		$this->displayName = $this->l('prettyPhoto View');
		$this->description = $this->l('Add a prettyPhoto View on your page.');

		/* initiate values for translation */
		$this->_defaultLanguage = intval(Configuration::get('PS_LANG_DEFAULT'));
		$this->_languages = Language::getLanguages();
		/* put xml in cache */
		$this->_xml = $this->_getXml();
	}
	
	private function copyControllerFilePaths(){
		$controllerFilePath = "PrettyPhotoViewController.php";
		$controllerFullPath = _PS_MODULE_DIR_.$this->name.'/'.$controllerFilePath;
		if (!@copy($controllerFullPath, _PS_CONTROLLER_DIR_.$controllerFilePath)) {
			return false;
		}
		
		$galleryFilePath = "/prettyPhotoGallery.php";
		$galleryFullPath = _PS_MODULE_DIR_.$this->name.$galleryFilePath;
		if (!@copy($galleryFullPath, _PS_ROOT_DIR_.$galleryFilePath)) {
			return false;
		}
		return true;
	}
	
	public function install()
	{
		if (!parent::install()
				|| !$this->installModuleTab('PrettyPhotoTab', array(1=>'prettyPhoto gallery', 2=>'Mon onglet tutoriel'), 7))
			return false;
		
		if (!$this->copyControllerFilePaths()){
			return false;
		}
		return true;
	}

	private function installModuleTab($tabClass, $tabName, $idTabParent)
	{
		@copy(_PS_MODULE_DIR_.$this->name.'/logo.gif', _PS_IMG_DIR_.'t/'.$tabClass.'.gif');
		$tab = new Tab();
		$tab->name = $tabName;
		$tab->class_name = $tabClass;
		$tab->module = $this->name;
		$tab->id_parent = $idTabParent;
		if(!$tab->save())
			return false;
		return true;
	}

	public function uninstall(){
		if (!parent::uninstall() || !$this->uninstallModuleTab('PrettyPhotoTab')){
			return false;
		}
		return true;
	}

	private function uninstallModuleTab($tabClass)
	{
		$idTab = Tab::getIdFromClassName($tabClass);
		if($idTab != 0)
		{
			$tab = new Tab($idTab);
			$tab->delete();
			return true;
		}
		return false;
	}

	public function getContent()
	{
		$this->_html = '<h2>'.$this->displayName.' - '.$this->l('version').' '.$this->version.'</h2>';
		$this->_html .= $this->_postProcess();
		$this->_html .= $this->_displayForm();
		$this->_html .= $this->_displayCredits();
		return $this->_html;
	}
	
	protected function putContent($xml_data, $key, $field)
	{
		$field = stripslashes(htmlspecialchars($field,ENT_QUOTES,"UTF-8"));
		if (!$field)
			return 0;
		$xml_data .= ("\n\t\t<".$key.">".$field."</".$key.">\n");
		return 1;
	}

	private function _postProcess()
	{
		if (Tools::getValue('itemToDelete')){
			$idToDelete = (int)preg_replace("/[^0-9]/","", Tools::getValue('itemToDelete'));
			$slideFilePath = dirname(__FILE__).'/'.(string)$this->_xml->item[$idToDelete]->img;
			$thumbFilePath = dirname(__FILE__).'/'.(string)$this->_xml->item[$idToDelete]->thumb;
			if (!( unlink($slideFilePath) and unlink($thumbFilePath) )){
				return $this->displayError($this->l('An error occurred during image deletion'));
			}
		}
		if (Tools::isSubmit('submitUpdate') OR Tools::getValue('itemToDelete'))
		{

			$newXml = '<'.'?'.'xml version="1.0" encoding="utf-8" '.'?'.'>';
			$newXml .= "\n<items>";
			$i = 0;
			$this->displayName = "";
			foreach (array_reverse(Tools::getValue('item') ) AS $item)
			{
				$newXml .= "\n\t<item>";
				$emptyCounter = 0;
				// gets all name/value combinations of input fields in the back office form
				foreach ($item AS $key => $field)
				{
					$addItemToXMLSuccess = $this->putContent(&$newXml, $key, $field);
					if (!$addItemToXMLSuccess){
						$emptyCounter++;
						
					}
						
				}
				if ($emptyCounter == 4){
					return $this->displayError($this->l('Error, you need to have at least one value for item #'.($i+1)));
				}
				$itemHasProperties = isset($_FILES['item_'.$i.'_img']);
				$itemHasTempNamePropertySet = isset($_FILES['item_'.$i.'_img']['tmp_name']);
				$itemHasTempNameValue = !empty($_FILES['item_'.$i.'_img']['tmp_name']);
				$errorIndex = $_FILES["file"]["error"];
				if ($itemHasProperties AND $itemHasTempNamePropertySet AND $itemHasTempNameValue)
				{
					Configuration::set('PS_IMAGE_GENERATION_METHOD', 1);
					if ($error = checkImage($_FILES['item_'.$i.'_img'], $this->maxImageSize))
						return $error;
					if (!$tmpName = tempnam(_PS_TMP_IMG_DIR_, 'PS') OR !move_uploaded_file($_FILES['item_'.$i.'_img']['tmp_name'], $tmpName))
						return false;
					$srcDir = dirname(__FILE__).'/'.$this->imageDir.'slide'.$i.'.jpg';
					if (!imageResize($tmpName, $srcDir))
						return $this->displayError($this->l('An error occurred during the image upload.'));
					$destDir = dirname(__FILE__).'/'.$this->thumbnailDir.'thumb'.$i.'.jpg';					
					try
					{
						$thumb = PhpThumbFactory::create($srcDir);
						$thumb->adaptiveResize(120, 120);
						$thumb->save($destDir);
					}
					catch (Exception $e)
					{
						return $this->displayError($this->l('An error occurred during thumbnail generation.'));
					}
					unlink($tmpName);
				}
				$addSlideToXMLSuccess = $this->putContent(&$newXml, 'img', $this->imageDir.'slide'.$i.'.jpg');
				$addThumbToXMLSuccess = $this->putContent(&$newXml, 'thumb', $this->thumbnailDir.'thumb'.$i.'.jpg');
				if (!($addSlideToXMLSuccess && $addThumbToXMLSuccess)){
					return $this->displayError($this->l('An error occurred during image saving'));
				}
				$newXml .= "\n\t</item>\n";
				$i++;
			}
			$newXml .= "\n</items>\n";

			if ($fd = @fopen(dirname(__FILE__).'/'.$this->getXmlFilename(), 'w'))
			{
				if (!@fwrite($fd, $newXml))
					return $this->displayError($this->l('Unable to write to the editor file.'));
				if (!@fclose($fd))
					return $this->displayError($this->l('Can\'t close the editor file.'));
			}
			else
				return $this->displayError($this->l('Unable to update the editor file. Please check the editor file\'s writing permissions.'));

			/* refresh XML */
			$this->_xml = $this->_getXml();
			return $this->displayConfirmation($this->l('Items updated.'));
		}
	}

	static private function getXmlFilename()
	{
		return 'data.xml';
	}

	private function _getXml()
	{
		$file = dirname(__FILE__).'/'.$this->getXmlFilename();
		if (file_exists($file))
		{
			if ($xml = @simplexml_load_file($file))
			{
				return $xml;
			}
		}
		return false;
	}

	public function _getFormItem($i, $isFirst, $categories, $itemNumber)
	{
		$divLangName = 'title'.$i.'&curren;cpara'.$i;
		$output = 
		'<div class="item" id="item'.$i.'">
			<h3>'.$this->l('Item #').($itemNumber).'</h3>';
			$output .=
			'<label>'.$this->l('Title').'</label>
			<div class="margin-form">';
				foreach ($this->_languages as $language)
				{
					$output .= '
					<div id="title'.$i.'_'.$language['id_lang'].'" style="display:'.($language['id_lang'] == $this->_defaultLanguage ? 'block' : 'none').';float: left;">
					<input type="text" name="item['.$i.'][title_'.$language['id_lang'].']" id="item_title_'.$i.'_'.$language['id_lang'].'" size="64" value="'.(isset($this->_xml->item[$i]->{'title_'.$language['id_lang']}) ? stripslashes(htmlspecialchars($this->_xml->item[$i]->{'title_'.$language['id_lang']})) : '').'" />
					</div>';
				}
				$output .= $this->displayFlags($this->_languages, $this->_defaultLanguage, $divLangName , 'title'.$i, true);
				$output .= '<div class="clear"></div>
			</div>';
			
			$output .=
			'<label>'.$this->l('Description').'</label>
			<div class="margin-form">';
				foreach ($this->_languages as $language)
				{
					$output .= '
					<div id="cpara'.$i.'_'.$language['id_lang'].'" style="display: '.($language['id_lang'] == $this->_defaultLanguage ? 'block' : 'none').';float: left;">
					<textarea cols="64" rows="3" name="item['.$i.'][text_'.$language['id_lang'].']" id="item_text_'.$i.'_'.$language['id_lang'].'">'.(isset($this->_xml->item[$i]-> {'text_'.$language['id_lang']}) ? stripslashes(htmlspecialchars($this->_xml->item[$i]-> {'text_'.$language['id_lang']})) : '').'</textarea>
					</div>';
				}
				$output .= $this->displayFlags($this->_languages, $this->_defaultLanguage, $divLangName , 'cpara'.$i, true);
				$output .= '<div class="clear"></div>
			</div>';
		
			$output .=
			'<label>'.$this->l('Category').'</label>
			<div class="margin-form">
				<input type="text" id="item_'.$i.'" name="item['.$i.'][category]" size="64" value="'.(isset($this->_xml->item[$i]->category) ? stripslashes(htmlspecialchars($this->_xml->item[$i]->category)) : '').'" />
				<p style="clear: both"></p>
			</div>';
			
			$output .=
			'<label>'.$this->l('Category selection').'</label>
			<div class="margin-form">
				<select onchange="setCategorySelection(this, \'#item_'.$i.'\')">
					<option>---</option>';
					foreach ($categories as $category){
							$output.='<option>'.$category.'</option>';
					}
			$output .='';
			$output .=
				'</select>
				<p style="clear: both"></p>
			</div>';
		
			$output .= 
			'<label>'.$this->l('Picture').'</label>
			<div class="margin-form">';
				if (isset($this->_xml->item[$i]->thumb)) {
					$output .= '<img src="'.$this->_path.$this->_xml->item[$i]->thumb.'" alt="" title="" style="" /><br />';
				}
			$output .= '<input type="file" name="item_'.$i.'_img" />
				<p style="clear: both"></p>
			</div>';
			
			$output .=
			'<label>'.$this->l('Product attribution link').'</label>
			<div class="margin-form">
				<input type="text" name="item['.$i.'][url]" size="64" value="'.(isset($this->_xml->item[$i]->url) ? stripslashes(htmlspecialchars($this->_xml->item[$i]->url)) : '').'" />
				<p style="clear: both"></p>
			</div>';
			
			$output .=
			'<div class="clear pspace"></div>
			<hr/>
		</div>';
		return $output;
	}

	public function _displayForm()
	{

		$output = '';

		$xml = false;
		if (!$xml = $this->_xml)
			$output .= $this->displayError($this->l('Your data file is empty.'));

		$output .= '
		<script type="text/javascript">
		function removeDiv(id){
			$("#"+id).fadeOut("slow");
			$("#"+id).remove();
	
			/* get some values from elements on the page: */
			$( "#itemToDelete" ).val(id);
			var form = $( "#imageGalleryForm" );
			//var formSubmit = form.serialize() + "&itemToDelete="+id;
			var url = form.attr( \'action\' );
			form.submit();	
		}
		function cloneIt(cloneId) {
			var currentDiv = $("#"+cloneId);
			var id = $(currentDiv).attr("id").match(/[0-9]+/gi);
			var nextId = parseInt(id) + 1;
			$.get("'._MODULE_DIR_.$this->name.'/ajax.php?id="+nextId, function(data) {
				$(currentDiv).before(data);
			});

		}
		function setCategorySelection(selectObject, itemID){
			  var selectedOption = selectObject.options[selectObject.selectedIndex];
			  $(itemID).val(selectedOption.value);
		}
		</script>
		<form method="post" action="'.$_SERVER['REQUEST_URI'].'" enctype="multipart/form-data" id="imageGalleryForm">
		<input type="hidden" id="itemToDelete" value="" name="itemToDelete" />
		<fieldset style="width: 900px;">
		<legend><img src="'.$this->_path.'logo.gif" alt="" title="" />'.$this->displayName.'</legend>';
		
		//$i = count($xml->item)-1;
		$counter = count($xml->item)-1;
		$output .= '
		<div class="margin-form clear">
		<a href="javascript:{}" onclick="removeDiv(\'item'.$counter.'\')" style="color:#EA2E30"><img src="'._PS_ADMIN_IMG_.'delete.gif" alt="'.$this->l('delete').'" />'.$this->l('Delete this item').'</a>
		<a id="clone'.$counter.'" href="javascript:cloneIt(\'item'.$counter.'\')" style="color:#488E41"><img src="'._PS_ADMIN_IMG_.'add.gif" alt="'.$this->l('add').'" /><b>'.$this->l('Add a new item').'</b></a>
		<input type="submit" name="submitUpdate" value="'.$this->l('Save').'" class="button" />
		</div>';

		foreach ($xml->item as $item)
		{
			$isFirst = ((count($xml->item) == $counter+1) ? true : false);
			$output .= $this->_getFormItem($counter, $isFirst, $this->getCategories(), $counter+1);
			$counter--;
		}
		$output .= '
		</fieldset>
		</form>';
		return $output;
	}

	private function _displayCredits()
	{
		$sf_url = 'https://sourceforge.net/projects/psjgalleryview/';
		$ps_url = 'http://www.prestashop.com/forums/viewthread/48180/';
		$output = '
		<br class="clear" /><br/>
		<form action="#" method="post" style="width: 95%;">
		<fieldset class="widthfull">
		<legend>'.$this->l('Credits').'</legend>
		<p>
		'.$this->l('This module is hosted on').'
		<a href="'.$sf_url.'"><b>source forge</b> - '.$sf_url.'</a>.
		</p>
		<p>
		<a href="'.$ps_url.'" style="text-decoration: underline;">
		'.$this->l('Please send feedback, bugs or other stuff on related thread in PrestaShop forum.').'
		</a>
		</p>
		</fieldset>
		</form>';
		return $output;
	}


	public function setMedia()
	{
		parent::setMedia();
		Tools::addCSS(_PS_CSS_DIR_.'prettyPhoto.css');
		Tools::addJS(_PS_JS_DIR_.'jquery/jquery.prettyPhoto.js');
	}

	public function displayContent()
	{
		parent::displayContent();
		$file = dirname(__FILE__).'/data.xml';;
		if (file_exists($file))
		{
			$xml = @simplexml_load_file($file);

			global $smarty;
			$smarty->assign(array(
					'xml' => $xml,
					'title' => 'title_1',
					'text' => 'text_1'
			));
			self::$smarty->display(_PS_THEME_DIR_.'gallerifficGallery.tpl');
		}
			
	}
	
	public function getCategories(){
		foreach ($this->_xml->item AS $item){
			$categoryIsSet = isset($item->category);
			if ($categoryIsSet){
				$categories[(string)$item->category]=(string)$item->category;
			}
		}
		return $categories;
	}

}

?>