<?php
namespace App\Controllers;

use App\Helpers\View;
use App\Helpers\Auth;
use App\Helpers\Session;
use App\Helpers\Database;
use App\Helpers\PdfGenerator;
use App\Helpers\Mailer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Appointment;

class AdminOrderController
{
    private Order $orderModel;
    private OrderItem $orderItemModel;
    private Appointment $appointmentModel;
    private Database $db;

    public function __construct()
    {
        Auth::require();
        $this->orderModel = new Order();
        $this->orderItemModel = new OrderItem();
        $this->appointmentModel = new Appointment();
        $this->db = Database::getInstance();
    }

    public function index()
    {
        Auth::requirePermission('orders_read');
        $orders = $this->orderModel->getAllWithDetails();
        $settingsModel = new \App\Models\SystemSetting();
        $maxReschedules = (int)$settingsModel->get('max_reschedules', '1');

        View::render('admin.orders.index', [
            'title' => 'Órdenes / Prefacturas',
            'orders' => $orders,
            'maxReschedules' => $maxReschedules
        ], 'admin');
    }

    public function create(string $appointmentId)
    {
        Auth::requirePermission('orders_create');
        $appointment = $this->appointmentModel->getByIdWithDetails((int)$appointmentId);
        if (!$appointment) {
            Session::flash('error', 'Cita no encontrada.');
            View::redirect('/admin/appointments');
        }

        // Determinar el próximo número de orden para mostrarlo en la vista
        $nextOrderQuery = $this->db->fetch("SELECT MAX(id) as max_id FROM orders");
        $nextOrderId = ($nextOrderQuery['max_id'] ?? 0) + 1;

        View::render('admin.orders.create', [
            'title' => 'Generar Orden / Prefactura',
            'appointment' => $appointment,
            'nextOrderId' => $nextOrderId
        ], 'admin');
    }

    // Endpoint AJAX para obtener el catálogo basado en la cita
    public function getCatalogForAppointment(string $appointmentId)
    {
        Auth::requirePermission('orders_read');
        header('Content-Type: application/json; charset=utf-8');

        try {
            $appointment = $this->appointmentModel->getByIdWithDetails((int)$appointmentId);
            if (!$appointment) {
                echo json_encode(['items' => [], 'error' => 'Cita no encontrada']);
                exit;
            }

            $doc = $this->db->fetch("SELECT specialty_id FROM doctors WHERE id = ?", [$appointment['doctor_id']]);
            if (!$doc) {
                echo json_encode(['items' => [], 'error' => 'Médico no encontrado']);
                exit;
            }

            $specialty = $this->db->fetch("SELECT catalog_type_code FROM specialties WHERE id = ?", [$doc['specialty_id']]);
            $catalogTypeCode = $specialty['catalog_type_code'] ?? null;

            if (!$catalogTypeCode) {
                $catalogItems = $this->db->fetchAll("
                    SELECT c.id, c.category, c.name as text, c.price 
                    FROM catalogs c
                    ORDER BY c.category ASC, c.name ASC
                ");
            } else {
                $catalogItems = $this->db->fetchAll("
                    SELECT c.id, c.category, c.name as text, c.price 
                    FROM catalogs c
                    JOIN catalog_categories cc ON c.category = cc.name
                    WHERE cc.catalog_type = ?
                    ORDER BY c.category ASC, c.name ASC
                ", [$catalogTypeCode]);
            }

            echo json_encode(['items' => $catalogItems], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            echo json_encode(['items' => [], 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function store()
    {
        Auth::requirePermission('orders_create');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Petición inválida.');
            View::redirect('/admin/appointments');
        }

        $appointmentId = (int)($_POST['appointment_id'] ?? 0);
        $items = $_POST['items'] ?? []; // array de ['id' => X, 'qty' => Y]

        if (!$appointmentId || empty($items)) {
            Session::flash('error', 'Debe seleccionar al menos un ítem.');
            View::redirect('/admin/appointments');
        }

        $appointment = $this->appointmentModel->getByIdWithDetails($appointmentId);
        if (!$appointment) {
            Session::flash('error', 'Cita no válida.');
            View::redirect('/admin/appointments');
        }

        // Prevent duplicate orders for the same appointment
        $existingOrder = $this->db->fetch(
            "SELECT id FROM orders WHERE appointment_id = ? LIMIT 1",
            [$appointmentId]
        );
        if ($existingOrder) {
            Session::flash('warning', 'Ya existe una orden generada para esta cita.');
            View::redirect('/admin/orders');
        }

        try {
            $this->db->beginTransaction();

            // Create Order
            $this->db->execute("
                INSERT INTO orders (appointment_id, patient_id, total, status)
                VALUES (?, ?, 0, 'pending')
            ", [$appointmentId, $appointment['patient_id']]);
            
            $orderId = $this->db->lastInsertId();
            $total = 0;
            $orderItemsArr = [];

            // Add Items
            foreach ($items as $item) {
                $catalogId = (int)$item['id'];
                $qty = (int)$item['qty'];
                
                if ($qty <= 0) continue;

                $catalogItem = $this->db->fetch("SELECT * FROM catalogs WHERE id = ?", [$catalogId]);
                if ($catalogItem) {
                    $price = (float)$catalogItem['price'];
                    $subtotal = $price * $qty;
                    $total += $subtotal;

                    $this->db->execute("
                        INSERT INTO order_items (order_id, catalog_item_id, item_name, price, quantity, subtotal)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ", [$orderId, $catalogId, $catalogItem['name'], $price, $qty, $subtotal]);

                    $orderItemsArr[] = [
                        'item_name' => $catalogItem['name'],
                        'price' => $price,
                        'quantity' => $qty,
                        'subtotal' => $subtotal,
                        'catalog_category' => $catalogItem['category']
                    ];
                }
            }

            // Update Total
            $this->db->execute("UPDATE orders SET total = ? WHERE id = ?", [$total, $orderId]);
            $this->db->commit();

            // Generate PDF
            $pdfGen = new PdfGenerator();
            $orderData = [
                'id' => $orderId,
                'total' => $total
            ];
            $pdfContent = $pdfGen->generateOrderPdf(
                $orderData, 
                $orderItemsArr, 
                $appointment['patient_name'], 
                $appointment['patient_id_number'], 
                $appointment['appointment_date']
            );

            // Send Email
            $patientEmail = $appointment['patient_email'];
            $emailSent = false;
            if (!empty($patientEmail)) {
                $catalogTypeCode = $appointment['catalog_type_code'] ?? '';
                $catalogTypeName = '';
                if ($catalogTypeCode && $catalogTypeCode !== 'ESPEC') {
                    $ctRow = $this->db->fetch("SELECT name FROM catalog_types WHERE code = ?", [$catalogTypeCode]);
                    if ($ctRow) {
                        $catalogTypeName = $ctRow['name'];
                    }
                }

                $patientExtra = [
                    'id' => $appointment['patient_id_number'] ?? '',
                    'phone' => $appointment['patient_phone'] ?? '',
                    'email' => $appointment['patient_email'] ?? ''
                ];

                $htmlBody = \App\Helpers\Mailer::buildOrderEmail($orderData, $appointment['patient_name'], $appointment['appointment_date'], false, $patientExtra, $catalogTypeName);

                $attachments = [
                    [
                        'name' => "Orden_{$orderId}.pdf",
                        'data' => $pdfContent,
                        'type' => 'application/pdf'
                    ]
                ];

                try {
                    $emailSent = Mailer::queue($patientEmail, "Orden de Servicio #" . str_pad($orderId, 6, '0', STR_PAD_LEFT), $htmlBody, $attachments);
                    if ($emailSent) {
                        // Marcar email_sent en la cita asociada
                        $this->appointmentModel->update($appointmentId, ['email_sent' => 1]);
                    }
                } catch (\Exception $e) {
                    error_log("Error enviando correo de orden: " . $e->getMessage());
                    $emailSent = false;
                }
            }

            if ($emailSent) {
                Session::flash('success', 'Orden generada y correo enviado al paciente correctamente ✅');
            } else {
                Session::flash('warning', 'Orden generada correctamente, pero no se pudo enviar el correo al paciente ⚠️');
            }
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Error generando orden: " . $e->getMessage());
            Session::flash('error', 'Ocurrió un error al generar la orden.');
        }

        View::redirect('/admin/orders');
    }

    public function show(string $id)
    {
        Auth::requirePermission('orders_read');
        $order = $this->orderModel->getWithDetails((int)$id);
        if (!$order) {
            Session::flash('error', 'Orden no encontrada.');
            View::redirect('/admin/orders');
        }
        $items = $this->orderItemModel->getByOrderId((int)$id);

        View::render('admin.orders.show', [
            'title' => 'Detalle de Orden',
            'order' => $order,
            'items' => $items
        ], 'admin');
    }

    public function edit(string $id)
    {
        Auth::requirePermission('orders_update');
        $order = $this->orderModel->getWithDetails((int)$id);
        if (!$order) {
            Session::flash('error', 'Orden no encontrada.');
            View::redirect('/admin/orders');
        }

        $items = $this->orderItemModel->getByOrderId((int)$id);

        View::render('admin.orders.edit', [
            'title' => 'Editar Orden / Prefactura',
            'order' => $order,
            'items' => $items
        ], 'admin');
    }

    public function update(string $id)
    {
        Auth::requirePermission('orders_update');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Petición inválida.');
            View::redirect('/admin/orders');
        }

        $status = $_POST['status'] ?? 'pending';
        $items = $_POST['items'] ?? []; 

        if (empty($items)) {
            Session::flash('error', 'Debe haber al menos un ítem en la orden.');
            View::redirect('/admin/orders/edit/' . $id);
        }

        try {
            $this->db->beginTransaction();

            $this->db->execute("UPDATE orders SET status = ? WHERE id = ?", [$status, (int)$id]);
            
            // Re-create items
            $this->db->execute("DELETE FROM order_items WHERE order_id = ?", [(int)$id]);

            $total = 0;
            foreach ($items as $item) {
                $catalogId = (int)$item['id'];
                $qty = (int)$item['qty'];
                
                if ($qty <= 0) continue;

                $catalogItem = $this->db->fetch("SELECT * FROM catalogs WHERE id = ?", [$catalogId]);
                if ($catalogItem) {
                    $price = (float)$catalogItem['price'];
                    $subtotal = $price * $qty;
                    $total += $subtotal;

                    $this->db->execute("
                        INSERT INTO order_items (order_id, catalog_item_id, item_name, price, quantity, subtotal)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ", [(int)$id, $catalogId, $catalogItem['name'], $price, $qty, $subtotal]);
                }
            }

            $this->db->execute("UPDATE orders SET total = ? WHERE id = ?", [$total, (int)$id]);

            $this->db->commit();
            
            // Get order with details to send email
            $order = $this->orderModel->getWithDetails((int)$id);
            if ($order && !empty($order['patient_email'])) {
                $pdfGen = new \App\Helpers\PdfGenerator();
                $orderData = [
                    'id' => (int)$id,
                    'total' => $total
                ];
                
                // Fetch items for PDF
                $itemsForPdf = $this->db->fetchAll("SELECT item_name, price, quantity, subtotal FROM order_items WHERE order_id = ?", [(int)$id]);
                
                $pdfContent = $pdfGen->generateOrderPdf(
                    $orderData, 
                    $itemsForPdf, 
                    $order['patient_name'], 
                    $order['patient_id_number'], 
                    $order['appointment_date']
                );

                $catalogTypeCode = $order['catalog_type_code'] ?? '';
                $catalogTypeName = '';
                if ($catalogTypeCode && $catalogTypeCode !== 'ESPEC') {
                    $ctRow = $this->db->fetch("SELECT name FROM catalog_types WHERE code = ?", [$catalogTypeCode]);
                    if ($ctRow) {
                        $catalogTypeName = $ctRow['name'];
                    }
                }

                $patientExtra = [
                    'id' => $order['patient_id_number'] ?? '',
                    'phone' => $order['patient_phone'] ?? '',
                    'email' => $order['patient_email'] ?? ''
                ];

                $htmlBody = \App\Helpers\Mailer::buildOrderEmail($orderData, $order['patient_name'], $order['appointment_date'], true, $patientExtra, $catalogTypeName);

                $attachments = [
                    [
                        'name' => "Orden_{$id}_Actualizada.pdf",
                        'data' => $pdfContent,
                        'type' => 'application/pdf'
                    ]
                ];

                try {
                    \App\Helpers\Mailer::queue($order['patient_email'], "Actualización de Orden #" . str_pad($id, 6, '0', STR_PAD_LEFT), $htmlBody, $attachments);
                    Session::flash('success', 'Orden actualizada y correo de confirmación enviado correctamente.');
                } catch (\Exception $e) {
                    error_log("Error enviando correo de orden actualizada: " . $e->getMessage());
                    Session::flash('warning', 'Orden actualizada correctamente, pero no se pudo enviar el correo.');
                }
            } else {
                Session::flash('success', 'Orden actualizada correctamente (sin correo de paciente).');
            }
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Error actualizando orden: " . $e->getMessage());
            Session::flash('error', 'No se pudo actualizar la orden.');
        }

        View::redirect('/admin/orders');
    }

    public function updateStatus(string $id)
    {
        Auth::requirePermission('orders_update');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Petición inválida.');
            View::redirect('/admin/orders');
        }

        $newStatus = $_POST['status'] ?? 'pending';
        $allowedStatuses = ['pending', 'paid', 'cancelled'];
        
        if (!in_array($newStatus, $allowedStatuses)) {
            Session::flash('error', 'Estado no válido.');
            View::redirect('/admin/orders');
        }

        // Check current status - prevent going back
        $currentOrder = $this->orderModel->findById((int)$id);
        if (!$currentOrder) {
            Session::flash('error', 'Orden no encontrada.');
            View::redirect('/admin/orders');
        }

        $statusOrder = ['pending' => 0, 'paid' => 1, 'cancelled' => 99];
        $currentLevel = $statusOrder[$currentOrder['status']] ?? 0;
        $newLevel = $statusOrder[$newStatus] ?? 0;

        if ($newLevel <= $currentLevel && $currentOrder['status'] !== $newStatus) {
            Session::flash('error', 'No se puede regresar a un estado anterior.');
            View::redirect('/admin/orders');
        }

        if ($currentOrder['status'] === $newStatus) {
            View::redirect('/admin/orders');
        }

        try {
            $this->db->execute("UPDATE orders SET status = ? WHERE id = ?", [$newStatus, (int)$id]);
            Session::flash('success', 'Estado de la orden actualizado correctamente.');
        } catch (\Exception $e) {
            Session::flash('error', 'No se pudo actualizar el estado de la orden.');
        }

        View::redirect('/admin/orders');
    }

    public function delete(string $id)
    {
        Auth::requirePermission('orders_delete');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Session::validateCsrf($_POST['csrf_token'] ?? '')) {
            Session::flash('error', 'Petición inválida.');
            View::redirect('/admin/orders');
        }

        try {
            $this->db->beginTransaction();
            $this->db->execute("DELETE FROM order_items WHERE order_id = ?", [(int)$id]);
            $this->db->execute("DELETE FROM orders WHERE id = ?", [(int)$id]);
            $this->db->commit();
            Session::flash('success', 'Orden eliminada correctamente.');
        } catch (\Exception $e) {
            $this->db->rollBack();
            Session::flash('error', 'No se pudo eliminar la orden.');
        }

        View::redirect('/admin/orders');
    }

    public function download(string $id)
    {
        Auth::requirePermission('orders_read');
        $orderId = (int)$id;
        $order = $this->orderModel->getWithDetails($orderId);
        if (!$order) {
            Session::flash('error', 'Orden no encontrada.');
            View::redirect('/admin/orders');
        }

        $items = $this->orderItemModel->getByOrderId($orderId);

        $pdfGen = new PdfGenerator();
        $pdfContent = $pdfGen->generateOrderPdf(
            $order, 
            $items, 
            $order['patient_name'], 
            $order['patient_id_number'], 
            $order['appointment_date']
        );

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="Orden_' . str_pad($orderId, 6, '0', STR_PAD_LEFT) . '.pdf"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        echo $pdfContent;
        exit;
    }
}
