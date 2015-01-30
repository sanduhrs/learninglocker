<?php namespace Helpers;

use \Locker\XApi\IMT as XAPIIMT;

class Attachments {

  static function setAttachments($content_type, $incoming_statement){
    $return = [];
    $sha_hashes = [];

    // Gets boundary from content_type header - @todo not sure which way is better?
    preg_match('/boundary=(.*)$/', $content_type, $matches);

    // Throws exception if not found.
    if (!isset($matches[1])) throw new \Exception(
      'You need to set a boundary if submitting attachments.'
    );

    $boundary = '--' . $matches[1];

    // Fetches each part of the multipart document.
    $parts = array_slice(explode($boundary, $incoming_statement), 1);

    $data = [];
    $raw_headers = $body = '';

    // Loops through all parts on the body.
    foreach ($parts as $count => $part) {
      // At the end of the file, break
      if ($part == "--") break;

      // Determines the delimiter.
      $delim = "\n";
      if (strpos($part, "\r".$delim) !== false) $delim = "\r".$delim;

      // Separates body contents from headers.
      $part = ltrim($part, $delim);
      list($raw_headers, $body) = explode($delim.$delim, $part, 2);

      // Parses headers and separate so we can access.
      $raw_headers = explode($delim, $raw_headers);
      $headers     = array();
      foreach ($raw_headers as $header) {
        list($name, $value) = explode(':', $header);
        $headers[strtolower($name)] = ltrim($value, ' ');
      }

      // The first part must be statements.
      if ($count == 0) {
        // This is part one, which must be statements.
        if ($headers['content-type'] !== 'application/json') throw new \Exception(
          'Statements must make up the first part of the body.'
        );

        // Gets sha2 hash from each statement.
        $set_body = json_decode($body, true);
        if (is_array(json_decode($body))) {
          foreach($set_body as $a){
            foreach($a['attachments'] as $attach){
              $sha_hashes[] = $attach['sha2'];
            }
          }
        } else {
          foreach ($set_body['attachments'] as $attach) {
            $sha_hashes[] = $attach['sha2'];
          }
        }

        // Sets body which will = statements.
        $return['body'] = $body;
      } else {
        // Gets the attachment type (Should this be required? @todo).
        if (!isset($headers['content-type'])) throw new \Exception(
          'You need to set a content type for your attachments.'
        );

        // Gets the correct ext if valid.
        $file_type = new XAPIIMT($headers['content-type']);
        Helpers::validateAtom($file_type);

        // Checks X-Experience-API-Hash is set, otherwise reject @todo.
        if (!isset($headers['x-experience-api-hash']) || $headers['x-experience-api-hash'] == '') throw new \Exception(
          'Attachments require an api hash.'
        );

        // Checks X-Experience-API-Hash is contained within a statement.
        if (!in_array($headers['x-experience-api-hash'], $sha_hashes)) throw new \Exception(
          'Attachments need to contain x-experience-api-hash that is declared in statement.'
        );

        $return['attachments'][$count] = $part;
      }
    }
    return $return;
  }
}
