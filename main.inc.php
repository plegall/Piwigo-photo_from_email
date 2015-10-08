<?php
/*
Plugin Name: Photo from Email
Version: auto
Description: Fetch emails and add attached/embedded photos
Plugin URI: http://piwigo.org/ext/extension_view.php?eid=
Author: plg
Author URI: http://le-gall.net/pierrick
*/

if (!defined('PHPWG_ROOT_PATH'))
{
  die('Hacking attempt!');
}

global $prefixeTable;

// +-----------------------------------------------------------------------+
// | Define plugin constants                                               |
// +-----------------------------------------------------------------------+

defined('PFEMAIL_ID') or define('PFEMAIL_ID', basename(dirname(__FILE__)));
define('PFEMAIL_PATH' , PHPWG_PLUGINS_PATH.basename(dirname(__FILE__)).'/');
define('PFEMAIL_MAILBOXES_TABLE', $prefixeTable.'pfemail_mailboxes');
define('PFEMAIL_PENDINGS_TABLE', $prefixeTable.'pfemail_pendings');

include_once(PFEMAIL_PATH.'include/functions.inc.php');

// init the plugin
add_event_handler('init', 'pfemail_init');
/**
 * plugin initialization
 *   - check for upgrades
 *   - unserialize configuration
 *   - load language
 */
function pfemail_init()
{
  global $conf;

  // prepare plugin configuration
  // $conf['pfemail'] = safe_unserialize($conf['pfemail']);
}

add_event_handler('get_admin_plugin_menu_links', 'pfemail_admin_menu');
function pfemail_admin_menu($menu)
{
  global $page;

  $query = '
SELECT
    COUNT(*)
  FROM '.PFEMAIL_PENDINGS_TABLE.'
    JOIN '.IMAGES_TABLE.' ON image_id = id
  WHERE state = \'moderation_pending\'
;';
  $result = pwg_query($query);
  list($page['pfemail_nb_pendings']) = pwg_db_fetch_row($result);

  $name = 'Photo from Email';
  if ($page['pfemail_nb_pendings'] > 0)
  {
    $style = 'background-color:#666;';
    $style.= 'color:white;';
    $style.= 'padding:1px 5px;';
    $style.= 'border-radius:10px;';
    $style.= 'margin-left:5px;';

    $name.= '<span style="'.$style.'">'.$page['pfemail_nb_pendings'].'</span>';
  }

  array_push(
    $menu,
    array(
      'NAME' => $name,
      'URL'  => get_root_url().'admin.php?page=plugin-photo_from_email'
      )
    );

  return $menu;
}

add_event_handler('loc_end_page_tail', 'pfemail_call_check');
function pfemail_call_check()
{
  global $template, $conf;

  if (isset($conf['pfemail_last_check']))
  {
    // check emails maximum every minute
    if (strtotime($conf['pfemail_last_check']) > strtotime('-1 minute'))
    {
      return;
    }
  }
  
  $template->set_filename('check_email_js', realpath(PFEMAIL_PATH.'check_email.tpl'));
  $template->parse('check_email_js');
}

add_event_handler('ws_add_methods', 'pfemail_add_methods');
function pfemail_add_methods($arr)
{
  $service = &$arr[0];
  
  $service->addMethod(
    'pwg.pfemail.check',
    'ws_pfemail_check',
    array(),
    'Check photo from email'
    );

  $service->addMethod(
    'pwg.pfemail.validate',
    'ws_pfemail_validate',
    array(
      'image_id' => array('default' => null),
      ),
    'Validate photo from email',
    null,
    array('admin_only'=>true)
    );

  $service->addMethod(
    'pwg.pfemail.reject',
    'ws_pfemail_reject',
    array(
      'image_id' => array('default' => null),
      ),
    'Reject photo from email',
    null,
    array('admin_only'=>true)
    );

  $service->addMethod(
    'pfemail.mailbox.save',
    'ws_pfemail_mailbox_save',
    array(
      'id' => array('default' => null, 'type' => WS_TYPE_ID),
      'path' => array('default' => null),
      'login' => array('default' => null),
      'password' => array('default' => null),
      'category_id' => array('default' => null, 'type' => WS_TYPE_ID),
      'moderated' => array('default' => true, 'type' => WS_TYPE_BOOL),
      'pwg_token' => array(),
      ),
    'Add or edit a mailbox',
    null,
    array('admin_only'=>true)
    );

  $service->addMethod(
    'pfemail.mailbox.delete',
    'ws_pfemail_mailbox_delete',
    array(
      'id' => array('type' => WS_TYPE_ID),
      'pwg_token' => array(),
      ),
    'Delete a mailbox',
    null,
    array('admin_only'=>true)
    );

  $service->addMethod(
    'pfemail.mailbox.test',
    'ws_pfemail_mailbox_test',
    array(
      'path' => array('default' => null),
      'login' => array('default' => null),
      'password' => array('default' => null),
      'pwg_token' => array(),
      ),
    'Test mailbox connection settings',
    null,
    array('admin_only'=>true)
    );
}
?>
