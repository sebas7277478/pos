<?php
require 'vendor/autoload.php';

use Dompdf\Dompdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class Productos extends Controller
{
    public function __construct()
    {
        parent::__construct();
        session_start();
        if (empty($_SESSION['id_usuario'])) {
            header('Location: ' . BASE_URL);
            exit;
        }
    }
    public function index()
    {
        if (!verificar('productos')) {
            header('Location: ' . BASE_URL . 'admin/permisos');
            exit;
        }
        $data['title'] = 'Productos';
        $data['script'] = 'productos.js';
        $data['medidas'] = $this->model->getDatos('medidas');
        $data['categorias'] = $this->model->getDatos('categorias');
        $this->views->getView('productos', 'index', $data);
    }
    public function listar()
    {
        if (!verificar('productos')) {
            header('Location: ' . BASE_URL . 'admin/permisos');
            exit;
        }
        $data = $this->model->getProductos(1);
        for ($i = 0; $i < count($data); $i++) {
            $foto = ($data[$i]['foto'] == null) ? 'assets/images/productos/default.png' : $data[$i]['foto'];
            $data[$i]['imagen'] = '<img class="img-thumbnail" src="' . BASE_URL . $foto . '" width="50">';
            $data[$i]['acciones'] = '<div>
            <button class="btn btn-danger" type="button" onclick="eliminarProducto(' . $data[$i]['id'] . ')"><i class="fas fa-trash"></i></button>
            <button class="btn btn-info" type="button" onclick="editarProducto(' . $data[$i]['id'] . ')"><i class="fas fa-edit"></i></button>
            </div>';
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        die();
    }
    public function registrar()
    {
        if (!verificar('productos')) {
            header('Location: ' . BASE_URL . 'admin/permisos');
            exit;
        }

        $codigo = strClean($_POST['codigo']);
        $nombre = strClean($_POST['nombre']);
        $precio_compra = strClean($_POST['precio_compra']);
        $precio_venta = strClean($_POST['precio_venta']);
        $vencimiento = strClean($_POST['vencimiento']);
        $id_medida = strClean($_POST['id_medida']);
        $id_categoria = strClean($_POST['id_categoria']);
        $id = strClean($_POST['id']);

        $imgNombre = null;
        $tmpFoto = $_FILES['imagen']['tmp_name'] ?? null;

        if ($tmpFoto) {
            $fecha = date("YmdHis");
            $imgNombre = $fecha . ".jpg";
            move_uploaded_file($tmpFoto, "Assets/images/productos/" . $imgNombre);
        }

        if ($id == "") {
            // Validar si el código ya existe
            $existe = $this->model->getValidar("codigo", $codigo, "registrar", 0);
            if (!empty($existe)) {
                $res = ["msg" => "EL CÓDIGO YA EXISTE", "type" => "warning"];
            } else {
                $res = $this->model->registrar($codigo, $nombre, $precio_compra, $precio_venta, $vencimiento, $id_medida, $id_categoria, $imgNombre);
                $res = ($res > 0)
                    ? ["msg" => "PRODUCTO REGISTRADO", "type" => "success"]
                    : ["msg" => "ERROR AL REGISTRAR", "type" => "error"];
            }
        } else {
            // Editar producto
            $producto = $this->model->editar($id);

            $fotoFinal = $imgNombre ?: $producto['foto']; // Mantener foto anterior si no suben nueva

            $res = $this->model->actualizar($codigo, $nombre, $precio_compra, $precio_venta, $vencimiento, $id_medida, $id_categoria, $fotoFinal, $id);

            if ($res == 1) {
                // Solo borra la foto vieja si la actualización en BD fue exitosa y subieron una nueva
                if ($imgNombre && $producto['foto'] && file_exists("Assets/images/productos/" . $producto['foto'])) {
                    unlink("Assets/images/productos/" . $producto['foto']);
                }
                $res = ["msg" => "PRODUCTO MODIFICADO", "type" => "success"];
            } else {
                // Si falla y ya se subió una nueva imagen, la eliminamos para no dejar basura
                if ($imgNombre && file_exists("Assets/images/productos/" . $imgNombre)) {
                    unlink("Assets/images/productos/" . $imgNombre);
                }
                $res = ["msg" => "ERROR AL MODIFICAR", "type" => "error"];
            }
        }

        echo json_encode($res, JSON_UNESCAPED_UNICODE);
        die();
    }

    public function eliminar($idProducto)
    {
        if (!verificar('productos')) {
            header('Location: ' . BASE_URL . 'admin/permisos');
            exit;
        }
        $data = $this->model->eliminar(0, $idProducto);
        $msg = ($data == 1)
            ? ["msg" => "PRODUCTO DADO DE BAJA", "type" => "success"]
            : ["msg" => "ERROR AL ELIMINAR", "type" => "error"];
        echo json_encode($msg, JSON_UNESCAPED_UNICODE);
        die();
    }

    public function editar($idProducto)
    {
        if (!verificar('productos')) {
            header('Location: ' . BASE_URL . 'admin/permisos');
            exit;
        }
        $data = $this->model->editar($idProducto);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        die();
    }

    public function inactivos()
    {
        if (!verificar('productos')) {
            header('Location: ' . BASE_URL . 'admin/permisos');
            exit;
        }
        $data['title'] = 'Productos Inactivos';
        $data['script'] = 'productos-inactivos.js';
        $this->views->getView('productos', 'inactivos', $data);
    }

    public function listarInactivos()
    {
        if (!verificar('productos')) {
            header('Location: ' . BASE_URL . 'admin/permisos');
            exit;
        }
        $data = $this->model->getProductos(0);
        for ($i = 0; $i < count($data); $i++) {
            $data[$i]['imagen'] = '<img class="img-thumbnail" src="' . BASE_URL . $data[$i]['foto'] . '" width="100">';
            $data[$i]['acciones'] = '<div>
            <button class="btn btn-danger" type="button" onclick="restaurarProducto(' . $data[$i]['id'] . ')"><i class="fas fa-check-circle"></i></button>
            </div>';
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        die();
    }

    public function restaurar($idProducto)
    {
        if (!verificar('productos')) {
            header('Location: ' . BASE_URL . 'admin/permisos');
            exit;
        }
        $data = $this->model->eliminar(1, $idProducto);
        $msg = ($data == 1)
            ? ["msg" => "PRODUCTO RESTAURADO", "type" => "success"]
            : ["msg" => "ERROR AL REINGRESAR", "type" => "error"];
        echo json_encode($msg, JSON_UNESCAPED_UNICODE);
        die();
    }

    //buscar Productos por codigo
    public function buscarPorCodigo($valor)
    {
        $array = array('estado' => false, 'datos' => '');
        $data = $this->model->buscarPorCodigo($valor);
        if (!empty($data)) {
            $array['estado'] = true;
            $array['datos'] = $data;
        }
        echo json_encode($array, JSON_UNESCAPED_UNICODE);
        die();
    }
    //buscar Productos por nombre
    public function buscarPorNombre()
    {
        $array = array();
        $valor = $_GET['term'];
        $data = $this->model->buscarPorNombre($valor);
        foreach ($data as $row) {
            $result['id'] = $row['id'];
            $result['label'] = $row['descripcion'] . '    Stock:  ' . $row['cantidad'];
            $result['stock'] = $row['cantidad'];
            $result['precio_venta'] = $row['precio_venta'];
            $result['precio_compra'] = $row['precio_compra'];
            array_push($array, $result);
        }
        echo json_encode($array, JSON_UNESCAPED_UNICODE);
        die();
    }

    public function buscarPorNombreVentas()
    {
        $array = array();
        $valor = $_GET['term'];
        $data = $this->model->buscarPorNombre($valor);
        foreach ($data as $row) {
            $result['id'] = $row['id'];
            $result['label'] = $row['descripcion'] . '    Stock:  ' . $row['cantidad'] . '   $:  ' . $row['precio_venta'];
            $result['stock'] = $row['cantidad'];
            $result['precio_venta'] = $row['precio_venta'];
            $result['precio_compra'] = $row['precio_compra'];
            array_push($array, $result);
        }
        echo json_encode($array, JSON_UNESCAPED_UNICODE);
        die();
    }

    //mostrar productos desde localStorage
    public function mostrarDatos()
    {
        $json = file_get_contents('php://input');
        $datos = json_decode($json, true);
        $array['productos'] = array();
        $totalCompra = 0;
        $totalVenta = 0;
        if (!empty($datos)) {
            foreach ($datos as $producto) {
                $result = $this->model->editar($producto['id']);
                $data['id'] = $result['id'];
                $data['nombre'] = $result['descripcion'];
                $data['precio_compra'] = number_format((empty($producto['precio'])) ? 0 : $producto['precio'], 2, '.', '');
                $data['precio_venta'] = number_format((empty($producto['precio'])) ? 0 : $producto['precio'], 2, '.', '');
                $data['cantidad'] = $producto['cantidad'];
                $subTotalCompra = $data['precio_compra'] * $producto['cantidad'];
                $subTotalVenta = $data['precio_venta'] * $producto['cantidad'];
                $data['subTotalCompra'] = number_format($subTotalCompra, 2);
                $data['subTotalVenta'] = number_format($subTotalVenta, 2);
                array_push($array['productos'], $data);
                $totalCompra += $subTotalCompra;
                $totalVenta += $subTotalVenta;
            }
        }
        $array['totalCompra'] = number_format($totalCompra, 2);
        $array['totalVenta'] = number_format($totalVenta, 2);
        $array['totalVentaSD'] = number_format($totalVenta, 2, '.', '');
        echo json_encode($array, JSON_UNESCAPED_UNICODE);
        die();
    }

    public function reporteExcel()
    {
        $spreadsheet = new Spreadsheet();

        $spreadsheet->getProperties()
            ->setCreator($_SESSION['nombre_usuario'])
            ->setTitle("Listado de Productos");

        $spreadsheet->setActiveSheetIndex(0);

        $hojaActiva = $spreadsheet->getActiveSheet();
        $hojaActiva->getColumnDimension('A')->setWidth(50);
        $hojaActiva->getColumnDimension('B')->setWidth(10);
        $hojaActiva->getColumnDimension('C')->setWidth(20);
        $hojaActiva->getColumnDimension('D')->setWidth(20);
        $hojaActiva->getColumnDimension('E')->setWidth(30);

        $spreadsheet->getActiveSheet()->getStyle('A1:E1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('008cff');

        $spreadsheet->getActiveSheet()->getStyle('A1:E1')
            ->getFont()->getColor()->setARGB(Color::COLOR_WHITE);

        $hojaActiva->setCellValue('A1', 'Producto');
        $hojaActiva->setCellValue('B1', 'Cantidad');
        $hojaActiva->setCellValue('C1', 'Precio Compra');
        $hojaActiva->setCellValue('D1', 'Precio Venta');
        $hojaActiva->setCellValue('E1', 'Categoria');

        $fila = 2;
        $productos = $this->model->getProductos(1);
        foreach ($productos as $producto) {
            $hojaActiva->setCellValue('A' . $fila, $producto['descripcion']);
            $hojaActiva->setCellValue('B' . $fila, $producto['cantidad']);
            $hojaActiva->setCellValue('C' . $fila, $producto['precio_compra']);
            $hojaActiva->setCellValue('D' . $fila, $producto['precio_venta']);
            $hojaActiva->setCellValue('E' . $fila, $producto['categoria']);
            $fila++;
        }

        //Generar archivo Excel
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="productos.xlsx"');
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
    }

    public function reportePdf()
    {
        ob_start();
        $data['title'] = 'Listado de Productos';
        $data['empresa'] = $this->model->getEmpresa();
        $data['productos'] = $this->model->getProductos(1);
        $this->views->getView('reportes', 'reportesPdf', $data);
        $html = ob_get_clean();
        $dompdf = new Dompdf();
        $options = $dompdf->getOptions();
        $options->set('isJavascriptEnabled', true);
        $options->set('isRemoteEnabled', true);
        $dompdf->setOptions($options);
        $dompdf->loadHtml($html);

        $dompdf->setPaper('A4', 'vertical');

        // Render the HTML as PDF
        $dompdf->render();

        // Output the generated PDF to Browser
        $dompdf->stream('reporte.pdf', array('Attachment' => false));
    }

    public function generarBarcode()
    {
        //$redColor = [255, 0, 0];
        $data['productos'] = $this->model->getProductos(1);
        $generator = new Picqer\Barcode\BarcodeGeneratorPNG();
        $ruta = 'assets/images/barcode/';
        foreach ($data['productos'] as $producto) {
            file_put_contents($ruta . $producto['id'] . '.png', $generator->getBarcode($producto['codigo'], $generator::TYPE_CODE_128, 3, 50));
        }
        ob_start();
        $data['title'] = 'Barcode';
        $this->views->getView('reportes', 'barcode', $data);
        $html = ob_get_clean();
        $dompdf = new Dompdf();
        $options = $dompdf->getOptions();
        $options->set('isJavascriptEnabled', true);
        $options->set('isRemoteEnabled', true);
        $dompdf->setOptions($options);
        $dompdf->loadHtml($html);

        $dompdf->setPaper('A4', 'vertical');

        // Render the HTML as PDF
        $dompdf->render();

        // Output the generated PDF to Browser
        $dompdf->stream('reporte.pdf', array('Attachment' => false));
    }
}