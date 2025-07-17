<?php include_once 'views/templates/header.php'; ?>

<div class="card">
    <div class="card-body">
        <div class="d-flex align-items-center">
            <div></div>
            <div class="dropdown ms-auto">
                <a class="dropdown-toggle dropdown-toggle-nocaret" href="#" data-bs-toggle="dropdown"><i
                        class='bx bx-dots-horizontal-rounded font-22 text-option'></i>
                </a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="#" id="nuevoPago"><i class="fas fa-dollar-sign"></i> Pagos</a>
                    </li>
                </ul>
            </div>
        </div>
        <nav>
            <div class="nav nav-tabs" id="nav-tab" role="tablist">
                <button class="nav-link active" id="nav-compras-tab" data-bs-toggle="tab" data-bs-target="#nav-compras"
                    type="button" role="tab" aria-controls="nav-compras" aria-selected="true">Compras</button>
                <button class="nav-link" id="nav-historial-tab" data-bs-toggle="tab" data-bs-target="#nav-historial"
                    type="button" role="tab" aria-controls="nav-historial" aria-selected="false">Historial</button>
            </div>
        </nav>
        <div class="tab-content" id="nav-tabContent">
            <div class="tab-pane fade show active p-3" id="nav-compras" role="tabpanel"
                aria-labelledby="nav-compras-tab" tabindex="0">
                <h5 class="card-title text-center"><i class="fas fa-truck"></i> Nueva Compra</h5>
                <hr>
                <div class="btn-group btn-group-toggle mb-2" data-toggle="buttons">
                    <label class="btn btn-primary">
                        <input type="radio" id="barcode" checked name="buscarProducto"><i class="fas fa-barcode"></i>
                        Barcode
                    </label>
                    <label class="btn btn-info">
                        <input type="radio" id="nombre" name="buscarProducto"><i class="fas fa-list"></i> Nombre
                    </label>
                </div>
                <!-- input para buscar codigo -->
                <div class="input-group mb-2" id="containerCodigo">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input class="form-control" type="text" id="buscarProductoCodigo"
                        placeholder="Ingrese Barcode - Enter" autocomplete="off">
                </div>

                <!-- input para buscar nombre -->
                <div class="input-group d-none mb-2" id="containerNombre">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input class="form-control" type="text" id="buscarProductoNombre" placeholder="Buscar Producto"
                        autocomplete="off">
                </div>

                <span class="text-danger fw-bold mb-2" id="errorBusqueda"></span>

                <!-- table productos -->

                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover align-middle" id="tblNuevaCompra"
                        style="width: 100%;">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Precio</th>
                                <th>Cantidad</th>
                                <th>SubTotal</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>

                <hr>

                <div class="row justify-content-between">
                    <div class="col-md-4">
                        <label>Buscar Proveedor</label>
                        <div class="input-group mb-2">
                            <input type="hidden" id="idProveedor">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input class="form-control" type="text" id="buscarProveedor" placeholder="Buscar Proveedor">
                        </div>
                        <span class="text-danger fw-bold mb-2" id="errorProveedor"></span>

                        <label>Telefono</label>
                        <div class="input-group mb-2">
                            <span class="input-group-text"><i class="fas fa-phone"></i></span>
                            <input class="form-control" type="text" id="telefonoProveedor" placeholder="Telefono"
                                disabled>
                        </div>

                        <label>Dirección</label>
                        <ul class="list-group mb-2">
                            <li class="list-group-item" id="proveedorDireccion"><i class="fas fa-home"></i></li>
                        </ul>

                        <label>Comprador</label>
                        <div class="input-group mb-2">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input class="form-control" type="text" value="<?php echo $_SESSION['nombre_usuario']; ?>"
                                placeholder="Comprador" disabled>
                        </div>
                    </div>

                    <div class="col-md-4">

                        <label>Total a Pagar</label>
                        <div class="input-group mb-2">
                            <span class="input-group-text"><i class="fas fa-dollar-sign"></i></span>
                            <input class="form-control" type="text" id="totalPagar" placeholder="Total Pagar" disabled>
                        </div>

                        <label>Serie</label>
                        <div class="input-group mb-2">
                            <span class="input-group-text"><i class="fas fa-spinner"></i></span>
                            <input class="form-control" type="text" id="serie" placeholder="Serie Compra"
                                autocomplete="off">
                        </div>
                        <div class="form-group mb-5">
                            <label for="metodo">Metodo</label>
                            <select id="metodo" class="form-control">
                                <option value="CONTADO">CONTADO</option>
                                <option value="CREDITO">CREDITO</option>
                            </select>
                        </div>
                        <div class="d-grid">
                            <button class="btn btn-primary" type="button" id="btnAccion">Completar</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="tab-pane fade p-3" id="nav-historial" role="tabpanel" aria-labelledby="nav-historial-tab"
                tabindex="0">
                <div class="d-flex justify-content-center mb-3">
                    <div class="form-group">
                        <label for="desde">Desde</label>
                        <input id="desde" class="form-control" type="date">
                    </div>
                    <div class="form-group">
                        <label for="hasta">Hasta</label>
                        <input id="hasta" class="form-control" type="date">
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover align-middle nowrap" id="tblHistorial"
                        style="width: 100%;">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Hora</th>
                                <th>Total</th>
                                <th>Proveedor</th>
                                <th>Serie</th>
                                <th>Metodo</th>
                                <th></th>
                            </tr>
                        </thead>

                        <tbody>
                        </tbody>
                    </table>

                </div>
            </div>
        </div>
    </div>
</div>

<div id="modalPagos" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="my-modal-title" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Pagar Facturas</h5>
                <button class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-12">
                        <label>Buscar Proveedor</label>
                        <div class="input-group mb-2">
                            <input type="hidden" id="idCreditoCompra">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input class="form-control" type="text" id="buscarProveedorPagos" placeholder="Buscar Proveedor" autocomplete="off">
                        </div>
                        <span class="text-danger fw-bold mb-2" id="errorProveedorPagos"></span>
                    </div>

                    <div class="col-md-12">
                        <label>Telefono</label>
                        <div class="input-group mb-2">
                            <span class="input-group-text"><i class="fas fa-phone"></i></span>
                            <input class="form-control" type="text" id="telProveedor" placeholder="Telefono"
                                disabled>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <label>Dirección</label>
                        <ul class="list-group mb-2">
                            <li class="list-group-item" id="dirProveedor"><i class="fas fa-home"></i></li>
                        </ul>
                    </div>

                    <div class="col-md-6 mb-2">
                        <label>Abonado</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-dollar-sign"></i></span>
                            <input class="form-control" type="text" id="abonoCompra" readonly>
                        </div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <label>Restante</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-dollar-sign"></i></span>
                            <input class="form-control" type="text" id="restanteCompra" readonly>
                        </div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="form-group">
                            <label for="fecha">Fecha Compra</label>
                            <input id="fecha" class="form-control" type="text" placeholder="Fecha Compra" readonly>
                        </div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="form-group">
                            <label for="monto_total">Monto Total</label>
                            <input id="monto_total" class="form-control" type="text" placeholder="Monto Total" readonly>
                        </div>
                    </div>
                    <div class="col-md-4 mb-2">
                        <div class="form-group">
                            <label for="monto_abonar">Abonar</label>
                            <input id="monto_abonar" class="form-control" type="number" step="0.01" min="0.01"
                                placeholder="Monto Abonar" autocomplete="off">
                        </div>
                    </div>
                </div>
                <div class="d-grid">
                    <button class="btn btn-primary" type="button" id="btnAccionAbonar">Abonar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once 'views/templates/footer.php'; ?>