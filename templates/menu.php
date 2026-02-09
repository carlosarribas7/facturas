<nav class="bg-white shadow-md mb-6">
    <div class="container mx-auto px-4">
        <div class="flex justify-between items-center py-4">
            <a href="index.php" class="text-2xl font-bold text-gray-800">Gestión de Facturas</a>
            <div class="hidden md:flex items-center space-x-4">
                <a href="facturas.php" class="text-gray-600 hover:text-blue-600">Facturas</a>
                <a href="proveedores.php" class="text-gray-600 hover:text-blue-600">Proveedores</a>
                <a href="pagos.php" class="text-gray-600 hover:text-blue-600">Pagos</a>
                <a href="montepio.php" class="text-gray-600 hover:text-blue-600">Montepio</a>
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" class="text-gray-600 hover:text-blue-600 focus:outline-none">
                        Auxiliares <i class="fas fa-caret-down ml-1"></i>
                    </button>
                    <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg z-10">
                        <a href="iva.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">IVA</a>
                        <a href="retenciones.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Retenciones</a>
                        <a href="provincias.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Provincias</a>
                        <a href="tipo_gasto.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Tipos de Gasto</a>
                        <a href="tipometodos.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Tipos de Método</a>
                    </div>
                </div>
            </div>
            <div class="md:hidden">
                <button id="mobile-menu-button" class="text-gray-600 hover:text-blue-600 focus:outline-none">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
        <div id="mobile-menu" class="hidden md:hidden">
            <a href="facturas.php" class="block py-2 px-4 text-sm text-gray-700 hover:bg-gray-100">Facturas</a>
            <a href="proveedores.php" class="block py-2 px-4 text-sm text-gray-700 hover:bg-gray-100">Proveedores</a>
            <a href="pagos.php" class="block py-2 px-4 text-sm text-gray-700 hover:bg-gray-100">Pagos</a>
            <a href="montepio.php" class="block py-2 px-4 text-sm text-gray-700 hover:bg-gray-100">Montepio</a>
            <h3 class="px-4 py-2 text-sm font-semibold text-gray-500">Auxiliares</h3>
            <a href="iva.php" class="block py-2 px-4 text-sm text-gray-700 hover:bg-gray-100">IVA</a>
            <a href="retenciones.php" class="block py-2 px-4 text-sm text-gray-700 hover:bg-gray-100">Retenciones</a>
            <a href="provincias.php" class="block py-2 px-4 text-sm text-gray-700 hover:bg-gray-100">Provincias</a>
            <a href="tipo_gasto.php" class="block py-2 px-4 text-sm text-gray-700 hover:bg-gray-100">Tipos de Gasto</a>
            <a href="tipometodos.php" class="block py-2 px-4 text-sm text-gray-700 hover:bg-gray-100">Tipos de Método</a>
        </div>
    </div>
</nav>

<script>
    document.getElementById('mobile-menu-button').addEventListener('click', function() {
        var menu = document.getElementById('mobile-menu');
        menu.classList.toggle('hidden');
    });
</script>
