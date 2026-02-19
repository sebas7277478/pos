<?php

set_time_limit(0);
ini_set('memory_limit', '512M');

$host = "localhost";
$db   = "sistema";
$user = "root";
$pass = "";

$ventasSimular = 3000;
$cajas = 4;
$id_usuario = 1;

try {

    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "🚀 Iniciando prueba masiva...\n";

    /*
|--------------------------------------------------------------------------
| 1. CREAR Y ABRIR CAJAS
|--------------------------------------------------------------------------
*/

    $cajaIds = [];

    for ($i = 1; $i <= $cajas; $i++) {

        $stmt = $pdo->prepare("
            INSERT INTO cajas 
            (monto_inicial, fecha_apertura, estado, id_usuario)
            VALUES (?, CURDATE(), 1, ?)
        ");

        $stmt->execute([100000, $id_usuario]);

        $cajaIds[] = $pdo->lastInsertId();
    }

    echo "✅ Cajas creadas\n";

    /*
|--------------------------------------------------------------------------
| 2. OBTENER PRODUCTOS CON STOCK
|--------------------------------------------------------------------------
*/

    $productos = $pdo->query("
        SELECT id, precio_venta, cantidad 
        FROM productos 
        WHERE cantidad > 50
    ")->fetchAll(PDO::FETCH_ASSOC);

    if (count($productos) == 0) {
        die("❌ No hay productos con suficiente cantidad.\n");
    }

    /*
|--------------------------------------------------------------------------
| 3. GENERAR VENTAS
|--------------------------------------------------------------------------
*/

    for ($i = 1; $i <= $ventasSimular; $i++) {

        $pdo->beginTransaction();

        try {

            $caja = $cajaIds[array_rand($cajaIds)];
            $metodo = rand(1, 100) <= 70 ? 'EFECTIVO' : 'CREDITO';

            $producto = $productos[array_rand($productos)];
            $cantidad = rand(1, 3);
            $total = $producto['precio_venta'] * $cantidad;

            // Insertar venta
            $stmt = $pdo->prepare("
                INSERT INTO ventas
                (productos, total, fecha, hora, metodo, descuento, serie, pago, estado, apertura, id_cliente, id_usuario)
                VALUES (?, ?, CURDATE(), CURTIME(), ?, 0, 'TST', ?, 1, ?, 1, ?)
            ");

            $productosJson = json_encode([
                [
                    "id" => $producto['id'],
                    "cantidad" => $cantidad,
                    "precio" => $producto['precio_venta']
                ]
            ]);

            $pago = $metodo == 'EFECTIVO' ? $total : 0;

            $stmt->execute([
                $productosJson,
                $total,
                $metodo,
                $pago,
                $caja,
                $id_usuario
            ]);

            $ventaId = $pdo->lastInsertId();

            // Insertar detalle
            $stmt = $pdo->prepare("
                INSERT INTO venta_detalle
                (id_venta, id_producto, cantidad, precio_venta, precio_compra, ganancia)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $precio_compra = 1000; // puedes mejorar esto
            $ganancia = ($producto['precio_venta'] - $precio_compra) * $cantidad;

            $stmt->execute([
                $ventaId,
                $producto['id'],
                $cantidad,
                $producto['precio_venta'],
                $precio_compra,
                $ganancia
            ]);

            // Actualizar stock
            $stmt = $pdo->prepare("
                UPDATE productos
                SET cantidad = cantidad - ?, ventas = ventas + ?
                WHERE id = ?
            ");

            $stmt->execute([$cantidad, $cantidad, $producto['id']]);

            /*
            |--------------------------------------------------------------------------
            | CRÉDITOS
            |--------------------------------------------------------------------------
            */

            if ($metodo == 'CREDITO') {

                $stmt = $pdo->prepare("
                    INSERT INTO creditos
                    (monto, fecha, hora, estado, id_venta)
                    VALUES (?, CURDATE(), CURTIME(), 1, ?)
                ");

                $stmt->execute([$total, $ventaId]);

                $creditoId = $pdo->lastInsertId();

                // Abono aleatorio
                $abono = rand(0, $total);

                if ($abono > 0) {

                    $stmt = $pdo->prepare("
                        INSERT INTO abonos
                        (abono, apertura, id_credito, id_usuario)
                        VALUES (?, ?, ?, ?)
                    ");

                    $stmt->execute([
                        $abono,
                        $caja,
                        $creditoId,
                        $id_usuario
                    ]);
                }
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "❌ Error en venta $i: " . $e->getMessage() . "\n";
        }

        if ($i % 500 == 0) {
            echo "Procesadas $i ventas...\n";
        }
    }

    echo "✅ Ventas generadas\n";

    /*
|--------------------------------------------------------------------------
| 4. VALIDACIONES
|--------------------------------------------------------------------------
*/

    echo "\n🔎 VALIDANDO SISTEMA...\n";

    // Stock negativo
    $negativos = $pdo->query("
        SELECT COUNT(*) FROM productos WHERE cantidad < 0
    ")->fetchColumn();

    echo $negativos == 0
        ? "✅ No hay stock negativo\n"
        : "❌ Hay productos con stock negativo\n";

    // Ventas sin detalle
    $sinDetalle = $pdo->query("
        SELECT COUNT(*)
        FROM ventas v
        LEFT JOIN venta_detalle d ON v.id = d.id_venta
        WHERE d.id IS NULL
    ")->fetchColumn();

    echo $sinDetalle == 0
        ? "✅ No hay ventas sin detalle\n"
        : "❌ Hay ventas sin detalle\n";

    echo "\n🎯 PRUEBA FINALIZADA\n";
} catch (PDOException $e) {
    die("Error conexión: " . $e->getMessage());
}


echo "\n🧮 VALIDANDO CUADRE DE CAJAS...\n";

foreach ($cajaIds as $cajaId) {

    // Datos base caja
    $stmt = $pdo->prepare("
        SELECT monto_inicial, IFNULL(gastos,0) gastos, IFNULL(egresos,0) egresos
        FROM cajas
        WHERE id = ?
    ");
    $stmt->execute([$cajaId]);
    $caja = $stmt->fetch(PDO::FETCH_ASSOC);

    $montoInicial = $caja['monto_inicial'];
    $gastos = $caja['gastos'];
    $egresos = $caja['egresos'];

    // Ventas efectivo
    $stmt = $pdo->prepare("
        SELECT IFNULL(SUM(total),0)
        FROM ventas
        WHERE apertura = ?
        AND metodo = 'EFECTIVO'
    ");
    $stmt->execute([$cajaId]);
    $ventasEfectivo = $stmt->fetchColumn();

    // Abonos
    $stmt = $pdo->prepare("
        SELECT IFNULL(SUM(abono),0)
        FROM abonos
        WHERE apertura = ?
    ");
    $stmt->execute([$cajaId]);
    $abonos = $stmt->fetchColumn();

    $montoEsperado = $montoInicial + $ventasEfectivo + $abonos - $gastos - $egresos;

    echo "Caja $cajaId\n";
    echo "Monto inicial: $montoInicial\n";
    echo "Ventas efectivo: $ventasEfectivo\n";
    echo "Abonos: $abonos\n";
    echo "Esperado: $montoEsperado\n\n";
}

echo "\n💳 VALIDANDO CRÉDITOS...\n";

// Total créditos
$totalCreditos = $pdo->query("
    SELECT IFNULL(SUM(monto),0)
    FROM creditos
    WHERE estado = 1
")->fetchColumn();

// Total abonos
$totalAbonos = $pdo->query("
    SELECT IFNULL(SUM(abono),0)
    FROM abonos
")->fetchColumn();

$deudaCalculada = $totalCreditos - $totalAbonos;

echo "Total créditos: $totalCreditos\n";
echo "Total abonos: $totalAbonos\n";
echo "Deuda pendiente real: $deudaCalculada\n\n";

echo "\n🔒 CERRANDO CAJAS AUTOMÁTICAMENTE...\n";

foreach ($cajaIds as $cajaId) {

    // Obtener datos base
    $stmt = $pdo->prepare("
        SELECT monto_inicial, IFNULL(gastos,0) gastos, IFNULL(egresos,0) egresos
        FROM cajas
        WHERE id = ?
    ");
    $stmt->execute([$cajaId]);
    $caja = $stmt->fetch(PDO::FETCH_ASSOC);

    $montoInicial = $caja['monto_inicial'];
    $gastos = $caja['gastos'];
    $egresos = $caja['egresos'];

    // Total ventas efectivo
    $stmt = $pdo->prepare("
        SELECT IFNULL(SUM(total),0)
        FROM ventas
        WHERE apertura = ?
        AND metodo = 'EFECTIVO'
    ");
    $stmt->execute([$cajaId]);
    $ventasEfectivo = $stmt->fetchColumn();

    // Total abonos
    $stmt = $pdo->prepare("
        SELECT IFNULL(SUM(abono),0)
        FROM abonos
        WHERE apertura = ?
    ");
    $stmt->execute([$cajaId]);
    $abonos = $stmt->fetchColumn();

    // Cálculo matemático
    $montoCalculado = $montoInicial + $ventasEfectivo + $abonos - $gastos - $egresos;

    // Actualizar caja (cerrar)
    $stmt = $pdo->prepare("
        UPDATE cajas
        SET 
            monto_final = ?,
            fecha_cierre = CURDATE(),
            estado = 0
        WHERE id = ?
    ");
    $stmt->execute([$montoCalculado, $cajaId]);

    // Verificación
    $stmt = $pdo->prepare("
        SELECT monto_final FROM cajas WHERE id = ?
    ");
    $stmt->execute([$cajaId]);
    $montoGuardado = $stmt->fetchColumn();

    echo "Caja $cajaId\n";
    echo "Monto calculado: $montoCalculado\n";
    echo "Monto guardado:  $montoGuardado\n";

    if (bccomp($montoCalculado, $montoGuardado, 2) === 0) {
        echo "✅ CUADRA PERFECTO\n\n";
    } else {
        echo "❌ ERROR: NO CUADRA\n\n";
    }
}
