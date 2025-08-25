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

if ( ! isset($can_view_ass_others)) {
    $can_view_ass_others = hesk_checkPermission('can_view_ass_others',0);
    $can_view_ass_by = hesk_checkPermission('can_view_ass_by',0);
    $can_view_unassigned = hesk_checkPermission('can_view_unassigned',0);
}

// Assignment
// -> SELF
$s_my[$fid] = empty($_GET['s_my']) ? 0 : 1;
// -> OTHERS
$s_ot[$fid] = empty($_GET['s_ot']) ? 0 : 1;
// -> UNASSIGNED
$s_un[$fid] = empty($_GET['s_un']) ? 0 : 1;
// -> Collaborate
$s_co[$fid] = 1;

// Overwrite by quick links? Ignore for ticket searches
if ( ! isset($is_quick_link))
{
    $is_quick_link = false;
}
// Quick link: assigned to me
elseif ($is_quick_link == 'my')
{
    $s_my[$fid] = 1;
    $s_ot[$fid] = 0;
    $s_un[$fid] = 0;
    $s_co[$fid] = 0;
}
// Quick link: tickets where I am collaborator
elseif ($is_quick_link == 'cbm')
{
    $s_my[$fid] = 0;
    $s_ot[$fid] = 0;
    $s_un[$fid] = 0;
    $s_co[$fid] = 1;
}
// Quick link: assigned to other
elseif ($is_quick_link == 'ot')
{
    $s_my[$fid] = 0;
    $s_ot[$fid] = 1;
    $s_un[$fid] = 0;
    $s_co[$fid] = 0;
}
// Quick link: unassigned
elseif ($is_quick_link == 'un')
{
    $s_my[$fid] = 0;
    $s_ot[$fid] = 0;
    $s_un[$fid] = 1;
    $s_co[$fid] = 0;
}

// Is assignment selection the same as a quick link?
if ($is_quick_link === false && (($can_view_ass_others || $can_view_ass_by) || $can_view_unassigned))
{
    if ($s_my[$fid] == 1 && $s_ot[$fid] == 0 && $s_un[$fid] == 0 && $s_co[$fid] == 1)
    {
        $is_quick_link = 'my';
        $s_co[$fid] = 0;
    }
    elseif ($s_my[$fid] == 0 && $s_ot[$fid] == 1 && $s_un[$fid] == 0 && $s_co[$fid] == 1)
    {
        $is_quick_link = 'ot';
        $s_co[$fid] = 0;
    }
    elseif ($s_my[$fid] == 0 && $s_ot[$fid] == 0 && $s_un[$fid] == 1 && $s_co[$fid] == 1)
    {
        $is_quick_link = 'un';
        $s_co[$fid] = 0;
    }
}

// -> Setup SQL based on selected ticket assignments

/* Make sure at least one is chosen */
if ( ! $s_my[$fid] && ! $s_ot[$fid] && ! $s_un[$fid] && $is_quick_link != 'cbm')
{
	$s_my[$fid] = 1;
	$s_ot[$fid] = 1;
	$s_un[$fid] = 1;
	$s_co[$fid] = 1;
	if (!defined('MAIN_PAGE'))
	{
		hesk_show_notice($hesklang['e_nose']);
	}
}

// Can view tickets assigned to others?
if ( ! $can_view_ass_others && ! $can_view_ass_by) {
    $s_ot[$fid] = 0;
}

// Can view unassigned tickets?
if ( ! $can_view_unassigned) {
    $s_un[$fid] = 0;
}

$my_user_id = intval($_SESSION['id']);
$sql_assignment = '';

// Show all
if ($s_my[$fid] == 1 && $s_ot[$fid] == 1 && $s_un[$fid] == 1 && $s_co[$fid] == 1) {
    if ($can_view_ass_others) {
        $sql_assignment .= "";
    } elseif ($can_view_ass_by) {
        $sql_assignment .= " AND ( `owner` IN (0, {$my_user_id}) OR `assignedby` = {$my_user_id} OR `w`.`user_id` = {$my_user_id} ) ";
    } else {
        die('Invalid view attempt (2)');
    }
}

// Assigned to me
if ($s_my[$fid] == 1 && $s_ot[$fid] == 0 && $s_un[$fid] == 0 && $s_co[$fid] == 0) {
    $sql_assignment .= " AND `owner` = {$my_user_id} ";
}

// Assigned to me + Assigned to others
if ($s_my[$fid] == 1 && $s_ot[$fid] == 1 && $s_un[$fid] == 0 && $s_co[$fid] == 0) {
    if ($can_view_ass_others) {
        $sql_assignment .= " AND `owner` <> 0 ";
    } elseif ($can_view_ass_by) {
        $sql_assignment .= " AND ( `owner` = {$my_user_id} OR `assignedby` = {$my_user_id} ) ";
    } else {
        die('Invalid view attempt (3)');
    }
}

// Assigned to me + Unassigned
if ($s_my[$fid] == 1 && $s_ot[$fid] == 0 && $s_un[$fid] == 1 && $s_co[$fid] == 0) {
    $sql_assignment .= " AND `owner` IN (0, {$my_user_id}) ";
}

// Assigned to me + Collaborator
if ($s_my[$fid] == 1 && $s_ot[$fid] == 0 && $s_un[$fid] == 0 && $s_co[$fid] == 1) {
    $sql_assignment .= " AND ( `owner` = {$my_user_id} OR `w`.`user_id` = {$my_user_id} ) ";
}

// Assigned to me + Assigned to others + Unassigned
if ($s_my[$fid] == 1 && $s_ot[$fid] == 1 && $s_un[$fid] == 1 && $s_co[$fid] == 0) {
    if ($can_view_ass_others) {
        $sql_assignment .= "";
    } elseif ($can_view_ass_by) {
        $sql_assignment .= " AND ( `owner` <> 99999 OR `assignedby` = {$my_user_id} ) ";
    } else {
        die('Invalid view attempt (4)');
    }
}

// Assigned to me + Assigned to others + Collaborator
if ($s_my[$fid] == 1 && $s_ot[$fid] == 1 && $s_un[$fid] == 0 && $s_co[$fid] == 1) {
    if ($can_view_ass_others) {
        $sql_assignment .= " AND ( `owner` <> 0 OR `w`.`user_id` = {$my_user_id} ) ";
    } elseif ($can_view_ass_by) {
        $sql_assignment .= " AND ( `owner` = {$my_user_id} OR `assignedby` = {$my_user_id} OR `w`.`user_id` = {$my_user_id} ) ";
    } else {
        die('Invalid view attempt (5)');
    }
}

// Assigned to me + Unassigned + Collaborator
if ($s_my[$fid] == 1 && $s_ot[$fid] == 0 && $s_un[$fid] == 1 && $s_co[$fid] == 1) {
    $sql_assignment .= " AND ( `owner` IN (0, {$my_user_id}) OR `w`.`user_id` = {$my_user_id} ) ";
}

// Assigned to me + Assigned to others + Unassigned + Collaborator
if ($s_my[$fid] == 1 && $s_ot[$fid] == 1 && $s_un[$fid] == 1 && $s_co[$fid] == 1) {
    if ($can_view_ass_others) {
        $sql_assignment .= " AND ( `owner` <> 99999 OR `w`.`user_id` = {$my_user_id} ) ";
    } elseif ($can_view_ass_by) {
        $sql_assignment .= " AND ( `owner` IN (0, {$my_user_id}) OR `assignedby` = {$my_user_id} OR `w`.`user_id` = {$my_user_id} ) ";
    } else {
        die('Invalid view attempt (6)');
    }
}

// Assigned to others (to others by me)
if ($s_my[$fid] == 0 && $s_ot[$fid] == 1 && $s_un[$fid] == 0 && $s_co[$fid] == 0) {
    $sql_assignment .= " AND (`owner` NOT IN (0, {$my_user_id}) ";

    if ( ! $can_view_ass_others) {
        if ($can_view_ass_by) {
            $sql_assignment .= " AND ( `assignedby` = {$my_user_id} OR `w`.`user_id` = {$my_user_id} ) ";
        } else {
            $sql_assignment .= " AND `w`.`user_id` = {$my_user_id} ";
        }
    }

    $sql_assignment .= " ) ";
}

// Assigned to others + Unassigned
if ($s_my[$fid] == 0 && $s_ot[$fid] == 1 && $s_un[$fid] == 1 && $s_co[$fid] == 0) {
    if ($can_view_ass_others) {
        $sql_assignment .= " AND `owner` <> {$my_user_id} ";
    } elseif ($can_view_ass_by) {
        $sql_assignment .= " AND `owner` <> {$my_user_id} AND `assignedby` = {$my_user_id} ";
    } else {
        die('Invalid view attempt (7)');
    }
}

// Assigned to others + Collaborator
if ($s_my[$fid] == 0 && $s_ot[$fid] == 1 && $s_un[$fid] == 0 && $s_co[$fid] == 1) {
    if ($can_view_ass_others) {
        $sql_assignment .= " AND ( `owner` NOT IN (0, {$my_user_id}) OR `w`.`user_id` = {$my_user_id} ) ";
    } elseif ($can_view_ass_by) {
        $sql_assignment .= " AND ( ( `owner` NOT IN (0, {$my_user_id}) AND `assignedby` = {$my_user_id} ) OR `w`.`user_id` = {$my_user_id} ) ";
    } else {
        die('Invalid view attempt (8)');
    }
}

// Assigned to others + Unassigned + Collaborator
if ($s_my[$fid] == 0 && $s_ot[$fid] == 1 && $s_un[$fid] == 1 && $s_co[$fid] == 1) {
    if ($can_view_ass_others) {
        $sql_assignment .= " AND ( `owner` <> {$my_user_id} OR `w`.`user_id` = {$my_user_id} ) ";
    } elseif ($can_view_ass_by) {
        $sql_assignment .= " AND ( ( `owner` <> {$my_user_id} AND `assignedby` = {$my_user_id} ) OR `w`.`user_id` = {$my_user_id} ) ";
    } else {
        die('Invalid view attempt (9)');
    }
}

// Unassigned
if ($s_my[$fid] == 0 && $s_ot[$fid] == 0 && $s_un[$fid] == 1 && $s_co[$fid] == 0) {
    $sql_assignment .= " AND `owner` = 0 ";
}

// Unassigned + Collaborator
if ($s_my[$fid] == 0 && $s_ot[$fid] == 0 && $s_un[$fid] == 1 && $s_co[$fid] == 1) {
    $sql_assignment .= " AND (`owner` = 0 OR `w`.`user_id` = {$my_user_id} ) ";
}

// Collaborator
if ($s_my[$fid] == 0 && $s_ot[$fid] == 0 && $s_un[$fid] == 0 && $s_co[$fid] == 1) {
    $sql_assignment .= " AND `w`.`user_id` = {$my_user_id} ";
}

$sql .= $sql_assignment;

