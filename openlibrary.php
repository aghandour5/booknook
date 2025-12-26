<?php
declare(strict_types=1);

function normalize_isbn(string $isbn): string {
  $isbn = strtoupper(trim($isbn));
  return preg_replace('/[^0-9X]/', '', $isbn) ?? "";
}

function http_get_json(string $url): ?array {
  $ctx = stream_context_create([
    "http" => [
      "timeout" => 6,
      "header" => "User-Agent: BookNookPHP/1.0\r\n"
    ]
  ]);
  $raw = @file_get_contents($url, false, $ctx);
  if ($raw === false) return null;
  $data = json_decode($raw, true);
  return is_array($data) ? $data : null;
}

function fetch_openlibrary_by_isbn(string $isbn): array {
  $isbn = normalize_isbn($isbn);
  if ($isbn === "") return [];

  $url = "https://openlibrary.org/api/books?bibkeys=ISBN:" . rawurlencode($isbn) . "&format=json&jscmd=data";
  $json = http_get_json($url);
  if (!$json) return [];

  $key = "ISBN:" . $isbn;
  $b = $json[$key] ?? null;
  if (!is_array($b)) return [];

  $title = $b["title"] ?? null;

  $authorName = null;
  $authorOlid = null;
  if (!empty($b["authors"]) && is_array($b["authors"])) {
    $authorName = $b["authors"][0]["name"] ?? null;
    $authorKey = $b["authors"][0]["key"] ?? null; // "/authors/OLxxxxA"
    if (is_string($authorKey) && preg_match('#^/authors/(OL[0-9A-Z]+A)$#', $authorKey, $m)) {
      $authorOlid = $m[1];
    }
  }

  $coverUrl = null;
  if (!empty($b["cover"]) && is_array($b["cover"])) {
    $coverUrl = $b["cover"]["medium"] ?? ($b["cover"]["small"] ?? ($b["cover"]["large"] ?? null));
  }

  $authorPhoto = null;
  if ($authorOlid) {
    $authorPhoto = "https://covers.openlibrary.org/a/olid/" . rawurlencode($authorOlid) . "-M.jpg?default=false";
  }

  return [
    "title" => is_string($title) ? $title : null,
    "author" => is_string($authorName) ? $authorName : null,
    "cover_url" => is_string($coverUrl) ? $coverUrl : null,
    "author_olid" => $authorOlid,
    "author_photo_url" => $authorPhoto
  ];
}
