<?php
namespace WpLdp;

/**
 * Class handling everything related to settings page and available options inside them
 **/
if (!class_exists('\WpLdp\WpLdpSettings')) {
    class WpLdpSettings {
      /**
       * __construct - Class default constructor
       *
       * @return {WpLdpSettings}  Instance of the WpLdpSettings Class
       */
      public function __construct() {
        add_action( 'admin_menu', array($this, 'ldp_menu'));
        add_action( 'admin_menu', array($this, 'menu_setup'));
        add_action( 'admin_init', array($this, 'backend_hooking'));
      }


       /**
        * initialize_container - Initialiaze the PAIR containers if the associated
        * option is checked
        *
        * @param  {type} $option    the checkbox to evaluate
        * @param  {type} $oldValue  the oldvalue (should be false)
        * @param  {type} $_newValue the new checkbox value (should be true)
        * @return {type}            description
        */
       function initialize_container() {
         if (isset($_GET['settings-updated'])) {
           $ldp_container_init = get_option('ldp_container_init', false);

           if ($ldp_container_init) {
             //TODO: Initialize the PAIR containers
             $pair_terms = array(
               'project' => array(
                  'label' => __('Project', 'wpldp'),
                  'rdftype' => 'foaf:project'
                ),
               'initiative' => array(
                  'label' => __('Initiative', 'wpldp'),
                  'rdftype' => 'pair:initiative'
                ),
               'organization' => array(
                  'label' => __('Organization', 'wpldp'),
                  'rdftype' => 'foaf:organization'
                ),
               'group' => array(
                  'label' => __('Group', 'wpldp'),
                  'rdftype' => 'foaf:group'
                ),
               'document' => array(
                  'label' => __('Document', 'wpldp'),
                  'rdftype' => 'foaf:document'
                ),
               'goodorservice' => array(
                  'label' => __('Good or Service', 'wpldp'),
                  'rdftype' => 'goodRelation:goodOrService'
                ),
               'artwork' => array(
                  'label' => __('Artwork', 'wpldp'),
                  'rdftype' => 'schema:artwork'
                ),
               'event' => array(
                  'label' => __('Event', 'wpldp'),
                  'rdftype' => 'schema:event'
                ),
               'place' => array(
                  'label' => __('Place', 'wpldp'),
                  'rdftype' => 'schema:place'
                ),
               'theme' => array(
                  'label' => __('Theme', 'wpldp'),
                  'rdftype' => 'pair:theme'
                ),
               'thesis' => array(
                  'label' => __('Thesis', 'wpldp'),
                  'rdftype' => 'pair:thesis'
                ),
               'actor' => array(
                  'label' => __('Actor', 'wpldp'),
                  'rdftype' => 'pair:actor'
                ),
               'idea' => array(
                  'label' => __('Idea', 'wpldp'),
                  'rdftype' => 'pair:label'
                ),
               'resource' => array(
                  'label' => __('Resource', 'wpldp'),
                  'rdftype' => 'pair:resource'
                )
             );

             foreach ($pair_terms as $term => $properties) {
               // Loop on the models files (or hardcoded array) and push them each as taxonomy term in the database
               $model = file_get_contents(__DIR__  . '/models/' . $term . '.json');
               $term_id = null;

               if (!term_exists($term, 'ldp_container')) {
                 $new_term = wp_insert_term(
                    $properties['label'],
                    'ldp_container',
                    array(
                      'slug' => $term,
                      'description' => sprintf( __('The %1$s object model', 'wpldp'), $term )
                    )
                  );

                  $term_id = $new_term['term_id'];
               } else {
                  $existing_term = get_term_by( 'slug', $term, 'ldp_container' );
                  $updated_term = wp_update_term(
                    $existing_term->term_id,
                    'ldp_container',
                    array(
                      'slug' => $term,
                      'description' => sprintf( __('The %1$s object model', 'wpldp'), $term )
                    )
                  );

                  $term_id = $existing_term->term_id;
               }

               if ( !empty( $term_id ) ) {
                 $term_meta = get_option("ldp_container_$term_id");
                 if (!is_array($term_meta)) {
                   $term_meta = array();
                 }

                 $term_meta['ldp_rdf_type'] = $properties['rdftype'];
                 $term_meta['ldp_model'] = stripslashes_deep($model);
                 update_option("ldp_container_$term_id", $term_meta);
               }
             }
           }
         }
       }


       /**
        * wpldp_validation_notice - Override the default update message or/and add a new one.
        *
        * @return {type}  current workflow
        */
       function wpldp_validation_notice() {
         global $pagenow;
         if ($pagenow == 'options-general.php' && $_GET['page'] == 'wpldp') { // change my-plugin to your plugin page
           if ( (isset($_GET['settings-updated']) && $_GET['settings-updated'] == 'true') ) {

            $ldp_container_init = get_option('ldp_container_init', false);
             if ($ldp_container_init) {
               $update_message = __('The PAIR containers have been initialized, enjoy ;-)', 'wpldp');
               add_settings_error('general', 'settings_updated', $update_message, 'updated');
             }
           }
         }
       }

       function menu_setup() {
          global $submenu;
          // Removing all resources menu
          remove_submenu_page('edit.php?post_type=ldp_resource', 'edit.php?post_type=ldp_resource');
          $terms = get_terms('ldp_container', array('hide_empty' => 0, 'order' => 'DESC'));

          $i = 0;
          foreach($terms as $term) {
            $this->term_slug = $term->slug;
            add_submenu_page(
              'edit.php?post_type=ldp_resource',
              __('List of all resources of type ' . $term->name, 'wpldp'),
              $term->name,
              'edit_posts',
              'edit.php?post_type=ldp_resource&ldp_container=' . $term->slug,
              false
            );

            // Reordering position of menu pages
            $key_to_remove = null;
            foreach($submenu['edit.php?post_type=ldp_resource'] as $submenu_item_key => $submenu_item_value) {
              if ($submenu_item_value[0] === $term->name) {
                $submenu['edit.php?post_type=ldp_resource'][10 - $i] = $submenu_item_value;
                $key_to_remove = $submenu_item_key;
              }
            }

            if (!empty($key_to_remove)) {
                unset($submenu['edit.php?post_type=ldp_resource'][$key_to_remove]);
            }
            $i++;
          }
          ksort($submenu['edit.php?post_type=ldp_resource']);
       }

       /**
        * ldp_menu - Generate the plugin settings menu and associated page
        *
        * @return {type}  current workflow
        */
       function ldp_menu() {
           $hook = add_options_page(
               __('WP-LDP Settings', 'wpldp'),
               __('WP-LDP Settings', 'wpldp'),
               'edit_posts',
               'wpldp',
               array($this, 'wpldp_options_page')
           );

           add_action( 'load-'.$hook, array($this, 'initialize_container') );
           add_action( 'admin_notices', array($this, 'wpldp_validation_notice'));
       }

       function wpldp_options_page() {
           echo '<div class="wrap">';
           echo '<h2>' . __('WP-LDP Settings', 'wpldp') . '</h2>';
           echo '<form method="post" action="options.php">';
             settings_fields('ldp_settings');
             do_settings_sections('wpldp');
             submit_button();
           echo '</form>';
           echo '</div>';
       }

       function ldp_context_field() {
           echo "<input type='text' size='150' name='ldp_context' value='" . get_option('ldp_context', 'http://owl.openinitiative.com/oicontext.jsonld') . "' />";
       }

       function ldp_container_init_field() {
         $optionValue = get_option('ldp_container_init', false);
         $optionValue = !empty($optionValue) ? 1 : 0;
         echo "<input type='checkbox' name='ldp_container_init' value='1' " . checked($optionValue, 1, false) . " />";
       }

       function backend_hooking() {
           add_settings_section(
             'ldp_settings',
             __('WP-LDP Settings', 'wpldp'),
             function() {
               echo __('The generals settings of the WP-LDP plugin.', 'wpldp');
             },
             'wpldp'
           );

           add_settings_field(
             'ldp_context',
             __('WP-LDP Context', 'wpldp'),
             array($this, 'ldp_context_field'),
             'wpldp',
             'ldp_settings'
           );

           add_settings_field(
             'ldp_container_init',
             __('Do you want to initialize PAIR containers ?', 'wpldp'),
             array($this, 'ldp_container_init_field'),
             'wpldp',
             'ldp_settings'
           );

           register_setting( 'ldp_settings', 'ldp_context' );
           register_setting( 'ldp_settings', 'ldp_container_init' );
       }
    }

    // Instanciating the settings page object
    $wpLdpSettings = new WpLdpSettings();
} else {
    exit ('Class WpLdpSettings already exists');
}

 ?>
