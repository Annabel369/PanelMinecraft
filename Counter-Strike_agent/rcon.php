<?php

class Rcon
{
    private $host;
    private $port;
    private $password;
    private $timeout;

    private $socket;
    private $authorized = false;
    private $last_response;

    const PACKET_AUTHORIZE = 5;
    const PACKET_COMMAND = 6;

    const SERVERDATA_AUTH = 3;
    const SERVERDATA_AUTH_RESPONSE = 2;
    const SERVERDATA_EXECCOMMAND = 2;
    const SERVERDATA_RESPONSE_VALUE = 0;

    public function __construct($host, $port, $password, $timeout)
    {
        $this->host = $host;
        $this->port = $port;
        $this->password = $password;
        $this->timeout = $timeout;

        echo "🔧 [DEBUG] Rcon::__construct - Host: $host, Porta: $port, Timeout: $timeout<br>";
    }

    public function get_response()
    {
        return $this->last_response;
    }

    public function connect()
    {
        echo "🔌 [DEBUG] Tentando conectar com fsockopen...<br>";
        @$this->socket = fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);

        if (!$this->socket) {
            $this->last_response = $errstr;
            echo "❌ [DEBUG] Falha na conexão: $errstr (Erro $errno)<br>";
            echo 'Server is offline.<br>';
            return false;
        }

        echo "✅ [DEBUG] Conexão estabelecida. Configurando timeout de leitura...<br>";
        stream_set_timeout($this->socket, 3, 0);

        echo "🔐 [DEBUG] Tentando autorizar com senha RCON...<br>";
        $auth = $this->authorize();

        if ($auth) {
            echo "✅ [DEBUG] Autorizado com sucesso!<br>";
            return true;
        }

        echo "❌ [DEBUG] Autorização falhou.<br>";
        return false;
    }

    public function disconnect()
    {
        if ($this->socket) {
            echo "🔌 [DEBUG] Fechando conexão...<br>";
            fclose($this->socket);
        }
    }

    public function is_connected()
    {
        echo "🔍 [DEBUG] Verificando conexão: " . ($this->authorized ? "Autorizado" : "Não autorizado") . "<br>";
        return $this->authorized;
    }

    public function send_command($command)
    {
        if (!$this->is_connected()) {
            echo "⚠️ [DEBUG] Não conectado. Comando não será enviado.<br>";
            return false;
        }

        echo "📤 [DEBUG] Enviando comando: $command<br>";
        $this->write_packet(Rcon::PACKET_COMMAND, Rcon::SERVERDATA_EXECCOMMAND, $command);

        echo "📥 [DEBUG] Lendo resposta do servidor...<br>";
        $response_packet = $this->read_packet();

        echo "📦 [DEBUG] Pacote recebido: ID={$response_packet['id']}, TYPE={$response_packet['type']}<br>";

        if ($response_packet['id'] == Rcon::PACKET_COMMAND && $response_packet['type'] == Rcon::SERVERDATA_RESPONSE_VALUE) {
            $this->last_response = $response_packet['body'];
            echo "✅ [DEBUG] Resposta do servidor: <pre>" . htmlspecialchars($response_packet['body']) . "</pre>";
            return $response_packet['body'];
        }

        echo "⚠️ [DEBUG] Resposta inesperada ou vazia.<br>";
        return false;
    }

    private function authorize()
    {
        $this->write_packet(Rcon::PACKET_AUTHORIZE, Rcon::SERVERDATA_AUTH, $this->password);
        $response_packet = $this->read_packet();

        echo "📦 [DEBUG] Pacote de autorização: ID={$response_packet['id']}, TYPE={$response_packet['type']}<br>";

        if ($response_packet['type'] == Rcon::SERVERDATA_AUTH_RESPONSE && $response_packet['id'] == Rcon::PACKET_AUTHORIZE) {
            $this->authorized = true;
            return true;
        }

        echo "❌ [DEBUG] Autorização negada pelo servidor.<br>";
        $this->disconnect();
        return false;
    }

    private function write_packet($packet_id, $packet_type, $packet_body)
    {
        echo "✏️ [DEBUG] Criando pacote: ID=$packet_id, TYPE=$packet_type, BODY=$packet_body<br>";

        $packet = pack("VV", $packet_id, $packet_type);
        $packet .= $packet_body . "\x00";
        $packet .= "\x00";

        $packet_size = strlen($packet);
        $packet = pack("V", $packet_size) . $packet;

        fwrite($this->socket, $packet, strlen($packet));
        echo "📤 [DEBUG] Pacote enviado com tamanho $packet_size bytes<br>";
    }

    private function read_packet()
    {
        $size_data = fread($this->socket, 4);
        if (strlen($size_data) < 4) {
            echo "⚠️ [DEBUG] Falha ao ler tamanho do pacote<br>";
            return ['id' => -1, 'type' => -1, 'body' => ''];
        }

        $size_pack = unpack("V1size", $size_data);
        $size = $size_pack['size'];
        echo "📏 [DEBUG] Tamanho do pacote: $size bytes<br>";

        $packet_data = fread($this->socket, $size);
        $packet_pack = unpack("V1id/V1type/a*body", $packet_data);

        echo "📥 [DEBUG] Dados do pacote lidos: ID={$packet_pack['id']}, TYPE={$packet_pack['type']}<br>";
        return $packet_pack;
    }
}