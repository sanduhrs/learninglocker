<?php namespace Controllers\XAPI\Document;

use \LockerRequest as LockerRequest;
use \IlluminateResponse as IlluminateResponse;
use \Input as Input;
use \Controllers\XAPI\BaseController as XAPIController;
use \Carbon\Carbon as Carbon;
use \Helpers\Helpers as Helpers;
use \Locker\XApi\Timestamp as XAPITimestamp;
use \Models\Authority as Authority;

abstract class BaseController extends XAPIController {

  // Defines properties to be set by sub classes.
  protected static $document_identifier = '';
  protected static $document_type = '';
  protected static $document_repo = '';

  public function get() {
    if (LockerRequest::getParam(static::$document_identifier) === null) {
      return $this->index();
    } else {
      return $this->show();
    }
  }

  protected function index() {
    $documents = (new static::$document_repo)->index(
      $this->getAuthority(),
      LockerRequest::getParams()
    );

    // Returns array of stateId's.
    $ids = array_column($documents, static::$document_identifier);
    return IlluminateResponse::json($ids, 200, $this->getCORSHeaders());
  }

  /**
   * Returns (GETs) a single document.
   * @return DocumentResponse
   */
  protected function show() {
    $document = (new static::$document_repo)->show(
      $this->getAuthority(),
      LockerRequest::getParams()
    );

    if ($document === null) return IlluminateResponse::make(null, 404, $this->getCORSHeaders());

    $headers = array_merge($this->getCORSHeaders(), [
      'Updated' => $document->updated_at->toISO8601String(),
      'Content-Type' => $document->contentType,
      'ETag' => $document->sha
    ]);

    if ($this->getMethod() === 'HEAD'){ //Only return headers
      return IlluminateResponse::make(null, 200, $headers);
    } else {
      switch ($document->contentType) {
        case "application/json":
          return IlluminateResponse::json($document->content, 200, $headers);
        case "text/plain":
          return IlluminateResponse::make($document->content, 200, $headers);
        default:
          return IlluminateResponse::download(
            $document->getFilePath(),
            $document->content,
            $headers
          );
      }
    }
  }

  /**
   * Creates (POSTs) a new document.
   * @return Response
   */
  public function store() {
    return $this->insert('POST', function (Authority $authority, array $data) {
      return (new static::$document_repo)->store($authority, $data);
    });
  }

  /**
   * Creates (PUTs) a new document.
   * @return Response
   */
  public function update() {
    return $this->insert('PUT', function (Authority $authority, array $data) {
      return (new static::$document_repo)->update($authority, $data);
    });
  }

  private function insert($method, callable $repository_handler) {
    $data = LockerRequest::getParams();
    $data['content_info'] = $this->getAttachedContent($method, 'content');
    $data['ifMatch'] = LockerRequest::header('If-Match');
    $data['ifNoneMatch'] = LockerRequest::header('If-None-Match');
    $data['updated'] = $this->getUpdatedHeader();

    // Stores the document.
    $document = $repository_handler($this->getAuthority(), $data);

    if ($document !== null) {
      return IlluminateResponse::json(null, 200, array_merge($this->getCORSHeaders(), [
        'ETag' => $document->sha
      ]));
    } else {
      throw new \Exception('Could not store Document.');
    }
  }

  /**
   * Deletes a document.
   * @return Response
   */
  public function destroy() {
    if (LockerRequest::hasParam(static::$document_identifier) !== true) throw new \Exception(
      'Multiple document DELETE not permitted'
    );

    (new static::$document_repo)->destroy(
      $this->getAuthority(),
      LockerRequest::getParams()
    );

    return IlluminateResponse::json(null, 204);
  }

  /**
   * Retrieves attached file content
   * @param string $name Field name
   * @return Array
   */
  protected function getAttachedContent($method, $name = 'content') {
    if (LockerRequest::hasParam('method') || $method === 'POST') {
      return $this->getPostContent($name);
    } else {
      $contentType = LockerRequest::header('Content-Type', 'text/plain');

      return [
        'content' => LockerRequest::getContent(),
        'contentType' => $contentType
      ];
    }
  }

  /**
   * Checks for files, then retrieves the stored param.
   * @param String $name Field name
   * @return Array
   */
  protected function getPostContent($name){
    if (Input::hasFile($name)) {
      $content = Input::file($name);
      $contentType = $content->getClientMimeType();
    } else if (LockerRequest::getContent()) {
      $content = LockerRequest::getContent();

      $contentType = LockerRequest::header('Content-Type');
      $isForm = $this->checkFormContentType($contentType);

      if (!$contentType || $isForm) {
        $contentType = is_object(json_decode($content)) ? 'application/json' : 'text/plain';
      }

    } else {
      throw new \Exception(sprintf('`%s` was not sent in this request', $name));
    }

    return [
      'content' => $content,
      'contentType' => $contentType
    ];
  }

  private function getUpdatedHeader() {
    $updated = LockerRequest::header('Updated', Carbon::now()->toISO8601String());
    Helpers::validateAtom(new XAPITimestamp($updated));
    return $updated;
  }

  /**
   * Determines if $contentType is a form.
   * @param string $contentType
   * @return boolean
   */
  private function checkFormContentType($contentType = '') {
    if (!is_string($contentType)) return false;
    return in_array(explode(';', $contentType)[0], [
      'multipart/form-data',
      'application/x-www-form-urlencoded'
    ]);
  }
}
