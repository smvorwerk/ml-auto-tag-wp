function artisan_ai_tags_generateClassifier() {
	button = jQuery( "#generate_classifier" );
    const data = {"action" : "generateClassifier"};
	jQuery.post( Artisan_Auto_Tag_Ajax_Settings.ajaxurl, data)
		.done( function(response ) { location.reload(); })
		.fail(function(xhr, status, error) {
			jQuery( "#artisan_ai_tags_error" ).html(xhr.responseText);
			button.width( button.width() ).text('Generate Classifier');
	    });
}

function artisan_ai_tags_saveSettings(event) {
    let serialized_array = jQuery("#artisan_ai_tags_save_settings_form").serializeArray();
	const Artisan_Auto_Tag_Taxonomies = [];
	const Artisan_Auto_Tag_Specified_Features = [];
	//Reduce form values down into one value
	serialized_form = serialized_array.filter(
		function(object){
			if (object.name === "taxonomies") {
				Artisan_Auto_Tag_Taxonomies.push(object.value);
				return false;
			}
			if (object.name === "features") {
				Artisan_Auto_Tag_Specified_Features.push(object.value)
				return false;
			}
			return true;
	});

	serialized_form.push({name : "Artisan_Auto_Tag_Taxonomies", value : Artisan_Auto_Tag_Taxonomies});
	serialized_form.push({name : "Artisan_Auto_Tag_Specified_Features", value : Artisan_Auto_Tag_Specified_Features});
	serialized_form = {settings : serialized_form};
	serialized_form["action"] = 'saveSettings';
	console.log(serialized_form)
	jQuery.post( Artisan_Auto_Tag_Ajax_Settings.ajaxurl, serialized_form)
		.done( function(response ) { artisan_ai_tags_generateClassifier(); })
    	.fail(function(xhr, status, error) { jQuery("#artisan_ai_tags_error").html(xhr.responseText); });
}

jQuery( "#generate_classifier" ).on("click", function( event ) {
	event.preventDefault(); //Prevent jumping to the top
	const button = jQuery( this );
    button.width( button.width() ).text('...');
    artisan_ai_tags_saveSettings(event);
});

jQuery( ".artisan_ai_tags_select_classifer" ).on("click", function( event ) {
	event.preventDefault();
	button = jQuery( this );
    button.width( button.width() ).text('...');
    let classifier_id = button.attr("value");
    const data = {
    	"action" : "selectClassifier",
    	"classifier_id" : classifier_id
	};

	jQuery.post( Artisan_Auto_Tag_Ajax_Settings.ajaxurl, data)
		.done( function(response ) { location.reload(); })
		.fail(function(xhr, status, error) {
			jQuery( "#artisan_ai_tags_error" ).html(xhr.responseText);
			button.width( button.width() ).text('Select Classifier');
	    });
});

jQuery( ".artisan_ai_tags_delete_classifer" ).on("click", function( event ) {
	event.preventDefault();
	button = jQuery( this );
    button.width( button.width() ).text('...');
    let classifier_id = button.attr("value");
    const data = {
    	"action" : "deleteClassifier",
    	"classifier_id" : classifier_id
	};

	jQuery.post( Artisan_Auto_Tag_Ajax_Settings.ajaxurl, data)
		.done( function(response ) { jQuery("#artisan_ai_tags_classifier_model_" + classifier_id ).fadeOut(); })
		.fail(function(xhr, status, error) {
			jQuery( "#artisan_ai_tags_error" ).html(xhr.responseText);
			button.width( button.width() ).text('Delete Classifier');
	    });
});


let artisanAiGenerateDatatable = function artisanAiGenerateDatatable(div, data) {
	let add_filters = function () {
        const column = this.api().column(1)
        //Create a select dropdown to use as a filter
        let select = jQuery('<select></select>')
        	.appendTo(jQuery(column.header()).empty() )
        	//This function will filter the table whenever the user selects a different taxonomy
        	.on( 'change', function () {
            	    const val = jQuery.fn.dataTable.util.escapeRegex( jQuery(this).val() );
				    column.search( val ? '^'+val+'$' : '', true, false ).draw();
            } );
       	//Add each taxonomy name to the filter
        column.data().unique().sort().each( function ( d, j ) {
            select.append( '<option value="'+d+'">'+d+'</option>' )
        });
        //Filter for the initial element
	    const val = jQuery.fn.dataTable.util.escapeRegex(
	        jQuery(select).val()
	    );

	    column.search( val ? '^'+val+'$' : '', true, false ).draw();
    }

    function format ( data ) {
	    // `d` is the original data object for the row
	    return '<table cellpadding="5" cellspacing="0" border="0" style="display:inline; padding-left:50px;">'+
	        '<tr>'+
	            '<td>False Negatives:</td>'+
	            '<td>'+data.false_negatives+'</td>'+
	        '</tr>'+
	        '<tr>'+
	            '<td>False Positives:</td>'+
	            '<td>'+data.false_positives+'</td>'+
	        '</tr>'+
	        '<tr>'+
	            '<td>True Negatives:</td>'+
	            '<td>'+data.true_negatives+'</td>'+
	        '</tr>'+
	        '<tr>'+
	            '<td>True Positives:</td>'+
	            '<td>'+data.true_positives+'</td>'+
	        '</tr>'+
	    '</table>' +
	    '<table cellpadding="5" cellspacing="0" border="0" style="display:inline; padding-left:25px;">'+
	        '<tr>'+
	            '<td>False Negative Rate:</td>'+
	            '<td>'+data.false_negative_rate+'</td>'+
	        '</tr>'+
	        '<tr>'+
	            '<td>False Positive Rate:</td>'+
	            '<td>'+data.false_positive_rate+'</td>'+
	        '</tr>'+
	        '<tr>'+
	            '<td>True Negative Rate:</td>'+
	            '<td>'+data.true_negative_rate+'</td>'+
	        '</tr>'+
	        '<tr>'+
	            '<td>True Positive Rate:</td>'+
	            '<td>'+data.true_positive_rate+'</td>'+
	        '</tr>'+
	    '</table>';
	}

	let table = div.DataTable( {
		"data": data,
		"columns": [
		    {
                "className":      'details-control',
                "orderable":      false,
                "data":           null,
                "defaultContent": ''
            },
	        { "title" : 'Taxonomy Name', data: 'taxonomy_name' },
	        { "title" : 'Term Name', data: 'term_name' },
	        { "title" : 'Accuracy', data: 'accuracy' },
	       	{ "title" : 'Positive Predictive Value', data: 'positive_predicitive_value' },
	       	{ "title" : 'Negative Predictive Value', data: 'negative_predictive_value' },
	        { "title" : 'Matthews correlation coefficient', data: 'mcc' },
	    ],
	    initComplete: add_filters
	});
    // Add event listener for opening and closing details
    div.on('click', 'td.details-control', function () {
        const tr = jQuery(this).closest('tr');
        const row = table.row( tr );
        if ( row.child.isShown() ) {
            // This row is already open - close it
            row.child.hide();
            tr.removeClass('shown');
            jQuery(this).removeClass("is_shown");
        } else {
            row.child( format(row.data()) ).show();
            tr.addClass('shown');
            jQuery(this).addClass("is_shown");
        }
    } );
}

let getTermModelData = async function(classifier_id) {
	return new Promise(function(resolve, reject) {
		const data = {
	    	"action" : "getTermModelData",
	    	"classifier_id" : classifier_id
		};

		jQuery.post( Artisan_Auto_Tag_Ajax_Settings.ajaxurl, data)
			.done( function(response ) {
				console.log(response)
				resolve(response.data);
		    })
			.fail(function(xhr, status, error) {
				jQuery( "#artisan_ai_tags_error" ).html(xhr.responseText);
				reject(error);
		    });
	});
};

jQuery( ".artisan_ai_tags_get_term_data" ).on("click", async function( event ) {
	event.preventDefault();
	const button = jQuery( this );
    button.width( button.width() ).text('...');
    const classifier_id = button.attr("value");
    try {
    	const data = await getTermModelData(classifier_id);
		button.css("display", "none");
		const datatable_display_div = button.parent().find(".data_display");
		artisanAiGenerateDatatable(datatable_display_div, data);
    } catch(err) {
    	button.width( button.width() ).text('Get Classifier Data');
    }
});

let loadCurrentClassifierDatatable = async function() {
    const current_classifier_table = jQuery('#current_classifier_table');
    const classifier_id = current_classifier_table.attr("classifier_id");
    const data = await getTermModelData(classifier_id);
    artisanAiGenerateDatatable(current_classifier_table, data);
}
jQuery(document).ready(function() { loadCurrentClassifierDatatable(); });
