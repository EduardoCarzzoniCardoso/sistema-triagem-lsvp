const senhaAtualInput = document.getElementById('senha_atual');
const novaSenhaInput = document.getElementById('senha');
const confirmarNovaSenhaInput = document.getElementById('confirmar_senha');

const senhaRequisitos = document.querySelector('.senha-requisitos');
const reqLength = document.getElementById('req-length');
const reqUppercase = document.getElementById('req-uppercase');
const reqNumber = document.getElementById('req-number');

const confirmarMsg = document.getElementById('msg-confirmar-senha');
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

function checkNovaSenhaRequirementsMet() {
  const senha = novaSenhaInput.value;
  const isLengthMet = senha.length >= 8;
  const isUppercaseMet = /[A-Z]/.test(senha);
  const isNumberMet = /\d/.test(senha);

  return isLengthMet && isUppercaseMet && isNumberMet;
}

function validarNovaSenha() {
  const senha = novaSenhaInput.value;

  if (senhaRequisitos) {
    if (senha.length === 0) {
      senhaRequisitos.style.display = 'none';
    } else {
      senhaRequisitos.style.display = 'block';
    }

    updateRequisito(reqLength, senha.length >= 8);
    updateRequisito(reqUppercase, /[A-Z]/.test(senha));
    updateRequisito(reqNumber, /\d/.test(senha));
  }

  validarConfirmacao();
  updateSubmitButtonState();
}

function validarConfirmacao() {
  const novaSenha = novaSenhaInput.value;
  const confirmar = confirmarNovaSenhaInput.value;

  if (confirmarMsg) {
    if (confirmar.length === 0 && novaSenha.length === 0) {
      confirmarMsg.style.display = 'none';
    } else if (novaSenha !== confirmar) {
      confirmarMsg.style.display = 'block';
    } else {
      confirmarMsg.style.display = 'none';
    }
  }
  
  updateSubmitButtonState(); 
}

function updateSubmitButtonState() {
  const allRequirementsMet = checkNovaSenhaRequirementsMet();
  const passwordsMatch = novaSenhaInput.value === confirmarNovaSenhaInput.value && novaSenhaInput.value.length > 0;
  
  let currentPasswordFilled = true;
  const inputGroupSenhaAtual = document.getElementById('input-group-senha-atual');

  if (inputGroupSenhaAtual && senhaAtualInput) { 
      currentPasswordFilled = senhaAtualInput.value.length > 0;
  }
  submitButton.disabled = !(allRequirementsMet && passwordsMatch && currentPasswordFilled);
}

if (senhaAtualInput) { 
    senhaAtualInput.addEventListener('input', updateSubmitButtonState);
}
novaSenhaInput.addEventListener('input', validarNovaSenha);
confirmarNovaSenhaInput.addEventListener('input', validarConfirmacao);
confirmarNovaSenhaInput.addEventListener('blur', validarConfirmacao);

document.addEventListener('DOMContentLoaded', () => {
  if (senhaRequisitos) senhaRequisitos.style.display = 'none';
  if (confirmarMsg) confirmarMsg.style.display = 'none';
  submitButton.disabled = true;

  const phpMessage = document.querySelector('.mensagem-login');
  if (phpMessage && phpMessage.textContent.trim() !== '') {
      phpMessage.style.display = 'block';
  }
  updateSubmitButtonState(); 
});