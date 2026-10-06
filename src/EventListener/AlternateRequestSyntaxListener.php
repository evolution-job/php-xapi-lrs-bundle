<?php

/*
 * This file is part of the xAPI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace XApi\LrsBundle\EventListener;

use InvalidArgumentException;
use JsonException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Throwable;
use Xabbuh\XApi\Common\Exception\BadRequestException;
use XApi\LrsBundle\App\XapiAttribute;
use XApi\LrsBundle\App\XapiHeader;

/**
 * Handles xAPI Alternate Request Syntax (POST Tunneling) for ALL methods (GET, PUT, DELETE, etc.)
 * Must be executed BEFORE the Symfony Routing Listener.
 *
 * @author Jérôme Parmentier <jerome.parmentier@acensi.fr>
 * @author Mathieu Boldo <mathieu.boldo@entrili.com>
 */
#[AsEventListener(event: KernelEvents::REQUEST, method: 'onKernelRequest', priority: 35)]
final readonly class AlternateRequestSyntaxListener
{
    public function __construct(private RouterInterface $router) { }

    /**
     * @throws BadRequestException
     */
    public function onKernelRequest(RequestEvent $requestEvent): void
    {
        if (!$requestEvent->isMainRequest()) {
            return;
        }

        $request = $requestEvent->getRequest();
        if (!$request->isMethod(Request::METHOD_POST)) {
            return;
        }

        $method = $this->matchMethod($request);
        if (null === $method) {
            return;
        }

        if (!$this->isXapiRoute($request)) {
            return;
        }

        if (!in_array($method, [Request::METHOD_GET, Request::METHOD_PUT, Request::METHOD_DELETE, Request::METHOD_POST], true)) {
            throw new BadRequestException(sprintf('The tunneled method "%s" is not supported by xAPI alternate request syntax.', $method));
        }

        // STRICT CONFORMANCE: "The Learning Record Provider MUST NOT include any
        // other query string parameters on the request [than method]"
        $unexpectedParameters = array_diff(
            array_keys($request->query->all()),
            ['method']
        );

        if ([] !== $unexpectedParameters) {
            throw new BadRequestException(
                'Including other query parameters than "method" in the URL is not allowed. You must send them inside the request body.'
            );
        }

        $request->setMethod($method);
        $request->query->remove('method');

        $tunneledContentType = $request->request->get('Content-Type');
        $content = $request->request->get('content');

        if (is_string($content)) {
            $request->request->remove('content');

            if ($request->files->count() > 0) {
                $content = $this->rebuildAttachments($content, $request);
            }

            $request->initialize(
                $request->query->all(),
                $request->request->all(),
                $request->attributes->all(),
                $request->cookies->all(),
                $request->files->all(),
                $request->server->all(),
                $content
            );
        }

        $statementIdFromForm = $request->request->get('statementId');

        foreach ($request->request as $key => $value) {
            if (in_array($key, XapiHeader::ALLOWED, true)) {
                $request->headers->set($key, $value);
            } else {
                $request->query->set($key, $value);
            }

            $request->request->remove($key);
        }

        if ($tunneledContentType) {
            $request->headers->set('Content-Type', $tunneledContentType);
        }

        // Specific Validation for PUT Statements
        if ($method === Request::METHOD_PUT && str_ends_with($request->getPathInfo(), '/statements')) {
            $this->validateStatementPutRequest($request, $statementIdFromForm);
        }
    }

    /**
     * Search for a file uploaded whose SHA-256 hash matches the hash declared in the Statement.
     */
    private function findMatchingFileUrl(array $files, string $targetSha2): ?string
    {
        foreach ($files as $file) {
            if ($file instanceof UploadedFile && $file->isValid()) {
                $fileContent = file_get_contents($file->getPathname());

                // Conformance xAPI: check hash SHA-2
                if (hash('sha256', $fileContent) === $targetSha2) {
                    return $file->getPathname();
                }
            }
        }
        
        return null;
    }

    private function isXapiRoute(Request $request): bool
    {
        try {
            $parameters = $this->router->matchRequest($request);
        } catch (Throwable) {
            return false;
        }

        return $parameters[XapiAttribute::LRS_ROUTE] ?? false;
    }

    private function matchMethod(Request $request): ?string
    {
        try {
            $method = $request->query->getString('method');
        } catch (InvalidArgumentException) {
            return null;
        }

        if (!$method) {
            return null;
        }

        return strtoupper(trim($method));
    }

    private function validateStatementPutRequest(Request $request, ?string $idFromForm): void
    {
        try {
            $content = $request->getContent();
            if (!is_string($content) || $content === '') {
                throw new BadRequestException('Missing JSON request payload.');
            }
            
            $payload = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new BadRequestException('Invalid JSON request payload.');
        }

        $idBody = $payload['id'] ?? null;
        if ($idBody === null) {
            throw new BadRequestException('Statement id must be present in the JSON body for PUT requests.');
        }

        if ($idFromForm === null) {
            throw new BadRequestException('The "statementId" parameter is required in the body for alternative PUT requests.');
        }

        if ($idFromForm !== $idBody) {
            throw new BadRequestException('The "statementId" parameter must match the statement id inside the JSON body.');
        }
    }

    private function rebuildAttachments(string $content, Request $request): string
    {
        // Rebuild Attachments for PHP-XAPI-MODEL
        try {
            $statementData = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            $isBatch = !isset($statementData['actor']) && isset($statementData);
            $statements = $isBatch ? $statementData : [$statementData];

            foreach ($statements as &$statement) {

                if (!isset($statement['attachments']) || !is_array($statement['attachments'])) {
                    continue;
                }

                foreach ($statement['attachments'] as &$attachment) {
                    if (!empty($attachment['fileUrl'])) {
                        continue;
                    }

                    $fileContent = $this->findMatchingFileUrl($request->files->all(), $attachment['sha2'] ?? '');

                    if ($fileContent !== null) {
                        $attachment['fileUrl'] = $fileContent;
                    }
                }
            }
            
            unset($statement, $attachment);

            $content = json_encode($isBatch ? $statements : $statements[0], JSON_THROW_ON_ERROR);

        } catch (JsonException) {
            throw new BadRequestException('Invalid JSON payload while processing attachments.');
        }

        return $content;
    }
}
