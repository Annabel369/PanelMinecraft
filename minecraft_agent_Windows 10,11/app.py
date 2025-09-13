# app.py - Agente Flask para gerenciar servidor Minecraft no Windows

from flask import Flask, jsonify, request
import subprocess
import logging
import os
import psutil
import time
from mcrcon import MCRcon
from config import (
    RCON_HOST, RCON_PORT, RCON_PASSWORD,
    MINECRAFT_START_COMMAND, AGENT_PORT,
    MINECRAFT_SERVER_DIR
)

# Configuração de logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')

app = Flask(__name__)

# --- Funções de gerenciamento ---

def find_minecraft_process():
    """Localiza o processo Java que executa o servidor Minecraft."""
    for proc in psutil.process_iter(['pid', 'name', 'cmdline']):
        try:
            if proc.info['cmdline'] and 'java' in proc.info['name'].lower():
                # A verificação deve ser mais robusta, procurando o .jar específico.
                # A função 'any' já é a melhor opção, então vamos mantê-la
                if any('fabric-server-launch.jar' in arg for arg in proc.info['cmdline']):
                    logging.info(f"Processo encontrado: PID {proc.pid}")
                    return proc
        except (psutil.NoSuchProcess, psutil.AccessDenied, psutil.ZombieProcess):
            continue
    return None

def is_rcon_ready():
    """Verifica se o servidor está aceitando comandos via RCON."""
    try:
        with MCRcon(RCON_HOST, RCON_PASSWORD, RCON_PORT) as mcr:
            mcr.command('say Verificação de status via RCON')
        return True
    except Exception as e:
        # A mensagem de falha na conexão RCON é útil, vamos mantê-la.
        logging.warning(f"Falha na conexão RCON: {e}")
        return False

# --- Endpoints ---

@app.route('/start_server', methods=['POST'])
def start_server():
    """Inicia o servidor Minecraft."""
    if find_minecraft_process():
        return jsonify({'success': False, 'error': 'Servidor já está rodando.'}), 409

    try:
        logging.info(f"Iniciando servidor com comando: {MINECRAFT_START_COMMAND}")
        
        # Alteração chave: uso de 'shell=True' para melhor compatibilidade com o Windows.
        # Adicione 'creationflags=subprocess.DETACHED_PROCESS' para garantir que o processo não seja
        # encerrado com o agente, permitindo que ele rode em segundo plano.
        subprocess.Popen(
            MINECRAFT_START_COMMAND,
            cwd=MINECRAFT_SERVER_DIR,
            creationflags=subprocess.DETACHED_PROCESS,
            shell=True # Adicionando 'shell=True' para melhor compatibilidade com Windows
        )
        time.sleep(2) # Pequena pausa para o processo começar
        return jsonify({'success': True, 'message': 'Servidor iniciado com sucesso.'})
    except Exception as e:
        logging.error(f"Erro ao iniciar servidor: {e}")
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/stop_server', methods=['POST'])
def stop_server():
    """Encerra o servidor Minecraft."""
    process = find_minecraft_process()
    if not process:
        return jsonify({'success': False, 'error': 'Servidor não está rodando.'}), 404
        
    try:
        # Envio de comando 'stop' via RCON para um encerramento seguro
        if is_rcon_ready():
            with MCRcon(RCON_HOST, RCON_PASSWORD, RCON_PORT) as mcr:
                mcr.command('stop')
            return jsonify({'success': True, 'message': 'Comando de parada enviado via RCON.'})
        else:
            # Se RCON não estiver pronto, use o método original de terminação
            logging.warning("RCON não está pronto. Forçando o encerramento do processo.")
            process.terminate()
            process.wait(timeout=10)
            if process.is_running():
                process.kill()
                process.wait(timeout=5)
            return jsonify({'success': True, 'message': 'Servidor encerrado por terminação de processo.'})
            
    except Exception as e:
        logging.error(f"Erro ao encerrar servidor: {e}")
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/server_status', methods=['GET'])
def server_status():
    """Verifica o status do servidor, incluindo uso de RAM e disponibilidade via RCON."""
    process = find_minecraft_process()
    is_running = process is not None
    status = 'Parado'
    ram_usage = 'N/A'
    is_ready = False

    if is_running:
        try:
            ram_mb = process.memory_info().rss / (1024 * 1024)
            ram_usage = f"{ram_mb:.2f} MB"
            is_ready = is_rcon_ready()
            status = 'Rodando e pronto' if is_ready else 'Rodando e iniciando...'
        except (psutil.NoSuchProcess, psutil.AccessDenied):
            status = 'Erro de acesso'
            is_running = False

    logging.info(f"Status: {status}, RAM: {ram_usage}, Pronto: {is_ready}")
    return jsonify({
        'success': True,
        'status': status,
        'is_running': is_running,
        'ram_usage': ram_usage,
        'is_ready': is_ready
    }), 200

@app.route('/rcon_command', methods=['POST'])
def rcon_command():
    """Executa comandos RCON no servidor Minecraft."""
    data = request.get_json()
    if not data or 'command' not in data:
        return jsonify({"error": "Comando RCON ausente"}), 400

    command = data['command']
    try:
        with MCRcon(RCON_HOST, RCON_PASSWORD, RCON_PORT) as mcr:
            response = mcr.command(command)
        logging.info(f"Comando RCON executado: {command}")
        return jsonify({"success": True, "response": response}), 200
    except Exception as e:
        logging.error(f"Erro ao executar comando RCON: {e}")
        return jsonify({"success": False, "error": str(e)}), 500

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=AGENT_PORT)