<?php
// Server details
$host = '127.0.0.1';
$port = 8080;

// Create a socket
$serverSocket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if (!$serverSocket) {
    die("Error creating socket: " . socket_strerror(socket_last_error()) . "\n");
}

// Allow the port to be reused
socket_set_option($serverSocket, SOL_SOCKET, SO_REUSEADDR, 1);

// Bind the socket to the host and port
if (!socket_bind($serverSocket, $host, $port)) {
    die("Error binding socket: " . socket_strerror(socket_last_error()) . "\n");
}

// Start listening for connections
if (!socket_listen($serverSocket)) {
    die("Error listening on socket: " . socket_strerror(socket_last_error()) . "\n");
}

echo "Server started on $host:$port\n";

// Array to hold client sockets
$clients = [];

while (true) {
    // Prepare an array of sockets to check for data
    $readSockets = $clients;
    $readSockets[] = $serverSocket;

    // Check for activity on the sockets
    if (socket_select($readSockets, $writeSockets, $exceptSockets, 0, 10) < 1) {
        continue;
    }

    // Check for new connections
    if (in_array($serverSocket, $readSockets)) {
        $newSocket = socket_accept($serverSocket);
        if ($newSocket !== false) {
            $clients[] = $newSocket;

            // Read the client's handshake request
            $header = socket_read($newSocket, 1024);
            // Perform the WebSocket handshake
            handshake($newSocket, $header);
            // Send a welcome message
            socket_write($newSocket, mask("Welcome to the WebSocket server!"));
        }
        unset($readSockets[array_search($serverSocket, $readSockets)]);
    }

    // Loop through the sockets with data
    foreach ($readSockets as $clientSocket) {
        $data = @socket_read($clientSocket, 1024);
        if (!$data) {
            // Client disconnected
            unset($clients[array_search($clientSocket, $clients)]);
            socket_close($clientSocket);
            continue;
        }
        // Decode the message
        $message = unmask($data);
        echo "Received: $message\n";
        // Send the message to all clients
        foreach ($clients as $client) {
            socket_write($client, mask("Echo: {$message}"));
        }
    }
}

// Perform the WebSocket handshake
function handshake($client, $header) {
    preg_match("/Sec-WebSocket-Key: (.*)\r\n/", $header, $matches);
    $key = trim($matches[1]);
    $acceptKey = base64_encode(pack('H*', sha1("{$key}258EAFA5-E914-47DA-95CA-C5AB0DC85B11")));
    $upgrade = "HTTP/1.1 101 Switching Protocols\r\n" .
               "Upgrade: websocket\r\n" .
               "Connection: Upgrade\r\n" .
               "Sec-WebSocket-Accept: {$acceptKey}\r\n\r\n";
    socket_write($client, $upgrade);
}

// Decode a WebSocket message
function unmask($payload) {
    $length = ord($payload[1]) & 127;
    if ($length == 126) {
        $masks = substr($payload, 4, 4);
        $data = substr($payload, 8);
    } elseif ($length == 127) {
        $masks = substr($payload, 10, 4);
        $data = substr($payload, 14);
    } else {
        $masks = substr($payload, 2, 4);
        $data = substr($payload, 6);
    }
    $text = '';
    for ($i = 0; $i < strlen($data); $i++) {
        $text .= $data[$i] ^ $masks[$i % 4];
    }
    return $text;
}

// Encode a message for WebSocket
function mask($text) {
    $b1 = 0x81;
    $length = strlen($text);
    if ($length <= 125) {
        $header = pack('CC', $b1, $length);
    } elseif ($length > 125 && $length < 65536) {
        $header = pack('CCn', $b1, 126, $length);
    } else {
        $header = pack('CCNN', $b1, 127, $length);
    }
    return "{$header}{$text}";
}
