<?php
if(session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir el autoload de Composer si existe
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
} else {
    die("Por favor, instale las dependencias de Composer ejecutando 'composer require phpoffice/phpspreadsheet'");
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Conexión a la base de datos
$pdo = new PDO('mysql:host=localhost;dbname=facturassalt', 'root', '');
$pdo->exec("SET NAMES utf8mb4");

// Consulta para obtener todos los datos de facturas con las relaciones
$query = "
    SELECT 
        f.id,
        pr.nombre AS proveedor_nombre,
        tg.cuenta AS tipo_cuenta,
        tg.ce AS tipo_ce,
        f.numero_factura,
        f.fecha_factura,
        f.mes,
        f.bi1,
        f.iva1,
        f.ci1,
        f.bi2,
        f.iva2,
        f.ci2,
        f.bi3,
        f.iva3,
        f.ci3,
        r.retencion AS tipo_retencion,
        r.ambito AS ambito_retencion,
        f.retencion AS valor_retencion,
        f.total_factura,
        p.nombreMetodoPago AS metodo_pago,
        f.fecha_vencimiento,
        f.fecha_banco
    FROM facturas f
    LEFT JOIN proveedores pr ON f.proveedor_id = pr.id
    LEFT JOIN tipo_gasto tg ON f.tipoGasto_id = tg.id
    LEFT JOIN retenciones r ON f.retencion_id = r.id
    LEFT JOIN pagos p ON f.pago_id = p.id
    ORDER BY f.fecha_factura DESC, f.id DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute();
$facturas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Crear un nuevo objeto Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Establecer estilos para el encabezado
$headerStyle = [
    'font' => ['bold' => true],
    'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
    'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E0E0E0']]
];

// Encabezados de las columnas
$headers = [
    'ID', 'Proveedor', 'Tipo de Cuenta', 'CE', 'Número Factura', 'Fecha Factura', 'Mes',
    'Base Imponible 1', 'IVA 1', 'Cuota IVA 1',
    'Base Imponible 2', 'IVA 2', 'Cuota IVA 2',
    'Base Imponible 3', 'IVA 3', 'Cuota IVA 3',
    'Tipo Retención', 'Ámbito Retención', 'Valor Retención',
    'Total Factura', 'Método de Pago', 'Fecha Vencimiento', 'Fecha Banco'
];

// Agregar encabezados
$sheet->fromArray($headers, NULL, 'A1');
$sheet->getStyle('A1:W1')->applyFromArray($headerStyle);

// Llenar datos
$row = 2;
foreach ($facturas as $factura) {
    $sheet->fromArray([
        $factura['id'],
        $factura['proveedor_nombre'],
        $factura['tipo_cuenta'],
        $factura['tipo_ce'],
        $factura['numero_factura'],
        $factura['fecha_factura'],
        $factura['mes'],
        $factura['bi1'],
        $factura['iva1'],
        $factura['ci1'],
        $factura['bi2'],
        $factura['iva2'],
        $factura['ci2'],
        $factura['bi3'],
        $factura['iva3'],
        $factura['ci3'],
        $factura['tipo_retencion'],
        $factura['ambito_retencion'],
        $factura['valor_retencion'],
        $factura['total_factura'],
        $factura['metodo_pago'],
        $factura['fecha_vencimiento'],
        $factura['fecha_banco']
    ], NULL, 'A' . $row);
    
    // Ajustar el ancho de las columnas automáticamente
    if ($row === 2) { // Solo la primera vez
        foreach (range('A', 'W') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }
    
    $row++;
}

// Crear el escritor y guardar el archivo Excel
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="facturas_' . date('Y-m-d') . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
