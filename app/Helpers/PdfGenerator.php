<?php
namespace App\Helpers;

require_once __DIR__ . '/fpdf/fpdf.php';

class PdfGenerator extends \FPDF
{
    private $clinicName;
    private $documentTitle = 'ORDEN / PREFACTURA';

    public function __construct()
    {
        parent::__construct();
        $settings = new \App\Models\SystemSetting();
        $this->clinicName = $settings->get('smtp_from_name', 'Portal Cmevi Pro');
    }

    public function setDocumentTitle(string $title): void
    {
        $this->documentTitle = $title;
    }

    public function Header()
    {
        $this->SetFont('Arial', 'B', 15);
        $this->Cell(0, 8, mb_convert_encoding($this->clinicName, 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
        
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(0, 8, mb_convert_encoding($this->documentTitle, 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
        $this->Ln(3);
    }

    public function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    public function generateOrderPdf(array $orderData, array $orderItems, string $patientName, string $patientId, string $date)
    {
        $this->AliasNbPages();
        $this->AddPage();
        
        // Datos del Paciente y de la Orden
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(40, 7, 'Orden Nro:', 0, 0);
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 7, str_pad($orderData['id'], 6, '0', STR_PAD_LEFT), 0, 1);

        $this->SetFont('Arial', 'B', 10);
        $this->Cell(40, 7, 'Fecha:', 0, 0);
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 7, date('d/m/Y H:i', strtotime($date)), 0, 1);

        $this->SetFont('Arial', 'B', 10);
        $this->Cell(40, 7, 'Paciente:', 0, 0);
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 7, mb_convert_encoding($patientName, 'ISO-8859-1', 'UTF-8'), 0, 1);

        $this->SetFont('Arial', 'B', 10);
        $this->Cell(40, 7, mb_convert_encoding('CI/RUC:', 'ISO-8859-1', 'UTF-8'), 0, 0);
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 7, mb_convert_encoding($patientId, 'ISO-8859-1', 'UTF-8'), 0, 1);

        $this->Ln(10);

        // Tabla de Ítems
        // Cabecera
        $this->SetFillColor(200, 220, 255);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(20, 10, 'Cantidad', 1, 0, 'C', true);
        $this->Cell(50, 10, mb_convert_encoding('Categoría', 'ISO-8859-1', 'UTF-8'), 1, 0, 'C', true);
        $this->Cell(120, 10, mb_convert_encoding('Descripción', 'ISO-8859-1', 'UTF-8'), 1, 1, 'C', true);

        // Filas
        $this->SetFont('Arial', '', 10);
        foreach ($orderItems as $item) {
            $catName = isset($item['catalog_category']) ? $item['catalog_category'] : '';
            $this->Cell(20, 10, $item['quantity'], 1, 0, 'C');
            $this->Cell(50, 10, mb_convert_encoding($catName, 'ISO-8859-1', 'UTF-8'), 1, 0, 'C');
            $this->Cell(120, 10, mb_convert_encoding($item['item_name'], 'ISO-8859-1', 'UTF-8'), 1, 1, 'L');
        }

        // Total
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(160, 10, 'TOTAL', 1, 0, 'R', true);
        $this->Cell(30, 10, '$' . number_format($orderData['total'], 2), 1, 1, 'C', true);

        $this->Ln(20);
        $this->SetFont('Arial', 'I', 9);
        $this->MultiCell(0, 5, mb_convert_encoding("El valor del servicio sera determinado y confirmado. Este documento es una prefactura / orden de servicio y no es un comprobante de pago válido hasta su cancelación en caja.", 'ISO-8859-1', 'UTF-8'));

        // Registrar en el log de auditoría
        \App\Helpers\Audit::logAction('generate_pdf', 'orders', $orderData['id'], 'Generación de PDF de prefactura/orden');

        return $this->Output('S'); // Retorna el contenido del PDF como string para enviarlo por correo
    }

    public function generatePrescriptionPdf(array $p): string
    {
        $this->setDocumentTitle('RECETA MÉDICA');
        $this->AliasNbPages();
        $this->AddPage();

        // Datos de la Receta
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(35, 7, 'Receta Nro:', 0, 0);
        $this->SetFont('Arial', '', 10);
        $this->Cell(60, 7, 'RX-' . str_pad($p['id'], 6, '0', STR_PAD_LEFT), 0, 0);

        $this->SetFont('Arial', 'B', 10);
        $this->Cell(25, 7, 'Fecha:', 0, 0);
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 7, date('d/m/Y H:i', strtotime($p['created_at'] ?? 'now')), 0, 1);

        // Médico
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(35, 7, mb_convert_encoding('Médico:', 'ISO-8859-1', 'UTF-8'), 0, 0);
        $this->SetFont('Arial', '', 10);
        $this->Cell(60, 7, mb_convert_encoding($p['doctor_name'] ?? 'N/A', 'ISO-8859-1', 'UTF-8'), 0, 0);

        $this->SetFont('Arial', 'B', 10);
        $this->Cell(25, 7, 'Especialidad:', 0, 0);
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 7, mb_convert_encoding($p['specialty_name'] ?? 'Medicina General', 'ISO-8859-1', 'UTF-8'), 0, 1);

        // Paciente
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(35, 7, 'Paciente:', 0, 0);
        $this->SetFont('Arial', '', 10);
        $this->Cell(60, 7, mb_convert_encoding($p['patient_name'] ?? 'N/A', 'ISO-8859-1', 'UTF-8'), 0, 0);

        $this->SetFont('Arial', 'B', 10);
        $this->Cell(25, 7, mb_convert_encoding('Cédula:', 'ISO-8859-1', 'UTF-8'), 0, 0);
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 7, mb_convert_encoding($p['patient_id_number'] ?? 'N/A', 'ISO-8859-1', 'UTF-8'), 0, 1);

        $this->Ln(5);
        $this->SetDrawColor(200, 200, 200);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(8);

        // Título Prescripción
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(30, 64, 175);
        $this->Cell(0, 8, mb_convert_encoding('Rp. / Prescripción Médica', 'ISO-8859-1', 'UTF-8'), 0, 1);
        $this->SetTextColor(0, 0, 0);

        // Medicamento
        $this->SetFillColor(240, 245, 255);
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(0, 9, '   ' . mb_convert_encoding($p['medication_name'], 'ISO-8859-1', 'UTF-8'), 1, 1, 'L', true);

        $this->Ln(2);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(35, 7, 'Dosis:', 0, 0);
        $this->SetFont('Arial', '', 10);
        $this->Cell(60, 7, mb_convert_encoding($p['dosage'], 'ISO-8859-1', 'UTF-8'), 0, 0);

        $this->SetFont('Arial', 'B', 10);
        $this->Cell(30, 7, 'Frecuencia:', 0, 0);
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 7, mb_convert_encoding($p['frequency'], 'ISO-8859-1', 'UTF-8'), 0, 1);

        $this->SetFont('Arial', 'B', 10);
        $this->Cell(35, 7, mb_convert_encoding('Duración:', 'ISO-8859-1', 'UTF-8'), 0, 0);
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 7, mb_convert_encoding($p['duration'], 'ISO-8859-1', 'UTF-8'), 0, 1);

        if (!empty($p['instructions'])) {
            $this->Ln(3);
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(0, 7, 'Indicaciones de uso:', 0, 1);
            $this->SetFont('Arial', '', 10);
            $this->MultiCell(0, 6, mb_convert_encoding($p['instructions'], 'ISO-8859-1', 'UTF-8'));
        }

        // Firma del médico al pie
        $this->SetY(-40);
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 5, '_________________________________________', 0, 1, 'C');
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 5, mb_convert_encoding('Dr(a). ' . ($p['doctor_name'] ?? ''), 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 4, mb_convert_encoding($p['specialty_name'] ?? 'Firma y Sello Médico', 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');

        return $this->Output('S');
    }

    /**
     * Generates a tabular appointments report in Landscape format
     */
    public function generateAppointmentsReportPdf(array $appointments, array $filters = []): string
    {
        $this->setDocumentTitle('REPORTE DE CITAS MÉDICAS');
        $this->AliasNbPages();
        $this->AddPage('L'); // Landscape for clean table display
        
        // Metadata / Filters bar
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(70, 70, 70);
        $filterInfo = 'Generado: ' . date('d/m/Y H:i');
        if (!empty($filters['status'])) {
            $filterInfo .= ' | Estado: ' . ucfirst($filters['status']);
        }
        if (!empty($filters['date_range'])) {
            $filterInfo .= ' | Período: ' . $filters['date_range'];
        }
        $filterInfo .= ' | Total registros: ' . count($appointments);
        
        $this->Cell(0, 6, mb_convert_encoding($filterInfo, 'ISO-8859-1', 'UTF-8'), 0, 1, 'L');
        $this->Ln(2);

        // Table Header
        // Usable width in Landscape A4 (297mm - 20mm margins) = 277mm
        // Columns: ID (18mm), Fecha/Hora (35mm), Paciente (75mm), Médico (75mm), Especialidad (44mm), Estado (30mm) = 277mm
        $this->SetFillColor(37, 99, 235); // Primary Blue
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 9);
        
        $this->Cell(18, 8, 'ID', 1, 0, 'C', true);
        $this->Cell(35, 8, 'Fecha / Hora', 1, 0, 'C', true);
        $this->Cell(75, 8, ' Paciente', 1, 0, 'L', true);
        $this->Cell(75, 8, mb_convert_encoding(' Médico', 'ISO-8859-1', 'UTF-8'), 1, 0, 'L', true);
        $this->Cell(44, 8, ' Especialidad', 1, 0, 'L', true);
        $this->Cell(30, 8, 'Estado', 1, 1, 'C', true);

        // Table Rows
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Arial', '', 8.5);
        
        $fill = false;
        $statusLabels = [
            'pending' => 'Pendiente', 'scheduled' => 'Agendada', 'agendada' => 'Agendada',
            'confirmed' => 'Confirmada', 'completed' => 'Completada', 'cancelled' => 'Cancelada'
        ];

        foreach ($appointments as $app) {
            $this->SetFillColor($fill ? 245 : 255, $fill ? 247 : 255, $fill ? 250 : 255);
            
            $appStatus = $statusLabels[$app['status']] ?? ucfirst($app['status']);
            if (($app['reschedule_count'] ?? 0) > 0) {
                $appStatus = 'Reagendada';
            }

            $dateFormatted = date('d/m/Y H:i', strtotime($app['appointment_date']));
            $patientName   = mb_convert_encoding($app['patient_name'] ?? 'N/A', 'ISO-8859-1', 'UTF-8');
            $doctorName    = mb_convert_encoding($app['doctor_name'] ?? 'N/A', 'ISO-8859-1', 'UTF-8');
            $specialtyName = mb_convert_encoding($app['specialty_name'] ?? 'N/A', 'ISO-8859-1', 'UTF-8');

            $this->Cell(18, 7, '#' . $app['id'], 1, 0, 'C', true);
            $this->Cell(35, 7, $dateFormatted, 1, 0, 'C', true);
            $this->Cell(75, 7, ' ' . substr($patientName, 0, 40), 1, 0, 'L', true);
            $this->Cell(75, 7, ' ' . substr($doctorName, 0, 40), 1, 0, 'L', true);
            $this->Cell(44, 7, ' ' . substr($specialtyName, 0, 24), 1, 0, 'L', true);
            $this->Cell(30, 7, $appStatus, 1, 1, 'C', true);

            $fill = !$fill;
        }

        if (empty($appointments)) {
            $this->Cell(277, 10, mb_convert_encoding('No se encontraron citas con los filtros seleccionados.', 'ISO-8859-1', 'UTF-8'), 1, 1, 'C');
        }

        return $this->Output('S');
    }

    /**
     * General static PDF generator method (fallback for arbitrary HTML or text content)
     */
    public static function generate(string $content, string $filename = 'documento.pdf'): void
    {
        $pdf = new self();
        $pdf->AliasNbPages();
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 10);
        
        $cleanText = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</tr>', '</h1>', '</h2>', '</h3>', '</p>'], "\n", $content));
        $cleanText = html_entity_decode($cleanText, ENT_QUOTES, 'UTF-8');
        $pdf->MultiCell(0, 6, mb_convert_encoding($cleanText, 'ISO-8859-1', 'UTF-8'));
        
        $pdfContent = $pdf->Output('S');
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdfContent));
        echo $pdfContent;
        exit;
    }
}
