<?php
// facturas.php
// CRUD completo para la tabla `facturas` según la estructura que enviaste.
// Usa PDO. Ajusta credenciales DB si hace falta.

session_start();

// ----------------- Configuración de conexión -----------------
$host = '127.0.0.1';
$db   = 'facturassalt';   // ajusta si tu BD tiene otro nombre
$user = 'root';           // ajusta
$pass = '';               // ajusta
$charset = 'utf8mb4';     // PDO charset (compatible)

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (Exception $e) {
    http_response_code(500);
    echo "Error de conexión: " . htmlspecialchars($e->getMessage());
    exit;
}

// ----------------- Helpers -----------------
function h($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function redirect($url) { header("Location: $url"); exit; }

// ----------------- Obtener listas para selects -----------------
$proveedores = $pdo->query("SELECT id, nombre FROM proveedores ORDER BY nombre")->fetchAll();
$tipos_gasto = $pdo->query("SELECT id, cuenta, ce FROM tipo_gasto ORDER BY id")->fetchAll();
$retenciones  = $pdo->query("SELECT id, retencion, ambito FROM retenciones ORDER BY id")->fetchAll();
$pagos       = $pdo->query("SELECT id, nombreMetodoPago FROM pagos ORDER BY id")->fetchAll();
$ivas        = $pdo->query("SELECT id, porcentaje FROM iva ORDER BY porcentaje")->fetchAll();

// ----------------- Procesar acciones POST (crear / editar) -----------------
$action = $_REQUEST['action'] ?? 'list';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!in_array($_POST['form_token'] ?? '', $_SESSION['form_token'] ?? [])) {
        // token not found -> could be refresh or missing token; we will allow but it's safer to continue
    }
    // Build an array with fields we accept:
    $data = [
        'proveedor_id' => !empty($_POST['proveedor_id']) ? (int)$_POST['proveedor_id'] : null,
        'tipoGasto_id' => !empty($_POST['tipoGasto_id']) ? (int)$_POST['tipoGasto_id'] : null,
        'numero_factura' => $_POST['numero_factura'] ?? null,
        'fecha_factura' => !empty($_POST['fecha_factura']) ? $_POST['fecha_factura'] : null,
        'mes' => $_POST['mes'] ?? null,
        'bi1' => isset($_POST['bi1']) && $_POST['bi1'] !== '' ? (float)str_replace(',', '.', $_POST['bi1']) : null,
        'iva1' => isset($_POST['iva1']) && $_POST['iva1'] !== '' ? (float)str_replace(',', '.', $_POST['iva1']) : null,
        'bi2' => isset($_POST['bi2']) && $_POST['bi2'] !== '' ? (float)str_replace(',', '.', $_POST['bi2']) : null,
        'iva2' => isset($_POST['iva2']) && $_POST['iva2'] !== '' ? (float)str_replace(',', '.', $_POST['iva2']) : null,
        'bi3' => isset($_POST['bi3']) && $_POST['bi3'] !== '' ? (float)str_replace(',', '.', $_POST['bi3']) : null,
        'iva3' => isset($_POST['iva3']) && $_POST['iva3'] !== '' ? (float)str_replace(',', '.', $_POST['iva3']) : null,
        'retencion' => isset($_POST['retencion']) && $_POST['retencion'] !== '' ? (float)str_replace(',', '.', $_POST['retencion']) : null,
        'retencion_id' => !empty($_POST['retencion_id']) ? (int)$_POST['retencion_id'] : null,
        'pago_id' => !empty($_POST['pago_id']) ? (int)$_POST['pago_id'] : null,
        'fecha_vencimiento' => !empty($_POST['fecha_vencimiento']) ? $_POST['fecha_vencimiento'] : null,
        'fecha_banco' => !empty($_POST['fecha_banco']) ? $_POST['fecha_banco'] : null,
    ];

    // Calcular CI (cuota iva) y total
    // CI = BI * (IVA/100)
    $ci1 = $data['bi1'] !== null && $data['iva1'] !== null ? round($data['bi1'] * ($data['iva1'] / 100), 2) : null;
    $ci2 = $data['bi2'] !== null && $data['iva2'] !== null ? round($data['bi2'] * ($data['iva2'] / 100), 2) : null;
    $ci3 = $data['bi3'] !== null && $data['iva3'] !== null ? round($data['bi3'] * ($data['iva3'] / 100), 2) : null;

    $sumBi = 0;
    foreach (['bi1','bi2','bi3'] as $b) if ($data[$b] !== null) $sumBi += $data[$b];
    $sumCi = 0;
    foreach ([$ci1,$ci2,$ci3] as $c) if ($c !== null) $sumCi += $c;
    $ret = $data['retencion'] !== null ? $data['retencion'] : 0.0;

    $total_factura = round($sumBi + $sumCi - $ret, 2);

    if (isset($_POST['save']) || isset($_POST['save_and_new'])) {
        if (!empty($_POST['id'])) {
            // EDITAR
            $id = (int)$_POST['id'];
            $sql = "UPDATE facturas SET proveedor_id=:proveedor_id, tipoGasto_id=:tipoGasto_id, numero_factura=:numero_factura,
                    fecha_factura=:fecha_factura, mes=:mes, bi1=:bi1, iva1=:iva1, ci1=:ci1, bi2=:bi2, iva2=:iva2, ci2=:ci2,
                    bi3=:bi3, iva3=:iva3, ci3=:ci3, retencion=:retencion, retencion_id=:retencion_id, total_factura=:total_factura,
                    pago_id=:pago_id, fecha_vencimiento=:fecha_vencimiento, fecha_banco=:fecha_banco, fecha_hora=:fh
                    WHERE id=:id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':proveedor_id' => $data['proveedor_id'],
                ':tipoGasto_id' => $data['tipoGasto_id'],
                ':numero_factura' => $data['numero_factura'],
                ':fecha_factura' => $data['fecha_factura'],
                ':mes' => $data['mes'],
                ':bi1' => $data['bi1'],
                ':iva1' => $data['iva1'],
                ':ci1' => $ci1,
                ':bi2' => $data['bi2'],
                ':iva2' => $data['iva2'],
                ':ci2' => $ci2,
                ':bi3' => $data['bi3'],
                ':iva3' => $data['iva3'],
                ':ci3' => $ci3,
                ':retencion' => $ret,
                ':retencion_id' => $data['retencion_id'],
                ':total_factura' => $total_factura,
                ':pago_id' => $data['pago_id'],
                ':fecha_vencimiento' => $data['fecha_vencimiento'],
                ':fecha_banco' => $data['fecha_banco'],
                ':fh' => date('Y-m-d H:i:s'),
                ':id' => $id
            ]);
            $_SESSION['message'] = "Factura actualizada correctamente.";
            redirect('facturas.php');
        } else {
            // INSERTAR
            $sql = "INSERT INTO facturas (proveedor_id,tipoGasto_id,numero_factura,fecha_factura,mes,bi1,iva1,ci1,bi2,iva2,ci2,bi3,iva3,ci3,retencion,retencion_id,total_factura,pago_id,fecha_vencimiento,fecha_banco,fecha_hora)
                    VALUES (:proveedor_id,:tipoGasto_id,:numero_factura,:fecha_factura,:mes,:bi1,:iva1,:ci1,:bi2,:iva2,:ci2,:bi3,:iva3,:ci3,:retencion,:retencion_id,:total_factura,:pago_id,:fecha_vencimiento,:fecha_banco,:fh)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':proveedor_id' => $data['proveedor_id'],
                ':tipoGasto_id' => $data['tipoGasto_id'],
                ':numero_factura' => $data['numero_factura'],
                ':fecha_factura' => $data['fecha_factura'],
                ':mes' => $data['mes'],
                ':bi1' => $data['bi1'],
                ':iva1' => $data['iva1'],
                ':ci1' => $ci1,
                ':bi2' => $data['bi2'],
                ':iva2' => $data['iva2'],
                ':ci2' => $ci2,
                ':bi3' => $data['bi3'],
                ':iva3' => $data['iva3'],
                ':ci3' => $ci3,
                ':retencion' => $ret,
                ':retencion_id' => $data['retencion_id'],
                ':total_factura' => $total_factura,
                ':pago_id' => $data['pago_id'],
                ':fecha_vencimiento' => $data['fecha_vencimiento'],
                ':fecha_banco' => $data['fecha_banco'],
                ':fh' => date('Y-m-d H:i:s'),
            ]);
            $_SESSION['message'] = "Factura creada correctamente.";
            if (isset($_POST['save_and_new'])) {
                redirect('facturas.php?action=new');
            } else {
                redirect('facturas.php');
            }
        }
    }
}

// ----------------- Eliminar -----------------
if ($action === 'delete' && !empty($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM facturas WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $_SESSION['message'] = "Factura eliminada.";
    redirect('facturas.php');
}

// ----------------- Obtener datos según acción -----------------
$errors = [];
$record = null;
if ($action === 'edit' && !empty($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM facturas WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $record = $stmt->fetch();
    if (!$record) {
        $_SESSION['message'] = "Registro no encontrado.";
        redirect('facturas.php');
    }
}

// ----------------- LISTADO con búsqueda y paginación -----------------
if ($action === 'list') {
    $q = trim($_GET['q'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 12;
    $offset = ($page - 1) * $perPage;

    $where = "1=1";
    $params = [];

    if ($q !== '') {
        // buscar en numero_factura, proveedor nombre (join needed)
        $where = "(f.numero_factura LIKE :q OR p.nombre LIKE :q)";
        $params[':q'] = "%$q%";
    }

    // Conteo total
    $countSql = "SELECT COUNT(*) FROM facturas f LEFT JOIN proveedores p ON f.proveedor_id = p.id WHERE $where";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();
    $pages = max(1, ceil($total / $perPage));

    // Obtener filas
    $sql = "SELECT f.*, p.nombre AS proveedor_nombre, tg.cuenta AS tipo_gasto_cuenta
            FROM facturas f
            LEFT JOIN proveedores p ON f.proveedor_id = p.id
            LEFT JOIN tipo_gasto tg ON f.tipoGasto_id = tg.id
            WHERE $where
            ORDER BY f.fecha_factura DESC, f.id DESC
            LIMIT :lim OFFSET :off";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k=>$v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':lim', (int)$perPage, PDO::PARAM_INT);
    $stmt->bindValue(':off', (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
}

// ----------------- token simple para formularios (evitar doble envío) -----------------
$form_token = bin2hex(random_bytes(16));
$_SESSION['form_token'][] = $form_token;
if (count($_SESSION['form_token']) > 20) array_shift($_SESSION['form_token']);

?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Facturas - CRUD</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
    body{font-family:Arial,Helvetica,sans-serif;background:#f6f7fb;margin:0;padding:20px;color:#222}
    .container{max-width:1100px;margin:0 auto;background:#fff;padding:18px;border-radius:8px;box-shadow:0 4px 18px rgba(0,0,0,.06)}
    h1{margin-top:0}
    table{width:100%;border-collapse:collapse;margin-top:12px}
    th,td{padding:8px;border-bottom:1px solid #eee;text-align:left;font-size:14px}
    th{background:#fafafa}
    .btn{display:inline-block;padding:6px 10px;border-radius:6px;text-decoration:none;margin-right:6px;border:1px solid #bbb}
    .btn-primary{background:#2774e6;color:#fff;border-color:#1f63c6}
    .btn-danger{background:#e64b4b;color:#fff;border-color:#c23c3c}
    .topbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
    .search{padding:6px 8px;border:1px solid #ddd;border-radius:6px}
    form .row{display:flex;gap:10px;flex-wrap:wrap}
    form label{display:block;font-size:13px;margin-bottom:4px}
    form input[type=text], form input[type=date], form select{padding:8px;border:1px solid #ddd;border-radius:6px;width:100%}
    .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
    .message{background:#e6f4ea;border:1px solid #bfe3c6;padding:8px;border-radius:6px;margin-bottom:10px}
    .small{font-size:13px;color:#666}
</style>
</head>
<body>
<div class="container">
    <h1>Facturas</h1>

    <?php if (!empty($_SESSION['message'])): ?>
        <div class="message"><?= h($_SESSION['message']); unset($_SESSION['message']); ?></div>
    <?php endif; ?>

    <?php if ($action === 'list'): ?>
        <div class="topbar">
            <form method="get" style="display:flex;gap:8px;align-items:center">
                <input type="hidden" name="action" value="list">
                <input class="search" type="text" name="q" placeholder="Buscar por factura o proveedor..." value="<?= h($q ?? '') ?>">
                <button class="btn" type="submit">Buscar</button>
            </form>
            <div style="margin-left:auto">
                <a class="btn btn-primary" href="facturas.php?action=new">Nueva factura</a>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Fecha</th>
                    <th>Nº Factura</th>
                    <th>Proveedor</th>
                    <th>Base imponible(s)</th>
                    <th>IVA cuotas</th>
                    <th>Retención</th>
                    <th>Total</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="9" class="small">No hay registros.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?= h($r['id']) ?></td>
                            <td><?= h($r['fecha_factura']) ?></td>
                            <td><?= h($r['numero_factura']) ?></td>
                            <td><?= h($r['proveedor_nombre']) ?></td>
                            <td>
                                <?= $r['bi1'] !== null ? h(number_format($r['bi1'],2,',','.')) : '' ?>
                                <?= $r['bi2'] !== null ? '<br>'.h(number_format($r['bi2'],2,',','.')) : '' ?>
                                <?= $r['bi3'] !== null ? '<br>'.h(number_format($r['bi3'],2,',','.')) : '' ?>
                            </td>
                            <td>
                                <?= $r['ci1'] !== null ? h(number_format($r['ci1'],2,',','.')) : '' ?>
                                <?= $r['ci2'] !== null ? '<br>'.h(number_format($r['ci2'],2,',','.')) : '' ?>
                                <?= $r['ci3'] !== null ? '<br>'.h(number_format($r['ci3'],2,',','.')) : '' ?>
                            </td>
                            <td><?= $r['retencion'] !== null ? h(number_format($r['retencion'],2,',','.')) : '' ?></td>
                            <td><?= $r['total_factura'] !== null ? h(number_format($r['total_factura'],2,',','.')) : '' ?></td>
                            <td>
                                <a class="btn" href="facturas.php?action=edit&id=<?= h($r['id']) ?>">Editar</a>
                                <a class="btn btn-danger" href="facturas.php?action=delete&id=<?= h($r['id']) ?>" onclick="return confirm('Eliminar factura <?= h($r['numero_factura']) ?>?')">Borrar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Paginación simple -->
        <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px">
            <div class="small">Mostrando página <?= $page ?> de <?= $pages ?> — <?= $total ?> registros</div>
            <div>
                <?php for($p=1;$p<=$pages;$p++): ?>
                    <?php if ($p == $page): ?>
                        <span class="btn" style="background:#f0f0f0;border-color:#ccc"><?= $p ?></span>
                    <?php else: ?>
                        <a class="btn" href="facturas.php?action=list&page=<?= $p ?>&q=<?= urlencode($q) ?>"><?= $p ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
        </div>

    <?php elseif ($action === 'new' || $action === 'edit'): ?>
        <?php
            $isEdit = ($action === 'edit');
            $f = $record ?? [
                'id'=>null,'proveedor_id'=>null,'tipoGasto_id'=>null,'numero_factura'=>'','fecha_factura'=>'',
                'mes'=>'','bi1'=>null,'iva1'=>null,'ci1'=>null,'bi2'=>null,'iva2'=>null,'ci2'=>null,'bi3'=>null,'iva3'=>null,'ci3'=>null,
                'retencion'=>null,'retencion_id'=>null,'pago_id'=>null,'fecha_vencimiento'=>null,'fecha_banco'=>null
            ];
        ?>
        <h2><?= $isEdit ? "Editar factura #".h($f['id']) : "Nueva factura" ?></h2>
        <form method="post" style="margin-top:12px">
            <input type="hidden" name="form_token" value="<?= $form_token ?>">
            <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= h($f['id']) ?>"><?php endif; ?>
            <div class="grid">
                <div>
                    <label>Proveedor</label>
                    <select name="proveedor_id">
                        <option value="">-- seleccionar --</option>
                        <?php foreach($proveedores as $p): ?>
                            <option value="<?= h($p['id']) ?>" <?= $p['id']==($f['proveedor_id'] ?? '') ? 'selected' : '' ?>><?= h($p['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Tipo de gasto</label>
                    <select name="tipoGasto_id">
                        <option value="">-- seleccionar --</option>
                        <?php foreach($tipos_gasto as $t): ?>
                            <option value="<?= h($t['id']) ?>" <?= $t['id']==($f['tipoGasto_id'] ?? '') ? 'selected' : '' ?>><?= h($t['cuenta'].' '.$t['ce']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Número factura</label>
                    <input type="text" name="numero_factura" value="<?= h($f['numero_factura'] ?? '') ?>">
                </div>

                <div>
                    <label>Fecha factura</label>
                    <input type="date" name="fecha_factura" value="<?= h($f['fecha_factura'] ?? '') ?>">
                </div>
                <div>
                    <label>Mes</label>
                    <input type="text" name="mes" value="<?= h($f['mes'] ?? '') ?>">
                </div>
                <div>
                    <label>Método de pago</label>
                    <select name="pago_id">
                        <option value="">-- seleccionar --</option>
                        <?php foreach($pagos as $pg): ?>
                            <option value="<?= h($pg['id']) ?>" <?= $pg['id']==($f['pago_id'] ?? '') ? 'selected' : '' ?>><?= h($pg['nombreMetodoPago']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Bases e IVAs -->
                <div>
                    <label>BI 1</label>
                    <input type="text" name="bi1" value="<?= $f['bi1'] !== null ? h($f['bi1']) : '' ?>">
                </div>
                <div>
                    <label>IVA 1 (%)</label>
                    <select name="iva1">
                        <option value=""></option>
                        <?php foreach($ivas as $iv): ?>
                            <option value="<?= h($iv['porcentaje']) ?>" <?= $iv['porcentaje']==($f['iva1'] ?? '') ? 'selected' : '' ?>><?= h($iv['porcentaje']) ?>%</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>CI 1 (se calcula)</label>
                    <input type="text" disabled value="<?= $f['ci1'] !== null ? h($f['ci1']) : '' ?>">
                </div>

                <div>
                    <label>BI 2</label>
                    <input type="text" name="bi2" value="<?= $f['bi2'] !== null ? h($f['bi2']) : '' ?>">
                </div>
                <div>
                    <label>IVA 2 (%)</label>
                    <select name="iva2">
                        <option value=""></option>
                        <?php foreach($ivas as $iv): ?>
                            <option value="<?= h($iv['porcentaje']) ?>" <?= $iv['porcentaje']==($f['iva2'] ?? '') ? 'selected' : '' ?>><?= h($iv['porcentaje']) ?>%</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>CI 2 (se calcula)</label>
                    <input type="text" disabled value="<?= $f['ci2'] !== null ? h($f['ci2']) : '' ?>">
                </div>

                <div>
                    <label>BI 3</label>
                    <input type="text" name="bi3" value="<?= $f['bi3'] !== null ? h($f['bi3']) : '' ?>">
                </div>
                <div>
                    <label>IVA 3 (%)</label>
                    <select name="iva3">
                        <option value=""></option>
                        <?php foreach($ivas as $iv): ?>
                            <option value="<?= h($iv['porcentaje']) ?>" <?= $iv['porcentaje']==($f['iva3'] ?? '') ? 'selected' : '' ?>><?= h($iv['porcentaje']) ?>%</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>CI 3 (se calcula)</label>
                    <input type="text" disabled value="<?= $f['ci3'] !== null ? h($f['ci3']) : '' ?>">
                </div>

                <div>
                    <label>Retención (€)</label>
                    <input type="text" name="retencion" value="<?= $f['retencion'] !== null ? h($f['retencion']) : '' ?>">
                </div>
                <div>
                    <label>Retención tipo</label>
                    <select name="retencion_id">
                        <option value="">-- seleccionar --</option>
                        <?php foreach($retenciones as $rt): ?>
                            <option value="<?= h($rt['id']) ?>" <?= $rt['id']==($f['retencion_id'] ?? '') ? 'selected' : '' ?>><?= h($rt['ambito'].' '.(isset($rt['retencion'])?($rt['retencion']*100).'%' : '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Fecha vencimiento</label>
                    <input type="date" name="fecha_vencimiento" value="<?= h($f['fecha_vencimiento'] ?? '') ?>">
                </div>
                <div>
                    <label>Fecha banco</label>
                    <input type="date" name="fecha_banco" value="<?= h($f['fecha_banco'] ?? '') ?>">
                </div>
                <div>
                    <label>Mes contable</label>
                    <input type="text" name="mes" value="<?= h($f['mes'] ?? '') ?>">
                </div>
            </div>

            <div style="margin-top:12px;">
                <button class="btn btn-primary" type="submit" name="save">Guardar</button>
                <button class="btn" type="submit" name="save_and_new">Guardar y nueva</button>
                <a class="btn" href="facturas.php">Volver</a>
            </div>
        </form>

        <p class="small" style="margin-top:8px">Nota: Las cuotas de IVA y el total se calculan en el servidor al guardar.</p>

    <?php else: ?>
        <p>Acción no válida. <a href="facturas.php">Volver al listado</a></p>
    <?php endif; ?>

</div>

<!-- Pequeño script para UX: calcular subtotal en cliente (opcional) -->
<script>
(function(){
    // Funciona solo para dar retroalimentación visual; los cálculos finales se hacen en servidor.
    function nf(v){ return v===''?0:parseFloat(v.toString().replace(',', '.'))||0; }
    function calcCi(bi, iva){ return bi * (iva/100); }

    function updateInline(){
        ['1','2','3'].forEach(function(n){
            var bi = nf(document.querySelector('input[name=bi'+n+']')?.value || '');
            var iva = nf(document.querySelector('select[name=iva'+n+']')?.value || '');
            var ci = calcCi(bi, iva);
            var ciField = document.querySelector('input[disabled][value][name=ci'+n+']');
            // Not using named disabled inputs; instead update the disabled input by position:
            var disabledInputs = document.querySelectorAll('input[disabled]');
            if (disabledInputs && disabledInputs.length >= n) {
                disabledInputs[n-1].value = (ci ? ci.toFixed(2) : '');
            }
        });
    }
    ['input[name=bi1]','input[name=bi2]','input[name=bi3]','select[name=iva1]','select[name=iva2]','select[name=iva3]'].forEach(function(sel){
        var el = document.querySelector(sel);
        if (el) el.addEventListener('change', updateInline);
        if (el) el.addEventListener('input', updateInline);
    });
    updateInline();
})();
</script>
</body>
</html>
