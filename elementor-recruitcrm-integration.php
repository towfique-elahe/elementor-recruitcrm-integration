<?php
/**
 * Plugin Name: Elementor → Recruit CRM Integration
 * Description: Creates/updates Company, creates Contact, and creates Job in Recruit CRM from Elementor form submission.
 * Version: 1.5
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
                    <label for="erc_form_name">Elementor Form Name</label>
                </th>
                <td>
                    <input type="text" id="erc_form_name" name="erc_form_name"
                        value="<?php echo esc_attr( get_option( 'erc_form_name' ) ); ?>" class="regular-text" />
                    <p class="description">
                        Enter the exact name of your Elementor form (e.g., "Recruitment Form"). Leave empty to process
                        all forms.
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
 * =========================================================
 * ELEMENTOR FORM HANDLER
 * =========================================================
 */
add_action( 'elementor_pro/forms/process', 'erc_handle_elementor_submission', 10, 2 );

function erc_handle_elementor_submission( $record, $handler ) {
    erc_log( '=== Elementor Form Submission Started ===' );
    
    /**
     * Check if we should process this form
     * Option 1: Process ALL forms (if no form name is set)
     * Option 2: Process only specific form (if form name is set)
     */
    $target_form_name = get_option( 'erc_form_name' );
    
    if ( ! empty( $target_form_name ) ) {
        // Get form name safely
        $form_settings = $record->get_form_settings();
        $current_form_name = isset( $form_settings['form_name'] ) ? $form_settings['form_name'] : '';
        
        erc_log( 'Checking form: ' . $current_form_name . ' against target: ' . $target_form_name );
        
        if ( $current_form_name !== $target_form_name ) {
            erc_log( 'Skipping form - name does not match target' );
            return;
        }
    } else {
        erc_log( 'No target form name set - processing all forms' );
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
    $fields = [];
    foreach ( $record->get( 'fields' ) as $id => $field ) {
        $clean_id = str_replace( 'form-field-', '', $id );
        $fields[ $clean_id ] = sanitize_text_field( $field['value'] );
    }

    erc_log( 'Form Fields Received: ' . print_r( $fields, true ) );

    $base_url = 'https://api.recruitcrm.io/v1';

    $headers = [
        'Authorization' => 'Bearer ' . $api_token,
        'Content-Type'  => 'application/json',
        'Accept'        => 'application/json',
    ];

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
    $search_url = $base_url . '/companies/search?keyword=' . urlencode( $company_name );
    erc_log( 'Searching company: ' . $search_url );
    
    $search_company = wp_remote_get( $search_url, [ 'headers' => $headers ] );

    if ( is_wp_error( $search_company ) ) {
        erc_log( 'ERROR searching company: ' . $search_company->get_error_message() );
    } else {
        $response_code = wp_remote_retrieve_response_code( $search_company );
        $response_body = wp_remote_retrieve_body( $search_company );
        erc_log( 'Company Search Response Code: ' . $response_code );
        erc_log( 'Company Search Response Body: ' . $response_body );
        
        if ( $response_code === 200 ) {
            $result = json_decode( $response_body, true );
            $company_id = $result['data'][0]['id'] ?? null;
            
            if ( $company_id ) {
                erc_log( 'Found existing company with ID: ' . $company_id );
            } else {
                erc_log( 'No existing company found, will create new one' );
            }
        }
    }

    // Create company if not found
    if ( ! $company_id ) {
        $company_data = [
            'name'    => $company_name,
            'website' => $fields['company_website'] ?? '',
        ];
        
        erc_log( 'Creating company with data: ' . json_encode( $company_data ) );
        
        $create_company = wp_remote_post(
            $base_url . '/companies',
            [
                'headers' => $headers,
                'body'    => json_encode( $company_data ),
                'timeout' => 30, // Increase timeout
            ]
        );

        if ( is_wp_error( $create_company ) ) {
            erc_log( 'ERROR creating company: ' . $create_company->get_error_message() );
            return;
        }
        
        $response_code = wp_remote_retrieve_response_code( $create_company );
        $response_body = wp_remote_retrieve_body( $create_company );
        erc_log( 'Company Create Response Code: ' . $response_code );
        erc_log( 'Company Create Response Body: ' . $response_body );
        
        if ( $response_code === 201 || $response_code === 200 ) {
            $company_response = json_decode( $response_body, true );
            $company_id = $company_response['data']['id'] ?? null;
            
            if ( $company_id ) {
                erc_log( 'Successfully created company with ID: ' . $company_id );
            } else {
                erc_log( 'ERROR: Company ID not found in response' );
                return;
            }
        } else {
            erc_log( 'ERROR: Failed to create company. Response code: ' . $response_code );
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
        
        $search_url = $base_url . '/contacts/search?email=' . urlencode( $contact_email );
        erc_log( 'Searching contact: ' . $search_url );
        
        $search_contact = wp_remote_get( $search_url, [ 'headers' => $headers ] );

        if ( is_wp_error( $search_contact ) ) {
            erc_log( 'ERROR searching contact: ' . $search_contact->get_error_message() );
        } else {
            $response_code = wp_remote_retrieve_response_code( $search_contact );
            $response_body = wp_remote_retrieve_body( $search_contact );
            erc_log( 'Contact Search Response Code: ' . $response_code );
            erc_log( 'Contact Search Response Body: ' . $response_body );
            
            if ( $response_code === 200 ) {
                $result = json_decode( $response_body, true );
                $contact_id = $result['data'][0]['id'] ?? null;
                
                if ( $contact_id ) {
                    erc_log( 'Found existing contact with ID: ' . $contact_id );
                }
            }
        }

        if ( ! $contact_id ) {
            $contact_data = [
                'first_name' => $fields['contact_first_name'] ?? '',
                'last_name'  => $fields['contact_last_name'] ?? '',
                'email'      => $contact_email,
                'phone'      => $fields['contact_phone'] ?? '',
                'company_id' => $company_id,
            ];
            
            erc_log( 'Creating contact with data: ' . json_encode( $contact_data ) );
            
            $create_contact = wp_remote_post(
                $base_url . '/contacts',
                [
                    'headers' => $headers,
                    'body'    => json_encode( $contact_data ),
                    'timeout' => 30,
                ]
            );
            
            if ( is_wp_error( $create_contact ) ) {
                erc_log( 'ERROR creating contact: ' . $create_contact->get_error_message() );
            } else {
                $response_code = wp_remote_retrieve_response_code( $create_contact );
                $response_body = wp_remote_retrieve_body( $create_contact );
                erc_log( 'Contact Create Response Code: ' . $response_code );
                erc_log( 'Contact Create Response Body: ' . $response_body );
                
                if ( $response_code === 201 || $response_code === 200 ) {
                    erc_log( 'Successfully created contact' );
                } else {
                    erc_log( 'WARNING: Contact creation returned non-success code: ' . $response_code );
                }
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
    
    $job_payload = [
        'title'       => $fields['job_title'] ?? 'New Position',
        'company_id'  => $company_id,
        'description' => $fields['job_description'] ?? '',
        'location'    => $fields['job_location'] ?? '',
        'custom_fields' => [
            'Department / Team'            => $fields['job_department'] ?? '',
            'Is this a new position?'       => $fields['job_is_new'] ?? '',
            'Is this a temporary position?' => $fields['job_is_temporary'] ?? '',
            'Education Requirements'        => $fields['education_requirements'] ?? '',
            'Years of Experience Required'  => $fields['experience_years'] ?? '',
            'Professional Certifications'   => $fields['certifications'] ?? '',
            'Technical Skills'              => $fields['technical_skills'] ?? '',
            'Soft Skills'                   => $fields['soft_skills'] ?? '',
            'Remote Option'                 => $fields['remote_option'] ?? '',
            'Work Hours'                    => $fields['work_hours'] ?? '',
            'Ideal Candidate Profile'       => $fields['ideal_candidate_profile'] ?? '',
            'Desired Start Date'            => erc_format_date( $fields['desired_start_date'] ?? '' ),
            'Application Deadline'          => erc_format_date( $fields['application_deadline'] ?? '' ),
            'Additional Information'        => $fields['additional_information'] ?? '',
        ],
    ];
    
    erc_log( 'Job Payload: ' . json_encode( $job_payload ) );

    $create_job = wp_remote_post(
        $base_url . '/jobs',
        [
            'headers' => $headers,
            'body'    => json_encode( $job_payload ),
            'timeout' => 30,
        ]
    );

    if ( is_wp_error( $create_job ) ) {
        erc_log( 'ERROR creating job: ' . $create_job->get_error_message() );
    } else {
        $response_code = wp_remote_retrieve_response_code( $create_job );
        $response_body = wp_remote_retrieve_body( $create_job );
        erc_log( 'Job Create Response Code: ' . $response_code );
        erc_log( 'Job Create Response Body: ' . $response_body );
        
        if ( $response_code === 201 || $response_code === 200 ) {
            erc_log( 'Successfully created job posting!' );
        } else {
            erc_log( 'ERROR: Job creation failed with code: ' . $response_code );
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