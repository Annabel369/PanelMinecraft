# app.py - Agente Flask para gerenciar servidor Minecraft no Windows

from flask import Flask, jsonify, request
import subprocess
import logging
import os
import psutil
import time
from mcrcon import MCRcon
# Importa as configurações do arquivo config.py
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
    # Garante que o diretório de destino está normalizado para comparação
    target_dir = os.path.normcase(os.path.abspath(MINECRAFT_SERVER_DIR))
    logging.debug(f"Procurando por processo no diretório: {target_dir}")
    
    for proc in psutil.process_iter(['pid', 'name', 'cmdline', 'cwd']):
        try:
            # 1. Deve ser um processo Java
            if proc.info['cmdline'] and 'java' in proc.info['name'].lower():
                # 2. Verifica se o Diretório de Trabalho (Current Working Directory - CWD)
                # do processo corresponde ao diretório do servidor.
                proc_cwd = os.path.normcase(proc.info['cwd']) if proc.info['cwd'] else None
                
                # Esta é uma verificação mais robusta para servidores Forge ou Fabric
                if proc_cwd and proc_cwd == target_dir:
                    logging.info(f"Processo Minecraft encontrado: PID {proc.pid} no CWD {proc_cwd}")
                    return proc
                
        except (psutil.NoSuchProcess, psutil.AccessDenied, psutil.ZombieProcess):
            continue
        except Exception as e:
            # Captura exceções ao acessar proc.info (pode acontecer em ambientes restritos)
            logging.debug(f"Erro ao inspecionar processo {proc.pid}: {e}")
            continue
            
    return None

def is_rcon_ready():
    """Verifica se o servidor está aceitando comandos via RCON."""
    try:
        # Tenta enviar um comando simples para verificar a conectividade
        with MCRcon(RCON_HOST, RCON_PASSWORD, RCON_PORT) as mcr:
            mcr.command('say Agente verificou o status via RCON.')
        return True
    except Exception as e:
        # A falha na conexão RCON é esperada enquanto o servidor está iniciando
        logging.warning(f"Falha na conexão RCON (Provavelmente servidor não pronto ou RCON desativado): {e}")
        return False

# --- Endpoints ---

@app.route('/start_server', methods=['POST'])
def start_server():
    """Inicia o servidor Minecraft."""
    if find_minecraft_process():
        return jsonify({'success': False, 'error': 'Servidor já está rodando.'}), 409

    try:
        # Se o comando for uma lista (como o Forge), une-o em uma string para usar com shell=True
        if isinstance(MINECRAFT_START_COMMAND, list):
            command_to_execute = " ".join(MINECRAFT_START_COMMAND)
        else:
            command_to_execute = MINECRAFT_START_COMMAND
            
        logging.info(f"Iniciando servidor com comando: {command_to_execute}")
        
        # Inicia o processo do servidor.
        # creationflags=subprocess.DETACHED_PROCESS + shell=True são cruciais para rodar em Windows
        # sem que o processo do servidor seja encerrado junto com o agente Flask.
        subprocess.Popen(
            command_to_execute,
            cwd=MINECRAFT_SERVER_DIR,
            creationflags=subprocess.DETACHED_PROCESS,
            shell=True # Necessário para o comando string ser executado no Windows
        )
        time.sleep(3) # Pequena pausa aumentada para 3s para o processo começar a aparecer
        return jsonify({'success': True, 'message': 'Servidor iniciado com sucesso. Verifique o status em alguns instantes.'})
    except Exception as e:
        logging.error(f"Erro ao iniciar servidor: {e}")
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/stop_server', methods=['POST'])
def stop_server():
    """Encerra o servidor Minecraft de forma segura (via RCON) ou forçada."""
    process = find_minecraft_process()
    if not process:
        return jsonify({'success': False, 'error': 'Servidor não está rodando.'}), 404
        
    try:
        # Envio de comando 'stop' via RCON para um encerramento seguro e salvamento do mundo
        if is_rcon_ready():
            with MCRcon(RCON_HOST, RCON_PASSWORD, RCON_PORT) as mcr:
                mcr.command('stop')
            return jsonify({'success': True, 'message': 'Comando de parada enviado via RCON. Aguardando desligamento seguro.'})
        else:
            # Se RCON não estiver pronto, usa terminação de processo
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
            
            # Checagem de RCON:
            # Se o processo estiver rodando, verifica se o RCON está acessível.
            is_ready = is_rcon_ready()
            
            status = 'Rodando e pronto (RCON OK)' if is_ready else 'Rodando e iniciando (RCON Falhou)'
        except (psutil.NoSuchProcess, psutil.AccessDenied):
            status = 'Rodando (Erro de acesso ao processo psutil)'
            is_running = True # Ainda está rodando, mas não podemos acessar os detalhes

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
        return jsonify({"success": False, "error": f"Erro RCON: {str(e)}. O servidor está pronto? Verifique as configurações RCON."}), 500

if __name__ == '__main__':
    # O agente escutará em todas as interfaces (0.0.0.0) na porta definida em config.py
    app.run(host='0.0.0.0', port=AGENT_PORT)
