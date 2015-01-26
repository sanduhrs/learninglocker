<?php namespace Controllers\XAPI\Document;

class StateController extends BaseController {
  // Overrides parent's properties.
  protected $identifier = 'stateId';
  protected $required = [
    'activityId' => 'iri',
    'agent' => 'agent',
    'stateId' => 'string'
  ];
  protected $optional = [
    'registration' => 'uuid'
  ];

  public function index(Authority $authority) {
    // Gets all documents.
    $documents = (new StateRepository)->index(
      $authority,
      $this->getIndexData([
        'since' => ['string', 'timestamp']
      ])
    );

    // Returns array of only the stateId values for each document.
    $ids = array_column($documents->toArray(), 'identId');
    return \Response::json($ids);
  }

  /**
   * Returns (GETs) a single document.
   * @return DocumentResponse
   */
  public function show() {
    return $this->documentResponse($this->getShowData());
  }

  /**
   * Creates (POSTs) a new document.
   * @return Response
   */
  public function store() {

    // Checks and gets the data from the params.
    $data = $this->getShowData();

    // Gets the content from the request.
    $data['content_info'] = $this->getAttachedContent('content');
    $data['ifMatch'] = \LockerRequest::header('If-Match');
    $data['ifNoneMatch'] = \LockerRequest::header('If-None-Match');

    // Stores the document.
    $document = $this->document->store(
      $this->lrs->_id,
      $this->document_type,
      $data,
      $this->getUpdatedValue(),
      $this->method
    );

    if ($document) {
      return \Response::json(null, BaseController::NO_CONTENT, [
        'ETag' => $document->sha
      ]);
    } else {
      throw new \Exception('Could not store Document.');
    }
  }

  /**
   * Creates (PUTs) a new document.
   * @return Response
   */
  public function update() {
    return $this->store();
  }

  /**
   * Deletes a document.
   * @return Response
   */
  public function destroy(){
    if (!\LockerRequest::hasParam($this->identifier)) {
      return BaseController::errorResponse('Multiple document DELETE not permitted');
    }
    return $this->completeDelete();
  }
}

