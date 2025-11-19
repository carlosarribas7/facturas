<?php
if(session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = new PDO('mysql:host=localhost;dbname=facturassalt', 'root', '');
$pdo->exec("SET NAMES utf8mb4");

// Alta/Edición/Borrado con validación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        !isset($_POST['cuenta']) || trim($_POST['cuenta']) === '' ||
        !isset($_POST['ce']) || trim($_POST['ce']) === ''
    ) {
        header('Location: ?');
        exit;
    }
    $cuenta = trim($_POST['cuenta']);
    $ce = trim($_POST['ce']);
    if (isset($_POST['id']) && $_POST['id'] !== '') {
        $stmt = $pdo->prepare("UPDATE tipo_gasto SET cuenta=?, ce=? WHERE id=?");
        $stmt->execute([$cuenta, $ce, $_POST['id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO tipo_gasto (cuenta, ce) VALUES (?, ?)");
        $stmt->execute([$cuenta, $ce]);
    }
    header('Location: ?');
    exit;
}
if (isset($_GET['eliminar'])) {
    // Obtener los datos del tipo de gasto antes de eliminarlo
    $stmtGasto = $pdo->prepare("SELECT cuenta, ce FROM tipo_gasto WHERE id=?");
    $stmtGasto->execute([$_GET['eliminar']]);
    $gastoData = $stmtGasto->fetch(PDO::FETCH_ASSOC);
    $cuentaGasto = $gastoData ? $gastoData['cuenta'] : '';
    $ceGasto = $gastoData ? $gastoData['ce'] : '';
    
    try {
        $stmt = $pdo->prepare("DELETE FROM tipo_gasto WHERE id=?");
        $stmt->execute([$_GET['eliminar']]);
        $_SESSION['mensaje_gasto'] = 'Tipo de gasto eliminado correctamente';
        $_SESSION['tipo_mensaje_gasto'] = 'success';
    } catch (PDOException $e) {
        // Capturar error de integridad referencial (foreign key constraint)
        // SQLSTATE[23000] es el código para violación de restricción de integridad
        $errorCode = $e->getCode();
        $errorMessage = $e->getMessage();
        
        if ($errorCode == '23000' || strpos($errorMessage, '1451') !== false || strpos($errorMessage, 'foreign key constraint') !== false) {
            $_SESSION['mensaje_gasto'] = 'No se puede eliminar el tipo de gasto "' . htmlspecialchars($cuentaGasto) . ' ' . htmlspecialchars($ceGasto) . '" porque está siendo utilizado en alguna factura o proveedor.';
            $_SESSION['tipo_mensaje_gasto'] = 'error';
        } else {
            $_SESSION['mensaje_gasto'] = 'Error al eliminar el tipo de gasto: ' . htmlspecialchars($errorMessage);
            $_SESSION['tipo_mensaje_gasto'] = 'error';
        }
    }
    header('Location: ?');
    exit;
}
$registros = $pdo->query("SELECT * FROM tipo_gasto ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Tipos de Gasto</title>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 p-7" x-data="gastoCrud()">

    <?php if (!empty($_SESSION['mensaje_gasto'])): ?>
        <div id="mensaje-gasto" class="mb-4 p-4 rounded <?= $_SESSION['tipo_mensaje_gasto'] === 'error' ? 'bg-red-100 border border-red-300 text-red-800' : 'bg-green-100 border border-green-300 text-green-800' ?>">
            <?= htmlspecialchars($_SESSION['mensaje_gasto']) ?>
        </div>
        <?php 
        unset($_SESSION['mensaje_gasto']);
        unset($_SESSION['tipo_mensaje_gasto']);
        ?>
        <script>
            setTimeout(function() {
                const mensaje = document.getElementById('mensaje-gasto');
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
            <h1 class="text-3xl font-bold text-gray-800">Gestión de Tipos de Gasto</h1>
            <a href="index.php" class="bg-gray-500 text-white font-semibold px-4 py-2 rounded hover:bg-gray-600 flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Volver a menú
            </a>
        </div>
        <button 
            @click="openModal()" 
            class="bg-green-600 text-white font-semibold px-4 py-2 rounded hover:bg-green-700">
            Añadir tipo de gasto
        </button>
    </div>

    <div class="bg-white shadow rounded overflow-x-auto">
        <table class="min-w-full border text-left">
            <thead class="bg-gray-200">
                <tr>
                    <th class="px-3 py-2 border text-left">ID</th>
                    <th class="px-3 py-2 border text-left">Cuenta</th>
                    <th class="px-3 py-2 border text-left">CE</th>
                    <th class="px-3 py-2 border text-left">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($registros as $row): ?>
                <tr>
                    <td class="border px-3 py-2 text-left"><?= $row['id'] ?></td>
                    <td class="border px-3 py-2 text-left"><?= htmlspecialchars($row['cuenta'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="border px-3 py-2 text-left"><?= htmlspecialchars($row['ce'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="border px-3 py-2">
                        <button 
                            @click="openModal('<?= $row['id'] ?>', '<?= htmlspecialchars($row['cuenta'], ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars($row['ce'], ENT_QUOTES, 'UTF-8') ?>')" 
                            class="bg-blue-500 text-white px-2 py-1 rounded hover:bg-blue-700">Editar</button>
                        <a href="?eliminar=<?= $row['id'] ?>" 
                            class="bg-red-500 text-white px-2 py-1 rounded hover:bg-red-700 ml-2"
                            onclick="return confirm('¿Seguro que deseas eliminar?')">Eliminar</a>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <!-- Modal para Alta/Edición -->
    <div 
        x-show="modal" 
        x-transition 
        class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-40"
        style="display: none;"
    >
        <div 
            class="bg-white rounded-lg p-7 shadow-lg w-full max-w-sm"
            @click.away="closeModal()"
        >
            <h2 class="text-xl font-semibold mb-4" x-text="formTitle"></h2>
            <form method="POST" @submit.prevent="submitForm($event)">
                <input type="hidden" name="id" :value="editId">
                <label class="block mb-3">
                    Cuenta:
                    <input name="cuenta" type="text" x-model="editCuenta" class="border px-2 py-1 w-full rounded"/>
                </label>
                <label class="block mb-3">
                    CE:
                    <input name="ce" type="text" x-model="editCE" class="border px-2 py-1 w-full rounded"/>
                </label>
                <div class="flex justify-end">
                    <button type="button" @click="closeModal()" class="mr-3 px-3 py-1 bg-gray-400 text-white rounded hover:bg-gray-500">Cancelar</button>
                    <button class="bg-green-600 px-3 py-1 text-white rounded hover:bg-green-700" type="submit">
                        Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function gastoCrud() {
            return {
                modal: false,
                editId: '',
                editCuenta: '',
                editCE: '',
                formTitle: '',
                openModal(id = '', cuenta = '', ce = '') {
                    this.modal = true;
                    this.editId = id;
                    this.editCuenta = cuenta || '';
                    this.editCE = ce || '';
                    this.formTitle = id ? 'Editar Tipo de Gasto' : 'Añadir Tipo de Gasto';
                },
                closeModal() {
                    this.modal = false;
                    this.editId = '';
                    this.editCuenta = '';
                    this.editCE = '';
                    this.formTitle = '';
                },
                submitForm(event) {
                    if (this.editCuenta.trim() === '' || this.editCE.trim() === '') {
                        alert('Por favor, completa todos los campos');
                        return;
                    }
                    event.target.submit();
                }
            }
        }
    </script>
</body>
</html>
