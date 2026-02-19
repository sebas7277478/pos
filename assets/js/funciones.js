const firstTabEl = document.querySelector("#nav-tab button:last-child");
const firstTab = new bootstrap.Tab(firstTabEl);

const primerTabEl = document.querySelector("#nav-tab button:first-child");
const primerTab = new bootstrap.Tab(primerTabEl);

function insertarRegistros(url, idFormulario, tbl, idButton, accion) {
  const data = new FormData(idFormulario);
  const http = new XMLHttpRequest();
  http.open("POST", url, true);
  http.send(data);

  http.onreadystatechange = function () {
    if (this.readyState == 4 && this.status == 200) {
      const res = JSON.parse(this.responseText);

      alertaPersonalizada(res.type, res.msg);

      if (res.type == "success") {
        // 1. Manejo de clave (para usuarios/clientes)
        if (accion && typeof clave !== "undefined") {
          clave.removeAttribute("readonly");
        }

        // 2. Resetear formulario
        idFormulario.reset();

        // 3. Limpiar ID si existe (evita errores si el campo no está)
        const idField = document.querySelector("#id");
        if (idField) idField.value = "";

        // 4. Restaurar texto del botón
        if (idButton) idButton.textContent = "Registrar";

        // 5. Recargar tabla si se proporcionó una
        if (tbl != null) {
          tbl.ajax.reload();
        }

        // 6. LÓGICA ESPECIAL PARA CAJAS (Actualizar gráfica y lista)
        // Si la función 'movimientos' existe (estás en cajas.js), la ejecutamos
        if (typeof movimientos === "function") {
          movimientos();
        } else {
          // Si NO estás en cajas, hacemos el cambio de pestaña normal
          if (typeof primerTab !== "undefined") {
            primerTab.show();
          }
        }
      }
    }
  };
}

function eliminarRegistros(url, tbl) {
  Swal.fire({
    title: "Esta seguro de eliminar?",
    text: "El registro no se eliminará de forma permanente, solo cambiará el estado!",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#3085d6",
    cancelButtonColor: "#d33",
    confirmButtonText: "Si, Eliminar!",
  }).then((result) => {
    if (result.isConfirmed) {
      //hacer una instancia del objeto XMLHttpRequest
      const http = new XMLHttpRequest();
      //Abrir una Conexion - POST - GET
      http.open("GET", url, true);
      //Enviar Datos
      http.send();
      //verificar estados
      http.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
          const res = JSON.parse(this.responseText);
          Swal.fire({
            toast: true,
            position: "top-right",
            icon: res.type,
            title: res.msg,
            showConfirmButton: false,
            timer: 2000,
          });
          if (res.type == "success") {
            tbl.ajax.reload();
          }
        }
      };
    }
  });
}

function restaurarRegistros(url, tbl) {
  Swal.fire({
    title: "Esta seguro de restaurar?",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#3085d6",
    cancelButtonColor: "#d33",
    confirmButtonText: "Si, Restaurar!",
  }).then((result) => {
    if (result.isConfirmed) {
      //hacer una instancia del objeto XMLHttpRequest
      const http = new XMLHttpRequest();
      //Abrir una Conexion - POST - GET
      http.open("GET", url, true);
      //Enviar Datos
      http.send();
      //verificar estados
      http.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
          const res = JSON.parse(this.responseText);
          Swal.fire({
            toast: true,
            position: "top-right",
            icon: res.type,
            title: res.msg,
            showConfirmButton: false,
            timer: 2000,
          });
          if (res.type == "success") {
            tbl.ajax.reload();
          }
        }
      };
    }
  });
}

function alertaPersonalizada(type, msg) {
  Swal.fire({
    toast: true,
    position: "top-right",
    icon: type,
    title: msg,
    showConfirmButton: false,
    timer: 2000,
  });
}
