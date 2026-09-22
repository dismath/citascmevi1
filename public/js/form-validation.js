/**
 * Sistema Centralizado de Validación de Formularios y Capitalización
 * Portal Salud / Clínica CMEVI
 * 
 * Reglas implementadas:
 * 1. Campos de Nombre (name): Solo acepta letras y números (con tildes, ñ y puntuación básica).
 *    Capitaliza automáticamente la primera letra de cada palabra.
 * 2. Campos de Dirección (address): Solo acepta letras y números (con puntuación de dirección .,#-/).
 *    Capitaliza automáticamente la primera letra.
 * 3. Campos de Identificación (id_number): Solo acepta números (excepto si se selecciona pasaporte/extranjera).
 * 4. Campos de Teléfono (phone): Solo acepta números.
 * 5. Todos los campos de texto (input[type="text"], textarea): Escribe al inicio la primera letra con Mayúscula.
 */

(function() {
    'use strict';

    // Función auxiliar para transformar el valor manteniendo la posición del cursor
    function transformInput(input, transformFn) {
        if (!input || input.readOnly || input.disabled) return;
        const start = input.selectionStart;
        const end = input.selectionEnd;
        const oldVal = input.value;
        if (oldVal === undefined || oldVal === null) return;

        const newVal = transformFn(oldVal);
        if (oldVal !== newVal) {
            input.value = newVal;
            const diff = newVal.length - oldVal.length;
            if (start !== null && end !== null) {
                const newStart = Math.max(0, start + diff);
                const newEnd = Math.max(0, end + diff);
                try {
                    input.setSelectionRange(newStart, newEnd);
                } catch (e) {
                    // Algunos inputs especiales no soportan setSelectionRange
                }
            }
        }
    }

    // Capitalizar la primera letra al inicio del texto
    function capitalizeFirstLetter(str) {
        if (!str || str.length === 0) return str;
        // Convierte el primer carácter alfabético a mayúscula respetando espacios iniciales
        return str.replace(/^(\s*)([a-z\u00e0-\u00ff])/i, function(match, space, letter) {
            return space + letter.toUpperCase();
        });
    }

    // Capitalizar la primera letra de cada palabra (ideal para nombres y títulos)
    function capitalizeWords(str) {
        if (!str || str.length === 0) return str;
        // Capitaliza la primera letra al inicio o tras espacio/guión
        return str.replace(/(?:^|[\s\-])([a-z\u00e0-\u00ff])/g, function(match) {
            return match.toUpperCase();
        });
    }

    // Comprueba si una tecla de evento keydown es tecla de control
    function isControlKey(e) {
        if (e.ctrlKey || e.metaKey || e.altKey) return true;
        const navKeys = [
            'Backspace', 'Delete', 'Tab', 'Enter', 'Escape',
            'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown',
            'Home', 'End', 'PageUp', 'PageDown'
        ];
        return navKeys.includes(e.key);
    }

    // Verifica si un campo de identificación permite pasaporte o ID extranjera actualmente
    function isAlternativeDocActive(input) {
        const form = input.closest('form');
        if (!form) return false;
        const docTypeHidden = form.querySelector('#document_type_hidden') || form.querySelector('input[name="document_type"]');
        if (docTypeHidden) {
            const val = docTypeHidden.value;
            return val === 'pasaporte' || val === 'id_extranjera';
        }
        return false;
    }

    // Determinar si el campo pertenece exclusivamente a los formularios de Pacientes, Médicos o Usuarios/Personal
    function isEligibleForm(input) {
        if (!input) return false;
        
        const currentPath = (window.location.pathname || '').toLowerCase();
        // Descartar inmediatamente páginas de login o registro de citas médicas
        if (currentPath.includes('/login') || currentPath.includes('/booking') || currentPath.includes('/appointment')) {
            return false;
        }

        const form = input.closest('form');
        if (form) {
            const action = (form.getAttribute('action') || '').toLowerCase();
            const formId = (form.getAttribute('id') || '').toLowerCase();

            // Excluir explícitamente login o citas médicas
            if (action.includes('login') || action.includes('booking') || action.includes('appointment') ||
                formId.includes('login') || formId.includes('booking') || formId.includes('appointment')) {
                return false;
            }

            // Aceptar únicamente si pertenece a pacientes, médicos o usuarios/staff
            const matchesTarget = 
                action.includes('/patients') || formId.includes('patient') ||
                action.includes('/doctors') || formId.includes('doctor') ||
                action.includes('/users') || formId.includes('user') ||
                action.includes('/staff') || formId.includes('staff') ||
                currentPath.includes('/patients') || currentPath.includes('/doctors') ||
                currentPath.includes('/users') || currentPath.includes('/staff');

            return matchesTarget;
        }

        // Si no está dentro de un <form>, solo permitir si la ruta es de pacientes, médicos o usuarios
        return currentPath.includes('/patients') || currentPath.includes('/doctors') ||
               currentPath.includes('/users') || currentPath.includes('/staff');
    }

    // Inicializar validaciones en un elemento específico
    function bindValidationToInput(input) {
        if (!input || input.dataset.validationBound) return;
        if (!isEligibleForm(input)) return;
        input.dataset.validationBound = 'true';

        const nameAttr = (input.getAttribute('name') || '').toLowerCase();
        const idAttr = (input.getAttribute('id') || '').toLowerCase();
        const typeAttr = (input.getAttribute('type') || '').toLowerCase();
        const tagName = input.tagName.toLowerCase();

        // Identificar tipo de campo
        const isPhone = nameAttr === 'phone' || nameAttr.includes('phone') || 
                        nameAttr.includes('telefono') || nameAttr.includes('celular') || 
                        typeAttr === 'tel' || idAttr === 'phone';

        const isIdNumber = nameAttr === 'id_number' || nameAttr === 'cedula' || 
                           nameAttr === 'dni' || idAttr === 'id_number' || 
                           idAttr === 'id_number_input';

        const isName = nameAttr === 'name' || nameAttr === 'first_name' || 
                       nameAttr === 'last_name' || idAttr === 'name' || 
                       nameAttr.includes('nombre');

        const isAddress = nameAttr === 'address' || nameAttr.includes('address') || 
                          nameAttr.includes('direccion') || idAttr === 'address';

        // ----------------------------------------------------
        // 1. Teléfono: Solo números
        // ----------------------------------------------------
        if (isPhone) {
            input.setAttribute('inputmode', 'numeric');
            input.setAttribute('pattern', '[0-9]*');

            input.addEventListener('keydown', function(e) {
                if (isControlKey(e)) return;
                if (!/^[0-9]$/.test(e.key)) {
                    e.preventDefault();
                }
            });

            input.addEventListener('input', function() {
                transformInput(this, function(val) {
                    return val.replace(/[^0-9]/g, '');
                });
            });
            return;
        }

        // ----------------------------------------------------
        // 2. Identificación: Solo números (salvo pasaporte/extranjera)
        // ----------------------------------------------------
        if (isIdNumber) {
            input.setAttribute('inputmode', 'numeric');

            input.addEventListener('keydown', function(e) {
                if (isAlternativeDocActive(this)) return; // Si es pasaporte, permitir caracteres
                if (isControlKey(e)) return;
                if (!/^[0-9]$/.test(e.key)) {
                    e.preventDefault();
                }
            });

            input.addEventListener('input', function() {
                if (isAlternativeDocActive(this)) {
                    // Pasaporte / ID extranjera: letras y números
                    transformInput(this, function(val) {
                        return val.replace(/[^a-zA-Z0-9\-]/g, '').toUpperCase();
                    });
                } else {
                    // Cédula / RUC / General: Solo números
                    transformInput(this, function(val) {
                        return val.replace(/[^0-9]/g, '');
                    });
                }
            });
            return;
        }

        // ----------------------------------------------------
        // 3. Nombre: Solo letras y números, capitaliza cada palabra
        // ----------------------------------------------------
        if (isName) {
            input.addEventListener('input', function() {
                transformInput(this, function(val) {
                    // Permitir letras, números, espacios, acentos, ñ, puntos y guiones
                    let cleaned = val.replace(/[^a-zA-Z0-9\sáéíóúÁÉÍÓÚñÑ.,\-]/g, '');
                    return capitalizeWords(cleaned);
                });
            });
            return;
        }

        // ----------------------------------------------------
        // 4. Dirección: Solo letras y números con puntuación común, primera mayúscula
        // ----------------------------------------------------
        if (isAddress) {
            input.addEventListener('input', function() {
                transformInput(this, function(val) {
                    // Permitir letras, números, espacios, acentos, ñ y caracteres de dirección: .,#-/°
                    let cleaned = val.replace(/[^a-zA-Z0-9\sáéíóúÁÉÍÓÚñÑ.,#\-\/°ºª]/g, '');
                    return capitalizeFirstLetter(cleaned);
                });
            });
            return;
        }

        // ----------------------------------------------------
        // 5. Todos los demás campos de texto: Primera letra en Mayúscula
        // ----------------------------------------------------
        // Se excluyen emails, passwords, dates, numbers, hidden, tokens, checkboxes, radio, etc.
        const excludedTypes = ['email', 'password', 'number', 'date', 'datetime-local', 'time', 'hidden', 'checkbox', 'radio', 'file', 'color', 'range'];
        if ((tagName === 'input' && !excludedTypes.includes(typeAttr)) || tagName === 'textarea') {
            // Ignorar campos marcados explícitamente como no-capitalize o csrf
            if (input.classList.contains('no-capitalize') || nameAttr.includes('token') || nameAttr.includes('csrf')) {
                return;
            }

            input.addEventListener('input', function() {
                transformInput(this, function(val) {
                    return capitalizeFirstLetter(val);
                });
            });
        }
    }

    // Escanear el DOM y enlazar todos los campos elegibles
    function initFormValidation(container) {
        const root = container || document;
        const inputs = root.querySelectorAll('input, textarea');
        inputs.forEach(bindValidationToInput);
    }

    // Inicializar al cargar el DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            initFormValidation();
        });
    } else {
        initFormValidation();
    }

    // Observar inserciones dinámicas en el DOM (modales, AJAX, etc.)
    if (window.MutationObserver) {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1) { // ELEMENT_NODE
                        if (node.matches && (node.matches('input') || node.matches('textarea'))) {
                            bindValidationToInput(node);
                        }
                        if (node.querySelectorAll) {
                            initFormValidation(node);
                        }
                    }
                });
            });
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }

    // Exponer globalmente para re-inicializaciones manuales si se requiere
    window.initFormValidation = initFormValidation;
})();

/* ============================================================
   PREVENCIÓN DE DOBLE ENVÍO GLOBAL
   Aplica a todos los formularios POST de la aplicación.
   ============================================================ */
(function () {
    'use strict';

    // Texto / ícono de carga que reemplaza al botón submit
    var LOADING_HTML = '<i class="fa-solid fa-circle-notch fa-spin" style="margin-right:6px;"></i> Procesando...';

    /**
     * Inicializa la prevención de doble submit en un contenedor (document o modal).
     * @param {Element} root - Elemento raíz donde buscar formularios
     */
    function initDoubleSubmitPrevention(root) {
        root = root || document;
        var forms = root.querySelectorAll('form[method="post"], form[method="POST"]');
        forms.forEach(function (form) {
            if (form.dataset.dsInit) return; // Ya inicializado
            form.dataset.dsInit = '1';

            form.addEventListener('submit', function (e) {
                // Ignorar si la validación HTML5 nativa falla
                if (typeof form.checkValidity === 'function' && !form.checkValidity()) return;

                // Buscar el botón submit que disparó el evento (puede ser varios)
                var submitBtns = form.querySelectorAll('button[type="submit"], input[type="submit"]');
                submitBtns.forEach(function (btn) {
                    // Guardar estado original
                    if (!btn.dataset.originalHtml) {
                        btn.dataset.originalHtml = btn.innerHTML || btn.value;
                        btn.dataset.originalWidth = btn.offsetWidth + 'px';
                    }
                    // Fijar ancho para evitar salto visual al cambiar texto
                    btn.style.minWidth = btn.dataset.originalWidth;
                    // Deshabilitar y mostrar spinner
                    btn.disabled = true;
                    if (btn.tagName === 'BUTTON') {
                        btn.innerHTML = LOADING_HTML;
                    } else {
                        btn.value = 'Procesando...';
                    }
                });

                // Seguridad extra: re-habilitar después de 30s por si algo falla
                setTimeout(function () {
                    restoreForm(form);
                }, 30000);
            });
        });
    }

    /**
     * Restaura todos los botones submit de un formulario a su estado original.
     */
    function restoreForm(form) {
        var submitBtns = form.querySelectorAll('button[type="submit"], input[type="submit"]');
        submitBtns.forEach(function (btn) {
            btn.disabled = false;
            if (btn.dataset.originalHtml) {
                if (btn.tagName === 'BUTTON') {
                    btn.innerHTML = btn.dataset.originalHtml;
                } else {
                    btn.value = btn.dataset.originalHtml;
                }
            }
        });
    }

    // Inicializar en carga
    document.addEventListener('DOMContentLoaded', function () {
        initDoubleSubmitPrevention(document);
    });

    // Re-habilitar botones si el usuario vuelve atrás con el navegador (bfcache)
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) {
            document.querySelectorAll('form[data-ds-init]').forEach(function (form) {
                restoreForm(form);
            });
        }
    });

    // Observar modales / contenido dinámico agregado al DOM
    if (window.MutationObserver) {
        var dsObserver = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                mutation.addedNodes.forEach(function (node) {
                    if (node.nodeType === 1 && node.querySelectorAll) {
                        initDoubleSubmitPrevention(node);
                    }
                });
            });
        });
        if (document.body) {
            dsObserver.observe(document.body, { childList: true, subtree: true });
        }
    }

    // Exponer globalmente para modales/ajax
    window.initDoubleSubmitPrevention = initDoubleSubmitPrevention;
    window.restoreFormSubmit = function (formOrSelector) {
        var form = typeof formOrSelector === 'string'
            ? document.querySelector(formOrSelector)
            : formOrSelector;
        if (form) restoreForm(form);
    };
})();
