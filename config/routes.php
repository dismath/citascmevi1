<?php
/**
 * Application Routes
 * @var App\Helpers\Router $router
 */

// ==========================================
// PUBLIC ROUTES - Booking Wizard
// ==========================================
$router->get('/', 'BookingController', 'index');
$router->get('/booking', 'BookingController', 'index');
$router->get('/booking/step/{step}', 'BookingController', 'step');
$router->post('/booking/process', 'BookingController', 'process');
$router->get('/booking/confirmation/{token}', 'BookingController', 'confirmation');
$router->get('/booking/print/{token}', 'BookingController', 'printAppointment');
$router->post('/booking/lock-slot', 'BookingController', 'lockSlot');

// ==========================================
// API ROUTES - AJAX endpoints for Booking
// ==========================================
$router->get('/api/specialties', 'ApiController', 'specialties');
$router->get('/api/doctors/{specialtyId}', 'ApiController', 'doctorsBySpecialty');
$router->get('/api/availability/{doctorId}', 'ApiController', 'availability');
$router->get('/api/availability/{doctorId}/{date}', 'ApiController', 'availabilityByDate');
$router->get('/api/doctor-fee/{doctorId}', 'ApiController', 'doctorFee');
$router->get('/api/catalogs/{typeCode}', 'ApiController', 'catalogs');
$router->get('/api/bank-info', 'ApiController', 'bankInfo');
$router->post('/api/upload-payment', 'ApiController', 'uploadPayment');
// New calendar-based booking API
$router->get('/api/specialty-dates/{specialtyId}', 'ApiController', 'specialtyDates');
$router->get('/api/specialty-slots/{specialtyId}/{date}', 'ApiController', 'specialtySlotsByDate');
$router->get('/api/patient/{idNumber}', 'ApiController', 'patientByIdNumber');
$router->get('/api/patients/search', 'ApiController', 'searchPatients');
// Admin schedule calendar API
$router->get('/api/schedule-month/{doctorId}', 'ApiController', 'scheduleMonthSummary');
$router->get('/api/schedule-day/{doctorId}/{date}', 'ApiController', 'scheduleDaySlots');

// ==========================================
// AUTH ROUTES
// ==========================================
$router->get('/login', 'AuthController', 'loginForm');
$router->post('/login', 'AuthController', 'login');
$router->get('/logout', 'AuthController', 'logout');

// 2FA Routes
$router->get('/login/2fa', 'AuthController', 'twoFactorForm');
$router->post('/login/2fa', 'AuthController', 'verifyTwoFactor');
$router->post('/login/2fa/resend', 'AuthController', 'resendTwoFactor');
$router->get('/login/2fa/cancel', 'AuthController', 'cancelTwoFactor');

// Revocar todas las sesiones (teléfono perdido / cuenta comprometida)
$router->post('/account/revoke-sessions', 'AuthController', 'revokeAllSessions');

$router->get('/forgot-password', 'PasswordResetController', 'showForgotForm');
$router->post('/forgot-password', 'PasswordResetController', 'sendResetLink');
$router->get('/reset-password', 'PasswordResetController', 'showForm');
$router->post('/reset-password', 'PasswordResetController', 'process');

// ==========================================
// ADMIN ROUTES
// ==========================================
$router->get('/admin', 'AdminController', 'dashboard');
$router->get('/admin/dashboard', 'AdminController', 'dashboard');

// ==========================================
// DOCTOR ROUTES
// ==========================================
$router->get('/doctor/dashboard', 'DoctorController', 'dashboard');
$router->get('/api/doctor/appointments/{doctorId}', 'DoctorController', 'apiConfirmedAppointments');
$router->get('/doctor/appointments', 'DoctorPatientController', 'index');
$router->get('/doctor/appointments/{appointmentId}/start', 'DoctorPatientController', 'startAppointment');
$router->post('/doctor/appointments/{appointmentId}/start', 'DoctorPatientController', 'startAppointment');
$router->get('/doctor/patients/attended', 'DoctorPatientController', 'attendedPatients');
$router->get('/doctor/patients/{patientId}/history', 'DoctorPatientController', 'history');
$router->post('/doctor/patients/{patientId}/notes/store', 'DoctorPatientController', 'storeNote');
$router->post('/doctor/patients/{patientId}/treatments/store', 'DoctorPatientController', 'storeTreatment');
$router->post('/doctor/treatments/{treatmentId}/status', 'DoctorPatientController', 'updateTreatmentStatus');
$router->post('/doctor/patients/{patientId}/prescriptions/store', 'DoctorPatientController', 'storePrescription');
$router->get('/doctor/prescriptions/{prescriptionId}/pdf', 'DoctorPatientController', 'downloadPrescriptionPdf');
$router->get('/doctor/schedule', 'DoctorScheduleController', 'index');
$router->post('/doctor/schedule/generate', 'DoctorScheduleController', 'generate');
$router->post('/doctor/schedule/delete-all', 'DoctorScheduleController', 'deleteAllSlots');
$router->post('/doctor/schedule/delete/{id}', 'DoctorScheduleController', 'deleteSlot');
$router->get('/doctor/profile', 'DoctorProfileController', 'edit');
$router->post('/doctor/profile/update', 'DoctorProfileController', 'update');
$router->get('/doctor/appointments/{appointmentId}/attachment', 'DoctorPatientController', 'appointmentAttachment');

// ==========================================
// PATIENT ROUTES
// ==========================================
$router->get('/patient/dashboard', 'PatientController', 'dashboard');
$router->get('/patient/records', 'PatientController', 'records');
$router->get('/patient/appointments/reschedule/{id}', 'PatientController', 'reschedule');
$router->post('/patient/appointments/update-schedule/{id}', 'PatientController', 'updateSchedule');
$router->get('/patient/appointments/attachment/{id}', 'PatientController', 'attachment');
$router->get('/patient/profile', 'PatientProfileController', 'edit');
$router->post('/patient/profile/update', 'PatientProfileController', 'update');

// Admin / Staff Profile
$router->get('/admin/profile', 'StaffProfileController', 'edit');
$router->post('/admin/profile/update', 'StaffProfileController', 'update');

// Admin - Appointments
$router->get('/admin/appointments', 'AdminAppointmentController', 'index');
$router->get('/admin/appointments/export/excel', 'AdminAppointmentController', 'exportExcel');
$router->get('/admin/appointments/export/pdf', 'AdminAppointmentController', 'exportPdf');
$router->get('/admin/appointments/create', 'AdminAppointmentController', 'create');
$router->post('/admin/appointments/store', 'AdminAppointmentController', 'store');
$router->get('/admin/appointments/edit/{id}', 'AdminAppointmentController', 'edit');
$router->post('/admin/appointments/update/{id}', 'AdminAppointmentController', 'update');
$router->get('/admin/appointments/reschedule/{id}', 'AdminAppointmentController', 'reschedule');
$router->post('/admin/appointments/update-schedule/{id}', 'AdminAppointmentController', 'updateSchedule');
$router->post('/admin/appointments/delete/{id}', 'AdminAppointmentController', 'delete');
$router->post('/admin/appointments/status/{id}', 'AdminAppointmentController', 'changeStatus');
$router->post('/admin/appointments/upload-receipt/{id}', 'AdminAppointmentController', 'uploadReceipt');
$router->get('/admin/appointments/attachment/{id}', 'AdminAppointmentController', 'attachment');
$router->get('/admin/appointments/print-ticket/{id}', 'AdminAppointmentController', 'printTicket');

// Admin - Doctors
$router->get('/admin/doctors', 'AdminDoctorController', 'index');
$router->get('/admin/doctors/create', 'AdminDoctorController', 'create');
$router->post('/admin/doctors/store', 'AdminDoctorController', 'store');
$router->get('/admin/doctors/edit/{id}', 'AdminDoctorController', 'edit');
$router->post('/admin/doctors/update/{id}', 'AdminDoctorController', 'update');
$router->post('/admin/doctors/delete/{id}', 'AdminDoctorController', 'delete');
$router->post('/admin/doctors/toggle/{id}', 'AdminDoctorController', 'toggleStatus');

// Admin - Patients
$router->get('/admin/patients', 'AdminPatientController', 'index');
$router->get('/admin/patients/create', 'AdminPatientController', 'create');
$router->post('/admin/patients/store', 'AdminPatientController', 'store');
$router->get('/admin/patients/edit/{id}', 'AdminPatientController', 'edit');
$router->post('/admin/patients/update/{id}', 'AdminPatientController', 'update');
$router->post('/admin/patients/delete/{id}', 'AdminPatientController', 'delete');

// Admin - Credentials (Modal AJAX para Médicos y Pacientes)
$router->post('/admin/credentials/get', 'AdminCredentialsController', 'getCredentials');
$router->post('/admin/credentials/generate-password', 'AdminCredentialsController', 'generatePassword');

// Admin - Staff (Personal Administrativo y Operativo)
$router->get('/admin/staff', 'AdminStaffController', 'index');
$router->get('/admin/staff/create', 'AdminStaffController', 'create');
$router->post('/admin/staff/store', 'AdminStaffController', 'store');
$router->get('/admin/staff/edit/{id}', 'AdminStaffController', 'edit');
$router->post('/admin/staff/update/{id}', 'AdminStaffController', 'update');
$router->post('/admin/staff/delete/{id}', 'AdminStaffController', 'delete');
$router->post('/admin/staff/toggle/{id}', 'AdminStaffController', 'toggleStatus');

// Admin - Users
$router->get('/admin/users', 'AdminUserController', 'index');
$router->get('/admin/users/create', 'AdminUserController', 'create');
$router->post('/admin/users/store', 'AdminUserController', 'store');
$router->get('/admin/users/edit/{id}', 'AdminUserController', 'edit');
$router->post('/admin/users/update/{id}', 'AdminUserController', 'update');
$router->post('/admin/users/delete/{id}', 'AdminUserController', 'delete');
$router->post('/admin/users/toggle/{id}', 'AdminUserController', 'toggleStatus');

// Admin - Roles & Permissions
$router->get('/admin/roles', 'AdminRoleController', 'index');
$router->post('/admin/roles/store', 'AdminRoleController', 'store');
$router->get('/admin/roles/get-permissions', 'AdminRoleController', 'getPermissionsAjax');
$router->post('/admin/roles/save-permissions-ajax', 'AdminRoleController', 'savePermissionsAjax');
$router->get('/admin/roles/edit/{id}', 'AdminRoleController', 'edit');
$router->post('/admin/roles/edit/{id}', 'AdminRoleController', 'update');
$router->post('/admin/roles/update/{id}', 'AdminRoleController', 'update');
$router->post('/admin/roles/update-info/{id}', 'AdminRoleController', 'updateRoleInfo');
$router->post('/admin/roles/delete/{id}', 'AdminRoleController', 'deleteRole');

// Admin - Specialties
$router->get('/admin/specialties', 'AdminSpecialtyController', 'index');
$router->post('/admin/specialties/store', 'AdminSpecialtyController', 'store');
$router->get('/admin/specialties/edit/{id}', 'AdminSpecialtyController', 'edit');
$router->post('/admin/specialties/update/{id}', 'AdminSpecialtyController', 'update');
$router->post('/admin/specialties/delete/{id}', 'AdminSpecialtyController', 'delete');
$router->post('/admin/specialties/toggle/{id}', 'AdminSpecialtyController', 'toggleStatus');
$router->post('/admin/specialties/store-ajax', 'AdminSpecialtyController', 'storeAjax');

// Admin - Schedules
$router->get('/admin/schedules', 'AdminScheduleController', 'index');
$router->get('/admin/schedules/view', 'AdminScheduleController', 'index');
$router->post('/admin/schedules/generate', 'AdminScheduleController', 'generate');
$router->post('/admin/schedules/block/{id}', 'AdminScheduleController', 'block');
$router->post('/admin/schedules/unblock/{id}', 'AdminScheduleController', 'unblock');
$router->post('/admin/schedules/delete-all', 'AdminScheduleController', 'deleteAllSlots');
$router->post('/admin/schedules/delete/{id}', 'AdminScheduleController', 'deleteSlot');

// Admin - Services (Lab & Imaging)
$router->get('/admin/services', 'AdminServiceController', 'index');
$router->post('/admin/services/store', 'AdminServiceController', 'store');
$router->post('/admin/services/update/{id}', 'AdminServiceController', 'update');
$router->post('/admin/services/delete/{id}', 'AdminServiceController', 'delete');

// Admin - Orders
$router->get('/admin/orders', 'AdminOrderController', 'index');
$router->get('/admin/orders/create/{id}', 'AdminOrderController', 'create');
$router->get('/admin/orders/catalog-items/{id}', 'AdminOrderController', 'getCatalogForAppointment');
$router->post('/admin/orders/store', 'AdminOrderController', 'store');
$router->get('/admin/orders/show/{id}', 'AdminOrderController', 'show');
$router->get('/admin/orders/edit/{id}', 'AdminOrderController', 'edit');
$router->post('/admin/orders/update/{id}', 'AdminOrderController', 'update');
$router->post('/admin/orders/status/{id}', 'AdminOrderController', 'updateStatus');
$router->post('/admin/orders/delete/{id}', 'AdminOrderController', 'delete');
$router->get('/admin/orders/download/{id}', 'AdminOrderController', 'download');

// Admin - Settings
$router->get('/admin/settings', 'AdminSettingsController', 'index');
$router->post('/admin/settings/update', 'AdminSettingsController', 'update');
$router->get('/admin/settings/test-email', 'AdminSettingsController', 'testEmail');
$router->post('/admin/settings/catalog-type/store', 'AdminSettingsController', 'storeCatalogType');
$router->post('/admin/settings/catalog-type/update/{code}', 'AdminSettingsController', 'updateCatalogType');
$router->post('/admin/settings/catalog-type/delete/{id}', 'AdminSettingsController', 'deleteCatalogType');

// Admin - Email Templates
$router->get('/admin/email-templates', 'AdminEmailTemplateController', 'index');
$router->post('/admin/email-templates/update', 'AdminEmailTemplateController', 'update');

// Admin - Database Backups
$router->get('/admin/backups', 'AdminBackupController', 'index');
$router->post('/admin/backups/create', 'AdminBackupController', 'create');
$router->get('/admin/backups/download/{filename}', 'AdminBackupController', 'download');
$router->post('/admin/backups/delete/{filename}', 'AdminBackupController', 'delete');

// Catálogos -> Categorías e Ítems
$router->post('/admin/services/category/store', 'AdminServiceController', 'storeCategory');
$router->post('/admin/services/category/delete/{id}', 'AdminServiceController', 'deleteCategory');
$router->post('/admin/services/catalog-item/store', 'AdminServiceController', 'storeCatalogItem');
$router->post('/admin/services/catalog-item/delete/{id}', 'AdminServiceController', 'deleteCatalogItem');

// ==========================================
// MOBILE API v1
// ==========================================
$router->post('/api/v1/auth/login', 'MobileApiController', 'login');
$router->post('/api/v1/auth/refresh', 'MobileApiController', 'refresh');
$router->post('/api/v1/auth/logout', 'MobileApiController', 'logout');
$router->get('/api/v1/doctor/appointments', 'MobileApiController', 'doctorAppointments');
$router->get('/api/v1/patient/appointments', 'MobileApiController', 'patientAppointments');
$router->get('/api/v1/appointments/upcoming', 'MobileApiController', 'getUpcoming');

// Doctor Profile & Schedule (Mobile)
$router->get('/api/v1/doctor/profile', 'MobileApiController', 'doctorProfile');
$router->post('/api/v1/doctor/profile', 'MobileApiController', 'updateDoctorProfile');
$router->get('/api/v1/doctor/schedule', 'MobileApiController', 'doctorSchedule');
$router->post('/api/v1/doctor/schedule/generate', 'MobileApiController', 'generateSchedule');
$router->post('/api/v1/doctor/schedule/delete/{id}', 'MobileApiController', 'deleteScheduleSlot');
