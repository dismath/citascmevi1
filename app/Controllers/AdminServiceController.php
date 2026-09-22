<?php
namespace App\Controllers;

use App\Helpers\View;
use App\Helpers\Auth;
use App\Helpers\Session;

class AdminServiceController
{
    private \App\Models\BaseModel $catalogModel;

    public function __construct()
    {
        Auth::require();
        // Create an anonymous class extending BaseModel to interact with catalogs table
        $this->catalogModel = new class extends \App\Models\BaseModel {
            protected string $table = 'catalogs';
            
            public function getAllWithTypes() {
                return $this->db->fetchAll("
                    SELECT c.*, ct.name as type_name, ct.color 
                    FROM catalogs c 
                    LEFT JOIN catalog_types ct ON c.catalog_type = ct.code
                    ORDER BY c.catalog_type ASC, c.category ASC, c.name ASC
                ");
            }

            public function getCategories() {
                return $this->db->fetchAll("SELECT * FROM catalog_categories ORDER BY name ASC");
            }
            
            public function getTypes() {
                return $this->db->fetchAll("SELECT * FROM catalog_types ORDER BY name ASC");
            }
        };
    }

    public function index()
    {
        Auth::requirePermission('services_read');
        $catalogs = $this->catalogModel->getAllWithTypes();
        $types = $this->catalogModel->getTypes();
        $categories = $this->catalogModel->getCategories();
        
        $db = \App\Helpers\Database::getInstance();
        $catalogCategories = $db->fetchAll("SELECT * FROM catalog_categories ORDER BY catalog_type ASC, name ASC");
        $catalogItems = $db->fetchAll("SELECT * FROM catalogs ORDER BY catalog_type ASC, category ASC, name ASC");

        View::render('admin.services.index', [
            'title' => 'Catálogo de Exámenes y Servicios',
            'catalogs' => $catalogs,
            'types' => $types,
            'categories' => $categories,
            'catalogCategories' => $catalogCategories,
            'catalogItems' => $catalogItems
        ], 'admin');
    }

    public function store()
    {
        Auth::requirePermission('services_create');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Petición inválida.');
            View::redirect('/admin/services');
        }

        try {
            $this->catalogModel->create([
                'catalog_type' => $_POST['catalog_type'],
                'category' => $_POST['category'],
                'name' => $_POST['name'],
                'price' => (float) $_POST['price']
            ]);
            Session::flash('success', 'Servicio agregado al catálogo.');
        } catch (\Exception $e) {
            Session::flash('error', 'Error al agregar servicio.');
        }

        View::redirect('/admin/services');
    }

    public function delete(string $id)
    {
        Auth::requirePermission('services_delete');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            View::redirect('/admin/services');
        }

        try {
            $this->catalogModel->delete((int)$id);
            Session::flash('success', 'Servicio eliminado.');
        } catch (\Exception $e) {
            Session::flash('error', 'Error al eliminar.');
        }
        
        View::redirect('/admin/services');
    }

    public function update(string $id)
    {
        Auth::requirePermission('services_update');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Petición inválida.');
            View::redirect('/admin/services');
        }

        try {
            $this->catalogModel->update((int)$id, [
                'catalog_type' => $_POST['catalog_type'],
                'category' => $_POST['category'],
                'name' => $_POST['name'],
                'price' => (float) $_POST['price']
            ]);
            Session::flash('success', 'Servicio actualizado.');
        } catch (\Exception $e) {
            Session::flash('error', 'Error al actualizar servicio.');
        }

        View::redirect('/admin/services');
    }

    public function storeCategory()
    {
        Auth::requirePermission('services_create');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Petición inválida.');
            View::redirect('/admin/services');
        }
        try {
            $db = \App\Helpers\Database::getInstance();
            $db->execute(
                "INSERT INTO catalog_categories (catalog_type, name) VALUES (?, ?)",
                [$_POST['catalog_type'], $_POST['name']]
            );
            Session::flash('success', 'Categoría agregada exitosamente.');
        } catch (\Exception $e) {
            Session::flash('error', 'Error al agregar la categoría.');
        }
        View::redirect('/admin/services');
    }

    public function deleteCategory(string $id)
    {
        Auth::requirePermission('services_delete');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            View::redirect('/admin/services');
        }
        try {
            $db = \App\Helpers\Database::getInstance();
            $db->execute("DELETE FROM catalog_categories WHERE id = ?", [(int)$id]);
            Session::flash('success', 'Categoría eliminada.');
        } catch (\Exception $e) {
            Session::flash('error', 'No se puede eliminar la categoría porque tiene elementos asociados o ocurrió un error.');
        }
        View::redirect('/admin/services');
    }

    public function storeCatalogItem()
    {
        Auth::requirePermission('services_create');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Petición inválida.');
            View::redirect('/admin/services');
        }
        try {
            $db = \App\Helpers\Database::getInstance();
            // Necesitamos el catalog_type para insertarlo. Lo obtenemos de la categoría.
            $category = $db->fetch("SELECT catalog_type FROM catalog_categories WHERE name = ? LIMIT 1", [$_POST['category']]);
            $catalogType = $category ? $category['catalog_type'] : $_POST['catalog_type_hidden']; // fallback en caso de error

            $db->execute(
                "INSERT INTO catalogs (catalog_type, category, name, price) VALUES (?, ?, ?, ?)",
                [$catalogType, $_POST['category'], $_POST['name'], $_POST['price']]
            );
            Session::flash('success', 'Ítem de catálogo agregado exitosamente.');
        } catch (\Exception $e) {
            Session::flash('error', 'Error al agregar el ítem del catálogo.');
        }
        View::redirect('/admin/services');
    }

    public function deleteCatalogItem(string $id)
    {
        Auth::requirePermission('services_delete');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            View::redirect('/admin/services');
        }
        try {
            $db = \App\Helpers\Database::getInstance();
            $db->execute("DELETE FROM catalogs WHERE id = ?", [(int)$id]);
            Session::flash('success', 'Ítem eliminado exitosamente.');
        } catch (\Exception $e) {
            Session::flash('error', 'Error al eliminar el ítem.');
        }
        View::redirect('/admin/services');
    }
}
