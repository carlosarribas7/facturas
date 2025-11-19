<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Facturas</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .card {
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-gray-800 mb-2">Sistema de Gestión de Facturas</h1>
            <p class="text-gray-600">Selecciona una opción para gestionar</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-3 gap-6">
            <!-- Facturas -->
            <a href="facturas.php" class="card bg-white rounded-lg shadow-md p-6 text-center no-underline block">
                <div class="mb-4">
                    <i class="fas fa-file-invoice text-5xl text-blue-600"></i>
                </div>
                <h2 class="text-xl font-semibold text-gray-800 mb-2">Facturas</h2>
                <p class="text-gray-600 text-sm">Gestión de facturas</p>
            </a>

            <!-- Proveedores -->
            <a href="proveedores.php" class="card bg-white rounded-lg shadow-md p-6 text-center no-underline block">
                <div class="mb-4">
                    <i class="fas fa-building text-5xl text-green-600"></i>
                </div>
                <h2 class="text-xl font-semibold text-gray-800 mb-2">Proveedores</h2>
                <p class="text-gray-600 text-sm">Gestión de proveedores</p>
            </a>

            <!-- IVA -->
            <a href="iva.php" class="card bg-white rounded-lg shadow-md p-6 text-center no-underline block">
                <div class="mb-4">
                    <i class="fas fa-percent text-5xl text-purple-600"></i>
                </div>
                <h2 class="text-xl font-semibold text-gray-800 mb-2">IVA</h2>
                <p class="text-gray-600 text-sm">Tipos de IVA</p>
            </a>

            <!-- Retenciones -->
            <a href="retenciones.php" class="card bg-white rounded-lg shadow-md p-6 text-center no-underline block">
                <div class="mb-4">
                    <i class="fas fa-hand-holding-usd text-5xl text-red-600"></i>
                </div>
                <h2 class="text-xl font-semibold text-gray-800 mb-2">Retenciones</h2>
                <p class="text-gray-600 text-sm">Tipos de retenciones</p>
            </a>

            <!-- Provincias -->
            <a href="provincias.php" class="card bg-white rounded-lg shadow-md p-6 text-center no-underline block">
                <div class="mb-4">
                    <i class="fas fa-map-marker-alt text-5xl text-orange-600"></i>
                </div>
                <h2 class="text-xl font-semibold text-gray-800 mb-2">Provincias</h2>
                <p class="text-gray-600 text-sm">Gestión de provincias</p>
            </a>

            <!-- Tipo de Gasto -->
            <a href="tipo_gasto.php" class="card bg-white rounded-lg shadow-md p-6 text-center no-underline block">
                <div class="mb-4">
                    <i class="fas fa-tags text-5xl text-indigo-600"></i>
                </div>
                <h2 class="text-xl font-semibold text-gray-800 mb-2">Tipo de Gasto</h2>
                <p class="text-gray-600 text-sm">Clasificación de gastos</p>
            </a>

            <!-- Tipos de Método -->
            <a href="tipometodos.php" class="card bg-white rounded-lg shadow-md p-6 text-center no-underline block">
                <div class="mb-4">
                    <i class="fas fa-credit-card text-5xl text-teal-600"></i>
                </div>
                <h2 class="text-xl font-semibold text-gray-800 mb-2">Tipos de Método</h2>
                <p class="text-gray-600 text-sm">Métodos de pago</p>
            </a>

            <!-- Pagos -->
            <a href="pagos.php" class="card bg-white rounded-lg shadow-md p-6 text-center no-underline block">
                <div class="mb-4">
                    <i class="fas fa-money-bill-wave text-5xl text-yellow-600"></i>
                </div>
                <h2 class="text-xl font-semibold text-gray-800 mb-2">Formas de Pago</h2>
                <p class="text-gray-600 text-sm">Gestión de de formas de pago</p>
            </a>

            <!-- Montepio -->
            <a href="montepio.php" class="card bg-white rounded-lg shadow-md p-6 text-center no-underline block">
                <div class="mb-4">
                    <i class="fas fa-file-invoice text-5xl text-yellow-300"></i>
                </div>
                <h2 class="text-xl font-semibold text-gray-800 mb-2">Montepio</h2>
                <p class="text-gray-600 text-sm">Gestión de Facturas de Montepio</p>
            </a>
        </div>
    </div>
</body>
</html>

