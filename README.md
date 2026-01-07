# Elementor → Recruit CRM Integration

A WordPress plugin that integrates Elementor forms with Recruit CRM to automatically create companies, contacts, and job postings from form submissions.

## Features

- **Seamless Integration**: Connects Elementor forms directly with Recruit CRM
- **Automated Workflow**: Creates/updates Company, creates Contact, and creates Job in one submission
- **Smart Matching**: Checks for existing companies and contacts before creating duplicates
- **Custom Field Support**: Maps form fields to Recruit CRM custom fields
- **Debug Logging**: Comprehensive logging system for troubleshooting
- **Secure**: API tokens stored securely in WordPress database

## Requirements

- WordPress 5.0 or higher
- Elementor Pro (for form functionality)
- Recruit CRM account with API access
- PHP 7.2 or higher

## Installation

1. Download the plugin ZIP file
2. Go to WordPress Admin → Plugins → Add New → Upload Plugin
3. Upload the ZIP file and click "Install Now"
4. Activate the plugin

## Configuration

### 1. Get Recruit CRM API Token

1. Log in to your Recruit CRM account
2. Go to Settings → API → Generate New Token
3. Copy the API token

### 2. Configure Plugin Settings

1. Go to WordPress Admin → Settings → Recruit CRM
2. Paste your API token in the "Recruit CRM API Token" field
3. (Optional) Set "Recruitment Form" as the target form name to process only specific forms
4. (Optional) Enable Debug Mode for troubleshooting
5. Save changes

### 3. Create Elementor Form

Create an Elementor form with these required fields:

**Required Fields:**

- `company_name` (Text field)
- `job_title` (Text field)
- `contact_email` (Email field)

**Optional Fields (will be mapped to custom fields):**

- `company_website` (URL field)
- `contact_first_name` (Text field)
- `contact_last_name` (Text field)
- `contact_phone` (Tel field)
- `job_description` (Textarea field)
- `job_location` (Text field)
- `job_department` (Text field)
- `job_is_new` (Radio/Select field)
- `job_is_temporary` (Radio/Select field)
- `education_requirements` (Textarea field)
- `experience_years` (Number field)
- `certifications` (Textarea field)
- `technical_skills` (Textarea field)
- `soft_skills` (Textarea field)
- `remote_option` (Select field)
- `work_hours` (Text field)
- `ideal_candidate_profile` (Textarea field)
- `additional_information` (Textarea field)
- `desired_start_date` (Date field)
- `application_deadline` (Date field)

## How It Works

### Submission Flow:

1. **Form Submission** → User submits Elementor form
2. **Company Processing** → Plugin searches for existing company by name, creates if not found
3. **Contact Processing** → Searches for contact by email, creates if not found, links to company
4. **Job Creation** → Creates job posting with all provided details
5. **Custom Fields** → Maps optional form fields to Recruit CRM custom fields

### API Endpoints Used:

- `GET /companies` - Search for existing company
- `POST /companies` - Create new company
- `GET /contacts` - Search for existing contact
- `POST /contacts` - Create new contact
- `POST /jobs` - Create job posting
- `PATCH /jobs/{slug}` - Update job with custom fields

## Debugging

### Debug Logs:

1. Enable Debug Mode in settings
2. Submit a test form
3. View detailed logs in Settings → Recruit CRM
4. Logs include:
   - API requests and responses
   - Error messages
   - Field mappings
   - Processing steps

### Common Issues:

1. **API Token Issues:**

   - Verify token is correctly copied
   - Check token permissions in Recruit CRM
   - Ensure no extra spaces in token

2. **Form Not Processing:**

   - Check form name matches target form name (if set)
   - Verify required fields exist in form
   - Check debug logs for specific errors

3. **Custom Fields Not Saving:**
   - Verify custom field names exist in Recruit CRM
   - Check field value length (max 500 characters)
   - Review API response in debug logs

## Custom Field Mapping

The plugin maps Elementor form fields to Recruit CRM custom fields using this mapping:

```
Elementor Field → Recruit CRM Custom Field
-------------------------------------------
job_department → Department / Team
job_is_new → Is this a new position?
job_is_temporary → Is this a temporary position?
education_requirements → Education Requirements
experience_years → Years of Experience Required
certifications → Professional Certifications
technical_skills → Technical Skills
soft_skills → Soft Skills
remote_option → Remote Option
work_hours → Work Hours
ideal_candidate_profile → Ideal Candidate Profile
additional_information → Additional Information
desired_start_date → Desired Start Date
application_deadline → Application Deadline
```

## Security

- API tokens are stored encrypted in WordPress database
- No sensitive data is logged
- All API communication uses HTTPS
- Form data is sanitized before processing

## Troubleshooting

### Error: "API token missing"

- Go to Settings → Recruit CRM and add your API token
- Verify the token is saved correctly

### Error: "Job creation failed"

- Check if job description is required (add placeholder if needed)
- Verify company was created successfully
- Check Recruit CRM API status

### Error: "Custom fields not saving"

- Verify custom field names exist in your Recruit CRM account
- Try submitting without custom fields first
- Check debug logs for specific error messages

## Support

For support, feature requests, or bug reports:

- Create an issue on GitHub
- Email: [your-email@example.com]
- Visit: [your-website.com]

## Changelog

### Version 2.2

- Added comprehensive debug logging system
- Improved error handling and validation
- Added custom field mapping support
- Enhanced form filtering options
- Fixed API request handling

### Version 2.1

- Added form name targeting
- Improved company and contact search
- Added date field formatting
- Enhanced error messages

### Version 2.0

- Initial stable release
- Basic company, contact, and job creation
- Elementor form integration

## License

GPL v2 or later

## Credits

Developed by Orbit570  
Author URI: https://towfiqueelahe.com

## Contributing

1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request

## Roadmap

- [ ] Add support for multiple forms
- [ ] Add webhook notifications
- [ ] Add test connection feature
- [ ] Add field mapping UI
- [ ] Add support for file attachments
- [ ] Add bulk import feature

---

**Note:** This plugin requires an active Recruit CRM subscription and API access. The free tier of Recruit CRM may have limitations on API usage.
