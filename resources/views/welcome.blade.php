<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">

    <title>Pedidos</title>

</head>

<body>
    <div class="container-fluid">
        <!-- <div id='search' class='d-flex'>
            <form class="col-3 row">
                <input type="search" class="form-control form-control-dark" placeholder="Ex.: Alex Medeiros" aria-label="Search" id='searchInput'>
                <button id='searchButton'>Pesquisar</button>
            </form>
        </div> -->
        <table class='table table-bordered table-striped table-hover caption-top'>
            <caption>Pedidos</caption>
            <thead class='table-dark'>
                <tr>
                    <th>N° Pedido</th>
                    <th>Nota Fiscal</th>
                    <th>Comprador</th>
                    <!-- <th>Descrição</th> -->
                    <th>Pagamentos</th>
                    <!-- <th>Total Pagamento</th> -->
                    <th>Taxas</th>
                    <!-- <th>Total Taxas</th> -->
                    <th class='text-end'>Data do Pagto</th>
                </tr>
            </thead>
            <tbody id='content'>
            </tbody>
        </table>
        <div class="text-center" id='loader'>
            <div class="spinner-grow" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    </div>

    <script src={{ URL::asset("js/main.js") }}></script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous"></script>
</body>

</html>