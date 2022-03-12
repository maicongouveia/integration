var url = new URL("http://127.0.0.1/api/mercadolivre/orders");
const limit = 30;

window.onload = function(){searchForOrders(0);}

function searchForOrders(i, search = null){
	params = {'offset': i};
	if (search) {
		params.q = search; 
	}

	Object.keys(params).forEach(key => url.searchParams.append(key, params[key]))
	console.log(url);
	fetch(url)
		.then((response) => response.json())
		.then(
			function (response){
				addContent(response);
				if (i < limit) {
					searchForOrders(++i);
				}
				else {
					stopLoader();
				}
			}
		)
		.catch(function (error){
			console.log(error);
			stopLoader();
		});
}

function stopLoader() {
	let loader = document.getElementById('loader');
	loader.style.display = 'none';
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
							'reason',
							'total_paid_amount',
							'payment_method', 
							'sales_fee',
							'payment_date',
						]

	orderTable.forEach((index) =>{
		cellElement = document.createElement('td');
		if(index == "sales_fee") {
			cellElement.innerHTML = order[index].length + " taxas";
		}		
		else if (index == "reason") {
			cellElement.innerHTML = order[index];
			cellElement.classList.add('overflow-auto');
		}
		else if (index == "payment_date") {
			date = new Date(order[index]);
			cellElement.innerHTML = date.toLocaleString('pt-BR');
			cellElement.classList.add('text-end');
		}		 
		else if (index == "total_paid_amount") {
			cellElement.innerHTML = order[index].toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
		}
		else {
			cellElement.innerHTML = order[index];
		}
		
		rowElement.appendChild(cellElement);
	});

	return rowElement;
	
	
}