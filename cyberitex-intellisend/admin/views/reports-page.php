<?php
/**
 * Admin page for email reports and logs
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Render the reports page
 */
function intellisend_render_reports_page_content() {
    // Get settings
    $settings = IntelliSend_Database::get_settings();

    // Get providers for filtering
    $providers = IntelliSend_Database::get_providers();

    // Get routing rules for filtering
    $routing_rules = IntelliSend_Database::get_routing_rules();

    // Pagination
    $page = isset( $_GET['paged'] ) ? intval( $_GET['paged'] ) : 1;
    $per_page = 20;

    // Filters
    $filters = array();
    $filters['per_page'] = $per_page;
    $filters['page'] = $page;

    // Sorting parameters
    if ( isset( $_GET['orderby'] ) && ! empty( $_GET['orderby'] ) ) {
        $filters['orderby'] = sanitize_text_field( $_GET['orderby'] );
    }
    
    if ( isset( $_GET['order'] ) && ! empty( $_GET['order'] ) ) {
        $filters['order'] = sanitize_text_field( $_GET['order'] );
    }

    // Status filter
    if ( isset( $_GET['status'] ) && ! empty( $_GET['status'] ) ) {
        $filters['status'] = sanitize_text_field( $_GET['status'] );
    }

    // Provider filter
    if ( isset( $_GET['providerName'] ) && ! empty( $_GET['providerName'] ) ) {
        $filters['providerName'] = sanitize_text_field( $_GET['providerName'] );
    }

    // Routing rule filter
    if ( isset( $_GET['routingRuleId'] ) && ! empty( $_GET['routingRuleId'] ) ) {
        $filters['routingRuleId'] = intval( $_GET['routingRuleId'] );
    }

    // Date range filter
    if ( isset( $_GET['date_from'] ) && ! empty( $_GET['date_from'] ) ) {
        $filters['date_from'] = sanitize_text_field( $_GET['date_from'] );
    }

    if ( isset( $_GET['date_to'] ) && ! empty( $_GET['date_to'] ) ) {
        $filters['date_to'] = sanitize_text_field( $_GET['date_to'] );
    }

    // Search filter
    if ( isset( $_GET['search'] ) && ! empty( $_GET['search'] ) ) {
        $filters['search'] = sanitize_text_field( $_GET['search'] );
    }

    // Get reports
    $reports = IntelliSend_Database::get_reports( $filters );

    // Get total count for pagination
    $total_reports = IntelliSend_Database::count_reports( $filters );
    $total_pages = ceil( $total_reports / $per_page );
    ?>
    <div class="wrap intellisend-admin">
        <h1><?php echo esc_html__( 'Email Reports', 'intellisend' ); ?></h1>
        
        <div class="intellisend-admin-content">
            <div class="intellisend-card">
                <h2><?php echo esc_html__( 'Email Logs', 'intellisend' ); ?></h2>
                
                <div class="intellisend-filters">
                    <form method="get" action="" id="filter-form">
                        <input type="hidden" name="page" value="intellisend-reports">
                        
                        <div class="filter-row">
                            <div class="filter-item">
                                <label for="filter-status"><?php echo esc_html__( 'Status', 'intellisend' ); ?></label>
                                <select id="filter-status" name="status">
                                    <option value=""><?php echo esc_html__( 'All', 'intellisend' ); ?></option>
                                    <option value="sent" <?php selected( isset( $_GET['status'] ) ? $_GET['status'] : '', 'sent' ); ?>>
                                        <?php echo esc_html__( 'Sent', 'intellisend' ); ?>
                                    </option>
                                    <option value="spam" <?php selected( isset( $_GET['status'] ) ? $_GET['status'] : '', 'spam' ); ?>>
                                        <?php echo esc_html__( 'Spam', 'intellisend' ); ?>
                                    </option>
                                    <option value="error" <?php selected( isset( $_GET['status'] ) ? $_GET['status'] : '', 'error' ); ?>>
                                        <?php echo esc_html__( 'Error', 'intellisend' ); ?>
                                    </option>
                                </select>
                            </div>
                            
                            <div class="filter-item">
                                <label for="filter-provider"><?php echo esc_html__( 'Provider', 'intellisend' ); ?></label>
                                <select id="filter-provider" name="providerName">
                                    <option value=""><?php echo esc_html__( 'All', 'intellisend' ); ?></option>
                                    <?php foreach ( $providers as $provider ) : ?>
                                        <option value="<?php echo esc_attr( $provider->name ); ?>" <?php selected( isset( $_GET['providerName'] ) ? $_GET['providerName'] : '', $provider->name ); ?>>
                                            <?php echo esc_html( $provider->name ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="filter-item">
                                <label for="filter-routing"><?php echo esc_html__( 'Routing Rule', 'intellisend' ); ?></label>
                                <select id="filter-routing" name="routingRuleId">
                                    <option value=""><?php echo esc_html__( 'All', 'intellisend' ); ?></option>
                                    <?php foreach ( $routing_rules as $rule ) : ?>
                                        <option value="<?php echo esc_attr( $rule->id ); ?>" <?php selected( isset( $_GET['routingRuleId'] ) ? $_GET['routingRuleId'] : '', $rule->id ); ?>>
                                            <?php echo esc_html( $rule->name ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="filter-row">
                            <div class="filter-item">
                                <label for="filter-date-from"><?php echo esc_html__( 'From Date', 'intellisend' ); ?></label>
                                <input type="date" id="filter-date-from" name="date_from" class="date-picker" value="<?php echo isset( $_GET['date_from'] ) ? esc_attr( $_GET['date_from'] ) : ''; ?>">
                            </div>
                            
                            <div class="filter-item">
                                <label for="filter-date-to"><?php echo esc_html__( 'To Date', 'intellisend' ); ?></label>
                                <input type="date" id="filter-date-to" name="date_to" class="date-picker" value="<?php echo isset( $_GET['date_to'] ) ? esc_attr( $_GET['date_to'] ) : ''; ?>">
                            </div>
                            
                            <div class="filter-item">
                                <label for="filter-search"><?php echo esc_html__( 'Search', 'intellisend' ); ?></label>
                                <input type="text" id="filter-search" name="search" placeholder="<?php echo esc_attr__( 'Search by subject, sender, or recipient', 'intellisend' ); ?>" value="<?php echo isset( $_GET['search'] ) ? esc_attr( $_GET['search'] ) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="filter-actions">
                            <button type="button" id="reset-filters" class="button button-secondary"><?php echo esc_html__( 'Reset', 'intellisend' ); ?></button>
                            <button type="submit" class="button button-primary"><?php echo esc_html__( 'Apply Filters', 'intellisend' ); ?></button>
                        </div>
                    </form>
                </div>
                
                <div class="intellisend-table-container">
                    <?php if ( ! empty( $reports ) ) : ?>
                        <div class="table-actions">
                            <div class="bulk-actions">
                                <select id="bulk-action-selector">
                                    <option value=""><?php echo esc_html__( 'Bulk Actions', 'intellisend' ); ?></option>
                                    <option value="delete"><?php echo esc_html__( 'Delete Selected', 'intellisend' ); ?></option>
                                </select>
                                <button type="button" id="bulk-action-apply" class="button action"><?php echo esc_html__( 'Apply', 'intellisend' ); ?></button>
                            </div>
                            <div class="selection-info">
                                <span id="selected-count">0</span> <?php echo esc_html__( 'items selected', 'intellisend' ); ?>
                            </div>
                            <div class="table-actions-right">
                                <button type="button" id="delete-all-reports" class="button action"><?php echo esc_html__( 'Delete All', 'intellisend' ); ?></button>
                            </div>
                        </div>
                        <table class="intellisend-table">
                            <thead>
                                <tr>
                                    <th class="checkbox-column">
                                        <input type="checkbox" id="select-all-reports">
                                    </th>
                                    <th class="sortable" data-sort="date">
                                        <?php 
                                        echo esc_html__( 'Date', 'intellisend' );
                                        $current_order = isset($_GET['orderby']) && $_GET['orderby'] === 'date' ? $_GET['order'] : '';
                                        if ($current_order) {
                                            echo '<span class="sort-indicator ' . ($current_order === 'asc' ? 'asc' : 'desc') . '"></span>';
                                        }
                                        ?>
                                    </th>
                                    <th class="sortable" data-sort="status">
                                        <?php 
                                        echo esc_html__( 'Status', 'intellisend' );
                                        $current_order = isset($_GET['orderby']) && $_GET['orderby'] === 'status' ? $_GET['order'] : '';
                                        if ($current_order) {
                                            echo '<span class="sort-indicator ' . ($current_order === 'asc' ? 'asc' : 'desc') . '"></span>';
                                        }
                                        ?>
                                    </th>
                                    <th class="sortable" data-sort="subject">
                                        <?php 
                                        echo esc_html__( 'Subject', 'intellisend' );
                                        $current_order = isset($_GET['orderby']) && $_GET['orderby'] === 'subject' ? $_GET['order'] : '';
                                        if ($current_order) {
                                            echo '<span class="sort-indicator ' . ($current_order === 'asc' ? 'asc' : 'desc') . '"></span>';
                                        }
                                        ?>
                                    </th>
                                    <th class="sortable" data-sort="sender">
                                        <?php 
                                        echo esc_html__( 'From', 'intellisend' );
                                        $current_order = isset($_GET['orderby']) && $_GET['orderby'] === 'sender' ? $_GET['order'] : '';
                                        if ($current_order) {
                                            echo '<span class="sort-indicator ' . ($current_order === 'asc' ? 'asc' : 'desc') . '"></span>';
                                        }
                                        ?>
                                    </th>
                                    <th class="sortable" data-sort="recipients">
                                        <?php 
                                        echo esc_html__( 'To', 'intellisend' );
                                        $current_order = isset($_GET['orderby']) && $_GET['orderby'] === 'recipients' ? $_GET['order'] : '';
                                        if ($current_order) {
                                            echo '<span class="sort-indicator ' . ($current_order === 'asc' ? 'asc' : 'desc') . '"></span>';
                                        }
                                        ?>
                                    </th>
                                    <th class="sortable" data-sort="providerName">
                                        <?php 
                                        echo esc_html__( 'Provider', 'intellisend' );
                                        $current_order = isset($_GET['orderby']) && $_GET['orderby'] === 'providerName' ? $_GET['order'] : '';
                                        if ($current_order) {
                                            echo '<span class="sort-indicator ' . ($current_order === 'asc' ? 'asc' : 'desc') . '"></span>';
                                        }
                                        ?>
                                    </th>
                                    <th><?php echo esc_html__( 'Actions', 'intellisend' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $reports as $report ) : ?>
                                    <tr>
                                        <td class="checkbox-column">
                                            <input type="checkbox" class="report-checkbox" data-id="<?php echo esc_attr( $report->id ); ?>">
                                        </td>
                                        <td><?php echo esc_html( date( 'Y-m-d H:i', strtotime( $report->date ) ) ); ?></td>
                                        <td>
                                            <span class="status-badge" data-status="<?php echo esc_attr( $report->status ); ?>">
                                                <?php echo esc_html( ucfirst( $report->status ) ); ?>
                                            </span>
                                        </td>
                                        <td><?php echo esc_html( $report->subject ); ?></td>
                                        <td><?php echo esc_html( $report->sender ); ?></td>
                                        <td><?php echo esc_html( $report->recipients ); ?></td>
                                        <td><?php echo esc_html( $report->providerName ); ?></td>
                                        <td>
                                            <button type="button" class="action-button view-report" data-id="<?php echo esc_attr( $report->id ); ?>" title="<?php echo esc_attr__( 'View Details', 'intellisend' ); ?>">
                                                <span class="dashicons dashicons-visibility"></span>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <?php if ( $total_pages > 1 ) : ?>
                            <div class="intellisend-pagination">
                                <div class="pagination-info">
                                    <?php
                                    $from = min( ( $page - 1 ) * $per_page + 1, $total_reports );
                                    $to = min( $page * $per_page, $total_reports );
                                    printf(
                                        esc_html__( 'Showing %1$d to %2$d of %3$d entries', 'intellisend' ),
                                        $from,
                                        $to,
                                        $total_reports
                                    );
                                    ?>
                                </div>
                                <div class="pagination-links">
                                    <?php
                                    // Previous page
                                    if ( $page > 1 ) {
                                        printf(
                                            '<a href="%s" class="prev-page" title="%s">&laquo;</a>',
                                            esc_url( add_query_arg( 'paged', $page - 1 ) ),
                                            esc_attr__( 'Previous page', 'intellisend' )
                                        );
                                    }
                                    
                                    // Page numbers
                                    $start_page = max( 1, $page - 2 );
                                    $end_page = min( $total_pages, $page + 2 );
                                    
                                    if ( $start_page > 1 ) {
                                        printf(
                                            '<a href="%s">1</a>',
                                            esc_url( add_query_arg( 'paged', 1 ) )
                                        );
                                        
                                        if ( $start_page > 2 ) {
                                            echo '<span class="pagination-ellipsis">&hellip;</span>';
                                        }
                                    }
                                    
                                    for ( $i = $start_page; $i <= $end_page; $i++ ) {
                                        if ( $i == $page ) {
                                            printf(
                                                '<span class="current">%d</span>',
                                                $i
                                            );
                                        } else {
                                            printf(
                                                '<a href="%s">%d</a>',
                                                esc_url( add_query_arg( 'paged', $i ) ),
                                                $i
                                            );
                                        }
                                    }
                                    
                                    if ( $end_page < $total_pages ) {
                                        if ( $end_page < $total_pages - 1 ) {
                                            echo '<span class="pagination-ellipsis">&hellip;</span>';
                                        }
                                        
                                        printf(
                                            '<a href="%s">%d</a>',
                                            esc_url( add_query_arg( 'paged', $total_pages ) ),
                                            $total_pages
                                        );
                                    }
                                    
                                    // Next page
                                    if ( $page < $total_pages ) {
                                        printf(
                                            '<a href="%s" class="next-page" title="%s">&raquo;</a>',
                                            esc_url( add_query_arg( 'paged', $page + 1 ) ),
                                            esc_attr__( 'Next page', 'intellisend' )
                                        );
                                    }
                                    ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php else : ?>
                        <div class="intellisend-empty-state">
                            <p><?php echo esc_html__( 'No email reports found. Try adjusting your filters or check back later.', 'intellisend' ); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Report Details Modal -->
    <div id="view-report-modal" class="intellisend-modal">
        <div class="intellisend-modal-content">
            <div class="intellisend-modal-header">
                <h3><?php echo esc_html__( 'Email Report Details', 'intellisend' ); ?></h3>
                <button type="button" class="intellisend-modal-close" aria-label="<?php echo esc_attr__( 'Close', 'intellisend' ); ?>">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>
            <div class="intellisend-modal-body">
                <div class="report-section">
                    <h3><?php echo esc_html__( 'General Information', 'intellisend' ); ?></h3>
                    <table class="widefat">
                        <tr>
                            <th><?php echo esc_html__( 'Date', 'intellisend' ); ?></th>
                            <td id="report-date"></td>
                        </tr>
                        <tr>
                            <th><?php echo esc_html__( 'Status', 'intellisend' ); ?></th>
                            <td id="report-status"></td>
                        </tr>
                        <tr>
                            <th><?php echo esc_html__( 'Provider', 'intellisend' ); ?></th>
                            <td id="report-provider"></td>
                        </tr>
                        <tr>
                            <th><?php echo esc_html__( 'Routing Rule', 'intellisend' ); ?></th>
                            <td id="report-routing"></td>
                        </tr>
                    </table>
                </div>
                
                <div class="report-section">
                    <h3><?php echo esc_html__( 'Email Information', 'intellisend' ); ?></h3>
                    <table class="widefat">
                        <tr>
                            <th><?php echo esc_html__( 'From', 'intellisend' ); ?></th>
                            <td id="report-from"></td>
                        </tr>
                        <tr>
                            <th><?php echo esc_html__( 'To', 'intellisend' ); ?></th>
                            <td id="report-to"></td>
                        </tr>
                        <tr>
                            <th><?php echo esc_html__( 'Subject', 'intellisend' ); ?></th>
                            <td id="report-subject"></td>
                        </tr>
                    </table>
                </div>
                
                <div class="report-section">
                    <h3><?php echo esc_html__( 'Message Content', 'intellisend' ); ?></h3>
                    <div id="report-message" class="report-message"></div>
                </div>
                
                <div class="report-section">
                    <h3><?php echo esc_html__( 'Log', 'intellisend' ); ?></h3>
                    <div id="report-headers" class="report-message"></div>
                </div>
                
                <div class="report-section" id="report-spam-section" style="display: none;">
                    <h3><?php echo esc_html__( 'Spam Information', 'intellisend' ); ?></h3>
                    <table class="widefat">
                        <tr>
                            <th><?php echo esc_html__( 'Spam Score', 'intellisend' ); ?></th>
                            <td id="report-spam-score"></td>
                        </tr>
                    </table>
                </div>
                
                <div class="report-section" id="report-error-section" style="display: none;">
                    <h3><?php echo esc_html__( 'Error Information', 'intellisend' ); ?></h3>
                    <table class="widefat">
                        <tr>
                            <th><?php echo esc_html__( 'Error Message', 'intellisend' ); ?></th>
                            <td id="report-error-message"></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <?php
    // Enqueue styles and scripts
    function intellisend_enqueue_reports_assets() {
        if ( isset( $_GET['page'] ) && $_GET['page'] === 'intellisend-reports' ) {
            wp_enqueue_style( 'intellisend-reports-css', INTELLISEND_PLUGIN_URL . 'admin/css/reports-page.css', array(), INTELLISEND_VERSION );
            wp_enqueue_script( 'intellisend-reports-js', INTELLISEND_PLUGIN_URL . 'admin/js/reports-page.js', array( 'jquery' ), INTELLISEND_VERSION, true );
            
            // Localize the script with necessary data including nonce
            wp_localize_script( 'intellisend-reports-js', 'intellisendData', array(
                'nonce' => wp_create_nonce( 'intellisend_ajax_nonce' ),
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'i18n' => array(
                    'loadingText' => __( 'Loading...', 'intellisend' ),
                    'errorText' => __( 'Error loading report', 'intellisend' )
                )
            ));
        }
    }
    add_action( 'admin_enqueue_scripts', 'intellisend_enqueue_reports_assets' );
}
