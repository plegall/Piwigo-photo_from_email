{combine_script id='jquery.colorbox' load='footer' require='jquery' path='themes/default/js/plugins/jquery.colorbox.min.js'}
{combine_css path="themes/default/js/plugins/colorbox/style2/colorbox.css"}

{footer_script}{literal}
jQuery(document).ready(function(){
  jQuery("a.zoom").colorbox({rel:"zoom"});

  jQuery('.validate a').click(function() {
    var imageId = jQuery(this).data('image_id');

    jQuery.ajax({
      url: "ws.php?format=json&method=pwg.pfemail.validate",
      type:"POST",
      data: {image_id:imageId},
      beforeSend: function() {
        jQuery('#s'+imageId+' .validate img.loading').show();
      },
      success:function(data) {
        var data = jQuery.parseJSON(data);
        if (data.result === true) {
          jQuery('#s'+imageId).fadeOut();
        }
        else {
          alert('problem on validate');
          jQuery('#s'+imageId+' .validate img.loading').hide();
        }
      },
      error:function(XMLHttpRequest, textStatus, errorThrows) {
        alert('problem on validate');
        jQuery('#s'+imageId+' .validate img.loading').hide();
      }
    });

    return false;
  });

  jQuery('.reject a').click(function() {
    var imageId = jQuery(this).data('image_id');

    jQuery.ajax({
      url: "ws.php?format=json&method=pwg.pfemail.reject",
      type:"POST",
      data: {image_id:imageId},
      beforeSend: function() {
        jQuery('#s'+imageId+' .reject img.loading').show();
      },
      success:function(data) {
        var data = jQuery.parseJSON(data);
        if (data.result === true) {
          jQuery('#s'+imageId).fadeOut();
        }
        else {
          alert('problem on reject');
          jQuery('#s'+imageId+' .reject img.loading').hide();
        }
      },
      error:function(XMLHttpRequest, textStatus, errorThrows) {
        alert('problem on reject');
        jQuery('#s'+imageId+' .reject img.loading').hide();
      }
    });

    return false;
  });
});
{/literal}{/footer_script}


{literal}
<style>
.rowSelected {background-color:#C2F5C2 !important}
.comment p {text-align:left; margin:5px 0 0 5px}
.comment table {margin:5px 0 0 0}
.comment table th {padding-right:10px}
.checkPhoto img.loading {display:none}

.state_not_reviewed {color:#ff7700}
.state_to_validate {color:green}
.state_quarantine {color:red}

.showcaseFilter {text-align:left;margin:5px;}
.showcaseFilter .navigationBar {float:right; margin:0;}
</style>
{/literal}

<div class="titrePage">
  <h2>{'Pending Photos'|@translate} - {'Photo from Email'|@translate}</h2>
</div>

{if !empty($photos) }

<div class="showcaseFilter">
{if !empty($navbar) }{include file='navigation_bar.tpl'|@get_extent:'navbar'}{/if}
</div>

<form>
<table width="99%">
  {foreach from=$photos item=photo name=photo}
  <tr valign="top" class="{if $smarty.foreach.photo.index is odd}row2{else}row1{/if}" id="s{$photo.ID}">
    <td style="width:50px;text-align:center" class="checkPhoto">
      <img src="{$photo.TN_SRC}" style="margin:0.5em"><br>

      <span class="validate"><a data-image_id="{$photo.ID}" href="#">{'Validate'|@translate}</a>
      <img class="loading" src="themes/default/images/ajax-loader-small.gif">
      </span>

      ·

      <span class="reject"><a data-image_id="{$photo.ID}" href="#">{'Reject'|@translate}</a>
      <img class="loading" src="themes/default/images/ajax-loader-small.gif">
      </span>
    </td>
    <td>
  <div class="comment">
    <p class="commentAction" style="float:left;margin:0.5em 0 0 0.5em"><a href="{$photo.WEBSIZE_SRC}" class="zoom">{'Zoom'|@translate}</a> &middot; <a href="{$photo.U_EDIT}">{'Edit'|@translate}</a></p>
    <p class="commentHeader"><strong>{$photo.FROM}</strong> - <em>{$photo.ADDED_ON}</em></p>
    <table>
      <tr>
        <th>{'Name'|@translate}</th>
        <td>{$photo.NAME}</td>
      </tr>
      <tr>
        <th>{'Created on'|@translate}</th>
        <td>{$photo.DATE_CREATION}</td>
      </tr>
    </table>
  </div>
    </td>
  </tr>
  {/foreach}
</table>
</form>

{if !empty($navbar) }{include file='navigation_bar.tpl'|@get_extent:'navbar'}{/if}
{/if}
