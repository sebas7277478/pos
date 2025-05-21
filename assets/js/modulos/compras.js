const tblNuevaCompra = document.querySelector('#tblNuevaCompra tbody');
const serie = document.querySelector('#serie');
//provedores
const idProveedor = document.querySelector('#idProveedor');
const telefonoProveedor = document.querySelector('#telefonoProveedor');
const direccionProveedor = document.querySelector('#proveedorDireccion');
const errorProveedor = document.querySelector('#errorProveedor');

const metodo = document.querySelector('#metodo');

const idProveedorPagos = document.querySelector('#buscarProveedorPagos');
const idCreditoCompra = document.querySelector('#idCreditoCompra');
const telefono = document.querySelector('#telProveedor');
const direccion = document.querySelector('#dirProveedor');
const abonado = document.querySelector('#abonoCompra');
const restante = document.querySelector('#restanteCompra');
const fecha = document.querySelector('#fecha');
const monto = document.querySelector('#monto_total');
const monto_abonar = document.querySelector('#monto_abonar');
const errorProveedorPagos = document.querySelector('#errorProveedorPagos');

const btnAccionAbonar = document.querySelector('#btnAccionAbonar');
const nuevoPago = document.querySelector('#nuevoPago');

const modalPagos = new bootstrap.Modal('#modalPagos');

document.addEventListener('DOMContentLoaded', function () {
    //autocomplete proveedores
    $("#buscarProveedor").autocomplete({
        source: function (request, response) {
            $.ajax({
                url: base_url + 'proveedor/buscar',
                dataType: "json",
                data: {
                    term: request.term
                },
                success: function (data) {
                    response(data);
                    if (data.length > 0) {
                        errorProveedor.textContent = '';
                    } else {
                        errorProveedor.textContent = 'NO HAY PROVEEDOR CON ESE NOMBRE';
                    }
                }
            });
        },
        minLength: 2,
        select: function (event, ui) {
            idProveedor.value = ui.item.id;
            telefonoProveedor.value = ui.item.telefono;
            direccionProveedor.innerHTML = ui.item.direccion;            
            serie.focus();
        }
    });

     //autocomplete proveedores para pagos
     $("#buscarProveedorPagos").autocomplete({
        source: function (request, response) {
            $.ajax({
                url: base_url + 'compras/buscarCreditoActivo',
                dataType: "json",
                data: {
                    term: request.term
                },
                success: function (data) {
                    response(data);
                    if (data.length > 0) {
                        errorProveedorPagos.textContent = '';
                    } else {
                        errorProveedorPagos.textContent = 'NO HAY CREDITOS CON EL PROVEEDOR';
                    }
                }
            });
        },
        minLength: 2,
        select: function (event, ui) {
            telefono.value = ui.item.telefono;
            direccion.innerHTML = ui.item.direccion;
            idCreditoCompra.value = ui.item.id;
            idProveedorPagos.value = ui.item.id;
            abonado.value = ui.item.abonado;
            restante.value = ui.item.restante;
            fecha.value = ui.item.fecha;
            monto.value = ui.item.monto;          
            monto_abonar.focus();
        }
    });

    //cargar datos
    mostrarProducto();

    //completar compra
    btnAccion.addEventListener('click', function () {
        let filas = document.querySelectorAll('#tblNuevaCompra tr').length;
        if (filas < 2) {
            alertaPersonalizada('warning', 'CARRITO VACIO');
            return;
        } else if (idProveedor.value == ''
            && telefonoProveedor.value == '') {
            alertaPersonalizada('warning', 'EL PROVEEDOR ES REQUERIDO');
            return;
        } else if (serie.value == '') {
            alertaPersonalizada('warning', 'LA SERIE ES REQUERIDO');
            return;
        } else if (metodo.value == '') {
            alertaPersonalizada('warning', 'EL METODO ES REQUERIDO');
            return;
        }
         else {
            const url = base_url + 'compras/registrarCompra';
            //hacer una instancia del objeto XMLHttpRequest 
            const http = new XMLHttpRequest();
            //Abrir una Conexion - POST - GET
            http.open('POST', url, true);
            //Enviar Datos
            http.send(JSON.stringify({
                productos: listaCarrito,
                idProveedor: idProveedor.value,
                metodo: metodo.value,
                serie: serie.value,                
            }));
            //verificar estados
            http.onreadystatechange = function () {
                if (this.readyState == 4 && this.status == 200) {
                    const res = JSON.parse(this.responseText);
                    console.log(this.responseText);
                    alertaPersonalizada(res.type, res.msg);
                    if (res.type == 'success') {
                        localStorage.removeItem(nombreKey);
                        setTimeout(() => {
                            Swal.fire({
                                title: 'Desea Generar Reporte?',
                                showDenyButton: true,
                                showCancelButton: true,
                                confirmButtonText: 'Ticked',
                                denyButtonText: `Factura`,
                            }).then((result) => {
                                /* Read more about isConfirmed, isDenied below */
                                if (result.isConfirmed) {
                                    const ruta = base_url + 'compras/reporte/ticked/' + res.idCompra;
                                    window.open(ruta, '_blank');
                                } else if (result.isDenied) {
                                    const ruta = base_url + 'compras/reporte/factura/' + res.idCompra;
                                    window.open(ruta, '_blank');
                                }
                                window.location.reload();
                            })

                        }, 2000);
                    }
                }
            }
        }

    })

    //mostrar historial
    //cargar datos con el plugin datatables
    tblHistorial = $('#tblHistorial').DataTable({
        ajax: {
            url: base_url + 'compras/listar',
            dataSrc: ''
        },
        columns: [
            { data: 'fecha' },
            { data: 'hora' },
            { data: 'total' },
            { data: 'nombre' },
            { data: 'serie' },
            { data: 'metodo' },
            { data: 'acciones' },
        ],
        language: {
            url: base_url + 'assets/js/espanol.json'
        },
        dom,
        buttons,
        responsive: true,
        order: [[0, 'desc']],
    });

    //levantar modal pagos

    nuevoPago.addEventListener('click', function() {
        idProveedorPagos.value = '';
        telefono.value = '';
        idCreditoCompra.value = '';
        direccion.innerHTML = '';
        abonado.value = '';
        restante.value = '';
        monto_total.value = '';
        fecha.value = '';
        monto.value = '';
        monto_abonar.value = '';
        modalPagos.show();
    })

    
    btnAccionAbonar.addEventListener('click', function () {

        if(idCreditoCompra.value == '' && telefono.value == ''){
            alertaPersonalizada('warning', 'BUSCA Y SELECCIONA UN PROVEEDOR');
        }else if(monto_abonar.value == ''){
            alertaPersonalizada('warning', 'INGRESE EL MONTO');         
        } else if(parseFloat(restante.value) < parseFloat(monto_abonar.value)) {
            alertaPersonalizada('warning', 'INGRESE MENOR A RESTANTE');
        } else {
            const url = base_url + 'compras/registrarAbonoCompras';
            //hacer una instancia del objeto XMLHttpRequest 
            const http = new XMLHttpRequest();
            //Abrir una Conexion - POST - GET
            http.open('POST', url, true);
            //Enviar Datos
            http.send(JSON.stringify({
                fecha : new Date(),
                idCreditoCompra : idCreditoCompra.value,
                monto_abonar : monto_abonar.value,
            }));
            //verificar estados
            http.onreadystatechange = function () {
                if (this.readyState == 4 && this.status == 200) {
                    const res = JSON.parse(this.responseText);
                    alertaPersonalizada(res.type, res.msg);
                    if(res.type == 'success'){
                        modalPagos.hide();
                        tblHistorial.ajax.reload();
                    }
                }
            }
        }
    })

})

//cargar productos
function mostrarProducto() {
    if (localStorage.getItem(nombreKey) != null) {
        const url = base_url + 'productos/mostrarDatos';
        //hacer una instancia del objeto XMLHttpRequest 
        const http = new XMLHttpRequest();
        //Abrir una Conexion - POST - GET
        http.open('POST', url, true);
        //Enviar Datos
        http.send(JSON.stringify(listaCarrito));
        //verificar estados
        http.onreadystatechange = function () {
            if (this.readyState == 4 && this.status == 200) {
                const res = JSON.parse(this.responseText);
                let html = '';
                if (res.productos.length > 0) {
                    res.productos.forEach(producto => {
                        html += `<tr>
                            <td>${producto.nombre}</td>
                            <td>${producto.precio_compra}</td>
                            <td width="100">
                            <input type="number" class="form-control inputCantidad" data-id="${producto.id}" value="${producto.cantidad}" placeholder="Cantidad">
                            </td>
                            <td>${producto.subTotalCompra}</td>
                            <td><button class="btn btn-danger btnEliminar" data-id="${producto.id}" type="button"><i class="fas fa-trash"></i></button></td>
                        </tr>`;
                    });
                    tblNuevaCompra.innerHTML = html;
                    totalPagar.value = res.totalCompra;
                    btnEliminarProducto();
                    agregarCantidad();
                } else {
                    tblNuevaCompra.innerHTML = '';
                }
            }
        }
    } else {
        tblNuevaCompra.innerHTML = `<tr>
            <td colspan="4" class="text-center">CARRITO VACIO</td>
        </tr>`;
    }
}

function verReporte(idCompra) {
    Swal.fire({
        title: 'Desea Generar Reporte?',
        showDenyButton: true,
        showCancelButton: true,
        confirmButtonText: 'Ticked',
        denyButtonText: `Factura`,
    }).then((result) => {
        /* Read more about isConfirmed, isDenied below */
        if (result.isConfirmed) {
            const ruta = base_url + 'compras/reporte/ticked/' + idCompra;
            window.open(ruta, '_blank');
        } else if (result.isDenied) {
            const ruta = base_url + 'compras/reporte/factura/' + idCompra;
            window.open(ruta, '_blank');
        }
    })
}

function anularCompra(idCompra) {
    Swal.fire({
        title: 'Esta seguro de anular la compra?',
        text: "El stock de los productos cambiarán!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Si, Anular!'
    }).then((result) => {
        if (result.isConfirmed) {
            const url = base_url + 'compras/anular/' + idCompra;
            //hacer una instancia del objeto XMLHttpRequest 
            const http = new XMLHttpRequest();
            //Abrir una Conexion - POST - GET
            http.open('GET', url, true);
            //Enviar Datos
            http.send();
            //verificar estados
            http.onreadystatechange = function () {
                if (this.readyState == 4 && this.status == 200) {
                    const res = JSON.parse(this.responseText);
                    alertaPersonalizada(res.type, res.msg);
                    if (res.type == 'success') {
                        tblHistorial.ajax.reload();
                    }
                }
            }
        }
    })
}