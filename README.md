# PanelMinecraft Version Linux e Windows e cs2 Windows painel
PANEL MINECRAFT

pip install Flask
pip install psutil
pip install mcrcon



python app.py

![image](https://github.com/user-attachments/assets/89f26776-c838-4bf1-be60-a24bae94b4fa)

test

Seu agente Flask define regras estritas sobre quais métodos podem ser usados em cada rota:

Endpoint	Função	Método Necessário

    /rcon_command	Enviar comando RCON	POST
    /start_server	Iniciar servidor	POST
    /stop_server	Parar servidor	POST
    /server_status	Obter status	GET

    curl -X POST      -H "Content-Type: application/json"      -d '{"command": "list"}'      http://192.168.100.170:5000/rcon_command

    curl -X GET http://192.168.100.170:5000/server_status

web
C:\Apache24\htdocs\minecraft_agent

java server

C:\Servidor1.21.6_FabricMinecraft



https://github.com/user-attachments/assets/b97c8798-70a3-4e39-a5d2-1c58f077c853

<img width="622" height="473" alt="image" src="https://github.com/user-attachments/assets/1a4867a9-9d38-447c-810b-2b0e25ce56ed" />
<img width="625" height="473" alt="image" src="https://github.com/user-attachments/assets/717338fd-5937-4534-b89f-34ebdfb198cc" />

<img width="972" height="555" alt="image" src="https://github.com/user-attachments/assets/ea5791fa-5ca8-46aa-b1ab-d80a8f327973" />

C:\Users\Astral\AppData\Local\Programs\Python\Python313\python.exe

C:\Apache24\htdocs\minecraft_agent\app.py




Próximos Passos (Continuando a Configuração do Agente Python)
Agora que o usuário existe, podemos finalizar as configurações.

Mudar o proprietário dos arquivos do agente Python:
Com o usuário minecraft_agent_user agora devidamente criado, este comando deve funcionar sem o erro de "usuário inválido". Certifique-se de que está no diretório correto do seu projeto /var/www/html/minecraft_agent/ ou use o caminho completo.

     sudo chown -R minecraft_agent_user:minecraft_agent_user /var/www/html/minecraft_agent/

Configurar as permissões sudo para o novo usuário:
Você precisa permitir que minecraft_agent_user execute os comandos de systemctl para gerenciar o Minecraft.

   sudo visudo

   minecraft_agent_user ALL=(ALL) NOPASSWD: /usr/bin/systemctl start minecraft.service, /usr/bin/systemctl stop minecraft.service, /usr/bin/systemctl restart minecraft.service, /usr/bin/systemctl status minecraft.service

   sudo nano /etc/systemd/system/minecraft_agent.service
    
    =======================================minecraft_agent.service===============================================================================
    [Unit]
    Description=Minecraft Agent Python API
    After=network.target

    [Service]
    User=minecraft_agent_user
    WorkingDirectory=/var/www/html/minecraft_agent/
    ExecStart=/var/www/html/minecraft_agent/venv/bin/python /var/www/html/minecraft_agent/app.py
    Restart=on-failure
    StandardOutput=syslog
    StandardError=syslog
    SyslogIdentifier=minecraft_agent

    [Install]
    WantedBy=multi-user.targe
   ==========================================minecraft_agent.service==========================================================================

   sudo systemctl daemon-reload

   sudo systemctl start minecraft_agent.service

   sudo systemctl status minecraft_agent.service

   sudo chown -R minecraft_agent_user:nogroup /var/www/html/minecraft_agent/

   sudo addgroup minecraft_agent_user

   sudo usermod -a -G minecraft_agent_user minecraft_agent_user

   sudo chown -R minecraft_agent_user:minecraft_agent_user /var/www/html/minecraft_agent/

   sudo systemctl stop minecraft_agent.service
  
   sudo systemctl disable minecraft_agent.service

  # Entre no shell do usuário minecraft_agent_user:

  sudo -u minecraft_agent_user /bin/bash

  # Ative o ambiente virtual:

  apt search python3

  sudo apt install python3-requests

  python3 -m venv venv
  
  source venv/bin/activate

  pip install Flask python-minecraft-rcon

  python app.py

  sudo systemctl enable minecraft_agent.service
  
  sudo systemctl start minecraft_agent.service
  
  sudo systemctl status minecraft_agent.service


  sudo nano /root/mi.sh

=======================================================================================
#!/bin/bash
cd /root/minecraft
screen -A -m -d -S mi java -Xmx1024M -Xms1024M -jar server.jar nogui
while true; do
    sleep 86400 # Dorme por 1 dia antes de verificar novamente
done

=======================================================================================

sudo chmod +x /root/mi.sh

sudo nano /etc/systemd/system/minecraft.service

=======================================================================================
[Unit]
Description=Minecraft Server

[Service]
User=root
WorkingDirectory=/root/minecraft
ExecStart=/root/mi.sh
Restart=on-failure

[Install]
WantedBy=multi-user.target
========================================================================================

sudo systemctl enable minecraft.service
sudo systemctl start minecraft.service


sudo systemctl daemon-reload
sudo systemctl restart minecraft.service


sudo systemctl status minecraft.service



#fim tutorial de backup de Mapa#!/bin/bash
# Script para backup


nano mi_script_de_backup.sh


==========================================================================================

#!/bin/bash
# Script para backup

SOURCE="/root/minecraft/world"  # Ajustei o nome do diretório para "world"
DEST="/var/www/html/fastdl"
BACKUP_FILE="$DEST/backup_$(date +%F).tar.gz"

tar -czf $BACKUP_FILE $SOURCE

echo "Backup realizado com sucesso: $BACKUP_FILE"


===========================================================================================
shmod +x mi_script_de_backup.sh

crontab -e

0 2 * * * /root/mi_script_de_backup.sh

Ótimo! Agendando com o crontab para rodar diariamente às 2 da manhã, seu backup estará sempre atualizado. 😎


-----------------------------------------------------------------------------------------------------------------



# User_Setup.h

Arquivo de configuração para a biblioteca TFT_eSPI. Precisa ser colocado no diretório onde a biblioteca está instalada.

# lv_conf.h

Arquivo de configuração da biblioteca LVGL. Precisa ser colocado no diretório de bibliotecas do Arduino.

Fonte: https://randomnerdtutorials.com/lvgl-cheap-yellow-display-esp32-2432s028r/



  


  



   

