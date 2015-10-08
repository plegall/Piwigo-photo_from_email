<?php
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

class photo_from_email_maintain extends PluginMaintain
{
  private $installed = false;

  function __construct($plugin_id)
  {
    parent::__construct($plugin_id);
  }

  function install($plugin_version, &$errors=array())
  {
    global $conf, $prefixeTable;
    
    $query = '
CREATE TABLE IF NOT EXISTS '.$prefixeTable.'pfemail_mailboxes (
  id int(11) NOT NULL AUTO_INCREMENT,
  path varchar(255) NOT NULL,
  login varchar(255) NOT NULL,
  password varchar(255) NOT NULL,
  category_id smallint(5) unsigned DEFAULT NULL,
  moderated enum(\'true\',\'false\') NOT NULL DEFAULT \'true\',
  PRIMARY KEY (id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8
;';
    pwg_query($query);

    $query = '
CREATE TABLE IF NOT EXISTS '.$prefixeTable.'pfemail_pendings (
  image_id mediumint(8) unsigned NOT NULL,
  state varchar(255) NOT NULL,
  added_on datetime NOT NULL,
  validated_by mediumint(8) unsigned DEFAULT NULL,
  from_name varchar(255) DEFAULT NULL,
  from_address varchar(255) DEFAULT NULL,
  subject varchar(255) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8
;';
    pwg_query($query);

    $result = pwg_query('SHOW COLUMNS FROM `'.GROUPS_TABLE.'` LIKE "pfemail_notify";');
    if (!pwg_db_num_rows($result))
    {
      pwg_query('ALTER TABLE '.GROUPS_TABLE.' ADD pfemail_notify enum(\'true\', \'false\') DEFAULT \'false\';');
    }
    
    $this->installed = true;
  }

  function activate($plugin_version, &$errors=array())
  {
    global $prefixeTable;
    
    if (!$this->installed)
    {
      $this->install($plugin_version, $errors);
    }
  }

  function update($old_version, $new_version, &$errors=array())
  {
    $this->install($new_version, $errors);
  }
  
  function deactivate()
  {
  }

  function uninstall()
  {
    global $prefixeTable;
  
    $query = 'DROP TABLE '.$prefixeTable.'pfemail_mailboxes;';
    pwg_query($query);
    
    $query = 'DROP TABLE '.$prefixeTable.'pfemail_pendings;';
    pwg_query($query);
    
    // delete configuration
    pwg_query('DELETE FROM `'. CONFIG_TABLE .'` WHERE param IN ("pfemail_last_check");');
  }
}
?>
