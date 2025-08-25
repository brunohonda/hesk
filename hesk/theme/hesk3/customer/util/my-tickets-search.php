<?php
// This guard is used to ensure that users can't hit this outside of actual HESK code
if (!defined('IN_SCRIPT')) {
    die();
}

function displayMyTicketsSearch($searchType, $searchCriteria) {
    global $hesklang;
    ?>

        <form action="my_tickets.php" method="get" aria-label="<?php echo $hesklang['customer_my_tickets_search_for_tickets']; ?>" style="display: inline; margin: 0;" name="searchform">
            <div class="search__form">
                <div class="form-group">
                    <button class="btn search__submit">
                        <svg class="icon icon-search">
                            <use xlink:href="<?php echo TEMPLATE_PATH; ?>customer/img/sprite.svg#icon-search"></use>
                        </svg>
                    </button>
                    <input id="kb_search" name="search" class="form-control" type="text"
                           placeholder="<?php echo $hesklang['customer_my_tickets_search_for_tickets']; ?>"
                           value="<?php echo stripslashes($searchCriteria); ?>">
                    <select name="search-by" id="search-by">
                        <option value="trackid" <?php if ($searchType === 'trackid') { echo 'selected'; } ?>>
                            <?php echo $hesklang['trackID']; ?>
                        </option>
                        <option value="subject" <?php if ($searchType === 'subject') { echo 'selected'; } ?>>
                            <?php echo $hesklang['subject']; ?>
                        </option>
                        <option value="message" <?php if ($searchType === 'message') { echo 'selected'; } ?>>
                            <?php echo $hesklang['message']; ?>
                        </option>
                    </select>
                    <button id="search-button" type="submit" class="btn btn-full"><?php echo $hesklang['search']; ?></button>
                </div>
            </div>
        </form>
    <?php
}

function outputSearchJavascript() {
    global $hesklang;
    ?>
    $('#search-by').selectize({
        onInitialize: function () {
            var $input = this.$control; // Selectize input container
            $input.attr({
                "role": "combobox",
                "aria-expanded": "false",
                "aria-label": "<?php echo $hesklang['wsel']; ?>", // Adjust label as needed
                "aria-controls": this.$dropdown.attr("id"), // Links input to dropdown
                "tabindex": "0" // Ensure it is focusable
            });
            this.$dropdown.attr("role", "listbox"); // Dropdown should be a listbox
            this.$dropdown.find("[data-value]").attr("role", "option"); // Each item is an option
        },
        onDropdownOpen: function ($dropdown) {
            this.$control.attr("aria-expanded", "true");
        },
        onDropdownClose: function ($dropdown) {
            this.$control.attr("aria-expanded", "false");
        }
    });
    <?php
}