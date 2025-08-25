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

/* Check if this is a valid include */
if (!defined('IN_SCRIPT')) {die('Invalid attempt');}

// List of staff and check their permissions
$admins = array();
$can_assign_to = array();
$res2 = hesk_dbQuery("SELECT `id`,`name`,`isadmin`,`heskprivileges` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` ORDER BY `name` ASC");
while ($row = hesk_dbFetchAssoc($res2))
{
    $admins[$row['id']] = $row['name'];

    if ($row['isadmin'] || strpos($row['heskprivileges'], 'can_view_tickets') !== false)
    {
        $can_assign_to[$row['id']] = $row['name'];
    }
}

/* List of categories */
if ( ! isset($hesk_settings['categories']))
{
    $hesk_settings['categories'] = array();
    $res2 = hesk_dbQuery('SELECT `id`, `name` FROM `'.hesk_dbEscape($hesk_settings['db_pfix']).'categories` WHERE ' . hesk_myCategories('id') . ' ORDER BY `cat_order` ASC');
    while ($row=hesk_dbFetchAssoc($res2))
    {
        $hesk_settings['categories'][$row['id']] = $row['name'];
    }
}

/* Current MySQL time */
$mysql_time = hesk_dbTime();

/* Get number of tickets and page number */
$result = hesk_dbQuery($sql_count);

while ($row = hesk_dbFetchAssoc($result))
{
    // Total tickets found
    $totals['filtered']['all'] += $row['cnt'];

    // Total by status
    if (isset($totals['filtered']['by_status'][$row['status']]))
    {
        $totals['filtered']['by_status'][$row['status']] += $row['cnt'];
    }
    else
    {
        $totals['filtered']['by_status'][$row['status']] = $row['cnt'];
    }

    // Count all filtered open tickets
    if (isset($row['status']) && $row['status'] != 3)
    {
        $totals['filtered']['open'] += $row['cnt'];
    }

    // Totals by assigned to
    if (isset($row['assigned_to'])):
    switch ($row['assigned_to'])
    {
        case 1:
            $totals['filtered']['assigned_to_me'] += $row['cnt'];
            break;
        case 2:
            $totals['filtered']['assigned_to_others'] += $row['cnt'];
            break;
        case 3:
            $totals['filtered']['assigned_to_others'] += $row['cnt'];
            $totals['filtered']['assigned_to_others_by_me'] += $row['cnt'];
            break;
        default:
            $totals['filtered']['unassigned'] += $row['cnt'];
    }
    endif;

    // Total by due date; ignore for Resolved tickets
    if ($row['status'] != 3)
    {
        switch ($row['due'])
        {
            case 1:
                $totals['filtered']['due_soon'] += $row['cnt'];
                break;
            case 2:
                $totals['filtered']['overdue'] += $row['cnt'];
                break;
        }
    }

    // Total bookmarks
    if ( ! empty($row['is_bookmark']))
    {
        $totals['filtered']['bookmarks'] += $row['cnt'];
    }
}

$result = hesk_dbQuery($sql_collaborator);
while ($row = hesk_dbFetchAssoc($result))
{
    // Total collaborator tickets
    $totals['filtered']['collaborator'] += $row['cnt'];
}

// Quick link: assigned to me
if ($is_quick_link == 'my')
{
    $total = $totals['filtered']['assigned_to_me'];
}
// Quick link: collaborator
elseif ($is_quick_link == 'cbm')
{
    $total = $totals['filtered']['collaborator'];
}
// Quick link: assigned to other
elseif ($is_quick_link == 'ot')
{
    $total = $totals['filtered']['assigned_to_others'];
}
// Quick link: unassigned
elseif ($is_quick_link == 'un')
{
    $total = $totals['filtered']['unassigned'];
}
// Quick link: bookmarks
elseif ($is_quick_link == 'bm')
{
    $total = $totals['filtered']['bookmarks'];
}
// Quick link: due soon
elseif ($is_quick_link == 'due')
{
    $total = $totals['filtered']['due_soon'];
}
// Quick link: overdue
elseif ($is_quick_link == 'ovr')
{
    $total = $totals['filtered']['overdue'];
}
// Quick link: all open
elseif ($is_quick_link == 'alo')
{
    $total = $totals['open'];
}
// Quick link: all
elseif ($is_quick_link == 'all')
{
    $total = $totals['all'];
}
// No quick link
else
{
    $is_quick_link = false;

    // Can view unassigned, but selected only Assigned to me + Assigned to others
    if ($can_view_unassigned && $s_my[1] == 1 && $s_ot[1] == 1 && $s_un[1] == 0) {
        $totals['filtered']['show'] = $totals['filtered']['all'] - $totals['filtered']['unassigned'];
    }

    // Can view tickets assigned to others, but selected only Assigned to me + Unassigned
    elseif (($can_view_ass_others || $can_view_ass_by) && $s_my[1] == 1 && $s_ot[1] == 0 && $s_un[1] = 1) {
        $totals['filtered']['show'] = $totals['filtered']['assigned_to_me'] + $totals['filtered']['unassigned'];
    }

    // Unassigned + Assigned to others
    elseif ($s_my[1] == 0 && $s_ot[1] == 1 && $s_un[1] = 1) {
        $totals['filtered']['show'] = $totals['filtered']['all'] - $totals['filtered']['assigned_to_me'];
    }

    if (isset($totals['filtered']['show'])) {
        $total = $totals['filtered']['show'];
    } else {
        $total = $totals['filtered']['all'];
    }
}

if (true)
{

	/* This query string will be used to browse pages */
    if ($href == 'admin_main.php' || $href == 'show_tickets.php')
	{
		#$query  = 'status='.$status;

        $query = '';
        $query .= 's' . implode('=1&amp;s',array_keys($status)) . '=1';
        $query .= '&amp;p' . implode('=1&amp;p',array_keys($priority)) . '=1';

		$query .= '&amp;category='.$category;
		$query .= '&amp;sort='.$sort;
		$query .= '&amp;asc='.$asc;
		$query .= '&amp;limit='.$maxresults;
		$query .= '&amp;archive='.$archive[1];
		$query .= '&amp;s_my='.$s_my[1];
		$query .= '&amp;s_ot='.$s_ot[1];
		$query .= '&amp;s_un='.$s_un[1];

        $query .= '&amp;duedate_option='.$duedate_search_type;
        $query .= '&amp;duedate_specific_date='.urlencode($duedate_input);
        $query .= '&amp;duedate_amount_value='.$duedate_amount_value;
        $query .= '&amp;duedate_amount_unit='.$duedate_amount_unit;

		$query .= '&amp;cot='.$cot;
		$query .= '&amp;g='.$group;
	}
	else
	{
		$query  = 'q='.$q;
	    $query .= '&amp;what='.$what;
		$query .= '&amp;category='.$category;
        $query .= '&amp;owner='.$owner_input;
		$query .= '&amp;dt='.urlencode($date_input);
		$query .= '&amp;sort='.$sort;
		$query .= '&amp;asc='.$asc;
		$query .= '&amp;limit='.$maxresults;
		$query .= '&amp;archive='.$archive[2];
		$query .= '&amp;s_my='.$s_my[2];
		$query .= '&amp;s_ot='.$s_ot[2];
		$query .= '&amp;s_un='.$s_un[2];
	}

    $query_for_quick_links = $query;

    if ($is_quick_link !== false)
    {
        $query .= '&amp;ql=' . $is_quick_link;
    }

    $query_for_pagination = $query . '&amp;page=';

	$pages = ceil($total/$maxresults) or $pages = 1;
	if ($page > $pages)
	{
		$page = $pages;
	}
	$limit_down = ($page * $maxresults) - $maxresults;

	$prev_page = ($page - 1 <= 0) ? 0 : $page - 1;
	$next_page = ($page + 1 > $pages) ? 0 : $page + 1;

	/* We have the full SQL query now, get tickets */
	$sql .= " LIMIT ".hesk_dbEscape($limit_down)." , ".hesk_dbEscape($maxresults)." ";
	$result = hesk_dbQuery($sql);

    /* Uncomment for debugging */
    # echo "SQL: $sql\n<br>";

	/* This query string will be used to order and reverse display */
    if ($href == 'admin_main.php' || $href == 'show_tickets.php')
	{
		#$query  = 'status='.$status;

        $query = '';
        $query .= 's' . implode('=1&amp;s',array_keys($status)) . '=1';
        $query .= '&amp;p' . implode('=1&amp;p',array_keys($priority)) . '=1';

		$query .= '&amp;category='.$category;
		#$query .= '&amp;asc='.(isset($is_default) ? 1 : $asc_rev);
		$query .= '&amp;limit='.$maxresults;
		$query .= '&amp;archive='.$archive[1];
		$query .= '&amp;s_my='.$s_my[1];
		$query .= '&amp;s_ot='.$s_ot[1];
		$query .= '&amp;s_un='.$s_un[1];
		$query .= '&amp;page=1';
		#$query .= '&amp;sort=';

        $query .= '&amp;duedate_option='.$duedate_search_type;
        $query .= '&amp;duedate_specific_date='.urlencode($duedate_input);
        $query .= '&amp;duedate_amount_value='.$duedate_amount_value;
        $query .= '&amp;duedate_amount_unit='.$duedate_amount_unit;

		$query .= '&amp;cot='.$cot;
		$query .= '&amp;g='.$group;

	}
	else
	{
		$query  = 'q='.$q;
	    $query .= '&amp;what='.$what;
		$query .= '&amp;category='.$category;
        $query .= '&amp;owner='.$owner_input;
		$query .= '&amp;dt='.urlencode($date_input);
		#$query .= '&amp;asc='.$asc;
		$query .= '&amp;limit='.$maxresults;
		$query .= '&amp;archive='.$archive[2];
		$query .= '&amp;s_my='.$s_my[2];
		$query .= '&amp;s_ot='.$s_ot[2];
		$query .= '&amp;s_un='.$s_un[2];
		$query .= '&amp;page=1';
		#$query .= '&amp;sort=';
	}

    if ($is_quick_link !== false)
    {
        $query .= '&amp;ql=' . $is_quick_link;
    }

    $query .= '&amp;asc=';

	/* Print the table with tickets */
	$random=rand(10000,99999);

	$modal_id = hesk_generate_old_delete_modal($hesklang['confirm'],
        $hesklang['confirm_execute'],
    "javascript:document.getElementById('delete-tickets-form').submit()",
        $hesklang['confirm']);

    // Are some open tickets hidden?
    if ($href != 'find_tickets.php' && ($totals['filtered']['open'] != $totals['open'] || (isset($totals['filtered']['show']) && $totals['filtered']['show'] != $totals['open'])))
    {
        hesk_show_info($hesklang['not_aos'], ' ', false, 'no-padding-top');
    }
	?>
    <section class="quick-links">
        <div class="filters__listing">
            <!--
            <a href="<?php echo $href . '?' . $query_for_quick_links . '&amp;ql=all&amp;s_my=1&amp;s_ot=1&amp;s_un=1&amp;category=0'; ?>" class="btn btn-transparent <?php if ($is_quick_link == 'all') echo 'is-bold is-selected'; ?>"><span><?php echo $hesklang['ql_all']; ?></span> <span class="filters__btn-value"><?php echo $totals['all']; ?></span></a>
            <a href="<?php echo $href . '?' . $query_for_quick_links . '&amp;ql=alo&amp;s_my=1&amp;s_ot=1&amp;s_un=1&amp;category=0'; ?>" class="btn btn-transparent <?php if ($is_quick_link == 'alo') echo 'is-bold is-selected'; ?>"><span><?php echo $hesklang['ql_alo']; ?></span> <span class="filters__btn-value"><?php echo $totals['open']; ?></span></a>
             -->
            <a href="<?php echo $href . '?' . $query_for_quick_links . '&amp;ql=&amp;s_my=1&amp;s_ot=1&amp;s_un=1'; ?>" class="btn btn-transparent <?php if (empty($is_quick_link)) echo 'is-bold is-selected'; ?>"><span><?php
            if ($href == 'find_tickets.php') {
                echo $hesklang['tickets_found'];
            }
            elseif (
                $totals['filtered']['open'] == $totals['open'] &&
                $totals['filtered']['open'] == $totals['filtered']['all'] &&
                ( ! isset($totals['filtered']['show']) || $totals['filtered']['show'] == $totals['open'])) {
                echo $hesklang['open_tickets'];
            }
            else {
                echo $hesklang['ql_fit'];
            }
            ?></span> <span class="filters__btn-value"><?php echo (isset($totals['filtered']['show']) ? $totals['filtered']['show'] : $totals['filtered']['all']); ?></span></a>
            <a href="<?php echo $href . '?' . $query_for_quick_links . '&amp;ql=my'; ?>" class="btn btn-transparent <?php if ($is_quick_link == 'my') echo 'is-bold is-selected'; ?>"><span><?php echo $hesklang['ql_a2m']; ?></span> <span class="filters__btn-value"><?php echo $totals['filtered']['assigned_to_me']; ?></span></a>
            <a href="<?php echo $href . '?' . $query_for_quick_links . '&amp;ql=cbm'; ?>" class="btn btn-transparent <?php if ($is_quick_link == 'cbm') echo 'is-bold is-selected'; ?>"><span><?php echo $hesklang['ql_cbm']; ?></span> <span class="filters__btn-value"><?php echo $totals['filtered']['collaborator']; ?></span></a>
            <?php if ($can_view_ass_others || $can_view_ass_by): ?>
            <a href="<?php echo $href . '?' . $query_for_quick_links . '&amp;ql=ot'; ?>" class="btn btn-transparent <?php if ($is_quick_link == 'ot') echo 'is-bold is-selected'; ?>"><span><?php echo $hesklang['ql_a2o']; ?></span> <span class="filters__btn-value"><?php echo $totals['filtered']['assigned_to_others']; ?></span></a>
            <?php endif; ?>
            <?php if ($can_view_unassigned): ?>
            <a href="<?php echo $href . '?' . $query_for_quick_links . '&amp;ql=un'; ?>" class="btn btn-transparent <?php if ($is_quick_link == 'un') echo 'is-bold is-selected'; ?>"><span><?php echo $hesklang['ql_una']; ?></span> <span class="filters__btn-value"><?php echo $totals['filtered']['unassigned']; ?></span></a>
            <?php endif; ?>
            <a href="<?php echo $href . '?' . $query_for_quick_links . '&amp;ql=bm&amp;s_my=1&amp;s_ot=1&amp;s_un=1'; ?>" class="btn btn-transparent is-bookmarks <?php if ($is_quick_link == 'bm') echo 'is-bold is-selected'; ?>"><span><?php echo $hesklang['ql_bookmarks']; ?></span> <span class="filters__btn-value"><?php echo $totals['filtered']['bookmarks']; ?></span></a>
            <a href="<?php echo $href . '?' . $query_for_quick_links . '&amp;ql=due&amp;s_my=1&amp;s_ot=1&amp;s_un=1'; ?>" class="btn btn-transparent is-due-soon <?php if ($is_quick_link == 'due') echo 'is-bold is-selected'; ?>"><span><?php echo $hesklang['ql_due']; ?></span> <span class="filters__btn-value"><?php echo $totals['filtered']['due_soon']; ?></span></a>
            <a href="<?php echo $href . '?' . $query_for_quick_links . '&amp;ql=ovr&amp;s_my=1&amp;s_ot=1&amp;s_un=1'; ?>" class="btn btn-transparent is-overdue <?php if ($is_quick_link == 'ovr') echo 'is-bold is-selected'; ?>"><span><?php echo $hesklang['ql_ovr']; ?></span> <span class="filters__btn-value"><?php echo $totals['filtered']['overdue']; ?></span></a>
        </div>

        <div class="checkbox-custom auto-reload">
            <input type="checkbox" id="reloadCB" onclick="toggleAutoRefresh(this);">
            <label for="reloadCB"><?php echo $hesklang['arp']; ?></label>&nbsp;<span id="timer"></span>
            <script type="text/javascript">heskCheckReloading();</script>
        </div>
    </section>
    <?php
    if ($total > 0)
    {
        ?>
        <form name="form1" id="delete-tickets-form" action="delete_tickets.php" method="post">
        <?php
        if (empty($group))
        {
            hesk_print_list_head();
        }
    }
    else
    {
        hesk_show_info($hesklang['no_tickets_crit'], ' ', false);
    }

	$checkall = '
    <div class="checkbox-custom">
        <input type="checkbox" id="ticket_checkall" onclick="hesk_changeAll()">
        <label for="ticket_checkall">&nbsp;</label>
    </div>';

    $group_tmp = '';
	$is_table = 0;
	$space = 0;

	while ($ticket=hesk_dbFetchAssoc($result))
	{
		// Are we grouping tickets?
		if ($group)
        {
			require(HESK_PATH . 'inc/print_group.inc.php');
        }

		// Set owner (needed for row title)
		$owner = '';
        $first_line = '(' . $hesklang['unas'] . ')'." \n\n";
		if ($ticket['owner'] == $_SESSION['id'])
		{
			$owner = '<svg class="icon icon-assign" style="margin-right: 3px">
                    <use xlink:href="'. HESK_PATH . 'img/sprite.svg#icon-assign"></use>
                </svg>';
            $first_line = $hesklang['tasy2'] . " \n\n";
		}
		elseif ($ticket['owner'])
		{
        	if (!isset($admins[$ticket['owner']]))
            {
            	$admins[$ticket['owner']] = $hesklang['e_udel'];
            }
			$owner = '<svg class="icon icon-assign-plus" style="margin-right: 3px">
                    <use xlink:href="'. HESK_PATH . 'img/sprite.svg#icon-assign-plus"></use>
                </svg>';
            $first_line = $hesklang['taso3'] . ' ' . $admins[$ticket['owner']] . " \n\n";
		}

		// Set message (needed for row title)
		$ticket['message'] = $first_line . hesk_mb_substr(strip_tags($ticket['message']),0,200).'...';

		// Start ticket row
		echo '
		<tr title="'.$ticket['message'].'" class="status-'. $ticket['status'] .' '.($ticket['owner'] ? '' : 'new').($ticket['priority'] == '0' ? ' bg-critical' : '').'">
		<td class="table__first_th sindu_handle">
            <div class="checkbox-custom">
                <input type="checkbox" id="ticket_check_'.$ticket['id'].'" name="id[]" value="'.$ticket['id'].'" class="group' . $hesk_settings['hesk-group-count'] . '">
                <label for="ticket_check_'.$ticket['id'].'">&nbsp;</label>
            </div>
        </td>
		';

		// Print sequential ID and link it to the ticket page
		if ( hesk_show_column('id') )
		{
			echo '<td><a href="admin_ticket.php?track='.$ticket['trackid'].'&amp;Refresh='.$random.'">'.$ticket['id'].'</a></td>';
		}

		// Print tracking ID and link it to the ticket page
		if ( hesk_show_column('trackid') )
		{
			echo '<td class="trackid">
                <div class="table__td-id">
                    <a class="link" href="admin_ticket.php?track='.$ticket['trackid'].'&amp;Refresh='.$random.'">'.$ticket['trackid'].'</a>
                </div>
            </td>';
		}

		// Print date submitted
		if ( hesk_show_column('dt') )
		{
			switch ($hesk_settings['submittedformat'])
			{
	        	case 1:
					$ticket['dt'] = hesk_date($ticket['dt'], true, true, true, $hesk_settings['format_timestamp']);
					break;
				case 2:
					$ticket['dt'] = hesk_time_lastchange($ticket['dt']);
					break;
                case 3:
                    $ticket['dt'] = hesk_date($ticket['dt'], true, true, true, $hesk_settings['format_date']);
                    break;
                case 4:
                    $ticket['dt'] = hesk_date($ticket['dt'], true, true, true, $hesk_settings['format_submitted']);
                    break;
				default:
					$ticket['dt'] = hesk_time_since( strtotime($ticket['dt']) );
			}
			echo '<td>'.$ticket['dt'].'</td>';
		}

		// Print last modified
		if ( hesk_show_column('lastchange') )
		{
            // Another usage over in my_tickets.php
			switch ($hesk_settings['updatedformat'])
			{
	        	case 1:
					$ticket['lastchange'] = hesk_date($ticket['lastchange'], true, true, true, $hesk_settings['format_timestamp']);
					break;
				case 2:
					$ticket['lastchange'] = hesk_time_lastchange($ticket['lastchange']);
					break;
                case 3:
                    $ticket['lastchange'] = hesk_date($ticket['lastchange'], true, true, true, $hesk_settings['format_date']);
                    break;
                case 4:
                    $ticket['lastchange'] = hesk_date($ticket['lastchange'], true, true, true, $hesk_settings['format_updated']);
                    break;
				default:
					$ticket['lastchange'] = hesk_time_since( strtotime($ticket['lastchange']) );
			}
			echo '<td>'.$ticket['lastchange'].'</td>';
		}

		// Print ticket category
		if ( hesk_show_column('category') )
		{
			$ticket['category_name'] = isset($hesk_settings['categories'][$ticket['category']]) ? $hesk_settings['categories'][$ticket['category']] : $hesklang['catd'];
			echo '<td class="category-'.intval($ticket['category']).'">'.$ticket['category_name'].'</td>';
		}

		// Print customer name
		if ( hesk_show_column('name') )
		{
            echo '<td>'.$ticket['name'];

            if (intval($ticket['customer_count']) > 1) {
                echo '<span class="customer-count">'.sprintf($hesklang['customer_count_x_more'], intval($ticket['customer_count']) - 1).'</span>';
            }

			echo '</td>';
		}

		// Print customer email
		if ( hesk_show_column('email') )
		{
			echo '<td>' . (strlen($ticket['email']) ? '<a href="mailto:'.$ticket['email'].'">'.$hesklang['clickemail'].'</a>' : '');

            if (intval($ticket['email_count']) > 1) {
                $subtraction_amount = strlen($ticket['email']) ? 1 : 0;
                echo '<span class="customer-count">'.sprintf($hesklang['customer_count_x_more'], intval($ticket['email_count']) - $subtraction_amount).'</span>';
            }

            echo '</td>';
		}

		// Print subject and link to the ticket page
		if ( hesk_show_column('subject') )
		{
			echo '<td class="subject">'.($ticket['archive'] ? '<svg class="icon icon-tag '.($ticket['owner'] != $_SESSION['id'] ? 'fill-gray' : '').'" style="margin-right: 3px">
                        <use xlink:href="'. HESK_PATH .'img/sprite.svg#icon-tag"></use>
                    </svg>' : '').($ticket['is_bookmark'] ? '<svg class="icon icon-pin is-bookmark" style="margin-right: 6px">
                        <use xlink:href="'. HESK_PATH .'img/sprite.svg#icon-pin"></use>
                    </svg>' : '').$owner.'<a class="link" href="admin_ticket.php?track='.$ticket['trackid'].'&amp;Refresh='.$random.'">'.$ticket['subject'].'</a></td>';
		}

		// Print ticket status
		if ( hesk_show_column('status') )
		{
            echo '<td>' . hesk_get_admin_ticket_status($ticket['status']) . '&nbsp;</td>';
		}

		// Print ticket owner
		if ( hesk_show_column('owner') )
		{
			if ($ticket['owner'])
			{
				$ticket['owner'] = isset($admins[$ticket['owner']]) ? $admins[$ticket['owner']] : $hesklang['unas'];
			}
			else
			{
				$ticket['owner'] = $hesklang['unas'];
			}
			echo '<td>'.$ticket['owner'].'</td>';
		}

		// Print number of all replies
		if ( hesk_show_column('replies') )
		{
			echo '<td>'.$ticket['replies'].'</td>';
		}

		// Print number of staff replies
		if ( hesk_show_column('staffreplies') )
		{
			echo '<td>'.$ticket['staffreplies'].'</td>';
		}

		// Print last replier
		if ( hesk_show_column('lastreplier') )
		{
			if ($ticket['lastreplier'])
			{
				$ticket['repliername'] = isset($admins[$ticket['replierid']]) ? $admins[$ticket['replierid']] : $hesklang['staff'];
			}
			else
            {
                $customer_name = $ticket['lastreplier_customername'] === null ? $ticket['name'] : $ticket['lastreplier_customername'];
				$ticket['repliername'] = $customer_name;
			}
			echo '<td>'.$ticket['repliername'].'</td>';
		}

		// Print time worked
		if ( hesk_show_column('time_worked') )
		{
			echo '<td>'.$ticket['time_worked'].'</td>';
		}

		// Print due date
        if (hesk_show_column('due_date')) {
            $due_date = $hesklang['none'];
            if ($ticket['due_date'] != null) {
                $due_date = hesk_date($ticket['due_date'], false, true, false);
                $due_date = date($hesk_settings['format_date'], $due_date);
            }

            echo '<td>'.$due_date.'</td>';
        }

		// Print custom fields
		foreach ($hesk_settings['custom_fields'] as $key => $value)
		{
			if ($value['use'] && hesk_show_column($key) )
            {
				echo '<td>'.($value['type'] == 'date' ? hesk_custom_date_display_format($ticket[$key], $value['value']['date_format']) : $ticket[$key]).'</td>';
            }
		}

		// End ticket row
        echo '<td><div class="td-flex">' . hesk_get_admin_ticket_priority_for_list($ticket['priority']) . '&nbsp;</div></td>';

	} // End while

    // Only show all this if we found any tickets
    if ($total > 0)
    {
	?>
    </tbody>
	</table>
	</div>
    <div class="pagination-wrap">
        <div class="pagination">
            <?php
            if ($pages > 1) {
                /* List pages */
                if ($pages >= 7) {
                    if ($page > 2) {
                        echo '
                        <a href="'.$href.'?'.$query_for_pagination.'1" class="btn pagination__nav-btn">
                            <svg class="icon icon-chevron-left" style="margin-right:-6px">
                              <use xlink:href="'. HESK_PATH .'img/sprite.svg#icon-chevron-left"></use>
                            </svg>
                            <svg class="icon icon-chevron-left">
                              <use xlink:href="'. HESK_PATH .'img/sprite.svg#icon-chevron-left"></use>
                            </svg>
                            '.$hesklang['pager_first'].'
                        </a>';
                    }

                    if ($prev_page)
                    {
                        echo '
                        <a href="'.$href.'?'.$query_for_pagination.$prev_page.'" class="btn pagination__nav-btn">
                            <svg class="icon icon-chevron-left">
                              <use xlink:href="'. HESK_PATH .'img/sprite.svg#icon-chevron-left"></use>
                            </svg>
                            '.$hesklang['pager_previous'].'
                        </a>';
                    }
                }

                echo '<ul class="pagination__list">';
                for ($i=1; $i<=$pages; $i++)
                {
                    if ($i <= ($page+5) && $i >= ($page-5))
                    {
                        if ($i == $page)
                        {
                            echo '
                            <li class="pagination__item is-current">
                              <a href="javascript:" class="pagination__link">'.$i.'</a>
                            </li>';
                        }
                        else
                        {
                            echo '
                            <li class="pagination__item ">
                              <a href="'.$href.'?'.$query_for_pagination.$i.'" class="pagination__link">'.$i.'</a>
                            </li>';
                        }
                    }
                }
                echo '</ul>';

                if ($pages >= 7)
                {
                    if ($next_page)
                    {
                        echo '
                        <a href="'.$href.'?'.$query_for_pagination.$next_page.'" class="btn pagination__nav-btn">
                            '.$hesklang['pager_next'].'
                            <svg class="icon icon-chevron-right">
                              <use xlink:href="'. HESK_PATH .'img/sprite.svg#icon-chevron-right"></use>
                            </svg>
                        </a>';
                    }

                    if ($page < ($pages - 1))
                    {
                        echo '
                        <a href="'.$href.'?'.$query_for_pagination.$pages.'" class="btn pagination__nav-btn">
                            '.$hesklang['pager_last'].'
                            <svg class="icon icon-chevron-right">
                              <use xlink:href="'. HESK_PATH .'img/sprite.svg#icon-chevron-right"></use>
                            </svg>
                            <svg class="icon icon-chevron-right" style="margin-left:-6px">
                              <use xlink:href="'. HESK_PATH .'img/sprite.svg#icon-chevron-right"></use>
                            </svg>
                        </a>';
                    }
                }
            } // end PAGES > 1
            ?>
        </div>
        <p class="pagination__amount"><?php echo sprintf($hesklang['tickets_on_pages'],$total,$pages); ?></p>
    </div>

    <section class="tickets__legend">
        <div>
            <?php
            if (hesk_checkPermission('can_add_archive',0))
            {
                ?>
                <div>
                    <svg class="icon icon-tag">
                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-tag"></use>
                    </svg>
                    <?php echo $hesklang['archived2']; ?>
                </div>
                <?php
            }
            ?>
            <div>
                <svg class="icon icon-assign">
                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-assign"></use>
                </svg> <?php echo $hesklang['tasy2']; ?>
            </div>
            <?php
            if (hesk_checkPermission('can_view_ass_others',0) || hesk_checkPermission('can_view_ass_by',0))
            {
                ?>
                <div>
                    <svg class="icon icon-assign-plus">
                        <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-assign-plus"></use>
                    </svg> <?php echo $hesklang['taso2']; ?>
                </div>
                <?php
            }
            ?>
        </div>
        <div class="bulk-actions">
            <?php echo $hesklang['with_selected']; ?>
            <div class="clear-on-mobile"></div>
            <div class="inline-bottom">
                <select name="a">
                    <?php
                    foreach ($hesk_settings['priorities'] as $k => $v) {
                        ?>
                        <option value="<?php echo $k;?>"><?php echo $hesklang['set_pri_to'].' '.$v['name']; ?></option>
                        <?php
                    }
                    ?>
                    <?php
                    if ( hesk_checkPermission('can_resolve', 0) && ! defined('HESK_DEMO') )
                    {
                        ?>
                        <option value="close"><?php echo $hesklang['close_selected']; ?></option>
                        <?php
                    }

                    if ( hesk_checkPermission('can_add_archive', 0) )
                    {
                        ?>
                        <option value="tag"><?php echo $hesklang['add_archive_quick']; ?></option>
                        <option value="untag"><?php echo $hesklang['remove_archive_quick']; ?></option>
                        <?php
                    }

                    ?>
                    <option value="print"><?php echo $hesklang['print_selected']; ?></option>
                    <?php

                    if ( ! defined('HESK_DEMO') )
                    {

                        if ( hesk_checkPermission('can_merge_tickets', 0) )
                        {
                            ?>
                            <option value="merge"><?php echo $hesklang['mer_selected']; ?></option>
                            <?php
                        }
                        if ( hesk_checkPermission('can_export', 0) )
                        {
                            ?>
                            <option value="export"><?php echo $hesklang['export_selected']; ?></option>
                            <?php
                        }
                        if ( hesk_checkPermission('can_privacy', 0) )
                        {
                            ?>
                            <option value="anonymize"><?php echo $hesklang['anon_selected']; ?></option>
                            <?php
                        }
                        if ( hesk_checkPermission('can_del_tickets', 0) )
                        {
                            ?>
                            <option value="delete"><?php echo $hesklang['del_selected']; ?></option>
                            <?php
                        }

                    } // End demo
                    ?>
                </select>
            </div>
            <input type="hidden" name="token" value="<?php hesk_token_echo(); ?>" />
            <button onclick="document.getElementById('action-type').value = 'bulk'" type="button" class="btn btn-full" ripple="ripple" data-modal="[data-modal-id='<?php echo $modal_id; ?>']">
                <?php echo $hesklang['execute']; ?>
            </button>

            <?php
            if (hesk_checkPermission('can_assign_others',0))
            {
                ?>
                <div style="height:6px"></div>

                <?php echo $hesklang['assign_selected']; ?>
                <div class="clear-on-mobile"></div>
                <div class="inline-bottom">
                    <select name="owner">
                        <option value="" selected="selected"><?php echo $hesklang['select']; ?></option>
                        <option value="-1"> &gt; <?php echo $hesklang['unas']; ?> &lt; </option>
                        <?php
                        foreach ($can_assign_to as $k=>$v)
                        {
                            echo '<option value="'.$k.'">'.$v.'</option>';
                        }
                        ?>
                    </select>
                </div>
                <button type="button" name="assign" class="btn btn-full" data-modal="[data-modal-id='<?php echo $modal_id; ?>']"
                    onclick="document.getElementById('action-type').value = 'assi'">
                    <?php echo $hesklang['assi']; ?>
                </button>
                <?php
            }
            ?>
        </div>
    </section>
    <input id="action-type" name="action-type" type="hidden" value="-1">
	</form>
	<?php
    } // END ticket list if total > 0
    elseif (!isset($is_search) && $href != 'find_tickets.php' && !$is_quick_link)
    {
        // No tickets in the DB? Show a welcome message
        $res = hesk_dbQuery("SELECT COUNT(*) FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets`");
        $num = hesk_dbResult($res,0,0);
        if ($num == 0)
        {
            hesk_show_notice(
                $hesklang['welcome1'] . '<br><br>' .
                sprintf(
                    $hesklang['welcome2'],
                    '<a href="https://www.hesk.com/knowledgebase/?article=109" target="_blank">' . $hesklang['welcome3'] . '</a>'
                ), ' ', false
            );
        }
    }
} // END ticket list if total > 0 or if this is a quick link
else
{
    if (isset($is_search) || $href == 'find_tickets.php')
    {
        hesk_show_notice($hesklang['no_tickets_crit']);
    }
}


function hesk_print_list_head()
{
	global $hesk_settings, $href, $query, $sort_possible, $hesklang;

    // Make sure selecting works correctly when tickets are grouped
    if (isset($hesk_settings['hesk-group-count'])) {
        $hesk_settings['hesk-group-count']++;
    } else {
        $hesk_settings['hesk-group-count'] = 1;
    }
	?>
    <div class="table-wrap ignore-overflow">
	<table class="table sindu-table ticket-list sindu_origin_table" id="default-table">
    <thead>
    <tr>
        <th class="table__first_th sindu_handle">
            <div class="checkbox-custom">
                <input type="checkbox" id="ticket_checkall<?php echo $hesk_settings['hesk-group-count']; ?>" name="checkall" value="2" onclick="hesk_changeAll(this, '<?php echo 'group' . $hesk_settings['hesk-group-count'] . "'"; ?>)">
                <label for="ticket_checkall<?php echo $hesk_settings['hesk-group-count']; ?>">&nbsp;</label>
            </div>
        </th>
        <?php
        $sort = hesk_GET('sort', 'status');
        $sort_direction = '';
        if (isset($_GET['asc'])) {
            $sort_direction = intval($_GET['asc']) == 0 ? 'desc' : 'asc';
        } else {
            $sort_direction = 'asc';
        }

        foreach ($hesk_settings['ticket_list'] as $field)
        {
            if (!key_exists($field, $hesk_settings['possible_ticket_list'])) {
                continue;
            }

            echo '<th class="sindu-handle '.($sort == $field ? $sort_direction : '').' '.($field == 'trackid' ? 'trackid' : '').'">
                <a href="' . $href . '?' . $query . $sort_possible[$field] . '&amp;sort=' . $field . '">
                    <div class="sort">
                        <span>' . $hesk_settings['possible_ticket_list'][$field] . '</span>
                        <i class="handle"></i>
                    </div>
                </a>
            </th>';
        }
        ?>
        <th class="sindu-handle <?php echo $sort == 'priority' ? $sort_direction : ''; ?>">
            <a href="<?php echo $href . '?' . $query . $sort_possible['priority'] . '&amp;sort='; ?>priority">
                <div class="sort">
                    <span><?php echo $hesklang['priority']; ?></span>
                    <i class="handle"></i>
                </div>
            </a>
        </th>
    </tr>
    </thead>
    <tbody>
	<?php
} // END hesk_print_list_head()
