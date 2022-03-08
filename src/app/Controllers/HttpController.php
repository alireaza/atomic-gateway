<?php

namespace AliReaza\Atomic\Controllers;

use AliReaza\Atomic\Events\HttpRequestEvent;
use AliReaza\Atomic\Events\HttpResponseEvent;
use AliReaza\EventDriven\EventDispatcher;
use AliReaza\EventDriven\ListenerProvider;
use RdKafka;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class HttpController
{
    public function __construct(private Request $request, private JsonResponse $response, private EventDispatcher $dispatcher, private ListenerProvider $listener)
    {
    }

    public function __invoke(): void
    {
        if ($this->request->isMethod(Request::METHOD_POST)) {
            $this->handleRequest();
        } else if ($this->hasCorrelationIdRequest() && !empty($correlation_id = $this->request->query->get('correlation_id'))) {
            $this->listen($correlation_id);
        }
    }

    private function handleRequest()
    {
        try {
            if (empty($files = $this->request->files->all())) {
                $content = $this->request->toArray(); // Just to check if the content is JSON
                $files = null;
            } else {
                $content = $this->request->request->all();
                $files = $this->handleFiles($files);
            }
        } catch (Throwable $exception) {
            $content = null;
            $files = null;

            if ($exception->getMessage() !== 'Request body is empty.') {
                throw $exception;
            }
        }

        if (is_array($content)) {
            $correlation_id = $this->dispatch($content, $files);

            if ($this->hasSyncRequest()) {
                $this->listen($correlation_id);
            }
        }
    }

    private function handleFiles(array $files): array
    {
        $_files = [];

        foreach ($files as $key => $file) {
            if ($file instanceof UploadedFile) {
                $_files[$key] = [
                    'name' => $file->getClientOriginalName(),
                    'mime' => $file->getMimeType(),
                    'hash' => $file->getRealPath(),
                ];
            }
        }

        return $_files;
    }

    private function dispatch(array $content, ?array $files): string
    {
        $event_class = env('HTTP_REQUEST_EVENT', HttpRequestEvent::class);

        $json_content = json_encode($content);

        $request = new $event_class($json_content, $files);

        $correlation_id = $this->dispatcher->getCorrelationId();

        $this->dispatcher->dispatch($request);

        $this->response->setStatusCode(Response::HTTP_ACCEPTED);
        $this->response->setData([
            'correlation_id' => $correlation_id
        ]);

        return $correlation_id;
    }

    private function hasSyncRequest(): bool
    {
        return $this->request->isMethod(Request::METHOD_POST) && $this->request->query->has('sync');
    }

    private function hasCorrelationIdRequest(): bool
    {
        return $this->request->isMethod(Request::METHOD_GET) && $this->request->query->has('correlation_id');
    }

    private function listen(string $correlation_id): void
    {
        $event_class = env('HTTP_RESPONSE_EVENT', HttpResponseEvent::class);

        if (property_exists($this->listener->provider, 'conf') && $this->listener->provider->conf instanceof RdKafka\Conf) {
            $this->listener->provider->conf->set('group.id', 'Gateway-Http-' . date("YmdHis") . time() . rand(1111, 9999));
        }

        $cid = $correlation_id;
        $this->listener->addListener($event_class, function (HttpResponseEvent $response, string $correlation_id) use ($cid): void {
            if ($cid === $correlation_id) {
                $this->response->headers->set('correlation_id', $correlation_id);
                $this->response->setStatusCode($response->status_code);
                $this->response->setJson($response->content);

                $this->listener->unsubscribe();
            }
        });

        $this->response->setStatusCode(Response::HTTP_REQUEST_TIMEOUT);

        $this->listener->subscribe(env('SYNC_TIMEOUT', 10000));
    }
}
