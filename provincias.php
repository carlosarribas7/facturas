<?php
if(session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = new PDO('mysql:host=localhost;dbname=facturassalt', 'root', '');
$pdo->exec("SET NAMES utf8mb4");

// Alta/Edición/Borrado con validación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        !isset($_POST['provincia']) || trim($_POST['provincia']) === ''
    ) {
        header('Location: ?');
        exit;
    }

    $provincia = trim($_POST['provincia']);

    if (isset($_POST['id']) && $_POST['id'] !== '') {
        $stmt = $pdo->prepare("UPDATE provincias SET provincia=? WHERE id=?");
        $stmt->execute([$provincia, $_POST['id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO provincias (provincia) VALUES (?)");
        $stmt->execute([$provincia]);
    }

    header('Location: ?');
    exit;
}

if (isset($_GET['eliminar'])) {
    // Obtener el nombre de la provincia antes de eliminarla
    $stmtProvincia = $pdo->prepare("SELECT provincia FROM provincias WHERE id=?");
    $stmtProvincia->execute([$_GET['eliminar']]);
    $provinciaData = $stmtProvincia->fetch(PDO::FETCH_ASSOC);
    $nombreProvincia = $provinciaData ? $provinciaData['provincia'] : '';
    
    try {
        $stmt = $pdo->prepare("DELETE FROM provincias WHERE id=?");
        $stmt->execute([$_GET['eliminar']]);
        $_SESSION['mensaje_provincia'] = 'Provincia eliminada correctamente';
        $_SESSION['tipo_mensaje_provincia'] = 'success';
    } catch (PDOException $e) {
        // Capturar error de integridad referencial (foreign key constraint)
        // SQLSTATE[23000] es el código para violación de restricción de integridad
        $errorCode = $e->getCode();
        $errorMessage = $e->getMessage();
        
        if ($errorCode == '23000' || strpos($errorMessage, '1451') !== false || strpos($errorMessage, 'foreign key constraint') !== false) {
            $_SESSION['mensaje_provincia'] = 'No se puede eliminar la provincia "' . htmlspecialchars($nombreProvincia) . '" porque está siendo utilizada en algún proveedor.';
            $_SESSION['tipo_mensaje_provincia'] = 'error';
        } else {
            $_SESSION['mensaje_provincia'] = 'Error al eliminar la provincia: ' . htmlspecialchars($errorMessage);
            $_SESSION['tipo_mensaje_provincia'] = 'error';
        }
    }
    header('Location: ?');
    exit;
}

$registros = $pdo->query("SELECT * FROM provincias ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Provincias</title>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="bg-gray-100 p-7" x-data="provinciaCrud()">

    <?php if (!empty($_SESSION['mensaje_provincia'])): ?>
        <div id="mensaje-provincia" class="mb-4 p-4 rounded <?= $_SESSION['tipo_mensaje_provincia'] === 'error' ? 'bg-red-100 border border-red-300 text-red-800' : 'bg-green-100 border border-green-300 text-green-800' ?>">
            <?= htmlspecialchars($_SESSION['mensaje_provincia']) ?>
        </div>
        <?php 
        unset($_SESSION['mensaje_provincia']);
        unset($_SESSION['tipo_mensaje_provincia']);
        ?>
        <script>
            setTimeout(function() {
                const mensaje = document.getElementById('mensaje-provincia');
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
            <h1 class="text-3xl font-bold text-gray-800">Gestión de Provincias</h1>
            <a href="index.php" class="bg-gray-500 text-white font-semibold px-4 py-2 rounded hover:bg-gray-600 flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Volver a menú
            </a>
        </div>

        <button 
            @click="openModal()" 
            class="bg-green-600 text-white font-semibold px-4 py-2 rounded hover:bg-green-700">
            Añadir provincia
        </button>
    </div>

    <div class="bg-white shadow rounded overflow-x-auto">
        <table class="min-w-full border text-left">
            <thead class="bg-gray-200">
                <tr>
                    <th class="px-3 py-2 border text-left">ID</th>
                    <th class="px-3 py-2 border text-left">Provincia</th>
                    <th class="px-3 py-2 border text-left">Acciones</th>
                </tr>
            </thead>

            <tbody>
            <?php foreach ($registros as $row): ?>
                <tr>
                    <td class="border px-3 py-2"><?= $row['id'] ?></td>
                    <td class="border px-3 py-2"><?= htmlspecialchars($row['provincia'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td class="border px-3 py-2">
                        <button 
                            @click="openModal('<?= $row['id'] ?>', '<?= htmlspecialchars($row['provincia'], ENT_QUOTES, 'UTF-8') ?>')" 
                            class="bg-blue-500 text-white px-2 py-1 rounded hover:bg-blue-700">
                            Editar
                        </button>

                        <a href="?eliminar=<?= $row['id'] ?>" 
                           class="bg-red-500 text-white px-2 py-1 rounded ml-2 hover:bg-red-700"
                           onclick="return confirm('¿Seguro que deseas eliminar esta provincia?')">
                            Eliminar
                        </a>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>

        </table>
    </div>

    <!-- Modal Alta / Edición -->
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
                    Provincia:
                    <input 
                        name="provincia" 
                        type="text" 
                        x-model="editProvincia" 
                        class="border px-2 py-1 w-full rounded"
                    />
                </label>

                <div class="flex justify-end">
                    <button type="button" 
                        @click="closeModal()" 
                        class="mr-3 px-3 py-1 bg-gray-400 text-white rounded hover:bg-gray-500">
                        Cancelar
                    </button>

                    <button 
                        class="bg-green-600 px-3 py-1 text-white rounded hover:bg-green-700" 
                        type="submit">
                        Guardar
                    </button>
                </div>
            </form>

        </div>
    </div>

    <script>
        function provinciaCrud() {
            return {
                modal: false,
                editId: '',
                editProvincia: '',
                formTitle: '',

                openModal(id = '', provincia = '') {
                    this.modal = true;
                    this.editId = id;
                    this.editProvincia = provincia || '';
                    this.formTitle = id ? 'Editar Provincia' : 'Añadir Provincia';
                },

                closeModal() {
                    this.modal = false;
                    this.editId = '';
                    this.editProvincia = '';
                    this.formTitle = '';
                },

                submitForm(event) {
                    if (this.editProvincia.trim() === '') {
                        alert('Por favor, completa el campo provincia');
                        return;
                    }
                    event.target.submit();
                }
            }
        }
    </script>

</body>
</html>
