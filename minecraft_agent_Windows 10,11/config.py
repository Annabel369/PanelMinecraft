# config.py - Configurações para o agente de controle do servidor Minecraft no Windows

# Configurações do RCON do Minecraft
RCON_HOST = '192.168.100.170'
RCON_PORT = 25575
RCON_PASSWORD = '9GNdf343422SOO' # Use a senha real do seu server.properties

# Diretório onde o servidor está localizado
# Use o caminho exato para a pasta que contém o arquivo 'fabric-server-launch.jar'.
#MINECRAFT_SERVER_DIR = r'C:\Servidor1.21.6_FabricMinecraft'
MINECRAFT_SERVER_DIR = r'C:\Gabryelle'

# Comando para iniciar o servidor Minecraft
# O comando usa 'java.exe' e o caminho do .jar
# Adicionamos 'nogui' para que o servidor não abra a interface gráfica.

# Fabric
"""

MINECRAFT_START_COMMAND = [
    'java',  
    '-Xmx8G',  
    '-Xms4G',  
    '-jar',  
    'fabric-server-launch.jar',  
    'nogui'
]"""

# Forge
MINECRAFT_START_COMMAND = [
    'java',
    '@user_jvm_args.txt',
    '@libraries/net/minecraftforge/forge/1.20.1-47.4.0/win_args.txt'
]

# --- Configuração de Segurança (IP Whitelist) ---
ALLOWED_IPS = [
    '127.0.0.1',       # Permite acesso da própria máquina onde o agente está rodando
    '192.168.100.1',   # Exemplo: Seu roteador ou outra máquina de controle
    # Adicione aqui todos os IPs que devem ter permissão de controle.
    '192.168.100.218'
]

# Porta que o agente Python vai escutar
AGENT_PORT = 5000
