<?php
namespace App\Helpers;

use App\Models\SystemSetting;

class Validator
{
    /**
     * Consulta si la validación de cédula ecuatoriana está activada en configuración.
     * Por defecto está activada (si no existe el setting).
     */
    public static function isEcuadorianIdValidationEnabled(): bool
    {
        $settingsModel = new SystemSetting();
        $value = $settingsModel->get('validate_ecuadorian_id');
        // Si no existe el setting o es '1', está activado
        return $value === null || $value === '1';
    }

    /**
     * Valida cédula ecuatoriana (10 dígitos) o RUC (13 dígitos).
     * Detecta automáticamente el tipo por longitud y tercer dígito.
     * Si la validación está desactivada en configuración, retorna true sin verificar.
     */
    public static function isValidEcuadorianId(string $idNumber): bool
    {
        // Si la validación está desactivada, aceptar cualquier documento
        if (!self::isEcuadorianIdValidationEnabled()) {
            return true;
        }

        $len = strlen($idNumber);

        // Solo acepta 10 dígitos (cédula) o 13 dígitos (RUC)
        if ($len === 10) {
            return self::isValidCedula($idNumber);
        } elseif ($len === 13) {
            return self::isValidRuc($idNumber);
        }

        return false;
    }

    /**
     * Valida cédula ecuatoriana (10 dígitos exactos).
     * Algoritmo Módulo 10 con coeficientes [2,1,2,1,2,1,2,1,2].
     */
    public static function isValidCedula(string $cedula): bool
    {
        if (!preg_match('/^[0-9]{10}$/', $cedula)) {
            return false;
        }

        $provincia = (int)substr($cedula, 0, 2);
        if ($provincia < 1 || $provincia > 24) {
            return false;
        }

        $tercerDigito = (int)$cedula[2];
        if ($tercerDigito >= 6) {
            return false;
        }

        return self::validarModulo10($cedula);
    }

    /**
     * Valida RUC ecuatoriano (13 dígitos exactos).
     * Detecta el tipo por el tercer dígito y aplica el algoritmo correspondiente.
     */
    public static function isValidRuc(string $ruc): bool
    {
        if (!preg_match('/^[0-9]{13}$/', $ruc)) {
            return false;
        }

        $provincia = (int)substr($ruc, 0, 2);
        if ($provincia < 1 || $provincia > 24) {
            return false;
        }

        $tercerDigito = (int)$ruc[2];

        // Persona natural (tercer dígito 0-5)
        if ($tercerDigito >= 0 && $tercerDigito <= 5) {
            // Los últimos 3 dígitos deben ser 001 o mayor (código de establecimiento)
            $establecimiento = substr($ruc, 10, 3);
            if ($establecimiento === '000') {
                return false;
            }
            return self::validarModulo10($ruc);
        }
        // Sociedad pública (tercer dígito 6)
        elseif ($tercerDigito === 6) {
            // Los últimos 4 dígitos deben ser 0001 o mayor (código de establecimiento)
            $establecimiento = substr($ruc, 9, 4);
            if ($establecimiento === '0000') {
                return false;
            }
            return self::validarModulo11SociedadPublica($ruc);
        }
        // Sociedad privada / extranjero sin cédula (tercer dígito 9)
        elseif ($tercerDigito === 9) {
            // Los últimos 3 dígitos deben ser 001 o mayor (código de establecimiento)
            $establecimiento = substr($ruc, 10, 3);
            if ($establecimiento === '000') {
                return false;
            }
            return self::validarModulo11SociedadPrivada($ruc);
        }

        return false;
    }

    /**
     * Módulo 10: Para cédulas y RUC de persona natural.
     * Coeficientes: [2,1,2,1,2,1,2,1,2]. Dígito verificador en posición 9.
     */
    private static function validarModulo10(string $numero): bool
    {
        $coeficientes = [2, 1, 2, 1, 2, 1, 2, 1, 2];
        $suma = 0;

        for ($i = 0; $i < 9; $i++) {
            $valor = (int)$numero[$i] * $coeficientes[$i];
            if ($valor > 9) {
                $valor -= 9;
            }
            $suma += $valor;
        }

        $digitoVerificador = (int)$numero[9];
        $residuo = $suma % 10;
        $resultado = ($residuo === 0) ? 0 : (10 - $residuo);

        return $resultado === $digitoVerificador;
    }

    /**
     * Módulo 11: Para RUC de sociedad pública (tercer dígito = 6).
     * Coeficientes: [3,2,7,6,5,4,3,2]. Dígito verificador en posición 8.
     */
    private static function validarModulo11SociedadPublica(string $ruc): bool
    {
        $coeficientes = [3, 2, 7, 6, 5, 4, 3, 2];
        $suma = 0;

        for ($i = 0; $i < 8; $i++) {
            $suma += (int)$ruc[$i] * $coeficientes[$i];
        }

        $digitoVerificador = (int)$ruc[8];
        $residuo = $suma % 11;
        $resultado = ($residuo === 0) ? 0 : (11 - $residuo);

        return $resultado === $digitoVerificador;
    }

    /**
     * Módulo 11: Para RUC de sociedad privada (tercer dígito = 9).
     * Coeficientes: [4,3,2,7,6,5,4,3,2]. Dígito verificador en posición 9.
     */
    private static function validarModulo11SociedadPrivada(string $ruc): bool
    {
        $coeficientes = [4, 3, 2, 7, 6, 5, 4, 3, 2];
        $suma = 0;

        for ($i = 0; $i < 9; $i++) {
            $suma += (int)$ruc[$i] * $coeficientes[$i];
        }

        $digitoVerificador = (int)$ruc[9];
        $residuo = $suma % 11;
        $resultado = ($residuo === 0) ? 0 : (11 - $residuo);

        return $resultado === $digitoVerificador;
    }

    /**
     * Valida política de contraseñas seguras
     * Mínimo 8 caracteres, al menos 1 mayúscula, 1 minúscula, 1 número.
     */
    public static function isStrongPassword(string $password): bool
    {
        return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $password);
    }

    /**
     * Valida que el teléfono contenga solo números.
     */
    public static function isValidPhone(string $phone): bool
    {
        return preg_match('/^[0-9]+$/', $phone) === 1;
    }

    /**
     * Valida nombre: solo letras y números (con tildes, ñ y puntuación básica).
     */
    public static function isValidPatientName(string $name, bool $allowNumbers = true): bool
    {
        return preg_match('/^[a-zA-Z0-9\s.,áéíóúÁÉÍÓÚñÑ\-]+$/u', $name) === 1;
    }

    /**
     * Valida nombre genérico: solo letras y números (con tildes, ñ y puntuación básica).
     */
    public static function isValidName(string $name): bool
    {
        return preg_match('/^[a-zA-Z0-9\s.,áéíóúÁÉÍÓÚñÑ\-]+$/u', $name) === 1;
    }

    /**
     * Valida dirección: solo letras, números y caracteres básicos (, . # - / °).
     */
    public static function isValidAddress(string $address): bool
    {
        return preg_match('/^[a-zA-Z0-9\s.,#\-\/°ºªáéíóúÁÉÍÓÚñÑ]+$/u', $address) === 1;
    }

    /**
     * Capitaliza la primera letra de un texto respetando caracteres multi-byte UTF-8.
     */
    public static function capitalizeFirst(string $str): string
    {
        $str = trim($str);
        if ($str === '') return '';
        $firstChar = mb_substr($str, 0, 1, 'UTF-8');
        $remainder = mb_substr($str, 1, null, 'UTF-8');
        return mb_strtoupper($firstChar, 'UTF-8') . $remainder;
    }

    /**
     * Capitaliza la primera letra de cada palabra respetando UTF-8 (tildes, ñ).
     */
    public static function capitalizeWords(string $str): string
    {
        $str = trim($str);
        if ($str === '') return '';
        return mb_convert_case($str, MB_CASE_TITLE, 'UTF-8');
    }
}

