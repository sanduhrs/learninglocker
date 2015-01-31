<?php namespace Repos\Document\State;

use \Jenssegers\Mongodb\Eloquent\Builder as Builder;
use \Repos\Document\EloquentRepository as DocumentRepository;

class EloquentRepository extends DocumentRepository {
  protected static $document_type = 'state';
  protected static $document_identifier = 'stateId';

  protected static $ap_props = [
    'stateId',
    'activityId',
    'agent',
    'registration'
  ];
  protected static $data_props = [
    'activityId' => null,
    'agent' => null,
    'registration' => null,
    'stateId' => null,
    'since' => null,
    'content_info' => null,
    'method' => 'POST',
    'updated' => null
  ];

  protected function constructIndexQuery(Builder $query, array $data) {
    $query = $query->where('documentType', static::$document_type);
    $query = $query->where('activityId', $data['activityId']);
    $query = $this->whereAgent($query, $data['agent']);
    $query = $this->whereRegistration($query, $data['registration']);
    $query = $this->whereSince($query, $data['since']);

    return $query;
  }

  protected function constructShowQuery(Builder $query, array $data) {
    $query = $query->where('documentType', static::$document_type);
    $query = $query->where('activityId', $data['activityId']);
    $query = $query->where('identId', $data[static::$document_identifier]);
    $query = $this->whereAgent($query, $data['agent']);
    $query = $this->whereRegistration($query, $data['registration']);

    return $query;
  }

  private function checkETag() {
    return; // State API should not check ETags (issues/493).
  }

  protected function validateDestroy(array $data) {
    if (!isset($data['activityId'])) throw new \Exception(
      'Missing activityId'
    );
    return; // State API should allow multiple deletes.
  }
}
