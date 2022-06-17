<?php
declare(strict_types=1);

namespace NorthropGrumman\Artisan\Services\Ml_Auto_Tagging;

use \NorthropGrumman\Artisan\Services\Ml_Auto_Tagging\Classifier\Model\Post_Info_Aggregator;
use \NorthropGrumman\Artisan\Services\Ml_Auto_Tagging\Classifier\Model\Classification_Model;
use \NorthropGrumman\Artisan\Services\Ml_Auto_Tagging\Classifier\Model\Term_Model;
use \NorthropGrumman\Artisan\Services\Ml_Auto_Tagging\Classifier\Analysis\Vectorizer;
use \NorthropGrumman\Artisan\Services\Ml_Auto_Tagging\Classifier\Analysis\Classifier;
use \NorthropGrumman\Artisan\Services\Ml_Auto_Tagging\Classifier\Wrapper\Term;
use \Phpml\Metric\Accuracy;
use \Phpml\Metric\ConfusionMatrix;
use \Phpml\Dataset\ArrayDataset;
use \Phpml\CrossValidation\RandomSplit;

class Auto_Tag_Ajax_Hooks {

	public function selectClassifier() {
		$data = $_POST;
		if (isset($data["classifier_id"])) {
			update_option("artisan_ai_tags_classifier_id", $data["classifier_id"]);
			$model = Classification_Model::getClassificationModel($data["classifier_id"]);
			update_option('artisan_ai_tags_taxonomies', $model->selected_taxonomies);
			update_option('artisan_ai_tags_specified_features', $model->specified_features);
			update_option('artisan_ai_tags_test_percentage', $model->training_percentage);
			update_option('artisan_ai_tags_cost', $model->cost);
			update_option('artisan_ai_tags_gamma', $model->gamma);
			update_option('artisan_ai_tags_tolerance', $model->tolerance);
		} else {
	    	wp_send_json_error('Could not detect a classifer_id option.');
	    }
		wp_die();
	}

	public function deleteClassifier() {
		$data = $_POST;
		$retval = [];
		if (isset($data["classifier_id"])) {
			Classification_Model::deleteClassificationModel($data["classifier_id"]);
		} else {
	    	wp_send_json_error('Could not detect a classifer_id option.');
	    }
		wp_die();
	}

	public function getTermModelData() {
		$data = $_POST;
		$retval = [];
		$classifier_id = $data["classifier_id"];
		$terms = Term_Model::getTerms($classifier_id);
		foreach($terms as $term) {
			$TP = $term->true_positives;
			$TN =  $term->true_negatives;
			$FP =  $term->false_positives;
			$FN =  $term->false_negatives;
			$TPR = floatval($TP) / ($TP + $FN);
			$FNR = floatval($FN) / ($TP + $FN);
			$FPR = floatval($FP) / ($FP + $TN);
			$TNR = floatval($TN) / ($FP + $TN);
			$PPV = floatval($TP) / ($TP + $FP);
			$NPV = floatval($TN) / ($TN + $FN);
			$MCC = ((floatval($TP) * $TN) - ($FP * $FN)) / sqrt(($TP + $FP)*($TP + $FN)*($TN + $FP)*($TN + $FN));
			$term_info = array(
				"taxonomy_name" => $term->taxonomy_name,
				"term_name" => $term->term_name,
				"accuracy" => $term->accuracy,
				'total' => $term->total,
				'positives' => $term->positives,
				'negatives' => $term->negatives,
				'true_positives' => $TP,
				'true_negatives' =>  $TN,
				'false_positives' =>  $FP,
				'false_negatives' =>  $FN,
				'true_positive_rate' => is_nan($TPR) ? "irrelevant" : $TPR,
				'false_negative_rate' => is_nan($FNR) ? "irrelevant" : $FNR,
				'false_positive_rate' => is_nan($FPR) ? "irrelevant" : $FPR,
				'true_negative_rate' => is_nan($TNR) ? "irrelevant" : $TNR,
				'positive_predicitive_value' => is_nan($PPV) ? "irrelevant" : $PPV,
				'negative_predictive_value' => is_nan($NPV) ? "irrelevant" : $NPV,
				'mcc' => is_nan($MCC) ? "irrelevant" : $MCC
			);
			array_push($retval, $term_info);
		}
		wp_send_json_success($retval);
		wp_die();
	}


	public function saveSettings() {
		$data = $_POST;
		$args = Auto_Tag_Service::getConfig();
		if (isset($data["settings"])) {
	    	foreach ($data["settings"] as $setting) {
	    		if (isset($setting["name"])) {
	    			if($setting["name"] == "artisan_ai_tags_classifier_name") {
	    				//If the user didn't select a custom name, make it a timestamp
	    				if (!$setting["value"]) {
							$setting["value"] = current_time('timestamp');
	    				} else {
	    					//delete any classification with this name
							$matching_classification_models = Classification_Model::getMatchingClassificationModels($setting["value"]);
							foreach($matching_classification_models as $matching_classification_model) {
								Classification_Model::deleteClassificationModel($matching_classification_model->id);
							}
	    					update_option("artisan_ai_tags_classifier_id", null);
	    				}
	    			}
	    			update_option($setting["name"], $setting["value"]);
	    		}
	    	}
	    	$message = Auto_Tag_Service::getConfig();
	    } else {
	    	wp_send_json_error('Could not detect a settings option.');
	    }
	    wp_send_json_success($message);
		wp_die();
	}

	public function getTermSlugs($term) {
		return $term->slug;
	}

	public function classifyPost() {
		$data = $_POST;
		$post_id = intval($data["post_id"]);
		$retval = [];
		$post = get_post($post_id);
		$args = Auto_Tag_Service::getConfig();
		// Get existing taxonomies for this post
		foreach($args["artisan_ai_tags_taxonomies"] as $taxonomy) {
			$terms = get_the_terms($post_id, $taxonomy);
			$term_names = [];
			if ($terms) {
				$term_names = array_map([ $this, 'getTermSlugs' ], $terms);
			}
			$selected_terms[$taxonomy] = $term_names;
		}
		//Vectorize post
		$info = new Post_Info_Aggregator([], $args["artisan_ai_tags_specified_features"], $post_id);
		$vectorizer = new Vectorizer($info->features);
		//Identify classifier
		//TODO: Have a selected classifier
		//For now, we just use the most recent
		$classification = Classification_Model::getClassificationModel($args["artisan_ai_tags_classifier_id"]);
		$termModels = Term_Model::getTerms($classification->id);
		foreach($termModels as $termModel) {
			if (!isset($retval[$termModel->taxonomy_name])) {
				$retval[$termModel->taxonomy_name] = [];
			}
			$term = new Term($termModel->term_name, $termModel->taxonomy_name);
			$term->setPath(ML_AUTO_TAGGING_SERVICE_BASE_URI . $classification->classifier_directory);
			$term->loadClassifier();
			$term->predictProbability($vectorizer->vectorized_samples);
			array_push($retval[$termModel->taxonomy_name], [
				"name" => $term->name,
				"probabilities" => $term->predicted_probability,
				"checked" => in_array( $term->name, $selected_terms[$termModel->taxonomy_name] )
			] );
		} 
		wp_send_json_success($retval);
		wp_die();
	}

	public function generateClassifier() {
		$retval = [];
		$args = Auto_Tag_Service::getConfig();
		$taxonomies = $args["artisan_ai_tags_taxonomies"];
		$info = new Post_Info_Aggregator($taxonomies, $args["artisan_ai_tags_specified_features"], 0);
		$vectorizer = new Vectorizer($info->features);
		$classificationModel = Classification_Model::saveClassificationModel($args);
		update_option("artisan_ai_tags_classifier_id", $classificationModel->id);
		for ($i=0; $i < count($taxonomies); $i++) { 
			$retval[$taxonomies[$i]] = [];
			foreach($info->targets_collection[$i] as $target) {
				$labels = array_column($info->labels_collection, $i);
				$vectorized_labels = $vectorizer->vectorize_labels($labels, $target);
				$dataset = new ArrayDataset($vectorizer->vectorized_samples, $vectorized_labels);
				$randomizedDataset = new RandomSplit($dataset, $args['artisan_ai_tags_test_percentage']);
				//train group
				$train_samples = $randomizedDataset->getTrainSamples();
				$train_labels = $randomizedDataset->getTrainLabels();
				//test group
				$test_samples = $randomizedDataset->getTestSamples();
				$test_labels = $randomizedDataset->getTestLabels();
				$classifier = new Classifier($train_samples, $train_labels, $args);
				$classifier->trainClassifier($train_samples, $train_labels, $args);
				$predictedLabels = $classifier->predict($test_samples, $test_labels);
				$retval[$taxonomies[$i]][$target] = Accuracy::score($test_labels, $predictedLabels, true);
				$term = new Term($target, $taxonomies[$i]);
				$term->setClassifier($classifier, $classificationModel->id);
				$term->setPath(ML_AUTO_TAGGING_SERVICE_BASE_URI . $classificationModel->classifier_directory);
				$term->setAccuracy(Accuracy::score($test_labels, $predictedLabels, true));
				$term->interpolateConfusionMatrix(ConfusionMatrix::compute($test_labels, $predictedLabels, [1, 0]));
				Term_Model::saveTermModel($term);
			}
		}
		wp_send_json_success($retval);
	}

	public function __construct() {
		add_action( 'wp_ajax_saveSettings', [ $this, 'saveSettings' ]);  
		add_action( 'wp_ajax_generateClassifier', [ $this, 'generateClassifier' ]); 
		add_action( 'wp_ajax_classifyPost', [ $this, 'classifyPost' ]); 
		add_action( 'wp_ajax_selectClassifier', [ $this, 'selectClassifier' ]);
		add_action( 'wp_ajax_deleteClassifier', [ $this, 'deleteClassifier' ]); 
		add_action( 'wp_ajax_getTermModelData', [ $this, 'getTermModelData' ]); 
	}

}
