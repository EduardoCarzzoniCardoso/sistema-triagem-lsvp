const senhaInput = document.getElementById('senha');
const confirmarSenhaInput = document.getElementById('confirmar_senha');

const senhaRequisitos = document.querySelector('.senha-requisitos');
const reqLength = document.getElementById('req-length');
const reqUppercase = document.getElementById('req-uppercase');
const reqNumber = document.getElementById('req-number');

const confirmarMsg = document.querySelector('.msg-error');

const togglePasswordSenha = document.getElementById('togglePasswordSenha');
const togglePasswordConfirmar = document.getElementById('togglePasswordConfirmar');
const submitButton = document.querySelector('button[type="submit"]');

function updateRequisito(element, isValid) {
  const iconSpan = element.querySelector('.icon');
  if (isValid) {
    element.classList.add('ok');
    iconSpan.textContent = '✔';
  } else {
    element.classList.remove('ok');
    iconSpan.textContent = '✘';
  }
}

function checkPasswordRequirementsMet() {
  const senha = senhaInput.value;
  const isLengthMet = senha.length >= 8;
  const isUppercaseMet = /[A-Z]/.test(senha);
  const isNumberMet = /\d/.test(senha);

  return isLengthMet && isUppercaseMet && isNumberMet;
}

function validarSenha() {
  const senha = senhaInput.value;

  if (senha.length === 0) {
    senhaRequisitos.style.display = 'none';
  } else {
    senhaRequisitos.style.display = 'block';
  }

  updateRequisito(reqLength, senha.length >= 8);
  updateRequisito(reqUppercase, /[A-Z]/.test(senha));
  updateRequisito(reqNumber, /\d/.test(senha));

  updateSubmitButtonState();
}

function validarConfirmacao() {
  const senha = senhaInput.value;
  const confirmar = confirmarSenhaInput.value;

  if (confirmar.length === 0 && senha.length === 0) {
    confirmarMsg.style.display = 'none';
  } else if (senha !== confirmar) {
    confirmarMsg.style.display = 'block';
  } else {
    confirmarMsg.style.display = 'none';
  }
  
  updateSubmitButtonState(); 
}

function updateSubmitButtonState() {
  const allRequirementsMet = checkPasswordRequirementsMet();
  const passwordsMatch = senhaInput.value === confirmarSenhaInput.value && senhaInput.value.length > 0;
  
  submitButton.disabled = !(allRequirementsMet && passwordsMatch);
}

function setupPasswordToggle(toggleElement, inputElement, eyeOpenId, eyeClosedId) {
    if (!toggleElement || !inputElement) return;

    const eyeOpen = toggleElement.querySelector('#' + eyeOpenId);
    const eyeClosed = toggleElement.querySelector('#' + eyeClosedId);

    toggleElement.addEventListener('click', function () {
        const type = inputElement.getAttribute('type') === 'password' ? 'text' : 'password';
        inputElement.setAttribute('type', type);

        if (type === 'password') {
            eyeOpen.style.display = 'block';
            eyeClosed.style.display = 'none';
        } else {
            eyeOpen.style.display = 'none';
            eyeClosed.style.display = 'block';
        }
    });
}

senhaInput.addEventListener('input', validarSenha);
confirmarSenhaInput.addEventListener('input', validarConfirmacao);
confirmarSenhaInput.addEventListener('blur', validarConfirmacao);

document.addEventListener('DOMContentLoaded', () => {
  setupPasswordToggle(togglePasswordSenha, senhaInput, 'eye-open-senha', 'eye-closed-senha');
  setupPasswordToggle(togglePasswordConfirmar, confirmarSenhaInput, 'eye-open-confirmar', 'eye-closed-confirmar');

  senhaRequisitos.style.display = 'none';
  confirmarMsg.style.display = 'none';

  submitButton.disabled = true;

  const phpMessage = document.querySelector('.mensagem-login');
  if (phpMessage && phpMessage.textContent.trim() !== '') {
      phpMessage.style.display = 'block';
  }
});