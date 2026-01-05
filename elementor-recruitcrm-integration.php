<?php
/**
 * Plugin Name: Elementor → Recruit CRM Integration
 * Description: Creates/updates Company, creates Contact, and creates Job in Recruit CRM from Elementor form submission.
 * Version: 1.2
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
        </table>

        <?php submit_button(); ?>
    </form>
</div>
<?php
}

/**
 * =========================================================
 * ELEMENTOR FORM HANDLER
 * =========================================================
 */
add_action( 'elementor_pro/forms/process', 'erc_handle_elementor_submission', 10, 3 );

function erc_handle_elementor_submission( $record, $handler, $form_data ) {

    /**
     * Trigger ONLY if Webhook action exists
     */
    $actions = $record->get_form_settings( 'actions' );
    if ( ! is_array( $actions ) || ! in_array( 'webhook', $actions, true ) ) {
        return;
    }

    /**
     * Get API token from settings
     */
    $api_token = get_option( 'erc_recruitcrm_api_token' );
    if ( empty( $api_token ) ) {
        error_log( 'Recruit CRM API token missing' );
        return;
    }

    /**
     * Normalize Elementor fields
     */
    $fields = [];
    foreach ( $record->get( 'fields' ) as $id => $field ) {
        $clean_id = str_replace( 'form-field-', '', $id );
        $fields[ $clean_id ] = sanitize_text_field( $field['value'] );
    }

    // error_log( print_r( $fields, true ) ); // Enable for debugging only

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
        return;
    }

    $company_id = null;

    $search_company = wp_remote_get(
        $base_url . '/companies/search?keyword=' . urlencode( $company_name ),
        [ 'headers' => $headers ]
    );

    if ( ! is_wp_error( $search_company ) ) {
        $result = json_decode( wp_remote_retrieve_body( $search_company ), true );
        $company_id = $result['data'][0]['id'] ?? null;
    }

    if ( ! $company_id ) {
        $create_company = wp_remote_post(
            $base_url . '/companies',
            [
                'headers' => $headers,
                'body'    => json_encode([
                    'name'    => $company_name,
                    'website' => $fields['company_website'] ?? '',
                ]),
            ]
        );

        if ( is_wp_error( $create_company ) ) {
            error_log( 'Recruit CRM company creation failed' );
            return;
        }

        $company_response = json_decode( wp_remote_retrieve_body( $create_company ), true );
        $company_id = $company_response['data']['id'] ?? null;
    }

    if ( ! $company_id ) {
        return;
    }

    /**
     * =========================================================
     * 2. CONTACT: SEARCH → CREATE
     * =========================================================
     */
    $contact_email = $fields['contact_email'] ?? '';
    $contact_id    = null;

    if ( $contact_email ) {
        $search_contact = wp_remote_get(
            $base_url . '/contacts/search?email=' . urlencode( $contact_email ),
            [ 'headers' => $headers ]
        );

        if ( ! is_wp_error( $search_contact ) ) {
            $result = json_decode( wp_remote_retrieve_body( $search_contact ), true );
            $contact_id = $result['data'][0]['id'] ?? null;
        }
    }

    if ( ! $contact_id ) {
        wp_remote_post(
            $base_url . '/contacts',
            [
                'headers' => $headers,
                'body'    => json_encode([
                    'first_name' => $fields['contact_first_name'] ?? '',
                    'last_name'  => $fields['contact_last_name'] ?? '',
                    'email'      => $contact_email,
                    'phone'      => $fields['contact_phone'] ?? '',
                    'company_id' => $company_id,
                ]),
            ]
        );
    }

    /**
     * =========================================================
     * 3. JOB: CREATE
     * =========================================================
     */
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

    wp_remote_post(
        $base_url . '/jobs',
        [
            'headers' => $headers,
            'body'    => json_encode( $job_payload ),
        ]
    );
}

/**
 * Format date fields to ISO-8601
 */
function erc_format_date( $date ) {
    if ( empty( $date ) ) {
        return '';
    }
    return date( 'Y-m-d', strtotime( $date ) );
}