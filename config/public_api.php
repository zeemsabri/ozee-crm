<?php

return [
    // 1) Simple keys via env (comma-separated). Example: PUBLIC_API_KEYS=key1,key2
    'keys' => collect(explode(',', (string) env('PUBLIC_API_KEYS', '')))
        ->map(fn ($v) => is_string($v) ? trim($v) : $v)
        ->filter(fn ($v) => is_string($v) && $v !== '')
        ->values()
        ->all(),

    // 2) Advanced entries allow per-key domain restrictions.
    // Each entry: ['key' => 'your-key', 'domains' => ['example.com', '*.example.org']]
    // If 'domains' is provided and non-empty, incoming requests must have an Origin or Referer host matching one of the patterns.
    'entries' => array (
  0 => 
  array (
    'key' => 'x8LI8gJpYqsROGm13Cl68ckodTmZd9wxYqOXz9Tzy5IT5nYjKIt8CpC5Nq4yx0x4',
    'domains' => 
    array (
      0 => 'ozeeweb.com.au',
      1 => '*.ozeeweb.com.au',
    ),
  ),
  1 => 
  array (
    'key' => 'h6BGj4JpKmZEV4VMoFg0zQfmiBef97CaUbgzMgavWro1QTOX7HiuciIVsiKEiVcs',
    'domains' => 
    array (
      0 => 'ozeeweb.com.au',
      1 => '*.ozeeweb.com.au',
    ),
  ),
  2 => 
  array (
    'key' => 'b36a1883b27b0b311b519e075836c49611f7f02d4f576e27a6f2b0f3408b8d1a',
    'domains' => 
    array (
      0 => 'ozeeweb.com.au',
      1 => '*.ozeeweb.com.au',
    ),
  ),
),
];