var url = "/api/mercadolivre/orders";
const limit = 30;

var searchingFlag = false;
const searchButton = document.getElementById('searchButton');
const searchInput = document.getElementById('searchInput');
searchButton.addEventListener(
	'click', 
	function (event) {
		event.preventDefault();
		searchForOrders(0, searchInput.value)
	}
);

window.onload = function(){searchForOrders(0);}

function searchForOrders(offset, search = null){

	if (offset == 0) { clearContent(); }

	params = {'offset': offset};

	if (search) {params.q = search;}

	urlFinal = url + "?";

	Object.keys(params).forEach(
		function (key){
			urlFinal += key + "=" + params[key] + "&"}
	)

	fetch(urlFinal)
		.then((response) => response.json())
		.then(
			function (response){
				addContent(response);
				if (offset < limit) {
					searchForOrders(++offset, search);
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

function clearContent() {
	let content = document.getElementById('content');
	content.innerHTML = '';
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

