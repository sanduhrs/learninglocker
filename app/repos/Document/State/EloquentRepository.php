<?php namespace Repos\Document\State;

use \Illuminate\Database\Query\Builder as Builder;
use \Repos\Document\EloquentRepository as DocumentRepository;

class EloquentRepository extends DocumentRepository {
  protected static $DOCUMENT_TYPE = 'state';
  protected static $DOCUMENT_IDENTIFIER = 'stateId';

  protected static $AP_PROPS = [
    'stateId',
    'activityId',
    'agent',
    'registration'
  ];
  protected static $DATA_PROPS = [
    'activityId' => null,
    'agent' => null,
    'registration' => null,
    'stateId' => null,
    'since' => null,
    'content_info' => null,
    'ifMatch' => null,
    'ifNoneMatch' => null,
    'method' => 'POST'
  ];

  protected function constructIndexQuery(Builder $query, array $data) {
    $query = $query->where('documentType', static::DOCUMENT_TYPE);
    $query = $query->where('activityId', $data['activityId']);
    $query = $this->whereAgent($query, $data['agent']);
    $query = $this->whereRegistration($query, $data['registration']);
    $query = $this->whereSince($query, $data['since']);

    return $query;
  }

  protected function constructShowQuery(Builder $query, array $data) {
    $query = $query->where('documentType', static::DOCUMENT_TYPE);
    $query = $query->where('activityId', $data['activityId']);
    $query = $query->where('identId', $data[static::DOCUMENT_IDENTIFIER]);
    $query = $this->whereAgent($query, $data['agent']);
    $query = $this->whereRegistration($query, $data['registration']);

    return $query;
  }
}