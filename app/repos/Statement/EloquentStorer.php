<?php namespace Repos\Statement;

use \Models\Authority as Authority;
use \Helpers\Helpers as Helpers;
use \Locker\XApi\Statement as XAPIStatement;
use \Locker\XApi\IMT as XAPIIMT;
use \Repos\Document\Files as DocumentFiles;

interface StorerInterface {
  public function store(array $statements, Authority $authority, array $attachments);
}

class EloquentStorer implements StorerInterface {
  /**
   * Stores the given statements with the authority and attachments.
   * @param [\stdClass] $statements
   * @param Authority $authority The Authority to restrict with.
   * @param [mixed] $attachments
   * @return [String] ID's of all the stored statements.
   */
  public function store(array $statements, Authority $authority, array $attachments) {
    $statements = $this->constructStatements($statements, $authority);

    $this->insertStatements($statements, $authority);
    $this->linkStatements($statements, $authority);
    $this->activateStatements($statements, $authority);

    $this->storeAttachments($attachments, $authority);

    return array_keys($statements);
  }

  /**
   * Constructs the given statements with the authority.
   * @param [\stdClass] $statements
   * @param Authority $authority The Authority to restrict with.
   * @return [XAPIStatement]
   */
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
      Helpers::validateAtom($constructed_statement, 'statement');

      // Adds $constructed_statement to $constructed_statements.
      if (isset($constructed_statements[$statement->id])) {
        (new Inserter)->checkMatch($constructed_statements, $constructed_statements[$statement->id]);
      } else {
        $constructed_statements[$statement->id] = $constructed_statement;
      }
    }

    return $constructed_statements;
  }

  /**
   * Inserts the given statements with the authority.
   * @param [\stdClass] $statements
   * @param Authority $authority The Authority to restrict with.
   * @return Boolean
   */
  private function insertStatements(array $statements, Authority $authority) {
    return (new EloquentInserter)->insert($statements, $authority);
  }

  /**
   * Links the given statements with the authority.
   * @param [\stdClass] $statements
   * @param Authority $authority The Authority to restrict with.
   * @return Boolean
   */
  private function linkStatements(array $statements, Authority $authority) {
    return (new EloquentLinker)->link($statements, $authority);
  }

  /**
   * Activates the given statements with the authority.
   * @param [\stdClass] $statements
   * @param Authority $authority The Authority to restrict with.
   * @return Boolean
   */
  private function activateStatements(array $statements, Authority $authority) {
    return (new EloquentGetter)
      ->where($authority)
      ->whereIn('statement.id', array_keys($statements))
      ->update(['active' => true]);
  }

  /**
   * Stores the attachments with the authority.
   * @param [mixed] $attachments
   * @param Authority $authority The Authority to restrict with.
   */
  private function storeAttachments(array $attachments, Authority $authority) {
    foreach ($attachments as $attachment) {
      // Determines the delimiter.
      $delim = "\n";
      if (strpos($attachment, "\r".$delim) !== false) $delim = "\r$delim";

      // Separates body contents from headers.
      $attachment = ltrim($attachment, $delim);
      list($raw_headers, $body) = explode($delim . $delim, $attachment, 2);

      // Stores the attachment.
      $file_name = $this->getFileName($raw_headers, $delim, $authority);
      $file = fopen($file_name, 'wb');
      $size = fwrite($file, $body);
      fclose( $file );

      if ($size === false) {
        throw new \Exception('There was an issue saving the attachment');
      }
    }
  }

  /**
   * Constructs the file path from the raw headers.
   * @param String $raw_headers
   * @param String $delim Delimiter to be used to split strings.
   * @param Authority $authority The Authority to restrict with.
   * @return String
   */
  private function getFileName($raw_headers, $delim, Authority $authority) {
    // Determines headers.
    $headers = $this->getHeaders($raw_headers, $delim);

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

    return $destination_path . $file_name;
  }

  /**
   * Gets an array of headers using the raw headers and the delimeter.
   * @param String $raw_headers
   * @param String $delim Delimiter to be used to split strings.
   * @return [String => mixed]
   */
  private function getHeaders($raw_headers, $delim) {
    $raw_headers = explode($delim, $raw_headers);
    $headers = [];

    foreach ($raw_headers as $header) {
      list($name, $value) = explode(':', $header);
      $headers[strtolower($name)] = ltrim($value, ' ');
    }

    return $headers;
  }

  /**
   * Gets the extension using the valid content type.
   * @param String $content_type
   * @return String
   */
  private function getExtension($content_type) {
    Helpers::validateAtom(new XAPIIMT($content_type));
    $ext = array_search($content_type, DocumentFiles::$types);
    return $ext;
  }
}
