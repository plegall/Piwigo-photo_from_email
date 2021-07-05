<?php
// +-----------------------------------------------------------------------+
// | Piwigo - a PHP based picture gallery                                  |
// +-----------------------------------------------------------------------+
// | Copyright(C) 2008-2015 Piwigo Team                  http://piwigo.org |
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

function pfemail_check_accounts()
{
  global $conf, $user;

  conf_update_param('pfemail_last_check', date('Y-m-d H:i:s'));
  
  require_once(PFEMAIL_PATH.'include/ImapMailbox.php');

  $image_ids = array();

  $query = '
SELECT
    *
  FROM '.PFEMAIL_MAILBOXES_TABLE.'
;';
  $accounts = query2array($query);
  
  foreach ($accounts as $account)
  {
    $mailbox = new ImapMailbox(
      $account['path'],
      $account['login'],
      $account['password'],
      $conf['upload_dir'].'/buffer',
      'utf-8'
      );

    $mails = array();

    // Get some mail
    $mailsIds = $mailbox->searchMailBox('UNSEEN');
    
    if (!$mailsIds)
    {
      continue; // check next email account
    }

    $mailId = reset($mailsIds);
    $mail = $mailbox->getMail($mailId);
    $attachments = $mail->getAttachments();

    include_once(PHPWG_ROOT_PATH.'admin/include/functions_upload.inc.php');

    
    foreach ($attachments as $attachment)
    {
      $extension = strtolower(get_extension($attachment->{'name'}));
      if (!in_array($extension, $conf['picture_ext']))
      {
        // the file has been downloaded, we have to remove it now
        unlink($attachment->{'filePath'});
        continue;
      }

      $moderate = get_boolean($account['moderated']);
      
      $image_id = add_uploaded_file(
        $attachment->{'filePath'},
        stripslashes($attachment->{'name'}), // function add_uploaded_file will secure before insert
        array($account['category_id']),
        ($moderate ? 16 : 0), // level
        null // image_id = not provided, this is a new photo
        );

      // the photo is added by nobody (using the current user may make the
      // photo editable by her with Admin Tools...)
      single_update(
        IMAGES_TABLE,
        array(
          'added_by' => null,
          'name' => pfemail_clean_email_subject($mail->subject),
          ),
        array('id' => $image_id)
        );

      $state = 'auto_validated';
      if ($moderate)
      {
        $state = 'moderation_pending';
      }

      list($dbnow) = pwg_db_fetch_row(pwg_query('SELECT NOW();'));
        
      single_insert(
        PFEMAIL_PENDINGS_TABLE,
        array(
          'image_id' => $image_id,
          'state' => $state,
          'added_on' => $dbnow,
          'from_name' => $mail->fromName,
          'from_address' => $mail->fromAddress,
          'subject' => $mail->subject,
          )
        );
      
      $image_ids[] = $image_id;
    }
  }

  if (count($image_ids) > 0)
  {
    include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');
    invalidate_user_cache();

    // let's notify administrators
    $query = '
SELECT id
  FROM '.GROUPS_TABLE.' 
  WHERE pfemail_notify = "true"
;';
    $group_ids = query2array($query, null, 'id');

    if (count($group_ids) > 0)
    {
      include_once(PHPWG_ROOT_PATH.'include/functions_mail.inc.php');

      $thumb_urls = array();
    
      // force $conf['derivative_url_style'] to 2 (script) to make sure we
      // will use i.php?/upload and not _data/i/upload because you don't
      // know when the cache will be flushed
      $previous_derivative_url_style = $conf['derivative_url_style'];
      $conf['derivative_url_style'] = 2;

      $query = '
SELECT
    id,
    path
  FROM '.IMAGES_TABLE.'
  WHERE id IN ('.implode(',', $image_ids).')
;';
      $result = pwg_query($query);
      while ($row = pwg_db_fetch_assoc($result))
      {
        $thumb = DerivativeImage::thumb_url(
          array(
            'id' => $row['id'],
            'path' => $row['path'],
            )
          );
        
        $thumb_urls[] = $thumb;
      }

      // restore configuration setting
      $conf['derivative_url_style'] = $previous_derivative_url_style;
      
      $thumbs_html_string = '';
      foreach ($thumb_urls as $thumb_url)
      {
        if (!empty($thumbs_html_string))
        {
          $thumbs_html_string.= '&nbsp;';
        }
        
        $thumbs_html_string.= '<img src="'.$thumb_url.'">';
      }
      
      $content = $thumbs_html_string;
      
      // how many photos pending?
      $pendings = pfemail_get_pending_ids();
      
      if (count($pendings) > 0)
      {
        $content.= '<br><br>';
        $content.= '<a href="'.get_absolute_root_url().'admin.php?page=plugin-photo_from_email-pendings'.'">';
        $content.= l10n('%d photos pending for validation', count($pendings));
        $content.= '</a>';
      }
      
      $real_user_id = $user['id'];
      $user['id'] = $conf['guest_id'];
      
      $subject = l10n('%d photos added by email', count($thumb_urls));

      foreach ($group_ids as $group_id)
      {
        pwg_mail_group(
          $group_id,
          array(
            'subject' => '['. $conf['gallery_title'] .'] '. $subject,
            'mail_title' => $conf['gallery_title'],
            'mail_subtitle' => $subject,
            'content' => $content,
            'content_format' => 'text/html',
            )
          );
      }
    }

    // restore current user
    $user['id'] = $real_user_id;
  }
}

function pfemail_validate($id)
{
  global $conf, $page, $user;

  list($dbnow) = pwg_db_fetch_row(pwg_query('SELECT NOW();'));

  single_update(
    PFEMAIL_PENDINGS_TABLE,
    array(
      'state' => 'validated',
      'validated_by' => $user['id'],
      ),
    array(
      'image_id' => $id
      )
    );

  single_update(
    IMAGES_TABLE,
    array(
      'date_available' => $dbnow,
      'level' => 0,
      ),
    array(
      'id' => $id,
      )
    );
    
  array_push($page['infos'], l10n('photo validated'));

  invalidate_user_cache();

  // notify users
/*
  $query = '
SELECT
    from
  FROM '.PFEMAIL_PENDINGS_TABLE.'
  WHERE image_id = '.$id.'
;';
  list($to) = pwg_db_fetch_row(pwg_query($query));
    
  if (empty($to))
  {
    return;
  }

  $headers = 'From: '.get_webmaster_mail_address()."\n";
  $headers.= 'X-Mailer: Piwigo Mailer'."\n";
    
  $headers.= "MIME-Version: 1.0\n";
  $headers.= "Content-type: text/plain; charset=utf-8\n";
  $headers.= "Content-Transfer-Encoding: quoted-printable\n";
  
  set_make_full_url();
  
  $message = 'Hi,

Your photo was added to the Piwigo Showcase,
see it on '.make_picture_url(array('image_id' => $id)).'

Have a great day!

--
Piwigo Team
http://piwigo.org';
    
  mail(
    $to,
    '[Piwigo Showcase] your gallery '.showcase_admin_get_simplified_url($url, true).' was successfully added',
    $message,
    $headers
    );
    
  unset_make_full_url();
*/
  
  return true;
}

function pfemail_reject($id)
{
  global $conf, $page;

  $query = '
DELETE
  FROM '.PFEMAIL_PENDINGS_TABLE.'
  WHERE image_id = '.$id.'
;';
  pwg_query($query);
  
  delete_elements(array($id), true);

  array_push($page['infos'], l10n('Photo rejected'));

  invalidate_user_cache();

  return true;
}

function pfemail_get_pending_ids()
{
  global $conf;
  
  $query = '
SELECT
    image_id
  FROM '.PFEMAIL_PENDINGS_TABLE.'
    JOIN '.IMAGES_TABLE.' ON image_id = id
  WHERE state = \'moderation_pending\'
;';
  $ids = query2array($query, null, 'image_id');

  return $ids;
}

/**
 * removes Re:Fwd: at the beginning of email subjects
 *
 * @param string $subject
 * @return string
 */
function pfemail_clean_email_subject($subject)
{
  $regexp = '/([\[\(] *)?(RE|FWD?) *([-:;)\]][ :;\])-]*|$)|\]+ *$/im';

  return preg_replace($regexp, '', $subject)."\n";
}

// +-----------------------------------------------------------------------+
// | API functions                                                         |
// +-----------------------------------------------------------------------+

function ws_pfemail_check($params, &$service)
{
  pfemail_check_accounts();
  
  return true;
}

function ws_pfemail_validate($params, &$service)
{
  if (pfemail_validate($params['image_id']))
  {
    return true;
  }
  
  return false;
}

function ws_pfemail_reject($params, &$service)
{
  if (pfemail_reject($params['image_id']))
  {
    return true;
  }

  return false;
}

function ws_pfemail_mailbox_save($params, &$service)
{
  $mailbox = array();
  
  if (isset($params['id']) and !empty($params['id']))
  {
    // we are edition mode
    $query = '
SELECT *
  FROM '.PFEMAIL_MAILBOXES_TABLE.'
  WHERE id = '.$params['id'].'
;';
    $mailboxes = query2array($query, 'id');

    if (!isset($mailboxes[ $params['id'] ]))
    {
      return new PwgError(404, 'id not found');
    }

    $mailbox = $mailboxes[ $params['id'] ];
  }

  $mailbox['path'] = $params['path'];
  $mailbox['login'] = $params['login'];
  $mailbox['password'] = $params['password'];
  $mailbox['category_id'] = $params['category_id'];
  $mailbox['moderated'] = $params['moderated'] ? 'true' : 'false';

  if (isset($mailbox['id']))
  {
    single_update(
      PFEMAIL_MAILBOXES_TABLE,
      $mailbox,
      array('id' => $params['id'])
      );
  }
  else
  {
    single_insert(
      PFEMAIL_MAILBOXES_TABLE,
      $mailbox
      );

    $mailbox['id'] = pwg_db_insert_id(PFEMAIL_MAILBOXES_TABLE);
  }

  return $mailbox;
}

function ws_pfemail_mailbox_delete($params, &$service)
{
  $query = '
SELECT *
  FROM '.PFEMAIL_MAILBOXES_TABLE.'
  WHERE id = '.$params['id'].'
;';
  $mailboxes = query2array($query, 'id');

  if (!isset($mailboxes[ $params['id'] ]))
  {
    return new PwgError(404, 'id not found');
  }

  $query = '
DELETE
  FROM '.PFEMAIL_MAILBOXES_TABLE.'
  WHERE id = '.$params['id'].'
;';
  pwg_query($query);

  return array('id' => $params['id']);
}

function ws_pfemail_mailbox_test($params, &$service)
{
  global $conf;
  
  require_once(PFEMAIL_PATH.'include/ImapMailbox.php');
  
  try
  {
    $mailbox = new ImapMailbox(
      $params['path'],
      $params['login'],
      $params['password'],
      $conf['upload_dir'].'/buffer',
      'utf-8'
      );

    $info = $mailbox->checkMailbox();
  }
  catch (Exception $e)
  {
    return array('error' => $e->getMessage());
  }

  return $info;
}
?>
