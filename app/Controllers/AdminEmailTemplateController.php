<?php
namespace App\Controllers;

use App\Helpers\Auth;
use App\Helpers\View;
use App\Helpers\Session;
use App\Models\SystemSetting;

class AdminEmailTemplateController
{
    private SystemSetting $settingsModel;

    public function __construct()
    {
        Auth::require();
        $this->settingsModel = new SystemSetting();
    }

    public function index(): void
    {
        Auth::requireAnyPermission(['settings_email_read', 'settings_read']);
        // Obtain current template or default if not set
        $defaultTemplate = "
<div style=\"background: #ffffff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08);\">
    <p>Estimado/a <strong>{patient_name}</strong>:</p>
    
    <p>Le informamos que su {order_type_text} <strong>{intro_text}</strong>.</p>
    
    <ul>
        <li><strong>Número de orden:</strong> {order_id}</li>
        <li><strong>Fecha de la cita:</strong> {date}</li>
        <li><strong>Nombre del paciente:</strong> {patient_name}</li>
        <li><strong>Identificación:</strong> {patient_id}</li>
        <li><strong>Teléfono:</strong> {patient_phone}</li>
        <li><strong>Correo:</strong> {patient_email}</li>
    </ul>

    <h3 style=\"color: #1a73e8; margin-top: 20px; border-bottom: 2px solid #e2e8f0; padding-bottom: 5px;\">Indicaciones para la toma de muestras</h3>
    <p>De acuerdo con los exámenes solicitados, tenga en cuenta las siguientes recomendaciones:</p>

    <h4 style=\"margin-bottom: 5px; color: #0d47a1;\">Exámenes de sangre:</h4>
    <ul style=\"margin-top: 0; padding-left: 20px;\">
        <li>Presentarse en ayunas únicamente si la orden médica así lo requiere.</li>
        <li>En caso de ayuno, no consumir alimentos ni bebidas, excepto agua, durante el período indicado por el laboratorio.</li>
        <li>Evitar realizar actividad física intensa antes de la toma de muestra.</li>
        <li>Seguir cualquier indicación adicional proporcionada por el médico o el laboratorio.</li>
    </ul>

    <h4 style=\"margin-bottom: 5px; color: #0d47a1;\">Exámenes de orina:</h4>
    <ul style=\"margin-top: 0; padding-left: 20px;\">
        <li>Preferiblemente recolectar la primera orina de la mañana, salvo que exista una indicación médica diferente.</li>
        <li>Realizar previamente una adecuada higiene de la zona genital.</li>
        <li>Recolectar la muestra en el recipiente estéril proporcionado o indicado por el laboratorio.</li>
        <li>Entregar la muestra dentro del tiempo indicado por el laboratorio.</li>
    </ul>

    <h4 style=\"margin-bottom: 5px; color: #0d47a1;\">Exámenes de heces:</h4>
    <ul style=\"margin-top: 0; padding-left: 20px;\">
        <li>Recolectar la muestra en un recipiente limpio, seco y adecuado para este propósito.</li>
        <li>Evitar que la muestra entre en contacto con agua, orina u otros materiales.</li>
        <li>No colocar papel higiénico directamente dentro del recipiente.</li>
        <li>Entregar la muestra al laboratorio según las indicaciones y el tiempo establecido.</li>
    </ul>

    <div style=\"background: #fff3cd; color: #856404; padding: 15px; border-radius: 8px; border-left: 4px solid #ffeeba; margin-top: 20px;\">
        <strong>Importante:</strong> Las indicaciones pueden variar según el examen solicitado. Si su médico o el laboratorio le proporcionó instrucciones específicas, estas tienen prioridad sobre las recomendaciones generales mencionadas anteriormente.
    </div>

    <p style=\"margin-top: 20px;\">Adjunto a este correo encontrará el PDF correspondiente al valor de la orden de laboratorio o examen, para su revisión y referencia.</p>

    <p>Si tiene alguna duda sobre la preparación o recolección de la muestra, comuníquese con el laboratorio antes de acudir a su cita.</p>

    <p style=\"margin-top: 30px; padding-top: 15px; border-top: 1px solid #e2e8f0;\">
        Saludos cordiales,<br>
        <strong>{company_name}</strong><br>
        {company_address}<br>
        {company_phone} | {company_email}<br>
        {company_website}
    </p>
</div>
        ";
        
        $template = $this->settingsModel->get('order_email_template', trim($defaultTemplate));

        View::render('admin.email_templates.index', [
            'title' => 'Plantillas de Correo',
            'order_email_template' => $template
        ]);
    }

    public function update(): void
    {
        Auth::requireAnyPermission(['settings_email_update', 'settings_update']);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            View::redirect('/admin/email-templates');
        }

        $template = $_POST['order_email_template'] ?? '';

        if (empty(trim($template))) {
            Session::flash('error', 'El formato del correo no puede estar vacío.');
            View::redirect('/admin/email-templates');
        }

        $this->settingsModel->set('order_email_template', $template);
        Session::flash('success', 'El formato de correo se ha guardado correctamente.');
        View::redirect('/admin/email-templates');
    }
}
