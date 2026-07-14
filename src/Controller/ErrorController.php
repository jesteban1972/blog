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
     * @param Environment $twig Twig is injected directly (safer than relying on $this->container->get('twig') because
     * it ensures the service is ready before the method runs.
     */
    public function __construct(
        private LoggerInterface $errorLogger,
        private Environment $twig,
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
        /// get the real HTTP status code

        $statusCode = ($exception instanceof HttpExceptionInterface)
            ? $exception->getStatusCode()
            : Response::HTTP_INTERNAL_SERVER_ERROR;


        ////////////////////////////////////////////////////////////////////////////////
        /// identify the specific template (e.g., 404.html.twig)

        $template = sprintf('errors/%s.html.twig', $statusCode);


        ////////////////////////////////////////////////////////////////////////////////
        /// special case: if it's a DB connection error, use the db template

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
        /// fallback to base if the specific template doesn't exist

        if (!$this->twig->getLoader()->exists($template)) {
            $template = 'errors/error_base.html.twig';
        }


        ////////////////////////////////////////////////////////////////////////////////
        /// log for your records

        $this->errorLogger->error('error page triggered', [
            'status' => $statusCode,
            'message' => $exception->getMessage()
        ]);


        ////////////////////////////////////////////////////////////////////////////////
        /// render the template and ensure the HTTP response code is correct

        return $this->render($template, [
            'status_code' => $statusCode,
            'status_text' => Response::$statusTexts[$statusCode] ?? '(unknown error)',
            'exception' => $exception,
            'exception_message' => $exception->getMessage(),
        ])->setStatusCode($statusCode);
    }
}
