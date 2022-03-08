<?php

namespace AliReaza\Atomic\Commands;

use AliReaza\Atomic\Events\WebSocketRequestEvent;
use AliReaza\Atomic\Events\WebSocketResponseEvent;
use AliReaza\EventDriven\EventDispatcher;
use AliReaza\EventDriven\ListenerProvider;
use RdKafka;
use Swoole;

class WebSocketCommand
{
    private Swoole\WebSocket\Server $server;
    private ?Swoole\Process $process = null;
    private ?Swoole\Table $keys = null;

    public function __construct(private EventDispatcher $dispatcher, private ListenerProvider $listener)
    {
    }

    public function server(): self
    {
        $this->server = new Swoole\WebSocket\Server(env('SWOOLE_WEB_SOCKET_HOST', 'localhost'), env('SWOOLE_WEB_SOCKET_PORT', 80));

        $this->server->set([
            'enable_coroutine' => false,
            'log_level' => SWOOLE_LOG_DEBUG,
        ]);

        return $this;
    }

    public function run(): void
    {
        $this->server->on('Start', [$this, 'onStart']);
        $this->server->on('Open', [$this, 'onOpen']);
        $this->server->on('Message', [$this, 'onMessage']);
        $this->server->on('Close', [$this, 'onClose']);

        $this->server->start();
    }

    public function __invoke()
    {
        $this->server()->run();
    }

    public function onStart(Swoole\WebSocket\Server $server): void
    {
        echo 'Swoole WebSocket Server is started at ' . $server->host . ':' . $server->port . "\n";
    }

    public function onOpen(Swoole\WebSocket\Server $server, Swoole\Http\Request $request): void
    {
        $this->newConnection($request);

        $this->listenerProcess($server);
    }

    private function newConnection(Swoole\Http\Request $request = null): void
    {
        if (is_null($this->keys)) {
            $this->keys = new Swoole\Table(env('SOCKET_SWOOLE_TABLE_SIZE', 1024 * 32));
            $this->keys->column('sec', Swoole\Table::TYPE_STRING, 32);
            $this->keys->create();
        }

        $this->keys->set($request->fd, ['sec' => $request->header['sec-websocket-key']]);
    }

    private function listenerProcess(Swoole\WebSocket\Server $server): void
    {
        if (is_null($this->process) || !file_exists("/proc/" . $this->process->pid)) {
            $this->process = new Swoole\Process(function (Swoole\Process $process) use ($server): void {
                $this->responsesHandler($server);

                $process->exit(0);
            }, false, true);

            $this->process->start();
        }
    }

    private function responsesHandler(Swoole\WebSocket\Server $server): void
    {
        $event_class = env('SOCKET_RESPONSE_EVENT', WebSocketResponseEvent::class);

        if (property_exists($this->listener->provider, 'conf') && $this->listener->provider->conf instanceof RdKafka\Conf) {
            $this->listener->provider->conf->set('group.id', 'Gateway-WebSocket-' . date("YmdHis") . time() . rand(1111, 9999));
        }

        $this->listener->addListener($event_class, function (WebSocketResponseEvent $response, string $correlation_id) use ($server): void {
            if ($server->isEstablished($response->fd) && $this->keys->exist($response->fd) && $this->keys->get($response->fd, 'sec') === $response->sec) {
                $server->push($response->fd, json_encode([$correlation_id => $response->content]));
            }
        });

        $this->listener->subscribe();
    }

    public function onMessage(Swoole\WebSocket\Server $server, Swoole\WebSocket\Frame $frame): void
    {
        $this->messagesHandler($server, $frame);
    }

    private function messagesHandler(Swoole\WebSocket\Server $server, Swoole\WebSocket\Frame $frame): void
    {
        if ($frame->data === '*PING*') {
            $server->push($frame->fd, '*PONG*');
        } else {
            $this->requestsHandler($server, $frame);
        }
    }

    private function requestsHandler(Swoole\WebSocket\Server $server, Swoole\WebSocket\Frame $frame): void
    {
        $event_class = env('SOCKET_REQUEST_EVENT', WebSocketRequestEvent::class);

        $request = new $event_class($frame->data, $frame->fd, $this->keys->get($frame->fd, 'sec'));

        $correlation_id = $this->dispatcher->getCorrelationId();

        $this->dispatcher->dispatch($request);

        $server->push($frame->fd, $correlation_id);
    }

    public function onClose(Swoole\WebSocket\Server $server, int $fd): void
    {
        if ($this->keys->exist($fd)) {
            $this->keys->del($fd);
        }
    }
}
