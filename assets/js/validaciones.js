/**
 * La Hormiga - Validación de formularios con JavaScript.
 *
 * Uso: <form data-validar> ... <input data-regla="email"> ...
 * Reglas disponibles (pueden combinarse separadas por "|"):
 *   requerido, email, contrasena, confirmar:idCampo, nombre, rfc, curp, telefono, cp,
 *   tarjeta, cvv, mes, anio, vencimiento:idMes:idAnio, precio, entero:min:max,
 *   texto:max, sku, seleccion, imagenes, estrellas, busqueda
 * Atributo data-opcional: el campo puede quedar vacío; si se llena, se valida.
 */
(function () {
    'use strict';

    var patrones = {
        email: /^[^\s@]+@[^\s@]+\.[A-Za-z]{2,}$/,
        contrasena: /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,64}$/,
        nombre: /^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ' .-]{2,80}$/,
        rfc: /^[A-ZÑ&]{4}\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])[A-Z0-9]{2}[0-9A]$/,
        curp: /^[A-Z][AEIOUX][A-Z]{2}\d{2}(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])[HM](AS|BC|BS|CC|CL|CM|CS|CH|DF|DG|GT|GR|HG|JC|MC|MN|MS|NT|NL|OC|PL|QT|QR|SP|SL|SR|TC|TS|TL|VZ|YN|ZS|NE)[B-DF-HJ-NP-TV-Z]{3}[A-Z0-9]\d$/,
        telefono: /^\d{10}$/,
        cp: /^\d{5}$/,
        cvv: /^\d{3,4}$/,
        precio: /^\d{1,8}(\.\d{1,2})?$/,
        entero: /^\d+$/,
        sku: /^[A-Z0-9-]{3,30}$/
    };

    var tiposImagen = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    var tamanoMaximoImagen = 5 * 1024 * 1024;

    function cumpleLuhn(numero) {
        if (!/^\d{13,19}$/.test(numero)) {
            return false;
        }
        var suma = 0;
        var duplicar = false;
        for (var posicion = numero.length - 1; posicion >= 0; posicion--) {
            var digito = parseInt(numero.charAt(posicion), 10);
            if (duplicar) {
                digito *= 2;
                if (digito > 9) {
                    digito -= 9;
                }
            }
            suma += digito;
            duplicar = !duplicar;
        }
        return suma % 10 === 0;
    }

    function valorDe(campo) {
        if (campo.type === 'checkbox') {
            return campo.checked ? '1' : '';
        }
        if (campo.type === 'radio') {
            var marcado = campo.form.querySelector('input[type="radio"][name="' + campo.name + '"]:checked');
            return marcado ? marcado.value : '';
        }
        if (campo.type === 'file') {
            return campo.files.length ? 'archivo' : '';
        }
        return (campo.value || '').trim();
    }

    /** Devuelve el mensaje de error de una regla o cadena vacía si se cumple. */
    function evaluarRegla(campo, regla, valor) {
        var partes = regla.split(':');
        var nombreRegla = partes[0];

        switch (nombreRegla) {
            case 'requerido':
            case 'seleccion':
                return valor === '' || valor === '0' && nombreRegla === 'seleccion' ? 'Este campo es obligatorio.' : '';
            case 'email':
                return patrones.email.test(valor) && valor.length <= 120 ? '' : 'Escriba un correo electrónico válido (ejemplo: nombre@dominio.com).';
            case 'contrasena':
                return patrones.contrasena.test(valor) ? '' : 'La contraseña debe tener de 8 a 64 caracteres, con mayúscula, minúscula, número y símbolo.';
            case 'confirmar':
                var original = document.getElementById(partes[1]);
                return original && original.value === campo.value ? '' : 'Las contraseñas no coinciden.';
            case 'nombre':
                return patrones.nombre.test(valor) ? '' : 'Use solo letras y espacios (de 2 a 80 caracteres).';
            case 'rfc':
                return patrones.rfc.test(valor.toUpperCase()) ? '' : 'El RFC debe tener 13 caracteres con formato válido (ejemplo: HELC900515AB1).';
            case 'curp':
                return patrones.curp.test(valor.toUpperCase()) ? '' : 'La CURP debe tener 18 caracteres con formato válido.';
            case 'telefono':
                return patrones.telefono.test(valor) ? '' : 'El teléfono debe tener exactamente 10 dígitos.';
            case 'cp':
                return patrones.cp.test(valor) ? '' : 'El código postal debe tener 5 dígitos.';
            case 'tarjeta':
                return cumpleLuhn(valor.replace(/\s+/g, '')) ? '' : 'El número de tarjeta no es válido.';
            case 'cvv':
                return patrones.cvv.test(valor) ? '' : 'El CVV debe tener 3 o 4 dígitos.';
            case 'mes':
                var mes = parseInt(valor, 10);
                return /^\d{1,2}$/.test(valor) && mes >= 1 && mes <= 12 ? '' : 'Seleccione un mes válido.';
            case 'anio':
                var anioActual = new Date().getFullYear();
                var anio = parseInt(valor, 10);
                return /^\d{4}$/.test(valor) && anio >= anioActual && anio <= anioActual + 20 ? '' : 'Seleccione un año válido.';
            case 'vencimiento':
                var campoMes = document.getElementById(partes[1]);
                var campoAnio = document.getElementById(partes[2]);
                if (!campoMes || !campoAnio || !campoMes.value || !campoAnio.value) {
                    return 'Indique el mes y el año de vencimiento.';
                }
                var hoy = new Date();
                var fechaTarjeta = parseInt(campoAnio.value, 10) * 100 + parseInt(campoMes.value, 10);
                var fechaActual = hoy.getFullYear() * 100 + (hoy.getMonth() + 1);
                return fechaTarjeta >= fechaActual ? '' : 'La tarjeta está vencida.';
            case 'precio':
                return patrones.precio.test(valor) && parseFloat(valor) > 0 ? '' : 'Escriba un precio mayor a 0 con hasta dos decimales.';
            case 'entero':
                var minimo = partes[1] !== undefined ? parseInt(partes[1], 10) : 0;
                var maximo = partes[2] !== undefined ? parseInt(partes[2], 10) : 999999;
                var numero = parseInt(valor, 10);
                return patrones.entero.test(valor) && numero >= minimo && numero <= maximo ? '' : 'Escriba un número entero entre ' + minimo + ' y ' + maximo + '.';
            case 'texto':
                var longitudMaxima = parseInt(partes[1] || '255', 10);
                if (valor === '') {
                    return 'Este campo es obligatorio.';
                }
                return valor.length <= longitudMaxima ? '' : 'El texto no debe exceder ' + longitudMaxima + ' caracteres.';
            case 'sku':
                return patrones.sku.test(valor.toUpperCase()) ? '' : 'Use de 3 a 30 caracteres: letras, números o guiones.';
            case 'imagenes':
                for (var indice = 0; indice < campo.files.length; indice++) {
                    var archivo = campo.files[indice];
                    if (tiposImagen.indexOf(archivo.type) === -1) {
                        return '«' + archivo.name + '» no es una imagen JPG, PNG, WEBP o GIF.';
                    }
                    if (archivo.size > tamanoMaximoImagen) {
                        return '«' + archivo.name + '» excede el tamaño máximo de 5 MB.';
                    }
                }
                return '';
            case 'estrellas':
                var estrellas = parseInt(valor, 10);
                return /^[0-5]$/.test(valor) && estrellas >= 0 && estrellas <= 5 ? '' : 'Seleccione de 0 a 5 estrellas.';
            case 'busqueda':
                return valor.length <= 100 ? '' : 'La búsqueda no debe exceder 100 caracteres.';
            default:
                return '';
        }
    }

    function mostrarError(campo, mensaje) {
        var contenedor = campo.closest('.campo') || campo.parentNode;
        var elementoError = contenedor.querySelector('.error-campo');
        if (!elementoError) {
            elementoError = document.createElement('div');
            elementoError.className = 'invalid-feedback d-block error-campo';
            contenedor.appendChild(elementoError);
        }
        elementoError.textContent = mensaje;
        campo.classList.toggle('is-invalid', mensaje !== '');
        campo.setAttribute('aria-invalid', mensaje !== '' ? 'true' : 'false');
    }

    function validarCampo(campo) {
        var reglas = (campo.getAttribute('data-regla') || '').split('|').filter(Boolean);
        var valor = valorDe(campo);
        var esOpcional = campo.hasAttribute('data-opcional');
        var mensaje = '';

        if (campo.disabled) {
            return true;
        }
        if (valor === '' && esOpcional && campo.type !== 'file') {
            mostrarError(campo, '');
            return true;
        }
        if (valor === '' && !esOpcional && reglas.indexOf('busqueda') === -1 && reglas.indexOf('imagenes') === -1) {
            mensaje = 'Este campo es obligatorio.';
        }
        for (var indice = 0; indice < reglas.length && mensaje === ''; indice++) {
            if (reglas[indice] === 'imagenes' && valor === '') {
                continue;
            }
            mensaje = evaluarRegla(campo, reglas[indice], valor);
        }
        mostrarError(campo, mensaje);
        return mensaje === '';
    }

    function validarFormulario(formulario) {
        var campos = formulario.querySelectorAll('[data-regla]');
        var primerInvalido = null;
        for (var indice = 0; indice < campos.length; indice++) {
            if (!validarCampo(campos[indice]) && primerInvalido === null) {
                primerInvalido = campos[indice];
            }
        }
        if (primerInvalido) {
            primerInvalido.focus();
            return false;
        }
        return true;
    }

    // Validación en vivo al salir de cada campo y al corregirlo
    document.addEventListener('focusout', function (evento) {
        var campo = evento.target;
        if (campo.matches && campo.matches('form[data-validar] [data-regla]') && valorDe(campo) !== '') {
            validarCampo(campo);
        }
    });
    document.addEventListener('input', function (evento) {
        var campo = evento.target;
        if (campo.matches && campo.matches('[data-regla].is-invalid')) {
            validarCampo(campo);
        }
        // Convierte a mayúsculas RFC, CURP y SKU mientras se escribe
        if (campo.matches && campo.matches('[data-mayusculas]')) {
            var posicion = campo.selectionStart;
            campo.value = campo.value.toUpperCase();
            campo.setSelectionRange(posicion, posicion);
        }
        // Solo dígitos en campos numéricos
        if (campo.matches && campo.matches('[data-solo-digitos]')) {
            campo.value = campo.value.replace(/\D+/g, '');
        }
    });

    window.Validaciones = {
        validarFormulario: validarFormulario,
        validarCampo: validarCampo,
        cumpleLuhn: cumpleLuhn
    };
})();
