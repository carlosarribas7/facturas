<?php
if(session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = new PDO('mysql:host=localhost;dbname=facturassalt', 'root', '');
$pdo->exec("SET NAMES utf8mb4");

// Alta / Edición
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['porcentaje']) || trim($_POST['porcentaje']) === ''
    ) {
        header('Location: ?');
        exit;
    }

    $porcentaje = trim($_POST['porcentaje']);

    if (isset($_POST['id']) && $_POST['id'] !== '') {
        $stmt = $pdo->prepare("UPDATE iva SET porcentaje=? WHERE id=?");
        $stmt->execute([$porcentaje, $_POST['id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO iva (porcentaje) VALUES (?)");
        $stmt->execute([$porcentaje]);
    }

    header('Location: ?');
    exit;
}

// Borrado
if (isset($_GET['eliminar'])) {
    // Obtener el porcentaje del IVA antes de eliminarlo
    $stmtIva = $pdo->prepare("SELECT porcentaje FROM iva WHERE id=?");
    $stmtIva->execute([$_GET['eliminar']]);
    $ivaData = $stmtIva->fetch(PDO::FETCH_ASSOC);
    $porcentajeIva = $ivaData ? $ivaData['porcentaje'] : '';
    
    try {
        $stmt = $pdo->prepare("DELETE FROM iva WHERE id=?");
        $stmt->execute([$_GET['eliminar']]);
        $_SESSION['mensaje_iva'] = 'IVA eliminado correctamente';
        $_SESSION['tipo_mensaje_iva'] = 'success';
    } catch (PDOException $e) {
        // Capturar error de integridad referencial (foreign key constraint)
        // SQLSTATE[23000] es el código para violación de restricción de integridad
        $errorCode = $e->getCode();
        $errorMessage = $e->getMessage();
        
        if ($errorCode == '23000' || strpos($errorMessage, '1451') !== false || strpos($errorMessage, 'foreign key constraint') !== false) {
            $_SESSION['mensaje_iva'] = 'No se puede eliminar el IVA del ' . htmlspecialchars($porcentajeIva) . '% porque está siendo utilizado en alguna factura.';
            $_SESSION['tipo_mensaje_iva'] = 'error';
        } else {
            $_SESSION['mensaje_iva'] = 'Error al eliminar el IVA: ' . htmlspecialchars($errorMessage);
            $_SESSION['tipo_mensaje_iva'] = 'error';
        }
    }
    header('Location: ?');
    exit;
}

// Listado
$registros = $pdo->query("SELECT * FROM iva ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de IVA</title>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="bg-gray-100 p-7" x-data="ivaCrud()">

    <?php if (!empty($_SESSION['mensaje_iva'])): ?>
        <div id="mensaje-iva" class="mb-4 p-4 rounded <?= $_SESSION['tipo_mensaje_iva'] === 'error' ? 'bg-red-100 border border-red-300 text-red-800' : 'bg-green-100 border border-green-300 text-green-800' ?>">
            <?= htmlspecialchars($_SESSION['mensaje_iva']) ?>
        </div>
        <?php 
        unset($_SESSION['mensaje_iva']);
        unset($_SESSION['tipo_mensaje_iva']);
        ?>
        <script>
            setTimeout(function() {
                const mensaje = document.getElementById('mensaje-iva');
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
            <h1 class="text-3xl font-bold text-gray-800">Gestión de IVA</h1>
            <a href="index.php" class="bg-gray-500 text-white font-semibold px-4 py-2 rounded hover:bg-gray-600 flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Volver a menú
            </a>
        </div>

        <button 
            @click="openModal()" 
            class="bg-green-600 text-white font-semibold px-4 py-2 rounded hover:bg-green-700">
            Añadir IVA
        </button>
    </div>

    <div class="bg-white shadow rounded overflow-x-auto">
        <table class="min-w-full border text-left">
            <thead class="bg-gray-200">
                <tr>
                    <th class="px-3 py-2 border">ID</th>
                    <th class="px-3 py-2 border">Porcentaje</th>
                    <th class="px-3 py-2 border">Acciones</th>
                </tr>
            </thead>

            <tbody>
            <?php foreach ($registros as $row): ?>
                <tr>
                    <td class="border px-3 py-2"><?= $row['id'] ?></td>
                    <td class="border px-3 py-2"><?= htmlspecialchars($row['porcentaje']) ?> %</td>

                    <td class="border px-3 py-2">
                        <button 
                            @click="openModal(
                                '<?= $row['id'] ?>',
                                '<?= htmlspecialchars($row['porcentaje'], ENT_QUOTES, 'UTF-8') ?>'
                            )"
                            class="bg-blue-500 text-white px-2 py-1 rounded hover:bg-blue-700">
                            Editar
                        </button>

                        <a href="?eliminar=<?= $row['id'] ?>" 
                           class="bg-red-500 text-white px-2 py-1 rounded ml-2 hover:bg-red-700"
                           onclick="return confirm('¿Seguro que deseas eliminar este IVA?')">
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
        <div class="bg-white rounded-lg p-7 shadow-lg w-full max-w-sm" @click.away="closeModal()">
            
            <h2 class="text-xl font-semibold mb-4" x-text="formTitle"></h2>

            <form method="POST" @submit.prevent="submitForm($event)">

                <input type="hidden" name="id" :value="editId">

                <label class="block mb-3">
                    Porcentaje (%):
                    <input 
                        name="porcentaje" 
                        type="text" 
                        x-model="editPorcentaje" 
                        class="border px-2 py-1 w-full rounded"
                    />
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
        function ivaCrud() {
            return {
                modal: false,
                editId: '',
                editPorcentaje: '',
                formTitle: '',

                openModal(id = '', porcentaje = '') {
                    this.modal = true;
                    this.editId = id;
                    this.editPorcentaje = porcentaje;
                    this.formTitle = id ? 'Editar IVA' : 'Añadir IVA';
                },

                closeModal() {
                    this.modal = false;
                    this.editId = '';
                    this.editPorcentaje = '';
                    this.formTitle = '';
                },

                submitForm(event) {
                    if (this.editPorcentaje.trim() === '') {
                        alert('Por favor, completa el porcentaje');
                        return;
                    }
                    event.target.submit();
                }
            }
        }
    </script>

</body>
</html>
