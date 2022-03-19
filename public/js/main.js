var url = "/api/mercadolivre/orders";
const limit = 300;

/* var searchingFlag = false;
const searchButton = document.getElementById('searchButton');
const searchInput = document.getElementById('searchInput');
searchButton.addEventListener(
	'click', 
	function (event) {
		event.preventDefault();
		searchForOrders(0, searchInput.value)
	}
); */

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
							'buyer',
							/* 'reason', */
							'payment_info', 
							'total_paid_amount',
							'sales_fee',
							'total_fee_amount',
							'payment_date',
						]

	orderTable.forEach((index) =>{
		cellElement = document.createElement('td');
		if(index == "buyer") {
			let buyer = order['buyer'];
			let innerTable = "<table class='table table-striped'>";

			innerTable += "<tr>";
			innerTable += "<td>Nome</td>";
			innerTable += "<td>" + buyer['full_name'] + "</td>";
			innerTable += "</tr>";

			if (buyer['email']) {
				innerTable += "<tr>";
				innerTable += "<td>Email</td>";
				innerTable += "<td>" + buyer['email'] + "</td>";
				innerTable += "</tr>";
			}

			if (buyer['number']) {
				innerTable += "<tr>";
				innerTable += "<td>Telefone</td>";
				innerTable += "<td>" + buyer['area_code'] + " " + buyer['extension'] + " " + buyer['number'] + " " + "</td>";
				innerTable += "</tr>";
			}

			if (buyer['identification']['type']) {
				innerTable += "<tr>";
				innerTable += "<td>" + buyer['identification']['type'] + "</td>";
				innerTable += "<td>" + buyer['identification']['number'] + "</td>";
				innerTable += "</tr>";
			}

			innerTable += "</table>";
			cellElement.innerHTML = innerTable;
		}				
		/* else if (index == "reason") {
			cellElement.innerHTML = order[index];
			cellElement.classList.add('overflow-auto');
		} */
		else if(index == "payment_info") {
			let innerTable = "<table class='table table-striped'>";
			order[index].forEach(payment => {
				innerTable += "<tr>";
				innerTable += "<td>" + payment['method'] + "</td>";
				innerTable += "<td>" + payment['amount'].toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }); + "</td>";
				innerTable += "</tr>";
			})
			innerTable += "</table>";
			cellElement.innerHTML = innerTable;
		}				 
		else if (index == "total_paid_amount") {
			let amount = 0;
			order['payment_info'].forEach(payment => {amount += payment['amount']});
			cellElement.innerHTML = amount.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
		}
		else if(index == "sales_fee") {
			let innerTable = "<table class='table table-striped'>";
			order[index].forEach(fee => {
				innerTable += "<tr>";
				innerTable += "<td>" + fee['description'] + "</td>";
				innerTable += "<td>" + fee['amount'].toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }); + "</td>";
				innerTable += "</tr>";
			})
			innerTable += "</table>";
			cellElement.innerHTML = innerTable;
		}
		else if (index == "total_fee_amount") {
			let amount = 0;
			order['sales_fee'].forEach(fee => {amount += fee['amount']});
			cellElement.innerHTML = amount.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });;
		}			
		else if (index == "payment_date") {
			date = new Date(order[index]);
			cellElement.innerHTML = date.toLocaleString('pt-BR');
			cellElement.classList.add('text-end');
		}
		else {
			cellElement.innerHTML = order[index];
		}
		
		rowElement.appendChild(cellElement);
	});

	return rowElement;
	
	
}

