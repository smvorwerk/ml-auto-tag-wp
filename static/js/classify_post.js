function artisan_ai_tags_createTermList(terms, taxonomy_name) {
	/*
		[
			{name: termName, probabilities: Object(0:false probability, 1:true probability), checked:  is term checked? }
			{name: "Advocacy", probabilities: {0: .03, 1: 97%}, checked: true}
			{name: "Legal", ...}
			...
		]
	*/
	let term_list = document.createElement("div")
	term_list.classList.add("artisan_ai_tags_term_list");
	terms.forEach(function(term){
		let term_item = document.createElement("div")
		term_item.classList.add("artisan_ai_tags_term_item");
		//In each subdiv, display checkbox with value of term name and id of term name.
		let checkbox = document.createElement("input");
		let term_name = term["name"]
		checkbox.setAttribute("type", "checkbox");
		checkbox.setAttribute("id", term_name);
		checkbox.setAttribute("name", taxonomy_name + "||" + term_name);
		checkbox.setAttribute("value", term_name);
		//If the term is already selected, display it as checked
		checkbox.checked = term["checked"];
		let label = document.createElement("label");
		label.setAttribute("for", term_name);
		//Display name of term and predicted probability of match
		label.innerHTML = term_name + ": <strong>" + ((1 - term["probabilities"]["0"]) * 100).toFixed(2) + "%</strong>";
		term_item.appendChild(checkbox);
		term_item.appendChild(label);
		if(term["probabilities"]["0"] > .97)
			term_item.style.display = "none";
		term_list.appendChild(term_item);
	});
	return term_list;
}

function artisan_ai_tags_sortByProbability(term1, term2) {
	if (term1["probabilities"]["0"] > term2["probabilities"]["0"]) { return 1}
	if (term1["probabilities"]["0"] < term2["probabilities"]["0"]) { return -1}
	return 0
}

jQuery( "#classify_post" ).on("click", function( event ) {
	event.preventDefault(); // Prevent jumping to the top
	let $button = jQuery( this ); // UI feedback that request is being processed
	$button.width( $button.width() ).text('...');
	let data = { "action" : "classifyPost" };
	data["post_id"] = document.getElementById("artisan-ai-tags-meta-box").getAttribute("rel");
	jQuery.post( Artisan_Auto_Tag_Ajax_Settings.ajaxurl, data)
		.done( function(response ) {
			console.log(response)
			/* Structure of return data:
                    [
                        success : true / false
                        data :
                            {
                                [Taxonomy Name : Term Data]
                                "category" :
                                    [
                                        [Term Data]
                                    ]
                                ...
                            }
                    ]
			*/
			if (response.success) {
				let container_div = document.getElementById("artisan-ai-tags-meta-box-content");
				container_div.innerHTML = "";
				//For each taxonomy use reverse because we want categories to come first--will have to change when things become dynamic
				Object.entries(response.data).reverse().forEach(function(taxonomy) {
					taxonomy[1].sort(artisan_ai_tags_sortByProbability);
					let taxonomy_div = document.createElement("div");
					taxonomy_div.classList.add("artisan_ai_tags_taxonomy_div");
					taxonomy_div.setAttribute("id", taxonomy[0]);
					let taxonomy_name = document.createElement("h3");
					taxonomy_name.innerText = taxonomy[0].charAt(0).toUpperCase() + taxonomy[0].slice(1);
					taxonomy_div.appendChild(taxonomy_name);
					let term_list = artisan_ai_tags_createTermList(taxonomy[1], taxonomy[0]);
					taxonomy_div.appendChild(term_list);
					container_div.appendChild(taxonomy_div);
				});
			}
			$button.css("display", "none");
	})
	.fail(function(xhr, status, error) {
		let container_div = document.getElementById("artisan-ai-tags-meta-box-content");
		container_div.innerHTML = xhr.responseText;
		$button.width( $button.width() ).text('Classify Post');
    });
});
