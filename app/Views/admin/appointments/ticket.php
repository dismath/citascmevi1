<?php
/**
 * Ticket de Agendamiento de Cita Médica (Formato Térmico 7 x 15 cm adaptable a rollo)
 * 
 * @var array $appointment
 * @var array $clinicInfo
 * @var \App\Models\SystemSetting $settings
 */
$dias = [
    'Sunday'    => 'Domingo',
    'Monday'    => 'Lunes',
    'Tuesday'   => 'Martes',
    'Wednesday' => 'Miércoles',
    'Thursday'  => 'Jueves',
    'Friday'    => 'Viernes',
    'Saturday'  => 'Sábado'
];
$diaIngles = date('l', strtotime($appointment['appointment_date']));
$diaSemana = $dias[$diaIngles] ?? $diaIngles;
$fechaCita = date('d/m/Y', strtotime($appointment['appointment_date']));
$horaCita  = date('H:i', strtotime($appointment['appointment_date']));
$citaNum   = str_pad($appointment['id'], 5, '0', STR_PAD_LEFT);
$statusLabel = strtoupper(htmlspecialchars($appointment['status'] ?? 'AGENDADA'));
if ($statusLabel === 'PENDING') $statusLabel = 'PENDIENTE';
if ($statusLabel === 'CONFIRMED') $statusLabel = 'CONFIRMADA';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket Cita #<?= $citaNum ?> - <?= htmlspecialchars($clinicInfo['name']) ?></title>
    <style>
        /* Reglas de Impresión Térmica 70mm x Auto */
        @page {
            size: 70mm auto;
            margin: 0;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Courier New', Courier, monospace, 'Segoe UI', Arial, sans-serif;
            background-color: #f1f5f9;
            color: #000000;
            font-size: 11px;
            line-height: 1.35;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        /* Barra de acciones en pantalla (no imprimible) */
        .actions-bar {
            position: fixed;
            top: 15px;
            left: 50%;
            transform: translateX(-50%);
            background: #1e293b;
            padding: 10px 20px;
            border-radius: 30px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.25);
            display: flex;
            gap: 12px;
            z-index: 9999;
        }
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            border: none;
            text-decoration: none;
            transition: all 0.2s;
            font-family: 'Segoe UI', Arial, sans-serif;
        }
        .btn-print {
            background: #22c55e;
            color: #ffffff;
        }
        .btn-print:hover {
            background: #16a34a;
        }
        .btn-close {
            background: #475569;
            color: #ffffff;
        }
        .btn-close:hover {
            background: #334155;
        }
        /* Contenedor del Ticket (70mm de ancho base, min-height 145mm) */
        .ticket-wrapper {
            display: flex;
            justify-content: center;
            padding: 70px 15px 40px;
        }
        .ticket {
            width: 70mm;
            min-height: 145mm;
            background: #ffffff;
            padding: 6mm 4mm;
            box-shadow: 0 4px 18px rgba(0,0,0,0.12);
            border-radius: 2px;
            color: #000;
            text-align: left;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .fw-bold { font-weight: bold; }
        .text-uppercase { text-transform: uppercase; }
        .clinic-name {
            font-size: 14px;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 3px;
            letter-spacing: -0.2px;
        }
        .clinic-info {
            font-size: 9.5px;
            color: #222;
            margin-bottom: 2px;
            line-height: 1.25;
        }
        .ticket-divider {
            border-top: 1px dashed #000000;
            margin: 6px 0;
        }
        .ticket-title {
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.5px;
            padding: 3px 0;
        }
        .ticket-id-badge {
            font-size: 15px;
            font-weight: 900;
            letter-spacing: 1px;
            margin: 3px 0;
        }
        .ticket-section-title {
            font-size: 10px;
            font-weight: 800;
            text-decoration: underline;
            margin-bottom: 4px;
        }
        .ticket-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
            font-size: 10.5px;
            align-items: flex-start;
        }
        .ticket-label {
            font-weight: 700;
            width: 38%;
            flex-shrink: 0;
        }
        .ticket-value {
            width: 62%;
            word-break: break-word;
        }
        .highlight-box {
            border: 1px solid #000000;
            padding: 6px 4px;
            margin: 6px 0;
            text-align: center;
        }
        .highlight-date {
            font-size: 13px;
            font-weight: 900;
        }
        .highlight-time {
            font-size: 15px;
            font-weight: 900;
        }
        .status-pill {
            display: inline-block;
            font-weight: 900;
            padding: 1px 6px;
            border: 1px solid #000;
            font-size: 10px;
            letter-spacing: 0.5px;
        }
        .notes-box {
            font-size: 9.5px;
            background: #f8fafc;
            border: 1px dotted #666;
            padding: 4px 6px;
            margin-top: 4px;
        }
        .notice-text {
            font-size: 9px;
            line-height: 1.25;
            text-align: justify;
            margin-top: 4px;
        }
        .barcode-box {
            margin: 8px 0 4px;
            text-align: center;
        }
        .barcode-bars {
            height: 28px;
            display: inline-flex;
            align-items: stretch;
            gap: 1.5px;
            margin: 0 auto;
        }
        .barcode-bars span {
            display: inline-block;
            background-color: #000;
            width: 2px;
        }
        .barcode-bars span.w-1 { width: 1px; }
        .barcode-bars span.w-2 { width: 2px; }
        .barcode-bars span.w-3 { width: 3px; }
        .barcode-bars span.space { background-color: transparent; width: 1.5px; }
        .barcode-number {
            font-size: 9px;
            letter-spacing: 3px;
            font-weight: bold;
            margin-top: 2px;
        }
        .cut-line {
            border-top: 1px dotted #888;
            margin: 10px 0 4px;
            text-align: center;
            font-size: 8px;
            color: #555;
        }
        /* AJUSTES PARA IMPRESIÓN REAL */
        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .no-print {
                display: none !important;
            }
            .ticket-wrapper {
                padding: 0 !important;
                margin: 0 !important;
            }
            .ticket {
                width: 70mm !important;
                min-height: auto !important;
                box-shadow: none !important;
                border: none !important;
                padding: 3mm 2mm 8mm 2mm !important;
                margin: 0 auto !important;
            }
        }
    </style>
</head>
<body>
    <div class="actions-bar no-print">
        <button class="btn-action btn-print" onclick="window.print()">
            🖨️ Imprimir Ticket
        </button>
        <button class="btn-action btn-close" onclick="window.close()">
            ✖ Cerrar
        </button>
    </div>
    <div class="ticket-wrapper">
        <div class="ticket">
            <div class="text-center">
                <div class="clinic-name"><?= htmlspecialchars($clinicInfo['name']) ?></div>
                <?php if (!empty($clinicInfo['address'])): ?>
                    <div class="clinic-info">📍 <?= htmlspecialchars($clinicInfo['address']) ?></div>
                <?php endif; ?>
                <?php if (!empty($clinicInfo['phone'])): ?>
                    <div class="clinic-info">📞 Tel: <?= htmlspecialchars($clinicInfo['phone']) ?></div>
                <?php endif; ?>
                <?php if (!empty($clinicInfo['email'])): ?>
                    <div class="clinic-info">✉️ <?= htmlspecialchars($clinicInfo['email']) ?></div>
                <?php endif; ?>
            </div>
            <div class="ticket-divider"></div>
            <div class="text-center">
                <div class="ticket-title">COMPROBANTE DE CITA MÉDICA</div>
                <div class="ticket-id-badge">CITA #<?= $citaNum ?></div>
                <div style="font-size: 9px; color: #444;">
                    Emisión: <?= date('d/m/Y H:i:s') ?>
                </div>
                <div style="margin-top: 4px;">
                    <span class="status-pill"><?= $statusLabel ?></span>
                </div>
            </div>
            <div class="ticket-divider"></div>
            <div class="ticket-section-title">DATOS DEL PACIENTE</div>
            <div class="ticket-row">
                <span class="ticket-label">Nombre:</span>
                <span class="ticket-value fw-bold"><?= htmlspecialchars($appointment['patient_name'] ?? 'N/A') ?></span>
            </div>
            <div class="ticket-row">
                <span class="ticket-label">Cédula/ID:</span>
                <span class="ticket-value"><?= htmlspecialchars($appointment['patient_id_number'] ?? 'N/A') ?></span>
            </div>
            <?php if (!empty($appointment['patient_phone'])): ?>
            <div class="ticket-row">
                <span class="ticket-label">Teléfono:</span>
                <span class="ticket-value"><?= htmlspecialchars($appointment['patient_phone']) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($appointment['patient_email'])): ?>
            <div class="ticket-row">
                <span class="ticket-label">Correo:</span>
                <span class="ticket-value" style="font-size: 9px;"><?= htmlspecialchars($appointment['patient_email']) ?></span>
            </div>
            <?php endif; ?>
            <div class="ticket-divider"></div>
            <div class="ticket-section-title">DETALLES DE LA ATENCIÓN</div>
            <div class="ticket-row">
                <span class="ticket-label">Especialidad:</span>
                <span class="ticket-value fw-bold"><?= htmlspecialchars($appointment['specialty_name'] ?? 'General') ?></span>
            </div>
            <div class="ticket-row">
                <span class="ticket-label">Médico:</span>
                <span class="ticket-value fw-bold">Dr(a). <?= htmlspecialchars($appointment['doctor_name'] ?? 'Por asignar') ?></span>
            </div>
            <div class="highlight-box">
                <div style="font-size: 9.5px; font-weight: 700; text-transform: uppercase;">📅 Fecha Programada</div>
                <div class="highlight-date"><?= $diaSemana ?>, <?= $fechaCita ?></div>
                <div class="highlight-time">🕐 <?= $horaCita ?></div>
            </div>
            <?php if (!empty($appointment['notes'])): ?>
            <div class="notes-box">
                <strong>Obs:</strong> <?= htmlspecialchars($appointment['notes']) ?>
            </div>
            <?php endif; ?>
            <div class="ticket-divider"></div>
            <div class="ticket-section-title">INDICACIONES IMPORTANTES</div>
            <div class="notice-text">
                • Presentarse en recepción con <strong>15 minutos</strong> de anticipación portando su cédula original.<br>
                • Cualquier reagendamiento debe solicitarse con un mínimo de <strong>24 horas</strong> antes del turno.<br>
                • Conserve este ticket como constancia de su agendamiento.
            </div>
            <div class="barcode-box">
                <div class="barcode-bars">
                    <span class="w-3"></span><span class="space"></span><span class="w-1"></span><span class="space"></span>
                    <span class="w-2"></span><span class="w-1"></span><span class="space"></span><span class="w-3"></span>
                    <span class="space"></span><span class="w-2"></span><span class="space"></span><span class="w-1"></span>
                    <span class="w-3"></span><span class="space"></span><span class="w-1"></span><span class="w-2"></span>
                    <span class="space"></span><span class="w-3"></span><span class="space"></span><span class="w-1"></span>
                    <span class="w-2"></span><span class="space"></span><span class="w-3"></span><span class="w-1"></span>
                    <span class="space"></span><span class="w-2"></span><span class="space"></span><span class="w-3"></span>
                </div>
                <div class="barcode-number">*<?= $citaNum ?>*</div>
            </div>
            <div class="text-center" style="font-size: 9.5px; font-weight: bold; margin-top: 4px;">
                ¡Gracias por su confianza!
            </div>
            <div class="cut-line">
                - - - - - - - CORTE DE PAPEL - - - - - - -
            </div>
        </div>
    </div>
    <script>
        // Disparar ventana de impresión automáticamente tras cargar
        window.addEventListener('load', function() {
            setTimeout(function() {
                window.print();
            }, 300);
        });
    </script>
</body>
</html>
