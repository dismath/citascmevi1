<?php
/**
 * Script de migración para encriptar notas médicas e historias clínicas existentes.
 * 
 * Uso: php scripts/encrypt_legacy_data.php
 */

require_once __DIR__ . '/../public/index.php'; // Carga entorno, base de datos y configuraciones

use App\Helpers\Database;
use App\Helpers\Crypto;

$db = Database::getInstance();
$db->beginTransaction();

try {
    echo "Iniciando migración de encriptación...\n";

    // 1. Encriptar medical_notes
    $notes = $db->fetchAll("SELECT * FROM medical_notes");
    $notesUpdated = 0;
    
    foreach ($notes as $note) {
        $id = $note['id'];
        
        $chief_complaint = $note['chief_complaint'] ? Crypto::encrypt($note['chief_complaint']) : null;
        $diagnosis = $note['diagnosis'] ? Crypto::encrypt($note['diagnosis']) : null;
        $treatment = $note['treatment'] ? Crypto::encrypt($note['treatment']) : null;
        $clinical_notes = $note['clinical_notes'] ? Crypto::encrypt($note['clinical_notes']) : null;

        // Solo actualizar si no estaban ya encriptados (Crypto::encrypt retorna igual si ya tiene ENC:)
        if ($chief_complaint !== $note['chief_complaint'] || 
            $diagnosis !== $note['diagnosis'] || 
            $treatment !== $note['treatment'] || 
            $clinical_notes !== $note['clinical_notes']) {
            
            $db->execute(
                "UPDATE medical_notes SET chief_complaint = ?, diagnosis = ?, treatment = ?, clinical_notes = ? WHERE id = ?",
                [$chief_complaint, $diagnosis, $treatment, $clinical_notes, $id]
            );
            $notesUpdated++;
        }
    }
    
    echo "Notas médicas actualizadas: $notesUpdated\n";

    // 2. Encriptar medical_history
    $histories = $db->fetchAll("SELECT * FROM medical_history");
    $historyUpdated = 0;

    foreach ($histories as $history) {
        $id = $history['id'];

        $family = $history['family_history'] ? Crypto::encrypt($history['family_history']) : null;
        $past = $history['past_medical_history'] ? Crypto::encrypt($history['past_medical_history']) : null;
        $allergies = $history['allergies'] ? Crypto::encrypt($history['allergies']) : null;
        $surgeries = $history['surgeries'] ? Crypto::encrypt($history['surgeries']) : null;
        $chronic = $history['chronic_diseases'] ? Crypto::encrypt($history['chronic_diseases']) : null;
        $medications = $history['medications'] ? Crypto::encrypt($history['medications']) : null;

        if ($family !== $history['family_history'] ||
            $past !== $history['past_medical_history'] ||
            $allergies !== $history['allergies'] ||
            $surgeries !== $history['surgeries'] ||
            $chronic !== $history['chronic_diseases'] ||
            $medications !== $history['medications']) {

            $db->execute(
                "UPDATE medical_history SET family_history = ?, past_medical_history = ?, allergies = ?, surgeries = ?, chronic_diseases = ?, medications = ? WHERE id = ?",
                [$family, $past, $allergies, $surgeries, $chronic, $medications, $id]
            );
            $historyUpdated++;
        }
    }

    echo "Historias clínicas actualizadas: $historyUpdated\n";

    $db->commit();
    echo "¡Migración completada exitosamente!\n";
} catch (\Exception $e) {
    $db->rollback();
    echo "Error durante la migración: " . $e->getMessage() . "\n";
}
