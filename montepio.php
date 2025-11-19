<?php
if(session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = new PDO('mysql:host=localhost;dbname=facturassalt', 'root', '');
$pdo->exec("SET NAMES utf8mb4");

// ALTA / EDICIÓN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validación básica
    if (
        !isset($_POST['numero_factura']) || trim($_POST['numero_factura']) === '' ||
        !isset($_POST['fecha_factura']) ||
        !isset($_POST['base']) || trim($_POST['base']) === '' ||
        !isset($_POST['irpf']) || trim($_POST['irpf']) === '' ||
        !isset($_POST['total']) || trim($_POST['total']) === ''
    ) {
        $_SESSION['mensaje_montepio'] = "Por favor, completa todos los campos obligatorios.";
        $_SESSION['tipo_mensaje_montepio'] = "error";
        header('Location: ?');
        exit;
    }

    $numero_factura = trim($_POST['numero_factura']);
    $fecha_factura = $_POST['fecha_factura'] !== '' ? $_POST['fecha_factura'] : NULL;
    $base = trim($_POST['base']);
    $irpf = trim($_POST['irpf']);
    $total = trim($_POST['total']);
    $fecha_pago = $_POST['fecha_pago'] !== '' ? $_POST['fecha_pago'] : NULL;

    // EDITAR
    if (isset($_POST['id']) && $_POST['id'] !== '') {
        $stmt = $pdo->prepare("
            UPDATE montepio SET 
            numero_factura=?, fecha_factura=?, base=?, irpf=?, total=?, fecha_pago=? 
            WHERE id=?
        ");
        $stmt->execute([$numero_factura, $fecha_factura, $base, $irpf, $total, $fecha_pago, $_POST['id']]);
        $_SESSION['mensaje_montepio'] = "Registro actualizado correctamente.";
        $_SESSION['tipo_mensaje_montepio'] = "success";

    // NUEVO
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO montepio (numero_factura, fecha_factura, base, irpf, total, fecha_pago)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$numero_factura, $fecha_factura, $base, $irpf, $total, $fecha_pago]);
        $_SESSION['mensaje_montepio'] = "Registro añadido correctamente.";
        $_SESSION['tipo_mensaje_montepio'] = "success";
    }

    header('Location: ?');
    exit;
}


// BORRAR
if (isset($_GET['eliminar'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM montepio WHERE id=?");
        $stmt->execute([$_GET['eliminar']]);
        $_SESSION['mensaje_montepio'] = "Registro eliminado correctamente.";
        $_SESSION['tipo_mensaje_montepio'] = "success";
    } catch (PDOException $e) {
        $_SESSION['mensaje_montepio'] = "Error al eliminar: " . $e->getMessage();
        $_SESSION['tipo_mensaje_montepio'] = "error";
    }

    header('Location: ?');
    exit;
}

$registros = $pdo->query("SELECT * FROM montepio ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$retenciones = $pdo->query("SELECT * FROM retenciones ORDER BY retencion ASC")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión Montepío</title>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100 p-7" x-data="crudMontepío()">

<?php if (!empty($_SESSION['mensaje_montepio'])): ?>
    <div id="mensaje" class="mb-4 p-4 rounded <?= $_SESSION['tipo_mensaje_montepio'] === 'error'
        ? 'bg-red-100 border border-red-300 text-red-800'
        : 'bg-green-100 border border-green-300 text-green-800' ?>">
        <?= htmlspecialchars($_SESSION['mensaje_montepio']) ?>
    </div>
    <?php unset($_SESSION['mensaje_montepio'], $_SESSION['tipo_mensaje_montepio']); ?>
    <script>
        setTimeout(() => {
            const m = document.getElementById('mensaje');
            if (m) {
                m.style.transition = 'opacity 0.5s';
                m.style.opacity = '0';
                setTimeout(() => m.remove(), 500);
            }
        }, 4000);
    </script>
<?php endif; ?>

<div class="flex justify-between items-center mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Gestión Montepío</h1>
    <a href="index.php" class="bg-gray-500 text-white font-semibold px-4 py-2 rounded hover:bg-gray-600 flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Volver a menú
            </a>
    <button @click="openModal()" class="bg-green-600 text-white font-semibold px-4 py-2 rounded hover:bg-green-700">
        Añadir registro
    </button>
</div>

<div class="bg-white shadow rounded overflow-x-auto">
    <table class="min-w-full border text-left">
        <thead class="bg-gray-200">
            <tr>
                <th class="px-3 py-2 border">ID</th>
                <th class="px-3 py-2 border">Factura</th>
                <th class="px-3 py-2 border">Fecha</th>
                <th class="px-3 py-2 border">Base</th>
                <th class="px-3 py-2 border">IRPF</th>
                <th class="px-3 py-2 border">Total</th>
                <th class="px-3 py-2 border">Fecha Pago</th>
                <th class="px-3 py-2 border">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($registros as $row): ?>
                <tr>
                    <td class="border px-3 py-2"><?= $row['id'] ?></td>
                    <td class="border px-3 py-2"><?= htmlspecialchars($row['numero_factura']) ?></td>
                    <td class="border px-3 py-2">
    <?= $row['fecha_factura'] ? date("d/m/Y", strtotime($row['fecha_factura'])) : '' ?>
</td>
                    <td class="border px-3 py-2"><?= $row['base'] ?></td>
                    <td class="border px-3 py-2"><?= $row['irpf'] ?></td>
                    <td class="border px-3 py-2"><?= $row['total'] ?></td>
                    <td class="border px-3 py-2">
    <?= $row['fecha_pago'] ? date("d/m/Y", strtotime($row['fecha_pago'])) : '' ?>
</td>

                    <td class="border px-3 py-2">
                        <button 
                            @click="openModal(
                                '<?= $row['id'] ?>',
                                '<?= htmlspecialchars($row['numero_factura'], ENT_QUOTES) ?>',
                                '<?= $row['fecha_factura'] ?>',
                                '<?= $row['base'] ?>',
                                '<?= $row['irpf'] ?>',
                                '<?= $row['total'] ?>',
                                '<?= $row['fecha_pago'] ?>'
                            )"
                            class="bg-blue-500 text-white px-2 py-1 rounded hover:bg-blue-700">Editar</button>

                        <a href="?eliminar=<?= $row['id'] ?>"
                            onclick="return confirm('¿Seguro que deseas eliminar?')"
                            class="bg-red-500 text-white px-2 py-1 rounded hover:bg-red-700 ml-2">Eliminar</a>
                    </td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>

<!-- MODAL -->
<div x-show="modal" x-transition class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-40" style="display:none;">
    <div class="bg-white rounded-lg p-7 shadow-lg w-full max-w-lg" @click.away="closeModal()">

        <h2 class="text-xl font-semibold mb-4" x-text="formTitle"></h2>

        <form method="POST" @submit.prevent="submitForm($event)">
            <input type="hidden" name="id" :value="editId">

            <label class="block mb-3">Factura:
                <input name="numero_factura" type="text" x-model="editFactura" class="border px-2 py-1 w-full rounded">
            </label>

            <label class="block mb-3">Fecha factura:
                <input name="fecha_factura" type="date" x-model="editFecha" class="border px-2 py-1 w-full rounded">
            </label>

            <label class="block mb-3">Base:
                <input name="base" type="number" step="0.01"
                x-model="editBase" @input="calcularTotal()"
                class="border px-2 py-1 w-full rounded">
            </label>

           <label class="block mb-3">IRPF:
               <select name="irpf" x-model="editIrpf" @change="calcularTotal()"
                    class="border px-2 py-1 w-full rounded">

                    <option value="">Seleccione IRPF</option>
                    <?php foreach ($retenciones as $r): ?>
                        <option value="<?= $r['retencion'] ?>">
                            <?= $r['retencion'] * 100 ?>%
                        </option>
                    <?php endforeach ?>
                </select>
            </label>


           <label class="block mb-3">Total:
                <input name="total" type="number" step="0.01" x-model="editTotal"
                    class="border px-2 py-1 w-full rounded bg-gray-200" readonly>
            </label>

            <label class="block mb-3">Fecha Pago:
                <input name="fecha_pago" type="date" x-model="editPago" class="border px-2 py-1 w-full rounded">
            </label>

            <div class="flex justify-end mt-4">
                <button type="button" @click="closeModal()" class="mr-3 px-3 py-1 bg-gray-400 text-white rounded">Cancelar</button>
                <button class="bg-green-600 px-3 py-1 text-white rounded" type="submit">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
function crudMontepío() {
    return {
        modal: false,
        editId: '',
        editFactura: '',
        editFecha: '',
        editBase: '',
        editIrpf: '',
        editTotal: '',
        editPago: '',
        formTitle: '',

        

        openModal(id = '', factura = '', fecha = '', base = '', irpf = '', total = '', pago = '') {
            this.modal = true;
            this.editId = id;
            this.editFactura = factura;
            this.editFecha = fecha;
            this.editBase = base;
            this.editIrpf = irpf;
            this.editTotal = total;
            this.editPago = pago;
            this.formTitle = id ? 'Editar Registro' : 'Añadir Registro';
        },

        closeModal() {
            this.modal = false;
            this.editId = '';
            this.editFactura = '';
            this.editFecha = '';
            this.editBase = '';
            this.editIrpf = '';
            this.editTotal = '';
            this.editPago = '';
        },

        submitForm(ev) {
            if (this.editFactura.trim() === '' ||
                this.editBase.trim() === '' ||
                this.editIrpf.trim() === '' ||
                this.editTotal.trim() === '') 
            {
                alert('Por favor, rellena los campos obligatorios.');
                return;
            }
            ev.target.submit();
        },

        calcularTotal() {
    let b = parseFloat(this.editBase) || 0;
    let r = parseFloat(this.editIrpf) || 0;
    this.editTotal = (b - (b * r)).toFixed(2);
},
watch: {
    editBase() { this.calcularTotal(); },
    editIrpf() { this.calcularTotal(); }
}


    }
}
</script>

</body>
</html>
