<?php

namespace App\Message;

final class ProcessDocumentMessage
{
    public function __construct(
        public readonly int $documentId,
    ) {}
}
