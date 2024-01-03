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
        
        
		$settings = $record->get( 'form_settings' );

		// Get submitted Form data
		$raw_fields = $record->get( 'fields' );

		// Normalize the Form Data
		$fields = [];
		foreach ( $raw_fields as $id => $field ) {
			$fields[ $id ] = $field['value'];
		}

		// Make sure that the user entered an email
		// which is required by BuilderCMS's API to subsribe a user
		if ( empty( $fields[ 'email' ] ) ) {
			return;
		}
                

		// Community settings at CMS
		$CommunityNumber = $this->get_global_api_key();
		$FollowUpCode = 'E';
		$Source = 'Website';
        $SourceDetail = !empty( $settings['builder_cms_source_detail'] ) ? esc_html( $settings['builder_cms_source_detail'] ) : '';
		
        $AdminEmail = esc_html( $settings['builder_cms_admin_email'] );
		$SendAdminEmail = !empty($AdminEmail) ? 'True': '';
		
        // Map the BuilderCMS specific fields to the form field_names
		$mapped_fields = [
				'FirstName' => esc_html( $fields['first_name']),
				'LastName' => esc_html( $fields['last_name']),
				'Email' => esc_html( $fields['email'] ),
				'Phone' => esc_html( $fields['phone']),
				'StreetAddress' => esc_html( $fields['street_address'] ).' '.esc_html( $fields['address_unit'] ),
				'City' => esc_html( $fields['city'] ),
				'State' => esc_html( $fields['state'] ),
				'Zip' => esc_html( $fields['zip'] ),
                
				'PurchaseType' => (!empty( $fields['interests']) )? 'broker' :'' ,
				'IPAddress' => \ElementorPro\Core\Utils::get_client_ip(),
				'CMSCookieID' => isset( $_COOKIE['buildercms'] )? $_COOKIE['buildercms'] : '',
				'Referrer' => isset( $_POST['referrer'] ) ? $_POST['referrer'] : '',
                'Source' => $Source,
                'SourceDetail' => $SourceDetail,
				'CommunityNumber' => $CommunityNumber,
				'FollowupCode' => $FollowUpCode,
				
				//extras
				'AutoFollowupPlan' => (!empty( $fields['autofollowup']) )? esc_html( $fields['autofollowup'] ) : '' ,
				'Company' => (!empty( $fields['company']) )? esc_html( $fields['company'] ) : '' ,
				'License' => (!empty( $fields['license']) )? esc_html( $fields['license'] ) : '' ,
                
                // Admin Email
                'AdminEmail' => $AdminEmail,
                'AlwaysSendAdminEmail' => $SendAdminEmail
		];
        
        // Setup custom fields    
        for ($i=1; $i<=6; $i++){
            
            $_field = 'custom'.$i; 
            
            if ( isset( $fields[$_field]) && !empty( $fields[$_field] ) ) {
                // Set "on" value for Acceptance fields = 'Yes'
                $val = ($fields[$_field] == 'on') ? 'Yes' : esc_html( $fields[$_field] ); 
                $mapped_fields['Custom'.$i] = $val;
            }
        }
                    
                

		// Base url for Builder CMS
        $base_page_url = 'https://www.buildercms.com/cms/custom/ProspectImport.aspx?ProspectData=';
		$datastring = '';

         /* //Send the request via POST
        $json_page_url = 'https://buildercms.com/cms/CmsService.svc/CMSProspectImport';
         wp_remote_post( $json_page_url , [
            'body' => $mapped_fields,
         ] );
         */


		foreach ( $mapped_fields as $fieldname => $input ) {
			if (!empty($input)){

				// Tilde before every value except Firstname
				if ($fieldname !='FirstName'){
					$datastring .='~';
				}
				$datastring .= $fieldname.':'.$input;
			}

		}

    // Example $datastring = "FirstName:".$FirstName."~LastName:".$LastName."~Email:".$Email."~Phone:".$PhoneNumber."~StreetAddress:".$StreetAddress."~City:".$City."~State:".$State."~Zip:".$Zip.$interests."~CommunityNumber:".$CommunityNumber."~FollowupCode:E~Source:".$Source."~AdminEmail:".$AdminEmail;

    // Encode data
    $encoded_data = urlencode($datastring);

    // Concatenate encoded string to url
    $cms_url =  $base_page_url.$encoded_data;

    $request = new WP_Http();
    $response = $request->post( $cms_url );

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
