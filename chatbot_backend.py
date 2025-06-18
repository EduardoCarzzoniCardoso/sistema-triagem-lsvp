from flask import Flask, request, jsonify
from flask_cors import CORS
import unicodedata

app = Flask(__name__)
CORS(app)

def normalize_text(text):
    text = text.lower()
    text = unicodedata.normalize('NFKD', text).encode('ascii', 'ignore').decode('utf-8')
    return text

@app.route('/chat', methods=['POST'])
def chat():
    user_message_raw = request.json.get('message', '')
    user_message = normalize_text(user_message_raw)
    
    print(f"Mensagem do usuário (normalizada): '{user_message}'") 

    opcoes_disponiveis = """Posso ajudar com os seguintes tópicos. Tente digitar uma das frases exatas:
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
"""
    bot_response = "Desculpe, não entendi. Poderia reformular sua pergunta ou digitar 'ajuda' para ver os tópicos que posso abordar?"

    if "bom dia" in user_message:
        bot_response = "Bom dia! Em que posso ajudar?"
    elif "boa tarde" in user_message:
        bot_response = "Boa tarde! No que posso ser útil?"
    elif "boa noite" in user_message:
        bot_response = "Boa noite! Como posso te auxiliar?"
    elif "ola" in user_message or "oi" in user_message:
        bot_response = "Olá! Como posso ajudar hoje?"
    elif "tchau" in user_message or "ate logo" in user_message or "adeus" in user_message:
        bot_response = "Até logo! Se precisar de algo, é só chamar!"
    elif "muito obrigado" in user_message or "obrigado" in user_message or "valeu" in user_message or "agradeco" in user_message:
        bot_response = "De nada! Fico feliz em ajudar!"
    elif "como voce esta" in user_message or "tudo bem" in user_message:
        bot_response = "Estou bem, obrigado(a)! E você?"
    elif "entendi" in user_message or "ok" in user_message or "certo" in user_message:
        bot_response = "Perfeito! Há mais alguma coisa em que posso ajudar?"
    elif "preciso de ajuda" in user_message or "duvida" in user_message:
        bot_response = "Claro, descreva sua dúvida para que eu possa tentar ajudar."

    elif "ajuda" in user_message or "opcoes" in user_message or "o que voce faz" in user_message or "comandos" in user_message:
        bot_response = "Claro! " + opcoes_disponiveis
    elif "como esta o sistema" in user_message or "status do sistema" in user_message or "sistema funcionando" in user_message:
        bot_response = "O sistema está funcionando normalmente!"
    elif "login" in user_message or "acessar sistema" in user_message:
        bot_response = "Para fazer login, acesse a página de login e insira seu código de acesso e senha."
    elif "recuperar senha" in user_message or "esqueci senha" in user_message:
        bot_response = "Se esqueceu sua senha, clique em 'Esqueceu sua senha?' na página de Login e siga as instruções."
    elif "recuperar codigo" in user_message or "esqueci codigo" in user_message:
        bot_response = "Se esqueceu seu Código de Acesso, clique em 'Esqueceu seu código de acesso?' na página de Login e preencha seu nome e cargo."
    elif "ativar conta" in user_message or "primeiro acesso" in user_message or "cadastrar usuario" in user_message or "novo usuario" in user_message or "cadastrar usuarios" in user_message:
        bot_response = "Para cadastrar um novo usuário: Diretores(as) e Coordenadores(as) devem acessar a seção 'Usuários' no menu, gerar o Código de Acesso e a Senha Temporária. O novo usuário, então, usa esses dados na tela de 'Ativação de Conta' (acessível pela página de Login) para definir sua senha final."
    elif "fluxo de triagem" in user_message or "etapas da triagem" in user_message or "processo de triagem" in user_message or "como triar" in user_message or "fluxo de triagens" in user_message or "processos de triagens" in user_message:
        bot_response = "O processo de triagem para acolhimento de idosos no Lar São Vicente de Paulo inclui as seguintes etapas: Ficha de Triagem (Inicio, Continuacao e Contrato), Pareceres (do Coordenador(a), Diretoria, Medico(a) e Psicologo(a)) e a Finalização da Triagem. Você pode acompanhar o status de cada idoso na aba 'Triagens'."
    elif "o que e lsvp" in user_message or "sobre lsvp" in user_message:
        bot_response = "LSVP significa Lar São Vicente de Paulo, uma instituição que assiste idosos. Nosso sistema de triagem auxilia no processo de acolhimento."
    elif "perfil idoso" in user_message or "idoso aceito" in user_message or "criterios idoso" in user_message or "perfil de idosos" in user_message or "idosos aceitos" in user_message or "criterios de idosos" in user_message:
        bot_response = "O Lar São Vicente de Paulo atende idosos de ambos os sexos, independentes, semidependentes e dependentes, conforme a Resolução RDC nº 283. A idade mínima para residentes é de 60 anos."
    elif "informacoes idoso" in user_message or "dados idoso" in user_message or "ficha idoso" in user_message or "informacoes de idosos" in user_message or "dados de idosos" in user_message or "fichas de idosos" in user_message:
        bot_response = "Para informações detalhadas de um idoso, incluindo documentos e dados complementares, acesse a aba 'Idosos'. Você pode buscar por nome ou CPF."
    elif "triagens em andamento" in user_message or "ver triagens" in user_message:
        bot_response = "Para editar ou visualizar triagens em andamento, vá para a aba 'Triagens'. Você pode usar filtros por nome, CPF, tipo de data e etapa atual. Clique em 'Editar' para continuar preenchendo a ficha."
    elif "documentos triagem" in user_message or "finalizacao triagem" in user_message or "checklist triagem" in user_message or "documentos de triagens" in user_message or "documentos para triagens" in user_message:
        bot_response = "Na etapa de Finalização da Triagem, é necessário verificar e anexar documentos como: certidão de nascimento/casamento, RG, CPF, receituários, medicamentos, roupas de uso pessoal e duas fotos 3x4."
    elif "suporte tecnico" in user_message or "erro sistema" in user_message or "contato tecnico" in user_message:
        bot_response = "Para suporte técnico ou dúvidas específicas sobre o sistema, por favor, entre em contato com a Diretoria ou a equipe de TI responsável pelo sistema."
    elif "notificacoes" in user_message:
        bot_response = "As notificações do sistema, como alertas sobre status de triagens ou mensagens importantes, são exibidas na área superior da tela inicial. Verifique o ícone de sino para novas notificações."
    elif "editar usuario" in user_message or "modificar usuario" in user_message:
        bot_response = "Para editar um usuário, acesse a aba 'Usuários'. Localize o usuário na tabela e utilize o botão 'Editar' na coluna 'Ações'."
    elif "desativar usuario" in user_message:
        bot_response = "Para desativar um usuário, acesse a aba 'Usuários'. Localize o usuário na tabela e utilize o botão de ação correspondente para alterar seu status."
    elif "parecer coordenador" in user_message:
        bot_response = "O Parecer do(a) Coordenador(a) é uma etapa do processo de triagem. Você acessa e preenche este parecer na aba 'Triagens', navegando pelas etapas laterais da ficha do idoso."
    elif "parecer diretoria" in user_message:
        bot_response = "O Parecer da Diretoria é uma etapa do processo de triagem. Você acessa e preenche este parecer na aba 'Triagens', navegando pelas etapas laterais da ficha do idoso."
    elif "parecer medico" in user_message:
        bot_response = "O Parecer do(a) Médico(a) é uma etapa do processo de triagem. Você acessa e preenche este parecer na aba 'Triagens', navegando pelas etapas laterais da ficha do idoso."
    elif "parecer psicologico" in user_message:
        bot_response = "O Parecer Psicológico é uma etapa do processo de triagem. Você acessa e preenche este parecer na aba 'Triagens', navegando pelas etapas laterais da ficha do idoso."
    elif "dados contrato idoso" in user_message:
        bot_response = "Dados complementares para o contrato são preenchidos na ficha do idoso, na etapa 'Ficha de Triagem - Contrato'."
    elif "parentes idoso" in user_message:
        bot_response = "Informações de parentes/conhecidos são preenchidas na ficha do idoso, na etapa 'Ficha de Triagem - Continuação'."

    return jsonify({"response": bot_response})

if __name__ == '__main__':
    app.run(port=5000)