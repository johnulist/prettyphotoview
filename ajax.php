<?php
require_once(realpath(dirname(__FILE__).'/../../').'/config/config.inc.php');
require_once(dirname(__FILE__).'/prettyPhotoView.php');

if (!$id = Tools::getValue('id'))
	die();

$module = new PrettyPhotoView();
$categories = $module->getCategories();
echo $module->_getFormItem(intval($id), true, $categories, intval($id)+1);

?>