<?php
global $hesk_settings, $hesklang;
/**
 * @var array $tickets
 * @var boolean $showStaffOnlyFields
 */

// This guard is used to ensure that users can't hit this outside of actual HESK code
if (!defined('IN_SCRIPT')) {
    die();
}

if ($hesk_settings['barcode']['print'] && ( ! $hesk_settings['barcode']['staff_only'] || $showStaffOnlyFields)) {
    require(HESK_PATH . 'inc/tecnick/autoload.php');
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo $hesk_settings['hesk_title']; ?></title>
    <meta http-equiv="Content-Type" content="text/html; charset=<?php echo $hesklang['ENCODING']; ?>">
    <style type="text/css">
        body, table, td, p {
            color: black;
            font-family: Verdana, Geneva, Arial, Helvetica, sans-serif;
            font-size: <?php echo $hesk_settings['print_font_size']; ?>px;
            word-wrap: break-word;
            word-break: break-word;
        }

        td:first-child {
            vertical-align: top;
        }

        table {
            border-collapse: collapse;
        }

        hr {
            border: 0;
            color: #9e9e9e;
            background-color: #9e9e9e;
            height: 1px;
            width: 100%;
            text-align: left;
        }
    </style>
</head>
<body onload="window.print()">
<?php foreach ($tickets as $ticket): ?>

<?php
// generate a barcode
if ($hesk_settings['barcode']['print'] && ( ! $hesk_settings['barcode']['staff_only'] || $showStaffOnlyFields)) {
    $padding_bottom = ($hesk_settings['barcode']['format'] == 'png') ? 20 : 2;
    $barcode = new \Com\Tecnick\Barcode\Barcode();
    $bobj = $barcode->getBarcodeObj(
        $hesk_settings['barcode']['type'],   // barcode type
        $ticket['trackid'],                  // data string to encode
        $hesk_settings['barcode']['width'],  // bar width (use absolute or negative value as multiplication factor)
        $hesk_settings['barcode']['height'], // bar height (use absolute or negative value as multiplication factor)
        $hesk_settings['barcode']['color'],  // foreground color
        array(2, 2, $padding_bottom, 2)      // padding (use absolute or negative values as multiplication factors)
        )->setBackgroundColor($hesk_settings['barcode']['bg']); // background color

    if ($hesk_settings['barcode']['format'] == 'png') {
        echo '<img alt="Barcode" src="data:image/png;base64,'.base64_encode($bobj->getPngData()).'">';
    } else {
        echo '<p style="font-family:monospace;">' . $bobj->getSvgCode() . '</p>';
    }
}
?>

    <?php
    $requester = array_filter($ticket['customers'], function($customer) { return $customer['customer_type'] === 'REQUESTER'; });
    $followers = array_filter($ticket['customers'], function($customer) { return $customer['customer_type'] === 'FOLLOWER'; });
    ?>
    <table border="0">
        <tr>
            <td><?php echo $hesklang['subject']; ?>:</td>
            <td><b><?php echo $ticket['subject']; ?></b></td>
        </tr>
        <?php if ($hesk_settings['sequential'] || $showStaffOnlyFields): ?>
        <tr>
            <td><?php echo $hesklang['seqid']; ?>:</td>
            <td><?php echo $ticket['id']; ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <td><?php echo $hesklang['trackID']; ?>:</td>
            <td><?php echo $ticket['trackid']; ?></td>
        </tr>
        <tr>
            <td><?php echo $hesklang['ticket_status']; ?>:</td>
            <td><?php echo $ticket['status']; ?></td>
        </tr>
        <tr>
            <td><?php echo $hesklang['created_on']; ?>:</td>
            <td><?php echo $ticket['dt']; ?></td>
        </tr>
        <tr>
            <td><?php echo $hesklang['last_update']; ?>:</td>
            <td><?php echo $ticket['lastchange']; ?></td>
        </tr>
        <?php if ($ticket['owner'] != ''): ?>
            <tr>
                <td><?php echo $hesklang['taso3']; ?></td>
                <td><?php echo $ticket['owner']; ?></td>
            </tr>
        <?php endif; ?>
        <tr>
            <td><?php echo $hesklang['last_replier']; ?>:</td>
            <td><?php echo $ticket['repliername']; ?></td>
        </tr>
        <tr>
            <td><?php echo $hesklang['category']; ?>:</td>
            <td><?php echo $ticket['categoryName']; ?></td>
        </tr>
        <?php if ($showStaffOnlyFields): ?>
            <tr>
                <td><?php echo $hesklang['ts']; ?>:</td>
                <td><?php echo $ticket['time_worked']; ?></td>
            </tr>
            <tr>
                <td><?php echo $hesklang['due_date']; ?>:</td>
                <td><?php echo $ticket['due_date']; ?></td>
            </tr>
            <tr>
                <td><?php echo $hesklang['ip']; ?>:</td>
                <td><?php echo $ticket['ip']; ?></td>
            </tr>
            <tr>
                <td><?php echo $hesklang['m_from']; ?>:</td>
                <td><?php echo count($requester) ? hesk_output_customer_name_and_email(reset($requester)) : $hesklang['anon_name']; ?></td>
            </tr>
            <tr>
                <td><?php echo $hesklang['cc']; ?>:</td>
                <td>
                    <?php
                    $first = true;
                    foreach ($followers as $follower) {
                        if (!$first) {
                            echo '<br>';
                        }
                        echo hesk_output_customer_name_and_email($follower);

                        $first = false;
                    }
                    ?>
                </td>
            </tr>
        <?php
        endif;
        foreach ($ticket['custom_fields'] as $customField): ?>
            <tr>
                <td><?php echo $customField['name']; ?></td>
                <td><?php echo $customField['value']; ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <?php if (count($ticket['notes'])): ?>
        <?php foreach ($ticket['notes'] as $note): ?>
            <p><?php echo $hesklang['noteby']; ?> <b><?php echo ($note['name'] ? $note['name'] : $hesklang['e_udel']); ?></b></i> - <?php echo hesk_date($note['dt'], true); ?><br>
            <?php echo strlen($note['message']) ? $note['message'] : '<i>no message</i>'; ?></p>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($ticket['message_html'] != ''): ?>
        <p><?php echo $ticket['message_html']; ?></p>
    <?php endif; ?>

    <?php foreach ($ticket['replies'] as $reply): ?>
        <hr>
        <table border="0">
            <tr>
                <td><?php echo $hesklang['date']; ?>:</td>
                <td><?php echo $reply['dt']; ?></td>
            </tr>
            <tr>
                <td><?php echo $hesklang['name']; ?>:</td>
                <td><?php echo $reply['name']; ?></td>
            </tr>
        </table>
        <p><?php echo $reply['message_html']; ?></p>
    <?php endforeach; ?>
    <div style="page-break-after: always;"><?php echo $hesklang['end_ticket']; ?></div>
<?php endforeach; ?>
</body>
</html>
