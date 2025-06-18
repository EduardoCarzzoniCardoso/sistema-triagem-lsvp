document.addEventListener('DOMContentLoaded', () => {
    const currentUserName = typeof userNameLoggedIn !== 'undefined' ? userNameLoggedIn : 'Usuário';

    const logoutArea = document.querySelector('.logout-area');
    if (logoutArea) {
        const chatbotHeaderButton = document.createElement('button');
        chatbotHeaderButton.id = 'chatbot-header-button';
        chatbotHeaderButton.title = 'Abrir Chatbot';

        const chatbotIconImg = document.createElement('img');
        chatbotIconImg.src = 'images/chatbot.png';
        chatbotIconImg.alt = 'Chatbot';

        chatbotHeaderButton.appendChild(chatbotIconImg);

        const logoutButton = logoutArea.querySelector('.logout-button');
        if (logoutButton) {
            logoutArea.insertBefore(chatbotHeaderButton, logoutButton);
        } else {
            logoutArea.appendChild(chatbotHeaderButton);
        }
    }

    const chatbotContainerHTML = `
        <div id="chatbot-container">
            <div id="chatbot-header">
                <span>Chatbot</span>
                <button id="close-chatbot">&times;</button>
            </div>
            <div id="chatbot-body">
            </div>
            <div id="chatbot-footer">
                <textarea id="user-input" placeholder="Digite sua mensagem..." rows="1"></textarea>
                <button id="send-button">Enviar</button>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', chatbotContainerHTML);

    const chatbotContainer = document.getElementById('chatbot-container');
    const closeChatbotButton = document.getElementById('close-chatbot');
    const chatbotBody = document.getElementById('chatbot-body');
    const userInput = document.getElementById('user-input');
    const sendButton = document.getElementById('send-button');

    const dynamicChatbotHeaderButton = document.getElementById('chatbot-header-button');
    if (dynamicChatbotHeaderButton) {
        dynamicChatbotHeaderButton.addEventListener('click', () => {
            chatbotContainer.classList.add('open');
            dynamicChatbotHeaderButton.style.display = 'none';
        });
    }

    closeChatbotButton.addEventListener('click', () => {
        chatbotContainer.classList.remove('open');
        if (dynamicChatbotHeaderButton) {
            dynamicChatbotHeaderButton.style.display = 'flex';
        }
    });

    userInput.addEventListener('input', () => {
        userInput.style.height = 'auto';
        userInput.style.height = userInput.scrollHeight + 'px';
    });

    function addMessage(message, sender) {
        const messageWrapper = document.createElement('div');
        messageWrapper.classList.add('message-wrapper', `${sender}-message-wrapper`);

        const messageIcon = document.createElement('div');
        messageIcon.classList.add('message-icon');

        if (sender === 'user') {
            messageIcon.innerHTML = '&#128100;';
        } else {
            const logoImg = document.createElement('img');
            logoImg.src = 'images/logo_lvsp2.png';
            logoImg.alt = 'Logo LSVP';
            messageIcon.appendChild(logoImg);
        }

        const messageDiv = document.createElement('div');
        messageDiv.classList.add('message');
        messageDiv.innerHTML = message.replace(/\n/g, '<br>');

        if (sender === 'user') {
            messageWrapper.appendChild(messageDiv);
            messageWrapper.appendChild(messageIcon);
        } else {
            messageWrapper.appendChild(messageIcon);
            messageWrapper.appendChild(messageDiv);
        }

        chatbotBody.appendChild(messageWrapper);
        chatbotBody.scrollTop = chatbotBody.scrollHeight;
    }

    const opcoesDisponiveisFrontend = `Posso ajudar com os seguintes tópicos. Tente digitar uma das frases exatas:
- Ajuda
- Status do sistema
- Login
- Recuperar senha
- Recuperar codigo
- Ativar conta
- Processo de triagem
- O que e lsvp
- Perfil idoso
- Informacoes idoso
- Triagens em andamento
- Documentos triagem
- Suporte tecnico
- Editar usuario
- Desativar usuario
- Parecer coordenador
- Parecer diretoria
- Parecer medico
- Parecer psicologico
- Dados contrato idoso
- Parentes idoso
`;

    addMessage(`Olá, ${currentUserName}! Como posso ajudar hoje?`, 'bot');

    async function sendMessageToBot() {
        const message = userInput.value.trim();
        if (message === '') return;

        addMessage(message, 'user');
        userInput.value = '';
        userInput.style.height = 'auto';

        try {
            const response = await fetch('http://127.0.0.1:5000/chat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ message: message })
            });
            const data = await response.json();
            const botResponseText = data.response;
            addMessage(botResponseText, 'bot');

            if (botResponseText.includes("Desculpe, não entendi. Poderia reformular sua pergunta?")) {
                addMessage("Se precisar de ajuda, digite 'ajuda' ou 'opções' para ver o que posso fazer.", 'bot');
            }

        } catch (error) {
            console.error('Erro ao comunicar com o chatbot:', error);
            addMessage('Desculpe, não consegui me conectar ao serviço de chatbot. Por favor, tente novamente mais tarde.', 'bot');
            addMessage("Parece que estou com dificuldades de conexão. Tente novamente, ou digite 'ajuda' para ver os tópicos.", 'bot');
        }
    }

    sendButton.addEventListener('click', sendMessageToBot);
    userInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessageToBot();
        }
    });
});