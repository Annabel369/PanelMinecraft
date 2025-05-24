# app.py

from flask import Flask, request, jsonify
from mcrcon import MCRcon
import subprocess
import logging
import os
from config import (
    RCON_HOST, RCON_PORT, RCON_PASSWORD,
    MINECRAFT_START_COMMAND, MINECRAFT_STOP_COMMAND,
    MINECRAFT_STATUS_COMMAND, AGENT_PORT
)

app = Flask(__name__)

# Configuração de logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')

@app.route('/rcon_command', methods=['POST'])
def rcon_command():
    """
    Executa um comando RCON no servidor Minecraft.
    Espera JSON: {"command": "say Hello World"}
    """
    data = request.get_json()
    if not data or 'command' not in data:
        return jsonify({"error": "Comando RCON ausente"}), 400

    command = data['command']
    response = ""
    try:
        with MCRcon(RCON_HOST, RCON_PASSWORD, RCON_PORT) as mcr:
            response = mcr.command(command)
        logging.info(f"RCON command '{command}' executed. Response: {response}")
        return jsonify({"success": True, "response": response}), 200
    except Exception as e:
        logging.error(f"Erro ao executar RCON command '{command}': {e}")
        return jsonify({"success": False, "error": str(e)}), 500

@app.route('/start_server', methods=['POST'])
def start_server():
    """
    Inicia o servidor Minecraft usando o comando configurado.
    """
    try:
        logging.info(f"Tentando iniciar servidor com comando: {' '.join(MINECRAFT_START_COMMAND)}")
        # Execute o comando de inicialização
        result = subprocess.run(MINECRAFT_START_COMMAND, capture_output=True, text=True, check=True)
        logging.info(f"Comando de inicialização executado. stdout: {result.stdout}, stderr: {result.stderr}")
        return jsonify({"success": True, "message": "Comando de inicialização enviado.", "stdout": result.stdout, "stderr": result.stderr}), 200
    except subprocess.CalledProcessError as e:
        logging.error(f"Erro ao iniciar servidor (CalledProcessError): {e.stderr}")
        return jsonify({"success": False, "error": f"Falha ao iniciar: {e.stderr}"}), 500
    except Exception as e:
        logging.error(f"Erro inesperado ao iniciar servidor: {e}")
        return jsonify({"success": False, "error": str(e)}), 500

@app.route('/stop_server', methods=['POST'])
def stop_server():
    """
    Para o servidor Minecraft usando o comando configurado.
    """
    try:
        logging.info(f"Tentando parar servidor com comando: {' '.join(MINECRAFT_STOP_COMMAND)}")
        result = subprocess.run(MINECRAFT_STOP_COMMAND, capture_output=True, text=True, check=True)
        logging.info(f"Comando de parada executado. stdout: {result.stdout}, stderr: {result.stderr}")
        return jsonify({"success": True, "message": "Comando de parada enviado.", "stdout": result.stdout, "stderr": result.stderr}), 200
    except subprocess.CalledProcessError as e:
        logging.error(f"Erro ao parar servidor (CalledProcessError): {e.stderr}")
        return jsonify({"success": False, "error": f"Falha ao parar: {e.stderr}"}), 500
    except Exception as e:
        logging.error(f"Erro inesperado ao parar servidor: {e}")
        return jsonify({"success": False, "error": str(e)}), 500

@app.route('/server_status', methods=['GET'])
def server_status():
    """
    Verifica o status do servidor Minecraft usando o comando configurado.
    """
    try:
        logging.info(f"Verificando status com comando: {' '.join(MINECRAFT_STATUS_COMMAND)}")
        result = subprocess.run(MINECRAFT_STATUS_COMMAND, capture_output=True, text=True, check=True)
        status_output = result.stdout
        
        is_running = "active (running)" in status_output.lower() # Exemplo para systemctl
        
        logging.info(f"Status do servidor verificado. Saída: {status_output}")
        return jsonify({"success": True, "status": status_output, "is_running": is_running}), 200
    except subprocess.CalledProcessError as e:
        logging.warning(f"Erro ao obter status do servidor: {e.stderr}. O servidor pode estar parado.")
        return jsonify({"success": True, "status": e.stderr, "is_running": False}), 200 # Considerar como parado se o comando falhar
    except Exception as e:
        logging.error(f"Erro inesperado ao obter status do servidor: {e}")
        return jsonify({"success": False, "error": str(e)}), 500

if __name__ == '__main__':
    # Em produção, você usaria um WSGI server como Gunicorn ou uWSGI
    # Para desenvolvimento, o Flask server é suficiente:
    app.run(host='0.0.0.0', port=AGENT_PORT)