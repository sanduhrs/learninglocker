<?php namespace Repos\Statement;

use \Models\Authority as Authority;
use \Helpers\Helpers as Helpers;
use \Locker\XApi\Statement as XAPIStatement;

interface StorerInterface {
  public function store(array $statements, Authority $authority, array $attachments);
}

class EloquentStorer implements StorerInterface {
  public function store(array $statements, Authority $authority, array $attachments) {
    $statements = $this->constructStatements($statements, $authority);

    $this->insertStatements($statements, $authority);
    $this->linkStatements($statements, $authority);
    $this->activateStatements($statements, $authority);

    $this->storeAttachments($attachments, $authority);

    return array_keys($statements);
  }

  private function constructStatements(array $statements, Authority $authority) {
    $constructed_statements = [];

    foreach ($statements as $statement) {
      $statement->authority = $authority->getActor();
      $statement->stored = Helpers::getCurrentDate();

      if (!isset($statement->timestamp)) {
        $statement->timestamp = $statement->stored;
      }

      if (!isset($statement->id)) {
        $statement->id = Helpers::makeUUID();
      }

      $constructed_statement = new XAPIStatement($statement);

      // Validates $constructed_statement.
      Helpers::validateAtom($constructed_statement);

      // Adds $constructed_statement to $constructed_statements.
      if (isset($constructed_statements[$statement->id])) {
        (new Inserter)->checkMatch($constructed_statements, $constructed_statements[$statement->id]);
      } else {
        $constructed_statements[$statement->id] = $constructed_statement;
      }
    }

    return $constructed_statements;
  }

  private function insertStatements(array $statements, Authority $authority) {
    return (new EloquentInserter)->insert($statements, $authority);
  }

  private function linkStatements(array $statements, Authority $authority) {
    return (new EloquentLinker)->link($statements, $authority);
  }

  private function activateStatements(array $statements, Authority $authority) {
    return (new EloquentGetter)
      ->where($authority)
      ->whereIn('statement.id', array_keys($statements))
      ->update(['active' => true]);
  }

  private function storeAttachments(array $attachments, Authority $authority) {
    foreach ($attachments as $attachment) {
      // Determines the delimiter.
      $delim = "\n";
      if (strpos($attachment, "\r".$delim) !== false) $delim = "\r$delim";

      // Separates body contents from headers.
      $attachment = ltrim($attachment, $delim);
      list($raw_headers, $body) = explode($delim . $delim, $attachment, 2);

      // Stores the attachment.
      $file_name = $this->getFileName($raw_headers, $authority);
      $file = fopen($file_name, 'wb');
      $size = fwrite($file, $body);
      fclose( $file );

      if ($size === false) {
        throw new \Exception('There was an issue saving the attachment');
      }
    }
  }

  private function getFileName(array $raw_headers, Authority $authority) {
    // Determines headers.
    $headers = $this->getHeaders($raw_headers);

    // Calculates the $file_name.
    $ext = $this->getExtension($headers['content-type']);
    $file_name = str_random(12) . "." . $ext;

    // Calculates the $destination_path.
    $hash = $headers['x-experience-api-hash'];
    $authority_id = $authority->_id;
    $destination_path = base_path() . '/uploads/' . $authority_id . '/attachments/' . $hash . '/';

    // Creates a directory if it doesn't exist.
    if (!\File::exists($destination_path)) {
      \File::makeDirectory($destination_path, 0775, true);
    }

    return $destinationPath . $file_name;
  }

  private function getHeaders(array $raw_headers) {
    $raw_headers = explode($delim, $raw_headers);
    $headers = [];

    foreach ($raw_headers as $header) {
      list($name, $value) = explode(':', $header);
      $headers[strtolower($name)] = ltrim($value, ' ');
    }

    return $headers;
  }

  private function getExtension($content_type) {
    $ext = array_search($content_type, FileTypes::getMap());
    if ($ext === false) throw new \Exception(
      'This file type cannot be supported'
    );
    return $ext;
  }
}
