/**
 * La Hormiga - Comportamiento general de la interfaz (usa Bootstrap 5).
 * Confirmaciones, carrito, galería y selector de estrellas.
 */
(function () {
    'use strict';

    var tokenCsrf = document.querySelector('meta[name="token-csrf"]').getAttribute('content');
    var urlBase = document.querySelector('meta[name="url-base"]').getAttribute('content');

    // -----------------------------------------------------------------
    //  Modal de confirmación para operaciones delicadas
    // -----------------------------------------------------------------
    var elementoModal = document.getElementById('modalConfirmacion');
    var modalConfirmacion = new bootstrap.Modal(elementoModal);
    var textoModal = document.getElementById('textoModal');
    var botonAceptar = document.getElementById('botonAceptarModal');
    var accionPendiente = null;

    function pedirConfirmacion(mensaje, alAceptar) {
        textoModal.textContent = mensaje;
        accionPendiente = alAceptar;
        modalConfirmacion.show();
    }

    botonAceptar.addEventListener('click', function () {
        var accion = accionPendiente;
        accionPendiente = null;
        modalConfirmacion.hide();
        if (accion) {
            accion();
        }
    });
    elementoModal.addEventListener('hidden.bs.modal', function () {
        accionPendiente = null;
    });

    // La validación HTML nativa se reemplaza por la validación en JavaScript
    document.querySelectorAll('form[data-validar]').forEach(function (formulario) {
        formulario.noValidate = true;
    });

    document.addEventListener('submit', function (evento) {
        var formulario = evento.target;
        var botonEnvio = evento.submitter || null;

        if (formulario.hasAttribute('data-validar') && !window.Validaciones.validarFormulario(formulario)) {
            evento.preventDefault();
            return;
        }

        var mensajeConfirmacion = (botonEnvio && botonEnvio.getAttribute('data-confirmar')) || formulario.getAttribute('data-confirmar');
        if (mensajeConfirmacion && formulario.getAttribute('data-confirmado') !== '1') {
            evento.preventDefault();
            pedirConfirmacion(mensajeConfirmacion, function () {
                formulario.setAttribute('data-confirmado', '1');
                if (botonEnvio && formulario.requestSubmit) {
                    formulario.requestSubmit(botonEnvio);
                } else {
                    formulario.submit();
                }
            });
            return;
        }
        formulario.removeAttribute('data-confirmado');
    });

    // -----------------------------------------------------------------
    //  Carrito de compras (peticiones asíncronas)
    // -----------------------------------------------------------------
    function formatoMoneda(cantidad) {
        return '$' + Number(cantidad).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function mostrarAviso(texto, tipo) {
        var contenedor = document.getElementById('contenedorMensajes');
        var alerta = document.createElement('div');
        alerta.className = 'alert alert-' + (tipo === 'error' ? 'danger' : 'success') + ' alert-dismissible fade show';
        alerta.setAttribute('role', 'alert');
        alerta.textContent = texto;
        var botonCerrar = document.createElement('button');
        botonCerrar.type = 'button';
        botonCerrar.className = 'btn-close';
        botonCerrar.setAttribute('data-bs-dismiss', 'alert');
        botonCerrar.setAttribute('aria-label', 'Cerrar');
        alerta.appendChild(botonCerrar);
        contenedor.appendChild(alerta);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function enviarAccionCarrito(datos) {
        var cuerpo = new URLSearchParams(datos);
        cuerpo.append('token_csrf', tokenCsrf);
        return fetch(urlBase + '/api/carrito.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
            body: cuerpo.toString(),
            credentials: 'same-origin'
        }).then(function (respuesta) {
            return respuesta.json().then(function (contenido) {
                if (respuesta.status === 401 && contenido.redirigir) {
                    window.location.href = contenido.redirigir;
                }
                return contenido;
            });
        });
    }

    function actualizarResumenCarrito(resumen) {
        var contador = document.getElementById('contadorCarrito');
        if (contador) {
            contador.textContent = resumen.articulos;
        }
        document.querySelectorAll('[data-subtotal]').forEach(function (elemento) {
            elemento.textContent = formatoMoneda(resumen.subtotal);
        });
        document.querySelectorAll('[data-texto-articulos]').forEach(function (elemento) {
            elemento.textContent = resumen.articulos + (resumen.articulos === 1 ? ' producto' : ' productos');
        });
        if (resumen.articulos === 0 && document.getElementById('tablaCarrito')) {
            window.location.reload();
        }
    }

    // Agregar desde la ficha o el catálogo
    document.addEventListener('submit', function (evento) {
        var formulario = evento.target;
        if (!formulario.matches('form[data-agregar-carrito]') || evento.defaultPrevented) {
            return;
        }
        evento.preventDefault();
        var datos = new FormData(formulario);
        enviarAccionCarrito({ accion: 'agregar', producto_id: datos.get('producto_id'), cantidad: datos.get('cantidad') || '1' })
            .then(function (respuesta) {
                mostrarAviso(respuesta.mensaje, respuesta.exito ? 'exito' : 'error');
                if (respuesta.resumen) {
                    actualizarResumenCarrito(respuesta.resumen);
                }
            });
    });

    // Actualizar cantidad en la vista del carrito
    document.addEventListener('change', function (evento) {
        var selector = evento.target;
        if (!selector.matches('[data-cantidad-carrito]')) {
            return;
        }
        var fila = selector.closest('[data-producto]');
        if (!window.Validaciones.validarCampo(selector)) {
            return;
        }
        enviarAccionCarrito({ accion: 'actualizar', producto_id: fila.getAttribute('data-producto'), cantidad: selector.value })
            .then(function (respuesta) {
                if (!respuesta.exito) {
                    mostrarAviso(respuesta.mensaje, 'error');
                    if (respuesta.cantidad_actual) {
                        selector.value = respuesta.cantidad_actual;
                    }
                    return;
                }
                fila.querySelector('[data-importe]').textContent = formatoMoneda(respuesta.importe);
                actualizarResumenCarrito(respuesta.resumen);
            });
    });

    // Eliminar desde la vista del carrito (con confirmación)
    document.addEventListener('click', function (evento) {
        var boton = evento.target.closest('[data-eliminar-carrito]');
        if (!boton) {
            return;
        }
        var fila = boton.closest('[data-producto]');
        pedirConfirmacion('¿Desea eliminar «' + boton.getAttribute('data-nombre') + '» de su carrito?', function () {
            enviarAccionCarrito({ accion: 'eliminar', producto_id: fila.getAttribute('data-producto') })
                .then(function (respuesta) {
                    if (respuesta.exito) {
                        fila.remove();
                        actualizarResumenCarrito(respuesta.resumen);
                    }
                    mostrarAviso(respuesta.mensaje, respuesta.exito ? 'exito' : 'error');
                });
        });
    });

    // -----------------------------------------------------------------
    //  Galería de la ficha de producto
    // -----------------------------------------------------------------
    document.addEventListener('click', function (evento) {
        var miniatura = evento.target.closest('[data-imagen-galeria]');
        if (!miniatura) {
            return;
        }
        document.getElementById('imagenPrincipal').src = miniatura.getAttribute('data-imagen-galeria');
        document.querySelectorAll('[data-imagen-galeria]').forEach(function (elemento) {
            elemento.classList.toggle('activa', elemento === miniatura);
        });
    });

    // -----------------------------------------------------------------
    //  Selector de estrellas (0 a 5)
    // -----------------------------------------------------------------
    document.querySelectorAll('.selector-estrellas').forEach(function (selector) {
        var campoOculto = selector.querySelector('input[type="hidden"]');
        var botones = selector.querySelectorAll('button[data-valor]');
        var etiqueta = selector.querySelector('.valor-estrellas');

        function pintar(valor) {
            botones.forEach(function (boton) {
                var valorBoton = parseInt(boton.getAttribute('data-valor'), 10);
                boton.classList.toggle('llena', valorBoton > 0 && valorBoton <= valor);
            });
            if (etiqueta) {
                etiqueta.textContent = campoOculto.value === '' ? 'Sin seleccionar' : valor + (valor === 1 ? ' estrella' : ' estrellas');
            }
        }
        botones.forEach(function (boton) {
            boton.addEventListener('click', function () {
                var valor = parseInt(boton.getAttribute('data-valor'), 10);
                campoOculto.value = String(valor);
                pintar(valor);
                window.Validaciones.validarCampo(campoOculto);
            });
        });
        pintar(parseInt(campoOculto.value || '0', 10));
    });

    // -----------------------------------------------------------------
    //  Formato del número de tarjeta y detección de la marca
    // -----------------------------------------------------------------
    document.querySelectorAll('[data-formato-tarjeta]').forEach(function (campo) {
        var indicador = document.getElementById(campo.getAttribute('data-formato-tarjeta'));
        campo.addEventListener('input', function () {
            var digitos = campo.value.replace(/\D+/g, '').slice(0, 19);
            campo.value = digitos.replace(/(\d{4})(?=\d)/g, '$1 ');
            if (indicador) {
                var marca = 'Tarjeta';
                if (/^4/.test(digitos)) {
                    marca = 'Visa';
                } else if (/^(5[1-5]|2[2-7])/.test(digitos)) {
                    marca = 'Mastercard';
                } else if (/^3[47]/.test(digitos)) {
                    marca = 'American Express';
                }
                indicador.textContent = marca;
            }
        });
    });

    // Vista previa de las imágenes antes de subirlas
    document.querySelectorAll('[data-vista-previa]').forEach(function (campo) {
        var contenedor = document.getElementById(campo.getAttribute('data-vista-previa'));
        campo.addEventListener('change', function () {
            contenedor.innerHTML = '';
            Array.prototype.forEach.call(campo.files, function (archivo) {
                if (archivo.type.indexOf('image/') !== 0) {
                    return;
                }
                var imagen = document.createElement('img');
                imagen.alt = archivo.name;
                imagen.className = 'rounded border me-2 mt-2';
                imagen.src = URL.createObjectURL(archivo);
                contenedor.appendChild(imagen);
            });
        });
    });
})();
