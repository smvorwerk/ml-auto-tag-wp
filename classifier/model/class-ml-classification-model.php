<?php
declare(strict_types=1);
namespace NorthropGrumman\Artisan\Services\Ml_Auto_Tagging\Classifier\Model;

global $wpdb;

class Classification_Model {

	public static function intializeTable() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'artisan_auto_tag_classifications';
		$charset_collate = $wpdb->get_charset_collate();
		if(!is_null(get_option("artisan_ai_tags_version")) || $GLOBALS["artisan_ai_tags_db_version"] > get_option("artisan_ai_tags_version")) {
			$sql = "CREATE TABLE $table_name (
							  id mediumint(9) NOT NULL AUTO_INCREMENT,
							  created_at datetime NOT NULL,
							  custom_name tinytext NOT NULL,
							  classifier_directory tinytext NOT NULL,
							  active bool,
							  gamma float,
							  cost float,
							  tolerance float,
							  training_percentage float,
							  specified_features TEXT,
							  selected_taxonomies TEXT,
							  PRIMARY KEY  (id)
							) $charset_collate;";
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
		}
	}

	public static function saveClassificationModel(array $args) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'artisan_auto_tag_classifications';
		$specified_features = maybe_serialize($args["artisan_ai_tags_specified_features"]);
		$selected_taxonomies = maybe_serialize($args["artisan_ai_tags_taxonomies"]);
		$gamma = $args["artisan_ai_tags_gamma"];
		$tolerance = $args["artisan_ai_tags_tolerance"];
		$cost = $args["artisan_ai_tags_cost"];
		$training_percentage = $args["artisan_ai_tags_test_percentage"];
		$custom_name = $args["artisan_ai_tags_classifier_name"];
		$classifier_directory = 'bin/' . $custom_name . '/';
		foreach($args["artisan_ai_tags_taxonomies"] as $taxonomy) {
			mkdir( ML_AUTO_TAGGING_SERVICE_BASE_URI . $classifier_directory . $taxonomy, 0777, true);
		}
		$wpdb->insert( $table_name, 
			[ 
				'created_at' => current_time( 'mysql' ), 
				'custom_name' => $custom_name,
				'gamma' => $gamma,
				'tolerance' => $tolerance,
				'cost' => $cost,
				'active' => true,
				'training_percentage' => $training_percentage,
				'classifier_directory' => $classifier_directory,
				'specified_features' => $specified_features,
				'selected_taxonomies' => $selected_taxonomies,
            ]
		);
		return $wpdb->get_row( "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 1", OBJECT );
	}


	public static function getClassificationModel(int $classifier_id) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'artisan_auto_tag_classifications';
		if ($classifier_id !== 0) {
			$classifications = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $classifier_id), OBJECT );
		} else {
			//Barring a specified classifier, just use the most recent
			$classifications = $wpdb->get_row( "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 1", OBJECT );
		}
		return $classifications;
	}

	public static function getClassificationModels() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'artisan_auto_tag_classifications';
		$classifications = $wpdb->get_results( "SELECT * FROM $table_name WHERE active = true ORDER BY created_at DESC", OBJECT ); 
        return $classifications;
	}

	public static function deleteDirectory(String $dir) {
	    if (!file_exists($dir))
	        return true;
	    if (!is_dir($dir))
	        return unlink($dir);
	    foreach (scandir($dir) as $item) {
	        if ($item == '.' || $item == '..')
	            continue;
	        if (!Classification_Model::deleteDirectory($dir . DIRECTORY_SEPARATOR . $item))
	            return false;
	    }
	    return rmdir($dir);
	}


	public static function deleteClassificationModel(int $classification_id) {
		global $wpdb;
		$classification = Classification_Model::getClassificationModel($classification_id);
		Classification_Model::deleteDirectory(ML_AUTO_TAGGING_SERVICE_BASE_URI . $classification->classifier_directory);
		$table_name = $wpdb->prefix . 'artisan_auto_tag_classifications';
		$wpdb->update( $table_name, array( 'active' => false ), array( 'id' => $classification->id ) );
	}

	public static function getMatchingClassificationModels(string $classification_name) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'artisan_auto_tag_classifications';
		$classifications = $wpdb->get_results( $wpdb->prepare("SELECT * FROM $table_name WHERE custom_name = %s ORDER BY created_at DESC", $classification_name), OBJECT);
		return $classifications;
	}

}