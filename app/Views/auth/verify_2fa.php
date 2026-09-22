<div class="container main-content d-flex align-center" style="min-height: 75vh; padding: 2rem 1rem;">
    <div class="card mx-auto shadow-lg" style="max-width: 480px; width: 100%; border-radius: 16px; overflow: hidden; border: 1px solid var(--border);">
        <div class="card-header text-center d-block" style="background: linear-gradient(135deg, rgba(37, 99, 235, 0.08), rgba(99, 102, 241, 0.08)); padding: 2rem 1.5rem 1.5rem; border-bottom: 1px solid var(--border);">
            <div style="width: 64px; height: 64px; margin: 0 auto 1rem; background: #eef2ff; color: var(--primary, #2563eb); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.75rem; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.15);">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <h2 class="mb-1" style="font-weight: 700; font-size: 1.5rem;">Verificación en Dos Pasos</h2>
            <p class="text-muted mb-0" style="font-size: 0.9rem;">Protección adicional para su cuenta</p>
        </div>
        <div class="card-body p-4">
            <div class="text-center mb-4">
                <p class="text-muted mb-2" style="font-size: 0.95rem;">
                    Hemos enviado un código numérico de <strong>6 dígitos</strong> a:
                </p>
                <div style="display: inline-block; background: var(--surface-2, #f1f5f9); padding: 0.4rem 1rem; border-radius: 20px; font-weight: 600; color: var(--text, #1e293b); font-size: 0.95rem; border: 1px solid var(--border);">
                    <i class="fa-solid fa-envelope me-1 text-primary"></i> <?= htmlspecialchars($maskedEmail ?? '') ?>
                </div>
            </div>
            <form id="form-2fa" action="<?= $baseUrl ?>/login/2fa" method="POST">
                <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::generateCsrf() ?>">
                <input type="hidden" name="code" id="full-code-input" value="">
                <div class="form-group mb-4">
                    <label class="form-label text-center d-block mb-3" style="font-weight: 600; font-size: 0.9rem;">
                        Ingrese el código recibido
                    </label>
                    <div class="d-flex justify-content-between gap-2" style="max-width: 360px; margin: 0 auto;" id="otp-container">
                        <?php for ($i = 1; $i <= 6; $i++): ?>
                            <input type="text" 
                                   name="code_<?= $i ?>" 
                                   id="otp_<?= $i ?>" 
                                   class="form-control text-center otp-box" 
                                   maxlength="1" 
                                   inputmode="numeric" 
                                   autocomplete="one-time-code"
                                   required 
                                   style="width: 48px; height: 56px; font-size: 1.6rem; font-weight: 700; border-radius: 10px; border: 2px solid var(--border); transition: all 0.2s;"
                                   <?= $i === 1 ? 'autofocus' : '' ?>>
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-4 px-2" style="font-size: 0.85rem;">
                    <span id="countdown-timer" class="text-muted">
                        <i class="fa-regular fa-clock me-1"></i> Expira en: <strong id="timer-display" class="text-danger">--:--</strong>
                    </span>
                    <span class="text-muted">
                        <i class="fa-solid fa-shield-check text-success"></i> Conexión segura
                    </span>
                </div>
                <button type="submit" id="btn-submit-2fa" class="btn btn-primary btn-block btn-lg mb-3" style="font-weight: 600; height: 48px;">
                    <i class="fa-solid fa-check-circle me-1"></i> Confirmar e Ingresar
                </button>
            </form>
            <div class="border-top pt-3 mt-3 text-center">
                <form id="form-resend-2fa" action="<?= $baseUrl ?>/login/2fa/resend" method="POST" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= \App\Helpers\Session::generateCsrf() ?>">
                    <button type="submit" id="btn-resend" class="btn btn-link text-decoration-none p-0" style="font-size: 0.9rem; font-weight: 500;">
                        <i class="fa-solid fa-rotate-right me-1"></i> <span id="resend-text">¿No recibió el código? Reenviar</span>
                    </button>
                </form>
                <div class="mt-3">
                    <a href="<?= $baseUrl ?>/login/2fa/cancel" class="text-muted text-decoration-none" style="font-size: 0.85rem;">
                        <i class="fa-solid fa-arrow-left me-1"></i> Cancelar e iniciar sesión con otra cuenta
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
.otp-box:focus {
    border-color: var(--primary, #2563eb) !important;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2) !important;
    outline: none;
    transform: translateY(-2px);
}
.otp-box.filled {
    border-color: #10b981;
    background-color: rgba(16, 185, 129, 0.05);
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('.otp-box');
    const fullInput = document.getElementById('full-code-input');
    const form = document.getElementById('form-2fa');
    const submitBtn = document.getElementById('btn-submit-2fa');
    let isSubmitting = false;
    let autoSubmitTimer = null;
    function getCombinedCode() {
        let code = '';
        inputs.forEach(i => code += i.value);
        return code;
    }
    function updateFullCode() {
        fullInput.value = getCombinedCode();
    }
    function doSubmit() {
        if (isSubmitting) return;
        const code = getCombinedCode();
        if (code.length !== 6) {
            // Foco en el primer campo vacío
            for (let i = 0; i < inputs.length; i++) {
                if (!inputs[i].value) {
                    inputs[i].focus();
                    break;
                }
            }
            return;
        }
        isSubmitting = true;
        if (autoSubmitTimer) {
            clearTimeout(autoSubmitTimer);
            autoSubmitTimer = null;
        }
        fullInput.value = code;
        // Deshabilitar botón y campos para evitar peticiones duplicadas / condiciones de carrera
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin me-2"></i> Verificando código...';
        }
        inputs.forEach(i => i.readOnly = true);
        form.submit();
    }
    // Interceptar evento submit para evitar doble envío nativo
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        doSubmit();
    });
    function checkAutoSubmit() {
        if (isSubmitting) return;
        const code = getCombinedCode();
        if (code.length === 6) {
            if (autoSubmitTimer) clearTimeout(autoSubmitTimer);
            autoSubmitTimer = setTimeout(() => {
                doSubmit();
            }, 250);
        }
    }
    // Manejar ingreso y salto entre casillas OTP
    inputs.forEach((input, index) => {
        input.addEventListener('input', function(e) {
            if (isSubmitting) return;
            const val = this.value.replace(/\D/g, '');
            this.value = val ? val[0] : '';
            if (this.value) {
                this.classList.add('filled');
                if (index < inputs.length - 1) {
                    inputs[index + 1].focus();
                    inputs[index + 1].select();
                }
            } else {
                this.classList.remove('filled');
            }
            updateFullCode();
            checkAutoSubmit();
        });
        input.addEventListener('keydown', function(e) {
            if (isSubmitting) return;
            if (e.key === 'Enter') {
                e.preventDefault();
                doSubmit();
                return;
            }
            if (e.key === 'Backspace') {
                if (!this.value && index > 0) {
                    inputs[index - 1].focus();
                    inputs[index - 1].value = '';
                    inputs[index - 1].classList.remove('filled');
                    e.preventDefault();
                    updateFullCode();
                } else {
                    this.classList.remove('filled');
                }
            } else if (e.key === 'ArrowLeft' && index > 0) {
                inputs[index - 1].focus();
            } else if (e.key === 'ArrowRight' && index < inputs.length - 1) {
                inputs[index + 1].focus();
            }
        });
        // Soporte para pegar el código completo de 6 dígitos
        input.addEventListener('paste', function(e) {
            if (isSubmitting) return;
            e.preventDefault();
            const pasteData = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').trim();
            if (pasteData.length >= 6) {
                for (let i = 0; i < 6; i++) {
                    if (inputs[i]) {
                        inputs[i].value = pasteData[i];
                        inputs[i].classList.add('filled');
                    }
                }
                updateFullCode();
                inputs[5].focus();
                checkAutoSubmit();
            } else if (pasteData.length > 0) {
                for (let i = 0; i < pasteData.length && (index + i) < inputs.length; i++) {
                    inputs[index + i].value = pasteData[i];
                    inputs[index + i].classList.add('filled');
                }
                const nextIdx = Math.min(inputs.length - 1, index + pasteData.length);
                inputs[nextIdx].focus();
                updateFullCode();
            }
        });
    });
    // Temporizador de expiración
    let timeLeft = <?= (int)($secondsRemaining ?? 600) ?>;
    const timerDisplay = document.getElementById('timer-display');
    const countdownContainer = document.getElementById('countdown-timer');
    function updateTimer() {
        if (timeLeft <= 0) {
            timerDisplay.textContent = 'Expirado';
            countdownContainer.classList.add('text-danger');
            return;
        }
        const minutes = Math.floor(timeLeft / 60);
        const seconds = timeLeft % 60;
        timerDisplay.textContent = 
            (minutes < 10 ? '0' : '') + minutes + ':' + 
            (seconds < 10 ? '0' : '') + seconds;
        timeLeft--;
    }
    updateTimer();
    const interval = setInterval(updateTimer, 1000);
    // Cooldown para botón de reenvío
    const btnResend = document.getElementById('btn-resend');
    const resendText = document.getElementById('resend-text');
    const formResend = document.getElementById('form-resend-2fa');
    let canResendNow = <?= ($canResend ?? true) ? 'true' : 'false' ?>;
    if (formResend) {
        formResend.addEventListener('submit', function(e) {
            if (btnResend.disabled) {
                e.preventDefault();
                return;
            }
            btnResend.disabled = true;
            resendText.textContent = 'Enviando nuevo código...';
        });
    }
    if (!canResendNow) {
        btnResend.disabled = true;
        let resendCooldown = 45;
        const resendInterval = setInterval(() => {
            if (resendCooldown <= 0) {
                clearInterval(resendInterval);
                btnResend.disabled = false;
                resendText.textContent = '¿No recibió el código? Reenviar';
            } else {
                resendText.textContent = 'Reenviar en ' + resendCooldown + 's';
                resendCooldown--;
            }
        }, 1000);
    }
});
</script>
