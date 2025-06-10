#include <WiFi.h>
#include <HTTPClient.h>
#include <TFT_eSPI.h>
#include <SPI.h>

#define LED_R 4
#define LED_G 16
#define LED_B 17

const char* ssid = "Maria Cristina 4G";
const char* password = "1247bfam";
const char* servidor = "http://192.168.100.170/minecraft_agent/verifica_players.php";

TFT_eSPI tft = TFT_eSPI();

String jogadoresOnline = "0";
String nomes = "";

void exibeTelaMinecraft() {
    tft.fillScreen(TFT_BLACK);
    tft.setRotation(0); // Modo vertical

    // Logo "MINECRAFT" grande no topo
    tft.setTextSize(4);
    tft.setTextColor(TFT_GREEN, TFT_BLACK);
    tft.setCursor(20, 20);
    tft.println("MINECRAFT");

    // Desenha Creeper no meio da tela
    // Desenha Creeper centralizado na tela
    int xCreeper = 50, yCreeper = 80, tamanho = 120; 
    tft.fillRect(xCreeper, yCreeper, tamanho, tamanho, TFT_GREEN);  // Cabeça Creeper
    tft.fillRect(xCreeper + 25, yCreeper + 20, 25, 25, TFT_BLACK);   // Olho esquerdo
    tft.fillRect(xCreeper + 70, yCreeper + 20, 25, 25, TFT_BLACK);   // Olho direito
    tft.fillRect(xCreeper + 40, yCreeper + 50, 40, 40, TFT_BLACK);   // Boca
    tft.fillRect(xCreeper + 15, yCreeper + 92, 15, 22, TFT_BLACK);   // Parte inferior esquerda
    tft.fillRect(xCreeper + 90, yCreeper + 90, 15, 25, TFT_BLACK); 


    // Texto de carregamento piscando
    for (int i = 0; i < 5; i++) {
        delay(500);
        tft.setTextSize(2);
        tft.setCursor(50, 230);
        if (i % 2 == 0) {
            tft.setTextColor(TFT_WHITE, TFT_BLACK);
            tft.println("Start Server...");
        } else {
            tft.fillRect(50, 230, 200, 20, TFT_BLACK); // Limpa texto
        }
    }

    delay(1000);
    tft.fillScreen(TFT_BLACK); // Limpa tela após animação
}

void buscarDadosServidor() {
    if (WiFi.status() == WL_CONNECTED) {
        HTTPClient http;
        http.begin(servidor);
        int resposta = http.GET();

        if (resposta == 200) {
            String payload = http.getString();

            // Extrai "jogadores_online"
            int start = payload.indexOf("\"jogadores_online\":") + 19;
            int end = payload.indexOf(",", start);
            jogadoresOnline = payload.substring(start, end);

            // Extrai nomes dos jogadores
            start = payload.indexOf("[", end) + 1;
            end = payload.indexOf("]", start);
            nomes = payload.substring(start, end);
            nomes.replace("\"", "");
        }
        http.end();
    }
}

void exibirStatusVertical() {
    tft.fillScreen(TFT_BLACK);
    tft.setTextSize(2);
    tft.setTextColor(TFT_ORANGE, TFT_BLACK);

    tft.setCursor(10, 20);
    tft.println("Servidor:");
    tft.setCursor(10, 40);
    tft.setTextColor(TFT_WHITE, TFT_BLACK);
    //tft.println(WiFi.localIP());
    tft.println("192.168.100.170");

    digitalWrite(LED_R, LOW);
  digitalWrite(LED_G, LOW);
  digitalWrite(LED_B, LOW);

    tft.setCursor(10, 70);
    tft.setTextColor(TFT_PURPLE, TFT_BLACK);
    tft.println("Rede Wi-Fi:");
    tft.setCursor(10, 90);
    tft.setTextColor(TFT_WHITE, TFT_BLACK);
    tft.println(ssid);

    tft.setCursor(10, 120);
    tft.setTextColor(TFT_YELLOW, TFT_BLACK);
    tft.println("Senha:");
    tft.setCursor(10, 140);
    tft.setTextColor(TFT_WHITE, TFT_BLACK);
    tft.println(password);

    tft.setCursor(10, 170);
    tft.setTextColor(TFT_BLUE, TFT_BLACK);
    tft.print("Jogadores: ");
    tft.setTextColor(TFT_WHITE, TFT_BLACK);
    tft.println(jogadoresOnline);

    tft.setCursor(10, 200);
    tft.setTextColor(TFT_WHITE, TFT_MAGENTA);
    tft.println("Nomes:");
    tft.setTextColor(TFT_WHITE, TFT_BLACK);
    tft.setCursor(10, 220);
    tft.println(nomes);
}

void setup() {
    Serial.begin(115200);
    tft.init();
    tft.invertDisplay(true);

    pinMode(LED_R,OUTPUT);
  pinMode(LED_G,OUTPUT);
  pinMode(LED_B,OUTPUT);
  digitalWrite(LED_R, HIGH);
  digitalWrite(LED_G, HIGH);
  digitalWrite(LED_B, HIGH);

    exibeTelaMinecraft(); // Mostra tela inicial estilizada

    WiFi.begin(ssid, password);
    while (WiFi.status() != WL_CONNECTED) {
        delay(500);
    }

    buscarDadosServidor();
    exibirStatusVertical(); // Mostra os dados do servidor
}

void loop() {
    delay(60000); // Espera 1 minuto (60 segundos)
    Serial.println("Reiniciando...");
    
    ESP.restart(); // Chama um reboot

    digitalWrite(LED_R, LOW);
  delay(200);
  digitalWrite(LED_R, HIGH);
  delay(1000);
}