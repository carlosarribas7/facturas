<?php
// facturas.php - Versión final corregida (modal bloque corregido)
// Ajusta credenciales DB:
$DB_HOST = '127.0.0.1';
$DB_NAME = 'facturassalt';
$DB_USER = 'root';
$DB_PASS = '';
$DB_CHARSET = 'utf8mb4';

$dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset={$DB_CHARSET}";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];
try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (Exception $e) {
    http_response_code(500);
    echo "<h1>Error de conexión</h1><pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    exit;
}

function h($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
function json_response($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

// AJAX endpoints
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    if ($action === 'save') {
        $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
        $proveedor_id = isset($_POST['proveedor_id']) && $_POST['proveedor_id'] !== '' ? (int)$_POST['proveedor_id'] : null;
        $tipoGasto_id = isset($_POST['tipoGasto_id']) && $_POST['tipoGasto_id'] !== '' ? (int)$_POST['tipoGasto_id'] : null;
        $numero_factura = trim($_POST['numero_factura'] ?? '');
        $fecha_factura = !empty($_POST['fecha_factura']) ? $_POST['fecha_factura'] : null;
        $mes = trim($_POST['mes'] ?? '');
        $bi1 = $_POST['bi1'] !== '' ? (float)str_replace(',', '.', $_POST['bi1']) : null;
        $iva1 = $_POST['iva1'] !== '' ? (float)str_replace(',', '.', $_POST['iva1']) : null;
        $bi2 = $_POST['bi2'] !== '' ? (float)str_replace(',', '.', $_POST['bi2']) : null;
        $iva2 = $_POST['iva2'] !== '' ? (float)str_replace(',', '.', $_POST['iva2']) : null;
        $bi3 = $_POST['bi3'] !== '' ? (float)str_replace(',', '.', $_POST['bi3']) : null;
        $iva3 = $_POST['iva3'] !== '' ? (float)str_replace(',', '.', $_POST['iva3']) : null;
        $retencion = $_POST['retencion'] !== '' ? (float)str_replace(',', '.', $_POST['retencion']) : null;
        $retencion_id = isset($_POST['retencion_id']) && $_POST['retencion_id'] !== '' ? (int)$_POST['retencion_id'] : null;
        $pago_id = isset($_POST['pago_id']) && $_POST['pago_id'] !== '' ? (int)$_POST['pago_id'] : null;
        $fecha_vencimiento = !empty($_POST['fecha_vencimiento']) ? $_POST['fecha_vencimiento'] : null;
        $fecha_banco = !empty($_POST['fecha_banco']) ? $_POST['fecha_banco'] : null;

        if (empty($numero_factura) || empty($proveedor_id) || empty($tipoGasto_id)) {
            json_response(['status'=>'error','message'=>'Número factura, proveedor y tipo de gasto son obligatorios.'], 422);
        }

        $ci1 = ($bi1 !== null && $iva1 !== null) ? round($bi1 * ($iva1/100), 2) : null;
        $ci2 = ($bi2 !== null && $iva2 !== null) ? round($bi2 * ($iva2/100), 2) : null;
        $ci3 = ($bi3 !== null && $iva3 !== null) ? round($bi3 * ($iva3/100), 2) : null;
        $sumBi = 0; foreach ([$bi1,$bi2,$bi3] as $b) if ($b !== null) $sumBi += $b;
        $sumCi = 0; foreach ([$ci1,$ci2,$ci3] as $c) if ($c !== null) $sumCi += $c;
        $ret = $retencion !== null ? $retencion : 0.0;
        $total_factura = round($sumBi + $sumCi - $ret, 2);

        try {
            if ($id) {
                $sql = "UPDATE facturas SET proveedor_id=:proveedor_id, tipoGasto_id=:tipoGasto_id,
                        numero_factura=:numero_factura, fecha_factura=:fecha_factura, mes=:mes,
                        bi1=:bi1, iva1=:iva1, ci1=:ci1, bi2=:bi2, iva2=:iva2, ci2=:ci2,
                        bi3=:bi3, iva3=:iva3, ci3=:ci3, retencion=:retencion, retencion_id=:retencion_id,
                        total_factura=:total_factura, pago_id=:pago_id, fecha_vencimiento=:fecha_vencimiento,
                        fecha_banco=:fecha_banco, fecha_hora=NOW()
                        WHERE id=:id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':proveedor_id'=>$proveedor_id, ':tipoGasto_id'=>$tipoGasto_id, ':numero_factura'=>$numero_factura,
                    ':fecha_factura'=>$fecha_factura, ':mes'=>$mes,
                    ':bi1'=>$bi1, ':iva1'=>$iva1, ':ci1'=>$ci1,
                    ':bi2'=>$bi2, ':iva2'=>$iva2, ':ci2'=>$ci2,
                    ':bi3'=>$bi3, ':iva3'=>$iva3, ':ci3'=>$ci3,
                    ':retencion'=>$retencion, ':retencion_id'=>$retencion_id,
                    ':total_factura'=>$total_factura, ':pago_id'=>$pago_id,
                    ':fecha_vencimiento'=>$fecha_vencimiento, ':fecha_banco'=>$fecha_banco,
                    ':id'=>$id
                ]);
                $stmt = $pdo->prepare("SELECT f.*, pr.nombre AS proveedor_nombre FROM facturas f LEFT JOIN proveedores pr ON f.proveedor_id=pr.id WHERE f.id=:id");
                $stmt->execute([':id'=>$id]); $row = $stmt->fetch();
                json_response(['status'=>'ok','message'=>'Factura actualizada','data'=>$row]);
            } else {
                $sql = "INSERT INTO facturas (proveedor_id, tipoGasto_id, numero_factura, fecha_factura, mes,
                        bi1, iva1, ci1, bi2, iva2, ci2, bi3, iva3, ci3,
                        retencion, retencion_id, total_factura, pago_id, fecha_vencimiento, fecha_banco, fecha_hora)
                        VALUES (:proveedor_id, :tipoGasto_id, :numero_factura, :fecha_factura, :mes,
                                :bi1, :iva1, :ci1, :bi2, :iva2, :ci2, :bi3, :iva3, :ci3,
                                :retencion, :retencion_id, :total_factura, :pago_id, :fecha_vencimiento, :fecha_banco, NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':proveedor_id'=>$proveedor_id, ':tipoGasto_id'=>$tipoGasto_id, ':numero_factura'=>$numero_factura,
                    ':fecha_factura'=>$fecha_factura, ':mes'=>$mes,
                    ':bi1'=>$bi1, ':iva1'=>$iva1, ':ci1'=>$ci1,
                    ':bi2'=>$bi2, ':iva2'=>$iva2, ':ci2'=>$ci2,
                    ':bi3'=>$bi3, ':iva3'=>$iva3, ':ci3'=>$ci3,
                    ':retencion'=>$retencion, ':retencion_id'=>$retencion_id,
                    ':total_factura'=>$total_factura, ':pago_id'=>$pago_id,
                    ':fecha_vencimiento'=>$fecha_vencimiento, ':fecha_banco'=>$fecha_banco
                ]);
                $newId = (int)$pdo->lastInsertId();
                $stmt = $pdo->prepare("SELECT f.*, pr.nombre AS proveedor_nombre FROM facturas f LEFT JOIN proveedores pr ON f.proveedor_id=pr.id WHERE f.id=:id");
                $stmt->execute([':id'=>$newId]); $row = $stmt->fetch();
                json_response(['status'=>'ok','message'=>'Factura creada','data'=>$row]);
            }
        } catch (PDOException $e) {
            json_response(['status'=>'error','message'=>'Error BD: '.$e->getMessage()], 500);
        }
    } elseif ($action === 'delete') {
        $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
        if (!$id) json_response(['status'=>'error','message'=>'ID no recibido'], 422);
        try {
            $stmt = $pdo->prepare("DELETE FROM facturas WHERE id=:id");
            $stmt->execute([':id'=>$id]);
            json_response(['status'=>'ok','message'=>'Factura eliminada']);
        } catch (PDOException $e) {
            json_response(['status'=>'error','message'=>'No se puede eliminar: '.$e->getMessage()], 500);
        }
    } elseif ($action === 'get') {
        $id = !empty($_POST['id']) ? (int)$_POST['id'] : null;
        if (!$id) json_response(['status'=>'error','message'=>'ID no recibido'], 422);
        try {
            $stmt = $pdo->prepare("SELECT * FROM facturas WHERE id=:id");
            $stmt->execute([':id'=>$id]); $row = $stmt->fetch();
            if (!$row) json_response(['status'=>'error','message'=>'No encontrado'], 404);
            json_response(['status'=>'ok','data'=>$row]);
        } catch (PDOException $e) {
            json_response(['status'=>'error','message'=>$e->getMessage()], 500);
        }
    } else {
        json_response(['status'=>'error','message'=>'Acción desconocida'], 400);
    }
}

// datos iniciales
try { $listaProveedores = $pdo->query("SELECT id, nombre FROM proveedores ORDER BY nombre")->fetchAll(); } catch(Exception $e){ $listaProveedores = []; }
try { $listaTipoGasto = $pdo->query("SELECT id, cuenta, ce FROM tipo_gasto ORDER BY cuenta")->fetchAll(); } catch(Exception $e){ $listaTipoGasto = []; }
try { $listaRetenciones = $pdo->query("SELECT id, retencion, ambito FROM retenciones ORDER BY id")->fetchAll(); } catch(Exception $e){ $listaRetenciones = []; }
try { $listaPagos = $pdo->query("SELECT id, nombreMetodoPago FROM pagos ORDER BY id")->fetchAll(); } catch(Exception $e){ $listaPagos = []; }
try { $listaIVA = $pdo->query("SELECT id, porcentaje FROM iva ORDER BY porcentaje")->fetchAll(); } catch(Exception $e){ $listaIVA = []; }

$stmt = $pdo->prepare("SELECT f.*, pr.nombre AS proveedor_nombre, tg.cuenta AS tipo_gasto_cuenta FROM facturas f LEFT JOIN proveedores pr ON f.proveedor_id=pr.id LEFT JOIN tipo_gasto tg ON f.tipoGasto_id = tg.id ORDER BY f.id DESC LIMIT 0, 50");
$stmt->execute();
$rows = $stmt->fetchAll();

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Gestión de Facturas</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
  <style>
    .modal-backdrop { background: rgba(0,0,0,0.45); }
  </style>
</head>
<body class="bg-gray-100 p-6">
  <div class="max-w-7xl mx-auto">
    <header class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Gestión de Facturas</h1>
      <div>
        <button id="btnAdd" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Añadir factura</button>
      </div>
    </header>

    <?php if (session_status() === PHP_SESSION_NONE) session_start(); if (!empty($_SESSION['error_facturas'])): ?>
      <div class="mb-4 bg-red-100 border border-red-300 text-red-800 p-3 rounded">
        <?= h($_SESSION['error_facturas']); unset($_SESSION['error_facturas']); ?>
      </div>
    <?php endif; ?>

    <script id="init-data" type="application/json">
      <?= json_encode([
          'proveedores' => $listaProveedores,
          'tipoGasto'   => $listaTipoGasto,
          'retenciones' => $listaRetenciones,
          'pagos'       => $listaPagos,
          'iva'         => $listaIVA,
          'rows'        => $rows
      ], JSON_UNESCAPED_UNICODE) ?>
    </script>

    <div x-data="facturasApp()" x-init="init()" class="bg-white shadow rounded-lg overflow-hidden">
      <div class="p-4 border-b">
        <input x-model="q" @input.debounce.300="filter()" type="text" placeholder="Buscar por número de factura o proveedor..." class="px-3 py-2 border rounded w-80">
        <span class="ml-3 text-sm text-gray-500">Mostrando <strong x-text="filtered.length"></strong> registros</span>
      </div>

      <div class="p-4 overflow-auto">
        <table class="min-w-full table-fixed">
          <thead class="bg-gray-50">
            <tr>
              <th class="text-left px-3 py-2">ID</th>
              <th class="text-left px-3 py-2">Fecha</th>
              <th class="text-left px-3 py-2">Nº Factura</th>
              <th class="text-left px-3 py-2">Proveedor</th>
              <th class="text-left px-3 py-2">Base(s)</th>
              <th class="text-left px-3 py-2">IVA cuotas</th>
              <th class="text-left px-3 py-2">Retención</th>
              <th class="text-left px-3 py-2">Total</th>
              <th class="text-right px-3 py-2">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <template x-for="r in filtered" :key="r.id">
              <tr class="border-b">
                <td class="px-3 py-2 text-sm" x-text="r.id"></td>
                <td class="px-3 py-2 text-sm" x-text="r.fecha_factura"></td>
                <td class="px-3 py-2 text-sm" x-text="r.numero_factura"></td>
                <td class="px-3 py-2 text-sm" x-text="r.proveedor_nombre"></td>
                <td class="px-3 py-2 text-sm">
                  <div x-text="formatMoney(r.bi1)"></div>
                  <div x-text="formatMoney(r.bi2)" x-show="r.bi2"></div>
                  <div x-text="formatMoney(r.bi3)" x-show="r.bi3"></div>
                </td>
                <td class="px-3 py-2 text-sm">
                  <div x-text="formatMoney(r.ci1)"></div>
                  <div x-text="formatMoney(r.ci2)" x-show="r.ci2"></div>
                  <div x-text="formatMoney(r.ci3)" x-show="r.ci3"></div>
                </td>
                <td class="px-3 py-2 text-sm" x-text="formatMoney(r.retencion)"></td>
                <td class="px-3 py-2 text-sm" x-text="formatMoney(r.total_factura)"></td>
                <td class="px-3 py-2 text-right">
                  <button @click="openEdit(r)" class="px-2 py-1 bg-yellow-500 text-white rounded mr-2">Editar</button>
                  <button @click="confirmDelete(r.id)" class="px-2 py-1 bg-red-600 text-white rounded">Borrar</button>
                </td>
              </tr>
            </template>
            <tr x-show="filtered.length===0"><td colspan="9" class="px-3 py-4 text-sm text-gray-500">No hay registros.</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal component -->
    <div x-data="modalData()" x-cloak>
      <div x-show="modal" x-transition class="fixed inset-0 z-40 flex items-center justify-center modal-backdrop" style="display:none;">
        <div @click.away="close()" class="bg-white rounded-lg p-6 shadow-lg w-full max-w-4xl mx-4">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold" x-text="title"></h2>
            <button @click="close()" class="text-gray-500 hover:text-gray-700">&times;</button>
          </div>

          <form @submit.prevent="save" class="space-y-4">
            <input type="hidden" name="id" x-model="form.id">

            <div class="grid grid-cols-5 gap-3">
              <div>
                <label class="block text-sm font-medium">Proveedor *</label>
                <select x-model="form.proveedor_id" class="border rounded px-2 py-1 w-full">
                  <option value="">-- Seleccionar --</option>
                  <template x-for="p in init.proveedores" :key="p.id">
                    <option :value="p.id" x-text="p.nombre"></option>
                  </template>
                </select>
              </div>

              <div>
                <label class="block text-sm font-medium">Tipo Gasto *</label>
                <select x-model="form.tipoGasto_id" class="border rounded px-2 py-1 w-full">
                  <option value="">-- Seleccionar --</option>
                  <template x-for="t in init.tipoGasto" :key="t.id">
                    <option :value="t.id" x-text="t.cuenta"></option>
                  </template>
                </select>
              </div>

              <div>
                <label class="block text-sm font-medium">Nº Factura *</label>
                <input x-model="form.numero_factura" type="text" class="border rounded px-2 py-1 w-full">
              </div>

              <div>
                <label class="block text-sm font-medium">Fecha</label>
                <input x-model="form.fecha_factura" type="date" class="border rounded px-2 py-1 w-full" @change="updateMes()">
              </div>

              <div>
                <label class="block text-sm font-medium">Mes</label>
                <input x-model="form.mes" readonly class="border rounded px-2 py-1 w-full bg-gray-100 text-gray-600">
              </div>
            </div>

            <!-- BI/IVA/CI rows -->
            <div class="grid grid-cols-5 gap-3">
              <div>
                <label class="block text-sm font-medium">BI1</label>
                <input x-model="form.bi1" @input="calcAll()" type="text" class="border rounded px-2 py-1 w-full">
              </div>
              <div>
                <label class="block text-sm font-medium">IVA1 (%)</label>
                <select x-model="form.iva1" @change="calcAll()" class="border rounded px-2 py-1 w-full">
                  <option value="">-- IVA --</option>
                  <template x-for="i in init.iva" :key="i.id">
                    <option :value="i.porcentaje" x-text="(i.porcentaje*100) + '%'"></option>
                  </template>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium">CI1</label>
                <input x-model="form.ci1" readonly class="border rounded px-2 py-1 w-full bg-gray-100 text-gray-600">
              </div>
              <div></div><div></div>
            </div>

            <div class="grid grid-cols-5 gap-3">
              <div>
                <label class="block text-sm font-medium">BI2</label>
                <input x-model="form.bi2" @input="calcAll()" type="text" class="border rounded px-2 py-1 w-full">
              </div>
              <div>
                <label class="block text-sm font-medium">IVA2 (%)</label>
                <select x-model="form.iva2" @change="calcAll()" class="border rounded px-2 py-1 w-full">
                  <option value="">-- IVA --</option>
                  <template x-for="i in init.iva" :key="i.id">
                    <option :value="i.porcentaje" x-text="(i.porcentaje*100) + '%'"></option>
                  </template>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium">CI2</label>
                <input x-model="form.ci2" readonly class="border rounded px-2 py-1 w-full bg-gray-100 text-gray-600">
              </div>
              <div></div><div></div>
            </div>

            <div class="grid grid-cols-5 gap-3">
              <div>
                <label class="block text-sm font-medium">BI3</label>
                <input x-model="form.bi3" @input="calcAll()" type="text" class="border rounded px-2 py-1 w-full">
              </div>
              <div>
                <label class="block text-sm font-medium">IVA3 (%)</label>
                <select x-model="form.iva3" @change="calcAll()" class="border rounded px-2 py-1 w-full">
                  <option value="">-- IVA --</option>
                  <template x-for="i in init.iva" :key="i.id">
                    <option :value="i.porcentaje" x-text="(i.porcentaje*100) + '%'"></option>
                  </template>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium">CI3</label>
                <input x-model="form.ci3" readonly class="border rounded px-2 py-1 w-full bg-gray-100 text-gray-600">
              </div>
              <div></div><div></div>
            </div>

            <div class="grid grid-cols-5 gap-3">
              <div>
                <label class="block text-sm font-medium">Retención (€)</label>
                <input x-model="form.retencion" @input="calcAll()" type="text" class="border rounded px-2 py-1 w-full">
              </div>
              <div>
                <label class="block text-sm font-medium">Retención tipo</label>
                <select x-model="form.retencion_id" class="border rounded px-2 py-1 w-full">
                  <option value="">-- Seleccionar --</option>
                  <template x-for="r in init.retenciones" :key="r.id">
                    <option :value="r.id" x-text="r.ambito + (r.retencion ? (' ' + (r.retencion*100) + '%') : '')"></option>
                  </template>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium">Total Factura</label>
                <input x-model="form.total_factura" readonly class="border rounded px-2 py-1 w-full bg-gray-100 text-gray-600">
              </div>
              <div>
                <label class="block text-sm font-medium">Método Pago</label>
                <select x-model="form.pago_id" class="border rounded px-2 py-1 w-full">
                  <option value="">-- Seleccionar --</option>
                  <template x-for="p in init.pagos" :key="p.id">
                    <option :value="p.id" x-text="p.nombreMetodoPago"></option>
                  </template>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium">Fecha Vencimiento</label>
                <input x-model="form.fecha_vencimiento" type="date" class="border rounded px-2 py-1 w-full">
              </div>
            </div>

            <div class="flex justify-end pt-4">
              <button type="button" @click="close()" class="mr-3 px-4 py-2 bg-gray-300 rounded">Cancelar</button>
              <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded">Guardar</button>
            </div>
          </form>
        </div>
      </div>
    </div>

  </div>

<script>
function facturasApp() {
  return {
    initData: JSON.parse(document.getElementById('init-data').textContent || '{}'),
    init() {
      this.init = this.initData;
      this.initRows = this.init.rows || [];
      this.filtered = [...this.initRows];
      this.q = '';
    },
    q: '',
    initRows: [],
    filtered: [],
    init: {},
    formatMoney(v) {
      if (v === null || v === undefined || v === '') return '';
      v = parseFloat(v) || 0;
      return v.toLocaleString('es-ES', {minimumFractionDigits:2, maximumFractionDigits:2});
    },
    filter() {
      const q = (this.q || '').toLowerCase();
      this.filtered = this.initRows.filter(r => {
        return String(r.numero_factura || '').toLowerCase().includes(q) ||
               String(r.proveedor_nombre || '').toLowerCase().includes(q);
      });
    },
    openAdd() { window.dispatchEvent(new CustomEvent('open-factura-modal', { detail: null })); },
    openEdit(row) { window.dispatchEvent(new CustomEvent('open-factura-modal', { detail: row })); }
  }
}

function modalData() {
  const initJSON = JSON.parse(document.getElementById('init-data').textContent || '{}');
  return {
    modal: false,
    title: '',
    init: initJSON,
    form: {
      id: '',
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
    calcAll() {
      if (!this.form) return;
      const toNum = s => { s = (s||'').toString().replace(',','.'); return parseFloat(s) || 0; };
      const getPercent = p => { return p ? parseFloat(p) : 0; };
      const bi1 = toNum(this.form.bi1), iva1 = getPercent(this.form.iva1);
      this.form.ci1 = (bi1 * (iva1/100)).toFixed(2);
      const bi2 = toNum(this.form.bi2), iva2 = getPercent(this.form.iva2);
      this.form.ci2 = (bi2 * (iva2/100)).toFixed(2);
      const bi3 = toNum(this.form.bi3), iva3 = getPercent(this.form.iva3);
      this.form.ci3 = (bi3 * (iva3/100)).toFixed(2);
      const sumBi = bi1 + bi2 + bi3;
      const sumCi = parseFloat(this.form.ci1 || 0) + parseFloat(this.form.ci2 || 0) + parseFloat(this.form.ci3 || 0);
      const ret = toNum(this.form.retencion);
      this.form.total_factura = (sumBi + sumCi - ret).toFixed(2);
    },
    updateMes() {
      if (!this.form.fecha_factura) { this.form.mes = ''; return; }
      const d = new Date(this.form.fecha_factura + 'T00:00:00');
      const meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
      this.form.mes = meses[d.getMonth()] || '';
    },
    open(payload) {
      this.modal = true;
      if (payload && payload.id) {
        this.title = 'Editar Factura';
        Object.keys(this.form).forEach(k => {
          // map values from payload to form, using '' for null/undefined
          const v = (payload[k] !== undefined && payload[k] !== null) ? payload[k] : '';
          this.form[k] = v;
        });
        this.calcAll();
      } else {
        this.title = 'Añadir Factura';
        Object.keys(this.form).forEach(k => this.form[k] = '');
      }
    },
    close() { this.modal = false; },
    async save() {
      if (!this.form.numero_factura || !this.form.proveedor_id || !this.form.tipoGasto_id) {
        alert('Rellena número de factura, proveedor y tipo de gasto.');
        return;
      }
      this.calcAll();
      const payload = new FormData();
      payload.append('action','save');
      for (const k in this.form) payload.append(k, this.form[k] ?? '');
      try {
        const res = await fetch(location.href, { method:'POST', body: payload });
        const json = await res.json();
        if (json.status === 'ok') {
          location.reload();
        } else {
          alert('Error: ' + (json.message || 'Error desconocido'));
        }
      } catch (e) {
        alert('Error enviando datos: ' + e.message);
      }
    },
    confirmDelete(id) {
      if (!confirm('Eliminar factura #' + id + '?')) return;
      const fd = new FormData();
      fd.append('action','delete'); fd.append('id', id);
      fetch(location.href, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(json => {
          if (json.status === 'ok') location.reload();
          else alert('No se ha podido eliminar: ' + (json.message || 'error'));
        }).catch(e => alert('Error: ' + e.message));
    }
  }
}

document.addEventListener('DOMContentLoaded', function() {
  const btn = document.getElementById('btnAdd');
  if (btn) btn.addEventListener('click', () => {
    const modalEl = document.querySelector('[x-data="modalData()"]');
    if (modalEl && modalEl.__x) modalEl.__x.$data.open(null);
    else window.dispatchEvent(new CustomEvent('open-factura-modal', { detail: null }));
  });

  window.addEventListener('open-factura-modal', function(e) {
    const modalEl = document.querySelector('[x-data="modalData()"]');
    if (modalEl && modalEl.__x) modalEl.__x.$data.open(e.detail);
  });

  // wire edit buttons (delegate)
  document.querySelectorAll('[@click="openEdit(r)"], [data-open-edit]').forEach(btn => {
    btn.addEventListener('click', function() {
      try {
        const raw = btn.getAttribute('data-row');
        const data = raw ? JSON.parse(raw) : null;
        window.dispatchEvent(new CustomEvent('open-factura-modal', { detail: data }));
      } catch (e) { console.error(e); alert('Error al abrir modal'); }
    });
  });
});
</script>
</body>
</html>
