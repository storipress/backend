<?php

namespace App\Resources\Partners\LinkedIn;

class Article
{
    public function __construct(
        public string $author,
        public string $title,
        public string $text,
        public string $link,
        public ?string $image,
    ) {}
}
