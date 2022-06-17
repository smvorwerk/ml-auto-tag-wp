<?php

use NorthropGrumman\Artisan\Services\Ml_Auto_Tagging\Classifier\Model\Classification_Model;

$classification_models = Classification_Model::getClassificationModels();

$currentConfiguration = $this->getConfig();

foreach ($classification_models as $classification_model) { 
	if ($classification_model->id != $currentConfiguration["artisan_ai_tags_classifier_id"]) { ?>
		<div class="artisan_ai_tags_past_classifier" id=<?php echo 'artisan_ai_tags_classifier_model_' . $classification_model->id ?> >
			<h3><?php echo  $classification_model->custom_name ?></h3>
			<p><strong>Created at:</strong> <?php echo date("m/d/y g:i A", strtotime($classification_model->created_at)) ?></p>

			<div class="artisan_ai_tags_classifier_info_list">
				<div class="artisan_ai_tags_classifier_info_item">
					<p><strong>Selected Taxonomies:</strong></p>
					<?php 
					foreach(maybe_unserialize($classification_model->selected_taxonomies) as $taxonomy) {
						echo ("<span>" . $taxonomy . "</span>");
					} ?>
				</div>

				<div class="artisan_ai_tags_classifier_info_item">
					<p><strong>Selected Features:</strong></p>
					<?php 
					foreach(maybe_unserialize($classification_model->specified_features) as $feature) {
						echo ("<span>" . $feature . "</span>");
					} ?>
				</div>
				<div class="artisan_ai_tags_classifier_info_item">
					<p><strong>Advanced settings:</strong></p>
					<?php 
						echo "<p><strong>Gamma: </strong>" . $classification_model->gamma . "</p>";
						echo "<p><strong>Tolerance: </strong>" . $classification_model->tolerance. "</p>";
						echo "<p><strong>Training Percentage: </strong>" . $classification_model->training_percentage . "</p>";
						echo "<p><strong>Cost: </strong>" . $classification_model->cost . "</p>";
					?>
				</div>
			</div>
			<table class="data_display"><!-- The datatable will go here. !--></table>
			<?php

			echo "<a href='#' class='artisan_ai_tags_button artisan_ai_tags_get_term_data button button-primary' value='" . $classification_model->id . "'>Get Classifier Data</a>";


			echo "<a href='#' class='artisan_ai_tags_button artisan_ai_tags_select_classifer button button-primary' value='" . $classification_model->id . "'>Select Classifier</a>";

			echo "<a href='#' class='artisan_ai_tags_button artisan_ai_tags_delete_classifer button button-primary' value='" . $classification_model->id . "'>Delete Classifier</a>";

			?>
		</div>
	<?php
	}
}

?>