<?php
// +-----------------------------------------------------------------------+
// | Piwigo - a PHP based picture gallery                                  |
// +-----------------------------------------------------------------------+
// | Copyright(C) 2008-2011 Piwigo Team                  http://piwigo.org |
// | Copyright(C) 2003-2008 PhpWebGallery Team    http://phpwebgallery.net |
// | Copyright(C) 2002-2003 Pierrick LE GALL   http://le-gall.net/pierrick |
// +-----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify  |
// | it under the terms of the GNU General Public License as published by  |
// | the Free Software Foundation                                          |
// |                                                                       |
// | This program is distributed in the hope that it will be useful, but   |
// | WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU      |
// | General Public License for more details.                              |
// |                                                                       |
// | You should have received a copy of the GNU General Public License     |
// | along with this program; if not, write to the Free Software           |
// | Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, |
// | USA.                                                                  |
// +-----------------------------------------------------------------------+

if( !defined("PHPWG_ROOT_PATH") )
{
  die ("Hacking attempt!");
}

include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');
include_once(PHPWG_ROOT_PATH.'include/functions_picture.inc.php');

$admin_base_url = get_root_url().'admin.php?page=plugin-showcase_admin-pendings';

if (isset($_GET['start']) and is_numeric($_GET['start']))
{
  $page['start'] = $_GET['start'];
}
else
{
  $page['start'] = 0;
}

$page['nb_pendings_per_page'] = 10;

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+

check_status(ACCESS_ADMINISTRATOR);

// +-----------------------------------------------------------------------+
// | template init                                                         |
// +-----------------------------------------------------------------------+

$template->set_filenames(
  array(
    'plugin_admin_content' => dirname(__FILE__).'/admin_pendings.tpl'
    )
  );

// +-----------------------------------------------------------------------+
// | pending photos list                                                   |
// +-----------------------------------------------------------------------+

$list = array();

$pending_ids = pfemail_get_pending_ids();
if (empty($pending_ids))
{
  $pending_ids[] = -1;
}

$query = '
SELECT
    id,
    path,
    date_creation,
    date_available,
    name,
    comment,
    author,
    file,
    comment,

    image_id,
    state,
    from_name,
    from_address,
    subject
  FROM '.IMAGES_TABLE.'
    JOIN '.PFEMAIL_PENDINGS_TABLE.' ON id = image_id

  WHERE image_id IN ('.implode(',', $pending_ids).')
  ORDER BY image_id DESC
  LIMIT '.$page['start'].', '.$page['nb_pendings_per_page'].'
;';
$result = pwg_query($query);
$rows = array();
$image_ids = array();
while ($row = pwg_db_fetch_assoc($result))
{
  array_push($rows, $row);
  array_push($image_ids, $row['id']);
}

$template->assign(
  array(
    'F_ACTION' => $admin_base_url,
    )
  );

foreach ($rows as $row)
{
  $thumb = DerivativeImage::thumb_url(
    array(
      'id'=>$row['image_id'],
      'path'=>$row['path'],
      )
    );

  $template->append(
    'photos',
    array(
      'U_EDIT' => get_root_url().'admin.php?page=plugin-showcase_admin-photo&amp;image_id='.$row['image_id'],
      'ID' => $row['image_id'],
      'TN_SRC' => $thumb,
      'WEBSIZE_SRC' => $row['path'],
      'ADDED_BY' => $row['author'],
      'ADDED_ON' => format_date($row['date_available'], true),
      'NAME' => $row['name'],
      'FILE' => $row['file'],
      'DATE_CREATION' => empty($row['date_creation']) ? l10n('N/A') : format_date($row['date_creation']),
      'DESCRIPTION' => $row['comment'],
      'FROM' => @$row['from_name'].' &lt;'.$row['from_address'].'&gt;',
      )
    );
}

// +-----------------------------------------------------------------------+
// |                            navigation bar                             |
// +-----------------------------------------------------------------------+

$template->assign(
  'navbar',
  create_navigation_bar(
    get_root_url().'admin.php'.get_query_string_diff(array('start', 'action','showcase_id')),
    count($pending_ids),
    $page['start'],
    $page['nb_pendings_per_page']
    )
  );


// +-----------------------------------------------------------------------+
// | sending html code                                                     |
// +-----------------------------------------------------------------------+

$template->assign_var_from_handle('ADMIN_CONTENT', 'plugin_admin_content');
?>