<?php

namespace AliReaza\Atomic\Controllers;

use AliReaza\Atomic\Events\HttpRequestEvent;
use AliReaza\Atomic\Events\HttpResponseEvent;
use AliReaza\Atomic\Events\UploadedFileEvent;
use AliReaza\EventDriven\EventDispatcher;
use Predis;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class HttpController
{
    public function __construct(private Request $request, private JsonResponse $response, private EventDispatcher $dispatcher, private Predis\Client $redis_client)
    {
    }

    public function __invoke(): void
    {
        if ($this->request->isMethod(Request::METHOD_POST)) {
            $this->handleRequest();
        } else if ($this->hasCorrelationIdRequest() && !empty($correlation_id = $this->request->query->get('correlation_id'))) {
            $this->getCorrelationId($correlation_id);
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

            if (env('HTTP_SYNC_ENABLE', false) && $this->hasSyncRequest()) {
                $this->getCorrelationId($correlation_id);
            }
        }
    }

    private function handleFiles(array $files): array
    {
        $_files = [];

        $filesystem = new Filesystem();

        foreach ($files as $key => $file) {
            if ($file instanceof UploadedFile) {
                $storage_directory = env('STORAGE_DIRECTORY', '/storage/');
                $hash = hash_file('sha256', $file->getPathname());
                $name = $file->getClientOriginalName();
                $mime = $file->getMimeType();

                if ($filesystem->exists($storage_directory . $hash)) {
                    $filesystem->remove($file->getRealPath());
                } else {
                    $file->move($storage_directory, $hash);
                }

                $_files[$key] = [
                    'name' => $name,
                    'mime' => $mime,
                    'hash' => $hash,
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

        $event_id = $this->dispatcher->getEventId();
        $correlation_id = $this->dispatcher->getCorrelationId();

        $this->redis_client->set($correlation_id, null);
        $this->redis_client->persist($correlation_id);

        $this->dispatcher->dispatch($request);

        if (is_array($files) && !empty($files)) {
            foreach ($files as $file) {
                $event_class = env('UPLOADED_FILE_EVENT', UploadedFileEvent::class);

                $uploaded = new $event_class($file['name'], $file['mime'], $file['hash']);

                $this->dispatcher->setCorrelationId($correlation_id);
                $this->dispatcher->setCausationId($event_id);
                $this->dispatcher->dispatch($uploaded);
            }
        }

        $this->response->setStatusCode(Response::HTTP_ACCEPTED);
        $this->response->setData([
            'correlation_id' => $correlation_id
        ]);

        return $correlation_id;
    }

    private function hasSyncRequest(): bool
    {
        $sync_param_name = env('HTTP_SYNC_PARAM_NAME', 'sync');
        return $this->request->isMethod(Request::METHOD_POST) && $this->request->query->has($sync_param_name);
    }

    private function hasCorrelationIdRequest(): bool
    {
        return $this->request->isMethod(Request::METHOD_GET) && $this->request->query->has('correlation_id');
    }

    private function getCorrelationId(string $correlation_id): void
    {
        $this->response->setStatusCode(Response::HTTP_NOT_FOUND);

        if ($this->redis_client->exists($correlation_id)) {
            $this->response->setStatusCode(Response::HTTP_ACCEPTED);

            $time_steps = 250000;
            for ($time = 0; $time <= (env('HTTP_SYNC_TIMEOUT_SEC', 5) * 1000000); $time += $time_steps) {
                $value = $this->redis_client->get($correlation_id);

                if (empty($value)) {
                    usleep($time_steps);

                    continue;
                }

                $values = json_decode($value);

                $event_class = env('HTTP_RESPONSE_EVENT', HttpResponseEvent::class);
                $response = new $event_class($values->content, $values->status_code);

                $this->response->headers->set('correlation_id', $correlation_id);
                $this->response->headers->set('Expires', $this->redis_client->ttl($correlation_id));
                $this->response->setStatusCode($response->status_code);
                $this->response->setJson($response->content);

                break;
            }
        }
    }
}
