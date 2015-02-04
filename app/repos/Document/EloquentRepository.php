<?php namespace Repos\Document;

use \Jenssegers\Mongodb\Eloquent\Builder as Builder;
use \Models\Authority as Authority;
use \Models\Document as Document;
use \Carbon\Carbon as Carbon;
use \Helpers\Exceptions\Precondition as PreconditionException;
use \Helpers\Exceptions\Conflict as ConflictException;
use \Helpers\Helpers as Helpers;

interface Repository {
  public function index(Authority $authority, array $data);
  public function show(Authority $authority, array $data);
  public function store(Authority $authority, array $data);
  public function update(Authority $authority, array $data);
  public function destroy(Authority $authority, array $data);
}

abstract class EloquentRepository implements Repository {
  protected static $document_type = '';
  protected static $document_identifier = '';
  protected static $ap_props = [];
  protected static $data_props = [];

  abstract protected function constructIndexQuery(Builder $query, array $data);
  abstract protected function constructShowQuery(Builder $query, array $data);

  /**
   * Constructs a query of the documents restricted by the given authority.
   * @param Authority $authority Authority to restrict with.
   * @return Builder
   */
  protected function where(Authority $authority) {
    return Document::where(
      'authority.homePage',
      'like',
      $authority->homePage.'%'
    );
  }

  /**
   * Gets multiple documents using the given data.
   * @param Authority $authority Authority to restrict with.
   * @param AssocArray $data Data from the request.
   * @return [Document]
   */
  public function index(Authority $authority, array $data) {
    return Helpers::replaceHTMLDots($this->indexBuilder($authority, $data)->get()->toArray());
  }

  /**
   * Builds a query to get multiple documents using the given data.
   * @param Authority $authority Authority to restrict with.
   * @param AssocArray $data Data from the request.
   * @return Builder
   */
  protected function indexBuilder(Authority $authority, array $data) {
    $data = $this->getData($data);
    return $this->constructIndexQuery($this->where($authority), $data);
  }

  /**
   * Gets a single document using the given data.
   * @param Authority $authority Authority to restrict with.
   * @param AssocArray $data Data from the request.
   * @return Document
   */
  public function show(Authority $authority, array $data) {
    $document = $this->showBuilder($authority, $data)->first();
    if ($document !== null) {
      $document->content = Helpers::replaceHTMLDots($document->content);
    }
    return $document;
  }

  /**
   * Builds a query to get a single document using the given data.
   * @param Authority $authority Authority to restrict with.
   * @param AssocArray $data Data from the request.
   * @return Builder
   */
  protected function showBuilder(Authority $authority, array $data) {
    $data = $this->getData($data);
    return $this->constructShowQuery($this->where($authority), $data);
  }

  /**
   * Updates (PUTs) a document using the given data.
   * @param Authority $authority Authority to restrict with.
   * @param AssocArray $data Data from the request.
   * @return Document
   */
  public function update(Authority $authority, array $data) {
    $data['method'] = 'PUT';
    return $this->store($authority, $data, function ($existing_document, $data) {
      $this->checkETag(
        isset($existing_document->sha) ? $existing_document->sha : null,
        $data['ifMatch'],
        $data['ifNoneMatch']
      );
    });
  }

  /**
   * Stores (POSTs) a document using the given data.
   * @param Authority $authority Authority to restrict with.
   * @param AssocArray $data Data from the request.
   * @param Callable|null $validator A function to validate the existing document and data with.
   * @return Document
   */
  public function store(Authority $authority, array $data, Callable $validator = null) {
    // Gets document and data.
    $existing_document = $this->show($authority, $data);
    $data = $this->getData($data);

    // Updates document.
    if ($existing_document === null) {
      $document = new Document;
      $document->authority = $authority->homePage.$authority->name;
      $document->documentType = static::$document_type;
      $document = $this->setActivityProviderProps($document, $data);
    } else {
      if ($validator !== null) $validator($existing_document, $data);
      $document = $existing_document;
    }

    // Saves document.
    $updated = isset($data['updated']) ? $data['updated'] : Carbon::now()->toISO8601String();
    $document->updated_at = new Carbon($updated);
    $document->setContent($data['content_info'], $data['method']);
    $document->save();

    return $document;
  }

  /**
   * Destroys the document using the given data.
   * @param Authority $authority Authority to restrict with.
   * @param AssocArray $data Data from the request.
   * @return Boolean
   */
  public function destroy(Authority $authority, array $data) {
    $this->validateDestroy($data);
    $data['since'] = null;

    if (isset($data[static::$document_identifier])) {
      $result = $this->showBuilder($authority, $data);
    } else {
      $result = $this->indexBuilder($authority, $data);
    }

    $documents = $result->get();

    foreach ($documents as $doc) {
      if ($doc->contentType !== 'application/json' && $doc->contentType !== 'text/plain') {
        $path = $doc->getFilePath();
        if (file_exists($path)) {
          unlink($path);
        }
      }
    }

    return $result->delete();
  }

  /**
   * Validates that the document can be destroyed using the given data.
   * @param AssocArray $data Data from the request.
   */
  protected function validateDestroy(array $data) {
    if (!isset($data[static::$document_identifier])) throw new \Exception(
      trans('xapi.errors.multi_delete')
    );
  }

  /**
   * Gets and validates the data.
   * @param AssocArray $data Data from the request.
   * @return AssocArray
   */
  protected function getData(array $data) {
    $data = array_merge(static::$data_props, $data);
    $this->validateData($data);
    return $data;
  }

  abstract protected function validateData(array $data);

  /**
   * Checks the ETag.
   * @param String $sha SHA of the document.
   * @param String $ifMatch If-Match header.
   * @param String $ifNoneMatch If-None-Match header.
   */
  private function checkETag($sha, $ifMatch, $ifNoneMatch) {
    $ifMatch = isset($ifMatch) ? '"'.strtoupper($ifMatch).'"' : null;

    if (isset($ifMatch) && $ifMatch !== $sha) {
      throw new PreconditionException('Precondition (If-Match) failed.');
    } else if (isset($ifNoneMatch) && isset($sha) && $ifNoneMatch === '*') {
      throw new PreconditionException('Precondition (If-None-Match) failed.');
    } else if (isset($sha) && !isset($ifNoneMatch) && !isset($ifMatch)) {
      throw new ConflictException(
        trans('xapi.errors.check_state')
      );
    }
  }

  /**
   * Sets the props on the given document that should have been given by the activity provider.
   * @param Document $document.
   * @param AssocArray $data Data from the request.
   * @return Document
   */
  private function setActivityProviderProps(Document $document, array $data) {
    foreach (static::$ap_props as $prop) {
      $document->{$prop} = $data[$prop];
    }
    return $document;
  }

  /**
   * Extends a query to match the given since.
   * @param Builder $query Query to extend.
   * @param String $since Since to match.
   * @return Builder
   */
  protected function whereSince(Builder $query, $since) {
    if (empty($since)) return $query;

    $since_carbon = new Carbon($since);
    return $query->where('timestamp', '>', $since_carbon);
  }

  /**
   * Extends a query to match the given agent.
   * @param Builder $query Query to extend.
   * @param String $agent Agent to match.
   * @return Builder
   */
  protected function whereAgent(Builder $query, \stdClass $agent) {
    if (empty($agent)) return $query;

    $identifier = Helpers::getAgentIdentifier($agent);

    if ($identifier !== null && $identifier !== 'account') {
      $query->where('agent.'.$identifier, $agent->{$identifier});
    } else if ($identifier === 'account') {
      if (!isset($agent->account->homePage) || !isset($agent->account->name)) {
        throw new \Exception(trans('xapi.errors.missing_account_params'));
      }

      $query->where('agent.account.homePage', $agent->account->homePage);
      $query->where('agent.account.name', $agent->account->name);
    } else {
      throw new \Exception(trans('xapi.errors.missing_agent_params'));
    }

    return $query;
  }

  /**
   * Extends a query to match the given registration.
   * @param Builder $query Query to extend.
   * @param String $registration Registration to match.
   * @return Builder
   */
  protected function whereRegistration(Builder $query, $registration) {
    if (empty($registration)) return $query;
    return $query->where('registration', $registration);
  }
}
