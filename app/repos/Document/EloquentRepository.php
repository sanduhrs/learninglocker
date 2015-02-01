<?php namespace Repos\Document;

use \Jenssegers\Mongodb\Eloquent\Builder as Builder;
use \Models\Authority as Authority;
use \Models\Document as Document;
use \Carbon\Carbon as Carbon;
use \Helpers\Exceptions\Precondition as PreconditionException;
use \Helpers\Exceptions\Conflict as ConflictException;

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

  protected function where(Authority $authority) {
    return Document::where('lrs', $authority->getLRS());
  }

  public function index(Authority $authority, array $data) {
    return $this->indexBuilder($authority, $data)->get()->toArray();
  }

  protected function indexBuilder(Authority $authority, array $data) {
    $data = $this->getData($data);
    return $this->constructIndexQuery($this->where($authority), $data);
  }

  public function show(Authority $authority, array $data) {
    return $this->showBuilder($authority, $data)->first();
  }

  protected function showBuilder(Authority $authority, array $data) {
    $data = $this->getData($data);
    return $this->constructShowQuery($this->where($authority), $data);
  }

  public function update(Authority $authority, array $data) {
    $data = $this->getData($data);
    $data['method'] = 'PUT';
    return $this->store($authority, $data, function ($existing_document) use ($data) {
      $this->checkETag(
        isset($existing_document->sha) ? $existing_document->sha : null,
        $data['ifMatch'],
        $data['ifNoneMatch']
      );
    });
  }

  public function store(Authority $authority, array $data, callable $validator = null) {
    // Gets document and data.
    $data = $this->getData($data);
    $existing_document = $this->indexBuilder($authority, $data)->first();

    if ($validator !== null) $validator($existing_document);

    // Updates document.
    if ($existing_document === null) {
      $document = new Document;
      $document->lrs = $authority->getLRS();
      $document->documentType = static::$document_type;
      $document = $this->setActivityProviderProps($document, $data);
    } else {
      $document = $existing_document;
    }

    // Saves document.
    $updated = isset($data['updated']) ? $data['updated'] : Carbon::now()->toISO8601String();
    $document->updated_at = new Carbon($updated);
    $document->setContent($data['content_info'], $data['method']);
    $document->save();

    return $document;
  }

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

  protected function validateDestroy(array $data) {
    if (!isset($data[static::$document_identifier])) throw new \Exception(
      'Multiple document DELETE not permitted'
    );
  }

  private function getData(array $data) {
    $data = array_merge(static::$data_props, $data);
    $this->validateData($data);
    return $data;
  }

  abstract protected function validateData(array $data);

  private function checkETag($sha, $ifMatch, $ifNoneMatch) {
    $ifMatch = isset($ifMatch) ? '"'.strtoupper($ifMatch).'"' : null;

    if (isset($ifMatch) && $ifMatch !== $sha) {
      throw new PreconditionException('Precondition (If-Match) failed.');
    } else if (isset($ifNoneMatch) && isset($sha) && $ifNoneMatch === '*') {
      throw new PreconditionException('Precondition (If-None-Match) failed.');
    } else if ($sha !== null && !isset($ifNoneMatch) && !isset($ifMatch)) {
      throw new ConflictException(
        'Check the current state of the resource then set the "If-Match" header with the current ETag to resolve the conflict.'
      );
    }
  }

  private function setActivityProviderProps(Document $document, array $data) {
    foreach (static::$ap_props as $prop) {
      $document->{$prop} = $data[$prop];
    }
    return $document;
  }

  protected function whereSince(Builder $query, $since) {
    if (empty($since)) return $query;

    $since_carbon = new Carbon($since);
    return $query->where('timestamp', '>', $since_carbon);
  }

  protected function whereAgent(Builder $query, array $agent) {
    if (empty($agent)) return $query;

    $identifier = Helpers::getAgentIdentifier((object) $agent);

    if ($identifier !== null && $identifier !== 'account') {
      $query->where($identifier, $agent[$identifier]);
    } else if ($identifier === 'account') {
      if (!isset($agent['account']['homePage']) || !isset($agent['account']['name'])) {
        throw new \Exception('Missing required paramaters in the agent.account');
      }

      $query->where('agent.account.homePage', $agent['account']['homePage']);
      $query->where('agent.account.name', $agent['account']['name']);
    } else {
      throw new \Exception('Missing required paramaters in the agent');
    }

    return $query;
  }

  protected function whereRegistration(Builder $query, $registration) {
    if (empty($registration)) return $query;
    return $query->where('registration', $registration);
  }
}
