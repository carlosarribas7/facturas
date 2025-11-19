<?php
// Conexión PDO
$pdo = new PDO('mysql:host=localhost;dbname=facturassalt', 'root', '');
$pdo->exec("SET NAMES utf8mb4");


// Seleccionar la tabla en modo seguro (default: iva)
$aux = in_array($_GET['aux'] ?? 'iva', ['iva', 'retenciones']) ? $_GET['aux'] : 'iva';

// Procesa CRUD auxiliar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Normalizar separador decimal (coma -> punto) para evitar valores inválidos
    if (isset($_POST['porcentaje'])) {
        $_POST['porcentaje'] = str_replace(',', '.', $_POST['porcentaje']);
    }
    if (isset($_POST['retencion'])) {
        $_POST['retencion'] = str_replace(',', '.', $_POST['retencion']);
    }

    // Validación en servidor y preservación de valores en caso de error (errores por campo)
    $errors = [];
    $oldId = $_POST['id'] ?? '';
    if ($aux === 'iva') {
        $oldPorcentaje = $_POST['porcentaje'] ?? '';
        if ($oldPorcentaje === '' || !is_numeric($oldPorcentaje)) {
            $errors['porcentaje'] = 'Porcentaje inválido. Introduce un número válido.';
        } elseif ((float)$oldPorcentaje < 0 || (float)$oldPorcentaje > 100) {
            $errors['porcentaje'] = 'Porcentaje debe estar entre 0 y 100.';
        } elseif (substr_count($oldPorcentaje, '.') === 1 && strlen(substr($oldPorcentaje, strpos($oldPorcentaje, '.') + 1)) > 2) {
            $errors['porcentaje'] = 'Máximo 2 decimales permitidos.';
        }
        if (empty($errors)) {
            if (isset($_POST['id']) && $_POST['id'] !== '') {
                $stmt = $pdo->prepare("UPDATE iva SET porcentaje=? WHERE id=?");
                $stmt->execute([$_POST['porcentaje'], $_POST['id']]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO iva (porcentaje) VALUES (?)");
                $stmt->execute([$_POST['porcentaje']]);
            }
        }
    } else {
        $oldRetencion = $_POST['retencion'] ?? '';
        $oldAmbito = $_POST['ambito'] ?? '';
        if ($oldRetencion === '' || !is_numeric($oldRetencion)) {
            $errors['retencion'] = 'Retención inválida. Introduce un número válido.';
        } elseif ((float)$oldRetencion < 0 || (float)$oldRetencion > 100) {
            $errors['retencion'] = 'Retención debe estar entre 0 y 100.';
        } elseif (substr_count($oldRetencion, '.') === 1 && strlen(substr($oldRetencion, strpos($oldRetencion, '.') + 1)) > 2) {
            $errors['retencion'] = 'Máximo 2 decimales permitidos.';
        }
        if ($oldAmbito === '') {
            $errors['ambito'] = 'Ámbito vacío.';
        }
        if (empty($errors)) {
            if (isset($_POST['id']) && $_POST['id'] !== '') {
                $stmt = $pdo->prepare("UPDATE retenciones SET retencion=?, ambito=? WHERE id=?");
                $stmt->execute([$_POST['retencion'], $_POST['ambito'], $_POST['id']]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO retenciones (retencion, ambito) VALUES (?, ?)");
                $stmt->execute([$_POST['retencion'], $_POST['ambito']]);
            }
        }
    }

    // Si no hay errores, redirigimos; si hay, dejamos la página renderizar y mostramos mensajes
    if (empty($errors)) {
        header('Location: ' . $_SERVER['PHP_SELF'] . '?aux=' . $aux);
        exit;
    }
}
if (isset($_GET['eliminar'])) {
    $id = (int)$_GET['eliminar'];
    if ($aux === 'iva') {
        $pdo->prepare("DELETE FROM iva WHERE id=?")->execute([$id]);
    } else {
        $pdo->prepare("DELETE FROM retenciones WHERE id=?")->execute([$id]);
    }
    header('Location: ' . $_SERVER['PHP_SELF'] . '?aux=' . $aux);
    exit;
}

// Consulta para cargar tabla actual
if ($aux === 'iva') {
    $rows = $pdo->query("SELECT * FROM iva ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $rows = $pdo->query("SELECT * FROM retenciones ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Auxiliares</title>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 p-7" x-data="auxCrud('<?= $aux ?>')">

    <form method="get" class="mb-6">
        <label class="font-bold mr-2 text-lg">Ver tabla:</label>
        <select name="aux" onchange="this.form.submit()" class="border px-2 py-1 rounded">
            <option value="iva" <?= $aux === 'iva' ? 'selected' : '' ?>>IVA</option>
            <option value="retenciones" <?= $aux === 'retenciones' ? 'selected' : '' ?>>Retenciones</option>
        </select>
    </form>

    <?php if (!empty($errors) && isset($errors['general'])): ?>
        <div class="mb-4 p-3 bg-red-100 text-red-700 rounded"><?= htmlspecialchars($errors['general']) ?></div>
    <?php endif; ?>

    <h1 class="text-3xl font-bold mb-6 text-gray-800">
        <?= $aux === 'iva' ? 'Gestión de IVA' : 'Gestión de Retenciones' ?>
    </h1>

    <button 
        @click="openModal()"
        x-text="'Añadir ' + (aux === 'iva' ? 'IVA' : 'Retención')"
        class="mb-4 bg-green-600 text-white font-semibold px-4 py-2 rounded hover:bg-green-700">Añadir</button>

    <div class="bg-white shadow rounded overflow-x-auto">
        <table class="min-w-full border text-center">
            <thead class="bg-gray-200">
                <tr>
                    <th class="px-3 py-2 border">ID</th>
                    <?php if ($aux === 'iva'): ?>
                        <th class="px-3 py-2 border">Porcentaje</th>
                    <?php else: ?>
                        <th class="px-3 py-2 border">Retención</th>
                        <th class="px-3 py-2 border">Ámbito</th>
                    <?php endif ?>
                    <th class="px-3 py-2 border">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td class="border px-3 py-2"><?= $row['id'] ?></td>
                    <?php if ($aux === 'iva'): ?>
                        <td class="border px-3 py-2"><?= number_format($row['porcentaje'], 2) ?></td>
                        <td class="border px-3 py-2">
                            <button 
                                @click="openModal('<?= $row['id'] ?>', '<?= $row['porcentaje'] ?>')" 
                                class="bg-blue-500 text-white px-2 py-1 rounded hover:bg-blue-700">Editar</button>
                            <a href="?aux=iva&eliminar=<?= $row['id'] ?>" 
                                class="bg-red-500 text-white px-2 py-1 rounded hover:bg-red-700 ml-2"
                                onclick="return confirm('¿Seguro que deseas eliminar?')">Eliminar</a>
                        </td>
                    <?php else: ?>
                        <td class="border px-3 py-2"><?= number_format($row['retencion'], 4) ?></td>
                        <td class="border px-3 py-2"><?= htmlspecialchars($row['ambito']) ?></td>
                        <td class="border px-3 py-2">
                            <button 
                                @click="openModal('<?= $row['id'] ?>', '<?= $row['retencion'] ?>', '<?= htmlspecialchars($row['ambito'], ENT_QUOTES) ?>')" 
                                class="bg-blue-500 text-white px-2 py-1 rounded hover:bg-blue-700">Editar</button>
                            <a href="?aux=retenciones&eliminar=<?= $row['id'] ?>" 
                                class="bg-red-500 text-white px-2 py-1 rounded hover:bg-red-700 ml-2"
                                onclick="return confirm('¿Seguro que deseas eliminar?')">Eliminar</a>
                        </td>
                    <?php endif ?>
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
            <form method="POST" onsubmit="return normalizeDecimals(this)">
                <input type="hidden" name="id" :value="editId">
                
                <!-- IVA fields -->
                <div x-show="aux === 'iva'">
                        <label class="block mb-3">Porcentaje (máx 2 decimales):
                            <input name="porcentaje" type="text" inputmode="decimal" placeholder="0.00" x-model="editPorcentaje" @input="formatDecimal($event, 2)" class="border px-2 py-1 w-full rounded" :class="{'border-red-500': editPorcentaje === '' || !isValidDecimal(editPorcentaje, 2)}"/>
                            <?php if (!empty($errors['porcentaje'])): ?>
                                <div class="text-red-600 text-sm mt-1"><?= htmlspecialchars($errors['porcentaje']) ?></div>
                            <?php endif; ?>
                            <div class="text-gray-500 text-xs mt-1" x-show="editPorcentaje && !isValidDecimal(editPorcentaje, 2)">Valor inválido o máximo 2 decimales</div>
                        </label>
                </div>
                <!-- Retenciones fields -->
                <div x-show="aux === 'retenciones'">
                    <label class="block mb-3">Retención (máx 2 decimales):
                        <input name="retencion" type="text" inputmode="decimal" placeholder="0.00" x-model="editRetencion" @input="formatDecimal($event, 2)" class="border px-2 py-1 w-full rounded" :class="{'border-red-500': editRetencion === '' || !isValidDecimal(editRetencion, 2)}"/>
                        <?php if (!empty($errors['retencion'])): ?>
                            <div class="text-red-600 text-sm mt-1"><?= htmlspecialchars($errors['retencion']) ?></div>
                        <?php endif; ?>
                        <div class="text-gray-500 text-xs mt-1" x-show="editRetencion && !isValidDecimal(editRetencion, 2)">Valor inválido o máximo 2 decimales</div>
                    </label>
                    <label class="block mb-3">Ámbito:
                        <input name="ambito" type="text" x-model="editAmbito" class="border px-2 py-1 w-full rounded" :class="{'border-red-500': editAmbito === ''}"/>
                        <?php if (!empty($errors['ambito'])): ?>
                            <div class="text-red-600 text-sm mt-1"><?= htmlspecialchars($errors['ambito']) ?></div>
                        <?php endif; ?>
                    </label>
                </div>
                <div class="flex justify-end">
                    <button type="button" @click="closeModal()" class="mr-3 px-3 py-1 bg-gray-400 text-white rounded hover:bg-gray-500">Cancelar</button>
                    <button class="px-3 py-1 text-white rounded" :class="isFormValid() ? 'bg-green-600 hover:bg-green-700' : 'bg-gray-400 cursor-not-allowed'" type="submit" :disabled="!isFormValid()">
                        Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function auxCrud(initAux) {
            return {
                modal: false,
                aux: initAux,
                editId: '',
                editPorcentaje: '',
                editRetencion: '',
                editAmbito: '',
                formTitle: '',
                openModal(id = '', v1 = '', v2 = '') {
                    this.modal = true;
                    this.editId = id;
                    this.aux = '<?= $aux ?>'; // siempre coincide con el selector
                    if (this.aux === 'iva') {
                        this.formTitle = id ? 'Editar IVA' : 'Añadir IVA';
                        this.editPorcentaje = v1 || '';
                    } else {
                        this.formTitle = id ? 'Editar Retención' : 'Añadir Retención';
                        this.editRetencion = v1 || '';
                        this.editAmbito = v2 || '';
                    }
                },
                closeModal() {
                    this.modal = false;
                    this.editId = '';
                    this.editPorcentaje = '';
                    this.editRetencion = '';
                    this.editAmbito = '';
                    this.formTitle = '';
                },
                isValidDecimal(value, decimals) {
                    if (value === '' || value === null) return false;
                    var numValue = parseFloat(value.replace(',', '.'));
                    if (isNaN(numValue) || numValue < 0 || numValue > 100) return false;
                    var parts = String(value).replace(',', '.').split('.');
                    if (parts.length > 2) return false;
                    if (parts.length === 2 && parts[1].length > decimals) return false;
                    return true;
                },
                formatDecimal(event, maxDecimals) {
                    var input = event.target;
                    var value = input.value;
                    value = value.replace(/[^0-9,.]/g, '');
                    value = value.replace(/,/g, '.');
                    var parts = value.split('.');
                    if (parts.length > 2) {
                        value = parts[0] + '.' + parts.slice(1).join('');
                    }
                    if (parts.length === 2 && parts[1].length > maxDecimals) {
                        value = parts[0] + '.' + parts[1].slice(0, maxDecimals);
                    }
                    input.value = value;
                    this[input.name === 'porcentaje' ? 'editPorcentaje' : 'editRetencion'] = value;
                },
                isFormValid() {
                    if (this.aux === 'iva') {
                        return this.editPorcentaje !== '' && this.isValidDecimal(this.editPorcentaje, 2);
                    } else {
                        return this.editRetencion !== '' && this.isValidDecimal(this.editRetencion, 2) && this.editAmbito !== '';
                    }
                }
            }
        }
    </script>

    <script>
        function normalizeDecimals(form) {
            var p = form.querySelector('[name="porcentaje"]');
            if (p && p.value) p.value = p.value.replace(/,/g, '.');
            var r = form.querySelector('[name="retencion"]');
            if (r && r.value) r.value = r.value.replace(/,/g, '.');
            return true;
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Si hubo un error en el servidor al procesar el POST, reabrimos el modal
            <?php if (!empty($errors)): ?>
                var compEl = document.querySelector('[x-data]');
                if (compEl && compEl.__x && compEl.__x.$data && typeof compEl.__x.$data.openModal === 'function') {
                    compEl.__x.$data.openModal(<?= json_encode($oldId ?? '') ?>, <?= json_encode($oldPorcentaje ?? ($oldRetencion ?? '')) ?>, <?= json_encode($oldAmbito ?? '') ?>);
                    // Si hay errores por campo, también asignamos los valores directamente
                    if (<?= json_encode(!empty($oldPorcentaje)) ?>) compEl.__x.$data.editPorcentaje = <?= json_encode($oldPorcentaje ?? '') ?>;
                    if (<?= json_encode(!empty($oldRetencion)) ?>) compEl.__x.$data.editRetencion = <?= json_encode($oldRetencion ?? '') ?>;
                    if (<?= json_encode(!empty($oldAmbito)) ?>) compEl.__x.$data.editAmbito = <?= json_encode($oldAmbito ?? '') ?>;
                }
            <?php endif; ?>
        });
    </script>
</body>
</html>
