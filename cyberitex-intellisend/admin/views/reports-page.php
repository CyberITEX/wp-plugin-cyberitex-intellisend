<?php
/**
 * Admin page for email reports and logs
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Render the reports page
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
    $offset = ( $page - 1 ) * $per_page;

    // Filters
    $filters = array();
    $filters['limit'] = $per_page;
    $filters['offset'] = $offset;

    // Status filter
    if ( isset( $_GET['status'] ) && ! empty( $_GET['status'] ) ) {
        $filters['status'] = sanitize_text_field( $_GET['status'] );
    }

    // Provider filter
    if ( isset( $_GET['defaultProviderName'] ) && ! empty( $_GET['defaultProviderName'] ) ) {
        $filters['defaultProviderName'] = sanitize_text_field( $_GET['defaultProviderName'] );
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
                    <form method="get" action="">
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
                                <select id="filter-provider" name="defaultProviderName">
                                    <option value=""><?php echo esc_html__( 'All', 'intellisend' ); ?></option>
                                    <?php foreach ( $providers as $provider ) : ?>
                                        <option value="<?php echo esc_attr( $provider->name ); ?>" <?php selected( isset( $_GET['defaultProviderName'] ) ? $_GET['defaultProviderName'] : '', $provider->name ); ?>>
                                            <?php echo esc_html( $provider->name ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="filter-item">
                                <label for="filter-rule"><?php echo esc_html__( 'Routing Rule', 'intellisend' ); ?></label>
                                <select id="filter-rule" name="routingRuleId">
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
                                <label for="filter-date-from"><?php echo esc_html__( 'Date From', 'intellisend' ); ?></label>
                                <input type="date" id="filter-date-from" name="date_from" value="<?php echo isset( $_GET['date_from'] ) ? esc_attr( $_GET['date_from'] ) : ''; ?>">
                            </div>
                            
                            <div class="filter-item">
                                <label for="filter-date-to"><?php echo esc_html__( 'Date To', 'intellisend' ); ?></label>
                                <input type="date" id="filter-date-to" name="date_to" value="<?php echo isset( $_GET['date_to'] ) ? esc_attr( $_GET['date_to'] ) : ''; ?>">
                            </div>
                            
                            <div class="filter-item">
                                <label for="filter-search"><?php echo esc_html__( 'Search', 'intellisend' ); ?></label>
                                <input type="text" id="filter-search" name="search" value="<?php echo isset( $_GET['search'] ) ? esc_attr( $_GET['search'] ) : ''; ?>" placeholder="<?php echo esc_attr__( 'Search by subject or recipient', 'intellisend' ); ?>">
                            </div>
                        </div>
                        
                        <div class="filter-actions">
                            <button type="submit" class="button"><?php echo esc_html__( 'Apply Filters', 'intellisend' ); ?></button>
                            <a href="?page=intellisend-reports" class="button"><?php echo esc_html__( 'Reset', 'intellisend' ); ?></a>
                        </div>
                    </form>
                </div>
                
                <div class="intellisend-reports-list">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__( 'Date', 'intellisend' ); ?></th>
                                <th><?php echo esc_html__( 'Subject', 'intellisend' ); ?></th>
                                <th><?php echo esc_html__( 'Recipient', 'intellisend' ); ?></th>
                                <th><?php echo esc_html__( 'Status', 'intellisend' ); ?></th>
                                <th><?php echo esc_html__( 'Spam Check', 'intellisend' ); ?></th>
                                <th><?php echo esc_html__( 'Provider', 'intellisend' ); ?></th>
                                <th><?php echo esc_html__( 'Actions', 'intellisend' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( empty( $reports ) ) : ?>
                                <tr>
                                    <td colspan="7"><?php echo esc_html__( 'No reports found.', 'intellisend' ); ?></td>
                                </tr>
                            <?php else : ?>
                                <?php foreach ( $reports as $report ) : ?>
                                    <?php 
                                    $provider_name = '';
                                    foreach ( $providers as $provider ) {
                                        if ( $provider->name == $report->defaultProviderName ) {
                                            $provider_name = $provider->name;
                                            break;
                                        }
                                    }
                                    
                                    $status_class = '';
                                    if ( $report->status == 'sent' ) {
                                        $status_class = 'status-success';
                                    } elseif ( $report->status == 'spam' ) {
                                        $status_class = 'status-warning';
                                    } elseif ( $report->status == 'error' ) {
                                        $status_class = 'status-error';
                                    }
                                    ?>
                                    <tr>
                                        <td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $report->date ) ) ); ?></td>
                                        <td><?php echo esc_html( $report->subject ); ?></td>
                                        <td><?php echo esc_html( $report->recipients ); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo esc_attr( $status_class ); ?>">
                                                <?php echo esc_html( ucfirst( $report->status ) ); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ( $report->antiSpamEnabled ) : ?>
                                                <span class="dashicons dashicons-yes"></span>
                                            <?php else : ?>
                                                <span class="dashicons dashicons-no"></span>
                                            <?php endif; ?>
                                            <?php if ( $report->isSpam ) : ?>
                                                <span class="spam-badge"><?php echo esc_html__( 'Spam', 'intellisend' ); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo esc_html( $provider_name ); ?></td>
                                        <td>
                                            <button class="button view-report" data-id="<?php echo esc_attr( $report->id ); ?>">
                                                <?php echo esc_html__( 'View', 'intellisend' ); ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ( $total_pages > 1 ) : ?>
                    <div class="intellisend-pagination">
                        <div class="tablenav-pages">
                            <span class="displaying-num">
                                <?php echo sprintf( _n( '%s item', '%s items', $total_reports, 'intellisend' ), number_format_i18n( $total_reports ) ); ?>
                            </span>
                            
                            <span class="pagination-links">
                                <?php if ( $page > 1 ) : ?>
                                    <a class="first-page button" href="<?php echo esc_url( add_query_arg( 'paged', 1 ) ); ?>">
                                        <span class="screen-reader-text"><?php echo esc_html__( 'First page', 'intellisend' ); ?></span>
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                    <a class="prev-page button" href="<?php echo esc_url( add_query_arg( 'paged', max( 1, $page - 1 ) ) ); ?>">
                                        <span class="screen-reader-text"><?php echo esc_html__( 'Previous page', 'intellisend' ); ?></span>
                                        <span aria-hidden="true">&lsaquo;</span>
                                    </a>
                                <?php else : ?>
                                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span>
                                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>
                                <?php endif; ?>
                                
                                <span class="paging-input">
                                    <label for="current-page-selector" class="screen-reader-text"><?php echo esc_html__( 'Current Page', 'intellisend' ); ?></label>
                                    <input class="current-page" id="current-page-selector" type="text" name="paged" value="<?php echo esc_attr( $page ); ?>" size="1" aria-describedby="table-paging">
                                    <span class="tablenav-paging-text"> <?php echo esc_html__( 'of', 'intellisend' ); ?> <span class="total-pages"><?php echo esc_html( $total_pages ); ?></span></span>
                                </span>
                                
                                <?php if ( $page < $total_pages ) : ?>
                                    <a class="next-page button" href="<?php echo esc_url( add_query_arg( 'paged', min( $total_pages, $page + 1 ) ) ); ?>">
                                        <span class="screen-reader-text"><?php echo esc_html__( 'Next page', 'intellisend' ); ?></span>
                                        <span aria-hidden="true">&rsaquo;</span>
                                    </a>
                                    <a class="last-page button" href="<?php echo esc_url( add_query_arg( 'paged', $total_pages ) ); ?>">
                                        <span class="screen-reader-text"><?php echo esc_html__( 'Last page', 'intellisend' ); ?></span>
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                <?php else : ?>
                                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>
                                    <span class="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- View Report Modal -->
    <div id="view-report-modal" class="intellisend-modal">
        <div class="intellisend-modal-content">
            <span class="intellisend-modal-close">&times;</span>
            <h2><?php echo esc_html__( 'Email Report Details', 'intellisend' ); ?></h2>
            
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
                    <tr>
                        <th><?php echo esc_html__( 'Headers', 'intellisend' ); ?></th>
                        <td id="report-headers"></td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__( 'Message', 'intellisend' ); ?></th>
                        <td id="report-message"></td>
                    </tr>
                    <tr>
                        <th><?php echo esc_html__( 'Attachments', 'intellisend' ); ?></th>
                        <td id="report-attachments"></td>
                    </tr>
                </table>
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
    
    <script>
        jQuery(document).ready(function($) {
            // View report
            $('.view-report').click(function() {
                var reportId = $(this).data('id');
                
                // Get report data via AJAX
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'intellisend_get_report',
                        id: reportId,
                        nonce: intellisendData.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            var report = response.data;
                            
                            // Fill the modal with report data
                            $('#report-date').text(report.date);
                            $('#report-status').text(report.status);
                            $('#report-provider').text(report.defaultProviderName);
                            $('#report-routing').text(report.routingRuleName);
                            $('#report-from').text(report.sender);
                            $('#report-to').text(report.recipients);
                            $('#report-subject').text(report.subject);
                            $('#report-headers').text(report.log);
                            $('#report-message').html(report.message.replace(/\n/g, '<br>'));
                            
                            // Show/hide spam section
                            if (report.isSpam) {
                                $('#report-spam-section').show();
                                $('#report-spam-score').text(report.spamScore);
                            } else {
                                $('#report-spam-section').hide();
                            }
                            
                            // Show/hide error section
                            if (report.status === 'error') {
                                $('#report-error-section').show();
                                $('#report-error-message').text(report.errorMessage);
                            } else {
                                $('#report-error-section').hide();
                            }
                            
                            // Show the modal
                            $('#view-report-modal').show();
                        } else {
                            alert(response.data);
                        }
                    }
                });
            });
            
            // Close modal
            $('.intellisend-modal-close').click(function() {
                $(this).closest('.intellisend-modal').hide();
            });
            
            // Close modal when clicking outside
            $(window).click(function(event) {
                if ($(event.target).hasClass('intellisend-modal')) {
                    $('.intellisend-modal').hide();
                }
            });
        });
    </script>
    <?php
}

intellisend_render_reports_page_content();
