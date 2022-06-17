<?php
declare(strict_types=1);
namespace NorthropGrumman\Artisan\Services\Ml_Auto_Tagging\Classifier\Model;

class Term_Model {

	public static function intializeTable(){
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		$table_name = $wpdb->prefix . 'artisan_auto_tag_terms';
		$classification_table_name = $wpdb->prefix . 'artisan_auto_tag_classifications';
		if(!is_null(get_option("artisan_ai_tags_version")) || $GLOBALS["artisan_ai_tags_db_version"] > get_option("artisan_ai_tags_version")) {
			$sql = "CREATE TABLE $table_name (
							  id mediumint(9) NOT NULL AUTO_INCREMENT,
							  classification_id mediumint(9),
					  		  taxonomy_name tinytext NOT NULL,
					          term_name tinytext NOT NULL,
					          accuracy float,
					          total mediumint(9),
					          positives mediumint(9),
					          negatives mediumint(9),
					          true_positives mediumint(9),
					          true_negatives mediumint(9),
					          false_positives mediumint(9),
					          false_negatives mediumint(9),
							  PRIMARY KEY  (id)
							) $charset_collate;";	
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
		}
	}

	public static function saveTermModel(Object $term) {
		global $wpdb;
		$term->saveToFile();
		$table_name = $wpdb->prefix . 'artisan_auto_tag_terms';
		$wpdb->insert( 
			$table_name, 
			[
				'classification_id' => $term->getClassificationId(), 
				'taxonomy_name' => $term->taxonomy,
				'accuracy' => $term->getAccuracy(),
				'term_name' => $term->name,
				'total' => $term->total_samples,
				'positives' => $term->positives,
				'negatives' => $term->negatives,
				'true_positives' => $term->true_positives,
				'true_negatives' =>  $term->true_negatives,
				'false_positives' =>  $term->false_positives,
				'false_negatives' =>  $term->false_negatives,
            ]
		);
	}

	public static function getTerms(int $classification_id) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'artisan_auto_tag_terms';
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name WHERE classification_id = %d", $classification_id ) );
	}

}

?>