<?php
/**
 * admin\views\report-single.php
 * Permalinked view of one email report.
 *
 * Reached at admin.php?page=intellisend-reports&report=<id>. The reports list
 * still opens the same record in its dialog; this page exists so a single
 * report has a URL that can be bookmarked, reloaded and shared with another
 * administrator.
 *
 * @package IntelliSend
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Build the sandboxed preview document for a stored message body.
 *
 * The body is stored through wp_kses_post(), so scripts and event handlers are
 * already gone. This adds the same two defences the reports dialog uses: a
 * sandbox with no allow-scripts, and a content policy that permits inline CSS
 * and data: images only, so nothing in a logged message can reach the network.
 *
 * @param string $message Stored message body.
 * @return string Complete HTML document for the iframe's srcdoc attribute.
 */
function intellisend_report_preview_document( $message ) {
    $style = 'html{color-scheme:light}'
        . 'body{margin:8px;background:#fff;color:#1e1e1e;font:14px/1.5 sans-serif;'
        . 'overflow-wrap:anywhere;white-space:pre-wrap}'
        . 'img{max-width:100%;height:auto}table{max-width:100%}';

    // Strip anything that could still pull a remote resource. Scripts cannot
    // run here, but an <img src="https://..."> would otherwise phone home and
    // tell a sender that their message had been read.
    $body = preg_replace( '#<(script|iframe|frame|object|embed|base|meta|link|form)\b[^>]*>.*?</\1\s*>#is', '', (string) $message );
    $body = preg_replace( '#<(script|iframe|frame|object|embed|base|meta|link|form)\b[^>]*/?>#i', '', (string) $body );
    $body = preg_replace( '#\s(?:href|srcset|srcdoc|action|formaction|target|ping|on[a-z]+)\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', (string) $body );
    $body = preg_replace_callback(
        '#\ssrc\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))#i',
        static function ( $matches ) {
            $value = '' !== $matches[1] ? $matches[1] : ( '' !== $matches[2] ? $matches[2] : ( isset( $matches[3] ) ? $matches[3] : '' ) );
            return preg_match( '#^data:image/(?:png|gif|jpeg|webp);#i', $value ) ? $matches[0] : '';
        },
        (string) $body
    );

    return '<!doctype html><html><head>'
        . '<meta http-equiv="Content-Security-Policy" content="default-src \'none\'; style-src \'unsafe-inline\'; img-src data:">'
        . '<style>' . $style . '</style>'
        . '</head><body>' . $body . '</body></html>';
}

/**
 * Render the single report page.
 *
 * @param int $report_id Report to display.
 */
function intellisend_render_single_report_content( $report_id ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to view this page.', 'intellisend' ) );
    }

    $list_url = admin_url( 'admin.php?page=intellisend-reports' );
    $report   = IntelliSend_Database::get_report( $report_id );

    if ( ! $report ) {
        ?>
        <div class="wrap intellisend-admin">
            <h1><?php echo esc_html__( 'Email Report', 'intellisend' ); ?></h1>
            <div class="intellisend-card">
                <p><?php echo esc_html__( 'That report no longer exists. It may have been deleted.', 'intellisend' ); ?></p>
                <p>
                    <a class="button button-primary" href="<?php echo esc_url( $list_url ); ?>">
                        <?php echo esc_html__( 'Back to Reports', 'intellisend' ); ?>
                    </a>
                </p>
            </div>
        </div>
        <?php
        return;
    }

    // Resolve the routing rule name so the page does not just show a number.
    $rule_label = '';
    if ( ! empty( $report->routingRuleId ) ) {
        $rule = IntelliSend_Database::get_routing_rule( (int) $report->routingRuleId );
        $rule_label = $rule && ! empty( $rule->name )
            ? sprintf( '%s (#%d)', $rule->name, (int) $report->routingRuleId )
            : sprintf( '#%d', (int) $report->routingRuleId );
    }

    $is_spam    = ! empty( $report->isSpam ) && '0' !== (string) $report->isSpam;
    $timestamp  = strtotime( $report->date );
    $date_label = $timestamp
        ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp )
        : $report->date;

    $fields = array(
        __( 'Date', 'intellisend' )         => $date_label,
        __( 'Subject', 'intellisend' )      => $report->subject,
        __( 'From', 'intellisend' )         => $report->sender,
        __( 'To', 'intellisend' )           => $report->recipients,
        __( 'Provider', 'intellisend' )     => $report->providerName,
        __( 'Routing Rule', 'intellisend' ) => $rule_label,
        __( 'Spam Check', 'intellisend' )   => empty( $report->antiSpamEnabled )
            ? __( 'Disabled for this rule', 'intellisend' )
            : __( 'Enabled', 'intellisend' ),
        __( 'Spam Verdict', 'intellisend' ) => $is_spam
            ? __( 'Flagged as spam', 'intellisend' )
            : __( 'Not spam', 'intellisend' ),
    );
    ?>
    <div class="wrap intellisend-admin">
        <h1 class="intellisend-report-title">
            <?php echo esc_html__( 'Email Report', 'intellisend' ); ?>
            <span class="intellisend-report-id">#<?php echo esc_html( $report->id ); ?></span>
        </h1>

        <p class="intellisend-report-back">
            <a href="<?php echo esc_url( $list_url ); ?>">
                <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
                <?php echo esc_html__( 'Back to Reports', 'intellisend' ); ?>
            </a>
        </p>

        <div class="intellisend-admin-content">
            <div class="intellisend-card">
                <h2 class="intellisend-report-heading">
                    <?php echo esc_html__( 'Delivery', 'intellisend' ); ?>
                    <span class="status-badge" data-status="<?php echo esc_attr( $report->status ); ?>">
                        <?php echo esc_html( ucfirst( $report->status ) ); ?>
                    </span>
                </h2>

                <table class="intellisend-report-meta">
                    <tbody>
                        <?php foreach ( $fields as $label => $value ) : ?>
                            <?php if ( '' === (string) $value ) { continue; } ?>
                            <tr>
                                <th scope="row"><?php echo esc_html( $label ); ?></th>
                                <td><?php echo esc_html( $value ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="intellisend-card">
                <h2><?php echo esc_html__( 'Message', 'intellisend' ); ?></h2>
                <?php if ( '' === trim( (string) $report->message ) ) : ?>
                    <p class="description"><?php echo esc_html__( 'This message had no body.', 'intellisend' ); ?></p>
                <?php else : ?>
                    <iframe
                        class="intellisend-report-preview"
                        title="<?php echo esc_attr__( 'Message preview', 'intellisend' ); ?>"
                        sandbox="allow-same-origin"
                        referrerpolicy="no-referrer"
                        loading="lazy"
                        srcdoc="<?php echo esc_attr( intellisend_report_preview_document( $report->message ) ); ?>"></iframe>
                    <p class="description">
                        <?php echo esc_html__( 'Remote images and links are disabled in this preview.', 'intellisend' ); ?>
                    </p>
                <?php endif; ?>
            </div>

            <div class="intellisend-card">
                <h2><?php echo esc_html__( 'Diagnostics', 'intellisend' ); ?></h2>
                <?php if ( '' === trim( (string) $report->log ) ) : ?>
                    <p class="description"><?php echo esc_html__( 'No diagnostic detail was recorded for this message.', 'intellisend' ); ?></p>
                <?php else : ?>
                    <pre class="intellisend-report-log"><?php echo esc_html( $report->log ); ?></pre>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}
