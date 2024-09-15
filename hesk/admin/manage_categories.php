<?php
/**
 *
 * This file is part of HESK - PHP Help Desk Software.
 *
 * (c) Copyright Klemen Stirn. All rights reserved.
 * https://www.hesk.com
 *
 * For the full copyright and license agreement information visit
 * https://www.hesk.com/eula.php
 *
 */

define('IN_SCRIPT',1);
define('HESK_PATH','../');

/* Get all the required files and functions */
require(HESK_PATH . 'hesk_settings.inc.php');
require(HESK_PATH . 'inc/common.inc.php');
require(HESK_PATH . 'inc/admin_functions.inc.php');
hesk_load_database_functions();

hesk_session_start();
hesk_dbConnect();
hesk_isLoggedIn();

/* Check permissions for this feature */
hesk_checkPermission('can_man_cat');

// Possible priorities
$priorities = array(
	3 => array('id' => 3, 'value' => 'low', 'text' => $hesklang['low'],		'formatted' => $hesklang['low']),
	2 => array('id' => 2, 'value' => 'medium', 'text' => $hesklang['medium'],		'formatted' => $hesklang['medium']),
	1 => array('id' => 1, 'value' => 'high', 'text' => $hesklang['high'],		'formatted' => $hesklang['high']),
	0 => array('id' => 0, 'value' => 'critical', 'text' => $hesklang['critical'],	'formatted' => $hesklang['critical']),
);

/* What should we do? */
if ( $action = hesk_REQUEST('a') ) {
	if ( defined('HESK_DEMO') )  {hesk_process_messages($hesklang['ddemo'], 'manage_categories.php', 'NOTICE');}
	elseif ($action == 'remove')     {remove();}
	elseif ($action == 'order')      {order_cat();}
	elseif ($action == 'type')       {toggle_type();}
	elseif ($action == 'priority')   {change_priority();}
	elseif ($action == 'due-date')   {change_default_due_date();}
}

/* Print header */
require_once(HESK_PATH . 'inc/header.inc.php');

/* Print main manage users page */
require_once(HESK_PATH . 'inc/show_admin_nav.inc.php');

/* This will handle error, success and notice messages */
if (!hesk_SESSION('error')) {
    hesk_handle_messages();
}
?>
<div class="main__content categories">
    <section class="categories__head">
        <h2>
            <?php echo $hesklang['menu_cat']; ?>
            <div class="tooltype right out-close">
                <svg class="icon icon-info">
                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                </svg>
                <div class="tooltype__content">
                    <div class="tooltype__wrapper">
                        <?php echo $hesklang['cat_intro']; ?>
                    </div>
                </div>
            </div>
        </h2>
        <a href="manage_category.php" class="btn btn btn--blue-border" ripple="ripple">
            <?php echo $hesklang['add_cat']; ?>
        </a>
    </section>
    <div class="table-wrap">
        <div class="table">
            <table id="default-table" class="table sindu-table">
                <thead>
                <tr>
                    <th><?php echo $hesklang['id']; ?></th>
                    <th><?php echo $hesklang['cat_name']; ?></th>
                    <th>
                        <span><?php echo $hesklang['priority']; ?></span>
                        <?php if ($hesk_settings['cust_urgency']): ?>
                        <div class="tooltype right out-close">
                            <svg class="icon icon-info">
                                <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                            </svg>
                            <div class="tooltype__content">
                                <div class="tooltype__wrapper">
                                    <?php echo $hesklang['cat_pri_info'] . ' <a href="#">' . $hesklang['cpri'] . '</a>'; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </th>
                    <th>
                        <span><?php echo $hesklang['not']; ?></span>
                    </th>
                    <th>
                        <span><?php echo $hesklang['cat_type']; ?></span>
                    </th>
                    <?php if ($hesk_settings['autoassign']): ?>
                    <th><?php echo $hesklang['aass']; ?></th>
                    <?php endif; ?>
                    <th class="due-date"><?php echo $hesklang['category_default_due_date'] ?></th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php
                /* Get number of tickets per category */
                $tickets_all   = array();
                $tickets_total = 0;

                $res = hesk_dbQuery('SELECT COUNT(*) AS `cnt`, `category` FROM `'.hesk_dbEscape($hesk_settings['db_pfix']).'tickets` GROUP BY `category`');
                while ($tmp = hesk_dbFetchAssoc($res))
                {
                    $tickets_all[$tmp['category']] = $tmp['cnt'];
                    $tickets_total += $tmp['cnt'];
                }

                /* Get list of categories */
                $res = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` ORDER BY `cat_order` ASC");
                $keyed_categories = array();
                $options='';

                $i=1;
                $j=0;
                $num = hesk_dbNumRows($res);

                while ($mycat=hesk_dbFetchAssoc($res)) {
                    $keyed_categories[$mycat['id']] = $mycat;
                }

                foreach ($keyed_categories as $id => $mycat) {
                    $j++;

                    $table_row = '';
                    if (isset($_SESSION['selcat2']) && $mycat['id'] == $_SESSION['selcat2'])
                    {
                        $table_row = 'class="ticket-new"';
                        unset($_SESSION['selcat2']);
                    }
                    else
                    {
                        $color = $i ? 'admin_white' : 'admin_gray';
                    }

                    $tmp   = $i ? 'White' : 'Blue';
                    $style = 'class="option'.$tmp.'OFF" onmouseover="this.className=\'option'.$tmp.'ON\'" onmouseout="this.className=\'option'.$tmp.'OFF\'"';
                    $i     = $i ? 0 : 1;

                    /* Number of tickets and graph width */
                    $all = isset($tickets_all[$mycat['id']]) ? $tickets_all[$mycat['id']] : 0;
                    $width_all = 0;
                    if ($tickets_total && $all)
                    {
                        $width_all  = round(($all / $tickets_total) * 100);
                    }

                    $options .= '<option value="'.$mycat['id'].'" ';
                    $options .= (isset($_SESSION['selcat']) && $mycat['id'] == $_SESSION['selcat']) ? ' selected="selected" ' : '';
                    $options .= '>'.$mycat['name'].'</option>';


                    ?>
                    <tr <?php echo $table_row; ?> data-category-id="<?php echo $mycat['id']; ?>" data-autoassign-enabled="<?php echo $mycat['autoassign'] ?>" data-autoassign-config="<?php echo hesk_stripslashes($mycat['autoassign_config']); ?>">
                        <td><?php echo $mycat['id']; ?></td>
                        <td>
                            <span class="category-name"><?php echo $mycat['name']; ?></span>
                        </td>
                        <td>
                            <span class="priority<?php echo $mycat['priority']; ?>">
                                <?php echo $priorities[$mycat['priority']]['text']; ?>
                            </span>
                        </td>
                        <td>
                            <?php
                            $tickets_url = 'show_tickets.php?category='.$mycat['id'].'&amp;s_all=1&amp;s_my=1&amp;s_ot=1&amp;s_un=1';
                            ?>
                            <a class="tooltip" data-ztt_vertical_offset="0" href="<?php echo $tickets_url; ?>" title="<?php echo $hesklang['list_tickets_cat']; ?>">
                                <?php echo $all; ?>
                                (<?php echo $width_all; ?>%)
                            </a>
                        </td>
                        <td>
                            <?php echo $mycat['type'] == 0 ? $hesklang['cat_public'] : $hesklang['cat_private']; ?>
                        </td>
                        <?php if ($hesk_settings['autoassign']): ?>
                        <td class="assign">
                            <?php
                            if ($mycat['autoassign']) {
                                echo $hesklang['on'];
                            } else {
                                echo $hesklang['off'];
                            } ?>
                            <?php if (($display = hesk_getAutoAssignConfigDisplay($mycat['autoassign_config'])) !== '') { ?>
                                <div class="autoassign-config-display">
                                    (<?php echo $display; ?>)
                                </div>
                            <?php } ?>
                        </td>
                        <?php endif; ?>
                        <td class="due-date">
                            <?php if ($mycat['default_due_date_amount'] === null && $mycat['default_due_date_unit'] === null) {
                                echo $hesklang['none'];
                            } else {
                                echo $mycat['default_due_date_amount'] . ' ' . $hesklang["d_{$mycat['default_due_date_unit']}"];
                            } ?>
                        </td>
                        <td class="nowrap generate">
                            <a class="tooltip" href="javascript:"
                               title="<?php echo $hesklang['geco']; ?>"
                               <?php echo $mycat['type'] == 1 ? 'style="visibility: hidden"' : '' ?>
                               data-action="generate-link"
                               data-link="<?php echo htmlspecialchars($hesk_settings['hesk_url']) . '/index.php?a=add&catid=' . intval($mycat['id']); ?>">
                                <svg class="icon icon-export">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-export"></use>
                                </svg>
                            </a>
                            <a class="tooltip" href="manage_category.php?id=<?php echo $mycat['id']; ?>"
                               title="<?php echo $hesklang['edit']; ?>">
                                <svg class="icon icon-edit-ticket">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-edit-ticket"></use>
                                </svg>
                            </a>
                            <?php
                            if ($num > 1) {
                                if ($j == 1) {
                                    ?>
                                    <a href="#" style="visibility: hidden">
                                        <svg class="icon icon-chevron-up">
                                            <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-chevron-down"></use>
                                        </svg>
                                    </a>
                                    <a class="tooltip" href="manage_categories.php?a=order&amp;catid=<?php echo $mycat['id']; ?>&amp;move=15&amp;token=<?php hesk_token_echo(); ?>"
                                       title="<?php echo $hesklang['move_dn']; ?>">
                                        <svg class="icon icon-chevron-down">
                                            <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-chevron-down"></use>
                                        </svg>
                                    </a>
                                    <?php
                                    echo'';
                                } elseif ($j == $num) {
                                    ?>
                                    <a class="tooltip" href="manage_categories.php?a=order&amp;catid=<?php echo $mycat['id']; ?>&amp;move=-15&amp;token=<?php hesk_token_echo(); ?>"
                                       title="<?php echo $hesklang['move_up']; ?>">
                                        <svg class="icon icon-chevron-up">
                                            <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-chevron-down"></use>
                                        </svg>
                                    </a>
                                    <a href="#" style="visibility: hidden"
                                       title="<?php echo $hesklang['move_dn']; ?>">
                                        <svg class="icon icon-chevron-down">
                                            <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-chevron-down"></use>
                                        </svg>
                                    </a>
                                    <?php
                                } else {
                                    ?>
                                    <a class="tooltip" href="manage_categories.php?a=order&amp;catid=<?php echo $mycat['id']; ?>&amp;move=-15&amp;token=<?php hesk_token_echo(); ?>"
                                       title="<?php echo $hesklang['move_up']; ?>">
                                        <svg class="icon icon-chevron-up">
                                            <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-chevron-down"></use>
                                        </svg>
                                    </a>
                                    <a class="tooltip" href="manage_categories.php?a=order&amp;catid=<?php echo $mycat['id']; ?>&amp;move=15&amp;token=<?php hesk_token_echo(); ?>"
                                       title="<?php echo $hesklang['move_dn']; ?>">
                                        <svg class="icon icon-chevron-down">
                                            <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-chevron-down"></use>
                                        </svg>
                                    </a>
                                    <?php
                                }
                            }
                            ?>
                            <?php
                            if ($mycat['id'] != 1):
                                $modal_body = $hesklang['confirm_del_cat'];
                                if ($all > 0) {
                                    //-- $j - 2 because $j is 1-indexed and the first category can't be deleted
                                    $modal_body .= '<br><br>'.
                                        '<div><b>'.sprintf($hesklang['select_new_category'], $all).'</b></div>'.
                                        '<select id="targetCat'.($j - 2).'" name="modal-dropdown" onchange="hesk_updateDeleteCategoryUrl('.($j - 2).')">';

                                    foreach ($keyed_categories as $potential_transfer_id => $dropdown_category) {
                                        //-- Don't allow transferring to self
                                        if ($potential_transfer_id === $id) {
                                            continue;
                                        }

                                        $modal_body .= '<option value="'.$potential_transfer_id.'">'.$dropdown_category['name'].'</option>';
                                    }
                                    $modal_body .= '</select>';
                                }
                                $modal_id = hesk_generate_delete_modal($hesklang['confirm_deletion'],
                                    $modal_body,
                                    'manage_categories.php?a=remove&catid='. $mycat['id'] .'&token='. hesk_token_echo(0).'&targetCategory=1');
                                ?>
                            <a class="tooltip delete" title="<?php echo $hesklang['delcat']; ?>" href="javascript:" data-modal="[data-modal-id='<?php echo $modal_id; ?>']">
                                <svg class="icon icon-delete">
                                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-delete"></use>
                                </svg>
                            </a>
                            <?php
                            endif;
                            ?>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<div class="notification-flash green" data-type="link-generate-message">
    <i class="close">
        <svg class="icon icon-close">
            <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-close"></use>
        </svg>
    </i>
    <div class="notification--title"><?php echo $hesklang['genl']; ?></div>
    <div class="notification--text"><?php echo $hesklang['genl2']; ?></div>
</div>
<?php
hesk_cleanSessionVars('error');
require_once(HESK_PATH . 'inc/footer.inc.php');
exit();


/*** START FUNCTIONS ***/
function remove()
{
	global $hesk_settings, $hesklang;

	/* A security check */
	hesk_token_check();

    $_SERVER['PHP_SELF'] = 'manage_categories.php';

	$mycat = intval( hesk_GET('catid') ) or hesk_error($hesklang['no_cat_id']);
	if ($mycat == 1)
    {
    	hesk_process_messages($hesklang['cant_del_default_cat'],$_SERVER['PHP_SELF']);
    }

	hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` WHERE `id`='".intval($mycat)."'");
	if (hesk_dbAffectedRows() != 1)
    {
    	hesk_error("$hesklang[int_error]: $hesklang[cat_not_found].");
    }

    $new_category = hesk_GET('targetCategory', 1);
    // Don't update resolved tickets "Last modified"
    hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `category`=".intval($new_category).", `lastchange`=`lastchange` WHERE `category`='".intval($mycat)."' AND `status` = '3'");
    // For unresolved tickets, update the "Last modified"
	hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `category`=".intval($new_category)." WHERE `category`='".intval($mycat)."'");

    hesk_process_messages($hesklang['cat_removed_db'],$_SERVER['PHP_SELF'],'SUCCESS');
} // End remove()


function order_cat()
{
	global $hesk_settings, $hesklang;

	/* A security check */
	hesk_token_check();

	$catid = intval( hesk_GET('catid') ) or hesk_error($hesklang['cat_move_id']);
	$_SESSION['selcat2'] = $catid;

	$cat_move=intval( hesk_GET('move') );

	hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` SET `cat_order`=`cat_order`+".intval($cat_move)." WHERE `id`='".intval($catid)."'");
	if (hesk_dbAffectedRows() != 1)
    {
    	hesk_error("$hesklang[int_error]: $hesklang[cat_not_found].");
    }

	/* Update all category fields with new order */
	$res = hesk_dbQuery("SELECT `id` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` ORDER BY `cat_order` ASC");

	$i = 10;
	while ($mycat=hesk_dbFetchAssoc($res))
	{
	    hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` SET `cat_order`=".intval($i)." WHERE `id`='".intval($mycat['id'])."'");
	    $i += 10;
	}

    header('Location: manage_categories.php');
    exit();
} // End order_cat()
?>
