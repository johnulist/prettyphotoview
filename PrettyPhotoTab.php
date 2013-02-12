<?php
include_once(PS_ADMIN_DIR.'/../classes/AdminTab.php');
require_once(dirname(__FILE__).'/prettyPhotoView.php');

class PrettyPhotoTab extends AdminTab
{
  private $module = 'prettyPhotoView';

  public function display()
  {
  	$module = new PrettyPhotoView();
  	echo $module->getContent();
  }
}
?>