const WebSocket = require('ws');

const PORT = 9090;

const wss = new WebSocket.Server({
    port: PORT
});

wss.on('connection', (ws) => {
    console.log('Client connected');

    ws.on('message', (message) => {
        console.log('Received:', message.toString());

        wss.clients.forEach((client) => {
            if (client.readyState === WebSocket.OPEN) {
                client.send(message.toString());
            }
        });
    });

    ws.on('close', () => {
        console.log('Client disconnected');
    });
});

console.log(`Running on ws://localhost:${PORT}`);