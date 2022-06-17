<?php
declare(strict_types=1);
namespace NorthropGrumman\Artisan\Services\Ml_Auto_Tagging;

use \NorthropGrumman\Artisan\Services\Ml_Auto_Tagging\Classifier\Model\Classification_Model;
use \NorthropGrumman\Artisan\Services\Ml_Auto_Tagging\Classifier\Model\Term_Model;

class Auto_Tag_Service {

	

	private function buildConfig() {
		add_option( "artisan_ai_tags_version", "0.1.0" );
		add_option( 'artisan_ai_tags_taxonomies', [ "category", "post_tag"  ] );
		add_option( 'artisan_ai_tags_specified_features', [ "post_title", "post_content"  ] );
		add_option( 'artisan_ai_tags_test_percentage', .3 );
		add_option( 'artisan_ai_tags_cost', 1.0 );
		add_option( 'artisan_ai_tags_gamma', null );
		add_option( 'artisan_ai_tags_tolerance', .001 );
		add_option( 'artisan_ai_tags_cache_size', 100 );
		add_option( 'artisan_ai_tags_label_minimum_count', 1 );
		add_option( 'artisan_ai_tags_save_old_classifiers', true );
		add_option( 'artisan_ai_tags_classifier_id', null );
	}

    public function enqueueAdminScripts() {
		wp_enqueue_style( 'artisan-ai-tags-admin-style', plugins_url('static/css/style.css', __FILE__));
		wp_enqueue_style( 'jquery.dataTables-style', plugins_url('static/css/jquery.dataTables.min.css', __FILE__));
   		wp_enqueue_script( 'jquery');
   		wp_enqueue_script( 'jquery.dataTables', plugins_url('static/js/jquery.dataTables.min.js', __FILE__), array ( 'jquery' ), 1.1, true);
    	wp_enqueue_script( 'artisan-ai-tags-settings', plugins_url('static/js/settings.js', __FILE__), array ( 'jquery', 'jquery.dataTables' ), 1.1, true);
    	wp_enqueue_script( 'artisan-ai-tags-classify-post', plugins_url('static/js/classify_post.js', __FILE__), array ( 'jquery' ), 1.1, true);
    	wp_localize_script( 'artisan-ai-tags-settings', 'Artisan_Auto_Tag_Ajax_Settings', [ 'ajaxurl'    => admin_url( 'admin-ajax.php' ) ] );
		wp_localize_script( 'artisan-ai-tags-classify-post', 'Artisan_Auto_Tag_Ajax_Settings', [ 'ajaxurl'    => admin_url( 'admin-ajax.php' ) ] );
    }

    public function metaboxSaveMeta( $post_id ) {
    	$data = $_POST;
    	$post = get_post($post_id);
    	$args = $this->getConfig();
    	$taxonomies = $args["artisan_ai_tags_taxonomies"];
    	foreach($taxonomies as $taxonomy) {
    		//get all terms
    		$all_terms = get_terms([ 'taxonomy' => $taxonomy ]);
    		foreach($all_terms as $term) {
    			$id_name = $taxonomy . "||" . $term->slug; // This is the id we used on the form
    			if (isset($data[$id_name])) { // If it is checked on the form, associate the term with the post
    				wp_set_post_terms($post_id, $term->term_id, $taxonomy, true );
    			} else { // If it's not checked on the form, unassociate the term with the post
    				wp_remove_object_terms($post_id, $term->term_id, $taxonomy);
    			}
    		}
    	}
    }

    public function displayPluginMetaBox() {
    	require_once 'partials/artisan-ai-tags-meta-box.php';
    }

    public function addPluginMetaBox() {
		add_meta_box( 'artisan-ai-tags-classify-post', // ID attribute of metabox
                  'Classify Post - ArtisanAI',       // Title of metabox visible to user
                  [ $this, 'displayPluginMetaBox' ], // Function that prints box in wp-admin
                  'post',              // Show box for posts, pages, custom, etc.
                  'normal',            // Where on the page to show the box
                  'low' );            // Priority of box in display order
    }

	public function displayPluginAdminSettings() {
         require_once 'partials/artisan-ai-auto-tagger-tag-admin-settings-display.php';
    }

	public function addPluginAdminMenu() {
	add_menu_page(  $this->service_name, 'ArtisanAI Auto Tagger', 'administrator', "artisan-ai-auto-tagger-tag", [ $this, 'displayPluginAdminSettings'  ] );
	}

	public static function getConfig() {
		return array (
			"artisan_ai_tags_taxonomies" => get_option('artisan_ai_tags_taxonomies'),
			"artisan_ai_tags_cost" => floatval(get_option('artisan_ai_tags_cost')),
			"artisan_ai_tags_gamma" => floatval(get_option('artisan_ai_tags_gamma')),
			"artisan_ai_tags_tolerance" => floatval(get_option('artisan_ai_tags_tolerance')),
			"artisan_ai_tags_cache_size" => intval(get_option('artisan_ai_tags_cache_size')),
			"artisan_ai_tags_save_old_classifiers" => (get_option('artisan_ai_tags_save_old_classifiers') == "false" ? false : true) ,
			"artisan_ai_tags_specified_features" => get_option('artisan_ai_tags_specified_features'),
			"artisan_ai_tags_label_minimum_count" => get_option('artisan_ai_tags_label_minimum_count'),
			"artisan_ai_tags_test_percentage" => floatval(get_option('artisan_ai_tags_test_percentage')),
			"artisan_ai_tags_classifier_name" => get_option("artisan_ai_tags_classifier_name"),
			"artisan_ai_tags_classifier_id" => intval(get_option("artisan_ai_tags_classifier_id"))
		);
	}


	private function init() {
		//If we haven't set the default classification configuration, configure it
		$GLOBALS["artisan_ai_tags_db_version"] = 1.0;

		//If SQL Table isn't initiated, initiate it
		Classification_Model::intializeTable();
		Term_Model::intializeTable();

		$this->buildConfig();

		update_option("artisan_ai_tags_version", $GLOBALS["artisan_ai_tags_db_version"]);
	}

	public function __construct() {
		// get wp-content/uploads directory
		$upload_dir = wp_upload_dir();
		$this->upload_dir = $upload_dir['basedir'];
        define( 'ML_AUTO_TAGGING_SERVICE_BASE_URI', __DIR__ );
        require_once __DIR__ . '/vendor/autoload.php';
		$this->init();
		new Auto_Tag_Ajax_Hooks();
		//Add actions and hooks
		add_action( 'admin_enqueue_scripts',  [ $this, 'enqueueAdminScripts' ]);
		add_action('admin_menu', [ $this, 'addPluginAdminMenu'  ]); 
		add_action('add_meta_boxes', [ $this, 'addPluginMetaBox' ] );
		add_action('save_post', [ $this, 'metaboxSaveMeta' ]);
	}

}

new Auto_Tag_Service();

?>