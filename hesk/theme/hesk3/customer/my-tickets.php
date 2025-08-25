<?php
global $hesk_settings, $hesklang;

// This guard is used to ensure that users can't hit this outside of actual HESK code
if (!defined('IN_SCRIPT')) {
    die();
}

/**
 * @var array $customerUserContext - User info for the customer.
 * @var array $tickets - Page of tickets to be displayed
 * @var array $ticketCounts - Count of open and closed tickets.  Array indices:
 *     open - Number of open tickets in search results
 *     closed - Number of closed tickets in search results
 * @var string $searchCriteria - Search criteria the user entered.  Empty string if no search was performed.
 * @var string $searchType - Search type (trackid, subject, message) from the user.  Empty string if no search was performed.
 * @var string $status - Ticket status to filter down by. Either 'ALL' (open and closed), 'OPEN', or 'CLOSED'.  Default: 'ALL'
 * @var array $ordering - Column and direction ticket results are sorted by.
 *     orderBy - The column tickets are currently ordered by
 *     orderDirection - Direction results are ordered (asc or desc)
 * @var array $paging - Array of paging-related details.  Can be used to build a proper pager component.
 *     pageNumber - Current page number of results
 *     pageSize - The requested page size
 */
$totalCount = $ticketCounts['open'] + $ticketCounts['closed'];
$totalNumberOfPages = intval($totalCount / $paging['pageSize']);
if ($totalCount % $paging['pageSize'] !== 0) {
    $totalNumberOfPages++;
}

require_once(TEMPLATE_PATH . 'customer/util/alerts.php');
require_once(TEMPLATE_PATH . 'customer/util/my-tickets-search.php');
require_once(TEMPLATE_PATH . 'customer/util/pager.php');
require_once(TEMPLATE_PATH . 'customer/partial/login-navbar-elements.php');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title><?php echo $hesk_settings['hesk_title']; ?></title>
    <meta http-equiv="X-UA-Compatible" content="IE=Edge" />
    <meta name="viewport" content="width=device-width,minimum-scale=1.0,maximum-scale=1.0" />
    <?php include(HESK_PATH . 'inc/favicon.inc.php'); ?>
    <meta name="format-detection" content="telephone=no" />
    <link rel="stylesheet" media="all" href="<?php echo TEMPLATE_PATH; ?>customer/css/app<?php echo $hesk_settings['debug_mode'] ? '' : '.min'; ?>.css?<?php echo $hesk_settings['hesk_version']; ?>" />
    <!--[if IE]>
    <link rel="stylesheet" media="all" href="<?php echo TEMPLATE_PATH; ?>customer/css/ie9.css" />
    <![endif]-->
    <style>
        #kb_search {
            width: 60%;
        }
        #search-button {
            width: 20%;
            margin-left: 10px;
            height: inherit;
        }
        #search-by {
            height: 56px;
        }

        .selectize-input {
            height: 56px !important;
        }
    </style>
    <?php include(TEMPLATE_PATH . '../../head.txt'); ?>
</head>

<body class="cust-help">
<?php include(TEMPLATE_PATH . '../../header.txt'); ?>
<?php renderCommonElementsAfterBody(); ?>
<div class="wrapper">
    <main class="main" id="maincontent">
        <header class="header">
            <div class="contr">
                <div class="header__inner">
                    <a href="<?php echo $hesk_settings['hesk_url']; ?>" class="header__logo">
                        <?php echo $hesk_settings['hesk_title']; ?>
                    </a>
                    <?php renderLoginNavbarElements($customerUserContext); ?>
                    <?php renderNavbarLanguageSelect(); ?>
                </div>
            </div>
        </header>
        <div class="breadcrumbs">
            <div class="contr">
                <div class="breadcrumbs__inner">
                    <a href="<?php echo $hesk_settings['site_url']; ?>">
                        <span><?php echo $hesk_settings['site_title']; ?></span>
                    </a>
                    <svg class="icon icon-chevron-right">
                        <use xlink:href="<?php echo TEMPLATE_PATH; ?>customer/img/sprite.svg#icon-chevron-right"></use>
                    </svg>
                    <a href="<?php echo $hesk_settings['hesk_url']; ?>">
                        <span><?php echo $hesk_settings['hesk_title']; ?></span>
                    </a>
                    <svg class="icon icon-chevron-right">
                        <use xlink:href="<?php echo TEMPLATE_PATH; ?>customer/img/sprite.svg#icon-chevron-right"></use>
                    </svg>
                    <div class="last"><?php echo $hesklang['customer_my_tickets_heading']; ?></div>
                </div>
            </div>
        </div>
        <div class="main__content">
            <div class="contr">
                <?php hesk3_show_messages($serviceMessages); ?>
                <div class="help-search">
                    <h1 class="search__title"><?php echo $hesklang['customer_my_tickets_heading']; ?></h1>
                    <?php displayMyTicketsSearch($searchType, $searchCriteria); ?>
                </div>
                <div class="table-wrap">
                    <div class="table">
                        <table id="default-table" class="table sindu-table">
                            <thead>
                            <tr>
                                <?php if ($hesk_settings['sequential']): ?>
                                    <th class="sindu-handle <?php echo $ordering['orderBy'] === 'id' ? hesk_mb_strtolower($ordering['orderDirection']) : '' ?>">
                                        <a href="<?php echo build_sort_url('id', $ordering, $searchType, $searchCriteria, $paging); ?>" aria-label="<?php echo ($hesklang['sort_by'] . ' ' .  $hesklang['id']); ?>">
                                            <div class="sort">
                                                <span><?php echo $hesklang['id']; ?></span>
                                                <i class="handle"></i>
                                            </div>
                                        </a>
                                    </th>
                                <?php endif; ?>
                                <th class="sindu-handle <?php echo $ordering['orderBy'] === 'trackid' ? hesk_mb_strtolower($ordering['orderDirection']) : '' ?>">
                                    <a href="<?php echo build_sort_url('trackid', $ordering, $searchType, $searchCriteria, $paging); ?>" aria-label="<?php echo ($hesklang['sort_by'] . ' ' .  $hesklang['trackID']); ?>">
                                        <div class="sort">
                                            <span><?php echo $hesklang['trackID']; ?></span>
                                            <i class="handle"></i>
                                        </div>
                                    </a>
                                </th>
                                <th class="sindu-handle <?php echo $ordering['orderBy'] === 'lastchange' ? hesk_mb_strtolower($ordering['orderDirection']) : '' ?>">
                                    <a href="<?php echo build_sort_url('lastchange', $ordering, $searchType, $searchCriteria, $paging); ?>" aria-label="<?php echo ($hesklang['sort_by'] . ' ' .  $hesklang['last_update']); ?>">
                                        <div class="sort">
                                            <span><?php echo $hesklang['last_update']; ?></span>
                                            <i class="handle"></i>
                                        </div>
                                    </a>
                                </th>
                                <th class="sindu-handle <?php echo $ordering['orderBy'] === 'subject' ? hesk_mb_strtolower($ordering['orderDirection']) : '' ?>">
                                    <a href="<?php echo build_sort_url('subject', $ordering, $searchType, $searchCriteria, $paging); ?>" aria-label="<?php echo ($hesklang['sort_by'] . ' ' .  $hesklang['subject']); ?>">
                                        <div class="sort">
                                            <span><?php echo $hesklang['subject']; ?></span>
                                            <i class="handle"></i>
                                        </div>
                                    </a>
                                </th>
                                <th class="sindu-handle <?php echo $ordering['orderBy'] === 'status' ? hesk_mb_strtolower($ordering['orderDirection']) : '' ?>">
                                    <a href="<?php echo build_sort_url('status', $ordering, $searchType, $searchCriteria, $paging); ?>" aria-label="<?php echo ($hesklang['sort_by'] . ' ' .  $hesklang['status']); ?>">
                                        <div class="sort">
                                            <span><?php echo $hesklang['status']; ?></span>
                                            <i class="handle"></i>
                                        </div>
                                    </a>
                                </th>
                                <th class="sindu-handle <?php echo $ordering['orderBy'] === 'priority' ? hesk_mb_strtolower($ordering['orderDirection']) : '' ?>">
                                    <a href="<?php echo build_sort_url('priority', $ordering, $searchType, $searchCriteria, $paging); ?>" aria-label="<?php echo ($hesklang['sort_by'] . ' ' .  $hesklang['priority']); ?>">
                                        <div class="sort">
                                            <span><?php echo $hesklang['priority']; ?></span>
                                            <i class="handle"></i>
                                        </div>
                                    </a>
                                </th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (count($tickets) === 0): ?>
                                <td colspan="<?php echo ($hesk_settings['sequential'] ? 6 : 5) ?> "><span role="alert"><?php echo $hesklang['no_results_found']; ?></span></td>
                            <?php endif; ?>
                            <?php foreach ($tickets as $ticket): ?>
                                <tr <?php if (intval($ticket['status_id']) === 2) { echo 'class="new"'; } ?>>
                                    <?php if ($hesk_settings['sequential']): ?>
                                        <td><?php echo $ticket['id']; ?></td>
                                    <?php endif; ?>
                                    <td>
                                        <a href="ticket.php?track=<?php echo stripslashes($ticket['trackid']); ?>">
                                            <?php echo $ticket['trackid']; ?>
                                        </a>
                                    </td>
                                    <td><?php echo $ticket['lastchange']; ?></td>
                                    <td>
                                        <a href="ticket.php?track=<?php echo stripslashes($ticket['trackid']); ?>">
                                            <?php echo $ticket['subject']; ?>
                                        </a>
                                    </td>
                                    <td><?php echo $ticket['status']; ?></td>
                                    <td>
                                        <?php $data_style = 'border-top-color:'.$hesk_settings['priorities'][$ticket['priority']]['color'].';border-left-color:'.$hesk_settings['priorities'][$ticket['priority']]['color'].';border-bottom-color:'.$hesk_settings['priorities'][$ticket['priority']]['color'].';' ?>
                                       <div class="value with-label priority" data-value="<?php echo $hesk_settings['priorities'][$ticket['priority']]['name']; ?>">
                                       <div class="priority_img" style="<?php echo $data_style; ?>"></div><span class="ml5"><?php echo $hesk_settings['priorities'][$ticket['priority']]['name']; ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="pager">
                        <?php echo sprintf($hesklang['tickets_on_pages'], $totalCount, $totalNumberOfPages); ?>
                        <?php
                        output_pager($totalNumberOfPages, $paging['pageNumber'], "my_tickets.php?search-by={$searchType}&search={$searchCriteria}");
                        ?>
                    </div>
                </div>
            </div>
        </div>
<?php
/*******************************************************************************
The code below handles HESK licensing and must be included in the template.

Removing this code is a direct violation of the HESK End User License Agreement,
will void all support and may result in unexpected behavior.

To purchase a HESK license and support future HESK development please visit:
https://www.hesk.com/buy.php
*******************************************************************************/
$hesk_settings['hesk_license']('Qo8Zm9vdGVyIGNsYXNzPSJmb290ZXIiPg0KICAgIDxwIGNsY
XNzPSJ0ZXh0LWNlbnRlciI+UG93ZXJlZCBieSA8YSBocmVmPSJodHRwczovL3d3dy5oZXNrLmNvbSIgY
2xhc3M9ImxpbmsiPkhlbHAgRGVzayBTb2Z0d2FyZTwvYT4gPHNwYW4gY2xhc3M9ImZvbnQtd2VpZ2h0L
WJvbGQiPkhFU0s8L3NwYW4+PGJyPk1vcmUgSVQgZmlyZXBvd2VyPyBUcnkgPGEgaHJlZj0iaHR0cHM6L
y93d3cuc3lzYWlkLmNvbS8/dXRtX3NvdXJjZT1IZXNrJmFtcDt1dG1fbWVkaXVtPWNwYyZhbXA7dXRtX
2NhbXBhaWduPUhlc2tQcm9kdWN0X1RvX0hQIiBjbGFzcz0ibGluayI+U3lzQWlkPC9hPjwvcD4NCjwvZ
m9vdGVyPg0K',"\104", "a809404e0adf9823405ee0b536e5701fb7d3c969");
/*******************************************************************************
END LICENSE CODE
*******************************************************************************/
?>
    </main>
</div>
<?php include(TEMPLATE_PATH . '../../footer.txt'); ?>
<script src="<?php echo TEMPLATE_PATH; ?>customer/js/jquery-3.5.1.min.js"></script>
<script src="<?php echo TEMPLATE_PATH; ?>customer/js/hesk_functions.js?<?php echo $hesk_settings['hesk_version']; ?>"></script>
<script src="<?php echo TEMPLATE_PATH; ?>customer/js/svg4everybody.min.js"></script>
<script src="<?php echo TEMPLATE_PATH; ?>customer/js/selectize.min.js?<?php echo $hesk_settings['hesk_version']; ?>"></script>
<script src="<?php echo TEMPLATE_PATH; ?>customer/js/app<?php echo $hesk_settings['debug_mode'] ? '' : '.min'; ?>.js?<?php echo $hesk_settings['hesk_version']; ?>"></script>
<script><?php outputSearchJavascript(); ?></script>
</body>

</html>
<?php
function build_sort_url($sortField, $ordering, $searchType, $searchCriteria, $paging) {
    $originalUrl = "my_tickets.php?search-by={$searchType}&search={$searchCriteria}&page-number={$paging['pageNumber']}&page-size={$paging['pageSize']}&order-by={$ordering['orderBy']}&order-direction={$ordering['orderDirection']}";

    $targetSortDirection = $ordering['orderDirection'] === 'asc' && $sortField === $ordering['orderBy'] ? 'desc' : 'asc';
    $encodedField = urlencode($sortField);

    $new_url = str_replace("order-by={$ordering['orderBy']}", "order-by={$encodedField}", $originalUrl);
    $new_url = str_replace("order-direction={$ordering['orderDirection']}", "order-direction=", $new_url);
    return str_replace("order-direction=", "order-direction={$targetSortDirection}", $new_url);
}
