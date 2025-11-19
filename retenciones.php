<?php
if(session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = new PDO('mysql:host=localhost;dbname=facturassalt', 'root', '');
$pdo->exec("SET NAMES utf8mb4");

// Alta / Edición
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['retencion']) || trim($_POST['retencion']) === '' ||
        !isset($_POST['ambito']) || trim($_POST['ambito']) === ''
    ) {
        header('Location: ?');
        exit;
    }

    $retencion = trim($_POST['retencion']);
    $ambito = trim($_POST['ambito']);

    if (isset($_POST['id']) && $_POST['id'] !== '') {
        $stmt = $pdo->prepare("UPDATE retenciones SET retencion=?, ambito=? WHERE id=?");
        $stmt->execute([$retencion, $ambito, $_POST['id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO retenciones (retencion, ambito) VALUES (?, ?)");
        $stmt->execute([$retencion, $ambito]);
    }

    header('Location: ?');
    exit;
}

// Borrado
if (isset($_GET['eliminar'])) {
    // Obtener los datos de la retención antes de eliminarla
    $stmtRetencion = $pdo->prepare("SELECT retencion, ambito FROM retenciones WHERE id=?");
    $stmtRetencion->execute([$_GET['eliminar']]);
    $retencionData = $stmtRetencion->fetch(PDO::FETCH_ASSOC);
    $retencionValor = $retencionData ? $retencionData['retencion'] : '';
    $ambitoValor = $retencionData ? $retencionData['ambito'] : '';
    
    try {
        $stmt = $pdo->prepare("DELETE FROM retenciones WHERE id=?");
        $stmt->execute([$_GET['eliminar']]);
        $_SESSION['mensaje_retencion'] = 'Retención eliminada correctamente';
        $_SESSION['tipo_mensaje_retencion'] = 'success';
    } catch (PDOException $e) {
        // Capturar error de integridad referencial (foreign key constraint)
        // SQLSTATE[23000] es el código para violación de restricción de integridad
        $errorCode = $e->getCode();
        $errorMessage = $e->getMessage();
        
        if ($errorCode == '23000' || strpos($errorMessage, '1451') !== false || strpos($errorMessage, 'foreign key constraint') !== false) {
            $_SESSION['mensaje_retencion'] = 'No se puede eliminar la retención del ' . htmlspecialchars($retencionValor) . '% (' . htmlspecialchars($ambitoValor) . ') porque está siendo utilizada en alguna factura.';
            $_SESSION['tipo_mensaje_retencion'] = 'error';
        } else {
            $_SESSION['mensaje_retencion'] = 'Error al eliminar la retención: ' . htmlspecialchars($errorMessage);
            $_SESSION['tipo_mensaje_retencion'] = 'error';
        }
    }
    header('Location: ?');
    exit;
}

// Listado
$registros = $pdo->query("SELECT * FROM retenciones ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Retenciones</title>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="bg-gray-100 p-7" x-data="retencionesCrud()">

    <?php if (!empty($_SESSION['mensaje_retencion'])): ?>
        <div id="mensaje-retencion" class="mb-4 p-4 rounded <?= $_SESSION['tipo_mensaje_retencion'] === 'error' ? 'bg-red-100 border border-red-300 text-red-800' : 'bg-green-100 border border-green-300 text-green-800' ?>">
            <?= htmlspecialchars($_SESSION['mensaje_retencion']) ?>
        </div>
        <?php 
        unset($_SESSION['mensaje_retencion']);
        unset($_SESSION['tipo_mensaje_retencion']);
        ?>
        <script>
            setTimeout(function() {
                const mensaje = document.getElementById('mensaje-retencion');
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
            <h1 class="text-3xl font-bold text-gray-800">Gestión de Retenciones</h1>
            <a href="index.php" class="bg-gray-500 text-white font-semibold px-4 py-2 rounded hover:bg-gray-600 flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Volver a menú
            </a>
        </div>

        <button 
            @click="openModal()" 
            class="bg-green-600 text-white font-semibold px-4 py-2 rounded hover:bg-green-700">
            Añadir retención
        </button>
    </div>

    <div class="bg-white shadow rounded overflow-x-auto">
        <table class="min-w-full border text-left">
            <thead class="bg-gray-200">
                <tr>
                    <th class="px-3 py-2 border">ID</th>
                    <th class="px-3 py-2 border">Retención</th>
                    <th class="px-3 py-2 border">Ámbito</th>
                    <th class="px-3 py-2 border">Acciones</th>
                </tr>
            </thead>

            <tbody>
            <?php foreach ($registros as $row): ?>
                <tr>
                    <td class="border px-3 py-2"><?= $row['id'] ?></td>
                    <td class="border px-3 py-2"><?= htmlspecialchars($row['retencion']) ?></td>
                    <td class="border px-3 py-2"><?= htmlspecialchars($row['ambito']) ?></td>

                    <td class="border px-3 py-2">
                        <button 
                            @click="openModal(
                                '<?= $row['id'] ?>',
                                '<?= htmlspecialchars($row['retencion'], ENT_QUOTES, 'UTF-8') ?>',
                                '<?= htmlspecialchars($row['ambito'], ENT_QUOTES, 'UTF-8') ?>'
                            )"
                            class="bg-blue-500 text-white px-2 py-1 rounded hover:bg-blue-700">
                            Editar
                        </button>

                        <a href="?eliminar=<?= $row['id'] ?>" 
                           class="bg-red-500 text-white px-2 py-1 rounded ml-2 hover:bg-red-700"
                           onclick="return confirm('¿Seguro que deseas eliminar esta retención?')">
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
                    Retención (% decimal):
                    <input 
                        name="retencion" 
                        type="text" 
                        x-model="editRetencion" 
                        class="border px-2 py-1 w-full rounded"
                    />
                </label>

                <label class="block mb-3">
                    Ámbito:
                    <input 
                        name="ambito" 
                        type="text" 
                        x-model="editAmbito" 
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
        function retencionesCrud() {
            return {
                modal: false,
                editId: '',
                editRetencion: '',
                editAmbito: '',
                formTitle: '',

                openModal(id = '', retencion = '', ambito = '') {
                    this.modal = true;
                    this.editId = id;
                    this.editRetencion = retencion;
                    this.editAmbito = ambito;

                    this.formTitle = id ? 'Editar Retención' : 'Añadir Retención';
                },

                closeModal() {
                    this.modal = false;
                    this.editId = '';
                    this.editRetencion = '';
                    this.editAmbito = '';
                    this.formTitle = '';
                },

                submitForm(event) {
                    if (this.editRetencion.trim() === '' || this.editAmbito.trim() === '') {
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
