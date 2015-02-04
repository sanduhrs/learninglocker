<?php namespace Controllers\XAPI\Document;

use \LockerRequest as LockerRequest;
use \IlluminateResponse as IlluminateResponse;
use \Input as Input;
use \Controllers\XAPI\BaseController as XAPIController;
use \Models\Authority as Authority;
use \Carbon\Carbon as Carbon;
use \Helpers\Helpers as Helpers;
use \Helpers\Exceptions\Precondition as PreconditionException;
use \Helpers\Exceptions\Conflict as ConflictException;
use \Locker\XApi\Timestamp as XAPITimestamp;

abstract class BaseController extends XAPIController {

  // Defines properties to be set by sub classes.
  protected static $document_identifier = '';
  protected static $document_type = '';
  protected static $document_repo = '';

  /**
   * Calls either index or show depending on whether the document identifier was given in the request.
   * @return \Illuminate\Http\ResponseTrait Result of the index/show.
   */
  public function get() {
    if (LockerRequest::getParam(static::$document_identifier) === null) {
      return $this->index();
    } else {
      return $this->show();
    }
  }

  /**
   * GETs all of the documents that fulfil the given request parameters.
   * @return \Illuminate\Http\JsonResponse Response containing the IDs of the documents fulfilling the parameters.
   */
  protected function index() {
    $documents = (new static::$document_repo)->index(
      $this->getAuthority(),
      LockerRequest::getParams()
    );

    // Returns array of Document ID's.
    $ids = array_map(function ($document) {
      return $document->{static::$document_identifier};
    }, $documents);
    return IlluminateResponse::json($ids, 200, $this->getCORSHeaders());
  }

  /**
   * GETs a single document that fulfils the given request parameters.
   * @return \Illuminate\Http\ResponseTrait Document.
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
   * Stores (POSTs) a document.
   * @return \Illuminate\Http\ResponseTrait Result of storing the document.
   */
  public function store() {
    return $this->insert('POST', function (Authority $authority, array $data) {
      $document = (new static::$document_repo)->store($authority, $data);
      return IlluminateResponse::make('', 204, array_merge($this->getCORSHeaders(), [
        'ETag' => $document->sha
      ]));
    });
  }

  /**
   * Updates (PUTs) a document.
   * @return \Illuminate\Http\ResponseTrait Result of updating the document.
   */
  public function update() {
    return $this->insert('PUT', function (Authority $authority, array $data) {
      $document = (new static::$document_repo)->update($authority, $data);
      return IlluminateResponse::make('', 204, array_merge($this->getCORSHeaders(), [
        'ETag' => $document->sha
      ]));
    });
  }

  /**
   * Inserts a document.
   * @param String $method HTTP method used in the request.
   * @param Callable $repository_handler A function that stores the documents and returns a response.
   * @return \Illuminate\Http\ResponseTrait Result of inserting the document.
   */
  private function insert($method, Callable $repository_handler) {
    $data = LockerRequest::getParams();
    $data['content_info'] = $this->getAttachedContent($method, 'content');
    $data['ifMatch'] = LockerRequest::header('If-Match');
    $data['ifNoneMatch'] = LockerRequest::header('If-None-Match');
    $data['updated'] = $this->getUpdatedHeader();

    // Stores the document.
    try {
      return $repository_handler($this->getAuthority(), $data);
    } catch (PreconditionException $ex) {
      return $this->errorResponse($ex, 412);
    } catch (ConflictException $ex) {
      return $this->errorResponse($ex, 409);
    }
  }

  /**
   * DELETEs a document.
   * @return \Illuminate\Http\ResponseTrait Result of deleting the document.
   */
  public function destroy() {
    (new static::$document_repo)->destroy(
      $this->getAuthority(),
      LockerRequest::getParams()
    );

    return IlluminateResponse::make('', 204, $this->getCORSHeaders());
  }

  /**
   * Gets the attached file content from the request.
   * @param String $method HTTP method used in the request.
   * @param String $name Field to be retrieved.
   * @return AssocArray Contains the content and the contentType.
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
   * Gets the POSTed content.
   * @param String $name Field to be retrieved.
   * @return AssocArray Contains the content and the contentType.
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
      throw new \Exception(trans('xapi.errors.unset_param', [
        'field' => $name
      ]));
    }

    return [
      'content' => $content,
      'contentType' => $contentType
    ];
  }

  /**
   * Gets and validates the 'Updated' header from the request.
   * @return String Updated header as a XAPITimestamp.
   */
  private function getUpdatedHeader() {
    $updated = LockerRequest::header('Updated', Carbon::now()->toISO8601String());
    Helpers::validateAtom(new XAPITimestamp($updated));
    return $updated;
  }

  /**
   * Determines if the given contentType is a form.
   * @param String $contentType
   * @return Boolean True if the contentType is a form.
   */
  private function checkFormContentType($contentType = '') {
    if (!is_string($contentType)) return false;
    return in_array(explode(';', $contentType)[0], [
      'multipart/form-data',
      'application/x-www-form-urlencoded'
    ]);
  }
}
