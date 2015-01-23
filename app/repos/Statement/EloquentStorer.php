<?php namespace Repos\Statement;

interface StorerInterface {
  public function store(array $statements, Authority $authority, array $attachments);
}

class EloquentStorer implements StorerInterface {
  public function store(array $statements, Authority $authority, array $attachments) {
    try {
      $statements = $this->constructed_statements($statements, $authority);
      
      $this->insertStatements($statements, $authority);
      $this->linkStatements($statements, $authority);
      $this->activateStatements($statements, $authority);
      
      $this->storeAttachments($attachments, $authority);
      
      return IlluminateResponse::json(array_keys($statements), 200);
    } catch (ConflictException $ex) {
      return IlluminateResponse::make($ex->getMessage(), 409);
    }
  }

  private function constructStatements(array $statements, Authority $authority) {
    $constructed_statements = [];

    foreach ($statements as $statement) {
      $statement->authority = $authority->actor;
      $statement->stored = Helpers::getCurrentDate();

      if (!$statement->timestamp) {
        $statement->timestamp = $statement->stored;
      }

      if (!$statement->id) {
        $statement->id = Helpers::makeUUID();
      }

      $constructed_statement = new XAPIStatement($statement);

      // Validates $constructed_statement.
      $errors = $constructed_statement->validate();
      if (count($errors) > 0) {
        throw new \Exception(json_encode($errors));
      }

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
    return (new Inserter)->insert($statements, $authority);
  }

  private function linkStatements(array $statements, Authority $authority) {
    return (new Linker)->link($statements, $authority);
  }

  private function activateStatements(array $statements, Authority $authority) {
    return (new Getter)
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