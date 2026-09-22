<?php
namespace App\Controllers;

use App\Helpers\View;
use App\Helpers\Auth;
use App\Helpers\Session;
use App\Models\Specialty;

class AdminSpecialtyController
{
    private Specialty $specialtyModel;

    public function __construct()
    {
        Auth::require();
        $this->specialtyModel = new Specialty();
    }

    public function index()
    {
        Auth::requirePermission('specialties_read');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 10;
        $paginationData = $this->specialtyModel->paginate($page, $perPage);
        $specialties = $paginationData['data'];

        $db = \App\Helpers\Database::getInstance();
        $catalogTypes = $db->fetchAll("SELECT * FROM catalog_types ORDER BY name ASC");
        
        $catalogTypeMap = [];
        foreach ($catalogTypes as $ct) {
            $catalogTypeMap[$ct['code']] = $ct['name'];
        }

        $medicalIcons = [
            'stethoscope' => 'Estetoscopio',
            'heart-pulse' => 'Cardiología / Pulso',
            'tooth' => 'Odontología',
            'eye' => 'Oftalmología',
            'brain' => 'Neurología',
            'bone' => 'Traumatología',
            'lungs' => 'Neumología',
            'staff-snake' => 'Medicina General',
            'microscope' => 'Laboratorio / Microscopio',
            'pills' => 'Farmacia / Pastillas',
            'syringe' => 'Vacunación / Jeringa',
            'user-doctor' => 'Médico',
            'x-ray' => 'Rayos X / Imagenología',
            'dna' => 'Genética',
            'virus' => 'Infectología / Virus',
            'vial' => 'Muestras / Vial',
            'baby' => 'Pediatría',
            'wheelchair' => 'Discapacidad / Movilidad'
        ];

        View::render('admin.specialties.index', [
            'title' => 'Gestión de Especialidades y Servicios',
            'specialties' => $specialties,
            'catalogTypes' => $catalogTypes,
            'catalogTypeMap' => $catalogTypeMap,
            'medicalIcons' => $medicalIcons,
            'currentPage' => $paginationData['current_page'],
            'totalPages' => $paginationData['total_pages'],
            'totalRecords' => $paginationData['total_records'],
            'perPage' => $paginationData['per_page']
        ], 'admin');
    }

    public function store()
    {
        Auth::requirePermission('specialties_create');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Petición inválida.');
            View::redirect('/admin/specialties');
        }

        try {
            $this->specialtyModel->create([
                'name' => trim($_POST['name']),
                'description' => trim($_POST['description'] ?? ''),
                'icon' => trim($_POST['icon'] ?? 'stethoscope'),
                'catalog_type_code' => !empty($_POST['catalog_type_code']) ? $_POST['catalog_type_code'] : null,
                'status' => 'active'
            ]);

            Session::flash('success', 'Especialidad creada correctamente.');
        } catch (\Exception $e) {
            Session::flash('error', 'Error al crear la especialidad: ' . $e->getMessage());
        }

        View::redirect('/admin/specialties');
    }

    public function edit(string $id)
    {
        Auth::requirePermission('specialties_update');
        $specialty = $this->specialtyModel->findById((int)$id);
        if (!$specialty) {
            Session::flash('error', 'Especialidad no encontrada.');
            View::redirect('/admin/specialties');
        }

        $db = \App\Helpers\Database::getInstance();
        $catalogTypes = $db->fetchAll("SELECT * FROM catalog_types ORDER BY name ASC");

        $medicalIcons = [
            'stethoscope' => 'Estetoscopio',
            'heart-pulse' => 'Cardiología / Pulso',
            'tooth' => 'Odontología',
            'eye' => 'Oftalmología',
            'brain' => 'Neurología',
            'bone' => 'Traumatología',
            'lungs' => 'Neumología',
            'staff-snake' => 'Medicina General',
            'microscope' => 'Laboratorio / Microscopio',
            'pills' => 'Farmacia / Pastillas',
            'syringe' => 'Vacunación / Jeringa',
            'user-doctor' => 'Médico',
            'x-ray' => 'Rayos X / Imagenología',
            'dna' => 'Genética',
            'virus' => 'Infectología / Virus',
            'vial' => 'Muestras / Vial',
            'baby' => 'Pediatría',
            'wheelchair' => 'Discapacidad / Movilidad'
        ];

        View::render('admin.specialties.edit', [
            'title' => 'Editar Especialidad / Servicio',
            'specialty' => $specialty,
            'catalogTypes' => $catalogTypes,
            'medicalIcons' => $medicalIcons
        ], 'admin');
    }

    public function update(string $id)
    {
        Auth::requirePermission('specialties_update');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Petición inválida.');
            View::redirect('/admin/specialties');
        }

        try {
            $this->specialtyModel->update((int)$id, [
                'name' => trim($_POST['name']),
                'description' => trim($_POST['description'] ?? ''),
                'icon' => trim($_POST['icon'] ?? 'stethoscope'),
                'catalog_type_code' => !empty($_POST['catalog_type_code']) ? $_POST['catalog_type_code'] : null
            ]);

            Session::flash('success', 'Especialidad actualizada correctamente.');
        } catch (\Exception $e) {
            Session::flash('error', 'Error al actualizar la especialidad.');
        }

        View::redirect('/admin/specialties');
    }

    public function delete(string $id)
    {
        Auth::requirePermission('specialties_delete');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Petición inválida.');
            View::redirect('/admin/specialties');
        }

        try {
            $this->specialtyModel->delete((int)$id);
            Session::flash('success', 'Especialidad eliminada correctamente.');
        } catch (\Exception $e) {
            Session::flash('error', 'No se puede eliminar porque hay médicos o servicios asociados a ella.');
        }

        View::redirect('/admin/specialties');
    }

    public function toggleStatus(string $id)
    {
        Auth::requirePermission('specialties_update');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Petición inválida.');
            View::redirect('/admin/specialties');
        }

        try {
            $specialty = $this->specialtyModel->findById((int)$id);
            if ($specialty) {
                $newStatus = $specialty['status'] === 'active' ? 'inactive' : 'active';
                $this->specialtyModel->update((int)$id, ['status' => $newStatus]);
                Session::flash('success', "Estado actualizado correctamente.");
            }
        } catch (\Exception $e) {
            Session::flash('error', 'Error al cambiar el estado.');
        }

        View::redirect('/admin/specialties');
    }

    /**
     * Crea una especialidad vía AJAX y retorna JSON.
     * Usado desde el formulario de creación de médicos para no perder datos.
     */
    public function storeAjax(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!Auth::hasRole('admin') && !Auth::hasPermission('specialties_create')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No dispone de permisos para crear especialidades (Permiso requerido: specialties_create).']);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
            exit;
        }

        // Leer JSON body o POST form
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        if (!Session::validateCsrf($input['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Token CSRF inválido.']);
            exit;
        }

        $name = trim($input['name'] ?? '');
        if (empty($name)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'El nombre de la especialidad es obligatorio.']);
            exit;
        }

        try {
            $id = $this->specialtyModel->create([
                'name'        => $name,
                'description' => trim($input['description'] ?? ''),
                'icon'        => trim($input['icon'] ?? 'stethoscope'),
                'catalog_type_code' => !empty($input['catalog_type_code']) ? $input['catalog_type_code'] : null,
                'status'      => 'active'
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Especialidad creada correctamente.',
                'specialty' => [
                    'id'   => $id,
                    'name' => $name
                ]
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error al crear: ' . $e->getMessage()]);
        }
        exit;
    }
}
