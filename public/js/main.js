const url = "/api/mercadolivre/orders"

window.onload = function(){
	fetch(url)
		.then((response) => response.json())
		.then(
			function (response){
				addContent(response);
			}
		)
		.catch(function (error){
			console.log(error);
		});
}

function addContent(response){
	contentTable = document.getElementById('content');
	response.forEach((order) => {
		contentTable.appendChild(row(order));
	})
}

function row(order){
	const rowElement = document.createElement('tr');
	const orderTable = [
							'order_id',
							'invoice',
							'payer',
							'total_paid_amount',
							'payment_method', 
							'payment_date',
							'sales_fee'
						]
	orderTable.forEach((index) =>{
		cellElement = document.createElement('td');
		cellElement.innerHTML = order[index];
		rowElement.appendChild(cellElement);
	});

	return rowElement;
	
	
}