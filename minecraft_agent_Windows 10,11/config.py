# config.py - Configurações para o agente de controle do servidor Minecraft no Windows

# Configurações do RCON do Minecraft
RCON_HOST = '100.107.34.48'
RCON_PORT = 25575
RCON_PASSWORD = '12312sdafa134' # Use a senha real do seu server.properties

# Diretório onde o servidor está localizado
# Use o caminho exato para a pasta que contém o arquivo 'fabric-server-launch.jar'.
MINECRAFT_SERVER_DIR = r'C:\Servidor1.21.6_FabricMinecraft'

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