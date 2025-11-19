<?php
$title = 'Gestión de Tipos de Método';
require_once 'bd/db.php';

// CRUD alta, edición, borrado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar que tipoMetodo no esté vacío
    if (!isset($_POST['tipoMetodo']) || trim($_POST['tipoMetodo']) === '') {
        header('Location: ?');
        exit;
    }
    
    if (isset($_POST['id']) && $_POST['id'] !== '') {
        $stmt = $pdo->prepare("UPDATE tipometodos SET tipoMetodo=? WHERE id=?");
        $stmt->execute([trim($_POST['tipoMetodo']), $_POST['id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO tipometodos (tipoMetodo) VALUES (?)");
        $stmt->execute([trim($_POST['tipoMetodo'])]);
    }
    header('Location: ?');
    exit;
}
if (isset($_GET['eliminar'])) {
    // Obtener el nombre del tipo de método antes de eliminarlo
    $stmtMetodo = $pdo->prepare("SELECT tipoMetodo FROM tipometodos WHERE id=?");
    $stmtMetodo->execute([$_GET['eliminar']]);
    $metodoData = $stmtMetodo->fetch(PDO::FETCH_ASSOC);
    $nombreMetodo = $metodoData ? $metodoData['tipoMetodo'] : '';
    
    try {
        $stmt = $pdo->prepare("DELETE FROM tipometodos WHERE id=?");
        $stmt->execute([$_GET['eliminar']]);
        $_SESSION['mensaje_metodo'] = 'Tipo de método eliminado correctamente';
        $_SESSION['tipo_mensaje_metodo'] = 'success';
    } catch (PDOException $e) {
        // Capturar error de integridad referencial (foreign key constraint)
        // SQLSTATE[23000] es el código para violación de restricción de integridad
        $errorCode = $e->getCode();
        $errorMessage = $e->getMessage();
        
        if ($errorCode == '23000' || strpos($errorMessage, '1451') !== false || strpos($errorMessage, 'foreign key constraint') !== false) {
            $_SESSION['mensaje_metodo'] = 'No se puede eliminar el tipo de método "' . htmlspecialchars($nombreMetodo) . '" porque está siendo utilizado en algún pago.';
            $_SESSION['tipo_mensaje_metodo'] = 'error';
        } else {
            $_SESSION['mensaje_metodo'] = 'Error al eliminar el tipo de método: ' . htmlspecialchars($errorMessage);
            $_SESSION['tipo_mensaje_metodo'] = 'error';
        }
    }
    header('Location: ?');
    exit;
}
$metodos = $pdo->query("SELECT * FROM tipometodos ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<?php require_once 'templates/header.php'; ?>
<body class="bg-gray-100">
<?php require_once 'templates/menu.php'; ?>
<div class="p-7" x-data="metodoCrud()">

    <?php if (!empty($_SESSION['mensaje_metodo'])): ?>
        <div id="mensaje-metodo" class="mb-4 p-4 rounded <?= $_SESSION['tipo_mensaje_metodo'] === 'error' ? 'bg-red-100 border border-red-300 text-red-800' : 'bg-green-100 border border-green-300 text-green-800' ?>">
            <?= htmlspecialchars($_SESSION['mensaje_metodo']) ?>
        </div>
        <?php 
        unset($_SESSION['mensaje_metodo']);
        unset($_SESSION['tipo_mensaje_metodo']);
        ?>
        <script>
            setTimeout(function() {
                const mensaje = document.getElementById('mensaje-metodo');
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

    <div class="flex items-center space-x-3 mb-6">
        <h1 class="text-3xl font-bold text-gray-800">Gestión de Tipos de Método de Pago</h1>
        <a href="index.php" class="bg-gray-500 text-white font-semibold px-4 py-2 rounded hover:bg-gray-600 flex items-center">
            <i class="fas fa-arrow-left mr-2"></i> Volver a menú
        </a>
    </div>

    <button 
        @click="openModal()" 
        class="mb-4 bg-green-600 text-white font-semibold px-4 py-2 rounded hover:bg-green-700">
        Añadir tipo
    </button>

    <div class="bg-white shadow rounded overflow-x-auto">
        <table class="min-w-full border text-center">
            <thead class="bg-gray-200">
                <tr>
                    <th class="px-3 py-2 border">ID</th>
                    <th class="px-3 py-2 border">Tipo Método</th>
                    <th class="px-3 py-2 border">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($metodos as $row): ?>
                <tr>
                    <td class="border px-3 py-2"><?= $row['id'] ?></td>
                    <td class="border px-3 py-2"><?= htmlspecialchars($row['tipoMetodo'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="border px-3 py-2">
                        <button 
                            @click="openModal('<?= $row['id'] ?>', '<?= htmlspecialchars($row['tipoMetodo'], ENT_QUOTES, 'UTF-8') ?>')" 
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
                    Tipo de Método:
                    <input name="tipoMetodo" type="text" x-model="editTipo" class="border px-2 py-1 w-full rounded"/>
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
        function metodoCrud() {
            return {
                modal: false,
                editId: '',
                editTipo: '',
                formTitle: '',
                openModal(id = '', tipo = '') {
                    this.modal = true;
                    this.editId = id;
                    this.editTipo = tipo || '';
                    this.formTitle = id ? 'Editar Tipo de Método' : 'Añadir Tipo de Método';
                },
                closeModal() {
                    this.modal = false;
                    this.editId = '';
                    this.editTipo = '';
                    this.formTitle = '';
                },
                submitForm(event) {
                    if (this.editTipo.trim() === '') {
                        alert('Por favor, completa el campo "Tipo de Método"');
                        return;
                    }
                    event.target.submit();
                }
            }
        }
    </script>
<?php require_once 'templates/footer.php'; ?>
