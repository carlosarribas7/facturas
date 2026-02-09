<?php
$title = 'Gestión de Pagos';
require_once 'bd/db.php';

// Cargar lista de tipos de método para el select
$tiposMetodo = $pdo->query("SELECT id, tipoMetodo FROM tipometodos ORDER BY tipoMetodo ASC")->fetchAll(PDO::FETCH_ASSOC);

// Alta / Edición
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        !isset($_POST['nombreMetodoPago']) || trim($_POST['nombreMetodoPago']) === '' ||
        !isset($_POST['tipoMetodo_id']) || trim($_POST['tipoMetodo_id']) === ''
    ) {
        header('Location: ?');
        exit;
    }

    $nombre = trim($_POST['nombreMetodoPago']);
    $tipoMetodo_id = trim($_POST['tipoMetodo_id']);
    $numero = trim($_POST['NumeroTarjetaCuentaCorriente'] ?? '');

    if (isset($_POST['id']) && $_POST['id'] !== '') {
        $stmt = $pdo->prepare("UPDATE pagos SET nombreMetodoPago=?, tipoMetodo_id=?, NumeroTarjetaCuentaCorriente=? WHERE id=?");
        $stmt->execute([$nombre, $tipoMetodo_id, $numero, $_POST['id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO pagos (nombreMetodoPago, tipoMetodo_id, NumeroTarjetaCuentaCorriente) VALUES (?, ?, ?)");
        $stmt->execute([$nombre, $tipoMetodo_id, $numero]);
    }

    header('Location: ?');
    exit;
}

// Borrado
if (isset($_GET['eliminar'])) {
    // Obtener el nombre del método de pago antes de eliminarlo
    $stmtPago = $pdo->prepare("SELECT nombreMetodoPago FROM pagos WHERE id=?");
    $stmtPago->execute([$_GET['eliminar']]);
    $pagoData = $stmtPago->fetch(PDO::FETCH_ASSOC);
    $nombrePago = $pagoData ? $pagoData['nombreMetodoPago'] : '';
    
    try {
        $stmt = $pdo->prepare("DELETE FROM pagos WHERE id=?");
        $stmt->execute([$_GET['eliminar']]);
        $_SESSION['mensaje_pago'] = 'Pago eliminado correctamente';
        $_SESSION['tipo_mensaje_pago'] = 'success';
    } catch (PDOException $e) {
        // Capturar error de integridad referencial (foreign key constraint)
        // SQLSTATE[23000] es el código para violación de restricción de integridad
        $errorCode = $e->getCode();
        $errorMessage = $e->getMessage();
        
        if ($errorCode == '23000' || strpos($errorMessage, '1451') !== false || strpos($errorMessage, 'foreign key constraint') !== false) {
            $_SESSION['mensaje_pago'] = 'No se puede eliminar el método de pago "' . htmlspecialchars($nombrePago) . '" porque está siendo utilizado en alguna factura.';
            $_SESSION['tipo_mensaje_pago'] = 'error';
        } else {
            $_SESSION['mensaje_pago'] = 'Error al eliminar el método de pago: ' . htmlspecialchars($errorMessage);
            $_SESSION['tipo_mensaje_pago'] = 'error';
        }
    }
    header('Location: ?');
    exit;
}

// Obtener lista
$registros = $pdo->query("
    SELECT p.*, t.tipoMetodo 
    FROM pagos p
    LEFT JOIN tipometodos t ON p.tipoMetodo_id = t.id
    ORDER BY p.id ASC
")->fetchAll(PDO::FETCH_ASSOC);

?>
<?php require_once 'templates/header.php'; ?>
<body class="bg-gray-100">
<?php require_once 'templates/menu.php'; ?>
<div class="p-7" x-data='pagoCrud(<?= json_encode($tiposMetodo) ?>)'>

    <?php if (!empty($_SESSION['mensaje_pago'])): ?>
        <div id="mensaje-pago" class="mb-4 p-4 rounded <?= $_SESSION['tipo_mensaje_pago'] === 'error' ? 'bg-red-100 border border-red-300 text-red-800' : 'bg-green-100 border border-green-300 text-green-800' ?>">
            <?= htmlspecialchars($_SESSION['mensaje_pago']) ?>
        </div>
        <?php 
        unset($_SESSION['mensaje_pago']);
        unset($_SESSION['tipo_mensaje_pago']);
        ?>
        <script>
            setTimeout(function() {
                const mensaje = document.getElementById('mensaje-pago');
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

    <div class="flex justify-between items-center mb-6">
        <div class="flex items-center space-x-3">
            <h1 class="text-3xl font-bold text-gray-800">Gestión de Pagos</h1>
            <a href="index.php" class="bg-gray-500 text-white font-semibold px-4 py-2 rounded hover:bg-gray-600 flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Volver a menú
            </a>
        </div>

        <button 
            @click="openModal()" 
            class="bg-green-600 text-white font-semibold px-4 py-2 rounded hover:bg-green-700">
            Añadir pago
        </button>
    </div>

    <div class="bg-white shadow rounded overflow-x-auto">
        <table class="min-w-full border">
            <thead class="bg-gray-200">
                <tr>
                    <th class="px-3 py-2 border">ID</th>
                    <th class="px-3 py-2 border">Método</th>
                    <th class="px-3 py-2 border">Tipo Método</th>
                    <th class="px-3 py-2 border">Tarjeta / Cuenta</th>
                    <th class="px-3 py-2 border">Acciones</th>
                </tr>
            </thead>

            <tbody>
            <?php foreach ($registros as $row): ?>
                <tr>
                    <td class="border px-3 py-2"><?= $row['id'] ?></td>
                    <td class="border px-3 py-2"><?= htmlspecialchars($row['nombreMetodoPago']) ?></td>
                    <td class="border px-3 py-2"><?= htmlspecialchars($row['tipoMetodo'] ?? '—') ?></td>
                    <td class="border px-3 py-2"><?= htmlspecialchars($row['NumeroTarjetaCuentaCorriente'] ?? '') ?></td>

                    <td class="border px-3 py-2">
                        <button 
                            @click="openModal(
                                '<?= $row['id'] ?>',
                                '<?= htmlspecialchars($row['nombreMetodoPago'], ENT_QUOTES, 'UTF-8') ?>',
                                '<?= $row['tipoMetodo_id'] ?>',
                                '<?= htmlspecialchars($row['NumeroTarjetaCuentaCorriente'], ENT_QUOTES, 'UTF-8') ?>'
                            )"
                            class="bg-blue-500 text-white px-2 py-1 rounded hover:bg-blue-700">
                            Editar
                        </button>

                        <a href="?eliminar=<?= $row['id'] ?>" 
                           class="bg-red-500 text-white px-2 py-1 rounded ml-2 hover:bg-red-700"
                           onclick="return confirm('¿Seguro que deseas eliminar el pago?')">
                           Eliminar
                        </a>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <!-- Modal -->
    <div 
        x-show="modal" 
        x-transition 
        class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-40"
        style="display: none;"
    >
        <div 
            class="bg-white rounded-lg p-7 shadow-lg w-full max-w-sm"
            @click.away="closeModal()">

            <h2 class="text-xl font-semibold mb-4" x-text="formTitle"></h2>

            <form method="POST" @submit.prevent="submitForm($event)">
                <input type="hidden" name="id" :value="editId">

                <label class="block mb-3">
                    Nombre Método:
                    <input name="nombreMetodoPago" type="text" x-model="editNombre" class="border px-2 py-1 w-full rounded"/>
                </label>

                <label class="block mb-3">
                    Tipo Método:
                    <select name="tipoMetodo_id" x-model="editTipoMetodo" class="border px-2 py-1 w-full rounded">
                        <option value="">-- Seleccionar --</option>
                        <template x-for="tm in tiposMetodo" :key="tm.id">
                            <option :value="tm.id" x-text="tm.tipoMetodo"></option>
                        </template>
                    </select>
                </label>

                <label class="block mb-3">
                    Tarjeta / Cuenta:
                    <input name="NumeroTarjetaCuentaCorriente" type="text" x-model="editNumero" class="border px-2 py-1 w-full rounded"/>
                </label>

                <div class="flex justify-end">
                    <button type="button" 
                            @click="closeModal()" 
                            class="mr-3 px-3 py-1 bg-gray-400 text-white rounded hover:bg-gray-500">
                        Cancelar
                    </button>

                    <button class="bg-green-600 px-3 py-1 text-white rounded hover:bg-green-700" type="submit">
                        Guardar
                    </button>
                </div>
            </form>

        </div>
    </div>

    <script>
        function pagoCrud(tiposMetodo) {
            return {
                modal: false,
                editId: '',
                editNombre: '',
                editTipoMetodo: '',
                editNumero: '',
                tiposMetodo: tiposMetodo,
                formTitle: '',

                openModal(id = '', nombre = '', tipoMetodo = '', numero = '') {
                    this.modal = true;
                    this.editId = id;
                    this.editNombre = nombre;
                    this.editTipoMetodo = tipoMetodo;
                    this.editNumero = numero;

                    this.formTitle = id ? 'Editar Pago' : 'Añadir Pago';
                },

                closeModal() {
                    this.modal = false;
                    this.editId = '';
                    this.editNombre = '';
                    this.editTipoMetodo = '';
                    this.editNumero = '';
                    this.formTitle = '';
                },

                submitForm(event) {
                    if (this.editNombre.trim() === '' || this.editTipoMetodo === '') {
                        alert('Completa los campos obligatorios');
                        return;
                    }
                    event.target.submit();
                }
            }
        }
    </script>

<?php require_once 'templates/footer.php'; ?>
