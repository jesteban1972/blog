<?php
declare(strict_types=1);
// file ~/Sites/blog/src/Controller/ErrorController.php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Doctrine\DBAL\Exception\ConnectionException;
use Twig\Environment;

class ErrorController extends AbstractController
{
    /**
     * @param LoggerInterface $errorLogger
     * @param Environment $twig Twig is injected directly to ensure service availability
     */
    public function __construct(
        private LoggerInterface $errorLogger,
        private Environment     $twig,
    ) {}

    public function showException(\Throwable $exception): Response
    {
        // automatically dump and die if we are in development mode:
        if ($this->getParameter('kernel.debug')) {
            dd([
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTrace()
            ]);
        }

        ////////////////////////////////////////////////////////////////////////////////
        /// 1. resolve http status code

        $statusCode = ($exception instanceof HttpExceptionInterface)
            ? $exception->getStatusCode()
            : Response::HTTP_INTERNAL_SERVER_ERROR;

        ////////////////////////////////////////////////////////////////////////////////
        /// 2. resilient logging (isolated from disk failure)

        try {

            $this->errorLogger->error('error page triggered', [
                'status' => $statusCode,
                'message' => $exception->getMessage()
            ]);

        } catch (\Throwable $logException) {

            // fallback directly to stderr if file logging channels crash
            error_log(sprintf('[CRITICAL] log system failed: %s', $logException->getMessage()));
        }

        ////////////////////////////////////////////////////////////////////////////////
        /// 3. map exception types to dedicated templates

        $template = sprintf('errors/%s.html.twig', $statusCode);

        // check the current exception AND any previous ones in the chain:
        $isDatabaseError = false;
        $checkException = $exception;

        while ($checkException) {
            if ($checkException instanceof ConnectionException || str_contains(get_class($checkException), 'Doctrine')) {
                $isDatabaseError = true;
                break;
            }
            $checkException = $checkException->getPrevious();
        }

        if ($isDatabaseError) {
            $template = 'errors/db.html.twig';
        }

        ////////////////////////////////////////////////////////////////////////////////
        /// 4. protected rendering pipeline

        try {

            if (!$this->twig->getLoader()->exists($template)) {
                $template = 'errors/error_base.html.twig';
            }

            return $this->render($template, [
                'status_code' => $statusCode,
                'status_text' => Response::$statusTexts[$statusCode] ?? '(unknown error)',
                'exception' => $exception,
                'exception_message' => $exception->getMessage(),
            ])->setStatusCode($statusCode);

        } catch (\Throwable $renderException) {

            // catches all twig compilation, path(), or tempnam() permission crashes
            return $this->renderEmergencyFallback($statusCode, $exception, $renderException);
        }
    }

    /**
     * raw html emergency fallback (bypasses twig and filesystem completely).
     *
     * DEV TROUBLESHOOTING:
     * if this screen renders in production, twig or filesystem storage failed completely.
     * do not scroll through raw container logs; use targeted filtering:
     *
     * 1. check stderr for critical runtime/boot exceptions:
     *    docker logs blog-php 2>&1 | grep -iE 'critical|emergency|fatal' | tail -n 20
     *
     * 2. verify filesystem & container permissions:
     *    docker exec -it blog-php ls -la var/cache
     *
     * 3. inspect latest application error logs (if file system is accessible):
     *    tail -n 50 logs/error.log
     */
    private function renderEmergencyFallback(
        int $statusCode,
        \Throwable $exception,
        ?\Throwable $renderException = null
    ): Response {
        if ($renderException !== null) {
            error_log(sprintf('[EMERGENCY FALLBACK] rendering failed: %s', $renderException->getMessage()));
        }

        if (ob_get_length()) {
            ob_clean();
        }

        $title = Response::$statusTexts[$statusCode] ?? 'service temporarily unavailable';

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>blog service error - {$statusCode}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #311432; /* --purple-eggplant */
            color: #E39FF6; /* --purple-lavender */
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
        }
        .card {
            background-color: #4D0F28; /* --purple-sangria */
            border: 2px solid #710193; /* --purple-violet */
            padding: 2.5rem;
            border-radius: 12px;
            max-width: 480px;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
        }
        h1 {
            font-size: 3.5rem;
            margin: 0 0 0.5rem;
            color: #E39FF6; /* --purple-lavender */
        }
        p {
            font-size: 1.1rem;
            line-height: 1.5;
            color: #f5f5dc; /* --beige */
            margin-bottom: 1rem;
        }
        .subtext {
            font-size: 0.95rem;
            color: #7A4988; /* --purple-mauve */
            margin-top: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>{$statusCode}</h1>
        <p><strong>{$title}</strong></p>
        <p>blog.pendoncete.org is experiencing temporary infrastructure issues.</p>
        <div class="subtext">technical details have been recorded in system logs.</div>
    </div>
</body>
</html>
HTML;

        return new Response($html, $statusCode, ['Content-Type' => 'text/html']);
    }
}
