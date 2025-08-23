# config.py - Configurações para o agente de controle do servidor Minecraft no Windows

# Configurações do RCON do Minecraft
RCON_HOST = 'localhost'
RCON_PORT = 25575
RCON_PASSWORD = 'zjhq72391zs' # Use a senha real do seu server.properties

# Diretório onde o servidor está localizado
# Use o caminho exato para a pasta que contém o arquivo 'fabric-server-launch.jar'.
MINECRAFT_SERVER_DIR = r'C:\Users\Astral\Desktop\Servidor 1.21.6 Fabric Minecraft'

# Comando para iniciar o servidor Minecraft
# O comando usa 'java.exe' e o caminho do .jar
# Adicionamos 'nogui' para que o servidor não abra a interface gráfica.
MINECRAFT_START_COMMAND = [
    'java',  
    '-Xmx4G',  
    '-Xms2G',  
    '-jar',  
    'fabric-server-launch.jar',  
    'nogui'
]

# Porta que o agente Python vai escutar
AGENT_PORT = 5000