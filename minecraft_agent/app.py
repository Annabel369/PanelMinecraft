# app.py - Agente Python para gerenciar o servidor Minecraft no Windows

# Importa as bibliotecas necessárias. Flask para criar o servidor web,
# psutil para gerenciar processos no sistema, e subprocess para iniciar o servidor.
from flask import Flask, jsonify, request
import subprocess
import logging
import os
import psutil
import time
from mcrcon import MCRcon # Importado para uso geral

# Importa as configurações do arquivo config.py.
# MINECRAFT_SERVER_DIR é o novo diretório que adicionamos para Windows.
from config import (
    RCON_HOST, RCON_PORT, RCON_PASSWORD,
    MINECRAFT_START_COMMAND, AGENT_PORT,
    MINECRAFT_SERVER_DIR
)

# Configuração do logging para ver o que está acontecendo.
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')

# Cria a instância do aplicativo Flask.
app = Flask(__name__)
server_process = None

# --- Funções para Gerenciamento de Processos no Windows ---

def find_minecraft_process():
    """
    Tenta encontrar o processo do servidor Minecraft em execução.
    Ele procura por um processo 'java.exe' que esteja rodando o
    'fabric-server-launch.jar'.
    Retorna o objeto do processo (psutil) se encontrado, caso contrário, retorna None.
    """
    # A verificação deve procurar por 'java' e o nome do seu arquivo JAR.
    jar_name = MINECRAFT_START_COMMAND[4] if len(MINECRAFT_START_COMMAND) > 4 else None
    
    for proc in psutil.process_iter(['pid', 'name', 'cmdline']):
        try:
            # Verifica se o 'cmdline' não é None antes de tentar unir.
            if proc.info['cmdline'] is not None:
                cmdline = " ".join(proc.info['cmdline'])
                if 'java' in proc.info['name'].lower() and jar_name in cmdline:
                    logging.info(f"Processo do servidor encontrado: PID {proc.info['pid']}")
                    return proc
        except (psutil.NoSuchProcess, psutil.AccessDenied, psutil.ZombieProcess):
            pass
    return None

def is_rcon_ready():
    """
    Tenta se conectar via RCON para verificar se o servidor está pronto para aceitar comandos.
    """
    try:
        with MCRcon(RCON_HOST, RCON_PASSWORD, RCON_PORT) as mcr:
            mcr.command('say Server status check') # Envia um comando de teste
        return True
    except Exception as e:
        logging.warning(f"RCON connection failed: {e}")
        return False

# --- Rotas (Endpoints) para a Página PHP ---

@app.route('/start_server', methods=['POST'])
def start_server():
    """
    Endpoint para iniciar o servidor Minecraft.
    """
    # Verifica se o servidor já está rodando para evitar duplicatas.
    if find_minecraft_process():
        return jsonify({'success': False, 'error': 'O servidor já está rodando.'}), 409

    try:
        logging.info(f"Tentando iniciar servidor com comando: {MINECRAFT_START_COMMAND}")
        
        # Inicia o processo em background usando subprocess.Popen.
        # 'cwd' (current working directory) é crucial para que o comando encontre o 'server.jar'.
        # 'creationflags' evita que uma nova janela de console apareça.
        server_process = subprocess.Popen(
            MINECRAFT_START_COMMAND,
            cwd=MINECRAFT_SERVER_DIR,
            shell=True,
            creationflags=subprocess.CREATE_NO_WINDOW
        )
        # Pequeno atraso para o processo iniciar
        time.sleep(2)
        return jsonify({'success': True, 'message': 'Servidor iniciado com sucesso.'})
    except Exception as e:
        logging.error(f"Erro inesperado ao iniciar servidor: {e}")
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/stop_server', methods=['POST'])
def stop_server():
    """
    Endpoint para parar o servidor Minecraft.
    """
    process = find_minecraft_process()
    if not process:
        return jsonify({'success': False, 'error': 'O servidor não está rodando.'}), 404

    try:
        logging.info(f"Parando processo do servidor com PID: {process.pid}")
        # Usa o método terminate para fechar o processo de forma segura.
        process.terminate()
        # Aguarda um tempo para garantir que o processo foi encerrado.
        process.wait(timeout=10)
        return jsonify({'success': True, 'message': 'Servidor parado com sucesso.'})
    except Exception as e:
        logging.error(f"Erro ao parar o servidor: {e}")
        return jsonify({'success': False, 'error': 'Erro ao parar o servidor: ' + str(e)}), 500

@app.route('/server_status', methods=['GET'])
def server_status():
    """
    Endpoint para verificar o status do servidor Minecraft, incluindo uso de RAM e se está pronto.
    """
    process = find_minecraft_process()
    is_running = process is not None
    status = 'Rodando' if is_running else 'Parado'
    ram_usage = 'N/A'
    is_ready = False
    
    if is_running:
        try:
            # O `rss` (Resident Set Size) é a memória física (RAM) real que o processo está usando.
            # O valor é em bytes, então dividimos por 1024^2 para converter para MB.
            ram_usage_mb = process.memory_info().rss / (1024 * 1024)
            ram_usage = f"{ram_usage_mb:.2f} MB"
            
            # Tenta verificar se o servidor está pronto para aceitar comandos RCON
            is_ready = is_rcon_ready()
            
            if not is_ready:
                status = "Iniciando..."
                
        except (psutil.NoSuchProcess, psutil.AccessDenied):
            status = 'Erro de Acesso'
            is_running = False
            
    logging.info(f"Status verificado: {status}, Uso de RAM: {ram_usage}, Pronto: {is_ready}")
    
    # Retorna o status de forma simples, sem a necessidade de parsing de saída complexa.
    return jsonify({
        'success': True, 
        'status': status, 
        'is_running': is_running,
        'ram_usage': ram_usage,
        'is_ready': is_ready
    }), 200

# Esta rota é do seu código original e deve funcionar bem, já que não depende de comandos do sistema.
@app.route('/rcon_command', methods=['POST'])
def rcon_command():
    """
    Executa um comando RCON no servidor Minecraft.
    Esperado JSON: {"command": "say Hello World"}
    """
    data = request.get_json()
    if not data or 'command' not in data:
        return jsonify({"error": "Comando RCON ausente"}), 400

    command = data['command']
    try:
        with MCRcon(RCON_HOST, RCON_PASSWORD, RCON_PORT) as mcr:
            response = mcr.command(command)
        logging.info(f"Comando RCON '{command}' executado. Resposta: {response}")
        return jsonify({"success": True, "response": response}), 200
    except Exception as e:
        logging.error(f"Erro ao executar comando RCON '{command}': {e}")
        return jsonify({"success": False, "error": str(e)}), 500

if __name__ == '__main__':
    # O servidor irá rodar na porta configurada no config.py.
    app.run(host='0.0.0.0', port=AGENT_PORT)