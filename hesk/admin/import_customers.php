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
define('LOAD_TABS',1);

/* Get all the required files and functions */
require(HESK_PATH . 'hesk_settings.inc.php');
require(HESK_PATH . 'inc/common.inc.php');
require(HESK_PATH . 'inc/admin_functions.inc.php');
require(HESK_PATH . 'inc/privacy_functions.inc.php');
require(HESK_PATH . 'inc/manage_customers_functions.inc.php');
hesk_load_database_functions();

hesk_session_start();
hesk_dbConnect();
hesk_isLoggedIn();

// Are customer accounts enabled?
if (empty($hesk_settings['customer_accounts'])) {
    hesk_error($hesklang['customer_accounts_disabled']);
}

// Check permissions for this feature
$can_man_customers = hesk_checkPermission('can_man_customers');

// This is a sensitive page, double-check user authentication
hesk_check_user_elevation('import_customers.php');

//-- We're utilizing the existing attachments functionality, but with a bunch of customizations.
require_once(HESK_PATH . 'inc/attachments.inc.php');

define('ATTACHMENTS', 1);
define('CSV', 1);
/* Print header */
require_once(HESK_PATH . 'inc/header.inc.php');

/* Print main manage users page */
require_once(HESK_PATH . 'inc/show_admin_nav.inc.php');

/* This will handle error, success and notice messages */
if (!hesk_SESSION(array('userdata', 'errors'))) {
    hesk_handle_messages();
}
?>
<div class="main__content team">
    <section class="team__head">
        <h2>
            <?php echo $hesklang['import_customers']; ?>
            <div class="tooltype right out-close">
                <svg class="icon icon-info">
                    <use xlink:href="<?php echo HESK_PATH; ?>img/sprite.svg#icon-info"></use>
                </svg>
                <div class="tooltype__content">
                    <div class="tooltype__wrapper">
                        <?php echo $hesklang['import_customers_tip']; ?>
                    </div>
                </div>
            </div>
        </h2>
    </section>
    <div class="table-wrap import" data-step="1">
        <div class="import-steps">
            <ul class="step-bar">
                <li data-link="1" data-all="3"><?php echo $hesklang['import_customer_select_file']; ?></li>
                <li data-link="2" data-all="3"><?php echo $hesklang['import_customer_select_columns']; ?></li>
                <li data-link="3" data-all="3"><?php echo $hesklang['import_customer_upload_customers']; ?></li>
            </ul>
        </div>
        <div class="step-slider form">
            <div class="step-item step-1">
                <div>
                    <strong>1. <?php echo $hesklang['import_customer_step1_instructions']; ?></strong>
                    <ul>
                        <li><?php echo $hesklang['import_customer_upload_requirements_1']; ?></li>
                        <li><?php echo $hesklang['import_customer_upload_requirements_2']; ?></li>
                        <li><?php echo $hesklang['import_customer_upload_requirements_3']; ?><br><br></li>
                    </ul>
                </div>
                <div>
                    <strong><?php echo $hesklang['import_customer_sample']; ?></strong>
                    <ul>
                        <li><a href="samples/customer-import-CSV-example-US.csv"><?php echo $hesklang['import_customer_sample_1']; ?></a></li>
                        <li><a href="samples/customer-import-CSV-example-EU.csv"><?php echo $hesklang['import_customer_sample_2']; ?></a><br><br></li>
                    </ul>
                </div>
                <div class="form-group short">
                    <label for="separator-column"><strong>2. <?php echo $hesklang['import_customer_step1_separator']; ?></strong></label>
                    <input id="separator-column" type="text" class="form-control" value=",">
                </div>
                <div><strong>3. <?php echo $hesklang['import_customer_step1_note']; ?></strong></div>
                <div class="attachments" id="attachments-container">
                    <?php
                    build_dropzone_markup(true, 'upload_filedrop', 1, false);
                    ?>
                </div>
            </div>
            <div class="step-item step-2">
                <div><strong><?php echo $hesklang['file']; ?>: <span data-field="file-name"></span></strong></div>
                <div class="form-group">
                    <label for="name-column"><?php echo $hesklang['import_customer_column_name']; ?></label>
                    <select id="name-column" class="selectized">
                        <option value="-1"><?php echo $hesklang['select']; ?></option>
                    </select>
                    <div class="form-control__error"><?php echo $hesklang['import_customer_name_or_email_required']; ?></div>
                </div>
                <div class="form-group">
                    <label for="email-column"><?php echo $hesklang['import_customer_column_email']; ?></label>
                    <select id="email-column" class="selectized">
                        <option value="-1"><?php echo $hesklang['select']; ?></option>
                    </select>
                    <div class="form-control__error"><?php echo $hesklang['import_customer_name_or_email_required']; ?></div>
                </div>
                <div class="form-group">
                    <label for="password-column"><?php echo $hesklang['import_customer_column_pass']; ?></label>
                    <select id="password-column" class="selectized">
                        <option value="-1"><?php echo $hesklang['select']; ?></option>
                    </select>
                </div>
                <p><?php echo $hesklang['import_customer_step2_note']; ?></p>
                <div class="action-buttons">
                    <a href="import_customers.php" class="btn btn--blue-border"><?php echo $hesklang['wizard_back']; ?></a>
                    <button type="submit" class="btn btn-full next" ripple="ripple" data-submit-step="2"><?php echo $hesklang['wizard_next']; ?></button>
                </div>
            </div>
            <div class="step-item step-3">
                <div class="notification blue" id="step-3-pending">
                    <?php echo $hesklang['import_customer_step3_note']; ?>
                </div>
                <div class="notification orange" id="step-3-partial-success" style="display: none">
                    <?php echo $hesklang['import_customer_step3_complete_some_failed']; ?>
                </div>
                <div class="notification green" id="step-3-total-success" style="display: none">
                    <?php echo $hesklang['import_customer_step3_complete']; ?>
                </div>
                <div class="upload-stats">
                    <div>
                        <p><?php echo $hesklang['import_customer_step3_successful_imports']; ?></p>
                        <p class="value"><span data-stat="successes">0</span></p>
                    </div>
                    <div>
                        <p><?php echo $hesklang['import_customer_step3_failed_imports']; ?></p>
                        <p class="value"><span data-stat="failures">0</span></p>
                    </div>
                    <div>
                        <p><?php echo $hesklang['import_customer_step3_progress']; ?></p>
                        <p class="value">
                            <span data-stat="finished-uploads">0</span>/<span data-stat="total-uploads">0</span>
                            (<span data-stat="percent-uploaded">0</span>%)
                        </p>
                    </div>
                </div>
                <table class="table sindu-table">
                    <thead>
                    <tr>
                        <th><?php echo $hesklang['name']; ?></th>
                        <th><?php echo $hesklang['email']; ?></th>
                        <th><?php echo $hesklang['status']; ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php // Filled in via JS ?>
                    </tbody>
                </table>
                <template id="row-template">
                    <tr data-attr="index">
                        <td data-attr="name"></td>
                        <td data-attr="email"></td>
                        <td data-attr="status"></td>
                    </tr>
                </template>
            </div>
        </div>
    </div>
</div>
<script>
    const UPLOAD = {
        name: '',
        contents: '',
        columnIndexes: {
            name: -1,
            email: -1,
            password: -1,
        },
        successfulUploads: 0,
        failedUploads: 0,
        totalUploads: function() {
            return this.successfulUploads + this.failedUploads;
        },
        addColumnsToDropdowns: function() {
            const $selectizedDropdowns = $('.selectized');
            $.each($selectizedDropdowns, function(idx, dropdown) {
                let index = 0;
                for (const column of UPLOAD.getColumns()) {
                    const opt = document.createElement('option');
                    opt.value = (index++).toString();
                    opt.innerHTML = column;
                    dropdown.appendChild(opt);
                }
            });
            $selectizedDropdowns.selectize();
            $('.selectize-control:not(.read-write) .selectize-input input').prop('readonly', true);
        },
        getColumns: function() {
            return UPLOAD.contents[0];
        }
    };

    Dropzone.autoDiscover = false;
    const importDropzone = new Dropzone('#upload_filedrop', {
        url: '#',
        autoProcessQueue: false,
        dictDefaultMessage: '<?php echo hesk_makeJsString($hesklang['attachment_viewer_message']); ?>',
        clickable: '.dz-click-upload_filedrop',
        accept: function(file, done) {
            const reader = new FileReader();
            const dz = this;
            reader.addEventListener('loadend', function(event) {
                dz.emit('success', file);
                dz.emit('complete', file);

                UPLOAD.contents = $.csv.toArrays(reader.result, {
                    separator: $('#separator-column').val()
                });
                UPLOAD.name = file.name;
                goToStep2();
            });
            reader.readAsText(file);
        }
    });

    function goToStep2() {
        $('[data-step="1"]').attr('data-step', 2);
        $('.step-2').find('[data-field="file-name"]').text(UPLOAD.name);
        UPLOAD.addColumnsToDropdowns();
    }

    $('[data-submit-step="2"]').click(function() {
        const $formValidationErrors = $('.step-2').find('.form-control__error');
        $formValidationErrors.hide();
        const nameColumnIndex = parseInt($('#name-column').val(), 10);
        const emailColumnIndex = parseInt($('#email-column').val(), 10);
        if (nameColumnIndex === -1 && emailColumnIndex === -1) {
            $formValidationErrors.show();
        }

        UPLOAD.columnIndexes.name = nameColumnIndex;
        UPLOAD.columnIndexes.email = emailColumnIndex;
        UPLOAD.columnIndexes.password = parseInt($('#password-column').val(), 10);
        goToStep3();
    });

    function goToStep3() {
        $('[data-step="2"]').attr('data-step', 3);

        //-- Output all records to the table
        const rows = UPLOAD.contents.slice(1);
        document.querySelector('[data-stat="total-uploads"]').innerHTML = rows.length.toString();
        let index = 0
        for (const row of rows) {
            const template = document.querySelector('#row-template');

            if (UPLOAD.columnIndexes.name > -1) {
                template.content.querySelector('[data-attr="name"]').textContent = row[UPLOAD.columnIndexes.name];
            }
            if (UPLOAD.columnIndexes.email > -1) {
                template.content.querySelector('[data-attr="email"]').textContent = row[UPLOAD.columnIndexes.email];
            }
            template.content.querySelector('[data-attr="index"]').setAttribute('data-customer-index', index++);
            template.content.querySelector('[data-attr="status"]').textContent = '<?php echo hesk_makeJsString($hesklang['import_customer_step3_pending']); ?>';

            const clone = document.importNode(template.content, true);
            document.querySelector('.step-3 tbody').appendChild(clone);
        }

        // Index 0 = headers
        doUpload(1);
    }

    function doUpload(index) {
        if (index >= UPLOAD.contents.length) {
            document.querySelector('#step-3-pending').style.display = 'none';
            if (UPLOAD.failedUploads === 0) {
                document.querySelector('#step-3-total-success').style.display = 'block';
            } else {
                const warningAlert = document.querySelector('#step-3-partial-success');
                warningAlert.style.display = 'block';
                const existingText = warningAlert.innerHTML;
                warningAlert.innerHTML = existingText.replace('%s', UPLOAD.failedUploads);
            }
            return;
        }

        const record = UPLOAD.contents[index];
        const requestBody = {
            name: UPLOAD.columnIndexes.name > -1 ? record[UPLOAD.columnIndexes.name] : '',
            email: UPLOAD.columnIndexes.email > -1 ? record[UPLOAD.columnIndexes.email] : '',
            password: UPLOAD.columnIndexes.password > -1 ? record[UPLOAD.columnIndexes.password] : ''
        };
        const customerStatus = document.querySelector('.step-3 [data-customer-index="'+ (index - 1) +'"] [data-attr="status"]');
        customerStatus.innerHTML = '<?php echo hesk_makeJsString($hesklang['import_customer_step3_importing']); ?>';

        $.ajax({
            url: 'ajax/create_customer.php',
            method: 'POST',
            data: requestBody,
            dataType: 'json',
            success: function(res) {
                customerStatus.innerHTML = '<?php echo hesk_makeJsString($hesklang['success']); ?>';
                customerStatus.classList.add('success');
                incrementSuccess();

                doUpload(index + 1);
            },
            error: function(err) {
                customerStatus.innerHTML = '<?php echo hesk_makeJsString($hesklang['error']) ?> - ';
                customerStatus.innerHTML += JSON.parse(err.responseText).message;
                customerStatus.classList.add('failed');
                incrementFailed();

                doUpload(index + 1);
            }
        });
    }

    function incrementSuccess() {
        UPLOAD.successfulUploads++;
        document.querySelector('[data-stat="successes"]').innerHTML = UPLOAD.successfulUploads;
        updateProgress();
    }

    function incrementFailed() {
        UPLOAD.failedUploads++;
        document.querySelector('[data-stat="failures"]').innerHTML = UPLOAD.failedUploads;
        updateProgress();
    }

    function updateProgress() {
        document.querySelector('[data-stat="finished-uploads"]').innerHTML = UPLOAD.totalUploads();

        document.querySelector('[data-stat="percent-uploaded"]').innerHTML =
            ((UPLOAD.totalUploads() / (UPLOAD.contents.length - 1)) * 100).toFixed(0);
    }
</script>
<?php

require_once(HESK_PATH . 'inc/footer.inc.php');
