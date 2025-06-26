#include <WiFi.h>
#include <HTTPClient.h>
#include <TFT_eSPI.h>
#include <SPI.h>          // REQUIRED for SPI communication (used by SD)
#include <FS.h>           // REQUIRED for generic filesystem access
#include <SD.h>           // REQUIRED for SD card specific functions
#include <NTPClient.h>    // Para obter a hora da internet
#include <WiFiUdp.h>      // Necessário para o NTPClient

// Biblioteca FTP Server (kept for file management, if you want to use it)
#include <ESP32FtpServer.h> 

// Biblioteca Web Server
#include <WebServer.h> 

// --- Configurações de Hardware e Pinos ---
// LEDs RGB (para feedback visual)
#define LED_R 4
#define LED_G 16
#define LED_B 17

// Pinos SPI para o Cartão SD no ESP32-2432S028
#define SD_SCK  18 // CLK (SCK)
#define SD_MISO 19 // DATO (MISO)
#define SD_MOSI 23 // CMD (MOSI)
#define SD_CS   5  // CD (Chip Select)

// --- Variáveis Globais de Credenciais e Controle ---
String ssidStr = "";
String passwordStr = "";
String servidorStr = ""; 

const char* ssid;
const char* password;
const char* servidor; 

WiFiUDP ntpUDP;
// -10800 é para GMT-3 (Brasília). A hora atual será ajustada para o fuso horário correto automaticamente.
NTPClient timeClient(ntpUDP, "pool.ntp.org", -10800, 60000); 

// Variáveis para controlar o que é exibido no TFT
enum DisplayMode {
  STATUS_MODE, // Mostra o status do servidor Minecraft
  TIME_MODE    // Mostra a hora atual
};
DisplayMode currentDisplayMode = STATUS_MODE; // Começa mostrando o status por padrão

TFT_eSPI tft = TFT_eSPI();
String jogadoresOnline = "0";
String nomes = "";

// Nome do arquivo de credenciais no SD
#define NOME_ARQUIVO_CREDENCIAIS "/creeper_credenciais.txt"

// --- Configurações do Servidor FTP ---
FtpServer ftpSrv; 
const char* FTP_USER = "creeper"; 
const char* FTP_PASS = "1234";    

// --- Configurações do Servidor Web ---
WebServer server(80); 

// --- Função para extrair o IP de uma URL ---
String extrairIpDaUrl(String url) {
  int inicioIp = url.indexOf("http://");
  if (inicioIp != -1) {
    inicioIp += 7; 
  } else {
    inicioIp = url.indexOf("https://");
    if (inicioIp != -1) {
      inicioIp += 8; 
    } else {
      inicioIp = 0; 
    }
  }
  
  int fimIp = url.indexOf("/", inicioIp); 
  if (fimIp == -1) { 
    fimIp = url.length(); 
  }
  
  return url.substring(inicioIp, fimIp); 
}

// --- Função: Gerenciamento do Cartão SD e Leitura/Criação de Credenciais ---
void gerenciarCredenciais() {
  Serial.println("Iniciando gerenciamento de credenciais e SD Card...");

  SPI.begin(SD_SCK, SD_MISO, SD_MOSI, SD_CS);

  if (!SD.begin(SD_CS)) {
    Serial.println("❌ Falha inicial ao montar o cartão SD.");
    tft.fillScreen(TFT_BLACK);
    tft.setTextSize(2);
    tft.setTextColor(TFT_RED);
    tft.setCursor(10, 10);
    tft.println("SD Corrompido ou Ausente!");
    tft.println("POR FAVOR:");
    tft.println("Remova o SD e formate-o");
    tft.println("manualmente em FAT32 no PC.");
    tft.println("Use 'SD Card Formatter'.");
    delay(10000); 
    Serial.println("Não foi possível montar o SD. Abortando a leitura de credenciais.");
    ssidStr = ""; passwordStr = ""; servidorStr = "";
    return;
  } else {
    Serial.println("✅ Cartão SD montado com sucesso.");
    Serial.printf("Tipo de Cartão SD: %s\n", (SD.cardType() == CARD_MMC ? "MMC" : SD.cardType() == CARD_SD ? "SDSC" : SD.cardType() == CARD_SDHC ? "SDHC" : "UNKNOWN"));
    Serial.printf("Tamanho do Cartão SD: %lluMB\n", SD.cardSize() / (1024 * 1024));
  }

  if (!SD.exists(NOME_ARQUIVO_CREDENCIAIS)) {
    Serial.println("📁 Arquivo de credenciais não existe. Criando...");
    File file = SD.open(NOME_ARQUIVO_CREDENCIAIS, FILE_WRITE);
    if (file) {
      file.println("Maria Cristina 4G"); 
      file.println("1247bfam");       
      file.println("http://192.168.100.170/minecraft_agent/verifica_players.php");
      file.close();
      Serial.println("✅ Arquivo de credenciais criado com sucesso.");
      Serial.println("Por favor, edite o arquivo 'creeper_credenciais.txt' no SD via FTP para suas credenciais reais.");
    } else {
      Serial.println("⚠️ Erro ao criar o arquivo de credenciais. Verifique o SD.");
      ssidStr = ""; passwordStr = ""; servidorStr = ""; 
      return;
    }
  }

  File file = SD.open(NOME_ARQUIVO_CREDENCIAIS);
  if (file) {
    ssidStr = file.readStringUntil('\n'); ssidStr.trim();
    passwordStr = file.readStringUntil('\n'); passwordStr.trim();
    servidorStr = file.readStringUntil('\n'); servidorStr.trim();
    file.close();

    ssid = ssidStr.c_str(); 
    password = passwordStr.c_str(); 
    servidor = servidorStr.c_str(); 

    Serial.println("🔐 Credenciais carregadas:");
    Serial.println("SSID: " + ssidStr);
    Serial.println("Senha: " + passwordStr);
    Serial.println("Servidor: " + servidorStr);
  } else {
    Serial.println("⚠️ Erro ao ler o arquivo de credenciais. O arquivo pode estar vazio ou corrompido.");
    ssidStr = ""; passwordStr = ""; servidorStr = ""; 
  }
}

// --- Funções de exibição no TFT e busca de dados ---
void exibeTelaMinecraft() {
  tft.fillScreen(TFT_BLACK);
  tft.setRotation(0);
  tft.setTextSize(4);
  tft.setTextColor(TFT_GREEN, TFT_BLACK);
  tft.setCursor(20, 20);
  tft.println("MINECRAFT");

  int x = 50, y = 80, tam = 120;
  tft.fillRect(x, y, tam, tam, TFT_GREEN);
  tft.fillRect(x + 25, y + 20, 25, 25, TFT_BLACK);
  tft.fillRect(x + 70, y + 20, 25, 25, TFT_BLACK);
  tft.fillRect(x + 40, y + 50, 40, 40, TFT_BLACK);
  tft.fillRect(x + 15, y + 92, 15, 22, TFT_BLACK);
  tft.fillRect(x + 90, y + 90, 15, 25, TFT_BLACK);

  for (int i = 0; i < 5; i++) {
    delay(500);
    tft.setTextSize(2);
    tft.setCursor(50, 230);
    if (i % 2 == 0) {
      tft.setTextColor(TFT_WHITE, TFT_BLACK);
      tft.println("Start Server...");
    } else {
      tft.fillRect(50, 230, 200, 20, TFT_BLACK); 
    }
  }

  delay(800);
  tft.fillScreen(TFT_BLACK);
}

void buscarDadosServidor() {
  if (WiFi.status() == WL_CONNECTED) {
    HTTPClient http;
    http.begin(servidor); 
    int resposta = http.GET(); 

    if (resposta == HTTP_CODE_OK) { 
      String payload = http.getString(); 
      int start = payload.indexOf("\"jogadores_online\":") + 19;
      int end = payload.indexOf(",", start);
      jogadoresOnline = payload.substring(start, end);

      start = payload.indexOf("[", end) + 1;
      end = payload.indexOf("]", start);
      nomes = payload.substring(start, end);
      nomes.replace("\"", ""); 
      nomes.replace(",", ", "); 
    } else {
      Serial.print("⚠️ Erro HTTP na busca de dados: ");
      Serial.println(resposta);
      jogadoresOnline = "Erro";
      nomes = "Falha ao buscar";
    }
    http.end(); 
  } else {
    Serial.println("⚠️ Wi-Fi não conectado. Não foi possível buscar dados do servidor.");
    jogadoresOnline = "Offline";
    nomes = "Wi-Fi Desconectado";
  }
}

void exibirStatus() {
  tft.fillScreen(TFT_BLACK);
  tft.setTextSize(2);

  tft.setTextColor(TFT_ORANGE);   tft.setCursor(10, 20);   tft.println("Servidor:");
  tft.setTextColor(TFT_WHITE);    tft.setCursor(10, 40);   tft.println(extrairIpDaUrl(servidorStr)); 
  
  digitalWrite(LED_R, HIGH); digitalWrite(LED_G, HIGH); digitalWrite(LED_B, HIGH);
  if (WiFi.status() == WL_CONNECTED) {
    digitalWrite(LED_G, LOW); 
  } else {
    digitalWrite(LED_R, LOW); 
  }

  tft.setTextColor(TFT_PURPLE);   tft.setCursor(10, 70);   tft.println("Rede Wi-Fi:");
  tft.setTextColor(TFT_WHITE);    tft.setCursor(10, 90);   tft.println(ssid);

  tft.setTextColor(TFT_YELLOW);   tft.setCursor(10, 120); tft.println("Senha:");
  tft.setTextColor(TFT_WHITE);    tft.setCursor(10, 140); tft.println(password);

  tft.setTextColor(TFT_BLUE);     tft.setCursor(10, 170); tft.print("Jogadores: ");
  tft.setTextColor(TFT_WHITE);    tft.println(jogadoresOnline);

  tft.setTextColor(TFT_WHITE, TFT_MAGENTA); 
  tft.setCursor(10, 200); tft.println("Nomes:");
  tft.setTextColor(TFT_WHITE);    tft.setCursor(10, 220); tft.println(nomes);
}

// --- Função para mostrar a hora no TFT ---
void showTimeOnTFT() {
  tft.fillScreen(TFT_BLACK);
  tft.setTextSize(3); 
  tft.setTextColor(TFT_CYAN, TFT_BLACK);
  tft.setCursor(10, 50);
  tft.println("Hora Atual:");

  tft.setTextSize(5); 
  tft.setTextColor(TFT_WHITE, TFT_BLACK);
  tft.setCursor(10, 100);
  tft.println(timeClient.getFormattedTime()); 
  
  tft.setTextSize(2);
  tft.setCursor(10, 200);
  tft.setTextColor(TFT_GREEN);
  tft.println("Controle via Web:");
  tft.println("IP: " + WiFi.localIP().toString());
}

// --- Funções para lidar com as requisições dos botões da web ---
void handleShowTime() {
  currentDisplayMode = TIME_MODE; 
  server.send(200, "text/plain", "Mostrando hora no TFT."); // Responde ao navegador, para evitar que o navegador fique esperando
}

void handleShowStatus() {
  currentDisplayMode = STATUS_MODE; 
  server.send(200, "text/plain", "Mostrando status do servidor no TFT."); // Responde ao navegador
}

// --- Função para mostrar o status detalhado na página web ---
void handleStatusWeb() {
  String html = "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Status do Servidor Minecraft</title>";
  html += "<style>body{font-family: sans-serif; background-color: #f0f0f0; margin: 20px;}";
  html += "h1{color: #333;} p{color: #555;} .back-button button { padding: 10px 15px; background-color: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; }";
  html += ".back-button button:hover { background-color: #0056b3; }</style>";
  html += "</head><body><h1>Status do Servidor Minecraft</h1>";
  html += "<p><strong>Servidor:</strong> " + servidorStr + "</p>";
  html += "<p><strong>Jogadores Online:</strong> " + jogadoresOnline + "</p>";
  html += "<p><strong>Nomes:</strong> " + nomes + "</p>";
  html += "<div class='back-button'><button onclick=\"location.href='/'\">Voltar para Listagem SD</button></div>";
  html += "</body></html>";
  server.send(200, "text/html", html);
}

// --- Função para servir arquivos ou listar diretórios na página web ---
void handleSdWeb() {
  String path = server.uri(); 
  Serial.print("Requisição Web para: ");
  Serial.println(path);

  if (!path.startsWith("/")) {
    path = "/" + path;
  }

  File root; 
  if (path.endsWith("/")) { 
    root = SD.open(path);
  } else { 
    root = SD.open(path, FILE_READ);
  }

  if (!root) {
    server.send(404, "text/plain", "404 Not Found (Arquivo/Pasta nao encontrada no SD)");
    Serial.println("404 Not Found: " + path);
    return;
  }

  if (root.isDirectory()) {
    String html = "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Diretorio: " + path + "</title>";
    html += "<style>body{font-family: sans-serif; background-color: #f0f0f0; margin: 20px;}";
    html += "h1{color: #333;} ul{list-style-type: none; padding: 0;}";
    html += "li{margin-bottom: 5px; background-color: #fff; padding: 8px; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);}";
    html += "a{text-decoration: none; color: #007bff;} a:hover{text-decoration: underline;}";
    html += ".control-buttons button { padding: 10px 15px; margin: 5px; background-color: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer; }";
    html += ".control-buttons button:hover { background-color: #218838; }</style>"; 
    html += "</head><body><h1>Diretorio: " + path + "</h1>"; 

    // === Adiciona os botões de controle e informações aqui ===
    html += "<div class='control-buttons'>";
    html += "<p><strong>IP do ESP32:</strong> " + WiFi.localIP().toString() + "</p>";
    html += "<p><strong>Status do Servidor Minecraft:</strong> <a href=\"/status\">Ver Status Detalhado</a></p>";
    html += "<button onclick=\"location.href='/showtime'\">Mostrar Hora no TFT</button>";
    html += "<button onclick=\"location.href='/showstatus'\">Mostrar Status Servidor no TFT</button>";
    html += "<p>Acesse com FTP (usuário: <strong>" + String(FTP_USER) + "</strong>, senha: <strong>" + String(FTP_PASS) + "</strong>) para gerenciar o SD.</p>";
    html += "</div><hr>"; 
    // =========================================================

    if (path != "/") {
      String parentPath = path.substring(0, path.lastIndexOf('/'));
      if (parentPath.length() == 0) parentPath = "/";
      html += "<li><a href=\"" + parentPath + "\">[VOLTAR] ..</a></li>";
    }

    File file = root.openNextFile(); 
    while (file) {
      html += "<li>";
      if (file.isDirectory()) {
        html += "[DIR] <a href=\"";
        html += path + file.name() + "/"; 
        html += "\">" + String(file.name()) + "</a>";
      } else {
        html += "[FILE] <a href=\"";
        html += path + file.name(); 
        html += "\">" + String(file.name()) + "</a> (" + String(file.size()) + " bytes)";
      }
      html += "</li>";
      file = root.openNextFile();
    }
    root.close();
    html += "</ul></body></html>";
    server.send(200, "text/html", html);
  } else { 
    Serial.print("Servindo arquivo: ");
    Serial.println(path);
    server.streamFile(root, "application/octet-stream"); 
    root.close();
  }
}

// --- Função Setup: Executada uma vez ao ligar/resetar o ESP32 ---
void setup() {
  Serial.begin(115200); 

  tft.init(); 
  tft.invertDisplay(true); 

  pinMode(LED_R, OUTPUT);
  pinMode(LED_G, OUTPUT);
  pinMode(LED_B, OUTPUT);
  digitalWrite(LED_R, HIGH); 
  digitalWrite(LED_G, HIGH);
  digitalWrite(LED_B, HIGH);

  gerenciarCredenciais();

  if (ssidStr.isEmpty() || passwordStr.isEmpty() || servidorStr.isEmpty()) {
    Serial.println("Erro crítico: Não foi possível carregar as credenciais do SD. Interrompendo a execução.");
    tft.fillScreen(TFT_BLACK);
    tft.setTextSize(2);
    tft.setTextColor(TFT_RED);
    tft.setCursor(10, 10);
    tft.println("ERRO FATAL!");
    tft.println("Verifique o SD / Arquivo.");
    while(true) {
        digitalWrite(LED_R, LOW); delay(200); 
        digitalWrite(LED_R, HIGH); delay(200);
    }
  }

  exibeTelaMinecraft();

  Serial.print("Conectando ao WiFi: ");
  Serial.println(ssid);
  WiFi.begin(ssid, password);
  int tentativas = 0;
  while (WiFi.status() != WL_CONNECTED && tentativas < 20) {
    delay(500);
    Serial.print(".");
    tentativas++;
    digitalWrite(LED_B, LOW); delay(100); 
    digitalWrite(LED_B, HIGH); delay(100);
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\n✅ WiFi Conectado!");
    Serial.print("Endereço IP: ");
    Serial.println(WiFi.localIP());

    ftpSrv.begin(FTP_USER, FTP_PASS); 
    Serial.println("=========================================");
    Serial.println("         Servidor FTP Iniciado!");
    Serial.print("IP do Aparelho: ");
    Serial.println(WiFi.localIP());
    Serial.print("Usuário FTP: ");
    Serial.println(FTP_USER);
    Serial.print("Senha FTP: ");
    Serial.println(FTP_PASS);
    Serial.println("Acesse usando um cliente FTP (FileZilla) ");
    Serial.println("com o IP acima e as credenciais para gerenciar o SD.");
    Serial.println("Lembre-se de usar 'FTP Simples (inseguro)' no cliente!");
    Serial.println("=========================================");

    // --- REGISTRO DAS ROTAS DO SERVIDOR WEB ---
    server.on("/", HTTP_GET, handleSdWeb); 
    server.on("/showtime", HTTP_GET, handleShowTime); 
    server.on("/showstatus", HTTP_GET, handleShowStatus); 
    server.on("/status", HTTP_GET, handleStatusWeb); 
    
    server.begin(); 
    Serial.println("Servidor Web HTTP iniciado na porta 80.");
    Serial.print("Acesse no navegador: http://");
    Serial.println(WiFi.localIP());
    Serial.println("=========================================");

    timeClient.begin(); // Inicializa o cliente NTP UMA ÚNICA VEZ
    timeClient.update(); // Pega a primeira atualização de tempo
    Serial.println("Tempo NTP inicializado.");

    digitalWrite(LED_G, LOW); 
  } else {
    Serial.println("\n❌ Falha ao conectar ao WiFi!");
    digitalWrite(LED_R, LOW); 
    tft.fillScreen(TFT_BLACK);
    tft.setTextSize(2);
    tft.setTextColor(TFT_RED);
    tft.setCursor(10, 10);
    tft.println("Falha ao Conectar WiFi!");
    tft.println("Verifique SSID/Senha no SD.");
    delay(5000);
  }

  // Define o modo inicial do TFT após a conexão (se bem-sucedida)
  currentDisplayMode = STATUS_MODE; // Garante que comece no status
  buscarDadosServidor(); // Pega os dados do servidor
  exibirStatus(); // Exibe no TFT
}

// --- Função Loop: Executada repetidamente após o setup ---
void loop() {
  ftpSrv.handleFTP();      
  server.handleClient();   

  // Atualiza o tempo NTP regularmente
  timeClient.update();

  static unsigned long lastUpdateTime = 0;
  const unsigned long updateInterval = 60000; // 1 minuto para atualizar dados do servidor

  // Atualiza os dados do servidor Minecraft em segundo plano
  if (millis() - lastUpdateTime >= updateInterval) {
    lastUpdateTime = millis();
    Serial.println("Atualizando dados do servidor...");
    buscarDadosServidor(); 
    // Se o display estiver no modo STATUS_MODE, atualiza a exibição no TFT
    if (currentDisplayMode == STATUS_MODE) {
      exibirStatus(); 
    }
  }

  // Se o display estiver no modo TIME_MODE, atualiza a hora no TFT a cada segundo
  if (currentDisplayMode == TIME_MODE) {
    static unsigned long lastTimeDisplayUpdate = 0;
    const unsigned long timeDisplayInterval = 1000; // Atualiza a hora a cada segundo no TFT
    if (millis() - lastTimeDisplayUpdate >= timeDisplayInterval) {
      lastTimeDisplayUpdate = millis();
      showTimeOnTFT();
    }
  }
}