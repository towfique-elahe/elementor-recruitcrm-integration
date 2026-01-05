<?php
/**
 * Plugin Name: Elementor → Recruit CRM Integration
 * Description: Creates/updates Company, creates Contact, and creates Job in Recruit CRM from Elementor form submission.
 * Version: 1.9
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
    <div class="notice notice-info">
        <h3>Recent Debug Logs</h3>
        <?php
        $debug_log = get_option( 'erc_debug_log', [] );
        if ( ! empty( $debug_log ) ) {
            echo '<pre>';
            foreach ( array_slice( $debug_log, -10 ) as $log ) { // Show last 10 entries
                echo esc_html( $log ) . "\n";
            }
            echo '</pre>';
        } else {
            echo '<p>No debug logs yet.</p>';
        }
        ?>
        <form method="post" style="margin-top: 10px;">
            <input type="hidden" name="erc_clear_logs" value="1">
            <?php wp_nonce_field( 'erc_clear_logs_action', 'erc_clear_logs_nonce' ); ?>
            <input type="submit" class="button" value="Clear Debug Logs">
        </form>
    </div>
    <?php endif; ?>
</div>
<?php
    // Handle log clearing
    if ( isset( $_POST['erc_clear_logs'] ) && wp_verify_nonce( $_POST['erc_clear_logs_nonce'], 'erc_clear_logs_action' ) ) {
        update_option( 'erc_debug_log', [] );
        echo '<div class="notice notice-success"><p>Debug logs cleared.</p></div>';
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
 * ELEMENTOR FORM HANDLER - UPDATED APPROACH
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
    // But we need to be careful about calling get_form_settings()
    
    if ( ! empty( $target_form_name ) ) {
        // Try a safer approach - check for required fields first
        // Get the form fields first
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
        
        // Now we know this is likely a Recruit CRM form, but we want to be sure
        // Try to get form name safely - use reflection or try/catch
        $current_form_name = '';
        
        // Method 1: Try with parameter (the correct way according to error)
        try {
            $current_form_name = $record->get_form_settings( 'form_name' );
            erc_log( 'Got form name via get_form_settings("form_name"): ' . $current_form_name );
        } catch ( Exception $e ) {
            erc_log( 'Error getting form name with parameter: ' . $e->getMessage() );
        }
        
        // Method 2: Try to use reflection to call the method properly
        if ( empty( $current_form_name ) ) {
            try {
                // Use reflection to inspect the method
                $reflection = new ReflectionClass( $record );
                $method = $reflection->getMethod( 'get_form_settings' );
                $params = $method->getParameters();
                
                if ( count( $params ) > 0 ) {
                    // The method expects parameters
                    erc_log( 'get_form_settings() expects ' . count( $params ) . ' parameters' );
                    
                    // Try with empty string as parameter
                    $current_form_name = $record->get_form_settings( '' );
                    erc_log( 'Got form name with empty parameter: ' . $current_form_name );
                }
            } catch ( Exception $e ) {
                erc_log( 'Reflection error: ' . $e->getMessage() );
            }
        }
        
        // If we still don't have a form name, log all available data
        if ( empty( $current_form_name ) ) {
            erc_log( 'Could not determine form name. Available data:' );
            
            // Try to get raw form data
            try {
                // Use get_data() method which might be available
                if ( method_exists( $record, 'get_data' ) ) {
                    $form_data = $record->get_data();
                    erc_log( 'Form data via get_data(): ' . print_r( $form_data, true ) );
                }
            } catch ( Exception $e ) {
                erc_log( 'Error getting form data: ' . $e->getMessage() );
            }
            
            // Since we can't get the form name but we have required fields,
            // we'll proceed but log a warning
            erc_log( 'WARNING: Proceeding without form name verification' );
        } else {
            // We have a form name, check if it matches
            erc_log( 'Current Form Name: ' . $current_form_name );
            erc_log( 'Target Form Name: ' . $target_form_name );
            
            if ( $current_form_name !== $target_form_name ) {
                erc_log( 'Skipping form - name does not match target' );
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
     * Normalize Elementor fields (if not already done)
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

    $company_id = null;

    // Search for existing company
    $search_result = erc_api_request( '/companies', 'GET', [ 'name' => $company_name ] );
    
    if ( ! is_wp_error( $search_result ) && $search_result['code'] === 200 ) {
        // Check if company exists in response
        if ( isset( $search_result['body']['data'] ) && is_array( $search_result['body']['data'] ) ) {
            foreach ( $search_result['body']['data'] as $company ) {
                if ( isset( $company['name'] ) && strcasecmp( $company['name'], $company_name ) === 0 ) {
                    $company_id = $company['id'] ?? null;
                    if ( $company_id ) {
                        erc_log( 'Found existing company with ID: ' . $company_id );
                        break;
                    }
                }
            }
            
            if ( ! $company_id ) {
                erc_log( 'No existing company found with exact name match' );
            }
        } else {
            erc_log( 'No companies data in search response' );
        }
    } else {
        erc_log( 'Company search failed or returned error, will try to create new company' );
    }

    // Create company if not found
    if ( ! $company_id ) {
        $company_data = [
            'name'        => $company_name,
            'company_url' => $fields['company_website'] ?? '',
        ];
        
        erc_log( 'Creating company with data: ' . json_encode( $company_data ) );
        
        $create_result = erc_api_request( '/companies', 'POST', $company_data );
        
        if ( ! is_wp_error( $create_result ) && in_array( $create_result['code'], [ 200, 201 ] ) ) {
            $company_id = $create_result['body']['id'] ?? null;
            
            if ( $company_id ) {
                erc_log( 'Successfully created company with ID: ' . $company_id );
            } else {
                // Try alternative location for ID
                $company_id = $create_result['body']['data']['id'] ?? null;
                if ( $company_id ) {
                    erc_log( 'Successfully created company with ID (from data field): ' . $company_id );
                } else {
                    erc_log( 'ERROR: Company ID not found in response. Full response: ' . $create_result['raw'] );
                    return;
                }
            }
        } else {
            erc_log( 'ERROR: Failed to create company. Response code: ' . ( $create_result['code'] ?? 'Unknown' ) );
            
            // Try alternative field names
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
    $contact_id    = null;

    if ( $contact_email ) {
        erc_log( 'Processing Contact with email: ' . $contact_email );
        
        // Search for contact by email
        $search_result = erc_api_request( '/contacts', 'GET', [ 'email' => $contact_email ] );
        
        if ( ! is_wp_error( $search_result ) && $search_result['code'] === 200 ) {
            if ( isset( $search_result['body']['data'] ) && is_array( $search_result['body']['data'] ) ) {
                foreach ( $search_result['body']['data'] as $contact ) {
                    if ( isset( $contact['email'] ) && strcasecmp( $contact['email'], $contact_email ) === 0 ) {
                        $contact_id = $contact['id'] ?? null;
                        if ( $contact_id ) {
                            erc_log( 'Found existing contact with ID: ' . $contact_id );
                            break;
                        }
                    }
                }
            }
        }

        if ( ! $contact_id ) {
            $contact_data = [
                'first_name' => $fields['contact_first_name'] ?? '',
                'last_name'  => $fields['contact_last_name'] ?? '',
                'email'      => $contact_email,
                'contact_number' => $fields['contact_phone'] ?? '',
                'company'    => $company_id,
            ];
            
            erc_log( 'Creating contact with data: ' . json_encode( $contact_data ) );
            
            $create_result = erc_api_request( '/contacts', 'POST', $contact_data );
            
            if ( ! is_wp_error( $create_result ) && in_array( $create_result['code'], [ 200, 201 ] ) ) {
                $contact_id = $create_result['body']['id'] ?? $create_result['body']['data']['id'] ?? null;
                if ( $contact_id ) {
                    erc_log( 'Successfully created contact with ID: ' . $contact_id );
                } else {
                    erc_log( 'WARNING: Contact created but ID not found in response' );
                }
            } else {
                erc_log( 'WARNING: Contact creation failed with code: ' . ( $create_result['code'] ?? 'Unknown' ) );
            }
        }
    } else {
        erc_log( 'No contact email provided, skipping contact creation' );
    }

    /**
     * =========================================================
     * 3. JOB: CREATE
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
    
    $job_payload = [
        'name'          => $fields['job_title'] ?? 'New Position',
        'company_id'    => $company_id,
        'description'   => $fields['job_description'] ?? '',
        'location'      => $fields['job_location'] ?? '',
        'custom_fields' => $custom_fields,
    ];
    
    erc_log( 'Job Payload: ' . json_encode( $job_payload ) );

    $create_result = erc_api_request( '/jobs', 'POST', $job_payload );

    if ( ! is_wp_error( $create_result ) && in_array( $create_result['code'], [ 200, 201 ] ) ) {
        $job_id = $create_result['body']['id'] ?? $create_result['body']['data']['id'] ?? null;
        if ( $job_id ) {
            erc_log( 'Successfully created job posting with ID: ' . $job_id );
        } else {
            erc_log( 'Job created but ID not found in response' );
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