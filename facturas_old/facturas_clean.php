<?php
$pdo = new PDO('mysql:host=localhost;dbname=facturassalt', 'root', '');
$pdo->exec("SET NAMES utf8mb4");

// ---------------- HELPER ----------------
function h($v) {
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

// ---------------- PAGINACIÓN ----------------
$porPagina = 10;
$pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;

// ---------------- BUSCADOR ----------------
$buscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$paramBuscarSQL = $buscar !== '' ? "%$buscar%" : '%';

// ---------------- LISTAS PARA SELECT (FKs) ----------------
$listaProveedores = $pdo->query("SELECT id, nombre FROM proveedores ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
$listaTipoGasto = $pdo->query("SELECT id, cuenta, ce FROM tipo_gasto ORDER BY cuenta ASC")->fetchAll(PDO::FETCH_ASSOC);
$listaRetenciones = $pdo->query("SELECT id, retencion, ambito FROM retenciones ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$listaPagos = $pdo->query("SELECT id, nombreMetodoPago FROM pagos ORDER BY nombreMetodoPago ASC")->fetchAll(PDO::FETCH_ASSOC);

// ---------------- CONTEO FILTRADO ----------------
$stmtCount = $pdo->prepare("
    SELECT COUNT(*)
    FROM facturas f
    LEFT JOIN proveedores pr ON f.proveedor_id = pr.id
    LEFT JOIN tipo_gasto tg ON f.tipoGasto_id = tg.id
    WHERE 
        f.numero_factura LIKE :buscar
        OR pr.nombre LIKE :buscar
        OR tg.cuenta LIKE :buscar
");

$stmtCount->bindValue(':buscar', $paramBuscarSQL, PDO::PARAM_STR);
$stmtCount->execute();
$totalRegistros = $stmtCount->fetchColumn();
$totalPaginas = max(1, ceil($totalRegistros / $porPagina));
$offset = ($pagina - 1) * $porPagina;
$listaIVA = $pdo->query("SELECT id, porcentaje FROM iva ORDER BY porcentaje ASC")
                ->fetchAll(PDO::FETCH_ASSOC);


// ---------------- CRUD: ALTA/EDICIÓN ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
    if (!isset($_POST['numero_factura']) || trim($_POST['numero_factura']) === '' || !isset($_POST['proveedor_id']) || trim($_POST['proveedor_id']) === '') {
        header('Location: ?');
        exit;
    }

    $data = [
        'proveedor_id' => $_POST['proveedor_id'] ?: null,
        'tipoGasto_id' => $_POST['tipoGasto_id'] ?: null,
        'numero_factura' => trim($_POST['numero_factura'] ?? ''),
        'fecha_factura' => $_POST['fecha_factura'] ?: null,
        'mes' => trim($_POST['mes'] ?? ''),
        'bi1' => $_POST['bi1'] !== '' ? $_POST['bi1'] : null,
        'iva1' => $_POST['iva1'] !== '' ? $_POST['iva1'] : null,
        'ci1' => $_POST['ci1'] !== '' ? $_POST['ci1'] : null,
        'bi2' => $_POST['bi2'] !== '' ? $_POST['bi2'] : null,
        'iva2' => $_POST['iva2'] !== '' ? $_POST['iva2'] : null,
        'ci2' => $_POST['ci2'] !== '' ? $_POST['ci2'] : null,
        'bi3' => $_POST['bi3'] !== '' ? $_POST['bi3'] : null,
        'iva3' => $_POST['iva3'] !== '' ? $_POST['iva3'] : null,
        'ci3' => $_POST['ci3'] !== '' ? $_POST['ci3'] : null,
        'retencion' => $_POST['retencion'] !== '' ? $_POST['retencion'] : null,
        'retencion_id' => $_POST['retencion_id'] ?: null,
        'total_factura' => $_POST['total_factura'] !== '' ? $_POST['total_factura'] : null,
        'pago_id' => $_POST['pago_id'] ?: null,
        'fecha_vencimiento' => $_POST['fecha_vencimiento'] ?: null,
        'fecha_banco' => $_POST['fecha_banco'] ?: null,
        'fecha_hora' => trim($_POST['fecha_hora'] ?? '')
    ];

        $sql = "UPDATE facturas SET proveedor_id=?, tipoGasto_id=?, numero_factura=?, fecha_factura=?, mes=?,
                bi1=?, iva1=?, ci1=?, bi2=?, iva2=?, ci2=?, bi3=?, iva3=?, ci3=?,
                retencion=?, retencion_id=?, total_factura=?, pago_id=?, fecha_vencimiento=?, fecha_banco=?, fecha_hora=?
                WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['proveedor_id'], $data['tipoGasto_id'], $data['numero_factura'], $data['fecha_factura'], $data['mes'],
            $data['bi1'], $data['iva1'], $data['ci1'], $data['bi2'], $data['iva2'], $data['ci2'],
            $data['bi3'], $data['iva3'], $data['ci3'], $data['retencion'], $data['retencion_id'], $data['total_factura'],
            $data['pago_id'], $data['fecha_vencimiento'], $data['fecha_banco'], $data['fecha_hora'],
            $_POST['id']
        ]);
    } else {
        // INSERT
        $sql = "INSERT INTO facturas (proveedor_id, tipoGasto_id, numero_factura, fecha_factura, mes,
                bi1, iva1, ci1, bi2, iva2, ci2, bi3, iva3, ci3,
                retencion, retencion_id, total_factura, pago_id, fecha_vencimiento, fecha_banco, fecha_hora)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['proveedor_id'], $data['tipoGasto_id'], $data['numero_factura'], $data['fecha_factura'], $data['mes'],
            $data['bi1'], $data['iva1'], $data['ci1'], $data['bi2'], $data['iva2'], $data['ci2'],
            $data['bi3'], $data['iva3'], $data['ci3'], $data['retencion'], $data['retencion_id'], $data['total_factura'],
            $data['pago_id'], $data['fecha_vencimiento'], $data['fecha_banco'], $data['fecha_hora']
        ]);
    }

    header('Location: ?');
    exit;
    } catch (PDOException $e) {
        if (!session_id()) session_start();
        $_SESSION['error_facturas'] = 'Error en la base de datos: ' . $e->getMessage();
        header('Location: ?'); exit;
    }
}$query = "
    SELECT f.*, pr.nombre AS proveedor_nombre, tg.cuenta AS tipoCuenta, tgm.ce AS tipoCE,
           r.retencion AS retencion_valor, r.ambito AS retencion_ambito, pay.nombreMetodoPago AS pago_nombre
    FROM facturas f
    LEFT JOIN proveedores pr ON f.proveedor_id = pr.id
    LEFT JOIN tipo_gasto tg ON f.tipoGasto_id = tg.id
    LEFT JOIN tipo_gasto tgm ON f.tipoGasto_id = tgm.id
    LEFT JOIN retenciones r ON f.retencion_id = r.id
    LEFT JOIN pagos pay ON f.pago_id = pay.id
    WHERE 
    f.numero_factura LIKE :buscar
    OR pr.nombre LIKE :buscar
    OR tg.cuenta LIKE :buscar

    ORDER BY f.id DESC
    LIMIT :offset, :porpagina
";

$stmt = $pdo->prepare($query);
$stmt->bindValue(':buscar', $paramBuscarSQL, PDO::PARAM_STR);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':porpagina', $porPagina, PDO::PARAM_INT);
$stmt->execute();
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Facturas</title>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 p-7"
      x-data="facturasCrud(<?= htmlspecialchars(json_encode($listaProveedores), ENT_QUOTES, 'UTF-8') ?>
<?php if(session_status()===PHP_SESSION_NONE) session_start(); if(!empty($_SESSION['error_facturas'])){ echo "<div class='bg-red-100 border border-red-300 text-red-800 p-3 rounded mb-4'>".htmlspecialchars($_SESSION['error_facturas'])."</div>"; unset($_SESSION['error_facturas']); } ?>
,
                        <?= htmlspecialchars(json_encode($listaTipoGasto), ENT_QUOTES, 'UTF-8') ?>,
                        <?= htmlspecialchars(json_encode($listaRetenciones), ENT_QUOTES, 'UTF-8') ?>,
                        <?= htmlspecialchars(json_encode($listaPagos), ENT_QUOTES, 'UTF-8') ?>,
                        <?= htmlspecialchars(json_encode($listaIVA), ENT_QUOTES, 'UTF-8') ?>)">

<div class="flex justify-between items-center mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Gestión de Facturas</h1>

    <div class="flex items-center space-x-3">

        <form method="GET" class="flex items-center">
            <input type="text" name="buscar" placeholder="Buscar por Proveedor o Tipo de Gasto" value="<?= h($buscar) ?>" class="border px-3 py-1 rounded-l w-48">
            <button class="bg-blue-600 text-white px-3 py-1 rounded-r hover:bg-blue-700" type="submit">Buscar</button>
        </form>

        <button id="btnAddFactura" @click="openModal()" class="bg-green-600 text-white font-semibold px-4 py-2 rounded hover:bg-green-700">Añadir factura</button>
    </div>
</div>

<div class="bg-white shadow rounded overflow-x-auto">
    <table class="min-w-full border">
        <thead class="bg-gray-200">
            <tr>
                <th class="border px-3 py-2">ID</th>
                <th class="border px-3 py-2">Proveedor</th>
                <th class="border px-3 py-2">Tipo Gasto</th>
                <th class="border px-3 py-2">Nº Factura</th>
                <th class="border px-3 py-2">Fecha</th>
                <th class="border px-3 py-2">Total</th>
                <th class="border px-3 py-2">Pago</th>
                <th class="border px-3 py-2">Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($registros as $row): ?>
            <tr>
                <td class="border px-3 py-2"><?= $row['id'] ?></td>
                <td class="border px-3 py-2"><?= h($row['proveedor_nombre']) ?></td>
                <td class="border px-3 py-2"><?= h($row['tipoCuenta']) ?></td>
                <td class="border px-3 py-2"><?= h($row['numero_factura']) ?></td>
                <td class="border px-3 py-2"><?= $row['fecha_factura'] ? date('d/m/Y', strtotime($row['fecha_factura'])) : '' ?>
</td>
                <td class="border px-3 py-2"><?= h($row['total_factura']) ?></td>
                <td class="border px-3 py-2"><?= h($row['pago_nombre']) ?></td>
                <td class="border px-3 py-2">
                    <button class="bg-blue-500 text-white px-2 py-1 rounded hover:bg-blue-700"
                        data-row='<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>'
                        onclick="handleEditFactura(this)">
                        Editar
                    </button>
                    <a href="?eliminar=<?= $row['id'] ?>" class="bg-red-500 text-white px-2 py-1 rounded ml-2 hover:bg-red-700" onclick="return confirm('¿Eliminar factura?')">Eliminar</a>
                </td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
</div>

<!-- PAGINACIÓN -->
<div class="flex justify-center items-center space-x-2 mt-4">

    <a href="?pagina=1&buscar=<?= urlencode($buscar) ?>" class="px-3 py-1 border rounded bg-gray-200 hover:bg-gray-300">⏮</a>
    <a href="?pagina=<?= max(1, $pagina - 1) ?>&buscar=<?= urlencode($buscar) ?>" class="px-3 py-1 border rounded bg-gray-200 hover:bg-gray-300">◀</a>
    <span class="px-4 py-1 border rounded bg-white">Página <?= $pagina ?> de <?= $totalPaginas ?></span>
    <a href="?pagina=<?= min($totalPaginas, $pagina + 1) ?>&buscar=<?= urlencode($buscar) ?>" class="px-3 py-1 border rounded bg-gray-200 hover:bg-gray-300">▶</a>
    <a href="?pagina=<?= $totalPaginas ?>&buscar=<?= urlencode($buscar) ?>" class="px-3 py-1 border rounded bg-gray-200 hover:bg-gray-300">⏭</a>

</div>

<!-- MODAL NUEVO EN 5 COLUMNAS -->
<div 
    x-show="modal" 
    x-transition 
    class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-40"
    style="display:none;"
>
    <div class="bg-white rounded-lg p-7 shadow-lg w-full max-w-7xl" 
         @click.away="closeModal()">

        <h2 class="text-xl font-semibold mb-4" x-text="formTitle"></h2>

        <form method="POST" @submit.prevent="submitForm($event)">

            <input type="hidden" name="id" :value="editId">

            <!-- FILA 1 -->
            <div class="grid grid-cols-5 gap-4 mb-4">

                <!-- PROVEEDOR -->
                <div>
                    <label class="block mb-1 font-semibold">Proveedor</label>
                    <select name="proveedor_id" x-model="form.proveedor_id"
                        class="border px-2 py-1 w-full rounded">
                        <option value="">-- Seleccionar --</option>
                        <template x-for="p in listaProveedores" :key="p.id">
                            <option :value="p.id" x-text="p.nombre"></option>
                        </template>
                    </select>
                </div>

                <!-- TIPO GASTO -->
                <div>
                    <label class="block mb-1 font-semibold">Tipo Gasto</label>
                    <select name="tipoGasto_id" x-model="form.tipoGasto_id"
                        class="border px-2 py-1 w-full rounded">
                        <option value="">-- Seleccionar --</option>
                        <template x-for="t in listaTipoGasto" :key="t.id">
                            <option :value="t.id" x-text="t.cuenta"></option>
                        </template>
                    </select>
                </div>

                <!-- Nº FACTURA -->
                <div>
                    <label class="block mb-1 font-semibold">Nº Factura</label>
                    <input name="numero_factura" type="text" 
                        x-model="form.numero_factura" 
                        class="border px-2 py-1 w-full rounded">
                </div>

                <!-- FECHA FACTURA -->
                <div>
                    <label class="block mb-1 font-semibold">Fecha Factura</label>
                    <input name="fecha_factura" type="date"
                        x-model="form.fecha_factura"
                        @change="form.mes = obtenerMesEspañol(form.fecha_factura)"
                        class="border px-2 py-1 w-full rounded">
                </div>

                <!-- MES -->
                <div>
                    <label class="block mb-1 font-semibold">Mes</label>
                    <input name="mes" type="text"
                    x-model="form.mes"
                    readonly
                    class="border px-2 py-1 w-full rounded bg-gray-100 text-gray-600">

                </div>
            </div>

            <!-- FILA 2: BI1 - IVA1 - CI1 -->
            <div class="grid grid-cols-5 gap-4 mb-4">

                <div>
                    <label class="block mb-1 font-semibold">BI1</label>
                    <input name="bi1" type="text" 
                        x-model="form.bi1" 
                        @input="calcularCI()"
                        class="border px-2 py-1 w-full rounded">
                </div>

                <div>
                    <label class="block mb-1 font-semibold">IVA1 (%)</label>
                    <select name="iva1" 
                            x-model="form.iva1"
                            @change="calcularCI()"
                            class="border px-2 py-1 w-full rounded">
                        <option value="">-- IVA --</option>
                        <template x-for="i in listaIVA" :key="i.id">
                            <option :value="i.id" 
                                    x-text="(i.porcentaje * 100) + '%'"></option>
                        </template>
                    </select>


                </div>

                <div>
                    <label class="block mb-1 font-semibold">CI1</label>
                    <input name="ci1" type="text" 
                        x-model="form.ci1" 
                        readonly
                        class="border px-2 py-1 w-full rounded bg-gray-100 text-gray-600">

                </div>

                <!-- columnas vacías -->
                <div></div>
                <div></div>
            </div>

            <!-- FILA 3 -->
            <div class="grid grid-cols-5 gap-4 mb-4">
                <div>
                    <label class="block mb-1 font-semibold">BI2</label>
                    <input name="bi2" type="text" 
                        x-model="form.bi2" 
                        @input="calcularCI()"
                        class="border px-2 py-1 w-full rounded">

                </div>

                <div>
                    <label class="block mb-1 font-semibold">IVA2 (%)</label>
                    <select name="iva2" 
                            x-model="form.iva2"
                            @change="calcularCI()"
                            class="border px-2 py-1 w-full rounded">
                        <option value="">-- IVA --</option>
                        <template x-for="i in listaIVA" :key="i.id">
                            <option :value="i.id" 
                                    x-text="(i.porcentaje * 100) + '%'"></option>
                        </template>
                    </select>


                </div>

                <div>
                    <label class="block mb-1 font-semibold">CI2</label>
                    <input name="ci2" type="text" 
                        x-model="form.ci2" 
                        readonly
                        class="border px-2 py-1 w-full rounded bg-gray-100 text-gray-600">

                </div>

                <div></div>
                <div></div>
            </div>

            <!-- FILA 4 -->
            <div class="grid grid-cols-5 gap-4 mb-4">
                <div>
                    <label class="block mb-1 font-semibold">BI3</label>
                    <input name="bi3" type="text" 
                        x-model="form.bi3" 
                        @input="calcularCI()"
                        class="border px-2 py-1 w-full rounded">

                </div>

                <div>
                    <label class="block mb-1 font-semibold">IVA3 (%)</label>
                    <select name="iva3" 
                        x-model="form.iva3"
                        @change="calcularCI()"
                        class="border px-2 py-1 w-full rounded">
                    <option value="">-- IVA --</option>
                    <template x-for="i in listaIVA" :key="i.id">
                        <option :value="i.id" 
                                x-text="(i.porcentaje * 100) + '%'"></option>
                    </template>
                </select>


                </div>

                <div>
                    <label class="block mb-1 font-semibold">CI3</label>
                    <input name="ci3" type="text" 
                        x-model="form.ci3" 
                        readonly
                        class="border px-2 py-1 w-full rounded bg-gray-100 text-gray-600">

                </div>

                <div></div>
                <div></div>
            </div>

            <!-- FILA 5 -->
            <div class="grid grid-cols-5 gap-4 mb-4">

                <div>
                    <label class="block mb-1 font-semibold">Retención (%)</label>
                    <input name="retencion" type="text"
                        x-model="form.retencion"
                        class="border px-2 py-1 w-full rounded">
                </div>

                <div>
                    <label class="block mb-1 font-semibold">Retención (tipo)</label>
                    <select name="retencion_id" x-model="form.retencion_id"
                        class="border px-2 py-1 w-full rounded">
                        <option value="">-- Seleccionar --</option>
                        <template x-for="r in listaRetenciones" :key="r.id">
                            <option :value="r.id" x-text="r.retencion + ' (' + r.ambito + ')'"></option>
                        </template>
                    </select>
                </div>

                <div>
                    <label class="block mb-1 font-semibold">Total Factura</label>
                    <input name="total_factura" type="text"
                        x-model="form.total_factura"
                        class="border px-2 py-1 w-full rounded">
                </div>

                <div></div>
                <div></div>
            </div>

            <!-- FILA 6 -->
            <div class="grid grid-cols-5 gap-4 mb-4">

                <div>
                    <label class="block mb-1 font-semibold">Método Pago</label>
                    <select name="pago_id" x-model="form.pago_id"
                        class="border px-2 py-1 w-full rounded">
                        <option value="">-- Seleccionar --</option>
                        <template x-for="p in listaPagos" :key="p.id">
                            <option :value="p.id" x-text="p.nombreMetodoPago"></option>
                        </template>
                    </select>
                </div>

                <div>
                    <label class="block mb-1 font-semibold">Fecha Vencimiento</label>
                    <input name="fecha_vencimiento" type="date"
                        x-model="form.fecha_vencimiento"
                        class="border px-2 py-1 w-full rounded">
                </div>

                <div>
                    <label class="block mb-1 font-semibold">Fecha Banco</label>
                    <input name="fecha_banco" type="date"
                        x-model="form.fecha_banco"
                        class="border px-2 py-1 w-full rounded">
                </div>

                <div>
                    <label class="block mb-1 font-semibold">Fecha-Hora (solo info)</label>
                    <div class="border px-2 py-1 w-full rounded bg-gray-100 text-gray-600">
                        <span x-text="form.fecha_hora || 'Se asigna automáticamente'"></span>
                    </div>
                </div>

                <div></div>
            </div>

            <!-- BOTONES -->
            <div class="flex justify-end mt-6">
                <button type="button" 
                    @click="closeModal()" 
                    class="mr-3 px-4 py-2 bg-gray-400 text-white rounded hover:bg-gray-500">
                    Cancelar
                </button>

                <button type="submit"
                    class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                    Guardar
                </button>
            </div>

        </form>
    </div>
</div>

<script>
function facturasCrud(listaProveedores, listaTipoGasto, listaRetenciones, listaPagos) {
    return {
        modal: false,
        editId: '',
        formTitle: '',
        listaProveedores: listaProveedores,
        listaTipoGasto: listaTipoGasto,
        listaRetenciones: listaRetenciones,
        listaPagos: listaPagos,
        

        form: {
            proveedor_id: '',
            tipoGasto_id: '',
            numero_factura: '',
            fecha_factura: '',
            mes: '',
            bi1: '',
            iva1: '',
            ci1: '',
            bi2: '',
            iva2: '',
            ci2: '',
            bi3: '',
            iva3: '',
            ci3: '',
            retencion: '',
            retencion_id: '',
            total_factura: '',
            pago_id: '',
            fecha_vencimiento: '',
            fecha_banco: '',
            fecha_hora: ''
        },

    calcularCI() {

    let getPorcentaje = (ivaId) => {
        if (!ivaId) return 0;
        let item = this.listaIVA.find(x => x.id == ivaId);
        return item ? parseFloat(item.porcentaje) : 0;
    };

    let bi1 = parseFloat(this.form.bi1) || 0;
    let iva1 = getPorcentaje(this.form.iva1);
    this.form.ci1 = (bi1 * iva1).toFixed(2);

    let bi2 = parseFloat(this.form.bi2) || 0;
    let iva2 = getPorcentaje(this.form.iva2);
    this.form.ci2 = (bi2 * iva2).toFixed(2);

    let bi3 = parseFloat(this.form.bi3) || 0;
    let iva3 = getPorcentaje(this.form.iva3);
    this.form.ci3 = (bi3 * iva3).toFixed(2);
}


    obtenerMesEspañol(fecha) {
            if (!fecha) return '';

            const meses = [
                'Enero','Febrero','Marzo','Abril','Mayo','Junio',
                'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'
            ];

            const f = new Date(fecha + "T00:00:00");
            let mes = f.getMonth(); // 0–11
            return meses[mes] ?? '';
    },


        init() {
            window.addEventListener('open-factura-modal', (e) => {
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

            // 🔥 Si hay una fecha, calcular el mes automáticamente (modo edición)
            if (this.form.fecha_factura) {
            this.form.mes = this.obtenerMesEspañol(this.form.fecha_factura);

            this.formTitle = id ? 'Editar Factura' : 'Añadir Factura';
}

        },

        closeModal() {
            this.modal = false;
            this.editId = '';
            Object.keys(this.form).forEach(k => this.form[k] = '');
            this.formTitle = '';
        },

        submitForm(event) {
            if (this.form.numero_factura.trim() === '' || this.form.proveedor_id === '') {
                alert('Rellena número de factura y proveedor');
                return;
            }
            event.target.submit();
        }
    }
}
</script>

<!-- FALLBACK JS -->
<script>
function findFacturaModal() {
    return document.querySelector('[x-show="modal"]');
}

function openFacturaFallback(data = null) {
    const modal = findFacturaModal();
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

function handleEditFactura(button) {
    try {
        const raw = button.getAttribute('data-row');
        const data = raw ? JSON.parse(raw) : null;
        window.dispatchEvent(new CustomEvent('open-factura-modal', { detail: data }));
        setTimeout(() => {
            const modal = findFacturaModal();
            if (!modal || modal.style.display === 'flex') return;
            openFacturaFallback(data);
        }, 80);
    } catch (e) {
        console.error('Error parseando data-row:', e);
        alert('Error al abrir modal.');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const btnAdd = document.getElementById('btnAddFactura');
    if (btnAdd) {
        btnAdd.addEventListener('click', function() {
            window.dispatchEvent(new CustomEvent('open-factura-modal', { detail: null }));
            setTimeout(() => {
                const modal = findFacturaModal();
                if (!modal || modal.style.display === 'flex') return;
                openFacturaFallback(null);
            }, 80);
        });
    }
});
</script>

</body>
</html>
