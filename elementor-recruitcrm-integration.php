<?php
/**
 * Plugin Name: Elementor → Recruit CRM Integration
 * Description: Creates/updates Company, creates Contact, and creates Job in Recruit CRM from Elementor form submission.
 * Version: 2.1
 * Author: Orbit570
 * Author URI: https://towfiqueelahe.com
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * =========================================================
 * ADMIN SETTINGS PAGE
 * =========================================================
 */
add_action( 'admin_menu', 'erc_add_settings_page' );
add_action( 'admin_init', 'erc_register_settings' );

function erc_add_settings_page() {
    add_options_page(
        'Recruit CRM Settings',
        'Recruit CRM',
        'manage_options',
        'recruitcrm-settings',
        'erc_render_settings_page'
    );
}

function erc_register_settings() {
    register_setting(
        'erc_settings_group',
        'erc_recruitcrm_api_token',
        [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ]
    );
    
    // Add debug option
    register_setting(
        'erc_settings_group',
        'erc_debug_mode',
        [
            'type'              => 'boolean',
            'sanitize_callback' => 'sanitize_text_field',
        ]
    );
    
    // Add form name setting
    register_setting(
        'erc_settings_group',
        'erc_form_name',
        [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ]
    );
}

function erc_render_settings_page() {
    ?>
<div class="wrap">
    <h1>Recruit CRM Integration</h1>

    <form method="post" action="options.php">
        <?php settings_fields( 'erc_settings_group' ); ?>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="erc_recruitcrm_api_token">Recruit CRM API Token</label>
                </th>
                <td>
                    <input type="password" id="erc_recruitcrm_api_token" name="erc_recruitcrm_api_token"
                        value="<?php echo esc_attr( get_option( 'erc_recruitcrm_api_token' ) ); ?>"
                        class="regular-text" />
                    <p class="description">
                        Paste your Recruit CRM API token here. This is stored securely in the database.
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="erc_form_name">Target Form Name</label>
                </th>
                <td>
                    <input type="text" id="erc_form_name" name="erc_form_name"
                        value="<?php echo esc_attr( get_option( 'erc_form_name' ) ); ?>" class="regular-text" />
                    <p class="description">
                        Enter "Recruitment Form" (exactly as shown) to target only that form. Leave empty to process all
                        forms.
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="erc_debug_mode">Debug Mode</label>
                </th>
                <td>
                    <input type="checkbox" id="erc_debug_mode" name="erc_debug_mode" value="1"
                        <?php checked( get_option( 'erc_debug_mode' ), 1 ); ?> />
                    <p class="description">
                        Enable debug logging to track API requests and responses.
                    </p>
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>
    </form>

    <?php if ( get_option( 'erc_debug_mode' ) ) : ?>
    <div class="notice notice-info" style="margin-top: 20px;">
        <h2 style="margin-top: 0;">Debug Logs</h2>

        <?php
        $debug_log = get_option( 'erc_debug_log', [] );
        if ( ! empty( $debug_log ) ) : 
            $total_logs = count( $debug_log );
            $recent_logs = array_slice( $debug_log, -50 ); // Show last 50 entries
            
            // Summary stats
            $today_count = 0;
            $current_date = date( 'Y-m-d' );
            foreach ( $debug_log as $log ) {
                if ( strpos( $log, '[' . $current_date ) === 0 ) {
                    $today_count++;
                }
            }
            ?>

        <div style="margin-bottom: 15px; padding: 10px; background: #f0f0f1; border-left: 4px solid #2271b1;">
            <strong>Log Summary:</strong>
            <?php echo number_format( $total_logs ); ?> total entries |
            <?php echo number_format( $today_count ); ?> today |
            Showing last <?php echo number_format( count( $recent_logs ) ); ?> entries
        </div>

        <div style="margin-bottom: 15px;">
            <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                <button type="button" class="button" onclick="copyLogs()">Copy Logs</button>
                <button type="button" class="button" onclick="toggleWordWrap()">Toggle Word Wrap</button>
                <button type="button" class="button" onclick="filterLogs('ERROR')">Show Errors Only</button>
                <button type="button" class="button" onclick="filterLogs('WARNING')">Show Warnings Only</button>
                <button type="button" class="button" onclick="filterLogs('ALL')">Show All</button>
            </div>

            <div style="position: relative;">
                <div style="position: absolute; top: 10px; right: 10px; z-index: 10;">
                    <span id="log-count"
                        style="background: #2271b1; color: white; padding: 3px 8px; border-radius: 3px; font-size: 12px;">
                        <?php echo count( $recent_logs ); ?> entries
                    </span>
                </div>

                <div id="log-container" style="
                        background: #1d2327;
                        color: #f0f0f0;
                        padding: 15px;
                        border-radius: 4px;
                        font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', 'Consolas', monospace;
                        font-size: 12px;
                        line-height: 1.5;
                        height: 500px;
                        overflow-y: auto;
                        overflow-x: auto;
                        white-space: nowrap;
                        margin-bottom: 15px;
                        position: relative;
                    ">
                    <?php foreach ( $recent_logs as $index => $log ) : 
                            $log_class = '';
                            if ( strpos( $log, 'ERROR:' ) !== false ) {
                                $log_class = 'log-error';
                            } elseif ( strpos( $log, 'WARNING:' ) !== false ) {
                                $log_class = 'log-warning';
                            } elseif ( strpos( $log, '=== Elementor' ) !== false ) {
                                $log_class = 'log-section';
                            } elseif ( strpos( $log, 'API Request:' ) !== false ) {
                                $log_class = 'log-api-request';
                            } elseif ( strpos( $log, 'Response Code:' ) !== false ) {
                                $log_class = 'log-api-response';
                            }
                        ?>
                    <div class="log-entry <?php echo $log_class; ?>" data-index="<?php echo $index; ?>"
                        style="margin-bottom: 4px; border-left: 3px solid transparent; padding-left: 5px;">
                        <?php echo esc_html( $log ); ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px;">
                <div>
                    <span style="display: inline-flex; align-items: center; margin-right: 15px;">
                        <span
                            style="display: inline-block; width: 12px; height: 12px; background: #dc3232; margin-right: 5px;"></span>
                        <span style="font-size: 12px;">Errors</span>
                    </span>
                    <span style="display: inline-flex; align-items: center; margin-right: 15px;">
                        <span
                            style="display: inline-block; width: 12px; height: 12px; background: #f0b849; margin-right: 5px;"></span>
                        <span style="font-size: 12px;">Warnings</span>
                    </span>
                    <span style="display: inline-flex; align-items: center; margin-right: 15px;">
                        <span
                            style="display: inline-block; width: 12px; height: 12px; background: #00a0d2; margin-right: 5px;"></span>
                        <span style="font-size: 12px;">Section Start</span>
                    </span>
                    <span style="display: inline-flex; align-items: center;">
                        <span
                            style="display: inline-block; width: 12px; height: 12px; background: #46b450; margin-right: 5px;"></span>
                        <span style="font-size: 12px;">API Calls</span>
                    </span>
                </div>

                <div>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="erc_clear_logs" value="1">
                        <?php wp_nonce_field( 'erc_clear_logs_action', 'erc_clear_logs_nonce' ); ?>
                        <input type="submit" class="button button-primary" value="Clear All Logs"
                            onclick="return confirm('Are you sure you want to clear all debug logs?');">
                    </form>
                    <button type="button" class="button" onclick="downloadLogs()" style="margin-left: 10px;">
                        Download Logs
                    </button>
                </div>
            </div>
        </div>

        <style>
        .log-error {
            border-left-color: #dc3232 !important;
            color: #ff6b6b;
            background: rgba(220, 50, 50, 0.1);
        }

        .log-warning {
            border-left-color: #f0b849 !important;
            color: #ffd166;
            background: rgba(240, 184, 73, 0.1);
        }

        .log-section {
            border-left-color: #00a0d2 !important;
            color: #4ecdc4;
            font-weight: bold;
            background: rgba(0, 160, 210, 0.1);
        }

        .log-api-request {
            border-left-color: #46b450 !important;
            color: #88d498;
            background: rgba(70, 180, 80, 0.1);
        }

        .log-api-response {
            border-left-color: #7c3aed !important;
            color: #c4b5fd;
            background: rgba(124, 58, 237, 0.1);
        }

        .log-entry {
            transition: all 0.2s ease;
        }

        .log-entry:hover {
            background: rgba(255, 255, 255, 0.05) !important;
        }
        </style>

        <script>
        function copyLogs() {
            const logContainer = document.getElementById('log-container');
            const text = logContainer.innerText;
            navigator.clipboard.writeText(text).then(() => {
                alert('Logs copied to clipboard!');
            });
        }

        function toggleWordWrap() {
            const logContainer = document.getElementById('log-container');
            logContainer.style.whiteSpace = logContainer.style.whiteSpace === 'nowrap' ? 'pre-wrap' : 'nowrap';
        }

        function filterLogs(type) {
            const entries = document.querySelectorAll('.log-entry');
            let visibleCount = 0;

            entries.forEach(entry => {
                let show = false;

                switch (type) {
                    case 'ERROR':
                        show = entry.classList.contains('log-error');
                        break;
                    case 'WARNING':
                        show = entry.classList.contains('log-warning');
                        break;
                    case 'ALL':
                    default:
                        show = true;
                        break;
                }

                entry.style.display = show ? 'block' : 'none';
                if (show) visibleCount++;
            });

            document.getElementById('log-count').textContent = visibleCount + ' entries';

            // Scroll to top after filtering
            document.getElementById('log-container').scrollTop = 0;
        }

        function downloadLogs() {
            const logContainer = document.getElementById('log-container');
            const text = logContainer.innerText;
            const blob = new Blob([text], {
                type: 'text/plain'
            });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'recruit-crm-debug-' + new Date().toISOString().split('T')[0] + '.log';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }

        // Auto-scroll to bottom on page load
        document.addEventListener('DOMContentLoaded', function() {
            const logContainer = document.getElementById('log-container');
            if (logContainer) {
                logContainer.scrollTop = logContainer.scrollHeight;
            }
        });
        </script>

        <?php else : ?>
        <div style="text-align: center; padding: 30px; background: #f0f0f1; border-radius: 4px;">
            <p style="font-size: 16px; color: #666; margin-bottom: 20px;">No debug logs yet.</p>
            <p style="color: #999;">Submit an Elementor form to see debug logs here.</p>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<?php
    // Handle log clearing
    if ( isset( $_POST['erc_clear_logs'] ) && wp_verify_nonce( $_POST['erc_clear_logs_nonce'], 'erc_clear_logs_action' ) ) {
        update_option( 'erc_debug_log', [] );
        echo '<div class="notice notice-success is-dismissible"><p>Debug logs cleared successfully.</p></div>';
    }
}

/**
 * Debug logging function
 */
function erc_log( $message ) {
    if ( get_option( 'erc_debug_mode' ) ) {
        $debug_log = get_option( 'erc_debug_log', [] );
        $debug_log[] = '[' . date( 'Y-m-d H:i:s' ) . '] ' . $message;
        // Keep only last 100 entries
        if ( count( $debug_log ) > 100 ) {
            $debug_log = array_slice( $debug_log, -100 );
        }
        update_option( 'erc_debug_log', $debug_log );
    }
}

/**
 * Make API request to Recruit CRM
 */
function erc_api_request( $endpoint, $method = 'GET', $data = [] ) {
    $api_token = get_option( 'erc_recruitcrm_api_token' );
    
    if ( empty( $api_token ) ) {
        return new WP_Error( 'no_token', 'API token missing' );
    }
    
    $base_url = 'https://api.recruitcrm.io/v1';
    $url = $base_url . $endpoint;
    
    $headers = [
        'Authorization' => 'Bearer ' . $api_token,
        'Content-Type'  => 'application/json',
        'Accept'        => 'application/json',
    ];
    
    $args = [
        'headers' => $headers,
        'timeout' => 30,
    ];
    
    if ( in_array( $method, [ 'POST', 'PUT', 'PATCH' ] ) && ! empty( $data ) ) {
        $args['body'] = json_encode( $data );
    }
    
    if ( $method === 'GET' && ! empty( $data ) ) {
        $url = add_query_arg( $data, $url );
    }
    
    erc_log( "API Request: $method $url" );
    if ( ! empty( $data ) ) {
        erc_log( "Request Data: " . json_encode( $data ) );
    }
    
    switch ( $method ) {
        case 'POST':
            $response = wp_remote_post( $url, $args );
            break;
        case 'PUT':
            $args['method'] = 'PUT';
            $response = wp_remote_request( $url, $args );
            break;
        case 'PATCH':
            $args['method'] = 'PATCH';
            $response = wp_remote_request( $url, $args );
            break;
        case 'DELETE':
            $args['method'] = 'DELETE';
            $response = wp_remote_request( $url, $args );
            break;
        default: // GET
            $response = wp_remote_get( $url, $args );
    }
    
    if ( is_wp_error( $response ) ) {
        erc_log( "API Error: " . $response->get_error_message() );
        return $response;
    }
    
    $response_code = wp_remote_retrieve_response_code( $response );
    $response_body = wp_remote_retrieve_body( $response );
    
    erc_log( "Response Code: $response_code" );
    erc_log( "Response Body: $response_body" );
    
    return [
        'code' => $response_code,
        'body' => json_decode( $response_body, true ),
        'raw'  => $response_body,
    ];
}

/**
 * =========================================================
 * ELEMENTOR FORM HANDLER - FIXED VERSION
 * =========================================================
 */
add_action( 'elementor_pro/forms/process', 'erc_handle_elementor_submission', 10, 2 );

function erc_handle_elementor_submission( $record, $handler ) {
    erc_log( '=== Elementor Form Submission Started ===' );
    
    /**
     * Get the target form name from settings
     */
    $target_form_name = get_option( 'erc_form_name' );
    
    // If a target form name is set, we need to check if this is the right form
    if ( ! empty( $target_form_name ) ) {
        // Try to get form name safely
        try {
            $current_form_name = $record->get_form_settings( 'form_name' );
            erc_log( 'Got form name via get_form_settings("form_name"): ' . $current_form_name );
            
            if ( $current_form_name !== $target_form_name ) {
                erc_log( 'Skipping form - name does not match target' );
                return;
            }
        } catch ( Exception $e ) {
            erc_log( 'Error getting form name: ' . $e->getMessage() );
            // If we can't verify form name, check for required fields
            $fields = [];
            foreach ( $record->get( 'fields' ) as $id => $field ) {
                $clean_id = str_replace( 'form-field-', '', $id );
                $fields[ $clean_id ] = sanitize_text_field( $field['value'] );
            }
            
            // Check if this form has the required Recruit CRM fields
            $has_required_fields = isset( $fields['company_name'] ) && 
                                  isset( $fields['job_title'] ) && 
                                  isset( $fields['contact_email'] );
            
            if ( ! $has_required_fields ) {
                erc_log( 'Skipping form - does not have required Recruit CRM fields' );
                return;
            }
        }
    } else {
        erc_log( 'No target form name set - processing all forms with required fields' );
        
        // Even without target, check for required fields
        $fields = [];
        foreach ( $record->get( 'fields' ) as $id => $field ) {
            $clean_id = str_replace( 'form-field-', '', $id );
            $fields[ $clean_id ] = sanitize_text_field( $field['value'] );
        }
        
        // Check if this form has the required Recruit CRM fields
        $has_required_fields = isset( $fields['company_name'] ) && 
                              isset( $fields['job_title'] ) && 
                              isset( $fields['contact_email'] );
        
        if ( ! $has_required_fields ) {
            erc_log( 'Skipping form - does not have required Recruit CRM fields' );
            return;
        }
    }
    
    erc_log( 'Processing form for Recruit CRM integration...' );

    /**
     * Get API token from settings
     */
    $api_token = get_option( 'erc_recruitcrm_api_token' );
    if ( empty( $api_token ) ) {
        $error_msg = 'Recruit CRM API token missing. Please configure it in Settings > Recruit CRM.';
        erc_log( 'ERROR: ' . $error_msg );
        error_log( $error_msg );
        return;
    }
    
    erc_log( 'API Token found (first 10 chars): ' . substr( $api_token, 0, 10 ) . '...' );

    /**
     * Normalize Elementor fields
     */
    if ( ! isset( $fields ) || empty( $fields ) ) {
        $fields = [];
        foreach ( $record->get( 'fields' ) as $id => $field ) {
            $clean_id = str_replace( 'form-field-', '', $id );
            $fields[ $clean_id ] = sanitize_text_field( $field['value'] );
        }
    }

    erc_log( 'Form Fields Received: ' . print_r( $fields, true ) );

    /**
     * =========================================================
     * 1. COMPANY: SEARCH → CREATE
     * =========================================================
     */
    $company_name = $fields['company_name'] ?? '';
    if ( empty( $company_name ) ) {
        erc_log( 'ERROR: Company name is required but not provided' );
        return;
    }
    
    erc_log( 'Processing Company: ' . $company_name );

    $company_slug = null;
    $company_id = null;

    // Search for existing company
    $search_result = erc_api_request( '/companies', 'GET', [ 'name' => $company_name ] );
    
    if ( ! is_wp_error( $search_result ) && $search_result['code'] === 200 ) {
        // Check if company exists in response
        if ( isset( $search_result['body']['data'] ) && is_array( $search_result['body']['data'] ) ) {
            foreach ( $search_result['body']['data'] as $company ) {
                if ( isset( $company['company_name'] ) && strcasecmp( $company['company_name'], $company_name ) === 0 ) {
                    $company_slug = $company['slug'] ?? null;
                    $company_id = $company['id'] ?? null;
                    if ( $company_slug ) {
                        erc_log( 'Found existing company with slug: ' . $company_slug . ' (ID: ' . $company_id . ')' );
                        break;
                    }
                }
            }
            
            if ( ! $company_slug ) {
                erc_log( 'No existing company found with exact name match' );
            }
        } else {
            erc_log( 'No companies data in search response' );
        }
    } else {
        erc_log( 'Company search failed or returned error, will try to create new company' );
    }

    // Create company if not found
    if ( ! $company_slug ) {
        $company_data = [
            'company_name' => $company_name,  // Note: field name is company_name not name
            'website'      => $fields['company_website'] ?? '',  // Note: field name is website not company_url
        ];
        
        erc_log( 'Creating company with data: ' . json_encode( $company_data ) );
        
        $create_result = erc_api_request( '/companies', 'POST', $company_data );
        
        if ( ! is_wp_error( $create_result ) && in_array( $create_result['code'], [ 200, 201 ] ) ) {
            $company_slug = $create_result['body']['slug'] ?? null;
            $company_id = $create_result['body']['id'] ?? null;
            
            if ( $company_slug ) {
                erc_log( 'Successfully created company with slug: ' . $company_slug . ' (ID: ' . $company_id . ')' );
            } else {
                erc_log( 'ERROR: Company slug not found in response. Full response: ' . $create_result['raw'] );
                return;
            }
        } else {
            erc_log( 'ERROR: Failed to create company. Response code: ' . ( $create_result['code'] ?? 'Unknown' ) );
            if ( isset( $create_result['body'] ) && is_array( $create_result['body'] ) ) {
                foreach ( $create_result['body'] as $key => $value ) {
                    if ( is_array( $value ) ) {
                        erc_log( "Error detail - $key: " . print_r( $value, true ) );
                    } else {
                        erc_log( "Error detail - $key: $value" );
                    }
                }
            }
            return;
        }
    }

    /**
     * =========================================================
     * 2. CONTACT: SEARCH → CREATE
     * =========================================================
     */
    $contact_email = $fields['contact_email'] ?? '';
    $contact_slug  = null;
    $contact_id    = null;

    if ( $contact_email ) {
        erc_log( 'Processing Contact with email: ' . $contact_email );
        
        // Search for contact by email
        $search_result = erc_api_request( '/contacts', 'GET', [ 'email' => $contact_email ] );
        
        if ( ! is_wp_error( $search_result ) && $search_result['code'] === 200 ) {
            if ( isset( $search_result['body']['data'] ) && is_array( $search_result['body']['data'] ) ) {
                foreach ( $search_result['body']['data'] as $contact ) {
                    if ( isset( $contact['email'] ) && strcasecmp( $contact['email'], $contact_email ) === 0 ) {
                        $contact_slug = $contact['slug'] ?? null;
                        $contact_id = $contact['id'] ?? null;
                        if ( $contact_slug ) {
                            erc_log( 'Found existing contact with slug: ' . $contact_slug . ' (ID: ' . $contact_id . ')' );
                            break;
                        }
                    }
                }
            }
        }

        if ( ! $contact_slug ) {
            $contact_data = [
                'first_name'     => $fields['contact_first_name'] ?? '',
                'last_name'      => $fields['contact_last_name'] ?? '',
                'email'          => $contact_email,
                'contact_number' => $fields['contact_phone'] ?? '',
                'company_slug'   => $company_slug,  // Use slug, not ID
            ];
            
            erc_log( 'Creating contact with data: ' . json_encode( $contact_data ) );
            
            $create_result = erc_api_request( '/contacts', 'POST', $contact_data );
            
            if ( ! is_wp_error( $create_result ) && in_array( $create_result['code'], [ 200, 201 ] ) ) {
                $contact_slug = $create_result['body']['slug'] ?? null;
                $contact_id = $create_result['body']['id'] ?? null;
                if ( $contact_slug ) {
                    erc_log( 'Successfully created contact with slug: ' . $contact_slug . ' (ID: ' . $contact_id . ')' );
                } else {
                    erc_log( 'WARNING: Contact created but slug not found in response' );
                }
            } else {
                erc_log( 'WARNING: Contact creation failed with code: ' . ( $create_result['code'] ?? 'Unknown' ) );
                if ( isset( $create_result['body'] ) ) {
                    erc_log( 'Error details: ' . print_r( $create_result['body'], true ) );
                }
            }
        }
    } else {
        erc_log( 'No contact email provided, skipping contact creation' );
    }

    /**
     * =========================================================
     * 3. JOB: CREATE
     * According to docs: https://docs.recruitcrm.io/docs/rcrm-api-reference/14816ef96a63a-creates-a-new-job
     * Required fields: name, company_slug, description
     * Optional: contact_slug, location, custom_fields, number_of_openings, currency_id
     * =========================================================
     */
    erc_log( 'Creating job posting...' );
    
    // Prepare custom fields
    $custom_fields = [];
    
    // Map field names to custom field values
    $field_mapping = [
        'job_department' => 'Department / Team',
        'job_is_new' => 'Is this a new position?',
        'job_is_temporary' => 'Is this a temporary position?',
        'education_requirements' => 'Education Requirements',
        'experience_years' => 'Years of Experience Required',
        'certifications' => 'Professional Certifications',
        'technical_skills' => 'Technical Skills',
        'soft_skills' => 'Soft Skills',
        'remote_option' => 'Remote Option',
        'work_hours' => 'Work Hours',
        'ideal_candidate_profile' => 'Ideal Candidate Profile',
        'additional_information' => 'Additional Information',
    ];
    
    foreach ( $field_mapping as $form_field => $custom_field_name ) {
        if ( isset( $fields[ $form_field ] ) && ! empty( $fields[ $form_field ] ) ) {
            $custom_fields[] = [
                'custom_field' => [
                    'field_name' => $custom_field_name,
                    'field_value' => $fields[ $form_field ],
                ]
            ];
        }
    }
    
    // Add date fields if they exist
    if ( ! empty( $fields['desired_start_date'] ) ) {
        $custom_fields[] = [
            'custom_field' => [
                'field_name' => 'Desired Start Date',
                'field_value' => erc_format_date( $fields['desired_start_date'] ),
            ]
        ];
    }
    
    if ( ! empty( $fields['application_deadline'] ) ) {
        $custom_fields[] = [
            'custom_field' => [
                'field_name' => 'Application Deadline',
                'field_value' => erc_format_date( $fields['application_deadline'] ),
            ]
        ];
    }
    
    // Build job payload according to API docs
    $job_payload = [
        'name'                => $fields['job_title'] ?? 'New Position',
        'company_slug'        => $company_slug,  // Use slug, not ID
        'description'         => $fields['job_description'] ?? '',
        'number_of_openings'  => 1,  // Default to 1 opening
        'currency_id'         => 1,  // Default currency (check your Recruit CRM for correct ID)
    ];
    
    // Add optional fields if they exist
    if ( ! empty( $fields['job_location'] ) ) {
        $job_payload['location'] = $fields['job_location'];
    }
    
    if ( ! empty( $contact_slug ) ) {
        $job_payload['contact_slug'] = $contact_slug;  // Use slug, not ID
    }
    
    if ( ! empty( $custom_fields ) ) {
        $job_payload['custom_fields'] = $custom_fields;
    }
    
    erc_log( 'Job Payload: ' . json_encode( $job_payload ) );

    $create_result = erc_api_request( '/jobs', 'POST', $job_payload );

    if ( ! is_wp_error( $create_result ) && in_array( $create_result['code'], [ 200, 201 ] ) ) {
        $job_slug = $create_result['body']['slug'] ?? null;
        $job_id = $create_result['body']['id'] ?? null;
        if ( $job_slug ) {
            erc_log( 'Successfully created job posting with slug: ' . $job_slug . ' (ID: ' . $job_id . ')' );
        } else {
            erc_log( 'Job created but slug not found in response' );
        }
    } else {
        erc_log( 'ERROR: Job creation failed with code: ' . ( $create_result['code'] ?? 'Unknown' ) );
        if ( isset( $create_result['body'] ) ) {
            erc_log( 'Error details: ' . print_r( $create_result['body'], true ) );
        }
    }
    
    erc_log( '=== Elementor Form Submission Completed ===' );
}

/**
 * Format date fields to ISO-8601
 */
function erc_format_date( $date ) {
    if ( empty( $date ) ) {
        return '';
    }
    
    $timestamp = strtotime( $date );
    if ( $timestamp === false ) {
        erc_log( 'WARNING: Could not parse date: ' . $date );
        return '';
    }
    
    return date( 'Y-m-d', $timestamp );
}