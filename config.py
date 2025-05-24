# config.py

# Configurações do RCON do Minecraft
RCON_HOST = 'localhost'  # Ou o IP do seu servidor Minecraft
RCON_PORT = 25575        # Porta RCON padrão
RCON_PASSWORD = 'sua_senha_rcon_aqui' # A senha que você configurou no server.properties

# Comando para iniciar o servidor Minecraft
# Ajuste o caminho para o seu server.jar e as opções de RAM
MINECRAFT_START_COMMAND = [
    'sudo', '/usr/bin/systemctl', 'start', 'minecraft.service'
    # Alternativa se você não usa systemd e quer rodar via screen:
    # 'sudo', '-u', 'minecraft', 'screen', '-dmS', 'minecraft', '/usr/bin/java', '-Xmx2G', '-Xms1G', '-jar', '/opt/minecraft/server.jar', 'nogui'
]

# Comando para parar o servidor Minecraft (via systemctl)
MINECRAFT_STOP_COMMAND = [
    'sudo', '/usr/bin/systemctl', 'stop', 'minecraft.service'
]

# Comandos para verificar o status
MINECRAFT_STATUS_COMMAND = [
    'sudo', '/usr/bin/systemctl', 'status', 'minecraft.service'
]

# Porta que o agente Python vai escutar
AGENT_PORT = 5000