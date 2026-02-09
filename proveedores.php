<?php
$title = 'Gestión de Proveedores';
require_once 'bd/db.php';

// ---------------- PAGINACIÓN ----------------
$porPagina = 10;
$pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;

// ---------------- BUSCADOR ----------------
$buscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$paramBuscarSQL = $buscar !== '' ? "%$buscar%" : '%';

// ---------------- ORDENAMIENTO ----------------
$ordenar = isset($_GET['ordenar']) && in_array($_GET['ordenar'], ['nombre', 'tipoGasto_id']) ? $_GET['ordenar'] : 'id';
$direccion = isset($_GET['direccion']) && $_GET['direccion'] === 'DESC' ? 'DESC' : 'ASC';

// Contar registros filtrados
$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM proveedores WHERE nombre LIKE :buscar");
$stmtCount->bindValue(':buscar', $paramBuscarSQL, PDO::PARAM_STR);
$stmtCount->execute();
$totalRegistros = $stmtCount->fetchColumn();

// Cálculo páginas
$totalPaginas = max(1, ceil($totalRegistros / $porPagina));
$offset = ($pagina - 1) * $porPagina;

// ---------------- LISTAS PARA SELECT ----------------
$listaProvincias = $pdo->query("SELECT id, provincia FROM provincias ORDER BY provincia ASC")->fetchAll(PDO::FETCH_ASSOC);
$listaGastos = $pdo->query("SELECT id, cuenta, ce FROM tipo_gasto ORDER BY cuenta ASC")->fetchAll(PDO::FETCH_ASSOC);

// ---------------- CRUD: ALTA/EDICIÓN ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['nombre']) || trim($_POST['nombre']) === '') {
        header('Location: ?');
        exit;
    }

    $data = [
        'nombre' => trim($_POST['nombre']),
        'tipoGasto_id' => $_POST['tipoGasto_id'] ?: null,
        'nif' => trim($_POST['nif'] ?? ''),
        'cuenta_bancaria' => trim($_POST['cuenta_bancaria'] ?? ''),
        'direccion' => trim($_POST['direccion'] ?? ''),
        'codigo_postal' => trim($_POST['codigo_postal'] ?? ''),
        'poblacion' => trim($_POST['poblacion'] ?? ''),
        'provincia_id' => $_POST['provincia_id'] ?: null,
        'pais' => trim($_POST['pais'] ?? ''),
        'telefono' => trim($_POST['telefono'] ?? ''),
        'movil' => trim($_POST['movil'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'url' => trim($_POST['url'] ?? '')
    ];

    if (isset($_POST['id']) && $_POST['id'] !== '') {

        $sql = "
            UPDATE proveedores SET
                nombre=?, tipoGasto_id=?, nif=?, cuenta_bancaria=?, direccion=?,
                codigo_postal=?, poblacion=?, provincia_id=?, pais=?, telefono=?,
                movil=?, email=?, url=?
            WHERE id=?
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['nombre'], $data['tipoGasto_id'], $data['nif'], $data['cuenta_bancaria'],
            $data['direccion'], $data['codigo_postal'], $data['poblacion'], $data['provincia_id'],
            $data['pais'], $data['telefono'], $data['movil'], $data['email'], $data['url'],
            $_POST['id']
        ]);

    } else {

        $sql = "
            INSERT INTO proveedores 
            (nombre, tipoGasto_id, nif, cuenta_bancaria, direccion, codigo_postal,
             poblacion, provincia_id, pais, telefono, movil, email, url)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['nombre'], $data['tipoGasto_id'], $data['nif'], $data['cuenta_bancaria'],
            $data['direccion'], $data['codigo_postal'], $data['poblacion'], $data['provincia_id'],
            $data['pais'], $data['telefono'], $data['movil'], $data['email'], $data['url']
        ]);
    }

    header('Location: ?');
    exit;
}

// ---------------- ELIMINAR ----------------
if (isset($_GET['eliminar'])) {
    // Obtener el nombre del proveedor antes de eliminarlo
    $stmtProveedor = $pdo->prepare("SELECT nombre FROM proveedores WHERE id=?");
    $stmtProveedor->execute([$_GET['eliminar']]);
    $proveedorData = $stmtProveedor->fetch(PDO::FETCH_ASSOC);
    $nombreProveedor = $proveedorData ? $proveedorData['nombre'] : '';
    
    try {
        $stmt = $pdo->prepare("DELETE FROM proveedores WHERE id=?");
        $stmt->execute([$_GET['eliminar']]);
        $_SESSION['mensaje_proveedor'] = 'Proveedor eliminado correctamente';
        $_SESSION['tipo_mensaje_proveedor'] = 'success';
    } catch (PDOException $e) {
        // Capturar error de integridad referencial (foreign key constraint)
        // SQLSTATE[23000] es el código para violación de restricción de integridad
        $errorCode = $e->getCode();
        $errorMessage = $e->getMessage();
        
        if ($errorCode == '23000' || strpos($errorMessage, '1451') !== false || strpos($errorMessage, 'foreign key constraint') !== false) {
            $_SESSION['mensaje_proveedor'] = 'No se puede eliminar el proveedor "' . htmlspecialchars($nombreProveedor) . '" porque está siendo utilizado en alguna factura.';
            $_SESSION['tipo_mensaje_proveedor'] = 'error';
        } else {
            $_SESSION['mensaje_proveedor'] = 'Error al eliminar el proveedor: ' . htmlspecialchars($errorMessage);
            $_SESSION['tipo_mensaje_proveedor'] = 'error';
        }
    }
    
    // Preservar parámetros de búsqueda y ordenamiento en la redirección
    $params = [];
    if (!empty($buscar)) {
        $params[] = 'buscar=' . urlencode($buscar);
    }
    if ($ordenar === 'nombre') {
        $params[] = 'ordenar=nombre';
        $params[] = 'direccion=' . urlencode($direccion);
    }
    if ($pagina > 1) {
        $params[] = 'pagina=' . $pagina;
    }
    $queryString = !empty($params) ? '?' . implode('&', $params) : '?';
    
    header('Location: ' . $queryString);
    exit;
}

// ---------------- CONSULTA FILTRADA + PAGINADA ----------------
// Validar y construir ORDER BY de forma segura
$campoOrden = match($ordenar) {
    'nombre' => 'p.nombre',
    'tipoGasto_id' => 'tg.cuenta', // Sort by the tipo_gasto.cuenta field
    default => 'p.id'
};
$direccionOrden = ($direccion === 'DESC') ? 'DESC' : 'ASC';

$query = "
    SELECT p.*, 
           pr.provincia AS nombreProvincia,
           tg.cuenta AS nombreCuenta,
           tg.ce AS ceGasto
    FROM proveedores p
    LEFT JOIN provincias pr ON p.provincia_id = pr.id
    LEFT JOIN tipo_gasto tg ON p.tipoGasto_id = tg.id
    WHERE p.nombre LIKE :buscar
    ORDER BY $campoOrden $direccionOrden
    LIMIT :offset, :porpagina
";

$stmt = $pdo->prepare($query);
$stmt->bindValue(':buscar', $paramBuscarSQL, PDO::PARAM_STR);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':porpagina', $porPagina, PDO::PARAM_INT);
$stmt->execute();
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php require_once 'templates/header.php'; ?>
<body class="bg-gray-100">
<?php require_once 'templates/menu.php'; ?>
<div class="p-7"
      x-data="proveedoresCrud(<?= htmlspecialchars(json_encode($listaProvincias), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($listaGastos), ENT_QUOTES, 'UTF-8') ?>)">

    <?php if (!empty($_SESSION['mensaje_proveedor'])): ?>
        <div id="mensaje-proveedor" class="mb-4 p-4 rounded <?= $_SESSION['tipo_mensaje_proveedor'] === 'error' ? 'bg-red-100 border border-red-300 text-red-800' : 'bg-green-100 border border-green-300 text-green-800' ?>">
            <?= htmlspecialchars($_SESSION['mensaje_proveedor']) ?>
        </div>
        <?php 
        unset($_SESSION['mensaje_proveedor']);
        unset($_SESSION['tipo_mensaje_proveedor']);
        ?>
        <script>
            setTimeout(function() {
                const mensaje = document.getElementById('mensaje-proveedor');
                if (mensaje) {
                    mensaje.style.transition = 'opacity 0.5s';
                    mensaje.style.opacity = '0';
                    setTimeout(function() {
                        mensaje.remove();
                    }, 500);
                }
            }, 4000);
        </script>
    <?php endif; ?>

<!-- HEADER + BUSCADOR -->
<div class="flex justify-between items-center mb-6">

    <div class="flex items-center space-x-3">
        <h1 class="text-3xl font-bold text-gray-800">Gestión de Proveedores</h1>
        <a href="index.php" class="bg-gray-500 text-white font-semibold px-4 py-2 rounded hover:bg-gray-600 flex items-center">
            <i class="fas fa-arrow-left mr-2"></i> Volver a menú
        </a>
    </div>

    <div class="flex items-center space-x-3">
        <!-- BUSCADOR -->
        <div class="relative">
            <input 
                type="text" 
                id="buscador"
                placeholder="Buscar proveedor..." 
                value="<?= h($buscar) ?>"
                class="border px-3 py-1 rounded w-64"
                autocomplete="off"
            />
            <div id="loading-indicator" class="absolute right-2 top-1/2 transform -translate-y-1/2 hidden">
                <svg class="animate-spin h-4 w-4 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
        </div>

        <!-- BOTÓN NUEVO -->
        <button 
            id="btnAddProveedor"
            @click="openModal()"
            class="bg-green-600 text-white font-semibold px-4 py-2 rounded hover:bg-green-700">
            Añadir proveedor
        </button>
    </div>

</div>

<!-- TABLA -->
<div class="bg-white shadow rounded overflow-x-auto">
    <table class="min-w-full border">
        <thead class="bg-gray-200">
            <tr>
                <th class="border px-3 py-2">ID</th>
                <th class="border px-3 py-2">
                    <a href="?ordenar=nombre&direccion=<?= ($ordenar === 'nombre' && $direccion === 'ASC') ? 'DESC' : 'ASC' ?>&buscar=<?= urlencode($buscar) ?>&pagina=<?= $pagina ?>" 
                       class="flex items-center justify-center hover:text-blue-600 cursor-pointer no-underline text-gray-800">
                        Nombre
                        <?php if ($ordenar === 'nombre'): ?>
                            <i class="fas fa-arrow-<?= $direccion === 'ASC' ? 'up' : 'down' ?> ml-2"></i>
                        <?php else: ?>
                            <i class="fas fa-sort ml-2 text-gray-400"></i>
                        <?php endif; ?>
                    </a>
                </th>
                <th class="border px-3 py-2">
                    <a href="?ordenar=tipoGasto_id&direccion=<?= ($ordenar === 'tipoGasto_id' && $direccion === 'ASC') ? 'DESC' : 'ASC' ?>&buscar=<?= urlencode($buscar) ?>&pagina=<?= $pagina ?>" 
                       class="flex items-center justify-center hover:text-blue-600 cursor-pointer no-underline text-gray-800">
                        Tipo gasto
                        <?php if ($ordenar === 'tipoGasto_id'): ?>
                            <i class="fas fa-arrow-<?= $direccion === 'ASC' ? 'up' : 'down' ?> ml-2"></i>
                        <?php else: ?>
                            <i class="fas fa-sort ml-2 text-gray-400"></i>
                        <?php endif; ?>
                    </a>
                </th>
                <th class="border px-3 py-2">NIF</th>
                <th class="border px-3 py-2">Provincia</th>
                <th class="border px-3 py-2">Email</th>
                <th class="border px-3 py-2">Teléfono</th>
                <th class="border px-3 py-2">Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($registros as $row): ?>
            <tr>
                <td class="border px-3 py-2"><?= $row['id'] ?></td>
                <td class="border px-3 py-2"><?= h($row['nombre']) ?></td>
                <td class="border px-3 py-2"><?= h($row['nombreCuenta']  ?? '') ?></td>
                <td class="border px-3 py-2"><?= h($row['nif']) ?></td>
                <td class="border px-3 py-2"><?= h($row['nombreProvincia']) ?></td>
                <td class="border px-3 py-2"><?= h($row['email']) ?></td>
                <td class="border px-3 py-2"><?= h($row['telefono']) ?></td>

                <td class="border px-3 py-2">

                    <!-- BOTÓN EDITAR SEGURO -->
                    <button 
                        class="bg-blue-500 text-white px-2 py-1 rounded hover:bg-blue-700"
                        data-row='<?= htmlspecialchars(json_encode([
                            'id' => $row['id'],
                            'nombre' => $row['nombre'] ?? '',
                            'tipoGasto_id' => $row['tipoGasto_id'] ?? '',
                            'nif' => $row['nif'] ?? '',
                            'cuenta_bancaria' => $row['cuenta_bancaria'] ?? '',
                            'direccion' => $row['direccion'] ?? '',
                            'codigo_postal' => $row['codigo_postal'] ?? '',
                            'poblacion' => $row['poblacion'] ?? '',
                            'provincia_id' => $row['provincia_id'] ?? '',
                            'pais' => $row['pais'] ?? '',
                            'telefono' => $row['telefono'] ?? '',
                            'movil' => $row['movil'] ?? '',
                            'email' => $row['email'] ?? '',
                            'url' => $row['url'] ?? ''
                        ]), ENT_QUOTES, 'UTF-8') ?>'
                        onclick="handleEditButtonClick(this)">
                        Editar
                    </button>

                    <a href="?eliminar=<?= $row['id'] ?>"
                       class="bg-red-500 text-white px-2 py-1 rounded ml-2 hover:bg-red-700"
                       onclick="return confirm('¿Eliminar proveedor?')">
                       Eliminar
                    </a>

                </td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
</div>

<!-- PAGINACIÓN -->
<div class="flex justify-center items-center space-x-2 mt-4">
    <?php
    $paramsPaginacion = [];
    if (!empty($buscar)) {
        $paramsPaginacion[] = 'buscar=' . urlencode($buscar);
    }
    if (in_array($ordenar, ['nombre', 'tipoGasto_id'])) {
        $paramsPaginacion[] = 'ordenar=' . urlencode($ordenar);
        $paramsPaginacion[] = 'direccion=' . urlencode($direccion);
    }
    $paramsPaginacion = implode('&', $paramsPaginacion);
    $paramsPaginacion = $paramsPaginacion ? '&' . $paramsPaginacion : '';
    ?>
    <a href="?pagina=1<?= $paramsPaginacion ?>"
        class="px-3 py-1 border rounded bg-gray-200 hover:bg-gray-300">⏮</a>

    <a href="?pagina=<?= max(1, $pagina - 1) . $paramsPaginacion ?>"
        class="px-3 py-1 border rounded bg-gray-200 hover:bg-gray-300">◀</a>

    <span class="px-4 py-1 border rounded bg-white">
        Página <?= $pagina ?> de <?= $totalPaginas ?>
    </span>

    <a href="?pagina=<?= min($totalPaginas, $pagina + 1) . $paramsPaginacion ?>"
        class="px-3 py-1 border rounded bg-gray-200 hover:bg-gray-300">▶</a>

    <a href="?pagina=<?= $totalPaginas . $paramsPaginacion ?>"
        class="px-3 py-1 border rounded bg-gray-200 hover:bg-gray-300">⏭</a>

</div>

<!-- MODAL COMPLETO -->
<div 
    x-show="modal" 
    x-transition 
    class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-40"
    style="display: none;"
>
    <div class="bg-white rounded-lg p-7 shadow-lg w-full max-w-4xl"
         @click.away="closeModal()">

        <h2 class="text-xl font-semibold mb-4" x-text="formTitle"></h2>

        <form method="POST" @submit.prevent="submitForm($event)">

            <input type="hidden" name="id" :value="editId">

            <!-- GRID EN 2 COLUMNAS -->
            <div class="grid grid-cols-2 gap-4">

                <!-- Columna 1 -->
                <div>
                    <label class="block mb-3">
                        Nombre:
                        <input name="nombre" type="text" x-model="form.nombre"
                            class="border px-2 py-1 w-full rounded"/>
                    </label>

                    <label class="block mb-3">
                        Tipo de Gasto:
                        <select name="tipoGasto_id" x-model="form.tipoGasto_id"
                                class="border px-2 py-1 w-full rounded">
                            <option value="">-- Seleccionar --</option>
                            <template x-for="g in listaGastos" :key="g.id">
                                <option :value="g.id" x-text="g.cuenta "></option>
                            </template>
                        </select>
                    </label>

                    <label class="block mb-3">
                        NIF:
                        <input name="nif" type="text" x-model="form.nif"
                            class="border px-2 py-1 w-full rounded"/>
                    </label>

                    <label class="block mb-3">
                        Dirección:
                        <input name="direccion" type="text" x-model="form.direccion"
                            class="border px-2 py-1 w-full rounded"/>
                    </label>

                    <label class="block mb-3">
                        Código Postal:
                        <input name="codigo_postal" type="text" x-model="form.codigo_postal"
                            class="border px-2 py-1 w-full rounded"/>
                    </label>
                </div>

                <!-- Columna 2 -->
                <div>
                    <label class="block mb-3">
                        Población:
                        <input name="poblacion" type="text" x-model="form.poblacion"
                            class="border px-2 py-1 w-full rounded"/>
                    </label>

                    <label class="block mb-3">
                        Provincia:
                        <select name="provincia_id" x-model="form.provincia_id"
                                class="border px-2 py-1 w-full rounded">
                            <option value="">-- Seleccionar --</option>
                            <template x-for="p in listaProvincias" :key="p.id">
                                <option :value="p.id" x-text="p.provincia"></option>
                            </template>
                        </select>
                    </label>

                    <label class="block mb-3">
                        País:
                        <input name="pais" type="text" x-model="form.pais"
                            class="border px-2 py-1 w-full rounded"/>
                    </label>

                    <label class="block mb-3">
                        Teléfono:
                        <input name="telefono" type="text" x-model="form.telefono"
                            class="border px-2 py-1 w-full rounded"/>
                    </label>

                    <label class="block mb-3">
                        Móvil:
                        <input name="movil" type="text" x-model="form.movil"
                            class="border px-2 py-1 w-full rounded"/>
                    </label>

                    <label class="block mb-3">
                        Email:
                        <input name="email" type="text" x-model="form.email"
                            class="border px-2 py-1 w-full rounded"/>
                    </label>

                    <label class="block mb-3">
                        Web:
                        <input name="url" type="text" x-model="form.url"
                            class="border px-2 py-1 w-full rounded"/>
                    </label>
                </div>

            </div>

            <!-- Botones -->
            <div class="flex justify-end mt-4">
                <button type="button"
                        @click="closeModal()"
                        class="mr-3 px-3 py-1 bg-gray-400 text-white rounded hover:bg-gray-500">
                    Cancelar
                </button>

                <button type="submit"
                        class="px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700">
                    Guardar
                </button>
            </div>

        </form>

    </div>
</div>

<!-- ALPINE.JS COMPONENT -->
<script>
// Handle search input with debounce
let searchTimeout;
const searchInput = document.getElementById('buscador');
const loadingIndicator = document.getElementById('loading-indicator');

if (searchInput) {
    searchInput.addEventListener('input', function(e) {
        // Show loading indicator
        loadingIndicator.classList.remove('hidden');
        
        // Clear previous timeout
        clearTimeout(searchTimeout);
        
        // Set new timeout
        searchTimeout = setTimeout(() => {
            const searchTerm = e.target.value.trim();
            updateResults(searchTerm);
        }, 300); // 300ms delay
    });
    
    // Initial focus on the search input
    searchInput.focus();
}

function updateResults(searchTerm) {
    // Get current URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    
    // Update search parameter
    if (searchTerm) {
        urlParams.set('buscar', searchTerm);
    } else {
        urlParams.delete('buscar');
    }
    
    // Preserve pagination and sorting
    if (urlParams.has('pagina') && searchTerm === '') {
        urlParams.set('pagina', '1');
    }
    
    // Update URL without page reload
    const newUrl = window.location.pathname + '?' + urlParams.toString();
    window.history.pushState({}, '', newUrl);
    
    // Reload the page to show results
    window.location.reload();
}

function proveedoresCrud(listaProvincias, listaGastos) {
    return {
        modal: false,
        editId: '',
        formTitle: '',
        listaProvincias,
        listaGastos,

        form: {
            nombre: '',
            tipoGasto_id: '',
            nif: '',
            cuenta_bancaria: '',
            direccion: '',
            codigo_postal: '',
            poblacion: '',
            provincia_id: '',
            pais: '',
            telefono: '',
            movil: '',
            email: '',
            url: ''
        },

        init() {
            window.addEventListener('open-proveedor-modal', (e) => {
                const data = e.detail;
                this.openModal(data?.id ?? '', data);
            });
        },

        openModal(id = '', data = null) {
            this.modal = true;
            this.editId = id;

            if (data) {
                Object.keys(this.form).forEach(k => this.form[k] = data[k] ?? '');
            } else {
                Object.keys(this.form).forEach(k => this.form[k] = '');
            }

            this.formTitle = id ? 'Editar Proveedor' : 'Añadir Proveedor';
        },

        closeModal() {
            this.modal = false;
            this.editId = '';
            Object.keys(this.form).forEach(k => this.form[k] = '');
            this.formTitle = '';
        },

        submitForm(event) {
            if (this.form.nombre.trim() === '') {
                alert('El nombre es obligatorio');
                return;
            }
            event.target.submit();
        }
    }
}
</script>

<!-- FALLBACK JS (si Alpine falla) -->
<script>
function findModalElement() {
    return document.querySelector('[x-show="modal"]');
}

function openModalFallback(data = null) {
    const modal = findModalElement();
    if (!modal) return;

    modal.style.display = 'flex';
    modal.classList.remove('hidden');

    const form = modal.querySelector('form');
    if (!form) return;

    form.reset();
    if (data) {
        Object.keys(data).forEach(k => {
            const input = form.querySelector('[name="'+k+'"]');
            if (input) input.value = data[k] ?? '';
        });
        const hid = form.querySelector('input[name="id"]');
        if (hid) hid.value = data.id ?? '';
    }
}

function handleEditButtonClick(button) {
    try {
        const raw = button.getAttribute('data-row');
        const data = raw ? JSON.parse(raw) : null;

        window.dispatchEvent(new CustomEvent('open-proveedor-modal', { detail: data }));

        setTimeout(() => {
            const modal = findModalElement();
            if (modal.style.display === 'none' || modal.classList.contains('hidden')) {
                openModalFallback(data);
            }
        }, 80);

    } catch (e) {
        console.error('Error parseando data-row:', e);
        alert('Error abriendo modal.');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const btnAdd = document.getElementById('btnAddProveedor');
    btnAdd.addEventListener('click', function() {
        window.dispatchEvent(new CustomEvent('open-proveedor-modal', { detail: null }));
        setTimeout(() => {
            const modal = findModalElement();
            if (modal.style.display === 'none' || modal.classList.contains('hidden')) {
                openModalFallback(null);
            }
        }, 80);
    });
});
</script>

<?php require_once 'templates/footer.php'; ?>
