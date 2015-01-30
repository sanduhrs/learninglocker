<?php namespace Repos\Document;

use \Illuminate\Database\Query\Builder as Builder;

interface Repository {
  public function index(Authority $authority, array $data);
  public function show(Authority $authority, array $data);
  public function store(Authority $authority, array $data);
  public function update(Authority $authority, array $data);
  public function destroy(Authority $authority, array $data);
}

abstract class EloquentRepository implements Repository {
  protected static $DOCUMENT_TYPE = '';
  protected static $DOCUMENT_IDENTIFIER = '';
  protected static $AP_PROPS = [];
  protected static $DATA_PROPS = [];

  abstract protected function constructIndexQuery(Builder $query, array $data);
  abstract protected function constructShowQuery(Builder $query, array $data);

  protected function where(Authority $authority) {
    return Document::where('lrs', $authority->getLRS());
  }

  public function index(Authority $authority, array $data) {
    $data = $this->getData($data);
    $query = $this->constructIndexQuery($this->where($authority), $data);
    return $query->get();
  }

  public function show(Authority $authority, array $data) {
    $data = $this->getData($data);
    $query = $this->constructShowQuery($this->where($authority), $data);
    return $query->first();
  }

  public function update(Authority $authority, array $data) {
    $data = $this->getData($data);
    $data['method'] = 'PUT';
    return $this->store($authority, $data, function ($existing_document) use ($data) {
      $this->checkETag(
        isset($existing_document->sha) ? $existing_document->sha : null,
        $data['ifMatch'],
        $data['ifNoneMatch'],
        false
      );
    });
  }

  public function store(Authority $authority, array $data, callable $validator = null) {
    // Gets document and data.
    $data = $this->getData($data);
    $existing_document = $this->show($authority, $data);

    if ($validator !== null) $validator($existing_document);

    // Updates document.
    if ($existing_document === null) {
      $document = new Document;
      $document->lrs = $lrs;
      $document->documentType = static::DOCUMENT_TYPE;
      $document = $this->setActivityProviderProps($document, $data);
    } else {
      $document = $existing_document;
    }

    // Saves document.
    $document->updated_at = new Carbon($updated);
    $document->setContent($data['content_info'], $data['method']);
    $document->save();

    return $document;
  }

  private function getData(array $data) {
    return array_merge(static::DATA_PROPS, $data);
  }

  private function checkETag($sha, $ifMatch, $ifNoneMatch, $noConflict = true) {
    $ifMatch = isset($ifMatch) ? '"'.strtoupper($ifMatch).'"' : null;
    if (isset($ifMatch) && $ifMatch !== $sha) {
      throw new PreconditionException('Precondition (If-Match) failed.');
    } else if (isset($ifNoneMatch) && isset($sha) && $ifNoneMatch === '*') {
      throw new PreconditionException('Precondition (If-None-Match) failed.');
    } else if ($noConflict && $sha !== null && !isset($ifNoneMatch) && !isset($ifMatch)) {
      throw new ConflictException('Check the current state of the resource then set the "If-Match" header with the current ETag to resolve the conflict.');
    }
  }

  private function setActivityProviderProps(Document $document, array $data) {
    foreach (static::AP_PROPS as $prop) {
      $document->{$prop} = $data[$prop];
    }
    return $document;
  }

  private function whereSince(Builder $query, $since) {
    if (empty($since)) return $query;

    $since_carbon = new Carbon($since);
    return $query->where('timestamp', '>', $since_carbon);
  }

  private function whereAgent(Builder $query, array $agent) {
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

  private function whereRegistration(Builder $query, $registration) {
    if (empty($registration)) return $query;
    return $query->where('registration', $registration);
  }
}