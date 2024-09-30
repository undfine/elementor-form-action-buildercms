<?php
/**
 * Class BuilderCMS_Action
 * @see https://developers.elementor.com/custom-form-action/
 * Custom elementor form action after submit to add a lead to
 * BuilderCMS list via API
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class BuilderCMS_Action extends \ElementorPro\Modules\Forms\Classes\Integration_Base {

	const OPTION_NAME_API_KEY = 'buildercms_key';

	private function get_global_api_key() {
		return get_option( 'elementor_' . self::OPTION_NAME_API_KEY, '' );
	}

    
    /** TO DO: FIX DEPRECATED FUCTION 
    *   `ElementorPro\Modules\Forms\Module::add_form_action` is soft deprecated since 3.5.0
    *   Use `actions_registrar->register()` instead 
    */
    
	private function register_form_action(){
		\ElementorPro\Plugin::instance()->modules_manager->get_modules( 'forms' )->add_form_action( $this->get_name(), $this );  
        
	}

	public function get_name() {
		return 'buildercms';
	}

	public function get_label() {
		return __( 'BuilderCMS', 'builder-cms' );
	}

	/**
	 * Run
	 *
	 * Runs the action after submit
	 *
	 * @access public
	 * @param \ElementorPro\Modules\Forms\Classes\Form_Record $record
	 * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $ajax_handler
	 */
	public function run( $record, $ajax_handler ) {
        
        // Get form settings
		$settings = $record->get( 'form_settings' );

		// Get submitted Form data
		$raw_fields = $record->get( 'fields' );

		// Community settings at CMS
		$CommunityNumber = $this->get_global_api_key();
		$FollowUpCode = 'E';
		$Source = 'Internet'; //Formerly 'Website'
        $SourceDetail = $settings['builder_cms_source_detail'] ?? '';
        $AdminEmail = $settings['builder_cms_admin_email'] ?? '';
		$SendAdminEmail = !empty($AdminEmail) ? 'True': '';

		// Map special BuilderCMS  fields
		$mapped_fields = [
			'CommunityNumber' => esc_html($CommunityNumber),
			'FollowupCode' => $FollowUpCode,
			'Source' => $Source,
			'SourceDetail' => esc_html($SourceDetail),		
			
			// Extra fields
			'IPAddress' => \ElementorPro\Core\Utils::get_client_ip() ?? '',
			'CMSCookieID' => $_COOKIE['buildercms'] ?? '',
			'Referrer' => $_POST['referrer'] ?? '',
			
			// Admin Email
			'AdminEmail' => esc_html($AdminEmail),
			'AlwaysSendAdminEmail' => $SendAdminEmail,
		];

		// Required BuilderCMS fields
		$requiredFields = array_fill_keys(['email', 'firstname', 'lastname','CommunityNumber','FollowupCode'], '');

		// Merge with mapped fields
		$mapped_fields = array_merge( $requiredFields, $mapped_fields );

		// Prospect fields
		$cmsUserFields = array_fill_keys([
			'firstname',
			'lastname',
			'email',
			'phone',
			'workphone',
			'cellphone',
			'streetaddress',
			'city',
			'state',
			'zip',
			'country',
			'international',
			'autofollowupplan',
			'interests',
			'purchasetype',
			'company',
			'license',
			'comments',
		], '');

		// add the optional custom fields (1-6)
		for ($i=1; $i<=6; $i++){
			$cmsUserFields[ "custom$i" ] = '';
		}


		// Normalize the Form Data
		foreach ( $raw_fields as $id => $field ) {
			
			// format the field name to remove spaces, dashes and underscores
			$fieldname = strtolower( str_replace([' ','_','-'], '', $id ) );
			$value = !empty( $field['value'] ) ? $field['value'] : '';

			// check if the value is "on" for acceptance and checkboxes, and set to "yes"
			$value = ( $value == 'on') ? 'Yes' : esc_html( $value );

			// check if the fieldname has a value and is in the list of BuilderCMS fields
			if ( !empty( $value ) && isset( $cmsUserFields[ $fieldname ]) ) {
				$mapped_fields[ $fieldname ] = $value;
			}
		}
		
		// check if the form has all required fields
		$hasRequiredFields = count( $requiredFields ) == count( array_intersect_key( $requiredFields, $mapped_fields ) );
		

		// exit if the form does not have all required fields
		if ( !$hasRequiredFields ) {
			return new WP_Error( 'missing_cms_fields', __( 'Missing required fields' , "builder_cms" ), $mapped_fields );
		}

		// double check if "interests" is set (defaults to "broker" if true)
		if ( !isset($mapped_fields['purchasetype']) && isset( $mapped_fields['interests']) ){
			$mapped_fields['purchasetype'] = 'broker';
			$mapped_fields['interests'] = ''; //unset this field
		}

		//filter out empty values
		$mapped_fields = array_filter($mapped_fields);

		// Send the data to Builder CMS
		$this->send_request($mapped_fields);

	}

	private function send_request($data, $method = 'GET') {
		// $url = get_home_url(); 
		$url = 'https://www.buildercms.com/cms/custom/ProspectImport.aspx';

		if ($method == 'POST'){
			$url = 'https://buildercms.com/cms/CmsService.svc/CMSProspectImport';

			// Send request
			$response = wp_remote_post(
				$url,
				[
					'method' => 'POST',
					'headers' => [
						'Content-Type' => 'application/json',
					],
					'body' => wp_json_encode($data),
				]
			);

		} else {
			// Encode the data
			$datastring	= $this->encode_url_data($data); 
			
			// Format the URL string to be sent to Builder CMS
			$request_url = "$url?ProspectData=$datastring";
				
			
			// Send request
			$response = wp_remote_get($request_url);
		}

		if ( ! is_wp_error( $response ) ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			return $body;
		} else {
			$error_message = $response->get_error_message();
			throw new Exception( $error_message );
		}	
	}

	private function encode_url_data($data) {
		// Format the URL string to be sent to Builder CMS
		// example $datastring = "FirstName:".$FirstName."~LastName:".$LastName."~Email:".$Email."~Phone:".$PhoneNumber."~StreetAddress:".$StreetAddress."~City:".$City."~State:".$State."~Zip:".$Zip.$interests."~CommunityNumber:".$CommunityNumber."~FollowupCode:E~Source:".$Source."~AdminEmail:".$AdminEmail;
	
		$datastring = implode('~', array_map(function($key, $value) {
			return $key.':'.$value;
		}, array_keys($data), $data));
	
		return urlencode($datastring);	
	}

	/**
		 * Register Settings Section
		 *
		 * Registers the Action controls
		 *
		 * @access public
		 * @param \Elementor\Widget_Base $widget
		 */
		public function register_settings_section( $widget ) {

			$widget->start_controls_section(
			'section_builder_cms',
			[
				'label' => __( 'BuilderCMS', 'builder-cms' ),
				'condition' => [
					'submit_actions' => $this->get_name(),
				],
			]
		);

        $widget->add_control(
			'builder_cms_source_detail',
			[
				'label' => __( 'Source Detail', 'builder-cms' ),
				'type' => \Elementor\Controls_Manager::TEXT,
                'description' => __( 'Details like form or page name for record-keeping', 'builder-cms' ),
			]
		);
            
            
		$widget->add_control(
			'builder_cms_admin_email',
			[
				'label' => __( 'Admin Email', 'builder-cms' ),
				'type' => \Elementor\Controls_Manager::TEXT,
                'description' => __( 'Email address to receive to import summary from BuilderCMS', 'builder-cms' ),
			]
		);
          
        $widget->add_control(
			'about_custom_fields',
			[
				'label' => __( 'Custom Field Options', 'builder-cms' ),
				'type' => \Elementor\Controls_Manager::RAW_HTML,
				'separator' => 'before',
                'raw' => __( 'To import to CMS custom fields, add form fields with names "custom1 ... custom6"', 'builder-cms' ),
                'content_classes' => 'elementor-control-field-description',
                
			]
		);  
           

		$widget->end_controls_section();

		}

		/**
		 * On Export
		 *
		 * Clears form settings on export
		 * @access Public
		 * @param array $element
		 */
		public function on_export( $element ) {
			unset(
                $element['builder_cms_source_detail'],
				$element['builder_cms_admin_email'],
			);
		}

		public function register_admin_fields( \Elementor\Settings $settings ) {

			$settings->add_section( 
                \Elementor\Settings::TAB_INTEGRATIONS, 
                'builderCMS', [
				    'callback' => function() {
					   echo '<hr><h2>' . esc_html__( 'BuilderCMS', 'builder-cms' ) . '</h2>';
                    },
                    'fields' => [
                        self::OPTION_NAME_API_KEY => [
                            'label' => esc_html__( 'Community Number', 'builder-cms' ),
                            'field_args' => [
                                'type' => 'text',
                                'desc' => esc_html__( 'Enter the "Community Number" used with BuilderCMS (use "215" for testing).', 'builder-cms' )
                            ],
                        ],
				    ],
			] );
		}

		public function __construct() {

			if ( is_admin() ) {
				add_action( 'elementor/admin/after_create_settings/' . \Elementor\Settings::PAGE_ID, [ $this, 'register_admin_fields' ], 999 );
			}

			$this->register_form_action();
		}

} // End BuilderCMS_Action Class


add_action( 'elementor_pro/init', function() {

	$builderCMS_action = new BuilderCMS_Action();

});
