
let updateURL = "?process=formchanges";

document.body.addEventListener("click", event => {
	let field = event.target;

    if (field.classList.contains("form-input")) {
    	field.onchange = function(){
    		console.log("UPDATE to ", field.value);
    		let formData = new FormData();
				formData.append('bookingID', '12345');
				formData.append('fieldName', field.name);
				formData.append('fieldValue', field.value);
				fetch(updateURL, {
			        method: "POST",
			        body: formData
			  	})
				.then((response) => {
					return response.text();
				})
				.then((text) => {
					console.log(text);
					field.classList.add('updated'); 
					setTimeout(() => {  field.classList.remove('updated'); }, 5000);
				})
				.catch((error) => {
					alert('No Internet, cannot update!');
				})
    	}
    }

  });

